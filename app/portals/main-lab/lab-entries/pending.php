<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

require_auth(['main_lab', 'imaging', 'admin']);
$orgId = current_organization_id();

$dbEntries = lab_repo()->getAll($orgId, 250);
$sourceEntries = !empty($dbEntries) ? $dbEntries : mock('mock_lab_entries');

$rows = [];
foreach ($sourceEntries as $e) {
    $status = (string)($e['status'] ?? 'pending');
    $sampleStatus = (string)($e['sample_status'] ?? $status);
    if (!in_array($status, ['pending', 'collected', 'in_progress'], true) && !in_array($sampleStatus, ['pending', 'collected'], true)) {
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
        status_badge($sampleStatus),
        status_badge($status),
        '<span class="font-semibold text-slate-900">' . e(format_money((float)($e['amount'] ?? 0))) . '</span>',
        '<span class="text-xs text-slate-500 whitespace-nowrap">' . e(format_date($dateVal)) . '</span>',
        '<div class="flex items-center gap-1.5 whitespace-nowrap">' .
        '<a href="/portals/results/entry.php?lab_no=' . $labQ . '" class="btn btn-primary text-xs px-2.5 py-1 font-semibold" title="Enter Results"><i class="fa-solid fa-keyboard mr-1"></i> Results</a>' .
        '<a href="/portals/reports/preview.php?lab_no=' . $labQ . '" class="btn btn-secondary text-xs px-2.5 py-1 font-semibold" title="View Report"><i class="fa-solid fa-file-lines"></i></a>' .
        '<a href="/portals/barcode-label.php?lab_no=' . $labQ . '" target="_blank" class="btn btn-secondary text-xs px-2 py-1" title="Barcode Label"><i class="fa-solid fa-barcode text-blue-600"></i></a>' .
        '</div>',
    ];
}

$filters = filter_bar([
    form_field('Search', 'q', 'search', null, 'Lab no, patient, phone…'),
    select_field('Sample status', 'sample', [
        '' => 'All',
        'pending' => 'Pending collection',
        'collected' => 'Collected / In Lab',
    ]),
]);

$content = page_header(
    'Pending Lab Orders',
    'Laboratory orders awaiting sample collection, testing, or result approval.',
    btn_primary('/portals/lab-entries/new.php', 'New Lab Order', 'fa-solid fa-plus')
);

$content .= card(
    $filters .
    data_table(
        ['Lab No', 'Patient', 'Tests Ordered', 'Doctor', 'Sample', 'Status', 'Total', 'Date', 'Actions'],
        $rows
    ),
    'overflow-hidden'
);

render_page('Pending Lab Orders', 'main-lab', 'pending', $content);
