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
 * Attach CBC multi-parameters to whichever CSV test matches CBC.
 */
function seed_cbc_parameters_for_imported_catalog(PDO $pdo, string $orgId = 'ORG-001'): ?string
{
    $row = $pdo->prepare(
        "SELECT id FROM tests
         WHERE organization_id = :org
           AND (UPPER(code) IN ('CBC','100') OR name LIKE '%Complete Blood Count%' OR name LIKE 'CBC (%')
         ORDER BY CASE WHEN UPPER(code)='CBC' THEN 0 WHEN UPPER(code)='100' THEN 1 ELSE 2 END
         LIMIT 1"
    );
    $row->execute(['org' => $orgId]);
    $test = $row->fetch(PDO::FETCH_ASSOC);
    if (!$test) {
        return null;
    }
    $testId = (string)$test['id'];

    $pdo->prepare('DELETE FROM test_parameters WHERE test_id = :id')->execute(['id' => $testId]);

    $cbcParams = [
        ['ERYTHROCYTES', 'Hemoglobin (HB)', 'g/dl', '12.0 - 16.5', "New born (HB): 15.2 - 23.5\nBaby (HB): 10.1 - 12.8\nInfant (HB): 10.8 - 12.9\nChildren (HB): 11.1 - 14.3", 1],
        ['ERYTHROCYTES', 'Total RBCs', 'X10^12/L', '4.50 - 6.50', null, 2],
        ['ABSOLUTE VALUES', 'HCT (Hematocrit)', '%', '38.0 - 52.0', null, 3],
        ['ABSOLUTE VALUES', 'MCV', 'Fl', '75.0 - 95.0', null, 4],
        ['ABSOLUTE VALUES', 'MCH', 'Pg', '27.0 - 32.0', null, 5],
        ['ABSOLUTE VALUES', 'MCHC', 'g/dl', '30.0 - 35.0', null, 6],
        ['THROMBOCYTE', 'Platelet Count', 'x10^3/µL', '150 - 400', null, 7],
        ['Differential Leukocytes Count', 'WBC (TLC)', 'K/uL', '4.0 - 11.0', null, 8],
        ['Differential Leukocytes Count', 'Neutrophils', '%', '40 - 75', null, 9],
        ['Differential Leukocytes Count', 'Lymphocytes', '%', '20 - 50', null, 10],
        ['Differential Leukocytes Count', 'Monocytes', '%', '02 - 10', null, 11],
        ['Differential Leukocytes Count', 'Eosinophils', '%', '01 - 06', null, 12],
        ['Differential Leukocytes Count', 'ESR', 'mm/1stHr', '0 - 18', null, 13],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO test_parameters (id, test_id, section, name, unit, normal_value, reference_range, sub_table, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($cbcParams as $i => $p) {
        $stmt->execute([
            'PRM-CBC-' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT),
            $testId,
            $p[0],
            $p[1],
            $p[2],
            $p[3],
            $p[3],
            $p[4],
            $p[5],
        ]);
    }

    return $testId;
}
