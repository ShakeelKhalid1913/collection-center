<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$q = trim((string)($_GET['q'] ?? ''));
$dateFrom = trim((string)($_GET['from'] ?? ''));
$dateTo = trim((string)($_GET['to'] ?? ''));

$entries = lab_repo()->searchEntries($orgId, $q, $dateFrom !== '' ? $dateFrom : null, $dateTo !== '' ? $dateTo : null, 300);

$rows = [];
foreach ($entries as $e) {
    $labQ = urlencode((string)$e['lab_no']);
    $actions = '<div class="flex flex-wrap gap-2">' .
        '<a href="/portals/main-lab/reports/preview.php?lab_no=' . $labQ . '" class="btn btn-secondary text-xs"><i class="fa-solid fa-file-medical mr-1"></i> Report</a>' .
        '<a href="/portals/main-lab/receipts.php?lab_no=' . $labQ . '" class="btn btn-secondary text-xs"><i class="fa-solid fa-receipt mr-1"></i> Bill</a>' .
        '<a href="/portals/main-lab/results/entry.php?lab_no=' . $labQ . '" class="btn btn-secondary text-xs"><i class="fa-solid fa-keyboard mr-1"></i> Results</a>' .
        '</div>';

    $rows[] = [
        e(format_date((string)($e['created_at'] ?? ''))),
        '<strong class="font-mono text-teal-800">' . e((string)$e['lab_no']) . '</strong>',
        e((string)($e['patient_name'] ?? '—')),
        e((string)($e['tests'] ?? '—')),
        e((string)($e['doctor'] ?? '—')),
        status_badge((string)($e['status'] ?? 'pending')),
        e(format_money((float)($e['amount'] ?? 0))),
        $actions,
    ];
}

$content = page_header(
    'All Dates History',
    'Complete archive of past diagnostic tests and reports across all dates.'
);

$content .= card(
    '<form method="get" class="flex flex-wrap items-end gap-3 p-4">' .
    '<div class="min-w-[180px] flex-1"><label class="field-label" for="q">Search</label>' .
    '<input type="search" id="q" name="q" class="field" value="' . e($q) . '" placeholder="Lab no, patient, doctor, test"></div>' .
    '<div><label class="field-label" for="from">From</label><input type="date" id="from" name="from" class="field" value="' . e($dateFrom) . '"></div>' .
    '<div><label class="field-label" for="to">To</label><input type="date" id="to" name="to" class="field" value="' . e($dateTo) . '"></div>' .
    '<button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>' .
    ($q !== '' || $dateFrom !== '' || $dateTo !== ''
        ? '<a href="/portals/main-lab/archive.php" class="btn btn-secondary">Clear</a>'
        : '') .
    '</form>'
);

$content .= '<div class="mt-4">' . card(
    panel_head('Tests + Reports (' . count($entries) . ')') .
    (count($rows) > 0
        ? data_table(['Date', 'Lab No', 'Patient', 'Tests', 'Doctor', 'Status', 'Amount', 'Actions'], $rows)
        : '<p class="p-4 text-sm text-slate-500">No historical records found for these filters.</p>'),
    'overflow-hidden'
) . '</div>';

render_page('All Dates History', 'main-lab', 'archive', $content);
