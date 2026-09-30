<?php

declare(strict_types=1);

/**
 * Import lab tests from tests.csv (project root).
 * Clears existing tests + parameters for the org, then inserts CSV rows.
 *
 * @return array{count:int, logs:string[]}
 */
function import_tests_from_csv(PDO $pdo, string $csvPath, string $orgId = 'ORG-001'): array
{
    $logs = [];
    if (!is_file($csvPath)) {
        throw new RuntimeException("tests.csv not found at {$csvPath}");
    }

    $handle = fopen($csvPath, 'rb');
    if ($handle === false) {
        throw new RuntimeException("Could not open tests.csv");
    }

    // Strip UTF-8 BOM if present
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        throw new RuntimeException('tests.csv is empty');
    }
    $header = array_map(static fn($h) => trim((string)$h), $header);

    $rows = [];
    while (($data = fgetcsv($handle)) !== false) {
        if ($data === [null] || $data === false) {
            continue;
        }
        if (count($data) === 1 && trim((string)$data[0]) === '') {
            continue;
        }
        $assoc = [];
        foreach ($header as $i => $key) {
            $assoc[$key] = isset($data[$i]) ? trim((string)$data[$i]) : '';
        }
        $name = normalize_test_name($assoc['Name'] ?? '');
        if ($name === '') {
            continue;
        }
        $rows[] = $assoc;
    }
    fclose($handle);

    $logs[] = 'Loaded ' . count($rows) . ' rows from tests.csv';

    // Wipe existing catalog for this org
    $pdo->exec('DELETE tp FROM test_parameters tp INNER JOIN tests t ON t.id = tp.test_id WHERE t.organization_id = ' . $pdo->quote($orgId));
    $pdo->prepare('DELETE FROM tests WHERE organization_id = :org')->execute(['org' => $orgId]);
    $logs[] = 'Cleared existing tests and parameters.';

    $stmt = $pdo->prepare(
        'INSERT INTO tests (id, organization_id, code, name, category, price, sample_type, unit, normal_range)
         VALUES (:id, :org_id, :code, :name, :category, :price, :sample_type, :unit, :normal_range)'
    );

    $usedCodes = [];
    $inserted = 0;
    $index = 1;

    foreach ($rows as $row) {
        $name = normalize_test_name($row['Name'] ?? '');
        if ($name === '') {
            continue;
        }

        $rawCode = trim((string)($row['Test Code'] ?? ''));
        $code = normalize_test_code($rawCode, $name, $index, $usedCodes);
        $usedCodes[strtoupper($code)] = true;

        $category = normalize_test_category((string)($row['Category'] ?? ''));
        $specimen = normalize_specimen((string)($row['Specimen'] ?? ''));
        $price = parse_pkr_price((string)($row['Your Price'] ?? ''), (string)($row['Base Price'] ?? ''));

        $id = sprintf('TST-%04d', $index);
        $stmt->execute([
            'id' => $id,
            'org_id' => $orgId,
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'price' => $price,
            'sample_type' => $specimen,
            'unit' => '—',
            'normal_range' => '—',
        ]);
        $inserted++;
        $index++;
    }

    $logs[] = "Imported {$inserted} tests from tests.csv.";
    return ['count' => $inserted, 'logs' => $logs];
}

function normalize_test_name(string $name): string
{
    // Remove emoji / symbols used in export labels
    $name = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $name) ?? $name;
    $name = preg_replace('/\s+/', ' ', $name) ?? $name;
    return trim($name);
}

/**
 * @param array<string,bool> $usedCodes
 */
function normalize_test_code(string $rawCode, string $name, int $index, array $usedCodes): string
{
    $code = strtoupper(trim($rawCode));
    if ($code === '' || $code === '-' || $code === 'N/A' || $code === 'NA') {
        $code = slug_test_code($name);
    }
    // Fit VARCHAR(32)
    $code = substr($code, 0, 28);
    if ($code === '') {
        $code = 'T' . $index;
    }
    $base = $code;
    $n = 2;
    while (isset($usedCodes[strtoupper($code)])) {
        $suffix = '-' . $n;
        $code = substr($base, 0, 32 - strlen($suffix)) . $suffix;
        $n++;
    }
    return $code;
}

function slug_test_code(string $name): string
{
    $slug = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', $name) ?? '');
    $slug = trim($slug, '-');
    if (strlen($slug) > 20) {
        $slug = substr($slug, 0, 20);
        $slug = rtrim($slug, '-');
    }
    return $slug !== '' ? $slug : 'TEST';
}

function normalize_test_category(string $category): string
{
    $c = strtolower(trim($category));
    $c = preg_replace('/\s+/', ' ', $c) ?? $c;

    $map = [
        'department of special chemistry' => 'Special Chemistry',
        'special chemistry' => 'Special Chemistry',
        'biochemistry' => 'Biochemistry',
        'department of chemical pathology' => 'Chemical Pathology',
        'chemical pathology' => 'Chemical Pathology',
        'department of microbiology' => 'Microbiology',
        'hematology' => 'Hematology',
        'haematology' => 'Hematology',
        'serology' => 'Serology',
        'histopathology' => 'Histopathology',
        'pathology' => 'Pathology',
        'report profile' => 'Report Profile',
        'department of radology' => 'Radiology',
        'department of radiology' => 'Radiology',
        'department of molecular biology' => 'Molecular',
        'molecular diagnostic section' => 'Molecular',
        'department of molecular virology' => 'Molecular',
        'natriuretic peptide test' => 'Special Chemistry',
        'tumor marker report' => 'Special Chemistry',
    ];

    return $map[$c] ?? (ucwords($category) ?: 'General');
}

function normalize_specimen(string $specimen): string
{
    $s = trim($specimen);
    if ($s === '' || $s === '.' || $s === '-') {
        return '—';
    }
    return ucwords(strtolower($s));
}

function parse_pkr_price(string $yourPrice, string $basePrice = ''): float
{
    foreach ([$yourPrice, $basePrice] as $raw) {
        if (preg_match('/([\d,]+(?:\.\d+)?)/', $raw, $m)) {
            return (float)str_replace(',', '', $m[1]);
        }
    }
    return 0.0;
}

/**
 * Attach multi-parameter panels (CBC, LFT, Lipid, RFT, TFT) to matching catalog tests.
 *
 * @return array<string,string> map of panel key => test id
 */
function seed_panel_parameters_for_imported_catalog(PDO $pdo, string $orgId = 'ORG-001'): array
{
    $panels = [
        'CBC' => [
            'match' => "UPPER(code) IN ('CBC','100') OR name LIKE '%Complete Blood Count%' OR name LIKE 'CBC (%'",
            'order' => "CASE WHEN UPPER(code)='CBC' THEN 0 WHEN UPPER(code)='100' THEN 1 WHEN name LIKE '%Complete Blood Count%' THEN 2 ELSE 3 END",
            'exclude' => "name NOT LIKE 'CBC For%'",
            'params' => [
                ['ERYTHROCYTES', 'Hemoglobin (HB)', 'g/dl', '12.0 - 16.5', "New born (HB): 15.2 - 23.5\nBaby (HB): 10.1 - 12.8\nInfant (HB): 10.8 - 12.9\nChildren (HB): 11.1 - 14.3"],
                ['ERYTHROCYTES', 'Total RBCs', 'X10^12/L', '4.50 - 6.50', null],
                ['ABSOLUTE VALUES', 'HCT (Hematocrit)', '%', '38.0 - 52.0', null],
                ['ABSOLUTE VALUES', 'MCV', 'Fl', '75.0 - 95.0', null],
                ['ABSOLUTE VALUES', 'MCH', 'Pg', '27.0 - 32.0', null],
                ['ABSOLUTE VALUES', 'MCHC', 'g/dl', '30.0 - 35.0', null],
                ['THROMBOCYTE', 'Platelet Count', 'x10^3/µL', '150 - 400', null],
                ['Differential Leukocytes Count', 'WBC (TLC)', 'K/uL', '4.0 - 11.0', null],
                ['Differential Leukocytes Count', 'Neutrophils', '%', '40 - 75', null],
                ['Differential Leukocytes Count', 'Lymphocytes', '%', '20 - 50', null],
                ['Differential Leukocytes Count', 'Monocytes', '%', '02 - 10', null],
                ['Differential Leukocytes Count', 'Eosinophils', '%', '01 - 06', null],
                ['Differential Leukocytes Count', 'ESR', 'mm/1stHr', '0 - 18', null],
            ],
        ],
        'LFT' => [
            'match' => "UPPER(code) IN ('LFT','LFTS') OR name LIKE 'LFTs (%' OR name LIKE '%Liver Function%'",
            'order' => "CASE WHEN name LIKE '%Liver Function%' THEN 0 WHEN name LIKE 'LFTs (%' THEN 1 ELSE 2 END",
            'exclude' => '1=1',
            'params' => [
                ['LIVER FUNCTION', 'Bilirubin Total', 'mg/dl', '0.1 - 1.2', null],
                ['LIVER FUNCTION', 'Bilirubin Direct', 'mg/dl', '0.0 - 0.3', null],
                ['LIVER FUNCTION', 'Bilirubin Indirect', 'mg/dl', '0.1 - 1.0', null],
                ['LIVER FUNCTION', 'SGPT (ALT)', 'U/L', '10 - 40', null],
                ['LIVER FUNCTION', 'SGOT (AST)', 'U/L', '10 - 40', null],
                ['LIVER FUNCTION', 'Alkaline Phosphatase (ALP)', 'U/L', '40 - 129', null],
                ['LIVER FUNCTION', 'Gamma GT (GGT)', 'U/L', '5 - 40', null],
                ['LIVER FUNCTION', 'Total Protein', 'g/dl', '6.0 - 8.3', null],
                ['LIVER FUNCTION', 'Albumin', 'g/dl', '3.5 - 5.2', null],
                ['LIVER FUNCTION', 'Globulin', 'g/dl', '2.0 - 3.5', null],
                ['LIVER FUNCTION', 'A/G Ratio', '', '1.0 - 2.5', null],
            ],
        ],
        'LIPID' => [
            'match' => "UPPER(code) IN ('LIPID','LP') OR name LIKE '%LIPID PROFILE%' OR name LIKE '%Lipid Profile%'",
            'order' => "CASE WHEN name LIKE '%LIPID PROFILE%' OR name LIKE '%Lipid Profile%' THEN 0 ELSE 1 END",
            'exclude' => '1=1',
            'params' => [
                ['LIPID PROFILE', 'Cholesterol Total', 'mg/dl', '< 200', null],
                ['LIPID PROFILE', 'Triglycerides', 'mg/dl', '< 150', null],
                ['LIPID PROFILE', 'HDL Cholesterol', 'mg/dl', '> 40', null],
                ['LIPID PROFILE', 'LDL Cholesterol', 'mg/dl', '< 100', null],
                ['LIPID PROFILE', 'VLDL Cholesterol', 'mg/dl', '5 - 40', null],
                ['LIPID PROFILE', 'Cholesterol / HDL Ratio', '', '< 5.0', null],
            ],
        ],
        'RFT' => [
            'match' => "UPPER(code) IN ('RFT','RFTS','200') OR name LIKE 'RFTs (%' OR name LIKE '%Renal Function%'",
            'order' => "CASE WHEN name LIKE '%Renal Function%' THEN 0 WHEN UPPER(code)='200' THEN 1 ELSE 2 END",
            'exclude' => '1=1',
            'params' => [
                ['RENAL FUNCTION', 'Urea', 'mg/dl', '15 - 40', null],
                ['RENAL FUNCTION', 'Creatinine', 'mg/dl', '0.6 - 1.3', null],
                ['RENAL FUNCTION', 'Uric Acid', 'mg/dl', '3.5 - 7.2', null],
                ['RENAL FUNCTION', 'Sodium (Na)', 'mmol/L', '136 - 145', null],
                ['RENAL FUNCTION', 'Potassium (K)', 'mmol/L', '3.5 - 5.1', null],
                ['RENAL FUNCTION', 'Chloride (Cl)', 'mmol/L', '98 - 107', null],
                ['RENAL FUNCTION', 'BUN', 'mg/dl', '7 - 20', null],
            ],
        ],
        'TFT' => [
            'match' => "UPPER(code) IN ('TFT','TFTS') OR name LIKE 'TFTs (%' OR name LIKE '%Thyroid Function%'",
            'order' => "CASE WHEN name LIKE '%Thyroid Function%HC%' OR name LIKE 'TFTs (%HC%' THEN 0 WHEN name LIKE '%Thyroid Function%' THEN 1 ELSE 2 END",
            'exclude' => '1=1',
            'params' => [
                ['THYROID', 'T3 (Triiodothyronine)', 'ng/ml', '0.8 - 2.0', null],
                ['THYROID', 'T4 (Thyroxine)', 'µg/dl', '5.1 - 14.1', null],
                ['THYROID', 'TSH', 'µIU/ml', '0.4 - 4.0', null],
            ],
        ],
        'UCE' => [
            'match' => "UPPER(code) IN ('UCE','URE','UA') OR name LIKE '%Urine Complete%' OR name LIKE '%URINE COMPLETE%' OR name LIKE 'UCE (%'",
            'order' => "CASE WHEN name LIKE '%Urine Complete%' THEN 0 WHEN UPPER(code)='UCE' THEN 1 ELSE 2 END",
            'exclude' => "name NOT LIKE '%Pregnancy%' AND name NOT LIKE '%Culture%' AND name NOT LIKE '%C/S%'",
            'params' => [
                ['PHYSICAL EXAMINATION', 'Color', '', 'Pale Yellow', null],
                ['PHYSICAL EXAMINATION', 'Appearance', '', 'Clear', null],
                ['PHYSICAL EXAMINATION', 'Specific Gravity', '', '1.005 - 1.030', null],
                ['PHYSICAL EXAMINATION', 'pH', '', '5.0 - 8.0', null],
                ['CHEMICAL EXAMINATION', 'Protein', '', 'Negative / Positive / Trace', null],
                ['CHEMICAL EXAMINATION', 'Glucose', '', 'Negative / Positive / Trace', null],
                ['CHEMICAL EXAMINATION', 'Ketones', '', 'Negative / Positive', null],
                ['CHEMICAL EXAMINATION', 'Blood', '', 'Negative / Positive / Trace', null],
                ['CHEMICAL EXAMINATION', 'Bilirubin', '', 'Negative / Positive', null],
                ['CHEMICAL EXAMINATION', 'Urobilinogen', '', 'Normal / Increased', null],
                ['CHEMICAL EXAMINATION', 'Nitrite', '', 'Negative / Positive', null],
                ['CHEMICAL EXAMINATION', 'Leukocyte Esterase', '', 'Negative / Positive', null],
                ['MICROSCOPIC EXAMINATION', 'RBCs', '/HPF', '0 - 2', null],
                ['MICROSCOPIC EXAMINATION', 'WBCs', '/HPF', '0 - 5', null],
                ['MICROSCOPIC EXAMINATION', 'Epithelial Cells', '/HPF', 'Few / Moderate / Many', null],
                ['MICROSCOPIC EXAMINATION', 'Casts', '/LPF', 'Nil / Occasional', null],
                ['MICROSCOPIC EXAMINATION', 'Crystals', '', 'Nil', null],
                ['MICROSCOPIC EXAMINATION', 'Bacteria', '', 'Nil / Few / Many', null],
                ['MICROSCOPIC EXAMINATION', 'Yeast', '', 'Nil', null],
                ['MICROSCOPIC EXAMINATION', 'Mucus Threads', '', 'Nil / Present', null],
            ],
        ],
        'BREAST_MILK' => [
            'match' => "name LIKE '%Breast Milk%' OR name LIKE '%MILK CE%' OR name LIKE 'MILK CE (%' OR (name LIKE '%Milk%' AND name LIKE '%Complete%')",
            'order' => "CASE WHEN name LIKE '%MILK CE%' THEN 0 WHEN name LIKE '%Breast Milk%' THEN 1 ELSE 2 END",
            'exclude' => "name NOT LIKE '%Abscess%'",
            'params' => [
                ['PHYSICAL', 'Color', '', 'White / Creamy', null],
                ['PHYSICAL', 'Appearance', '', 'Homogenous', null],
                ['PHYSICAL', 'Odour', '', 'Normal', null],
                ['CHEMICAL', 'pH', '', '6.5 - 7.5', null],
                ['CHEMICAL', 'Specific Gravity', '', '1.026 - 1.036', null],
                ['CHEMICAL', 'Protein', 'g/dl', '0.9 - 1.2', null],
                ['CHEMICAL', 'Fat', 'g/dl', '3.0 - 5.0', null],
                ['CHEMICAL', 'Lactose', 'g/dl', '6.0 - 7.5', null],
                ['MICROSCOPIC', 'Pus Cells', '/HPF', '0 - 5', null],
                ['MICROSCOPIC', 'RBCs', '/HPF', '0 - 2', null],
                ['MICROSCOPIC', 'Epithelial Cells', '/HPF', 'Few', null],
                ['MICROSCOPIC', 'Bacteria', '', 'Nil / Present', null],
                ['MICROSCOPIC', 'Fat Globules', '', 'Present', null],
            ],
        ],
        'SEMEN' => [
            'match' => "UPPER(code) IN ('SEMEN','SA') OR name LIKE 'SEMEN ANALYSIS%' OR name LIKE '%Semen Analysis%'",
            'order' => "CASE WHEN name LIKE 'SEMEN ANALYSIS%' THEN 0 ELSE 1 END",
            'exclude' => "name NOT LIKE '%Culture%' AND name NOT LIKE '%C/S%' AND name NOT LIKE '%FOR CS%'",
            'params' => [
                ['MACROSCOPIC', 'Volume', 'ml', '1.5 - 5.0', null],
                ['MACROSCOPIC', 'Color', '', 'Grey-white', null],
                ['MACROSCOPIC', 'Viscosity', '', 'Normal / Increased', null],
                ['MACROSCOPIC', 'Liquefaction Time', 'min', '15 - 60', null],
                ['MACROSCOPIC', 'pH', '', '7.2 - 8.0', null],
                ['MICROSCOPIC', 'Sperm Count', 'million/ml', '15 - 200', null],
                ['MICROSCOPIC', 'Total Motility', '%', '40 - 100', null],
                ['MICROSCOPIC', 'Progressive Motility', '%', '32 - 100', null],
                ['MICROSCOPIC', 'Non-Progressive', '%', '—', null],
                ['MICROSCOPIC', 'Immotile', '%', '—', null],
                ['MICROSCOPIC', 'Normal Morphology', '%', '4 - 100', null],
                ['MICROSCOPIC', 'Vitality', '%', '58 - 100', null],
                ['MICROSCOPIC', 'WBCs', '/HPF', '0 - 5', null],
                ['MICROSCOPIC', 'Agglutination', '', 'Absent / Present', null],
            ],
        ],
        'PUS_CS' => [
            'match' => "name LIKE '%PUS FOR CS%' OR name LIKE '%Pus Culture%' OR name LIKE '%Pus for C/S%' OR (name LIKE '%Pus%' AND (name LIKE '%C/S%' OR name LIKE '%Culture%'))",
            'order' => "CASE WHEN name LIKE '%PUS FOR CS%' THEN 0 WHEN name LIKE '%Pus Culture%' THEN 1 ELSE 2 END",
            'exclude' => '1=1',
            'params' => [
                ['CULTURE', 'Specimen', '', 'Pus', null],
                ['CULTURE', 'Culture Growth', '', 'No Growth / Growth Seen', null],
                ['CULTURE', 'Organism Isolated', '', '—', null],
                ['CULTURE', 'Gram Stain', '', '—', null],
                ['ANTIBIOTICS', 'Amikacin', '', 'S / I / R', null],
                ['ANTIBIOTICS', 'Amoxicillin', '', 'S / I / R', null],
                ['ANTIBIOTICS', 'Ampicillin', '', 'S / I / R', null],
                ['ANTIBIOTICS', 'Azithromycin', '', 'S / I / R', null],
                ['ANTIBIOTICS', 'Cefixime', '', 'S / I / R', null],
                ['ANTIBIOTICS', 'Ceftriaxone', '', 'S / I / R', null],
                ['ANTIBIOTICS', 'Ciprofloxacin', '', 'S / I / R', null],
                ['ANTIBIOTICS', 'Co-Amoxiclav', '', 'S / I / R', null],
                ['ANTIBIOTICS', 'Doxycycline', '', 'S / I / R', null],
                ['ANTIBIOTICS', 'Gentamicin', '', 'S / I / R', null],
                ['ANTIBIOTICS', 'Imipenem', '', 'S / I / R', null],
                ['ANTIBIOTICS', 'Levofloxacin', '', 'S / I / R', null],
                ['ANTIBIOTICS', 'Linezolid', '', 'S / I / R', null],
                ['ANTIBIOTICS', 'Meropenem', '', 'S / I / R', null],
                ['ANTIBIOTICS', 'Vancomycin', '', 'S / I / R', null],
            ],
        ],
        'HCV_PCR_QL' => [
            'match' => "name LIKE '%HCV%PCR%QUAL%' OR name LIKE '%HCV PCR QL%' OR name LIKE '%HCV RNA BY PCR (QL)%' OR UPPER(code) IN ('HCVPCRQL','302')",
            'order' => "CASE WHEN name LIKE '%QUAL%' THEN 0 ELSE 1 END",
            'exclude' => "name NOT LIKE '%QUANT%'",
            'params' => [
                ['MOLECULAR', 'Test Method', '', 'Real-Time PCR', null],
                ['MOLECULAR', 'Specimen', '', 'Serum / Plasma', null],
                ['MOLECULAR', 'HCV RNA', '', 'Detected / Not Detected', null],
                ['MOLECULAR', 'Interpretation', '', '—', null],
            ],
        ],
        'HCV_PCR_QN' => [
            'match' => "name LIKE '%HCV%PCR%QUANT%' OR name LIKE '%HCV PCR QN%' OR name LIKE '%HCV BY PCR QUANTITATION%' OR UPPER(code) IN ('HCVPCRQN','303')",
            'order' => "CASE WHEN name LIKE '%QUANT%' THEN 0 ELSE 1 END",
            'exclude' => "name NOT LIKE '%QUAL%'",
            'params' => [
                ['MOLECULAR', 'Test Method', '', 'Real-Time PCR', null],
                ['MOLECULAR', 'Specimen', '', 'Serum / Plasma', null],
                ['MOLECULAR', 'HCV Viral Load', 'IU/ml', '—', null],
                ['MOLECULAR', 'Log Value', 'log10', '—', null],
                ['MOLECULAR', 'Interpretation', '', '—', null],
            ],
        ],
    ];

    $attached = [];
    $stmt = $pdo->prepare(
        'INSERT INTO test_parameters (id, test_id, section, name, unit, normal_value, reference_range, sub_table, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($panels as $key => $panel) {
        $sql = "SELECT id, name FROM tests
                WHERE organization_id = :org
                  AND ({$panel['match']})
                  AND ({$panel['exclude']})
                ORDER BY {$panel['order']}
                LIMIT 1";
        $q = $pdo->prepare($sql);
        $q->execute(['org' => $orgId]);
        $test = $q->fetch(PDO::FETCH_ASSOC);
        if (!$test) {
            continue;
        }
        $testId = (string)$test['id'];
        $pdo->prepare('DELETE FROM test_parameters WHERE test_id = :id')->execute(['id' => $testId]);

        foreach ($panel['params'] as $i => $p) {
            $stmt->execute([
                'PRM-' . $key . '-' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT),
                $testId,
                $p[0],
                $p[1],
                $p[2],
                $p[3],
                $p[3],
                $p[4],
                $i + 1,
            ]);
        }
        $attached[$key] = $testId . ' (' . $test['name'] . ')';
    }

    return $attached;
}

/** @deprecated use seed_panel_parameters_for_imported_catalog */
function seed_cbc_parameters_for_imported_catalog(PDO $pdo, string $orgId = 'ORG-001'): ?string
{
    $attached = seed_panel_parameters_for_imported_catalog($pdo, $orgId);
    if (empty($attached['CBC'])) {
        return null;
    }
    // Return bare id for older callers
    if (preg_match('/^(TST-\S+)/', $attached['CBC'], $m)) {
        return $m[1];
    }
    $parts = explode(' ', $attached['CBC'], 2);
    return $parts[0] ?: null;
}
