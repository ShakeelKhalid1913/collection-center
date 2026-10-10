<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/documents.php';

$labNo = trim((string)($_GET['lab_no'] ?? ''));
$entry = null;
$ctx = null;

if ($labNo !== '') {
    $entry = lab_repo()->findByLabNo($labNo);
    if ($entry) {
        $ctx = load_document_context($labNo);
    }
}

$chainSteps = [
    'collected' => ['label' => 'Sample Collected', 'desc' => 'Specimen received at Collection Center', 'icon' => 'fa-hospital-user'],
    'in_transit' => ['label' => 'In Transit', 'desc' => 'En route to Main Laboratory', 'icon' => 'fa-truck-fast'],
    'testing' => ['label' => 'Processing / Testing', 'desc' => 'Under analysis on laboratory bench', 'icon' => 'fa-vial-virus'],
    'result_entered' => ['label' => 'Results Entered', 'desc' => 'Awaiting pathologist verification', 'icon' => 'fa-keyboard'],
    'verified' => ['label' => 'Verified & Ready', 'desc' => 'Electronically signed and released', 'icon' => 'fa-file-circle-check'],
];

$curStatus = (string)($entry['transit_status'] ?? 'collected');
if (!isset($chainSteps[$curStatus])) {
    $curStatus = 'collected';
}

$stepKeys = array_keys($chainSteps);
$curIdx = array_search($curStatus, $stepKeys, true);
if ($curIdx === false) {
    $curIdx = 0;
}

$settings = branding_settings();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <?php render_head('Diagnostic Report Tracking · ' . e($settings['name'] ?? 'City Medical Lab')); ?>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .print-container { box-shadow: none !important; margin: 0 !important; width: 100% !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="min-h-full flex flex-col text-slate-800 antialiased font-sans">

    <!-- Top Navigation Bar -->
    <header class="no-print bg-slate-900 text-white shadow-md border-b border-slate-800">
        <div class="max-w-5xl mx-auto px-4 py-3.5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white text-lg font-black shadow-inner">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <div>
                    <h1 class="text-base font-extrabold tracking-tight"><?= e($settings['name'] ?? 'City Medical Lab') ?></h1>
                    <p class="text-xs text-slate-400">Official Report Verification & Specimen Tracking</p>
                </div>
            </div>
            <a href="/login.php" class="text-xs text-slate-300 hover:text-white flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 transition">
                <i class="fa-solid fa-lock text-[10px]"></i> Staff Login
            </a>
        </div>
    </header>

    <main class="flex-1 max-w-5xl w-full mx-auto p-4 sm:p-6 space-y-6">

        <!-- Tracking Search Form -->
        <div class="no-print bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <form method="get" action="/track.php" class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[240px]">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input type="text" name="lab_no" value="<?= e($labNo) ?>" placeholder="Enter Lab Number (e.g. LAB-1001)..." 
                           class="w-full pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-900 focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none transition uppercase tracking-wider" required>
                </div>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold shadow-sm transition flex items-center gap-2">
                    <i class="fa-solid fa-magnifying-glass"></i> Track Specimen
                </button>
            </form>
        </div>

        <?php if ($labNo === ''): ?>
            <!-- Initial Empty State -->
            <div class="no-print bg-white rounded-2xl shadow-sm border border-slate-200 p-12 text-center max-w-lg mx-auto">
                <div class="w-16 h-16 rounded-full bg-blue-50 text-blue-600 mx-auto flex items-center justify-center text-2xl mb-4">
                    <i class="fa-solid fa-barcode"></i>
                </div>
                <h2 class="text-lg font-bold text-slate-900 mb-2">Track Specimen & Verify Report</h2>
                <p class="text-sm text-slate-500 leading-relaxed">
                    Scan the QR code on your printed receipt or diagnostic report, or enter your Lab Number above to see live status milestones and verified results.
                </p>
            </div>
        <?php elseif (!$entry): ?>
            <!-- Not Found State -->
            <div class="no-print bg-amber-50 rounded-2xl border border-amber-200 p-8 text-center max-w-md mx-auto">
                <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-700 mx-auto flex items-center justify-center text-xl mb-3">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <h3 class="text-base font-bold text-amber-950 mb-1">Record Not Found</h3>
                <p class="text-xs text-amber-800 leading-relaxed">
                    No specimen found with Lab Number <strong class="font-mono"><?= e($labNo) ?></strong>. Please double-check the number on your receipt or report.
                </p>
            </div>
        <?php else: ?>
            <!-- Found Specimen Details -->
            <div class="no-print bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <!-- Header Info Bar -->
                <div class="p-5 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 text-white flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <span class="text-xs uppercase tracking-wider font-extrabold text-[#c2f13c]">Lab Tracking ID</span>
                            <span class="font-mono text-lg font-black tracking-wide text-white bg-white/10 px-2.5 py-0.5 rounded-lg"><?= e($entry['lab_no']) ?></span>
                        </div>
                        <h2 class="text-lg font-extrabold mt-1 text-white"><?= e($entry['patient_name'] ?? '—') ?></h2>
                        <p class="text-xs text-slate-300 mt-0.5">Tests: <?= e(normalize_tests_list($entry['tests'] ?? '')) ?></p>
                    </div>

                    <div class="flex items-center gap-2">
                        <?php if ($curStatus === 'verified'): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500/20 border border-emerald-400 text-emerald-300 text-xs font-bold uppercase tracking-wider">
                                <i class="fa-solid fa-circle-check"></i> Report Verified
                            </span>
                            <button onclick="window.print()" class="px-3.5 py-1.5 bg-white text-slate-900 hover:bg-slate-100 rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5">
                                <i class="fa-solid fa-print"></i> Print / PDF
                            </button>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500/20 border border-amber-400 text-amber-300 text-xs font-bold uppercase tracking-wider">
                                <i class="fa-solid fa-clock"></i> <?= e($chainSteps[$curStatus]['label']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 5-Stage Visual Progress Bar -->
                <div class="p-6 bg-slate-50 border-b border-slate-200">
                    <h3 class="text-xs uppercase tracking-wider font-extrabold text-slate-500 mb-6">Specimen Lifecycle Pipeline</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-5 gap-4 relative">
                        <?php foreach ($stepKeys as $idx => $k): 
                            $step = $chainSteps[$k];
                            $isDone = $idx < $curIdx;
                            $isCurrent = $idx === $curIdx;
                        ?>
                            <div class="flex flex-col items-center text-center relative z-10">
                                <div class="w-10 h-10 rounded-2xl flex items-center justify-center text-base shadow-sm mb-2 transition <?= $isCurrent ? 'bg-blue-600 text-white ring-4 ring-blue-100 shadow-md font-bold' : ($isDone ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-400') ?>">
                                    <i class="fa-solid <?= $step['icon'] ?>"></i>
                                </div>
                                <span class="text-xs font-bold <?= $isCurrent ? 'text-blue-900' : ($isDone ? 'text-slate-900' : 'text-slate-400') ?>"><?= e($step['label']) ?></span>
                                <span class="text-[11px] text-slate-500 mt-0.5 leading-tight"><?= e($step['desc']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Report Document Preview (If ready) -->
            <?php if ($ctx && !empty($ctx['lines'])): ?>
                <div class="print-container bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-8 overflow-x-auto">
                    <div class="no-print mb-4 flex items-center justify-between pb-3 border-b border-slate-200">
                        <span class="text-xs uppercase tracking-wider font-extrabold text-slate-500"><i class="fa-solid fa-file-medical text-blue-600 mr-1.5"></i> Official Diagnostic Report</span>
                        <button onclick="window.print()" class="text-xs text-blue-600 hover:text-blue-800 font-bold flex items-center gap-1">
                            <i class="fa-solid fa-print"></i> Print Report
                        </button>
                    </div>
                    <?= render_report_document(
                        $ctx['settings'],
                        $ctx['patient'],
                        $ctx['lines'],
                        $ctx['entry'],
                        $ctx['report_title'] ?? 'DEPARTMENT OF LABORATORY MEDICINE',
                        $ctx['signatories'] ?? [],
                        $ctx['specimen'] ?? 'Serum'
                    ) ?>
                </div>
            <?php elseif ($curStatus !== 'verified'): ?>
                <div class="no-print bg-white rounded-2xl border border-slate-200 p-8 text-center">
                    <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 mx-auto flex items-center justify-center text-xl mb-3">
                        <i class="fa-solid fa-flask-vial"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-900 mb-1">Testing In Progress</h3>
                    <p class="text-xs text-slate-500 max-w-md mx-auto leading-relaxed">
                        Your specimen has been received and is currently undergoing analysis. Once the pathologist verifies the findings, the full diagnostic report will automatically display here.
                    </p>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="no-print py-4 bg-white border-t border-slate-200 mt-auto">
        <div class="max-w-5xl mx-auto px-4 text-center">
            <?= software_credit_footer(true) ?>
        </div>
    </footer>

</body>
</html>
