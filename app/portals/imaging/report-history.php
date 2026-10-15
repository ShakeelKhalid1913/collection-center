<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$scans = result_repo()->getImagingScans($orgId);

$rows = [];
foreach ($scans as $scan) {
    $scanNo = (string)($scan['scan_no'] ?? '');
    $patient = (string)($scan['patient'] ?? 'Walk-in Patient');
    $modality = (string)($scan['modality'] ?? 'Radiology');
    $study = (string)($scan['study'] ?? $modality);
    $date = !empty($scan['scan_date']) ? format_date($scan['scan_date']) : (!empty($scan['created_at']) ? format_date($scan['created_at']) : '—');
    $status = (string)($scan['status'] ?? 'pending');

    $isReported = in_array(strtolower($status), ['reported', 'completed', 'verified'], true);
    $statusBadge = $isReported
        ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Reported</span>'
        : '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending Findings</span>';

    $q = urlencode($scanNo);
    $actionHtml = '<div class="inline-flex items-center gap-1.5 whitespace-nowrap">' .
        '<a href="/portals/imaging/reports.php?scan_no=' . $q . '" class="btn btn-primary text-xs px-2.5 py-1 font-bold" title="View & Print Report">' .
            '<i class="fa-solid fa-file-waveform mr-1"></i> View Report' .
        '</a>' .
        '<a href="/portals/imaging/results.php?scan_no=' . $q . '" class="btn btn-secondary text-xs px-2.5 py-1 font-bold" title="Edit Findings">' .
            '<i class="fa-solid fa-pen-to-square text-teal-600 mr-1"></i> Edit' .
        '</a>' .
    '</div>';

    $rows[] = [
        '<span class="font-mono font-bold text-teal-800">' . e($scanNo) . '</span>',
        '<div class="font-bold text-slate-900">' . e($patient) . '</div>',
        '<span class="font-medium text-slate-700">' . e($study) . '</span>',
        '<span class="text-xs text-slate-500">' . e($date) . '</span>',
        $statusBadge,
        $actionHtml
    ];
}

$headerActions = '<div class="flex items-center gap-2">' .
    '<a href="/portals/imaging/new-scan.php" class="btn btn-primary text-xs py-2 px-3"><i class="fa-solid fa-plus mr-1"></i> New Study</a>' .
    '<a href="/portals/imaging/results.php" class="btn btn-secondary text-xs py-2 px-3"><i class="fa-solid fa-notes-medical mr-1"></i> Enter Findings</a>' .
'</div>';

$content = page_header('Imaging Report History', 'View, print, and edit all Ultrasound, X-Ray, ECG, and CT Scan reports.', $headerActions, 'fa-solid fa-folder-open');
$content .= card(data_table(['Scan No', 'Patient', 'Study', 'Date', 'Status', 'Actions'], $rows), 'overflow-hidden');

render_page('Report History', 'imaging', 'report-history', $content);
