<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';

// 100% REAL DATABASE METRICS
$totalBiomarkers = result_repo()->countTotalBiomarkers();
$outOfRangeCount = result_repo()->countOutOfRange();
$inReviewCount   = result_repo()->countInReview();
$inRangeCount    = result_repo()->countInRange();

$realAvgTAT      = result_repo()->getRealAverageTATMinutes();
$realRate        = result_repo()->getRealVerificationRate();
$criticalCount   = result_repo()->countCritical();
$realDepartments = result_repo()->getRealDepartmentCounts(3);

// Real Organ Systems Metrics from Database
$organLiver      = result_repo()->getRealOrganMetrics(['LFT', 'Liver', 'ALT', 'AST', 'SGPT', 'Bilirubin', 'Albumin']);
$organRenal      = result_repo()->getRealOrganMetrics(['RFT', 'Renal', 'Kidney', 'Creatinine', 'Urea', 'Uric', 'BUN']);
$organHema       = result_repo()->getRealOrganMetrics(['CBC', 'Blood', 'Hemoglobin', 'Platelet', 'WBC', 'ESR', 'TLC']);
$organCardiac    = result_repo()->getRealOrganMetrics(['Lipid', 'Cardiac', 'Cholesterol', 'Triglyceride', 'HDL', 'LDL', 'Troponin']);
$organMetabolic  = result_repo()->getRealOrganMetrics(['Sugar', 'Glucose', 'HbA1c', 'FBS', 'RBS']);

$labEntries = lab_repo()->getAll($orgId, 25);
$totalEntriesCount = count($labEntries);

// Precise Donut Circumference & Arc calculations based on 100% real numbers
$c = 251.327; // 2 * pi * 40
$safeTotal = max(1, $totalBiomarkers);
$pctCoral = ($outOfRangeCount / $safeTotal);
$pctAmber = ($inReviewCount / $safeTotal);
$pctGreen = ($inRangeCount / $safeTotal);

$lenCoral = round($pctCoral * $c, 2);
$lenAmber = round($pctAmber * $c, 2);
$lenGreen = round($pctGreen * $c, 2);

$offsetCoral = 0;
$offsetAmber = -$lenCoral;
$offsetGreen = -($lenCoral + $lenAmber);

ob_start();
?>
<div class="space-y-6">

    <!-- Top Diagnostic Operations Header -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between bg-white rounded-3xl p-5 sm:p-6 border border-slate-200/90 shadow-card">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-slate-900 to-slate-800 text-[#c2f13c] flex items-center justify-center text-2xl shadow-md shrink-0">
                <i class="fa-solid fa-flask-vial"></i>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Laboratory Command Center</h2>
                    <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-bold bg-[#c2f13c]/30 text-slate-900 border border-[#c2f13c]">
                        <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                        Live Diagnostic Hub
                    </span>
                </div>
                <p class="text-xs sm:text-sm text-slate-500 font-medium">Real-time specimen routing, biomarker verification, and pathology automation.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="/portals/main-lab/patients/register.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-[#c2f13c] text-slate-950 font-bold text-xs sm:text-sm hover:bg-[#b2e62a] transition-all shadow-sm">
                <i class="fa-solid fa-user-plus"></i>
                <span>Register Patient</span>
            </a>
            <a href="/portals/main-lab/results/entry.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-blue-600 text-white font-bold text-xs sm:text-sm hover:bg-blue-700 transition-all shadow-sm">
                <i class="fa-solid fa-keyboard"></i>
                <span>Result Entry</span>
            </a>
            <a href="/portals/main-lab/reports/history.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-slate-100 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-200 transition-all border border-slate-200">
                <i class="fa-solid fa-folder-open"></i>
                <span>Reports History</span>
            </a>
        </div>
    </div>

    <!-- Health Overview & Biomarkers Spectrum Grid (100% Real Database Data) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-5">
        
        <!-- Left Hero: Operational Score & Real TAT Velocity -->
        <div class="lg:col-span-4 bg-gradient-to-br from-[#0c1322] via-[#0f172a] to-[#1e293b] text-white rounded-3xl p-6 shadow-xl border border-slate-800 flex flex-col justify-between relative overflow-hidden">
            <div class="absolute -right-12 -top-12 w-44 h-44 rounded-full bg-[#c2f13c]/10 blur-3xl pointer-events-none"></div>
            <div class="absolute -left-12 -bottom-12 w-44 h-44 rounded-full bg-blue-600/15 blur-3xl pointer-events-none"></div>

            <div>
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-full bg-slate-800/80 text-[#c2f13c] border border-slate-700">
                        Operational Velocity
                    </span>
                    <span class="text-xs text-slate-400 font-medium">Auto-calibrated</span>
                </div>

                <h3 class="text-xl font-extrabold tracking-tight text-white mb-1">High Precision Flow</h3>
                <p class="text-xs text-slate-400">Specimen processing and release turnaround speed.</p>

                <!-- Big Dial / Gauge Visual with Real Data -->
                <div class="my-6 flex items-center gap-5">
                    <div class="relative w-24 h-24 shrink-0 flex items-center justify-center">
                        <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#1e293b" stroke-width="9" />
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#c2f13c" stroke-width="9"
                                    stroke-dasharray="251.327"
                                    stroke-dashoffset="<?= max(10, 251.327 - (251.327 * ($realRate / 100))) ?>"
                                    stroke-linecap="round" />
                        </svg>
                        <div class="absolute flex flex-col items-center justify-center text-center">
                            <span class="text-lg font-black text-white leading-none"><?= $realRate ?>%</span>
                            <span class="text-[9px] text-[#c2f13c] font-bold mt-0.5">Verified</span>
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl font-black tracking-tight text-white"><?= $realAvgTAT ?>m <span class="text-xs font-semibold text-slate-400">Avg TAT</span></div>
                        <p class="text-xs text-slate-300 mt-1">Live database verified turnaround time from sample registration.</p>
                    </div>
                </div>

                <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 text-xs text-slate-300 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-bell text-[#c2f13c]"></i>
                        <span><?= $criticalCount ?> Critical Flag<?= $criticalCount === 1 ? '' : 's' ?></span>
                    </span>
                    <span class="font-bold text-[#c2f13c]">Verified First</span>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-800/80 flex items-center justify-between">
                <span class="text-xs text-slate-400">Lab Dash Pro Engine 3.2</span>
                <a href="/portals/main-lab/results/entry.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs transition-colors">
                    <span>Inspect Queue</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>
        </div>

        <!-- Middle Card: Real Diagnostic Departments from DB -->
        <div class="lg:col-span-4 bg-white rounded-3xl p-6 border border-slate-200/90 shadow-card flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Diagnostic Spectrum</span>
                    <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
                        <?= count($realDepartments) ?> Departments Active
                    </span>
                </div>

                <h3 class="text-xl font-extrabold text-slate-900 tracking-tight mb-1">Pathology & Modality Spectrum</h3>
                <p class="text-xs text-slate-500 mb-5">Continuous analyzer connectivity and parameter tracking.</p>

                <!-- Department list cards with real DB counts -->
                <div class="space-y-2.5">
                    <?php if (empty($realDepartments)): ?>
                        <p class="text-xs text-slate-400 py-4 text-center">No active departments yet.</p>
                    <?php else: ?>
                        <?php 
                        $icons = ['fa-droplet text-rose-600 bg-rose-100', 'fa-vial text-amber-700 bg-amber-100', 'fa-dna text-blue-700 bg-blue-100'];
                        foreach ($realDepartments as $idx => $deptRow): 
                            $iconClass = $icons[$idx % count($icons)];
                            $deptName = e($deptRow['dept'] ?: 'General');
                            $bCount = (int)$deptRow['biomarker_count'];
                            $oCount = (int)$deptRow['order_count'];
                        ?>
                            <div class="flex items-center justify-between p-3 rounded-2xl bg-slate-50 border border-slate-100 hover:border-slate-200 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl flex items-center justify-center text-sm font-bold <?= $iconClass ?>">
                                        <i class="fa-solid <?= explode(' ', $iconClass)[0] ?>"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-800"><?= $deptName ?></p>
                                        <p class="text-[11px] text-slate-500"><?= $bCount ?> biomarkers &middot; <?= $oCount ?> orders</p>
                                    </div>
                                </div>
                                <span class="text-xs font-extrabold text-slate-700 bg-white px-2 py-1 rounded-lg border border-slate-200">Active</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Integrated with Diagnostic Center</span>
                <a href="/portals/main-lab/tests/index.php" class="font-bold text-blue-600 hover:underline">Manage Catalog &rarr;</a>
            </div>
        </div>

        <!-- Right Card: Biomarkers Donut Ring Spectrum (100% Real SQL Counts) -->
        <div class="lg:col-span-4 bg-white rounded-3xl p-6 border border-slate-200/90 shadow-card flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Specimen Breakdown</span>
                    <a href="/portals/main-lab/results/entry.php" class="text-xs font-bold text-blue-600 hover:text-blue-700">See all &gt;</a>
                </div>

                <h3 class="text-xl font-extrabold text-slate-900 tracking-tight mb-1">Biomarker Health Spectrum</h3>
                <p class="text-xs text-slate-500 mb-4">Real-time status of all measured laboratory parameters.</p>

                <!-- Donut Chart & 100% Real DB Counts -->
                <div class="flex items-center gap-6 my-2">
                    <div class="relative w-28 h-28 shrink-0 flex items-center justify-center">
                        <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
                            <!-- Background ring -->
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#f1f5f9" stroke-width="12" />
                            <?php if ($lenCoral > 0): ?>
                            <!-- Out of range segment (coral) -->
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#f87171" stroke-width="12"
                                    stroke-dasharray="<?= $lenCoral ?> <?= max(0, $c - $lenCoral) ?>"
                                    stroke-dashoffset="<?= $offsetCoral ?>"
                                    stroke-linecap="round" />
                            <?php endif; ?>
                            <?php if ($lenAmber > 0): ?>
                            <!-- In review segment (amber) -->
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#fbbf24" stroke-width="12"
                                    stroke-dasharray="<?= $lenAmber ?> <?= max(0, $c - $lenAmber) ?>"
                                    stroke-dashoffset="<?= $offsetAmber ?>"
                                    stroke-linecap="round" />
                            <?php endif; ?>
                            <?php if ($lenGreen > 0): ?>
                            <!-- In range segment (neon lime) -->
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#a3e635" stroke-width="12"
                                    stroke-dasharray="<?= $lenGreen ?> <?= max(0, $c - $lenGreen) ?>"
                                    stroke-dashoffset="<?= $offsetGreen ?>"
                                    stroke-linecap="round" />
                            <?php endif; ?>
                        </svg>
                        <div class="absolute flex flex-col items-center justify-center text-center">
                            <span class="text-2xl font-black text-slate-900 leading-none"><?= $totalBiomarkers ?></span>
                            <span class="text-[9px] uppercase font-bold text-slate-400 mt-0.5">Biomarkers</span>
                        </div>
                    </div>

                    <!-- Real Pills breakdown -->
                    <div class="space-y-2 flex-1">
                        <div class="flex items-center justify-between px-3 py-1.5 rounded-full bg-rose-50 border border-rose-200 text-xs font-bold text-rose-700">
                            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-rose-500"></span> Out of range</span>
                            <span><?= $outOfRangeCount ?></span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-1.5 rounded-full bg-amber-50 border border-amber-200 text-xs font-bold text-amber-700">
                            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500"></span> In review</span>
                            <span><?= $inReviewCount ?></span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-1.5 rounded-full bg-emerald-50 border border-emerald-200 text-xs font-bold text-emerald-800">
                            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> In range</span>
                            <span><?= $inRangeCount ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Button: Verification Queue -->
            <a href="/portals/main-lab/results/entry.php" class="mt-4 w-full flex items-center justify-center gap-2 py-3 px-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs sm:text-sm transition-all shadow-md">
                <span>Pathologist Verification Queue</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

    </div>

    <!-- Health Systems & Organ Profile Visual Deck with Real Data for ALL TABS -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-card">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Clinical Focus Areas</span>
                <h3 class="text-xl font-extrabold text-slate-900 tracking-tight">Key Organ Systems &amp; Diagnostic Profiles</h3>
            </div>
            
            <!-- Organ switcher icons strip -->
            <div class="flex items-center gap-1.5 p-1.5 bg-slate-100 rounded-2xl overflow-x-auto" id="organ-tabs">
                <button type="button" onclick="switchOrgan('liver')" data-organ="liver" class="organ-btn px-3 py-1.5 rounded-xl font-bold text-xs bg-white text-slate-900 shadow-sm border border-slate-200 flex items-center gap-1.5">
                    <i class="fa-solid fa-shield-virus text-amber-600"></i> Liver (Hepatic)
                </button>
                <button type="button" onclick="switchOrgan('renal')" data-organ="renal" class="organ-btn px-3 py-1.5 rounded-xl font-semibold text-xs text-slate-600 hover:text-slate-900 flex items-center gap-1.5">
                    <i class="fa-solid fa-vial-circle-check text-blue-600"></i> Kidneys (Renal)
                </button>
                <button type="button" onclick="switchOrgan('hematology')" data-organ="hematology" class="organ-btn px-3 py-1.5 rounded-xl font-semibold text-xs text-slate-600 hover:text-slate-900 flex items-center gap-1.5">
                    <i class="fa-solid fa-droplet text-rose-600"></i> Blood (CBC)
                </button>
                <button type="button" onclick="switchOrgan('cardiac')" data-organ="cardiac" class="organ-btn px-3 py-1.5 rounded-xl font-semibold text-xs text-slate-600 hover:text-slate-900 flex items-center gap-1.5">
                    <i class="fa-solid fa-heart-pulse text-red-500"></i> Cardiac &amp; Lipids
                </button>
                <button type="button" onclick="switchOrgan('metabolic')" data-organ="metabolic" class="organ-btn px-3 py-1.5 rounded-xl font-semibold text-xs text-slate-600 hover:text-slate-900 flex items-center gap-1.5">
                    <i class="fa-solid fa-cube text-emerald-600"></i> Glucose &amp; HbA1c
                </button>
            </div>
        </div>

        <?php
        $organDecks = [
            'liver' => [
                'title' => 'Hepatic Function (LFT)',
                'icon' => 'fa-shield-virus',
                'bg' => 'bg-amber-50 text-amber-600',
                'stats' => $organLiver,
                'lead' => 'Liver Enzyme & Protein Evaluation',
                'desc' => 'Evaluated biomarkers include Alanine Transaminase (ALT/SGPT), Aspartate Aminotransferase (AST/SGOT), Alkaline Phosphatase, Total Bilirubin, and Albumin/Globulin ratio.',
                'ranges' => ['ALT (SGPT): 10–40 U/L', 'AST: 10–35 U/L', 'Bilirubin: 0.2–1.2 mg/dL'],
                'entry_link' => '/portals/main-lab/results/entry.php?filter=lft',
            ],
            'renal' => [
                'title' => 'Renal Profile (RFT)',
                'icon' => 'fa-vial-circle-check',
                'bg' => 'bg-blue-50 text-blue-600',
                'stats' => $organRenal,
                'lead' => 'Kidney Filtration & Electrolytes',
                'desc' => 'Tracks serum creatinine, Blood Urea Nitrogen (BUN), serum uric acid, sodium, potassium, and estimated Glomerular Filtration Rate (eGFR).',
                'ranges' => ['Creatinine: 0.6–1.2 mg/dL', 'Urea: 15–45 mg/dL', 'Uric Acid: 3.5–7.2 mg/dL'],
                'entry_link' => '/portals/main-lab/results/entry.php?filter=rft',
            ],
            'hematology' => [
                'title' => 'Hematology (CBC)',
                'icon' => 'fa-droplet',
                'bg' => 'bg-rose-50 text-rose-600',
                'stats' => $organHema,
                'lead' => 'Complete Blood Count & Morphology',
                'desc' => 'Automatic calculation of Red Blood Cells (RBC), Total Leukocyte Count (TLC), Platelets, Hemoglobin, MCV, MCH, MCHC, and differential WBC percentages.',
                'ranges' => ['Hemoglobin: 13.0–17.5 g/dL', 'Platelets: 150–450 k/uL', 'WBC: 4.0–11.0 k/uL'],
                'entry_link' => '/portals/main-lab/results/entry.php?filter=cbc',
            ],
            'cardiac' => [
                'title' => 'Lipid & Cardiac Panel',
                'icon' => 'fa-heart-pulse',
                'bg' => 'bg-red-50 text-red-600',
                'stats' => $organCardiac,
                'lead' => 'Atherosclerosis Risk & Cardiac Markers',
                'desc' => 'Total Cholesterol, Triglycerides, HDL (Good Cholesterol), LDL (Bad Cholesterol), VLDL, Troponin-I, and CK-MB testing with automated coronary heart disease risk categorization.',
                'ranges' => ['Cholesterol: < 200 mg/dL', 'Triglycerides: < 150 mg/dL', 'HDL: > 40 mg/dL'],
                'entry_link' => '/portals/main-lab/results/entry.php?filter=lipid',
            ],
            'metabolic' => [
                'title' => 'Metabolic & Glycemic',
                'icon' => 'fa-cube',
                'bg' => 'bg-emerald-50 text-emerald-600',
                'stats' => $organMetabolic,
                'lead' => 'Fasting Blood Sugar & Glycated Hemoglobin',
                'desc' => 'Fasting Blood Glucose (FBS), Post-Prandial (RBS), and HbA1c with estimated Average Glucose (eAG) calculation. Flags high glycemic indexes immediately.',
                'ranges' => ['Fasting: 70–100 mg/dL', 'Random: < 140 mg/dL', 'HbA1c: 4.0–5.6 %'],
                'entry_link' => '/portals/main-lab/results/entry.php?filter=sugar',
            ],
        ];

        foreach ($organDecks as $key => $deck):
            $hidden = ($key === 'liver') ? '' : 'hidden';
            $st = $deck['stats'];
            $riskBadge = ($st['out_of_range'] > 0)
                ? '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-100 text-rose-800">Alert Flags (' . $st['out_of_range'] . ')</span>'
                : (($st['in_review'] > 0)
                    ? '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-800">In Review (' . $st['in_review'] . ')</span>'
                    : '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800">Optimal / Active</span>');
        ?>
            <div id="organ-<?= $key ?>" class="organ-content <?= $hidden ?>">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center bg-slate-50/70 rounded-2xl p-5 border border-slate-200/80">
                    <div class="md:col-span-3 flex flex-col items-center text-center justify-center p-4 bg-white rounded-2xl border border-slate-200">
                        <div class="w-16 h-16 rounded-2xl <?= $deck['bg'] ?> flex items-center justify-center text-3xl mb-2">
                            <i class="fa-solid <?= $deck['icon'] ?>"></i>
                        </div>
                        <h4 class="font-extrabold text-slate-900 text-sm"><?= $deck['title'] ?></h4>
                        <div class="mt-1"><?= $riskBadge ?></div>
                        <span class="text-[11px] font-bold text-slate-500 mt-2"><?= $st['total'] ?> Real Biomarkers in DB</span>
                    </div>

                    <div class="md:col-span-6 space-y-2">
                        <div class="flex items-center gap-2">
                            <h5 class="font-bold text-slate-800 text-sm"><?= $deck['lead'] ?></h5>
                        </div>
                        <p class="text-xs text-slate-600 leading-relaxed"><?= $deck['desc'] ?></p>

                        <!-- Real Database Status Pills for this specific organ -->
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <span class="px-2.5 py-1 rounded-lg bg-rose-50 border border-rose-200 text-xs font-bold text-rose-700">
                                <?= $st['out_of_range'] ?> Out of range
                            </span>
                            <span class="px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200 text-xs font-bold text-amber-700">
                                <?= $st['in_review'] ?> In review
                            </span>
                            <span class="px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-xs font-bold text-emerald-800">
                                <?= $st['in_range'] ?> In range
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-2 pt-1">
                            <?php foreach ($deck['ranges'] as $r): ?>
                                <span class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700"><?= $r ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="md:col-span-3 flex flex-col gap-2">
                        <a href="<?= $deck['entry_link'] ?>" class="w-full text-center py-2.5 px-4 rounded-xl bg-slate-900 text-white hover:bg-slate-800 font-bold text-xs transition-colors">
                            Enter <?= explode(' ', $deck['title'])[0] ?> Batch
                        </a>
                        <a href="/portals/main-lab/tests/index.php" class="w-full text-center py-2 px-4 rounded-xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 font-semibold text-xs transition-colors">
                            Configure Parameters
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <!-- Active Batches & Specimen Worklist Table -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-card">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-5 border-b border-slate-100">
            <div>
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Specimen Queue</span>
                <h3 class="text-xl font-extrabold text-slate-900 tracking-tight">Active Batches &amp; Laboratory Worklist</h3>
            </div>

            <!-- Tab Pills -->
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="filterBatch('all')" data-batch="all" class="batch-tab px-3.5 py-1.5 rounded-full text-xs font-bold bg-slate-900 text-white shadow-sm">
                    All Entries (<?= $totalEntriesCount ?>)
                </button>
                <button type="button" onclick="filterBatch('pending')" data-batch="pending" class="batch-tab px-3.5 py-1.5 rounded-full text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700">
                    Awaiting Results (<?= $inReviewCount ?>)
                </button>
                <button type="button" onclick="filterBatch('completed')" data-batch="completed" class="batch-tab px-3.5 py-1.5 rounded-full text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700">
                    Verified (<?= $inRangeCount ?>)
                </button>
                <button type="button" onclick="filterBatch('critical')" data-batch="critical" class="batch-tab px-3.5 py-1.5 rounded-full text-xs font-bold bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200">
                    Critical Flags (<?= $outOfRangeCount ?>)
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 py-4">
            <div class="relative w-full sm:w-80">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="search" id="batchSearch" onkeyup="searchBatches()" placeholder="Search by Lab No, patient name or test..." class="w-full pl-9 pr-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                <span class="text-xs text-slate-400">Showing recent <?= $totalEntriesCount ?> orders</span>
                <a href="/portals/main-lab/archive.php" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold text-xs transition-colors">
                    <i class="fa-solid fa-clock-rotate-left"></i> All History
                </a>
            </div>
        </div>

        <!-- Sleek Modern Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="batchTable">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                        <th class="py-3 px-4 rounded-l-xl">Batch / Lab ID</th>
                        <th class="py-3 px-4">Patient Demographics</th>
                        <th class="py-3 px-4">Tests Ordered</th>
                        <th class="py-3 px-4">Route / Doctor</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right rounded-r-xl">Quick Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-700 font-medium">
                    <?php if (empty($labEntries)): ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                <i class="fa-solid fa-vials text-3xl mb-2 text-slate-300 block"></i>
                                No active lab entries found. Click <a href="/portals/main-lab/patients/register.php" class="text-blue-600 underline font-bold">Register Patient</a> to create an entry.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($labEntries as $entry): 
                            $status = strtolower($entry['status'] ?? 'pending');
                            $labNo = e($entry['lab_no'] ?? '');
                            $patientName = e($entry['patient_name'] ?? $entry['patient'] ?? 'Unknown');
                            $tests = e(normalize_tests_list((string)($entry['tests'] ?? '')));
                            $doctor = e($entry['doctor'] ?? 'Walk-in / Self');
                            $date = date('d M, h:i A', strtotime($entry['created_at'] ?? 'now'));
                            $initials = user_initials($entry['patient_name'] ?? $entry['patient'] ?? 'U');
                            $amount = format_money((float)($entry['amount'] ?? 0));
                            
                            $statusBadgeClass = match($status) {
                                'verified', 'completed' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                'processing', 'received' => 'bg-blue-100 text-blue-800 border-blue-200',
                                'critical' => 'bg-rose-100 text-rose-800 border-rose-200',
                                default => 'bg-amber-100 text-amber-800 border-amber-200',
                            };
                        ?>
                            <tr class="hover:bg-slate-50/80 transition-colors batch-row" data-status="<?= $status ?>">
                                <td class="py-3.5 px-4 font-bold text-slate-900">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full <?= $status === 'verified' ? 'bg-emerald-500' : ($status === 'critical' ? 'bg-rose-500' : 'bg-amber-400') ?>"></span>
                                        <span class="font-mono tracking-tight font-black"><?= $labNo ?></span>
                                    </div>
                                    <span class="text-[10px] text-slate-400 block mt-0.5"><?= $date ?></span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 font-bold text-xs flex items-center justify-center shrink-0 border border-slate-200">
                                            <?= $initials ?>
                                        </div>
                                        <div>
                                            <span class="font-bold text-slate-900 block"><?= $patientName ?></span>
                                            <span class="text-[10px] text-slate-500">ID: <?= e($entry['patient_id'] ?? '—') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 max-w-xs">
                                    <span class="inline-block px-2.5 py-1 rounded-lg bg-slate-100 text-slate-800 font-semibold text-[11px] truncate max-w-[240px]">
                                        <?= $tests ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-600">
                                    <span class="block truncate max-w-[140px]"><?= $doctor ?></span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border <?= $statusBadgeClass ?>">
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="/portals/main-lab/results/entry.php?lab_no=<?= urlencode($entry['lab_no']) ?>" class="p-1.5 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition-all text-xs font-bold" title="Enter Results">
                                            <i class="fa-solid fa-keyboard"></i>
                                        </a>
                                        <a href="/portals/main-lab/reports/preview.php?lab_no=<?= urlencode($entry['lab_no']) ?>" class="p-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-900 hover:text-white transition-all text-xs font-bold" title="View Report">
                                            <i class="fa-solid fa-file-lines"></i>
                                        </a>
                                        <a href="/portals/main-lab/receipts.php?lab_no=<?= urlencode($entry['lab_no']) ?>" class="p-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-900 hover:text-white transition-all text-xs font-bold" title="Billing Receipt">
                                            <i class="fa-solid fa-receipt"></i>
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

    <!-- Quick Operations Launchpad Dock -->
    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white rounded-3xl p-6 shadow-xl border border-slate-800">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
            <div>
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-[#c2f13c]">Rapid Actions</span>
                <h3 class="text-lg font-extrabold text-white">Laboratory Operational Tools</h3>
            </div>
            <span class="text-xs text-slate-400">All tools persistent in MariaDB</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
            <a href="/portals/main-lab/patients/register.php" class="p-3.5 rounded-2xl bg-white/5 hover:bg-[#c2f13c] hover:text-slate-950 border border-white/10 text-center transition-all group">
                <i class="fa-solid fa-user-plus text-xl text-[#c2f13c] group-hover:text-slate-950 mb-1.5 block"></i>
                <span class="text-xs font-bold block">Patient Register</span>
            </a>
            <a href="/portals/main-lab/results/entry.php" class="p-3.5 rounded-2xl bg-white/5 hover:bg-[#c2f13c] hover:text-slate-950 border border-white/10 text-center transition-all group">
                <i class="fa-solid fa-keyboard text-xl text-[#c2f13c] group-hover:text-slate-950 mb-1.5 block"></i>
                <span class="text-xs font-bold block">Result Entry</span>
            </a>
            <a href="/portals/main-lab/waste-record.php" class="p-3.5 rounded-2xl bg-white/5 hover:bg-[#c2f13c] hover:text-slate-950 border border-white/10 text-center transition-all group">
                <i class="fa-solid fa-trash-can text-xl text-[#c2f13c] group-hover:text-slate-950 mb-1.5 block"></i>
                <span class="text-xs font-bold block">Waste Record</span>
            </a>
            <a href="/portals/main-lab/doctors-share.php" class="p-3.5 rounded-2xl bg-white/5 hover:bg-[#c2f13c] hover:text-slate-950 border border-white/10 text-center transition-all group">
                <i class="fa-solid fa-user-doctor text-xl text-[#c2f13c] group-hover:text-slate-950 mb-1.5 block"></i>
                <span class="text-xs font-bold block">Doctor's Share</span>
            </a>
            <a href="/portals/main-lab/tests/index.php" class="p-3.5 rounded-2xl bg-white/5 hover:bg-[#c2f13c] hover:text-slate-950 border border-white/10 text-center transition-all group">
                <i class="fa-solid fa-flask text-xl text-[#c2f13c] group-hover:text-slate-950 mb-1.5 block"></i>
                <span class="text-xs font-bold block">Add Test</span>
            </a>
            <a href="/portals/main-lab/settings/report-template.php" class="p-3.5 rounded-2xl bg-white/5 hover:bg-[#c2f13c] hover:text-slate-950 border border-white/10 text-center transition-all group">
                <i class="fa-solid fa-heading text-xl text-[#c2f13c] group-hover:text-slate-950 mb-1.5 block"></i>
                <span class="text-xs font-bold block">Header / Footer</span>
            </a>
        </div>
    </div>

</div>

<script>
function switchOrgan(organKey) {
    document.querySelectorAll('.organ-content').forEach(el => el.classList.add('hidden'));
    const target = document.getElementById('organ-' + organKey);
    if (target) target.classList.remove('hidden');

    document.querySelectorAll('.organ-btn').forEach(btn => {
        if (btn.getAttribute('data-organ') === organKey) {
            btn.className = 'organ-btn px-3 py-1.5 rounded-xl font-bold text-xs bg-white text-slate-900 shadow-sm border border-slate-200 flex items-center gap-1.5';
        } else {
            btn.className = 'organ-btn px-3 py-1.5 rounded-xl font-semibold text-xs text-slate-600 hover:text-slate-900 flex items-center gap-1.5';
        }
    });
}

function filterBatch(status) {
    document.querySelectorAll('.batch-tab').forEach(btn => {
        if (btn.getAttribute('data-batch') === status) {
            btn.className = 'batch-tab px-3.5 py-1.5 rounded-full text-xs font-bold bg-slate-900 text-white shadow-sm';
        } else {
            btn.className = 'batch-tab px-3.5 py-1.5 rounded-full text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700';
        }
    });

    const rows = document.querySelectorAll('.batch-row');
    rows.forEach(r => {
        const rowStatus = r.getAttribute('data-status');
        if (status === 'all') {
            r.style.display = '';
        } else if (status === 'critical') {
            r.style.display = (rowStatus === 'critical') ? '' : 'none';
        } else if (status === 'completed') {
            r.style.display = (rowStatus === 'completed' || rowStatus === 'verified') ? '' : 'none';
        } else if (status === 'pending') {
            r.style.display = (rowStatus === 'pending' || rowStatus === 'collected' || rowStatus === 'processing' || rowStatus === 'received') ? '' : 'none';
        }
    });
}

function searchBatches() {
    const q = document.getElementById('batchSearch').value.toLowerCase();
    const rows = document.querySelectorAll('.batch-row');
    rows.forEach(r => {
        const text = r.innerText.toLowerCase();
        r.style.display = text.includes(q) ? '' : 'none';
    });
}
</script>
<?php
$content = ob_get_clean();

render_page('Dashboard', 'main-lab', 'dashboard', $content);
