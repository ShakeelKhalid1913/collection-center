<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [];
foreach (mock('mock_lab_entries') as $e) {
    $labQ = urlencode($e['lab_no']);
    $rows[] = [
        e($e['lab_no']),
        e($e['patient']),
        e(normalize_tests_list((string)($e['tests'] ?? ''))),
        e($e['doctor'] ?? '—'),
        e($e['route'] ?? '—'),
        status_badge($e['status']),
        e(format_money((float) $e['amount'])),
        e(format_money((float) ($e['discount'] ?? 0))),
        e(format_money((float) $e['paid'])),
        e(format_money(max(0, (float) $e['amount'] - (float) $e['paid']))),
        e(format_date($e['date'])),
        '<div class="flex flex-wrap gap-2">' .
        '<a href="/portals/collection-center/lab-entries/edit.php?lab_no=' . $labQ . '" class="btn btn-secondary text-xs"><i class="fa-solid fa-pen-to-square mr-1"></i> Edit Tests</a>' .
        btn_secondary('/portals/collection-center/receipts.php?lab_no=' . $labQ, 'Receipt') .
        btn_secondary('/portals/collection-center/reports/preview.php?lab_no=' . $labQ, 'Report') .
        '</div>',
    ];
}

$content = page_header(
    'Lab Entry History',
    'All entries — open receipt or report preview without leaving Collection Center.',
    btn_primary('/portals/collection-center/lab-entries/new.php', 'New entry')
);

if (isset($_GET['created'])) {
    $content .= flash_success('Entry created.');
}

$content .= card(
    data_table(
        ['Lab No', 'Patient', 'Tests', 'Doctor', 'Route', 'Status', 'Total', 'Discount', 'Paid', 'Due', 'Date', 'Actions'],
        $rows
    ),
    'overflow-hidden'
);

render_page('History', 'collection-center', 'history', $content);
