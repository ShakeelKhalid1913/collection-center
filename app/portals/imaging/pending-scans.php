<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

require_auth(['imaging', 'admin']);
$orgId = current_organization_id();

// Handle quick doctor switch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_doctor'])) {
    $scanNoToUpdate = trim((string)($_POST['scan_no'] ?? ''));
    $customDoc = trim((string)($_POST['custom_doctor'] ?? ''));
    $selectedDoc = trim((string)($_POST['selected_doctor'] ?? ''));
    $newDoc = $customDoc !== '' ? $customDoc : ($selectedDoc !== '' ? resolve_doctor_name($selectedDoc) : '');

    if ($scanNoToUpdate !== '' && $newDoc !== '') {
        result_repo()->updateImagingDoctor($scanNoToUpdate, $newDoc);
        $retMod = !empty($_GET['modality']) ? ('&modality=' . urlencode($_GET['modality'])) : '';
        header('Location: /portals/pending-scans.php?doc_switched=1' . $retMod);
        exit;
    }
}

$selectedModality = strtolower(trim((string)($_GET['modality'] ?? '')));
$selectedStatus = strtolower(trim((string)($_GET['status'] ?? 'pending')));
$searchQuery = strtolower(trim((string)($_GET['q'] ?? '')));

$allDoctors = mock('mock_doctors');
$allScans = result_repo()->getImagingScans($orgId);

// Filter scans by modality, status, and search query
$filteredScans = array_filter($allScans, function ($scan) use ($selectedModality, $selectedStatus, $searchQuery) {
    $st = strtolower((string)($scan['status'] ?? 'pending'));
    $mod = strtolower((string)($scan['modality'] ?? ''));
    $study = strtolower((string)($scan['study'] ?? ''));
    $patient = strtolower((string)($scan['patient'] ?? ''));
    $scanNo = strtolower((string)($scan['scan_no'] ?? ''));
    $doctor = strtolower((string)($scan['radiologist'] ?? $scan['doctor'] ?? ''));

    // Status filter
    if ($selectedStatus === 'pending' && !in_array($st, ['pending', 'in_progress'], true)) {
        return false;
    }
    if ($selectedStatus === 'reported' && !in_array($st, ['reported', 'completed', 'verified'], true)) {
        return false;
    }

    // Modality filter
    if ($selectedModality !== '' && $selectedModality !== 'all') {
        $matchesMod = false;
        if ($selectedModality === 'us' || $selectedModality === 'ultrasound') {
            $matchesMod = str_contains($mod, 'ultra') || str_contains($mod, 'us') || str_contains($study, 'ultra') || str_contains($study, 'pelvis') || str_contains($study, 'abdomen') || str_contains($study, 'kub') || str_contains($study, 'anomaly');
        } elseif ($selectedModality === 'xray' || $selectedModality === 'x-ray') {
            $matchesMod = str_contains($mod, 'xray') || str_contains($mod, 'x-ray') || str_contains($study, 'x-ray') || str_contains($study, 'chest') || str_contains($study, 'spine') || str_contains($study, 'skelet');
        } elseif ($selectedModality === 'ecg') {
            $matchesMod = str_contains($mod, 'ecg') || str_contains($study, 'ecg') || str_contains($study, 'electrocardiogram');
        } elseif ($selectedModality === 'echo') {
            $matchesMod = str_contains($mod, 'echo') || str_contains($study, 'echo') || str_contains($study, 'cardio');
        } elseif ($selectedModality === 'ct') {
            $matchesMod = str_contains($mod, 'ct') || str_contains($study, 'ct') || str_contains($study, 'computed tomography');
        } elseif ($selectedModality === 'mri') {
            $matchesMod = str_contains($mod, 'mri') || str_contains($study, 'mri') || str_contains($study, 'magnetic');
        }

        if (!$matchesMod) {
            return false;
        }
    }

    // Search query filter
    if ($searchQuery !== '') {
        $haystack = $scanNo . ' ' . $patient . ' ' . $study . ' ' . $mod . ' ' . $doctor;
        if (!str_contains($haystack, $searchQuery)) {
            return false;
        }
    }

    return true;
});

// Modality tabs definition
$modalityTabs = [
    '' => ['label' => 'All Radiology Studies', 'icon' => 'fa-solid fa-list-check'],
    'us' => ['label' => 'Ultrasound', 'icon' => 'fa-solid fa-wave-square'],
    'xray' => ['label' => 'Digital X-Ray', 'icon' => 'fa-solid fa-x-ray'],
    'ecg' => ['label' => 'ECG', 'icon' => 'fa-solid fa-heart-pulse'],
    'echo' => ['label' => 'Echocardiography', 'icon' => 'fa-solid fa-heart-pulse'],
    'ct' => ['label' => 'CT Scan', 'icon' => 'fa-solid fa-laptop-medical'],
    'mri' => ['label' => 'MRI', 'icon' => 'fa-solid fa-magnet'],
];

$tabsHtml = '<div class="flex flex-wrap items-center gap-2 mb-4">';
foreach ($modalityTabs as $key => $info) {
    $isActive = ($selectedModality === $key) || ($key === '' && ($selectedModality === '' || $selectedModality === 'all'));
    $activeClasses = $isActive
        ? 'bg-slate-900 text-white shadow-sm'
        : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200';
    $url = '/portals/pending-scans.php?' . http_build_query([
        'modality' => $key,
        'status' => $selectedStatus,
        'q' => $searchQuery,
    ]);
    $tabsHtml .= '<a href="' . e($url) . '" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all ' . $activeClasses . '">';
    $tabsHtml .= '<i class="' . $info['icon'] . '"></i>';
    $tabsHtml .= '<span>' . e($info['label']) . '</span>';
    $tabsHtml .= '</a>';
}
$tabsHtml .= '</div>';

$rows = [];
foreach ($filteredScans as $scan) {
    $scanNo = (string)($scan['scan_no'] ?? '');
    $currentDoc = (string)($scan['radiologist'] ?? ($scan['doctor'] ?? 'Dr. Imran Sheikh'));
    $st = strtolower((string)($scan['status'] ?? 'pending'));
    $isReported = in_array($st, ['reported', 'completed', 'verified'], true);

    // Quick doctor switcher
    $docOptsHtml = '';
    foreach ($allDoctors as $d) {
        $sel = (strcasecmp($d['name'], $currentDoc) === 0) ? ' selected' : '';
        $docOptsHtml .= '<option value="' . e($d['name']) . '"' . $sel . '>' . e($d['name']) . '</option>';
    }

    $docCell = <<<HTML
<div class="flex items-center gap-1.5">
    <form method="post" class="inline-flex items-center gap-1">
        <input type="hidden" name="scan_no" value="{$scanNo}">
        <input type="hidden" name="switch_doctor" value="1">
        <select name="selected_doctor" onchange="this.form.submit()" class="text-xs font-semibold py-1 px-2 rounded-lg border border-slate-200 bg-white hover:border-slate-300 text-slate-800 cursor-pointer shadow-2xs max-w-[11rem]" title="Switch reporting doctor">
            {$docOptsHtml}
        </select>
    </form>
</div>
HTML;

    $reportBtn = $isReported
        ? '<a href="/portals/reports.php?scan_no=' . urlencode($scanNo) . '" class="btn btn-secondary text-xs px-2.5 py-1 font-semibold text-teal-700 hover:bg-teal-50" title="View / Print Verified Report"><i class="fa-solid fa-file-waveform mr-1 text-teal-600"></i> Report</a>'
        : '';

    $actionButtons = <<<HTML
<div class="flex items-center gap-1.5 flex-wrap">
    <a href="/portals/receipt.php?scan_no={$scanNo}" class="btn btn-secondary text-xs px-2.5 py-1 font-semibold text-slate-700" title="Print Token Slip">
        <i class="fa-solid fa-receipt mr-1 text-slate-500"></i> Slip
    </a>
    <a href="/portals/results.php?scan_no={$scanNo}" class="btn btn-primary text-xs px-3 py-1 font-semibold" title="Enter / Edit Findings">
        <i class="fa-solid fa-pen-nib mr-1"></i> Findings
    </a>
    {$reportBtn}
</div>
HTML;

    $modalityBadge = '<span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">' . e($scan['modality'] ?? '—') . '</span>';

    $rows[] = [
        '<span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md text-xs">' . e($scanNo) . '</span>',
        '<span class="font-bold text-slate-800">' . e($scan['patient'] ?? 'Unknown') . '</span>',
        $modalityBadge,
        '<span class="text-xs text-slate-700 font-medium">' . e($scan['study'] ?? '—') . '</span>',
        $docCell,
        status_badge($scan['status'] ?? 'pending'),
        $actionButtons,
    ];
}

$pendingBtnClass = ($selectedStatus === 'pending') ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-slate-100 text-slate-700 hover:bg-slate-200';
$reportedBtnClass = ($selectedStatus === 'reported') ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : 'bg-slate-100 text-slate-700 hover:bg-slate-200';
$allBtnClass = ($selectedStatus === 'all') ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200';
$clearLink = ($searchQuery !== '') ? '<a href="/portals/pending-scans.php?modality=' . urlencode($selectedModality) . '&status=' . urlencode($selectedStatus) . '" class="text-xs text-rose-600 font-bold hover:underline">Clear</a>' : '';

$topControls = <<<HTML
<div class="bg-white p-4 rounded-2xl border border-slate-200 mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
    <div class="flex items-center gap-2">
        <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Status:</span>
        <a href="/portals/pending-scans.php?status=pending&modality={$selectedModality}" class="px-3 py-1 rounded-lg text-xs font-bold transition-colors {$pendingBtnClass}">
            <i class="fa-regular fa-clock mr-1"></i> Pending Queue
        </a>
        <a href="/portals/pending-scans.php?status=reported&modality={$selectedModality}" class="px-3 py-1 rounded-lg text-xs font-bold transition-colors {$reportedBtnClass}">
            <i class="fa-solid fa-circle-check mr-1"></i> Reported / Completed
        </a>
        <a href="/portals/pending-scans.php?status=all&modality={$selectedModality}" class="px-3 py-1 rounded-lg text-xs font-bold transition-colors {$allBtnClass}">
            All
        </a>
    </div>
    <form method="get" class="flex items-center gap-2">
        <input type="hidden" name="modality" value="{$selectedModality}">
        <input type="hidden" name="status" value="{$selectedStatus}">
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
            <input type="search" name="q" value="{$searchQuery}" placeholder="Search patient, scan #, study..." class="pl-8 pr-3 py-1.5 text-xs rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-slate-900 w-52 sm:w-64">
        </div>
        <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-xs transition-colors">Search</button>
        {$clearLink}
    </form>
</div>
HTML;

$content = page_header(
    'Radiology Department Workstation',
    'Unified clinical queue for Ultrasound, Digital X-Ray, ECG, Echocardiography, CT Scan, and MRI.',
    '<div class="flex items-center gap-2">' .
    btn_primary('/portals/new-scan.php', '+ Book Radiology Study', 'fa-solid fa-plus') .
    '<a href="/portals/templates.php" class="btn btn-secondary text-xs"><i class="fa-solid fa-folder-open mr-1 text-[#c2f13c]"></i> Report Templates</a>' .
    '</div>'
);

if (isset($_GET['created'])) {
    $content .= flash_success('Radiology study booked successfully.');
}
if (isset($_GET['doc_switched'])) {
    $content .= flash_success('Reporting doctor successfully updated / switched.');
}

$content .= $tabsHtml;
$content .= $topControls;

if (empty($rows)) {
    $content .= card(
        '<div class="p-12 text-center text-slate-500 font-medium">' .
        '<i class="fa-solid fa-inbox text-4xl text-slate-300 mb-3 block"></i>' .
        'No radiology studies found matching the selected filters.' .
        '<div class="mt-4"><a href="/portals/new-scan.php" class="btn btn-primary text-xs">+ Book New Study</a></div>' .
        '</div>'
    );
} else {
    $content .= card(
        data_table(['Scan No', 'Patient', 'Modality', 'Study', 'Reporting Doctor', 'Status', 'Actions'], $rows),
        'overflow-hidden'
    );
}

render_page('Radiology Workstation', 'imaging', 'pending-scans', $content);
