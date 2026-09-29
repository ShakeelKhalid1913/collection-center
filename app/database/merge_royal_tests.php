<?php

declare(strict_types=1);

/**
 * Merge unique tests from royal_laboratory_price_list.csv into the catalog.
 * Does NOT wipe existing tests — skips names that already exist (fuzzy match).
 *
 * Browser: /database/merge_royal_tests.php
 * CLI:     php app/database/merge_royal_tests.php
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/import_tests_csv.php';

function royal_normalize_key(string $name): string
{
    $n = normalize_test_name($name);
    $n = strtoupper($n);
    $n = str_replace(['&', '/', '\\', '-', '_', '.', ',', ':', ';'], ' ', $n);
    $n = preg_replace('/\s+/', ' ', $n) ?? $n;
    $n = trim($n);

    // Leading acronym: "CBC (Complete Blood Count)", "FBS (Fasting…)", "HB (Hemoglobin)"
    if (preg_match('/^([A-Z0-9]{2,12})\s*\(/', $n, $m)) {
        $abbr = $m[1];
        if (!in_array($abbr, ['ANTI', 'SERUM', 'TOTAL', 'FREE', 'SPOT', 'URINE', 'STOOL'], true)) {
            return royal_alias_key($abbr);
        }
    }

    // Trailing acronym: "COMPLETE BLOOD COUNT (CBC)", "HEMAGLOBIN(HB)", "LIVER…(LFTs)"
    if (preg_match('/\(([A-Z0-9]{2,12})\)\s*$/', $n, $m)) {
        return royal_alias_key($m[1]);
    }
    // Acronym somewhere in the name: "LECTATE DEHYDROGENASE (LDH) REPORT"
    if (preg_match('/\(([A-Z0-9]{2,12})\)/', $n, $m)) {
        $abbr = $m[1];
        if (!in_array($abbr, ['SERUM', 'URINE', 'STOOL', 'FLUID', 'TOTAL', 'FREE', 'SPOT', 'ELISA', 'AIDS'], true)) {
            return royal_alias_key($abbr);
        }
    }

    // Strip parentheses content for remaining text key
    $plain = preg_replace('/\([^)]*\)/', ' ', $n) ?? $n;
    $plain = preg_replace('/\s+/', ' ', $plain) ?? $plain;
    $plain = trim($plain);

    return royal_alias_key($plain !== '' ? $plain : $n);
}

function royal_alias_key(string $key): string
{
    $key = strtoupper(trim($key));
    $key = preg_replace('/\s+/', ' ', $key) ?? $key;

    static $aliases = [
        'CBC' => 'CBC',
        'COMPLETE BLOOD COUNT' => 'CBC',
        'COMPLETE BLOOD COUNT CBC' => 'CBC',
        'CBC COMPLETE BLOOD COUNT' => 'CBC',
        'COMPLETE BLOOD COUNT DENGU' => 'CBC DENGUE',
        'CBC FOR DENGUE' => 'CBC DENGUE',
        'CBC DENGUE' => 'CBC DENGUE',
        'DENGU' => 'CBC DENGUE',
        'DENGUE' => 'CBC DENGUE',
        'COMPLETE BLOOD COUNT DENGU' => 'CBC DENGUE',
        'HB' => 'HEMOGLOBIN',
        'HEMOGLOBIN' => 'HEMOGLOBIN',
        'HEMAGLOBIN' => 'HEMOGLOBIN',
        'HEMAGLOBIN HB' => 'HEMOGLOBIN',
        'HEMOGLOBIN HB' => 'HEMOGLOBIN',
        'RBS' => 'RBS',
        'RANDOM BLOOD SUGAR' => 'RBS',
        'RANDOM BLOOD SUGAR RBS' => 'RBS',
        'FBS' => 'FBS',
        'FASTING BLOOD SUGAR' => 'FBS',
        'FASTING BLOOD SUGAR FBS' => 'FBS',
        'LFT' => 'LFT',
        'LFTS' => 'LFT',
        'LIVER FUNCTION' => 'LFT',
        'LIVER FUNCTION TEST' => 'LFT',
        'LIVER FUNCTION TESTS' => 'LFT',
        'LIVER FUNCTIONS REPORT' => 'LFT',
        'LIVER FUNCTIONS REPORT LFTS' => 'LFT',
        'RFT' => 'RFT',
        'RFTS' => 'RFT',
        'RENAL FUNCTION' => 'RFT',
        'RENAL FUNCTION TEST' => 'RFT',
        'RENAL FUNCTION TESTS' => 'RFT',
        'RENAL FUNCTION TEST RFTS' => 'RFT',
        'TFT' => 'TFT',
        'TFTS' => 'TFT',
        'T3 T4 TSH' => 'TFT',
        'THYROID FUNCTION' => 'TFT',
        'THYROID FUNCTION TESTS' => 'TFT',
        'LIPID PROFILE' => 'LIPID PROFILE',
        'LIPID' => 'LIPID PROFILE',
        'CRP' => 'CRP',
        'C REACTIVE PROTEIN' => 'CRP',
        'C REACTIVE PROTEIN TEST' => 'CRP',
        'C REACTIVE PROTEIN TEST CRP' => 'CRP',
        'ESR' => 'ESR',
        'ERYTHROCYTE SEDIMENTATION RATE' => 'ESR',
        'ERYTHROCYTE SEDIMENTATION RATE ESR' => 'ESR',
        'LDH' => 'LDH',
        'LDH SERUM' => 'LDH',
        'LECTATE DEHYDROGENASE' => 'LDH',
        'LECTATE DEHYDROGENASE LDH REPORT' => 'LDH',
        'LECTATE DEHYDROGENASE REPORT' => 'LDH',
        'LDH LACTIC DEHYDROGENASE' => 'LDH',
        'LACTATE DEHYDROGENASE' => 'LDH',
        'PO4' => 'PHOSPHORUS',
        'PHOSPHOROUS' => 'PHOSPHORUS',
        'PHOSPHOROUS PHOSPHATE' => 'PHOSPHORUS',
        'PHOSPHOROUS PHOSPHATE PO4' => 'PHOSPHORUS',
        'HBSAG' => 'HBSAG',
        'HBSAG ICT' => 'HBSAG',
        'BLOOD GROUP' => 'BLOOD GROUP',
        'BLOOD GROUP RH FACTOR' => 'BLOOD GROUP',
        'ABO BLOOD GROUP' => 'BLOOD GROUP',
        'ABO' => 'BLOOD GROUP',
        'URINE COMPLETE EXAMINATION' => 'URINE CE',
        'URINE COMPLETE' => 'URINE CE',
        'URINE C E' => 'URINE CE',
        'URIC ACID' => 'URIC ACID',
        'SERUM URIC ACID' => 'URIC ACID',
        'HBA1C' => 'HBA1C',
        'HBA1C GLYCATED HEMOGLOBIN' => 'HBA1C',
        'VITAMIN B12' => 'VITAMIN B12',
        'VITAMIN D' => 'VITAMIN D',
        '25 HYDROXY VIT D3 LEVEL' => 'VITAMIN D',
        'VITAMIN D3' => 'VITAMIN D',
        'T4' => 'T4',
        'T3' => 'T3',
        'TSH' => 'TSH',
        'TSH THYROID STIMULATING HORMONE' => 'TSH',
        'T3 TRI IODOETHRONINE' => 'T3',
        'FREE T3' => 'FREE T3',
        'FREE T4' => 'FREE T4',
        'T4 FREE' => 'FREE T4',
        'BETA HCG' => 'BETA HCG',
        'HCG' => 'BETA HCG',
        'VDRL' => 'VDRL',
        'RA FACTOR' => 'RA FACTOR',
        'RA FACTOR QNT' => 'RA FACTOR',
        'ASO' => 'ASO',
        'ASO TITER' => 'ASO',
        'ASOT' => 'ASO',
        'ASOT TITER QNT' => 'ASO',
        'ELECTROLYTES' => 'ELECTROLYTES',
        'SERUM ELECTROLYTES' => 'ELECTROLYTES',
        'CALCIUM' => 'CALCIUM',
        'SERUM CALCIUM' => 'CALCIUM',
        'AMYLASE' => 'AMYLASE',
        'SERUM AMYLASE' => 'AMYLASE',
        'PHOSPHORUS' => 'PHOSPHORUS',
        'SERUM PHOSPHORUS REPORT' => 'PHOSPHORUS',
        'PHOSPHOROUS PHOSPHATE PO4' => 'PHOSPHORUS',
        'CHOLESTEROL' => 'CHOLESTEROL',
        'TRIGLYCERIDES' => 'TRIGLYCERIDES',
        'CREATININE' => 'CREATININE',
        'UREA' => 'UREA',
        'ALBUMIN' => 'ALBUMIN',
        'TOTAL PROTEIN' => 'TOTAL PROTEIN',
        'AG RATIO' => 'AG RATIO',
        'A G RATIO' => 'AG RATIO',
        'ALBUMIN GLOBULIN RATIO AG RATIO' => 'AG RATIO',
        'ALP' => 'ALP',
        'ALKALINE PHOSPHATE' => 'ALP',
        'ALKALINE PHOSPHATASE' => 'ALP',
        'SGPT' => 'SGPT',
        'SGPT ALT' => 'SGPT',
        'SGOT' => 'SGOT',
        'SGOT AST' => 'SGOT',
        'GGT' => 'GGT',
        'GAMMA GT' => 'GGT',
        'EGFR' => 'EGFR',
        'PT' => 'PT',
        'PT PROTHROMBIN TIME' => 'PT',
        'APTT' => 'APTT',
        'INR' => 'INR',
        'COAGULATION PROFILE' => 'COAGULATION PROFILE',
        'CUAGULATION PROFILE PT INR APTT' => 'COAGULATION PROFILE',
        'H PYLORI' => 'H PYLORI',
        'H PYLORI ABS IGA' => 'H PYLORI IGA',
        'HELICOBACTER PYLORI ABS IGG' => 'H PYLORI IGG',
        'H PYLORI STOOL' => 'H PYLORI STOOL',
        'H PYLORI STOOL ANTIGEN' => 'H PYLORI STOOL',
        'STOOL FOR H PYLORI ANTIGEN' => 'H PYLORI STOOL',
        'MP ICT' => 'MP ICT',
        'MALARIAL PARASITE ICT MP' => 'MP ICT',
        'MP SMEAR' => 'MP SMEAR',
        'MP BY SLIDE' => 'MP SMEAR',
        'MALARIAL PARASITE SMEAR MP' => 'MP SMEAR',
        'UPT' => 'UPT',
        'URINE PREGNANCY REPORT UPT' => 'UPT',
        'PREGNANCY TEST' => 'UPT',
        'TYPHIDOT' => 'TYPHIDOT',
        'TYPHIDOT TEST IGG IGM' => 'TYPHIDOT',
        'DENGUE PROFILE' => 'DENGUE PROFILE',
        'DENGUE SEROLOGY' => 'DENGUE SEROLOGY',
        'TROPONIN I' => 'TROPONIN I',
        'CARDIAC ENZYMES REPORT TROP I' => 'TROPONIN I',
        'TROPONIN T' => 'TROPONIN T',
        'CARDIAC ENZYMES REPORT TROP T' => 'TROPONIN T',
        'BT CT' => 'BT CT',
        'BLEEDING TIME' => 'BLEEDING TIME',
        'CLOTTING TIME' => 'CLOTTING TIME',
        'CROSS MATCH' => 'CROSS MATCH',
        'CROSS MATCHING WITH SCREENING' => 'CROSS MATCH',
        'CROSS MATCH ELISA' => 'CROSS MATCH ELISA',
        'CROSS MATCHING WITH ELISA' => 'CROSS MATCH ELISA',
        'DONOR SCREENING' => 'DONOR SCREENING',
        'DONOR SCREENING REPORT' => 'DONOR SCREENING',
        'OGTT' => 'OGTT',
        'OGTT 75 GRAMS GLUCOSE TOLERANCE TEST' => 'OGTT',
        'SEMEN ANALYSIS' => 'SEMEN ANALYSIS',
        'PERIPHERAL SMEAR' => 'PERIPHERAL SMEAR',
        'SPUTUM AFB' => 'SPUTUM AFB',
        'SPUTUM AFB REPORT' => 'SPUTUM AFB',
        'SPUTUM FOR AFB SMEAR Z N' => 'SPUTUM AFB',
        'STONE ANALYSIS' => 'STONE ANALYSIS',
        'STONE ANALYSIS REPORT' => 'STONE ANALYSIS',
        'KIDNEY STONE FOR ANALYSIS' => 'STONE ANALYSIS',
        'STOOL CE' => 'STOOL CE',
        'STOOL ROUTINE EXAMINATION' => 'STOOL CE',
        'STOOL FOR C E' => 'STOOL CE',
        'ANTI HCV' => 'ANTI HCV',
        'ANTI HCV SCREENING' => 'ANTI HCV',
        'ANTI HCV ELISA' => 'ANTI HCV ELISA',
        'HIV SCREENING' => 'HIV SCREENING',
        'HIV AIDS BY SCREENING' => 'HIV SCREENING',
        'HIV ELISA' => 'HIV ELISA',
        'HIV AIDS BY ELISA' => 'HIV ELISA',
        'HCV PCR QN' => 'HCV PCR QN',
        'HCV BY PCR QUANTITATION' => 'HCV PCR QN',
        'HCV RNA BY PCR QN' => 'HCV PCR QN',
        'HCV PCR QL' => 'HCV PCR QL',
        'HCV RNA BY PCR QL' => 'HCV PCR QL',
        'PTH' => 'PTH',
        'INTACT PARATHYROID HORMONE PTH' => 'PTH',
        'PTH PARATHYROID HORMONE' => 'PTH',
        'CA125' => 'CA125',
        'CA 125' => 'CA125',
        'AFP' => 'AFP',
        'ALPHA FETOPROTEIN AFP' => 'AFP',
        'ALPHA FETO PROTEIN' => 'AFP',
        'FERRITIN' => 'FERRITIN',
        'PROLACTIN' => 'PROLACTIN',
        'FSH' => 'FSH',
        'LH' => 'LH',
        'LH LUTEINHIZING HORMONE' => 'LH',
        'IGE' => 'IGE',
        'IG E IMMUNOGLOBULIN E' => 'IGE',
        'IRON' => 'IRON',
        'IRON FE' => 'IRON',
        'TIBC' => 'TIBC',
        'TIBC TOTAL IRON BINDING CAPACITY' => 'TIBC',
        'PSA' => 'PSA',
        'ANA' => 'ANA',
        'ANA TITER' => 'ANA',
        'ANTI CCP' => 'ANTI CCP',
        'BICARBONATE' => 'BICARBONATE',
        'BICARBONATES HCO3' => 'BICARBONATE',
        'BICARBONATE HCO3' => 'BICARBONATE',
        'LIPASE' => 'LIPASE',
        'SERUM LIPASE' => 'LIPASE',
        'G6PD' => 'G6PD',
        'MYCODOT' => 'MYCODOT',
        'MYCODOT SCREENING' => 'MYCODOT',
        'VIRAL PROFILE' => 'VIRAL PROFILE',
        'HEPATITIS VIROLOGICAL PROFILE' => 'VIRAL PROFILE',
        'MAGNESIUM' => 'MAGNESIUM',
        'MAGNESIUM SERUM ELECTROLYTES' => 'MAGNESIUM',
        'ALBUMIN URINE' => 'ALBUMIN URINE',
        'ALBUMIN SPOT URINE' => 'ALBUMIN URINE',
        'CREATININE CLEARANCE' => 'CREATININE CLEARANCE',
        'WIDAL' => 'WIDAL',
        'WIDAL TEST' => 'WIDAL',
        'BREAST MILK CE' => 'BREAST MILK CE',
        'BREAST MILK COMPLETE EXAMINATION' => 'BREAST MILK CE',
        'VIRAL SCREEN HCV HBSAG HIV' => 'HCV HBSAG HIV',
        'HCV HBSAG HIV' => 'HCV HBSAG HIV',
        'HCV HBSAG' => 'HCV HBSAG',
        'ACTH' => 'ACTH',
        'ACTH ADRENOCORTICOTROPIC HORMONE' => 'ACTH',
        'ALDOLASE' => 'ALDOLASE',
        'ALDOLASE SERUM' => 'ALDOLASE',
        'ALDOSTERONE' => 'ALDOSTERONE',
        'ALDOSTERONE LEVEL' => 'ALDOSTERONE',
        'ACID PHOSPHATASE' => 'ACID PHOSPHATASE',
        'AMMONIA' => 'AMMONIA',
        'AMMONIA NH3' => 'AMMONIA',
        'DEPARTMENT OF' => '',
    ];

    return $aliases[$key] ?? $key;
}

/**
 * @return list<array{mtid:string,name:string,price:float,sample:string,reporting:string}>
 */
function parse_royal_csv(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException("Cannot open {$path}");
    }
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }
    fgetcsv($handle); // header

    $out = [];
    while (($cols = fgetcsv($handle)) !== false) {
        if ($cols === [null] || $cols === false) {
            continue;
        }
        while (count($cols) > 0 && trim((string)end($cols)) === '') {
            array_pop($cols);
        }
        if (count($cols) < 2) {
            continue;
        }

        $mtid = trim((string)$cols[0]);
        if ($mtid === '' || !ctype_digit($mtid)) {
            continue;
        }

        $name = '';
        $price = 0.0;
        $sample = '';
        $reporting = '';

        if (count($cols) >= 5 && preg_match('/^\d+(\.\d+)?$/', trim((string)$cols[2]))) {
            $name = trim((string)$cols[1]);
            $price = (float)$cols[2];
            $sample = trim((string)$cols[3]);
            $reporting = trim((string)$cols[4]);
        } elseif (count($cols) === 4 && preg_match('/^\d+(\.\d+)?$/', trim((string)$cols[2]))) {
            $name = trim((string)$cols[1]);
            $price = (float)$cols[2];
            $sample = trim((string)$cols[3]);
        } elseif (count($cols) === 3 && preg_match('/^\d+(\.\d+)?$/', trim((string)$cols[2]))) {
            $name = trim((string)$cols[1]);
            $price = (float)$cols[2];
        } elseif (count($cols) === 2) {
            $name = trim((string)$cols[1]);
        } else {
            // Broken row with commas in name — find numeric price column
            $priceIdx = null;
            for ($i = 2; $i < count($cols); $i++) {
                $tok = trim((string)$cols[$i]);
                if ($tok !== '' && preg_match('/^\d+(\.\d+)?$/', $tok)) {
                    $priceIdx = $i;
                    break;
                }
            }
            if ($priceIdx === null) {
                continue; // unrecoverable / junk
            }
            $name = trim(implode(' ', array_slice($cols, 1, $priceIdx - 1)));
            $price = (float)$cols[$priceIdx];
            $sample = trim((string)($cols[$priceIdx + 1] ?? ''));
            $reporting = trim((string)($cols[$priceIdx + 2] ?? ''));
        }

        $name = normalize_test_name($name);
        if ($name === '' || strcasecmp($name, 'Department of') === 0) {
            continue;
        }
        // Skip obviously broken fragments from bad CSV splits
        if (preg_match('/^(ALPHA|CA|Sugar|Cortisol \(Urine)$/i', $name)) {
            continue;
        }
        if (strlen($name) < 2) {
            continue;
        }

        $out[] = [
            'mtid' => $mtid,
            'name' => $name,
            'price' => $price,
            'sample' => $sample !== '' ? $sample : '—',
            'reporting' => $reporting,
        ];
    }
    fclose($handle);
    return $out;
}

function guess_category(string $name): string
{
    $u = strtoupper($name);
    if (preg_match('/CBC|HEMOGLOBIN|HEMAGLOBIN|\bHB\b|ESR|PERIPHERAL|PLATELET|TLC|DLC|RETIC|G6PD|BT\/?CT|BLEEDING|CLOTTING/', $u)) {
        return 'Hematology';
    }
    if (preg_match('/URINE|STOOL|SPUTUM|SEMEN|C\/S|C\/E|FLUID|CSF|ASCITIC|PLEURAL|BREAST MILK/', $u)) {
        return 'Clinical Pathology';
    }
    if (preg_match('/HBSAG|HCV|HIV|VDRL|WIDAL|TYPHI|DENGUE|BRUCELLA|ANA|ASO|RA FACTOR|TORCH|RUBELLA|TOXO|MYCODOT|H\.?\s*PYLORI|H PYLORI|CRP|\bICT\b/', $u)) {
        return 'Serology';
    }
    if (preg_match('/PCR|DNA|RNA|GENOTYP/', $u)) {
        return 'Molecular';
    }
    if (preg_match('/\bT3\b|\bT4\b|TSH|TFT|FSH|\bLH\b|PROLACTIN|BETA\s*HCG|PSA|AFP|CA-?125|PTH|CORTISOL|INSULIN|FERRITIN|VITAMIN|B12|D3/', $u)) {
        return 'Special Chemistry';
    }
    if (preg_match('/ULTRASOUND|X-?RAY/', $u)) {
        return 'Radiology';
    }
    if (preg_match('/\bPT\b|APTT|INR|D-?DIMER|FIBRINOGEN|COAG/', $u)) {
        return 'Coagulation';
    }
    return 'Biochemistry';
}

function run_merge_royal_tests(): array
{
    $config = require __DIR__ . '/../config/database.php';
    $logs = [];
    $csvPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'royal_laboratory_price_list.csv';

    if (!is_file($csvPath)) {
        throw new RuntimeException('royal_laboratory_price_list.csv not found at project root.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['dbname'],
        $config['charset']
    );
    $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);

    $rows = parse_royal_csv($csvPath);
    $logs[] = 'Parsed ' . count($rows) . ' usable rows from royal_laboratory_price_list.csv';

    $existing = $pdo->query(
        "SELECT id, code, name, price FROM tests WHERE organization_id = 'ORG-001'"
    )->fetchAll(PDO::FETCH_ASSOC);

    $byKey = [];
    $usedCodes = [];
    foreach ($existing as $t) {
        $key = royal_normalize_key((string)$t['name']);
        if ($key !== '') {
            $byKey[$key] = $t;
        }
        $codeKey = strtoupper(trim((string)$t['code']));
        if ($codeKey !== '') {
            $usedCodes[$codeKey] = true;
        }
    }
    $logs[] = 'Existing catalog: ' . count($existing) . ' tests (' . count($byKey) . ' unique keys)';

    $maxId = 0;
    foreach ($existing as $t) {
        if (preg_match('/^TST-(\d+)$/', (string)$t['id'], $m)) {
            $maxId = max($maxId, (int)$m[1]);
        }
    }
    $index = $maxId + 1;

    $insert = $pdo->prepare(
        'INSERT INTO tests (id, organization_id, code, name, category, price, sample_type, unit, normal_range)
         VALUES (:id, :org_id, :code, :name, :category, :price, :sample_type, :unit, :normal_range)'
    );

    $added = 0;
    $skipped = 0;
    $seenKeys = [];
    $skipExamples = [];

    foreach ($rows as $row) {
        $key = royal_normalize_key($row['name']);
        if ($key === '') {
            $skipped++;
            continue;
        }
        if (isset($seenKeys[$key])) {
            $skipped++;
            continue;
        }
        $seenKeys[$key] = true;

        if (isset($byKey[$key])) {
            $skipped++;
            if (count($skipExamples) < 15) {
                $skipExamples[] = $row['name'] . ' ≈ ' . $byKey[$key]['name'];
            }
            continue;
        }

        $code = normalize_test_code('', $row['name'], $index, $usedCodes);
        $usedCodes[strtoupper($code)] = true;

        $id = sprintf('TST-%04d', $index);
        while (true) {
            $check = $pdo->prepare('SELECT 1 FROM tests WHERE id = ? LIMIT 1');
            $check->execute([$id]);
            if (!$check->fetchColumn()) {
                break;
            }
            $index++;
            $id = sprintf('TST-%04d', $index);
        }

        $sample = normalize_specimen($row['sample']);
        $insert->execute([
            'id' => $id,
            'org_id' => 'ORG-001',
            'code' => $code,
            'name' => $row['name'],
            'category' => guess_category($row['name']),
            'price' => $row['price'],
            'sample_type' => $sample,
            'unit' => '—',
            'normal_range' => '—',
        ]);

        $byKey[$key] = ['id' => $id, 'name' => $row['name']];
        $logs[] = "ADD  {$id}  {$row['name']}  Rs {$row['price']}";
        $added++;
        $index++;
    }

    foreach ($skipExamples as $ex) {
        $logs[] = "SKIP example: {$ex}";
    }
    $logs[] = "Skipped (already exist / duplicate / junk): {$skipped}";
    $logs[] = "Added new tests: {$added}";
    $total = (int)$pdo->query("SELECT COUNT(*) FROM tests WHERE organization_id = 'ORG-001'")->fetchColumn();
    $logs[] = "Catalog total now: {$total}";
    $logs[] = 'Done.';

    return ['success' => true, 'added' => $added, 'skipped' => $skipped, 'logs' => $logs];
}

$isEntry = (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'merge_royal_tests.php')
    || str_ends_with(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/merge_royal_tests.php')
    || (PHP_SAPI === 'cli' && isset($_SERVER['argv'][0]) && realpath((string)$_SERVER['argv'][0]) === realpath(__FILE__));

if ($isEntry) {
    try {
        $res = run_merge_royal_tests();
    } catch (Throwable $e) {
        $res = ['success' => false, 'added' => 0, 'skipped' => 0, 'logs' => ['ERROR: ' . $e->getMessage()]];
    }

    if (PHP_SAPI === 'cli') {
        foreach ($res['logs'] as $msg) {
            echo $msg . PHP_EOL;
        }
        exit(!empty($res['success']) ? 0 : 1);
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Merge Royal Tests</title></head>';
    echo '<body style="font-family:sans-serif;padding:2rem;max-width:52rem;margin:0 auto">';
    echo !empty($res['success'])
        ? '<h1>Merge OK</h1><p>Added ' . (int)($res['added'] ?? 0) . ' new tests. Skipped ' . (int)($res['skipped'] ?? 0) . ' existing/duplicate.</p>'
        : '<h1>Merge failed</h1>';
    echo '<pre style="white-space:pre-wrap;background:#f8fafc;padding:1rem;border:1px solid #e2e8f0;border-radius:8px">'
        . htmlspecialchars(implode("\n", $res['logs'] ?? []), ENT_QUOTES, 'UTF-8')
        . '</pre>';
    echo '<p><a href="/portals/main-lab/tests/index.php">Back to Tests</a></p></body></html>';
}
