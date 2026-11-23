<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';

// Fetch live statistics
$allScans = result_repo()->getImagingScans($orgId);
$pendingScansCount = 0;
$reportedScansCount = 0;
foreach ($allScans as $sc) {
    $st = strtolower((string)($sc['status'] ?? 'pending'));
    if (in_array($st, ['reported', 'completed', 'verified'], true)) {
        $reportedScansCount++;
    } else {
        $pendingScansCount++;
    }
}

// Modality breakdown counts
$usCount = 0;
$xrCount = 0;
$ecgCount = 0;
$echoCount = 0;
$ctCount = 0;
$mriCount = 0;

foreach ($allScans as $sc) {
    $mod = strtolower((string)($sc['modality'] ?? ''));
    $std = strtolower((string)($sc['study'] ?? ''));
    if (str_contains($mod, 'ultra') || str_contains($mod, 'us') || str_contains($std, 'ultra') || str_contains($std, 'pelvis') || str_contains($std, 'abdomen')) $usCount++;
    elseif (str_contains($mod, 'xray') || str_contains($mod, 'x-ray') || str_contains($std, 'x-ray') || str_contains($std, 'chest')) $xrCount++;
    elseif (str_contains($mod, 'ecg') || str_contains($std, 'ecg')) $ecgCount++;
    elseif (str_contains($mod, 'echo') || str_contains($std, 'echo')) $echoCount++;
    elseif (str_contains($mod, 'ct') || str_contains($std, 'ct')) $ctCount++;
    elseif (str_contains($mod, 'mri') || str_contains($std, 'mri')) $mriCount++;
}

ob_start();
?>
<div class="space-y-8">

    <!-- Top Clean Overview Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-slate-200">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-hospital text-teal-600"></i>
                <span>Diagnostic Center Workstation</span>
            </h1>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Two primary clinical wings: <strong>Laboratory Department (Pathology)</strong> &amp; <strong>Radiology Department (Imaging)</strong></p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/portals/new-scan.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition-all hover:scale-[1.02]">
                <i class="fa-solid fa-plus text-[#c2f13c]"></i>
                <span>+ Book New Patient</span>
            </a>
            <a href="/portals/templates.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs border border-slate-300 shadow-xs transition-colors">
                <i class="fa-solid fa-bookmark text-teal-600"></i>
                <span>Report Templates</span>
            </a>
            <a href="/portals/report-history.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs border border-slate-300 shadow-xs transition-colors">
                <i class="fa-solid fa-folder-open text-blue-600"></i>
                <span>All Archive</span>
            </a>
        </div>
    </div>

    <!-- ==========================================
         1. LABORATORY DEPARTMENT (PATHOLOGY)
         ========================================== -->
    <div class="bg-gradient-to-b from-sky-50/40 via-white to-white p-5 sm:p-6 rounded-3xl border border-sky-100 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 pb-3 border-b border-sky-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-sky-500 text-white flex items-center justify-center text-lg shadow-sm">
                    <i class="fa-solid fa-flask-vial"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-black text-slate-900 tracking-tight">1. Laboratory Department</h2>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-sky-100 text-sky-800">Pathology</span>
                    </div>
                    <p class="text-xs text-slate-500 font-medium">Hematology, CBC, Clinical Chemistry, Serology, Urinalysis &amp; Microbiology</p>
                </div>
            </div>
            <a href="/portals/laboratory.php" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-sky-100 hover:bg-sky-200 text-sky-900 font-bold text-xs transition-colors self-start sm:self-auto">
                <span>Open Lab Workstation</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Lab Booking -->
            <a href="/portals/new-scan.php?dept=lab" class="group flex items-center gap-4 p-5 rounded-2xl bg-[#38bdf8] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-sky-400/50 min-h-[96px]">
                <i class="fa-solid fa-file-circle-plus text-4xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                <div class="min-w-0">
                    <div class="font-extrabold text-base text-slate-900 leading-tight">Register Patient</div>
                    <div class="text-xs text-slate-900/80 font-medium truncate mt-0.5">Patient Registration &amp; Tests</div>
                </div>
            </a>

            <!-- Pending Samples / Worklist -->
            <a href="/portals/laboratory.php?status=pending" class="group flex items-center gap-4 p-5 rounded-2xl bg-[#c084fc] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-purple-400/50 min-h-[96px]">
                <i class="fa-regular fa-clock text-4xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                <div class="min-w-0">
                    <div class="font-extrabold text-base text-slate-900 leading-tight">Pending Orders</div>
                    <div class="text-xs text-slate-900/80 font-medium truncate mt-0.5">Sample Collection &amp; Worklist</div>
                </div>
            </a>

            <!-- Lab Results Entry -->
            <a href="/portals/results/entry.php" class="group flex items-center gap-4 p-5 rounded-2xl bg-[#2dd4bf] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-teal-400/50 min-h-[96px]">
                <i class="fa-solid fa-vials text-4xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                <div class="min-w-0">
                    <div class="font-extrabold text-base text-slate-900 leading-tight">Results Entry</div>
                    <div class="text-xs text-slate-900/80 font-medium truncate mt-0.5">Parameters &amp; Clinical Sign-off</div>
                </div>
            </a>

            <!-- Print Lab Reports -->
            <a href="/portals/reports/generate.php" class="group flex items-center gap-4 p-5 rounded-2xl bg-[#60a5fa] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-blue-400/50 min-h-[96px]">
                <i class="fa-solid fa-file-pdf text-4xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                <div class="min-w-0">
                    <div class="font-extrabold text-base text-slate-900 leading-tight">Generate Lab Report</div>
                    <div class="text-xs text-slate-900/80 font-medium truncate mt-0.5">Print PDF with QR Code</div>
                </div>
            </a>
        </div>
    </div>

    <!-- ==========================================
         2. RADIOLOGY DEPARTMENT (UNIFIED IMAGING)
         ========================================== -->
    <div class="bg-gradient-to-b from-indigo-50/40 via-white to-white p-5 sm:p-6 rounded-3xl border border-indigo-100 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-indigo-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-lg shadow-sm">
                    <i class="fa-solid fa-x-ray"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-black text-slate-900 tracking-tight">2. Radiology Department</h2>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-indigo-100 text-indigo-900">Unified Imaging</span>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-amber-100 text-amber-900">
                            <?= $pendingScansCount ?> Pending Queue
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 font-medium">Covers <strong>X-ray, ECG, Ultrasound, Echocardiography, CT scan, and MRI</strong> — unified into one clinical workflow.</p>
                </div>
            </div>
            <a href="/portals/pending-scans.php" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-indigo-100 hover:bg-indigo-200 text-indigo-900 font-bold text-xs transition-colors self-start sm:self-auto">
                <span>Open Radiology Workstation</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <!-- Modality Quick Filter Bar -->
        <div class="flex flex-wrap items-center gap-2 mb-5">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wide mr-1">Modality Quick Worklists:</span>
            <a href="/portals/pending-scans.php?modality=us" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-bold bg-purple-50 text-purple-800 hover:bg-purple-100 border border-purple-200 transition-colors">
                <i class="fa-solid fa-wave-square text-purple-600"></i>
                <span>Ultrasound (<?= $usCount ?>)</span>
            </a>
            <a href="/portals/pending-scans.php?modality=xray" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-bold bg-blue-50 text-blue-800 hover:bg-blue-100 border border-blue-200 transition-colors">
                <i class="fa-solid fa-x-ray text-blue-600"></i>
                <span>Digital X-Ray (<?= $xrCount ?>)</span>
            </a>
            <a href="/portals/pending-scans.php?modality=ecg" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-bold bg-rose-50 text-rose-800 hover:bg-rose-100 border border-rose-200 transition-colors">
                <i class="fa-solid fa-heart-pulse text-rose-600"></i>
                <span>ECG (<?= $ecgCount ?>)</span>
            </a>
            <a href="/portals/pending-scans.php?modality=echo" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-800 hover:bg-indigo-100 border border-indigo-200 transition-colors">
                <i class="fa-solid fa-heart-pulse text-indigo-600"></i>
                <span>Echo (<?= $echoCount ?>)</span>
            </a>
            <a href="/portals/pending-scans.php?modality=ct" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200 transition-colors">
                <i class="fa-solid fa-laptop-medical text-emerald-600"></i>
                <span>CT Scan (<?= $ctCount ?>)</span>
            </a>
            <a href="/portals/pending-scans.php?modality=mri" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-bold bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200 transition-colors">
                <i class="fa-solid fa-magnet text-amber-600"></i>
                <span>MRI (<?= $mriCount ?>)</span>
            </a>
        </div>

        <!-- 6 Main Radiology Action Tiles -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

            <!-- 1. Radiology Booking -->
            <a href="/portals/new-scan.php" class="group flex items-center gap-4 p-5 rounded-2xl bg-[#60a5fa] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-blue-400/50 min-h-[96px]">
                <i class="fa-solid fa-calendar-plus text-4xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                <div class="min-w-0">
                    <div class="font-extrabold text-base text-slate-900 leading-tight">Book Radiology Study</div>
                    <div class="text-xs text-slate-900/80 font-medium truncate mt-0.5">X-Ray, Ultrasound, ECG, Echo, CT &amp; MRI</div>
                </div>
            </a>

            <!-- 2. Unified Radiology Queue -->
            <a href="/portals/pending-scans.php" class="group flex items-center gap-4 p-5 rounded-2xl bg-[#c084fc] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-purple-400/50 min-h-[96px]">
                <i class="fa-regular fa-clock text-4xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                <div class="min-w-0">
                    <div class="font-extrabold text-base text-slate-900 leading-tight">Radiology Workstation</div>
                    <div class="text-xs text-slate-900/80 font-medium truncate mt-0.5">Live Queue across all modalities</div>
                </div>
            </a>

            <!-- 3. Edit Findings -->
            <a href="/portals/results.php" class="group flex items-center gap-4 p-5 rounded-2xl bg-[#2dd4bf] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-teal-400/50 min-h-[96px]">
                <i class="fa-solid fa-notes-medical text-4xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                <div class="min-w-0">
                    <div class="font-extrabold text-base text-slate-900 leading-tight">Enter / Edit Findings</div>
                    <div class="text-xs text-slate-900/80 font-medium truncate mt-0.5">Reporting pad with organ macros</div>
                </div>
            </a>

            <!-- 4. Print / View Report -->
            <a href="/portals/reports.php" class="group flex items-center gap-4 p-5 rounded-2xl bg-[#86efac] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-green-400/50 min-h-[96px]">
                <i class="fa-solid fa-file-waveform text-4xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                <div class="min-w-0">
                    <div class="font-extrabold text-base text-slate-900 leading-tight">Print Radiology Report</div>
                    <div class="text-xs text-slate-900/80 font-medium truncate mt-0.5">Letterhead report with impressions</div>
                </div>
            </a>

            <!-- 5. Report Templates Library -->
            <a href="/portals/templates.php" class="group flex items-center gap-4 p-5 rounded-2xl bg-[#fbbf24] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-amber-400/50 min-h-[96px]">
                <i class="fa-solid fa-bookmark text-4xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                <div class="min-w-0">
                    <div class="font-extrabold text-base text-slate-900 leading-tight">Report Templates</div>
                    <div class="text-xs text-slate-900/80 font-medium truncate mt-0.5">Store &amp; edit Ultrasound, X-Ray, ECG macros</div>
                </div>
            </a>

            <!-- 6. Radiology Report History -->
            <a href="/portals/report-history.php" class="group flex items-center gap-4 p-5 rounded-2xl bg-[#a5b4fc] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-indigo-300 min-h-[96px]">
                <i class="fa-solid fa-folder-tree text-4xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                <div class="min-w-0">
                    <div class="font-extrabold text-base text-slate-900 leading-tight">Radiology History</div>
                    <div class="text-xs text-slate-900/80 font-medium truncate mt-0.5">Search past scans &amp; reprints</div>
                </div>
            </a>

        </div>
    </div>

    <!-- ==========================================
         3. RECEPTION, BILLING & CLOSING BAR
         ========================================== -->
    <div class="p-5 bg-white rounded-3xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-800 flex items-center justify-center text-sm font-bold">
                <i class="fa-solid fa-cash-register"></i>
            </div>
            <div>
                <h4 class="text-sm font-bold text-slate-900">Reception, Billing &amp; Financial Audits</h4>
                <p class="text-xs text-slate-500">Token receipts, patient MR cards, daily counter closings, and accounting</p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="/portals/receipt.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs transition-colors">
                <i class="fa-solid fa-receipt text-slate-600"></i> Patient Bill / Token
            </a>
            <a href="/portals/patients.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs transition-colors">
                <i class="fa-solid fa-users text-slate-600"></i> Patients Directory
            </a>
            <a href="/portals/financial-closing.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs transition-colors">
                <i class="fa-solid fa-calculator text-slate-600"></i> Daily Closing
            </a>
            <a href="/portals/summary-reports.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-900 font-bold text-xs border border-amber-200 transition-colors">
                <i class="fa-solid fa-chart-pie text-amber-600"></i> Summary Reports Engine
            </a>
        </div>
    </div>

</div>
<?php
$content = ob_get_clean();

render_page('Diagnostic Center', 'imaging', 'dashboard', $content);
