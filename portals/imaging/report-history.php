<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$rows = [];
foreach (mock('mock_imaging') as $scan) {
    if ($scan['status'] !== 'completed') {
        continue;
    }
    $rows[] = [e($scan['scan_no']), e($scan['patient']), e($scan['study']), e(format_date($scan['date'])), btn_secondary('/portals/imaging/reports.php', 'Open')];
}

$content = page_header('Report History', 'Completed imaging reports.');
$content .= card(data_table(['Scan No', 'Patient', 'Study', 'Date', 'Action'], $rows), 'overflow-hidden');

render_page('Report History', 'imaging', 'report-history', $content);
