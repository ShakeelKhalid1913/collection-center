<?php

declare(strict_types=1);

/**
 * Seed panel parameters (CBC/LFT/Lipid/RFT/TFT) and rebuild result sheets
 * for existing lab visits that still have stub single-line results.
 *
 * CLI:  php app/database/repair_panel_results.php
 * Web:  /database/repair_panel_results.php
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/import_tests_csv.php';
require_once __DIR__ . '/../includes/bootstrap.php';

use App\Database\Database;

function run_repair_panel_results(): array
{
    $logs = [];
    $pdo = Database::getInstance()->getPdo();
    if (!$pdo instanceof PDO) {
        return ['success' => false, 'logs' => ['Database connection failed.']];
    }

    $attached = seed_panel_parameters_for_imported_catalog($pdo, 'ORG-001');
    if ($attached === []) {
        $logs[] = 'WARNING: no panel tests found to attach parameters.';
    } else {
        foreach ($attached as $panel => $info) {
            $logs[] = "Seeded {$panel} → {$info}";
        }
    }

    $tr = test_repo();
    foreach (['CBC', 'LFT', 'LFTs', 'LIPID PROFILE', 'RFT', 'RFTs', 'TFT', 'TFTs'] as $code) {
        $t = $tr->findByCode($code);
        $n = $t ? count($tr->getParameters($t['id'])) : 0;
        $logs[] = "Lookup {$code}: " . ($t ? $t['name'] . " ({$n} params)" : 'NOT FOUND');
    }

    $entries = lab_repo()->getAll();
    $fixed = 0;
    foreach ($entries as $e) {
        $labNo = (string)($e['lab_no'] ?? '');
        $tests = (string)($e['tests'] ?? '');
        if ($labNo === '' || $tests === '') {
            continue;
        }
        // Touch panels / anything that might be a stub
        $before = count(result_repo()->getResultsByLabNo($labNo));
        $afterRows = result_repo()->ensureResultsInitialized(
            $labNo,
            $tests,
            (string)($e['patient_name'] ?? ''),
            (string)($e['organization_id'] ?? 'ORG-001')
        );
        $after = count($afterRows);
        if ($after !== $before) {
            $fixed++;
            $logs[] = "Rebuilt {$labNo} [{$tests}]: {$before} → {$after} result lines";
        }
    }

    $logs[] = "Done. Rebuilt {$fixed} visit(s).";
    return ['success' => true, 'logs' => $logs];
}

$isEntry = (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'repair_panel_results.php')
    || str_ends_with(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/repair_panel_results.php')
    || (PHP_SAPI === 'cli' && isset($_SERVER['argv'][0]) && realpath($_SERVER['argv'][0]) === realpath(__FILE__));

if ($isEntry) {
    $result = run_repair_panel_results();
    if (PHP_SAPI === 'cli') {
        foreach ($result['logs'] as $line) {
            echo $line . PHP_EOL;
        }
        exit($result['success'] ? 0 : 1);
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo implode("\n", $result['logs']);
}
