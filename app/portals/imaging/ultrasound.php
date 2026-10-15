<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';

// Fetch all ultrasound scans from database
$allScans = result_repo()->getImagingScans($orgId);
$usScans = array_values(array_filter($allScans, function ($s) {
    $m = strtolower((string)($s['modality'] ?? ''));
    $st = strtolower((string)($s['study'] ?? ''));
    return str_contains($m, 'ultra') || str_contains($m, 'us') || str_contains($st, 'ultra') || str_contains($st, 'usg') || str_contains($st, 'pelvis') || str_contains($st, 'abdomen') || str_contains($st, 'kub') || str_contains($st, 'doppler') || str_contains($st, 'anomaly');
}));

$pendingCount = 0;
$reportedCount = 0;
foreach ($usScans as $sc) {
    $st = strtolower((string)($sc['status'] ?? 'pending'));
    if (in_array($st, ['reported', 'completed', 'verified'], true)) {
        $reportedCount++;
    } else {
        $pendingCount++;
    }
}
$totalCount = count($usScans);

$tableRowsHtml = '';
if (empty($usScans)) {
    $tableRowsHtml = '<tr><td colspan="7" class="py-12 text-center text-slate-400 italic text-sm">No Ultrasound patients in queue today. <a href="/portals/imaging/new-scan.php?modality=us" class="text-teal-700 font-bold underline ml-1">Book new ultrasound</a></td></tr>';
} else {
    foreach ($usScans as $idx => $sc) {
        $scanNo = (string)($sc['scan_no'] ?? '');
        $patient = (string)($sc['patient'] ?? 'Walk-in Patient');
        $study = (string)($sc['study'] ?? 'Ultrasound Study');
        $doctor = (string)($sc['radiologist'] ?? $sc['doctor'] ?? 'Dr. Imran Sheikh');
        $dateDisp = !empty($sc['scan_date']) ? date('d M Y', strtotime($sc['scan_date'])) : date('d M Y');
        $status = strtolower((string)($sc['status'] ?? 'pending'));
        $isReported = in_array($status, ['reported', 'completed', 'verified'], true);

        $statusBadge = $isReported
            ? '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Reported</span>'
            : '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending Findings</span>';

        $q = urlencode($scanNo);
        $tableRowsHtml .= <<<TR
        <tr class="hover:bg-teal-50/40 transition-colors border-b border-slate-100 us-row" data-search="{$scanNo} {$patient} {$study} {$doctor}">
            <td class="py-3.5 px-3 font-mono font-bold text-teal-800 whitespace-nowrap">
                <a href="/portals/imaging/reports.php?scan_no={$q}" class="hover:underline">{$scanNo}</a>
            </td>
            <td class="py-3.5 px-3">
                <div class="font-extrabold text-slate-900 text-sm">{$patient}</div>
                <div class="text-[11px] text-slate-400">Ultrasound Patient Queue</div>
            </td>
            <td class="py-3.5 px-3">
                <div class="font-bold text-slate-800">{$study}</div>
                <span class="inline-block mt-0.5 px-1.5 py-0.2 rounded text-[10px] font-bold uppercase tracking-wider bg-teal-100 text-teal-800">Ultrasound</span>
            </td>
            <td class="py-3.5 px-3 text-sm text-slate-700 whitespace-nowrap">
                <i class="fa-solid fa-user-doctor text-slate-400 mr-1"></i> {$doctor}
            </td>
            <td class="py-3.5 px-3 text-xs text-slate-500 whitespace-nowrap">{$dateDisp}</td>
            <td class="py-3.5 px-3 whitespace-nowrap">{$statusBadge}</td>
            <td class="py-3.5 px-3 text-right whitespace-nowrap">
                <div class="inline-flex items-center gap-1.5">
                    <a href="/portals/imaging/results.php?scan_no={$q}" class="btn btn-primary text-xs px-2.5 py-1 font-bold shadow-2xs" title="Enter / Edit Ultrasound Report">
                        <i class="fa-solid fa-file-pen mr-1"></i> Enter Report
                    </a>
                    <a href="/portals/imaging/reports.php?scan_no={$q}" class="btn btn-secondary text-xs px-2 py-1 font-bold" title="Print Full Letterhead Report">
                        <i class="fa-solid fa-file-waveform text-teal-600 mr-1"></i> View Report
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
    <a href="/portals/imaging/new-scan.php?modality=us" class="btn btn-primary text-xs py-2 px-3">
        <i class="fa-solid fa-plus mr-1"></i> Book Ultrasound Patient
    </a>
    <a href="/portals/imaging/results.php" class="btn btn-secondary text-xs py-2 px-3">
        <i class="fa-solid fa-notes-medical mr-1"></i> All Findings
    </a>
</div>
HTML;

$content = page_header(
    'Ultrasound Workstation',
    'Department of Sonology & Ultrasound — Patient queue, examination reports, organ templates, and printouts.',
    $headerActions,
    'fa-solid fa-wave-square'
);

// Stat cards
$content .= <<<HTML
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-sm flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center text-lg">
            <i class="fa-solid fa-wave-square"></i>
        </div>
        <div>
            <div class="text-2xl font-black text-slate-900">{$totalCount}</div>
            <div class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Total Ultrasound Studies</div>
        </div>
    </div>
    <div class="bg-white p-4 rounded-2xl border border-amber-200/80 shadow-sm flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center text-lg">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div>
            <div class="text-2xl font-black text-amber-900">{$pendingCount}</div>
            <div class="text-xs text-amber-700 font-semibold uppercase tracking-wider">Pending Findings</div>
        </div>
    </div>
    <div class="bg-white p-4 rounded-2xl border border-emerald-200/80 shadow-sm flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-lg">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div>
            <div class="text-2xl font-black text-emerald-900">{$reportedCount}</div>
            <div class="text-xs text-emerald-700 font-semibold uppercase tracking-wider">Reports Ready &amp; Printed</div>
        </div>
    </div>
</div>

<!-- Search & Filter bar -->
<div class="mb-4 bg-white p-3.5 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-3">
    <div class="relative flex-1 min-w-[16rem]">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
        <input type="text" id="us-search-input" oninput="filterUsTable(this.value)" placeholder="Search Ultrasound queue by patient name, scan number, or doctor..." class="field pl-9 text-xs w-full">
    </div>
    <div class="text-xs text-slate-500 font-medium">
        Showing <strong class="text-slate-800" id="us-count-label">{$totalCount}</strong> ultrasound patients
    </div>
</div>

<!-- Table Card -->
<div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[10px] font-bold">
                    <th class="py-3 px-3">Scan / Token No</th>
                    <th class="py-3 px-3">Patient Name</th>
                    <th class="py-3 px-3">Examination Study</th>
                    <th class="py-3 px-3">Sonologist / Doctor</th>
                    <th class="py-3 px-3">Scan Date</th>
                    <th class="py-3 px-3">Status</th>
                    <th class="py-3 px-3 text-right">Workstation Action</th>
                </tr>
            </thead>
            <tbody id="us-table-body">
                {$tableRowsHtml}
            </tbody>
        </table>
    </div>
</div>

<script>
function filterUsTable(q) {
    const query = (q || '').trim().toLowerCase();
    const rows = document.querySelectorAll('.us-row');
    let visible = 0;
    rows.forEach(r => {
        const text = (r.getAttribute('data-search') || '').toLowerCase();
        if (!query || text.includes(query)) {
            r.style.display = '';
            visible++;
        } else {
            r.style.display = 'none';
        }
    });
    const lbl = document.getElementById('us-count-label');
    if (lbl) lbl.innerText = visible;
}
</script>
HTML;

render_page('Ultrasound Workstation', 'imaging', 'station-us', $content);
