<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [];
foreach (mock('mock_lab_entries') as $e) {
    $remaining = max(0, (float) $e['amount'] - (float) $e['paid']);
    $rows[] = [
        e($e['lab_no']),
        e($e['patient']),
        e($e['tests']),
        e($e['doctor'] ?? '—'),
        e($e['route'] ?? '—'),
        status_badge($e['status']),
        e(format_money((float) $e['amount'])),
        e(format_money((float) ($e['discount'] ?? 0))),
        e(format_money((float) $e['paid'])),
        e(format_money($remaining)),
        e(format_date($e['date'])),
        '<div class="flex flex-wrap gap-2">' .
        btn_secondary('/portals/collection-center/receipts.php', 'Receipt') .
        btn_secondary('/portals/collection-center/reports.php', 'Report') .
        '</div>',
    ];
}

$filters = filter_bar([
    form_field('Search', 'q', 'search', null, 'Lab no, patient, test…'),
    select_field('Status', 'status', [
        '' => 'All statuses',
        'pending' => 'Pending',
        'collected' => 'Collected',
        'completed' => 'Completed',
        'critical' => 'Critical',
    ]),
    select_field('Date from', 'from', ['' => '—', '2026-09-14' => '14 Sep', '2026-09-15' => '15 Sep', '2026-09-16' => '16 Sep']),
    select_field('Date to', 'to', ['' => '—', '2026-09-14' => '14 Sep', '2026-09-15' => '15 Sep', '2026-09-16' => '16 Sep']),
    select_field('Payment', 'pay', [
        '' => 'Any',
        'paid' => 'Fully paid',
        'partial' => 'Partial',
        'unpaid' => 'Unpaid',
    ]),
    select_field('Discount', 'disc', ['' => 'Any', 'yes' => 'With discount', 'no' => 'No discount']),
    select_field('Branch', 'branch', ['' => 'All', 'CC-01' => 'CC-01', 'CC-02' => 'CC-02']),
    select_field('Route', 'route', ['' => 'All routes', 'path' => 'Pathology', 'bio' => 'Biochemistry', 'hema' => 'Hematology']),
]);

$content = page_header(
    'Lab Entry History',
    'All entries with amount, discount, paid, and remaining.',
    btn_primary('/portals/collection-center/lab-entries/new.php', 'New entry')
);

$content .= card(
    $filters .
    data_table(
        ['Lab No', 'Patient', 'Tests', 'Doctor', 'Route', 'Status', 'Total', 'Discount', 'Paid', 'Due', 'Date', 'Actions'],
        $rows
    ),
    'overflow-hidden'
);

render_page('History', 'collection-center', 'history', $content);
