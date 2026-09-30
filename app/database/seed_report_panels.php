<?php

declare(strict_types=1);

/**
 * Attach full-page multi-parameter templates (UCE, Breast Milk, Semen, Pus C/S, PCR, CBC…).
 * Safe to re-run — replaces parameters on matched catalog tests only.
 *
 * Usage: php app/database/seed_report_panels.php
 */

require_once __DIR__ . '/import_tests_csv.php';
require_once __DIR__ . '/Database.php';

use App\Database\Database;

$pdo = Database::getInstance()->getPdo();
if (!$pdo) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}
$orgId = $argv[1] ?? 'ORG-001';

echo "Seeding report panel parameters for org {$orgId}…\n";
$attached = seed_panel_parameters_for_imported_catalog($pdo, $orgId);

if ($attached === []) {
    echo "No matching catalog tests found. Import the price list / tests first.\n";
    exit(1);
}

foreach ($attached as $key => $label) {
    echo "  ✓ {$key} → {$label}\n";
}

echo "Done. Open an existing visit (or book these tests) and re-open Enter Results to expand parameters.\n";
