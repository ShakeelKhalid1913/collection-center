<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$dbEntries = lab_repo()->getAll($orgId, 200);
$sourceEntries = !empty($dbEntries) ? $dbEntries : mock('mock_lab_entries');

$rows = [];
foreach ($sourceEntries as $e) {
    $st = (string)($e['status'] ?? 'pending');
    if ($st !== 'completed' && $st !== 'verified' && $st !== 'critical') {
        continue;
    }
    $labNo = (string)($e['lab_no'] ?? '');
    $labQ = urlencode($labNo);
    $pName = (string)($e['patient_name'] ?? $e['patient'] ?? '—');
    $dateVal = $e['created_at'] ?? $e['date'] ?? null;

    $actions = '<div class="flex items-center gap-1.5 whitespace-nowrap">' .
        '<a href="/portals/main-lab/reports/preview.php?lab_no=' . $labQ . '" class="btn btn-primary text-xs px-2.5 py-1 font-semibold"><i class="fa-solid fa-file-medical mr-1"></i> Report</a>' .
        '<a href="/portals/main-lab/results/entry.php?lab_no=' . $labQ . '" class="btn btn-secondary text-xs px-2.5 py-1 font-semibold"><i class="fa-solid fa-pen-to-square mr-1"></i> Results</a>' .
        '</div>';

    $rows[] = [
        '<span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md text-xs">' . e($labNo) . '</span>',
        '<span class="font-bold text-slate-800">' . e($pName) . '</span>',
        '<span class="text-xs text-slate-600 font-medium">' . e(normalize_tests_list((string)($e['tests'] ?? ''))) . '</span>',
        status_badge($st),
        '<span class="text-xs text-slate-500 whitespace-nowrap">' . e(format_date($dateVal)) . '</span>',
        $actions,
    ];
}

$content = page_header('Reports History', 'Verified and completed diagnostic reports ready for print and WhatsApp.');
$content .= card(data_table(['Lab No', 'Patient', 'Tests', 'Status', 'Date', 'Actions'], $rows), 'overflow-hidden');

render_page('Reports History', 'main-lab', 'reports-history', $content);
