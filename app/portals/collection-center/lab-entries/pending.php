<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [];
foreach (mock('mock_lab_entries') as $e) {
    if (!in_array($e['status'], ['pending', 'collected'], true)) {
        continue;
    }
    $remaining = max(0, (float) $e['amount'] - (float) $e['paid']);
    $rows[] = [
        e($e['lab_no']),
        e($e['patient']),
        e(normalize_tests_list((string)($e['tests'] ?? ''))),
        e($e['doctor'] ?? '—'),
        e($e['route'] ?? '—'),
        status_badge($e['priority'] ?? 'Normal'),
        status_badge($e['sample_status'] ?? $e['status']),
        e(format_money((float) $e['amount'])),
        e(format_money($remaining)),
        e(format_date($e['date'])),
        '<div class="flex flex-wrap gap-2">' .
        '<a href="/portals/collection-center/lab-entries/edit.php?lab_no=' . urlencode($e['lab_no']) . '" class="btn btn-secondary text-xs"><i class="fa-solid fa-pen-to-square mr-1"></i> Edit Tests</a>' .
        btn_secondary('/portals/collection-center/receipts.php?lab_no=' . urlencode($e['lab_no']), 'Receipt') .
        '</div>',
    ];
}

$filters = filter_bar([
    form_field('Search', 'q', 'search', null, 'Lab no, patient, phone…'),
    select_field('Sample status', 'sample', [
        '' => 'All',
        'pending' => 'Pending collection',
        'collected' => 'Collected',
        'home' => 'Home collection',
    ]),
    select_field('Priority', 'priority', [
        '' => 'All',
        'Normal' => 'Normal',
        'Urgent' => 'Urgent',
        'STAT' => 'STAT',
    ]),
    select_field('Route', 'route', [
        '' => 'All routes',
        'path' => 'Laboratory — Pathology',
        'bio' => 'Biochemistry',
        'hema' => 'Hematology',
        'img' => 'Radiology',
    ]),
    select_field('Payment', 'pay', [
        '' => 'Any',
        'paid' => 'Fully paid',
        'due' => 'Balance due',
    ]),
    select_field('Date', 'date', [
        '' => 'Any',
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        '7d' => 'Last 7 days',
    ]),
    select_field('Branch', 'branch', ['' => 'All', 'CC-01' => 'CC-01', 'CC-02' => 'CC-02']),
    select_field('Referring doctor', 'doctor', [
        '' => 'All doctors',
        'D-01' => 'Dr. Imran Sheikh',
        'D-02' => 'Dr. Fatima Noor',
        'D-03' => 'Dr. Usman Malik',
        'D-04' => 'Dr. Nadia Hussain',
        'D-05' => 'Walk-in / Self',
    ]),
]);

$content = page_header(
    'Pending Entries',
    'Filter by sample status, priority, route, payment, and referral.',
    btn_primary('/portals/collection-center/lab-entries/new.php', 'New entry')
);

$content .= card(
    $filters .
    data_table(
        ['Lab No', 'Patient', 'Tests', 'Doctor', 'Route', 'Priority', 'Sample', 'Amount', 'Due', 'Date', 'Actions'],
        $rows
    ),
    'overflow-hidden'
);

render_page('Pending', 'collection-center', 'pending', $content);
