<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$labNo = trim((string)($_GET['lab_no'] ?? ''));
$scanNo = trim((string)($_GET['scan_no'] ?? ''));

if ($scanNo !== '') {
    require __DIR__ . '/portals/imaging/reports.php';
    return;
}

require __DIR__ . '/portals/main-lab/reports/preview.php';
