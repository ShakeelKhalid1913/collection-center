<?php

declare(strict_types=1);

/**
 * Re-import tests from project-root tests.csv into an existing database.
 * Usage (CLI): php app/database/reimport_tests.php
 * Or open in browser: /database/reimport_tests.php
 */

namespace App\Database;

use PDO;
use PDOException;
use Throwable;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/import_tests_csv.php';

function runReimportTests(): array
{
    $config = require __DIR__ . '/../config/database.php';
    $logs = [];

    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['dbname'],
            $config['charset']
        );
        $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);

        $csvPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tests.csv';
        $logs[] = "Importing from {$csvPath}";

        $import = import_tests_from_csv($pdo, $csvPath, 'ORG-001');
        $logs = array_merge($logs, $import['logs']);

        $attached = seed_panel_parameters_for_imported_catalog($pdo, 'ORG-001');
        if ($attached !== []) {
            foreach ($attached as $panel => $info) {
                $logs[] = "{$panel} multi-parameters attached to {$info}.";
            }
        } else {
            $logs[] = 'No panel tests found for parameter seeding.';
        }

        $logs[] = 'Done.';
        return ['success' => true, 'logs' => $logs];
    } catch (Throwable $e) {
        $logs[] = 'REIMPORT ERROR: ' . $e->getMessage();
        return ['success' => false, 'logs' => $logs];
    }
}

$isEntry = (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'reimport_tests.php')
    || str_ends_with(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/reimport_tests.php')
    || (PHP_SAPI === 'cli' && realpath($_SERVER['argv'][0] ?? '') === realpath(__FILE__));

if ($isEntry) {
    $res = runReimportTests();
    if (PHP_SAPI === 'cli') {
        foreach ($res['logs'] as $msg) {
            echo $msg . PHP_EOL;
        }
        exit($res['success'] ? 0 : 1);
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Reimport Tests</title></head><body style="font-family:sans-serif;padding:2rem">';
    echo $res['success'] ? '<h1>Import OK</h1>' : '<h1>Import failed</h1>';
    echo '<pre>' . htmlspecialchars(implode("\n", $res['logs'])) . '</pre>';
    echo '<p><a href="/portals/main-lab/tests/index.php">Back to Tests</a></p></body></html>';
}
