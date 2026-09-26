<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$rows = [];
foreach (mock('mock_imaging') as $scan) {
    if (!in_array($scan['status'], ['pending', 'in_progress'], true)) {
        continue;
    }
    $rows[] = [
        e($scan['scan_no']),
        e($scan['patient']),
        e($scan['modality']),
        e($scan['study']),
        status_badge($scan['status']),
        btn_secondary('/portals/imaging/results.php?scan_no=' . urlencode($scan['scan_no']), 'Enter findings'),
    ];
}

$content = page_header('Pending Scans', 'Track scan status through reporting.');
if (isset($_GET['created'])) {
    $content .= flash_success('Study created.');
}
$content .= card(data_table(['Scan No', 'Patient', 'Modality', 'Study', 'Status', 'Action'], $rows), 'overflow-hidden');

render_page('Pending Scans', 'imaging', 'pending-scans', $content);
