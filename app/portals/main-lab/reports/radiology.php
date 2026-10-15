<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';
require_once __DIR__ . '/../../../includes/documents.php';

require_auth();

$user = current_user();
$orgId = $user['organization_id'] ?? 'ORG-001';
$portal = current_portal();

// Fetch entries from lab_entries
$allLabEntries = lab_repo()->getAll($orgId, 500);

// Filter specifically for Imaging / Radiology studies (Ultrasound, X-Ray, ECG, CT, etc.)
$imagingEntries = [];
$modalityCounts = [
    'all' => 0,
    'ultrasound' => 0,
    'xray' => 0,
    'ecg' => 0,
    'ct' => 0,
    'other' => 0,
];

foreach ($allLabEntries as $e) {
    $testsStr = (string)($e['tests'] ?? '');
    $testsLower = strtolower($testsStr);

    $isImaging = preg_match('/(ultrasound|usg|sonograph|doppler|pelvis|abdomen|kub|x-ray|xray|radiograph|chest pa|ecg|ekg|ct scan|computed tomography|mri)/i', $testsStr)
        || is_narrative_imaging_test($testsStr);

    if (!$isImaging) {
        continue;
    }

    // Classify primary modality
    $modality = 'other';
    $modalityLabel = 'Radiology';
    $modalityIcon = 'fa-solid fa-radiation text-slate-600';
    $modalityBadgeClass = 'bg-slate-100 text-slate-800 border-slate-200';

    if (preg_match('/(ultrasound|usg|sonograph|doppler|pelvis|kub)/i', $testsStr)) {
        $modality = 'ultrasound';
        $modalityLabel = 'Ultrasound';
        $modalityIcon = 'fa-solid fa-wave-square text-teal-600';
        $modalityBadgeClass = 'bg-teal-50 text-teal-800 border-teal-200';
        $modalityCounts['ultrasound']++;
    } elseif (preg_match('/(x-ray|xray|radiograph|chest pa)/i', $testsStr)) {
        $modality = 'xray';
        $modalityLabel = 'X-Ray';
        $modalityIcon = 'fa-solid fa-x-ray text-blue-600';
        $modalityBadgeClass = 'bg-blue-50 text-blue-800 border-blue-200';
        $modalityCounts['xray']++;
    } elseif (preg_match('/(ecg|ekg)/i', $testsStr)) {
        $modality = 'ecg';
        $modalityLabel = 'ECG';
        $modalityIcon = 'fa-solid fa-heart-pulse text-rose-600';
        $modalityBadgeClass = 'bg-rose-50 text-rose-800 border-rose-200';
        $modalityCounts['ecg']++;
    } elseif (preg_match('/(ct|computed tomography)/i', $testsStr)) {
        $modality = 'ct';
        $modalityLabel = 'CT Scan';
        $modalityIcon = 'fa-solid fa-laptop-medical text-purple-600';
        $modalityBadgeClass = 'bg-purple-50 text-purple-800 border-purple-200';
        $modalityCounts['ct']++;
    } else {
        $modalityCounts['other']++;
    }

    $modalityCounts['all']++;

    $imagingEntries[] = [
        'lab_no' => (string)($e['lab_no'] ?? ''),
        'patient_name' => (string)($e['patient_name'] ?? $e['patient'] ?? 'Walk-in Patient'),
        'patient_id' => (string)($e['patient_id'] ?? ''),
        'age' => (string)($e['age'] ?? '—'),
        'gender' => (string)($e['gender'] ?? '—'),
        'phone' => (string)($e['phone'] ?? '—'),
        'doctor' => (string)($e['doctor'] ?? 'Dr. Imran Sheikh'),
        'tests' => $testsStr,
        'status' => (string)($e['status'] ?? 'pending'),
        'created_at' => $e['created_at'] ?? $e['date'] ?? null,
        'modality' => $modality,
        'modality_label' => $modalityLabel,
        'modality_icon' => $modalityIcon,
        'modality_badge_class' => $modalityBadgeClass,
    ];
}

// Check dedicated imaging_scans table as well so nothing is missed
$dedicatedScans = result_repo()->getImagingScans($orgId);
$existingLabNos = array_column($imagingEntries, 'lab_no');

foreach ($dedicatedScans as $sc) {
    $scNo = (string)($sc['scan_no'] ?? '');
    if (in_array($scNo, $existingLabNos, true)) {
        continue;
    }

    $scMod = strtolower((string)($sc['modality'] ?? 'us'));
    $modality = 'other';
    $modalityLabel = 'Radiology';
    $modalityIcon = 'fa-solid fa-radiation text-slate-600';
    $modalityBadgeClass = 'bg-slate-100 text-slate-800 border-slate-200';

    if (str_contains($scMod, 'us') || str_contains($scMod, 'ultra')) {
        $modality = 'ultrasound';
        $modalityLabel = 'Ultrasound';
        $modalityIcon = 'fa-solid fa-wave-square text-teal-600';
        $modalityBadgeClass = 'bg-teal-50 text-teal-800 border-teal-200';
        $modalityCounts['ultrasound']++;
    } elseif (str_contains($scMod, 'xray') || str_contains($scMod, 'x-ray')) {
        $modality = 'xray';
        $modalityLabel = 'X-Ray';
        $modalityIcon = 'fa-solid fa-x-ray text-blue-600';
        $modalityBadgeClass = 'bg-blue-50 text-blue-800 border-blue-200';
        $modalityCounts['xray']++;
    } elseif (str_contains($scMod, 'ecg')) {
        $modality = 'ecg';
        $modalityLabel = 'ECG';
        $modalityIcon = 'fa-solid fa-heart-pulse text-rose-600';
        $modalityBadgeClass = 'bg-rose-50 text-rose-800 border-rose-200';
        $modalityCounts['ecg']++;
    } elseif (str_contains($scMod, 'ct')) {
        $modality = 'ct';
        $modalityLabel = 'CT Scan';
        $modalityIcon = 'fa-solid fa-laptop-medical text-purple-600';
        $modalityBadgeClass = 'bg-purple-50 text-purple-800 border-purple-200';
        $modalityCounts['ct']++;
    } else {
        $modalityCounts['other']++;
    }

    $modalityCounts['all']++;

    $imagingEntries[] = [
        'lab_no' => $scNo,
        'patient_name' => (string)($sc['patient'] ?? 'Patient'),
        'patient_id' => (string)($sc['patient_id'] ?? ''),
        'age' => '—',
        'gender' => '—',
        'phone' => '—',
        'doctor' => (string)($sc['radiologist'] ?? $sc['doctor'] ?? 'Dr. Imran Sheikh'),
        'tests' => (string)($sc['study'] ?? 'Scan Study'),
        'status' => (string)($sc['status'] ?? 'pending'),
        'created_at' => $sc['scan_date'] ?? null,
        'modality' => $modality,
        'modality_label' => $modalityLabel,
        'modality_icon' => $modalityIcon,
        'modality_badge_class' => $modalityBadgeClass,
    ];
}

$headerActions = <<<HTML
<div class="flex items-center gap-2">
    <a href="/portals/imaging/new-scan.php" class="btn btn-secondary text-xs py-2 px-3">
        <i class="fa-solid fa-plus mr-1"></i> New Scan Booking
    </a>
    <a href="/portals/main-lab/tests/estimate.php" class="btn btn-secondary text-xs py-2 px-3">
        <i class="fa-solid fa-calculator mr-1"></i> Test Estimate
    </a>
</div>
HTML;

$content = page_header(
    'Ultrasound & Radiology Reports Hub',
    'Dedicated viewing, editing, and printing station for Ultrasound, X-Ray, ECG, and CT Scan examinations.',
    $headerActions,
    'fa-solid fa-file-waveform'
);

// Top Stat Cards
$cntAll = $modalityCounts['all'];
$cntUs = $modalityCounts['ultrasound'];
$cntXr = $modalityCounts['xray'];
$cntEcg = $modalityCounts['ecg'];

$content .= <<<HTML
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div onclick="filterModality('all')" class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-2xs hover:border-slate-400 cursor-pointer transition-all">
        <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">All Imaging Reports</div>
        <div class="text-2xl font-black text-slate-900 font-mono mt-1">{$cntAll}</div>
    </div>
    <div onclick="filterModality('ultrasound')" class="bg-teal-50/70 border border-teal-200 rounded-2xl p-4 shadow-2xs hover:border-teal-400 cursor-pointer transition-all">
        <div class="text-[11px] font-bold uppercase tracking-wider text-teal-800 flex items-center gap-1.5">
            <i class="fa-solid fa-wave-square text-teal-600"></i> Ultrasound Scans
        </div>
        <div class="text-2xl font-black text-teal-900 font-mono mt-1">{$cntUs}</div>
    </div>
    <div onclick="filterModality('xray')" class="bg-blue-50/70 border border-blue-200 rounded-2xl p-4 shadow-2xs hover:border-blue-400 cursor-pointer transition-all">
        <div class="text-[11px] font-bold uppercase tracking-wider text-blue-800 flex items-center gap-1.5">
            <i class="fa-solid fa-x-ray text-blue-600"></i> X-Ray Studies
        </div>
        <div class="text-2xl font-black text-blue-900 font-mono mt-1">{$cntXr}</div>
    </div>
    <div onclick="filterModality('ecg')" class="bg-rose-50/70 border border-rose-200 rounded-2xl p-4 shadow-2xs hover:border-rose-400 cursor-pointer transition-all">
        <div class="text-[11px] font-bold uppercase tracking-wider text-rose-800 flex items-center gap-1.5">
            <i class="fa-solid fa-heart-pulse text-rose-600"></i> ECG Tracings
        </div>
        <div class="text-2xl font-black text-rose-900 font-mono mt-1">{$cntEcg}</div>
    </div>
</div>

<!-- Main Table Container -->
<div class="bg-white border border-slate-200/90 rounded-3xl p-5 sm:p-6 shadow-card space-y-4">
    <!-- Controls Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-4 border-b border-slate-100">
        <!-- Modality Filter Tabs -->
        <div class="flex flex-wrap items-center gap-1.5 text-xs font-bold" id="modality-pills">
            <button type="button" onclick="filterModality('all')" id="pill-all" class="px-3 py-1.5 rounded-xl bg-slate-900 text-white shadow-2xs transition-all">
                All Reports ({$cntAll})
            </button>
            <button type="button" onclick="filterModality('ultrasound')" id="pill-ultrasound" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-teal-100 text-slate-700 hover:text-teal-900 transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-wave-square text-teal-600"></i> Ultrasound ({$cntUs})
            </button>
            <button type="button" onclick="filterModality('xray')" id="pill-xray" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-blue-100 text-slate-700 hover:text-blue-900 transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-x-ray text-blue-600"></i> X-Ray ({$cntXr})
            </button>
            <button type="button" onclick="filterModality('ecg')" id="pill-ecg" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-rose-100 text-slate-700 hover:text-rose-900 transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-heart-pulse text-rose-600"></i> ECG ({$cntEcg})
            </button>
        </div>

        <!-- Search Bar -->
        <div class="relative w-full md:w-72">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <input type="text" id="imaging-search" class="field text-xs pl-8 py-2 w-full" placeholder="Search patient name, lab no, doctor..." oninput="filterTable()">
        </div>
    </div>

    <!-- Table of Imaging Patients -->
    <div class="overflow-x-auto rounded-2xl border border-slate-200">
        <table class="w-full text-left border-collapse text-xs">
            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                <tr>
                    <th class="py-3 px-3">Lab / Scan No</th>
                    <th class="py-3 px-3">Patient Information</th>
                    <th class="py-3 px-3">Modality &amp; Study</th>
                    <th class="py-3 px-3">Doctor / Radiologist</th>
                    <th class="py-3 px-3">Date</th>
                    <th class="py-3 px-3">Status</th>
                    <th class="py-3 px-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="imaging-tbody" class="divide-y divide-slate-100 bg-white">
HTML;

if (empty($imagingEntries)) {
    $content .= '<tr><td colspan="7" class="py-12 text-center text-slate-400 italic">No ultrasound, X-ray, or ECG reports found yet. Book a new scan to start.</td></tr>';
} else {
    foreach ($imagingEntries as $row) {
        $lNo = e($row['lab_no']);
        $pName = e($row['patient_name']);
        $ageGen = e(trim($row['age'] . ' / ' . $row['gender'], ' /'));
        $phone = e($row['phone']);
        $study = e($row['tests']);
        $doc = e($row['doctor']);
        $dateDisp = !empty($row['created_at']) ? date('d-M-Y', strtotime((string)$row['created_at'])) : '—';
        $modKey = e($row['modality']);
        $modBadge = '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border ' . $row['modality_badge_class'] . '"><i class="' . $row['modality_icon'] . '"></i> ' . e($row['modality_label']) . '</span>';
        
        $st = strtolower((string)$row['status']);
        $isDone = in_array($st, ['completed', 'verified', 'reported'], true);
        $statusBadge = $isDone
            ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Reported</span>'
            : '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending Findings</span>';

        $qLab = urlencode($row['lab_no']);

        $content .= <<<TR
        <tr class="hover:bg-slate-50/80 transition-colors imaging-row" data-modality="{$modKey}" data-search="{$lNo} {$pName} {$study} {$doc}">
            <td class="py-3 px-3 font-mono font-bold text-teal-800 whitespace-nowrap">
                <a href="/portals/main-lab/reports/preview.php?lab_no={$qLab}" class="hover:underline">{$lNo}</a>
            </td>
            <td class="py-3 px-3">
                <div class="font-extrabold text-slate-900 text-sm">{$pName}</div>
                <div class="text-[11px] text-slate-500 mt-0.5">{$ageGen} &middot; {$phone}</div>
            </td>
            <td class="py-3 px-3">
                <div class="font-bold text-slate-800">{$study}</div>
                <div class="mt-1">{$modBadge}</div>
            </td>
            <td class="py-3 px-3 font-medium text-slate-700 whitespace-nowrap">
                <i class="fa-solid fa-user-doctor text-slate-400 mr-1"></i> {$doc}
            </td>
            <td class="py-3 px-3 font-medium text-slate-500 whitespace-nowrap">{$dateDisp}</td>
            <td class="py-3 px-3 whitespace-nowrap">{$statusBadge}</td>
            <td class="py-3 px-3 text-right whitespace-nowrap">
                <div class="inline-flex items-center gap-1.5">
                    <a href="/portals/main-lab/reports/preview.php?lab_no={$qLab}" class="btn btn-primary text-xs px-2.5 py-1 font-bold shadow-2xs" title="View & Print Full Report">
                        <i class="fa-solid fa-file-waveform mr-1"></i> View Report
                    </a>
                    <a href="/portals/main-lab/results/entry.php?lab_no={$qLab}" class="btn btn-secondary text-xs px-2.5 py-1 font-bold" title="Edit Narrative Findings & Presets">
                        <i class="fa-solid fa-pen-to-square text-teal-600 mr-1"></i> Edit Findings
                    </a>
                    <a href="/portals/main-lab/lab-entries/edit.php?lab_no={$qLab}" class="btn btn-secondary text-xs px-2 py-1" title="Edit Patient Info or Bill">
                        <i class="fa-solid fa-user-pen text-slate-600"></i>
                    </a>
                </div>
            </td>
        </tr>
TR;
    }
}

$content .= <<<HTML
            </tbody>
        </table>
    </div>
</div>

<script>
let activeModality = 'all';

function filterModality(mod) {
    activeModality = mod;

    const pills = ['all', 'ultrasound', 'xray', 'ecg'];
    pills.forEach(p => {
        const el = document.getElementById('pill-' + p);
        if (!el) return;
        if (p === mod) {
            el.className = 'px-3 py-1.5 rounded-xl bg-slate-900 text-white shadow-2xs transition-all';
        } else {
            el.className = 'px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition-all flex items-center gap-1.5';
        }
    });

    filterTable();
}

function filterTable() {
    const q = (document.getElementById('imaging-search')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.imaging-row');

    rows.forEach(r => {
        const rowMod = r.getAttribute('data-modality') || '';
        const rowSearch = (r.getAttribute('data-search') || '').toLowerCase();

        const matchMod = (activeModality === 'all' || rowMod === activeModality);
        const matchSearch = (q === '' || rowSearch.includes(q));

        if (matchMod && matchSearch) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}
</script>
HTML;

render_page('Ultrasound & Radiology Reports', $portal, 'radiology-reports', $content);
