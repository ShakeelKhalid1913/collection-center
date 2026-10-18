<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';

// Fetch all MRI scans from database
$allScans = result_repo()->getImagingScans($orgId);
$mriScans = array_values(array_filter($allScans, function ($s) {
    $m = strtolower((string)($s['modality'] ?? ''));
    $st = strtolower((string)($s['study'] ?? ''));
    return str_contains($m, 'mri') || str_contains($st, 'mri') || str_contains($st, 'magnetic resonance') || str_contains($st, 'mrcp');
}));

$pendingCount = 0;
$reportedCount = 0;
foreach ($mriScans as $sc) {
    $st = strtolower((string)($sc['status'] ?? 'pending'));
    if (in_array($st, ['reported', 'completed', 'verified'], true)) {
        $reportedCount++;
    } else {
        $pendingCount++;
    }
}
$totalCount = count($mriScans);

$tableRowsHtml = '';
if (empty($mriScans)) {
    $tableRowsHtml = '<tr><td colspan="7" class="py-12 text-center text-slate-400 italic text-sm">No MRI Scan patients in queue today. <a href="/portals/imaging/new-scan.php?modality=mri" class="text-indigo-700 font-bold underline ml-1">Book new MRI Scan</a></td></tr>';
} else {
    foreach ($mriScans as $idx => $sc) {
        $scanNo = (string)($sc['scan_no'] ?? '');
        $patient = (string)($sc['patient'] ?? 'Walk-in Patient');
        $study = (string)($sc['study'] ?? 'Magnetic Resonance Imaging');
        $doctor = (string)($sc['radiologist'] ?? $sc['doctor'] ?? 'Dr. Imran Sheikh');
        $dateDisp = !empty($sc['scan_date']) ? date('d M Y', strtotime($sc['scan_date'])) : date('d M Y');
        $status = strtolower((string)($sc['status'] ?? 'pending'));
        $isReported = in_array($status, ['reported', 'completed', 'verified'], true);

        $statusBadge = $isReported
            ? '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Reported</span>'
            : '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending Findings</span>';

        $q = urlencode($scanNo);
        $tableRowsHtml .= <<<TR
        <tr class="hover:bg-indigo-50/40 transition-colors border-b border-slate-100 mri-row" data-search="{$scanNo} {$patient} {$study} {$doctor}">
            <td class="py-3.5 px-3 font-mono font-bold text-indigo-900 whitespace-nowrap">
                <a href="/portals/imaging/reports.php?scan_no={$q}" class="hover:underline">{$scanNo}</a>
            </td>
            <td class="py-3.5 px-3">
                <div class="font-extrabold text-slate-900 text-sm">{$patient}</div>
                <div class="text-[11px] text-slate-400">Magnetic Resonance Queue</div>
            </td>
            <td class="py-3.5 px-3">
                <div class="font-bold text-slate-800">{$study}</div>
                <span class="inline-block mt-0.5 px-1.5 py-0.2 rounded text-[10px] font-bold uppercase tracking-wider bg-indigo-100 text-indigo-800">MRI</span>
            </td>
            <td class="py-3.5 px-3 text-sm text-slate-700 whitespace-nowrap">
                <i class="fa-solid fa-user-doctor text-slate-400 mr-1"></i> {$doctor}
            </td>
            <td class="py-3.5 px-3 text-xs text-slate-500 whitespace-nowrap">{$dateDisp}</td>
            <td class="py-3.5 px-3 whitespace-nowrap">{$statusBadge}</td>
            <td class="py-3.5 px-3 text-right whitespace-nowrap">
                <div class="inline-flex items-center gap-1.5">
                    <a href="/portals/imaging/results.php?scan_no={$q}" class="btn btn-primary text-xs px-2.5 py-1 font-bold shadow-2xs" title="Enter / Edit MRI Report">
                        <i class="fa-solid fa-file-pen mr-1"></i> Enter Report
                    </a>
                    <a href="/portals/imaging/reports.php?scan_no={$q}" class="btn btn-secondary text-xs px-2 py-1 font-bold" title="Print Full Letterhead Report">
                        <i class="fa-solid fa-file-waveform text-indigo-600 mr-1"></i> View Report
                    </a>
                    <a href="/portals/imaging/receipt.php?scan_no={$q}" class="btn btn-secondary text-xs px-2 py-1" title="Print Token Slip">
                        <i class="fa-solid fa-receipt text-slate-600"></i>
                    </a>
                </div>
            </td>
        </tr>
TR;
    }
}

$headerActions = <<<HTML
<div class="flex items-center gap-2">
    <a href="/portals/imaging/new-scan.php?modality=mri" class="btn btn-primary text-xs py-2 px-3">
        <i class="fa-solid fa-plus mr-1"></i> Book MRI Patient
    </a>
    <a href="/portals/imaging/results.php" class="btn btn-secondary text-xs py-2 px-3">
        <i class="fa-solid fa-notes-medical mr-1"></i> All Findings
    </a>
</div>
HTML;

$content = page_header(
    'MRI Workstation',
    'Department of Magnetic Resonance Imaging — High-field MRI queue, neuro, musculoskeletal & vascular imaging templates, and printouts.',
    $headerActions,
    'fa-solid fa-magnet'
);

$content .= <<<HTML
<div class="space-y-5">

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Total MRI Studies</span>
                <span class="text-2xl font-black text-slate-900 mt-1 block">{$totalCount}</span>
                <span class="text-[11px] text-slate-500 font-medium">Recorded in PACS</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-magnet"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Pending Reporting</span>
                <span class="text-2xl font-black text-amber-600 mt-1 block">{$pendingCount}</span>
                <span class="text-[11px] text-amber-700 font-medium">Awaiting Consultant Review</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-clock"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Verified &amp; Reported</span>
                <span class="text-2xl font-black text-emerald-600 mt-1 block">{$reportedCount}</span>
                <span class="text-[11px] text-emerald-700 font-medium">Ready for Patient Collection</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Scanner Modality</span>
                <span class="text-sm font-extrabold text-indigo-900 mt-1 block">1.5T Superconducting</span>
                <span class="text-[11px] text-slate-500 font-medium">DICOM / PACS Active</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-slate-900 text-[#c2f13c] flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-server"></i>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="p-4 bg-white rounded-2xl border border-slate-200 shadow-2xs flex flex-wrap items-center justify-between gap-3">
        <div class="relative flex-1 min-w-[15rem]">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <input type="text" id="mri-search" placeholder="Search MRI patient by name, scan number, study or doctor..." class="field text-xs pl-8 w-full">
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="filterMriStatus('all')" class="mri-filter-btn px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-900 text-white" data-status="all">All ({$totalCount})</button>
            <button type="button" onclick="filterMriStatus('pending')" class="mri-filter-btn px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 text-slate-700 hover:bg-slate-200" data-status="pending">Pending ({$pendingCount})</button>
            <button type="button" onclick="filterMriStatus('reported')" class="mri-filter-btn px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 text-slate-700 hover:bg-slate-200" data-status="reported">Reported ({$reportedCount})</button>
        </div>
    </div>

    <!-- MRI Table Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-magnet text-indigo-600"></i>
                <span>MRI Patient Queue &amp; Examination Records</span>
            </h3>
            <span class="text-xs text-slate-400">Department of Magnetic Resonance</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                        <th class="py-3 px-3">Scan No</th>
                        <th class="py-3 px-3">Patient Name</th>
                        <th class="py-3 px-3">Study / Protocol</th>
                        <th class="py-3 px-3">Reporting Radiologist</th>
                        <th class="py-3 px-3">Date</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="mri-table-body">
                    {$tableRowsHtml}
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('mri-search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            const rows = document.querySelectorAll('.mri-row');
            rows.forEach(r => {
                const searchData = (r.getAttribute('data-search') || '').toLowerCase();
                r.style.display = searchData.includes(q) ? '' : 'none';
            });
        });
    }
});

function filterMriStatus(status) {
    document.querySelectorAll('.mri-filter-btn').forEach(btn => {
        btn.classList.remove('bg-slate-900', 'text-white');
        btn.classList.add('bg-slate-100', 'text-slate-700');
    });
    const activeBtn = document.querySelector(`.mri-filter-btn[data-status="\${status}"]`);
    if (activeBtn) {
        activeBtn.classList.remove('bg-slate-100', 'text-slate-700');
        activeBtn.classList.add('bg-slate-900', 'text-white');
    }
    const rows = document.querySelectorAll('.mri-row');
    rows.forEach(r => {
        if (status === 'all') {
            r.style.display = '';
        } else if (status === 'reported') {
            r.style.display = r.innerHTML.includes('Reported') ? '' : 'none';
        } else {
            r.style.display = r.innerHTML.includes('Pending') ? '' : 'none';
        }
    });
}
</script>
HTML;

render_page('MRI Workstation', 'imaging', 'station-mri', $content);
