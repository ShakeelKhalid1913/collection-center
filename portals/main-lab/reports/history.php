<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [];
foreach (mock('mock_lab_entries') as $e) {
    if ($e['status'] !== 'completed' && $e['status'] !== 'critical') {
        continue;
    }
    $rows[] = [e($e['lab_no']), e($e['patient']), e($e['tests']), status_badge($e['status']), e(format_date($e['date'])), btn_secondary('/portals/main-lab/reports/preview.php', 'Open')];
}

$content = page_header('Reports History', 'Verified reports with print and WhatsApp actions.');
$content .= card(data_table(['Lab No', 'Patient', 'Tests', 'Status', 'Date', 'Action'], $rows), 'overflow-hidden');

render_page('Reports History', 'main-lab', 'reports-history', $content);
