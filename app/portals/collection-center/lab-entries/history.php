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
    $labNo = (string)($e['lab_no'] ?? '');
    $labQ = urlencode($labNo);
    $pName = (string)($e['patient_name'] ?? $e['patient'] ?? '—');
    $dateVal = $e['created_at'] ?? $e['date'] ?? null;
    $amount = (float)($e['amount'] ?? 0);
    $discount = (float)($e['discount'] ?? 0);
    $paid = (float)($e['paid'] ?? 0);
    $due = max(0, $amount - $paid);

    $rows[] = [
        '<span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md">' . e($labNo) . '</span>',
        '<span class="font-semibold text-slate-800">' . e($pName) . '</span>',
        '<span class="text-xs text-slate-600 font-medium">' . e(normalize_tests_list((string)($e['tests'] ?? ''))) . '</span>',
        e((string)($e['doctor'] ?? 'Walk-in / Self')),
        '<span class="text-xs text-slate-500">' . e((string)($e['route'] ?? 'Laboratory')) . '</span>',
        status_badge((string)($e['status'] ?? 'pending')),
        '<span class="font-semibold text-slate-900">' . e(format_money($amount)) . '</span>',
        '<span class="text-xs text-slate-500">' . e(format_money($discount)) . '</span>',
        '<span class="text-xs font-semibold text-emerald-600">' . e(format_money($paid)) . '</span>',
        '<span class="font-semibold ' . ($due > 0 ? 'text-amber-600' : 'text-slate-400') . '">' . e(format_money($due)) . '</span>',
        '<span class="text-xs text-slate-500 whitespace-nowrap">' . e(format_date($dateVal)) . '</span>',
        '<div class="flex items-center gap-1.5 whitespace-nowrap">' .
        '<a href="/portals/collection-center/lab-entries/edit.php?lab_no=' . $labQ . '" class="btn btn-secondary text-xs px-2.5 py-1" title="Edit tests"><i class="fa-solid fa-pen-to-square"></i></a>' .
        '<a href="/portals/collection-center/receipts.php?lab_no=' . $labQ . '" class="btn btn-secondary text-xs px-2.5 py-1 font-semibold text-slate-700">Receipt</a>' .
        '<a href="/portals/collection-center/reports/preview.php?lab_no=' . $labQ . '" class="btn btn-primary text-xs px-2.5 py-1 font-semibold">Report</a>' .
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
