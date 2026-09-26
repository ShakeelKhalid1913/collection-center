<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$rows = [];
foreach (mock('mock_lab_entries') as $e) {
    $labQ = urlencode($e['lab_no']);
    $rows[] = [
        e($e['lab_no']),
        e($e['patient']),
        e($e['tests']),
        e($e['doctor'] ?? '—'),
        status_badge($e['status']),
        e($e['branch'] ?? '—'),
        e(format_date($e['date'])),
        '<div class="flex flex-wrap gap-2">' .
        btn_secondary('/portals/collection-center/reports/preview.php?lab_no=' . $labQ, 'Report') .
        btn_secondary('/portals/collection-center/receipts.php?lab_no=' . $labQ, 'Receipt') .
        '</div>',
    ];
}

$content = page_header('Reports', 'All entries — open report or receipt (stays in Collection Center).');
$content .= card(
    data_table(['Lab No', 'Patient', 'Tests', 'Doctor', 'Status', 'Branch', 'Date', 'Actions'], $rows),
    'overflow-hidden'
);

render_page('Reports', 'collection-center', 'reports', $content);
