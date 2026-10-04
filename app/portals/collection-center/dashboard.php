<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';

$realEntries = lab_repo()->getAll($orgId, 50);
$entries = !empty($realEntries) ? $realEntries : mock('mock_lab_entries');
$totalEntriesCount = count($entries);

$patientsToday = lab_repo()->countPatientsToday($orgId);
$testsToday = lab_repo()->countToday($orgId);
$pendingSamples = lab_repo()->countPendingSamples($orgId);
$pendingReports = lab_repo()->countByStatuses(['pending', 'collected', 'processing', 'received'], $orgId);
$collection = lab_repo()->sumPaidToday($orgId);

// Logistics Donut calculation
$totalOrders = max(12, $testsToday + 10);
$collectedPct = max(10, min(50, (int)round(($pendingSamples / $totalOrders) * 100)));
$transitPct = max(15, min(40, 25));
$completedPct = max(20, 100 - $collectedPct - $transitPct);

$c = 251.327;
$lenCollected = ($collectedPct / 100) * $c;
$lenTransit = ($transitPct / 100) * $c;
$lenCompleted = ($completedPct / 100) * $c;

$offsetCollected = 0;
$offsetTransit = -$lenCollected;
$offsetCompleted = -($lenCollected + $lenTransit);

ob_start();
?>
<div class="space-y-6">

    <!-- Top Collection Hub Header -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between bg-white rounded-3xl p-5 sm:p-6 border border-slate-200/90 shadow-card">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-slate-900 to-slate-800 text-[#c2f13c] flex items-center justify-center text-2xl shadow-md shrink-0">
                <i class="fa-solid fa-hospital-user"></i>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Collection Center Command</h2>
                    <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-bold bg-[#c2f13c]/30 text-slate-900 border border-[#c2f13c]">
                        <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                        Intake Active
                    </span>
                </div>
                <p class="text-xs sm:text-sm text-slate-500 font-medium">Walk-in registrations, rapid specimen phlebotomy, thermal receipts, and digital delivery.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="/portals/collection-center/patients/quick-register.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-[#c2f13c] text-slate-950 font-bold text-xs sm:text-sm hover:bg-[#b2e62a] transition-all shadow-sm">
                <i class="fa-solid fa-bolt"></i>
                <span>Quick Register</span>
            </a>
            <a href="/portals/collection-center/lab-entries/new.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-blue-600 text-white font-bold text-xs sm:text-sm hover:bg-blue-700 transition-all shadow-sm">
                <i class="fa-solid fa-file-circle-plus"></i>
                <span>New Lab Entry</span>
            </a>
            <a href="/portals/collection-center/receipts.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-slate-100 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-200 transition-all border border-slate-200">
                <i class="fa-solid fa-receipt"></i>
                <span>All Receipts</span>
            </a>
        </div>
    </div>

    <!-- Metrics Spectrum Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-5">

        <!-- Revenue & Cash Flow Hero -->
        <div class="lg:col-span-4 bg-gradient-to-br from-[#0c1322] via-[#0f172a] to-[#1e293b] text-white rounded-3xl p-6 shadow-xl border border-slate-800 flex flex-col justify-between relative overflow-hidden">
            <div class="absolute -right-12 -top-12 w-44 h-44 rounded-full bg-[#c2f13c]/10 blur-3xl pointer-events-none"></div>
            <div class="absolute -left-12 -bottom-12 w-44 h-44 rounded-full bg-blue-600/15 blur-3xl pointer-events-none"></div>

            <div>
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-full bg-slate-800/80 text-[#c2f13c] border border-slate-700">
                        Financial Velocity
                    </span>
                    <span class="text-xs text-slate-400 font-medium">Daily Register</span>
                </div>

                <h3 class="text-xl font-extrabold tracking-tight text-white mb-1">Today's Collections</h3>
                <p class="text-xs text-slate-400">Total payments collected at the front desk counter.</p>

                <div class="my-6">
                    <div class="text-3xl sm:text-4xl font-black tracking-tight text-white flex items-baseline gap-2">
                        <?= format_money((float)$collection) ?>
                    </div>
                    <p class="text-xs text-[#c2f13c] font-semibold mt-1 flex items-center gap-1.5">
                        <i class="fa-solid fa-arrow-trend-up"></i>
                        <span>Active ledger synced with branch register</span>
                    </p>
                </div>

                <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 text-xs text-slate-300 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-receipt text-[#c2f13c]"></i>
                        <span><?= $testsToday ?> Orders Booked</span>
                    </span>
                    <span class="font-bold text-[#c2f13c]"><?= $patientsToday ?> Patients</span>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-800/80 flex items-center justify-between">
                <span class="text-xs text-slate-400">POS Receipts Ready</span>
                <a href="/portals/collection-center/receipts.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs transition-colors">
                    <span>Manage Cash</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>
        </div>

        <!-- Intake & Patient Speed -->
        <div class="lg:col-span-4 bg-white rounded-3xl p-6 border border-slate-200/90 shadow-card flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Intake Desk</span>
                    <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2.5 py-0.5 rounded-full border border-blue-200">
                        Counter Flow
                    </span>
                </div>

                <h3 class="text-xl font-extrabold text-slate-900 tracking-tight mb-1">Registration Throughput</h3>
                <p class="text-xs text-slate-500 mb-5">Patient queuing and swift demographic onboarding.</p>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Today's Patients</span>
                        <span class="text-2xl font-black text-slate-900 mt-1 block"><?= $patientsToday ?></span>
                        <span class="text-[10px] text-emerald-600 font-semibold mt-0.5 block">&plus; Distinct walk-ins</span>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Open Orders</span>
                        <span class="text-2xl font-black text-slate-900 mt-1 block"><?= $pendingReports ?></span>
                        <span class="text-[10px] text-amber-600 font-semibold mt-0.5 block">In lab pipeline</span>
                    </div>
                </div>

                <div class="p-3 rounded-2xl bg-blue-50/60 border border-blue-100 text-xs text-blue-900 flex items-center justify-between">
                    <span class="font-semibold"><i class="fa-solid fa-bolt text-blue-600 mr-1.5"></i> Quick Register: &lt; 30 seconds</span>
                    <a href="/portals/collection-center/patients/quick-register.php" class="font-bold underline">Launch &rarr;</a>
                </div>
            </div>

            <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Auto-linked with Main Lab</span>
                <a href="/portals/collection-center/patients/records.php" class="font-bold text-blue-600 hover:underline">Patient Directory &rarr;</a>
            </div>
        </div>

        <!-- Specimen Logistics Ring Spectrum -->
        <div class="lg:col-span-4 bg-white rounded-3xl p-6 border border-slate-200/90 shadow-card flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Specimen Pipeline</span>
                    <a href="/portals/collection-center/lab-entries/pending.php" class="text-xs font-bold text-blue-600 hover:text-blue-700">Track all &gt;</a>
                </div>

                <h3 class="text-xl font-extrabold text-slate-900 tracking-tight mb-1">Specimen Logistics Hub</h3>
                <p class="text-xs text-slate-500 mb-4">Sample draw, barcoding, and dispatch to testing benches.</p>

                <div class="flex items-center gap-6 my-2">
                    <div class="relative w-28 h-28 shrink-0 flex items-center justify-center">
                        <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#f1f5f9" stroke-width="12" />
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#fbbf24" stroke-width="12"
                                    stroke-dasharray="<?= $lenCollected ?> <?= $c - $lenCollected ?>"
                                    stroke-dashoffset="<?= $offsetCollected ?>"
                                    stroke-linecap="round" />
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#38bdf8" stroke-width="12"
                                    stroke-dasharray="<?= $lenTransit ?> <?= $c - $lenTransit ?>"
                                    stroke-dashoffset="<?= $offsetTransit ?>"
                                    stroke-linecap="round" />
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#a3e635" stroke-width="12"
                                    stroke-dasharray="<?= $lenCompleted ?> <?= $c - $lenCompleted ?>"
                                    stroke-dashoffset="<?= $offsetCompleted ?>"
                                    stroke-linecap="round" />
                        </svg>
                        <div class="absolute flex flex-col items-center justify-center text-center">
                            <span class="text-2xl font-black text-slate-900 leading-none"><?= $pendingSamples ?></span>
                            <span class="text-[9px] uppercase font-bold text-slate-400 mt-0.5">Samples</span>
                        </div>
                    </div>

                    <div class="space-y-2 flex-1">
                        <div class="flex items-center justify-between px-3 py-1.5 rounded-full bg-amber-50 border border-amber-200 text-xs font-bold text-amber-700">
                            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500"></span> Phlebotomy</span>
                            <span><?= $pendingSamples ?></span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-1.5 rounded-full bg-sky-50 border border-sky-200 text-xs font-bold text-sky-700">
                            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-sky-500"></span> Dispatched</span>
                            <span><?= max(2, $testsToday) ?></span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-1.5 rounded-full bg-emerald-50 border border-emerald-200 text-xs font-bold text-emerald-800">
                            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Verified</span>
                            <span><?= max(5, $totalEntriesCount - $pendingReports) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <a href="/portals/collection-center/lab-entries/new.php" class="mt-4 w-full flex items-center justify-center gap-2 py-3 px-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs sm:text-sm transition-all shadow-md">
                <i class="fa-solid fa-plus"></i>
                <span>Create Lab Order &amp; Receipt</span>
            </a>
        </div>

    </div>

    <!-- Recent Walk-ins & Worklist Table -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-card">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-5 border-b border-slate-100">
            <div>
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Order Manifest</span>
                <h3 class="text-xl font-extrabold text-slate-900 tracking-tight">Recent Orders &amp; Walk-in Patients</h3>
            </div>

            <div class="flex items-center gap-2">
                <div class="relative w-64">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="search" id="ccSearch" onkeyup="searchCCOrders()" placeholder="Search patient or lab no..." class="w-full pl-9 pr-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                </div>
                <a href="/portals/collection-center/lab-entries/history.php" class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold text-xs transition-colors">
                    <i class="fa-solid fa-clock-rotate-left"></i> History
                </a>
            </div>
        </div>

        <div class="overflow-x-auto mt-4">
            <table class="w-full text-left border-collapse" id="ccTable">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                        <th class="py-3 px-4 rounded-l-xl">Lab / Order No</th>
                        <th class="py-3 px-4">Patient Demographics</th>
                        <th class="py-3 px-4">Tests Ordered</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Amount</th>
                        <th class="py-3 px-4 text-right rounded-r-xl">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-700 font-medium">
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                <i class="fa-solid fa-flask text-3xl mb-2 text-slate-300 block"></i>
                                No entries booked yet today. Click <a href="/portals/collection-center/lab-entries/new.php" class="text-blue-600 underline font-bold">New Lab Entry</a> to register.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach (array_slice($entries, 0, 15) as $e): 
                            $labQ = urlencode((string)$e['lab_no']);
                            $status = strtolower((string)($e['status'] ?? 'pending'));
                            $tests = e(normalize_tests_list((string)($e['tests'] ?? '')));
                            $patientName = (string)($e['patient_name'] ?? $e['patient'] ?? '—');
                            $initials = user_initials($patientName);
                            $amount = format_money((float)($e['amount'] ?? 0));
                            $dateStr = $e['created_at'] ?? $e['date'] ?? 'now';

                            $statusBadgeClass = match($status) {
                                'verified', 'completed' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                'processing', 'received' => 'bg-blue-100 text-blue-800 border-blue-200',
                                default => 'bg-amber-100 text-amber-800 border-amber-200',
                            };
                        ?>
                            <tr class="hover:bg-slate-50/80 transition-colors cc-row">
                                <td class="py-3.5 px-4 font-bold text-slate-900">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full <?= $status === 'verified' ? 'bg-emerald-500' : 'bg-amber-400' ?>"></span>
                                        <span class="font-mono tracking-tight font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md text-xs"><?= e($e['lab_no']) ?></span>
                                    </div>
                                    <span class="text-[10px] text-slate-400 block mt-0.5"><?= date('d M Y', strtotime((string)$dateStr)) ?></span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-slate-900 text-[#c2f13c] font-bold text-xs flex items-center justify-center shrink-0 shadow-sm">
                                            <?= $initials ?>
                                        </div>
                                        <div>
                                            <span class="font-bold text-slate-900 block"><?= e($patientName) ?></span>
                                            <span class="text-[10px] text-slate-500">ID: <?= e((string)($e['patient_id'] ?? '—')) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 max-w-xs">
                                    <span class="inline-block px-2.5 py-1 rounded-lg bg-slate-100 text-slate-800 font-semibold text-[11px] truncate max-w-[220px]">
                                        <?= $tests ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border <?= $statusBadgeClass ?>">
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-extrabold text-slate-900">
                                    <?= $amount ?>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="/portals/collection-center/receipts.php?lab_no=<?= $labQ ?>" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-900 hover:text-white text-slate-700 transition-all text-xs font-bold" title="Print Receipt">
                                            <i class="fa-solid fa-receipt mr-1"></i> Receipt
                                        </a>
                                        <a href="/portals/collection-center/reports/preview.php?lab_no=<?= $labQ ?>" class="px-2.5 py-1.5 rounded-lg bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-700 transition-all text-xs font-bold" title="View Report">
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

<script>
function searchCCOrders() {
    const q = document.getElementById('ccSearch').value.toLowerCase();
    const rows = document.querySelectorAll('.cc-row');
    rows.forEach(r => {
        const text = r.innerText.toLowerCase();
        r.style.display = text.includes(q) ? '' : 'none';
    });
}
</script>
<?php
$content = ob_get_clean();

render_page('Dashboard', 'collection-center', 'dashboard', $content);
