<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$studiesToday = result_repo()->countImagingToday();
$pendingStudies = result_repo()->countImagingPending();
$reportedStudies = result_repo()->countImagingReported();

$imagingScans = mock('mock_imaging');
$totalScans = count($imagingScans);

// Spectrum Donut
$total = max(10, $totalScans);
$pendingPct = max(10, min(50, (int)round(($pendingStudies / $total) * 100)));
$inProgressPct = 25;
$completedPct = max(20, 100 - $pendingPct - $inProgressPct);

$c = 251.327;
$lenPending = ($pendingPct / 100) * $c;
$lenProg = ($inProgressPct / 100) * $c;
$lenComp = ($completedPct / 100) * $c;

$offsetPending = 0;
$offsetProg = -$lenPending;
$offsetComp = -($lenPending + $lenProg);

ob_start();
?>
<div class="space-y-6">

    <!-- Top Diagnostic Operations Header -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between bg-white rounded-3xl p-5 sm:p-6 border border-slate-200/90 shadow-card">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-slate-900 to-slate-800 text-[#c2f13c] flex items-center justify-center text-2xl shadow-md shrink-0">
                <i class="fa-solid fa-x-ray"></i>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Diagnostic Center Command</h2>
                    <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-bold bg-[#c2f13c]/30 text-slate-900 border border-[#c2f13c]">
                        <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                        PACS Online
                    </span>
                </div>
                <p class="text-xs sm:text-sm text-slate-500 font-medium">Digital X-Ray, Multi-Slice CT, 4D Ultrasound, and 12-Lead ECG modalities.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="/portals/imaging/new-scan.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-[#c2f13c] text-slate-950 font-bold text-xs sm:text-sm hover:bg-[#b2e62a] transition-all shadow-sm">
                <i class="fa-solid fa-file-circle-plus"></i>
                <span>New Study</span>
            </a>
            <a href="/portals/imaging/pending-scans.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-blue-600 text-white font-bold text-xs sm:text-sm hover:bg-blue-700 transition-all shadow-sm">
                <i class="fa-solid fa-clock"></i>
                <span>Pending Studies (<?= $pendingStudies ?>)</span>
            </a>
            <a href="/portals/imaging/reports.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-slate-100 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-200 transition-all border border-slate-200">
                <i class="fa-solid fa-file-medical"></i>
                <span>Radiology Reports</span>
            </a>
        </div>
    </div>

    <!-- Modalities Overview & Ring Spectrum Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-5">

        <!-- Modality Throughput Hero -->
        <div class="lg:col-span-4 bg-gradient-to-br from-[#0c1322] via-[#0f172a] to-[#1e293b] text-white rounded-3xl p-6 shadow-xl border border-slate-800 flex flex-col justify-between relative overflow-hidden">
            <div class="absolute -right-12 -top-12 w-44 h-44 rounded-full bg-[#c2f13c]/10 blur-3xl pointer-events-none"></div>
            <div class="absolute -left-12 -bottom-12 w-44 h-44 rounded-full bg-blue-600/15 blur-3xl pointer-events-none"></div>

            <div>
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-full bg-slate-800/80 text-[#c2f13c] border border-slate-700">
                        Imaging Velocity
                    </span>
                    <span class="text-xs text-slate-400 font-medium">DICOM Station</span>
                </div>

                <h3 class="text-xl font-extrabold tracking-tight text-white mb-1">Radiology Operations</h3>
                <p class="text-xs text-slate-400">Study acquisition to consultant impression release.</p>

                <div class="my-6 flex items-center gap-5">
                    <div class="relative w-24 h-24 shrink-0 flex items-center justify-center">
                        <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#1e293b" stroke-width="9" />
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#c2f13c" stroke-width="9"
                                    stroke-dasharray="251.327"
                                    stroke-dashoffset="40"
                                    stroke-linecap="round" />
                        </svg>
                        <div class="absolute flex flex-col items-center justify-center text-center">
                            <span class="text-lg font-black text-white leading-none"><?= $studiesToday ?></span>
                            <span class="text-[9px] text-[#c2f13c] font-bold mt-0.5">Today</span>
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl font-black tracking-tight text-white"><?= $reportedStudies ?> <span class="text-xs font-semibold text-slate-400">Reported</span></div>
                        <p class="text-xs text-slate-300 mt-1"><strong><?= $pendingStudies ?> studies</strong> currently waiting in radiologist review queue.</p>
                    </div>
                </div>

                <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 text-xs text-slate-300 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-[#c2f13c]"></i>
                        <span>All Modalities Connected</span>
                    </span>
                    <span class="font-bold text-[#c2f13c]">Zero Errors</span>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-800/80 flex items-center justify-between">
                <span class="text-xs text-slate-400">Radiology PACS 4.0</span>
                <a href="/portals/imaging/pending-scans.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs transition-colors">
                    <span>Pending Worklist</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>
        </div>

        <!-- 4 Modality Stations Deck -->
        <div class="lg:col-span-4 bg-white rounded-3xl p-6 border border-slate-200/90 shadow-card flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Modality Suites</span>
                    <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
                        Ready
                    </span>
                </div>

                <h3 class="text-xl font-extrabold text-slate-900 tracking-tight mb-1">Diagnostic Modalities</h3>
                <p class="text-xs text-slate-500 mb-5">Quick initiation for scheduled imaging studies.</p>

                <div class="grid grid-cols-2 gap-2.5">
                    <a href="/portals/imaging/new-scan.php?modality=xray" class="p-3.5 rounded-2xl bg-slate-50 hover:bg-blue-50 border border-slate-100 hover:border-blue-200 transition-all text-center group">
                        <i class="fa-solid fa-x-ray text-2xl text-blue-600 mb-1.5 block group-hover:scale-110 transition-transform"></i>
                        <span class="text-xs font-bold text-slate-900 block">Digital X-Ray</span>
                        <span class="text-[10px] text-slate-500">Chest, Bones, Spine</span>
                    </a>

                    <a href="/portals/imaging/new-scan.php?modality=ct" class="p-3.5 rounded-2xl bg-slate-50 hover:bg-blue-50 border border-slate-100 hover:border-blue-200 transition-all text-center group">
                        <i class="fa-solid fa-laptop-medical text-2xl text-purple-600 mb-1.5 block group-hover:scale-110 transition-transform"></i>
                        <span class="text-xs font-bold text-slate-900 block">CT Scan</span>
                        <span class="text-[10px] text-slate-500">Brain, Abdomen, Chest</span>
                    </a>

                    <a href="/portals/imaging/new-scan.php?modality=us" class="p-3.5 rounded-2xl bg-slate-50 hover:bg-blue-50 border border-slate-100 hover:border-blue-200 transition-all text-center group">
                        <i class="fa-solid fa-wave-square text-2xl text-emerald-600 mb-1.5 block group-hover:scale-110 transition-transform"></i>
                        <span class="text-xs font-bold text-slate-900 block">Ultrasound</span>
                        <span class="text-[10px] text-slate-500">Abdomen, Pelvis, Doppler</span>
                    </a>

                    <a href="/portals/imaging/new-scan.php?modality=ecg" class="p-3.5 rounded-2xl bg-slate-50 hover:bg-blue-50 border border-slate-100 hover:border-blue-200 transition-all text-center group">
                        <i class="fa-solid fa-heart-pulse text-2xl text-rose-600 mb-1.5 block group-hover:scale-110 transition-transform"></i>
                        <span class="text-xs font-bold text-slate-900 block">12-Lead ECG</span>
                        <span class="text-[10px] text-slate-500">Cardiac rhythm</span>
                    </a>
                </div>
            </div>

            <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Shared with Lab &amp; Collection</span>
                <a href="/portals/imaging/patients.php" class="font-bold text-blue-600 hover:underline">Patient Database &rarr;</a>
            </div>
        </div>

        <!-- Radiologist Reporting Ring Spectrum -->
        <div class="lg:col-span-4 bg-white rounded-3xl p-6 border border-slate-200/90 shadow-card flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Radiology Workflow</span>
                    <a href="/portals/imaging/reports.php" class="text-xs font-bold text-blue-600 hover:text-blue-700">All reports &gt;</a>
                </div>

                <h3 class="text-xl font-extrabold text-slate-900 tracking-tight mb-1">Study Reporting Spectrum</h3>
                <p class="text-xs text-slate-500 mb-4">Real-time status of scans across doctor verification benches.</p>

                <div class="flex items-center gap-6 my-2">
                    <div class="relative w-28 h-28 shrink-0 flex items-center justify-center">
                        <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#f1f5f9" stroke-width="12" />
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#f87171" stroke-width="12"
                                    stroke-dasharray="<?= $lenPending ?> <?= $c - $lenPending ?>"
                                    stroke-dashoffset="<?= $offsetPending ?>"
                                    stroke-linecap="round" />
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#fbbf24" stroke-width="12"
                                    stroke-dasharray="<?= $lenProg ?> <?= $c - $lenProg ?>"
                                    stroke-dashoffset="<?= $offsetProg ?>"
                                    stroke-linecap="round" />
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#a3e635" stroke-width="12"
                                    stroke-dasharray="<?= $lenComp ?> <?= $c - $lenComp ?>"
                                    stroke-dashoffset="<?= $offsetComp ?>"
                                    stroke-linecap="round" />
                        </svg>
                        <div class="absolute flex flex-col items-center justify-center text-center">
                            <span class="text-2xl font-black text-slate-900 leading-none"><?= $totalScans ?></span>
                            <span class="text-[9px] uppercase font-bold text-slate-400 mt-0.5">Studies</span>
                        </div>
                    </div>

                    <div class="space-y-2 flex-1">
                        <div class="flex items-center justify-between px-3 py-1.5 rounded-full bg-rose-50 border border-rose-200 text-xs font-bold text-rose-700">
                            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-rose-500"></span> Pending Scan</span>
                            <span><?= $pendingStudies ?></span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-1.5 rounded-full bg-amber-50 border border-amber-200 text-xs font-bold text-amber-700">
                            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500"></span> Findings Queue</span>
                            <span><?= max(1, (int)round($totalScans * 0.25)) ?></span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-1.5 rounded-full bg-emerald-50 border border-emerald-200 text-xs font-bold text-emerald-800">
                            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Signed / Verified</span>
                            <span><?= $reportedStudies ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <a href="/portals/imaging/results.php" class="mt-4 w-full flex items-center justify-center gap-2 py-3 px-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs sm:text-sm transition-all shadow-md">
                <i class="fa-solid fa-notes-medical"></i>
                <span>Enter Radiologist Findings</span>
            </a>
        </div>

    </div>

    <!-- Studies & Scans Table -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-card">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-5 border-b border-slate-100">
            <div>
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Imaging Manifest</span>
                <h3 class="text-xl font-extrabold text-slate-900 tracking-tight">Recent Scans &amp; Imaging Manifest</h3>
            </div>

            <div class="flex items-center gap-2">
                <a href="/portals/imaging/new-scan.php" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-[#c2f13c] text-slate-950 font-bold text-xs hover:bg-[#b2e62a] transition-colors">
                    <i class="fa-solid fa-plus"></i> Order Study
                </a>
                <a href="/portals/imaging/report-history.php" class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold text-xs transition-colors">
                    <i class="fa-solid fa-clock-rotate-left"></i> History
                </a>
            </div>
        </div>

        <div class="overflow-x-auto mt-4">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                        <th class="py-3 px-4 rounded-l-xl">Scan / Study ID</th>
                        <th class="py-3 px-4">Patient</th>
                        <th class="py-3 px-4">Modality</th>
                        <th class="py-3 px-4">Study Description</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right rounded-r-xl">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-700 font-medium">
                    <?php if (empty($imagingScans)): ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                <i class="fa-solid fa-x-ray text-3xl mb-2 text-slate-300 block"></i>
                                No active studies. Click <a href="/portals/imaging/new-scan.php" class="text-blue-600 underline font-bold">New Study</a> to book an imaging scan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach (array_slice($imagingScans, 0, 10) as $s): 
                            $status = strtolower($s['status'] ?? 'pending');
                            $modality = strtoupper($s['modality'] ?? 'XR');
                            $statusBadgeClass = match($status) {
                                'reported', 'completed' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                'in_progress', 'scanned' => 'bg-blue-100 text-blue-800 border-blue-200',
                                default => 'bg-amber-100 text-amber-800 border-amber-200',
                            };
                        ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3.5 px-4 font-bold text-slate-900">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full <?= $status === 'reported' ? 'bg-emerald-500' : 'bg-amber-400' ?>"></span>
                                        <span class="font-mono tracking-tight font-black"><?= e($s['scan_no']) ?></span>
                                    </div>
                                    <span class="text-[10px] text-slate-400 block mt-0.5"><?= date('d M Y', strtotime($s['date'] ?? 'now')) ?></span>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-slate-900">
                                    <?= e($s['patient']) ?>
                                    <span class="text-[10px] text-slate-500 block font-normal">ID: <?= e($s['patient_id'] ?? '—') ?></span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-block px-2.5 py-1 rounded-lg bg-slate-900 text-white font-black text-[10px] tracking-wider">
                                        <?= e($modality) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-800 font-semibold">
                                    <?= e($s['study'] ?? 'Standard Scan') ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border <?= $statusBadgeClass ?>">
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="/portals/imaging/results.php?scan_no=<?= urlencode($s['scan_no']) ?>" class="px-2.5 py-1.5 rounded-lg bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-700 transition-all text-xs font-bold" title="Enter Findings">
                                            <i class="fa-solid fa-notes-medical mr-1"></i> Findings
                                        </a>
                                        <a href="/portals/imaging/reports.php?scan_no=<?= urlencode($s['scan_no']) ?>" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-900 hover:text-white text-slate-700 transition-all text-xs font-bold" title="Report">
                                            <i class="fa-solid fa-file-medical mr-1"></i> Report
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<?php
$content = ob_get_clean();

render_page('Diagnostic Center', 'imaging', 'dashboard', $content);
