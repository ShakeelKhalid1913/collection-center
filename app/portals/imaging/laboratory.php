<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';

// Fetch all lab entries for this organization
$allEntries = lab_repo()->getAll($orgId);

// Filter entries that contain pathology/laboratory tests (or general lab entries)
$labEntries = array_values(array_filter($allEntries, function ($e) {
    $tests = (string)($e['tests'] ?? '');
    // If it has tests, check if at least one is pathology or not pure imaging
    $testArr = array_filter(array_map('trim', explode(',', $tests)));
    if (empty($testArr)) {
        return true;
    }
    foreach ($testArr as $t) {
        $meta = get_department_meta($t);
        if (empty($meta['is_imaging']) || ($meta['id'] ?? '') === 'lab') {
            return true;
        }
    }
    return false;
}));

// If none found, show all entries as laboratory orders
if (empty($labEntries) && !empty($allEntries)) {
    $labEntries = $allEntries;
}

$pendingCount = 0;
$completedCount = 0;
$todayCount = 0;
$todayDate = date('Y-m-d');

foreach ($labEntries as $le) {
    $cAt = (string)($le['created_at'] ?? '');
    if (str_starts_with($cAt, $todayDate)) {
        $todayCount++;
    }
    $st = strtolower((string)($le['status'] ?? 'pending'));
    if (in_array($st, ['completed', 'verified', 'reported'], true)) {
        $completedCount++;
    } else {
        $pendingCount++;
    }
}
$totalCount = count($labEntries);

$tableRowsHtml = '';
if (empty($labEntries)) {
    $tableRowsHtml = '<tr><td colspan="7" class="py-12 text-center text-slate-400 italic text-sm">No Laboratory patients in queue today. <a href="/portals/imaging/new-scan.php" class="text-emerald-700 font-bold underline ml-1">Book new test</a></td></tr>';
} else {
    foreach ($labEntries as $idx => $le) {
        $labNo = (string)($le['lab_no'] ?? '');
        $patient = (string)($le['patient_name'] ?? 'Walk-in Patient');
        $patientId = (string)($le['patient_id'] ?? '—');
        $tests = (string)($le['tests'] ?? 'Pathology Test');
        $doctor = (string)($le['doctor'] ?? 'Walk-in / Self');
        $dateDisp = !empty($le['created_at']) ? date('d M Y, h:i A', strtotime($le['created_at'])) : date('d M Y');
        $status = strtolower((string)($le['status'] ?? 'pending'));
        $sampleStatus = strtolower((string)($le['sample_status'] ?? 'pending'));
        $isCompleted = in_array($status, ['completed', 'verified', 'reported'], true);

        $statusBadge = $isCompleted
            ? '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Verified</span>'
            : '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending Results</span>';

        $sampleBadge = match ($sampleStatus) {
            'collected', 'received' => '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"><i class="fa-solid fa-vial-circle-check text-[10px]"></i> Received</span>',
            default => '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-100 text-slate-600 border border-slate-200"><i class="fa-solid fa-clock text-[10px]"></i> Sample Pending</span>',
        };

        $q = urlencode($labNo);
        $testPills = '';
        foreach (array_slice(array_filter(array_map('trim', explode(',', $tests))), 0, 4) as $tn) {
            $testPills .= '<span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 mr-1 mb-0.5">' . e($tn) . '</span>';
        }

        $tableRowsHtml .= <<<TR
        <tr class="hover:bg-emerald-50/40 transition-colors border-b border-slate-100 lab-row" data-search="{$labNo} {$patient} {$tests} {$doctor}">
            <td class="py-3.5 px-3 font-mono font-bold text-emerald-800 whitespace-nowrap">
                <a href="/portals/main-lab/reports/preview.php?lab_no={$q}" class="hover:underline">{$labNo}</a>
            </td>
            <td class="py-3.5 px-3">
                <div class="font-extrabold text-slate-900 text-sm">{$patient}</div>
                <div class="text-[11px] text-slate-400 font-mono">MR: {$patientId}</div>
            </td>
            <td class="py-3.5 px-3">
                <div class="max-w-xs">{$testPills}</div>
                <div class="text-[10px] text-slate-400 mt-0.5">Ref: {$doctor}</div>
            </td>
            <td class="py-3.5 px-3 whitespace-nowrap">
                {$sampleBadge}
            </td>
            <td class="py-3.5 px-3 whitespace-nowrap">
                {$statusBadge}
                <div class="text-[10px] text-slate-400 mt-0.5">{$dateDisp}</div>
            </td>
            <td class="py-3.5 px-3 text-right whitespace-nowrap">
                <div class="inline-flex items-center gap-1">
                    <a href="/portals/main-lab/results/entry.php?lab_no={$q}" class="btn btn-secondary text-xs px-2.5 py-1 font-bold text-emerald-800 hover:bg-emerald-100" title="Enter Results">
                        <i class="fa-solid fa-flask mr-1"></i> Results
                    </a>
                    <a href="/portals/main-lab/reports/preview.php?lab_no={$q}" class="btn btn-primary text-xs px-2.5 py-1 font-bold bg-emerald-600 hover:bg-emerald-700 text-white" title="View Lab Report">
                        <i class="fa-solid fa-file-lines mr-1"></i> Report
                    </a>
                    <a href="/portals/imaging/receipt.php?lab_no={$q}" class="btn btn-secondary text-xs px-2 py-1 font-semibold text-slate-600 hover:bg-slate-100" title="Patient Bill">
                        <i class="fa-solid fa-receipt"></i>
                    </a>
                </div>
            </td>
        </tr>
TR;
    }
}

$headerActions = <<<HTML
<div class="flex items-center gap-2">
    <a href="/portals/imaging/new-scan.php" class="btn btn-primary text-xs font-bold py-2 px-3 bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm">
        <i class="fa-solid fa-plus mr-1"></i> Book Diagnostic Order
    </a>
    <a href="/portals/imaging/dashboard.php" class="btn btn-secondary text-xs font-semibold py-2 px-3">
        <i class="fa-solid fa-gauge-high mr-1"></i> Center Dashboard
    </a>
</div>
HTML;

$content = page_header('Laboratory Workstation', 'Pathology, Biochemistry, Hematology & Serology orders inside the Diagnostic Center.', $headerActions, 'fa-solid fa-flask');

$content .= <<<HTML
<!-- Workstation Stat Strip -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
    <div class="p-4 bg-white rounded-2xl border border-slate-200/90 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Orders</span>
            <div class="text-2xl font-black text-slate-900 mt-0.5">{$totalCount}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
            <i class="fa-solid fa-flask"></i>
        </div>
    </div>
    <div class="p-4 bg-white rounded-2xl border border-slate-200/90 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Pending Results</span>
            <div class="text-2xl font-black text-amber-600 mt-0.5">{$pendingCount}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg">
            <i class="fa-solid fa-clock"></i>
        </div>
    </div>
    <div class="p-4 bg-white rounded-2xl border border-slate-200/90 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Verified Reports</span>
            <div class="text-2xl font-black text-emerald-600 mt-0.5">{$completedCount}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
            <i class="fa-solid fa-circle-check"></i>
        </div>
    </div>
    <div class="p-4 bg-white rounded-2xl border border-slate-200/90 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Today's Visits</span>
            <div class="text-2xl font-black text-slate-900 mt-0.5">{$todayCount}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg">
            <i class="fa-solid fa-calendar-day"></i>
        </div>
    </div>
</div>

<!-- Department Navigation Pills -->
<div class="flex flex-wrap items-center gap-2 mb-4 p-2 bg-white rounded-2xl border border-slate-200/90 shadow-2xs">
    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider px-2">Workstations:</span>
    <a href="/portals/imaging/laboratory.php" class="px-3 py-1.5 rounded-xl bg-emerald-600 text-white font-bold text-xs shadow-sm flex items-center gap-1.5">
        <i class="fa-solid fa-flask"></i> Laboratory
    </a>
    <a href="/portals/imaging/ultrasound.php" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-teal-50 text-slate-700 hover:text-teal-800 font-semibold text-xs transition-colors flex items-center gap-1.5">
        <i class="fa-solid fa-wave-square text-teal-600"></i> Ultrasound
    </a>
    <a href="/portals/imaging/xray.php" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-blue-800 font-semibold text-xs transition-colors flex items-center gap-1.5">
        <i class="fa-solid fa-x-ray text-blue-600"></i> X-Ray
    </a>
    <a href="/portals/imaging/ecg.php" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-rose-50 text-slate-700 hover:text-rose-800 font-semibold text-xs transition-colors flex items-center gap-1.5">
        <i class="fa-solid fa-heart-pulse text-rose-600"></i> ECG
    </a>
    <a href="/portals/imaging/ct.php" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-purple-50 text-slate-700 hover:text-purple-800 font-semibold text-xs transition-colors flex items-center gap-1.5">
        <i class="fa-solid fa-laptop-medical text-purple-600"></i> CT Scan
    </a>
    <a href="/portals/imaging/mri.php" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-indigo-50 text-slate-700 hover:text-indigo-800 font-semibold text-xs transition-colors flex items-center gap-1.5">
        <i class="fa-solid fa-magnet text-indigo-600"></i> MRI
    </a>
</div>

<!-- Queue Table Card -->
<div class="bg-white rounded-3xl border border-slate-200/90 shadow-card overflow-hidden">
    <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50">
        <div class="flex items-center gap-2">
            <span class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-sm">
                <i class="fa-solid fa-vial"></i>
            </span>
            <div>
                <h3 class="text-sm sm:text-base font-extrabold text-slate-900 leading-tight">Laboratory Orders &amp; Testing Queue</h3>
                <span class="text-xs text-slate-500">Live pathology manifest</span>
            </div>
        </div>
        <div class="w-full sm:w-72">
            <input type="text" id="lab-search" placeholder="Filter by patient, Lab No, test..." 
                   class="w-full px-3 py-1.5 text-xs rounded-xl border border-slate-300 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                   onkeyup="filterLabTable(this.value)">
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead class="bg-slate-50 border-b border-slate-100 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                <tr>
                    <th class="py-3 px-3">Lab No</th>
                    <th class="py-3 px-3">Patient</th>
                    <th class="py-3 px-3">Tests Ordered</th>
                    <th class="py-3 px-3">Specimen Status</th>
                    <th class="py-3 px-3">Results Status</th>
                    <th class="py-3 px-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="lab-tbody" class="divide-y divide-slate-100">
                {$tableRowsHtml}
            </tbody>
        </table>
    </div>
</div>

<script>
function filterLabTable(q) {
    q = (q || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.lab-row');
    rows.forEach(r => {
        const text = (r.getAttribute('data-search') || '').toLowerCase();
        r.style.display = (text.indexOf(q) !== -1) ? '' : 'none';
    });
}
</script>
HTML;

render_page('Laboratory Station', 'imaging', 'station-lab', $content);
