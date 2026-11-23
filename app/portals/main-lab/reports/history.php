<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$searchQ = trim((string)($_GET['q'] ?? ''));

$dbEntries = lab_repo()->getAll($orgId, 250);
$sourceEntries = !empty($dbEntries) ? $dbEntries : mock('mock_lab_entries');

if ($searchQ !== '') {
    $directEntry = lab_repo()->findByLabNo($searchQ, $orgId);
    if ($directEntry) {
        $alreadyInList = false;
        foreach ($sourceEntries as $se) {
            if (($se['lab_no'] ?? '') === $directEntry['lab_no']) {
                $alreadyInList = true;
                break;
            }
        }
        if (!$alreadyInList) {
            array_unshift($sourceEntries, $directEntry);
        }
    }
}

$rows = [];
foreach ($sourceEntries as $e) {
    $st = (string)($e['status'] ?? 'pending');
    if ($st !== 'completed' && $st !== 'verified' && $st !== 'critical' && $searchQ === '') {
        continue;
    }
    $labNo = (string)($e['lab_no'] ?? '');
    $labQ = urlencode($labNo);
    $pName = (string)($e['patient_name'] ?? $e['patient'] ?? '—');
    $pPhone = (string)($e['phone'] ?? $e['patient_phone'] ?? '');
    $pMr = (string)($e['patient_id'] ?? '');
    $dateVal = $e['created_at'] ?? $e['date'] ?? null;

    if ($searchQ !== '') {
        $qLower = strtolower($searchQ);
        $matched = str_contains(strtolower($pName), $qLower)
            || str_contains(strtolower($labNo), $qLower)
            || str_contains(strtolower($pPhone), $qLower)
            || str_contains(strtolower($pMr), $qLower)
            || str_contains(strtolower((string)($e['tests'] ?? '')), $qLower);
        if (!$matched) {
            continue;
        }
    }

    $actions = '<div class="flex items-center gap-1.5 whitespace-nowrap">' .
        '<a href="/portals/reports/preview.php?lab_no=' . $labQ . '" class="btn btn-primary text-xs px-2.5 py-1 font-semibold"><i class="fa-solid fa-file-medical mr-1"></i> Report</a>' .
        '<a href="/portals/results/entry.php?lab_no=' . $labQ . '" class="btn btn-secondary text-xs px-2.5 py-1 font-semibold"><i class="fa-solid fa-pen-to-square mr-1"></i> Results</a>' .
        '</div>';

    $rows[] = [
        '<span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md text-xs">' . e($labNo) . '</span>',
        '<div><span class="font-bold text-slate-800">' . e($pName) . '</span>' . ($pPhone !== '' ? '<div class="text-[11px] text-slate-400 font-mono">' . e($pPhone) . '</div>' : '') . '</div>',
        '<div class="space-y-1.5 max-w-md">' .
            '<div class="text-xs text-slate-800 font-semibold">' . e(normalize_tests_list((string)($e['tests'] ?? ''))) . '</div>' .
            render_department_badges_html((string)($e['tests'] ?? ''), $labNo) .
        '</div>',
        status_badge($st),
        '<span class="text-xs text-slate-500 whitespace-nowrap">' . e(format_date($dateVal)) . '</span>',
        $actions,
    ];
}

$foundCount = count($rows);
$searchBarHtml = <<<HTML
<div class="mb-5 p-4 bg-white rounded-3xl border border-slate-200/90 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
    <form method="get" class="flex-1 w-full flex items-center gap-2">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-sm"></i>
            <input type="text" name="q" value="{$searchQ}" 
                   placeholder="Search patient by Slip # (e.g. 3235), Name, Phone, MR Number, or Lab Number..." 
                   class="w-full pl-10 pr-4 py-2 text-sm font-semibold rounded-2xl border-2 border-slate-300 bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-4 focus:ring-teal-500/10 focus:border-teal-600 shadow-2xs transition-all placeholder:text-slate-400">
        </div>
        <button type="submit" class="btn btn-primary text-xs font-bold px-4 py-2.5 rounded-2xl">
            <i class="fa-solid fa-search mr-1"></i> Search
        </button>
        <a href="/portals/reports/history.php" class="btn btn-secondary text-xs font-semibold px-3 py-2.5 rounded-2xl">
            Reset
        </a>
    </form>
    <div class="text-xs text-slate-500 font-semibold whitespace-nowrap">
        Found <strong class="text-slate-900 font-bold">{$foundCount}</strong> record(s)
    </div>
</div>
HTML;

$content = page_header('Reports History & Patient Archive', 'Instant patient lookup by Name or Phone number to pull complete diagnostic dossiers.');
$content .= $searchBarHtml;
$content .= card(data_table(['Lab No', 'Patient', 'Tests', 'Status', 'Date', 'Actions'], $rows), 'overflow-hidden');

render_page('Reports History', 'main-lab', 'reports-history', $content);
