<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';

// Fetch all Echo scans from database
$allScans = result_repo()->getImagingScans($orgId);
$echoScans = array_values(array_filter($allScans, function ($s) {
    $m = strtolower((string)($s['modality'] ?? ''));
    $st = strtolower((string)($s['study'] ?? ''));
    return str_contains($m, 'echo') || str_contains($st, 'echo') || str_contains($st, 'echocardio') || str_contains($st, 'color doppler echo');
}));

$pendingCount = 0;
$reportedCount = 0;
foreach ($echoScans as $sc) {
    $st = strtolower((string)($sc['status'] ?? 'pending'));
    if (in_array($st, ['reported', 'completed', 'verified'], true)) {
        $reportedCount++;
    } else {
        $pendingCount++;
    }
}
$totalCount = count($echoScans);

$tableRowsHtml = '';
if (empty($echoScans)) {
    $tableRowsHtml = '<tr><td colspan="7" class="py-12 text-center text-slate-400 italic text-sm">No Echocardiography patients in queue today. <a href="/portals/new-scan.php?modality=echo" class="text-indigo-700 font-bold underline ml-1">Book new Echocardiography study</a></td></tr>';
} else {
    foreach ($echoScans as $idx => $sc) {
        $scanNo = (string)($sc['scan_no'] ?? '');
        $patient = (string)($sc['patient'] ?? 'Walk-in Patient');
        $study = (string)($sc['study'] ?? '2D Color Doppler Echocardiography');
        $doctor = (string)($sc['radiologist'] ?? $sc['doctor'] ?? 'Dr. Usman Malik (Cardiologist)');
        $dateDisp = !empty($sc['scan_date']) ? date('d M Y', strtotime($sc['scan_date'])) : date('d M Y');
        $status = strtolower((string)($sc['status'] ?? 'pending'));
        $isReported = in_array($status, ['reported', 'completed', 'verified'], true);

        $statusBadge = $isReported
            ? '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Reported</span>'
            : '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending Findings</span>';

        $q = urlencode($scanNo);
        $tableRowsHtml .= <<<TR
        <tr class="hover:bg-indigo-50/40 transition-colors border-b border-slate-100 echo-row" data-search="{$scanNo} {$patient} {$study} {$doctor}">
            <td class="py-3.5 px-3 font-mono font-bold text-indigo-900 whitespace-nowrap">
                <a href="/portals/reports.php?scan_no={$q}" class="hover:underline">{$scanNo}</a>
            </td>
            <td class="py-3.5 px-3">
                <div class="font-extrabold text-slate-900 text-sm">{$patient}</div>
                <div class="text-[11px] text-slate-400">Cardiology / Echo Station</div>
            </td>
            <td class="py-3.5 px-3">
                <div class="font-bold text-slate-800">{$study}</div>
                <span class="inline-block mt-0.5 px-1.5 py-0.2 rounded text-[10px] font-bold uppercase tracking-wider bg-indigo-100 text-indigo-800">Echocardiography</span>
            </td>
            <td class="py-3.5 px-3 text-xs text-slate-600 font-medium whitespace-nowrap">
                <i class="fa-solid fa-user-doctor text-indigo-600 mr-1"></i>{$doctor}
            </td>
            <td class="py-3.5 px-3 text-xs text-slate-500 whitespace-nowrap">{$dateDisp}</td>
            <td class="py-3.5 px-3 whitespace-nowrap">{$statusBadge}</td>
            <td class="py-3.5 px-3 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-1.5">
                    <a href="/portals/receipt.php?scan_no={$q}" class="btn btn-secondary text-xs px-2.5 py-1 font-semibold text-slate-700" title="Token Slip / Bill">
                        <i class="fa-solid fa-receipt mr-1 text-slate-500"></i> Slip
                    </a>
                    <a href="/portals/results.php?scan_no={$q}" class="btn btn-secondary text-xs px-2.5 py-1 font-semibold text-indigo-700 hover:bg-indigo-50" title="Edit Echo Measurements & Findings">
                        <i class="fa-solid fa-file-pen mr-1"></i> Enter Findings
                    </a>
                    <a href="/portals/reports.php?scan_no={$q}" class="btn btn-primary text-xs px-2.5 py-1 font-semibold" title="View Echo Report Dossier">
                        <i class="fa-solid fa-eye mr-1"></i> Report
                    </a>
                </div>
            </td>
        </tr>
TR;
    }
}

$content = <<<HTML
<div class="space-y-6">
    <!-- Header banner -->
    <div class="bg-gradient-to-r from-indigo-900 via-indigo-800 to-slate-900 rounded-3xl p-6 text-white shadow-xl flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-xs font-semibold text-indigo-200 border border-white/10 mb-2">
                <i class="fa-solid fa-heart-pulse text-indigo-300"></i> Cardiology &amp; Cardiac Ultrasound Workstation
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Echocardiography (Echo)</h1>
            <p class="text-sm text-indigo-200 mt-1 max-w-xl">Transthoracic 2D Echo, M-Mode &amp; Color Doppler assessment of cardiac chambers, valves, and ejection fraction (EF).</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="/portals/new-scan.php?modality=echo" class="btn bg-indigo-500 hover:bg-indigo-400 text-white font-extrabold text-sm px-4 py-2.5 rounded-2xl shadow-lg border-0 transition-all flex items-center gap-2">
                <i class="fa-solid fa-plus-circle"></i> New Echo Booking
            </a>
            <a href="/portals/results.php" class="btn bg-white/10 hover:bg-white/20 text-white font-bold text-sm px-4 py-2.5 rounded-2xl border border-white/20 transition-all flex items-center gap-2">
                <i class="fa-solid fa-notes-medical"></i> All Findings Pad
            </a>
        </div>
    </div>

    <!-- Quick KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-card flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Echo Studies</p>
                <p class="text-2xl font-black text-slate-900 mt-1">{$totalCount}</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center text-xl font-bold">
                <i class="fa-solid fa-heart-pulse"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-card flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Pending Findings</p>
                <p class="text-2xl font-black text-amber-600 mt-1">{$pendingCount}</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-bold">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-card flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Reported &amp; Stamped</p>
                <p class="text-2xl font-black text-emerald-600 mt-1">{$reportedCount}</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>
    </div>

    <!-- Echo Patients Table -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-bold">
                    <i class="fa-solid fa-heart-pulse"></i>
                </span>
                <div>
                    <h2 class="text-base font-extrabold text-slate-900 tracking-tight">Echo Clinical Queue</h2>
                    <p class="text-xs text-slate-500">Live cardiology patient queue</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <input type="text" id="echo-search" onkeyup="filterEchoTable()" placeholder="Search echo patient, doctor..." class="text-xs py-1.5 px-3 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:border-indigo-500 outline-none w-48 sm:w-64 transition-all">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">
                        <th class="py-3 px-3">Echo No</th>
                        <th class="py-3 px-3">Patient</th>
                        <th class="py-3 px-3">Study / Modality</th>
                        <th class="py-3 px-3">Reporting Doctor</th>
                        <th class="py-3 px-3">Study Date</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="echo-table-body">
                    {$tableRowsHtml}
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function filterEchoTable() {
    const q = (document.getElementById('echo-search').value || '').toLowerCase();
    const rows = document.querySelectorAll('.echo-row');
    rows.forEach(r => {
        const text = (r.getAttribute('data-search') || '').toLowerCase();
        r.style.display = text.includes(q) ? '' : 'none';
    });
}
</script>
HTML;

render_page('Echocardiography Station', 'imaging', 'station-echo', $content);
