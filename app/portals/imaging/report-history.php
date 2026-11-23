<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$searchQ = trim((string)($_GET['q'] ?? ''));

$scans = result_repo()->getImagingScans($orgId);

$rows = [];
foreach ($scans as $scan) {
    $scanNo = (string)($scan['scan_no'] ?? '');
    $patient = (string)($scan['patient'] ?? 'Walk-in Patient');
    $modality = (string)($scan['modality'] ?? 'Radiology');
    $study = (string)($scan['study'] ?? $modality);
    $date = !empty($scan['scan_date']) ? format_date($scan['scan_date']) : (!empty($scan['created_at']) ? format_date($scan['created_at']) : '—');
    $status = (string)($scan['status'] ?? 'pending');
    $pId = (string)($scan['patient_id'] ?? '');

    if ($searchQ !== '') {
        $qLower = strtolower($searchQ);
        $matched = str_contains(strtolower($patient), $qLower)
            || str_contains(strtolower($scanNo), $qLower)
            || str_contains(strtolower($study), $qLower)
            || str_contains(strtolower($pId), $qLower);
        if (!$matched) {
            continue;
        }
    }

    $isReported = in_array(strtolower($status), ['reported', 'completed', 'verified'], true);
    $statusBadge = $isReported
        ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Reported</span>'
        : '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending Findings</span>';

    $q = urlencode($scanNo);
    $actionHtml = '<div class="inline-flex items-center gap-1.5 whitespace-nowrap">' .
        '<a href="/portals/reports.php?scan_no=' . $q . '" class="btn btn-primary text-xs px-2.5 py-1 font-bold" title="View & Print Report">' .
            '<i class="fa-solid fa-file-waveform mr-1"></i> View Report' .
        '</a>' .
        '<a href="/portals/results.php?scan_no=' . $q . '" class="btn btn-secondary text-xs px-2.5 py-1 font-bold" title="Edit Findings">' .
            '<i class="fa-solid fa-pen-to-square text-teal-600 mr-1"></i> Edit' .
        '</a>' .
    '</div>';

    $deptMeta = get_department_meta($study, $modality);
    $modBadge = '<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase border ' . $deptMeta['badge_class'] . '"><i class="' . $deptMeta['icon'] . ' ' . $deptMeta['icon_color'] . '"></i> ' . e($deptMeta['name']) . '</span>';

    $rows[] = [
        '<span class="font-mono font-bold text-teal-800">' . e($scanNo) . '</span>',
        '<div class="font-bold text-slate-900">' . e($patient) . '</div>',
        '<div><div class="font-bold text-slate-900 text-xs">' . e($study) . '</div><div class="mt-1">' . $modBadge . '</div></div>',
        '<span class="text-xs text-slate-500">' . e($date) . '</span>',
        $statusBadge,
        $actionHtml
    ];
}

$foundCount = count($rows);
$searchBarHtml = <<<HTML
<div class="mb-5 p-4 bg-white rounded-3xl border border-slate-200/90 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
    <form method="get" class="flex-1 w-full flex items-center gap-2">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-sm"></i>
            <input type="text" name="q" value="{$searchQ}" 
                   placeholder="Search patient by Name, Study, Scan No, or MR Number..." 
                   class="w-full pl-10 pr-4 py-2 text-sm font-semibold rounded-2xl border-2 border-slate-300 bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-4 focus:ring-teal-500/10 focus:border-teal-600 shadow-2xs transition-all placeholder:text-slate-400">
        </div>
        <button type="submit" class="btn btn-primary text-xs font-bold px-4 py-2.5 rounded-2xl">
            <i class="fa-solid fa-search mr-1"></i> Search
        </button>
        <a href="/portals/report-history.php" class="btn btn-secondary text-xs font-semibold px-3 py-2.5 rounded-2xl">
            Reset
        </a>
    </form>
    <div class="text-xs text-slate-500 font-semibold whitespace-nowrap">
        Found <strong class="text-slate-900 font-bold">{$foundCount}</strong> study record(s)
    </div>
</div>
HTML;

$headerActions = '<div class="flex items-center gap-2">' .
    '<a href="/portals/new-scan.php" class="btn btn-primary text-xs py-2 px-3"><i class="fa-solid fa-plus mr-1"></i> New Study</a>' .
    '<a href="/portals/results.php" class="btn btn-secondary text-xs py-2 px-3"><i class="fa-solid fa-notes-medical mr-1"></i> Enter Findings</a>' .
'</div>';

$content = page_header('Imaging Report History', 'View, search, print, and edit all Ultrasound, X-Ray, ECG, CT Scan, and MRI reports.', $headerActions, 'fa-solid fa-folder-open');
$content .= $searchBarHtml;
$content .= card(data_table(['Scan No', 'Patient', 'Study', 'Date', 'Status', 'Actions'], $rows), 'overflow-hidden');

render_page('Report History', 'imaging', 'report-history', $content);
