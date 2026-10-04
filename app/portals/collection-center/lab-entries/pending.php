<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$dbEntries = lab_repo()->getAll($orgId, 250);
$sourceEntries = !empty($dbEntries) ? $dbEntries : mock('mock_lab_entries');

$rows = [];
foreach ($sourceEntries as $e) {
    $status = (string)($e['status'] ?? 'pending');
    $sampleStatus = (string)($e['sample_status'] ?? $status);
    if (!in_array($status, ['pending', 'collected'], true) && !in_array($sampleStatus, ['pending', 'collected'], true)) {
        continue;
    }
    $pName = (string)($e['patient_name'] ?? $e['patient'] ?? '—');
    $labNo = (string)($e['lab_no'] ?? '');
    $labQ = urlencode($labNo);
    $dateVal = $e['created_at'] ?? $e['date'] ?? null;
    $remaining = max(0, (float)($e['amount'] ?? 0) - (float)($e['paid'] ?? 0));

    $rows[] = [
        '<span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md">' . e($labNo) . '</span>',
        '<span class="font-semibold text-slate-800">' . e($pName) . '</span>',
        '<span class="text-xs text-slate-600 font-medium">' . e(normalize_tests_list((string)($e['tests'] ?? ''))) . '</span>',
        e((string)($e['doctor'] ?? 'Walk-in / Self')),
        '<span class="text-xs text-slate-500">' . e((string)($e['route'] ?? 'Laboratory')) . '</span>',
        status_badge($e['priority'] ?? 'Normal'),
        status_badge($sampleStatus),
        '<span class="font-semibold text-slate-900">' . e(format_money((float)($e['amount'] ?? 0))) . '</span>',
        '<span class="font-semibold ' . ($remaining > 0 ? 'text-amber-600' : 'text-emerald-600') . '">' . e(format_money($remaining)) . '</span>',
        '<span class="text-xs text-slate-500 whitespace-nowrap">' . e(format_date($dateVal)) . '</span>',
        '<div class="flex items-center gap-1.5 whitespace-nowrap">' .
        '<a href="/portals/collection-center/lab-entries/edit.php?lab_no=' . $labQ . '" class="btn btn-secondary text-xs px-2.5 py-1" title="Edit tests"><i class="fa-solid fa-pen-to-square"></i></a>' .
        '<a href="/portals/collection-center/receipts.php?lab_no=' . $labQ . '" class="btn btn-secondary text-xs px-2.5 py-1 font-semibold text-slate-700">Receipt</a>' .
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
