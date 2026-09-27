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
    $actions = '<a href="/portals/main-lab/reports/preview.php?lab_no=' . urlencode($e['lab_no']) . '" class="btn btn-secondary text-xs mr-2"><i class="fa-solid fa-eye mr-1"></i> View Report</a>';
    $actions .= '<a href="/portals/main-lab/results/entry.php?lab_no=' . urlencode($e['lab_no']) . '" class="btn btn-primary text-xs"><i class="fa-solid fa-pen-to-square mr-1"></i> Edit Results</a>';
    $rows[] = [e($e['lab_no']), e($e['patient']), e($e['tests']), status_badge($e['status']), e(format_date($e['date'])), $actions];
}

$content = page_header('Reports History', 'Verified reports with print and WhatsApp actions.');
$content .= card(data_table(['Lab No', 'Patient', 'Tests', 'Status', 'Date', 'Action'], $rows), 'overflow-hidden');

render_page('Reports History', 'main-lab', 'reports-history', $content);
