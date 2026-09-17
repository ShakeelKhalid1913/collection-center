<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$rows = [];
foreach (mock('mock_imaging') as $scan) {
    $rows[] = [e($scan['scan_no']), e($scan['patient']), e($scan['modality']), e($scan['study']), status_badge($scan['status']), btn_secondary('/portals/imaging/results.php', 'Enter findings')];
}

$content = page_header('Pending Scans', 'Track scan status through reporting.');
$content .= card(data_table(['Scan No', 'Patient', 'Modality', 'Study', 'Status', 'Action'], $rows), 'overflow-hidden');

render_page('Pending Scans', 'imaging', 'pending-scans', $content);
