<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgCount = 1;
$branchCount = 3;
$userCount = user_repo()->countActive();
$testCount = test_repo()->countTests();
$pkgCount = test_repo()->countPackages();
$totalCatalog = $testCount + $pkgCount;

// Donut calculation
$c = 251.327;
$testsPct = max(10, min(80, (int)round(($testCount / max(1, $totalCatalog)) * 100)));
$pkgPct = 100 - $testsPct;

$lenTests = ($testsPct / 100) * $c;
$lenPkg = ($pkgPct / 100) * $c;

$offsetTests = 0;
$offsetPkg = -$lenTests;

ob_start();
?>
<div class="space-y-6">

    <!-- Top Admin Header -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between bg-white rounded-3xl p-5 sm:p-6 border border-slate-200/90 shadow-card">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-slate-900 to-slate-800 text-[#c2f13c] flex items-center justify-center text-2xl shadow-md shrink-0">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Admin Command Console</h2>
                    <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-bold bg-[#c2f13c]/30 text-slate-900 border border-[#c2f13c]">
                        <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                        Full System Control
                    </span>
                </div>
                <p class="text-xs sm:text-sm text-slate-500 font-medium">Global branch network, staff authorization, test definitions, and client branding.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="/portals/admin/users.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-[#c2f13c] text-slate-950 font-bold text-xs sm:text-sm hover:bg-[#b2e62a] transition-all shadow-sm">
                <i class="fa-solid fa-user-shield"></i>
                <span>Users &amp; Roles</span>
            </a>
            <a href="/portals/admin/tests.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-blue-600 text-white font-bold text-xs sm:text-sm hover:bg-blue-700 transition-all shadow-sm">
                <i class="fa-solid fa-flask"></i>
                <span>Tests Catalog</span>
            </a>
            <a href="/portals/admin/branding.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-slate-100 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-200 transition-all border border-slate-200">
                <i class="fa-solid fa-heading"></i>
                <span>Client Branding</span>
            </a>
        </div>
    </div>

    <!-- Admin Overview Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-5">

        <!-- Network Health Card -->
        <div class="lg:col-span-4 bg-gradient-to-br from-[#0c1322] via-[#0f172a] to-[#1e293b] text-white rounded-3xl p-6 shadow-xl border border-slate-800 flex flex-col justify-between relative overflow-hidden">
            <div class="absolute -right-12 -top-12 w-44 h-44 rounded-full bg-[#c2f13c]/10 blur-3xl pointer-events-none"></div>
            <div class="absolute -left-12 -bottom-12 w-44 h-44 rounded-full bg-blue-600/15 blur-3xl pointer-events-none"></div>

            <div>
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-full bg-slate-800/80 text-[#c2f13c] border border-slate-700">
                        Multi-Portal Network
                    </span>
                    <span class="text-xs text-slate-400 font-medium">3 Branches Active</span>
                </div>

                <h3 class="text-xl font-extrabold tracking-tight text-white mb-1">Infrastructure Status</h3>
                <p class="text-xs text-slate-400">All 3 staff portals partitioned with strict RBAC access.</p>

                <div class="my-6 space-y-2.5">
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-white/5 border border-white/10 text-xs">
                        <span class="flex items-center gap-2 text-slate-300">
                            <i class="fa-solid fa-flask text-[#c2f13c]"></i> Laboratory (Main HQ)
                        </span>
                        <span class="font-bold text-emerald-400">Online</span>
                    </div>
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-white/5 border border-white/10 text-xs">
                        <span class="flex items-center gap-2 text-slate-300">
                            <i class="fa-solid fa-building text-blue-400"></i> Collection Center (Gulberg)
                        </span>
                        <span class="font-bold text-emerald-400">Online</span>
                    </div>
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-white/5 border border-white/10 text-xs">
                        <span class="flex items-center gap-2 text-slate-300">
                            <i class="fa-solid fa-x-ray text-purple-400"></i> Diagnostic Center (Imaging)
                        </span>
                        <span class="font-bold text-emerald-400">Online</span>
                    </div>
                </div>

                <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 text-xs text-slate-300 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-users text-[#c2f13c]"></i>
                        <span><?= $userCount ?> Authorized Staff Users</span>
                    </span>
                    <span class="font-bold text-[#c2f13c]">Bcrypt Auth</span>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-800/80 flex items-center justify-between">
                <span class="text-xs text-slate-400">Lab Dash Pro Enterprise</span>
                <a href="/portals/admin/labs.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs transition-colors">
                    <span>Manage Branches</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>
        </div>

        <!-- Quick Management Hub -->
        <div class="lg:col-span-4 bg-white rounded-3xl p-6 border border-slate-200/90 shadow-card flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Administration</span>
                    <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
                        Operational
                    </span>
                </div>

                <h3 class="text-xl font-extrabold text-slate-900 tracking-tight mb-1">Administrative Utilities</h3>
                <p class="text-xs text-slate-500 mb-5">Core modules for configuration and customization.</p>

                <div class="grid grid-cols-2 gap-2.5">
                    <a href="/portals/admin/users.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-slate-100 border border-slate-100 transition-all text-left">
                        <i class="fa-solid fa-user-shield text-xl text-blue-600 mb-1 block"></i>
                        <span class="text-xs font-bold text-slate-900 block">Staff Accounts</span>
                        <span class="text-[10px] text-slate-500"><?= $userCount ?> accounts</span>
                    </a>

                    <a href="/portals/admin/tests.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-slate-100 border border-slate-100 transition-all text-left">
                        <i class="fa-solid fa-flask text-xl text-emerald-600 mb-1 block"></i>
                        <span class="text-xs font-bold text-slate-900 block">Catalog Tests</span>
                        <span class="text-[10px] text-slate-500"><?= $testCount ?> tests active</span>
                    </a>

                    <a href="/portals/admin/packages.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-slate-100 border border-slate-100 transition-all text-left">
                        <i class="fa-solid fa-boxes-stacked text-xl text-amber-600 mb-1 block"></i>
                        <span class="text-xs font-bold text-slate-900 block">Test Packages</span>
                        <span class="text-[10px] text-slate-500"><?= $pkgCount ?> profiles</span>
                    </a>

                    <a href="/portals/admin/branding.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-slate-100 border border-slate-100 transition-all text-left">
                        <i class="fa-solid fa-heading text-xl text-purple-600 mb-1 block"></i>
                        <span class="text-xs font-bold text-slate-900 block">Report Headers</span>
                        <span class="text-[10px] text-slate-500">Logo &amp; Footer</span>
                    </a>
                </div>
            </div>

            <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>System Settings</span>
                <a href="/portals/admin/settings.php" class="font-bold text-blue-600 hover:underline">Configuration &rarr;</a>
            </div>
        </div>

        <!-- Catalog Spectrum Donut -->
        <div class="lg:col-span-4 bg-white rounded-3xl p-6 border border-slate-200/90 shadow-card flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Diagnostic Inventory</span>
                    <a href="/portals/admin/tests.php" class="text-xs font-bold text-blue-600 hover:text-blue-700">Catalog &gt;</a>
                </div>

                <h3 class="text-xl font-extrabold text-slate-900 tracking-tight mb-1">Catalog &amp; Package Spectrum</h3>
                <p class="text-xs text-slate-500 mb-4">Total pathology and diagnostic tests configured.</p>

                <div class="flex items-center gap-6 my-2">
                    <div class="relative w-28 h-28 shrink-0 flex items-center justify-center">
                        <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#f1f5f9" stroke-width="12" />
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#2563eb" stroke-width="12"
                                    stroke-dasharray="<?= $lenTests ?> <?= $c - $lenTests ?>"
                                    stroke-dashoffset="<?= $offsetTests ?>"
                                    stroke-linecap="round" />
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#a3e635" stroke-width="12"
                                    stroke-dasharray="<?= $lenPkg ?> <?= $c - $lenPkg ?>"
                                    stroke-dashoffset="<?= $offsetPkg ?>"
                                    stroke-linecap="round" />
                        </svg>
                        <div class="absolute flex flex-col items-center justify-center text-center">
                            <span class="text-2xl font-black text-slate-900 leading-none"><?= $totalCatalog ?></span>
                            <span class="text-[9px] uppercase font-bold text-slate-400 mt-0.5">Catalog</span>
                        </div>
                    </div>

                    <div class="space-y-2 flex-1">
                        <div class="flex items-center justify-between px-3 py-1.5 rounded-full bg-blue-50 border border-blue-200 text-xs font-bold text-blue-700">
                            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Tests</span>
                            <span><?= $testCount ?></span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-1.5 rounded-full bg-emerald-50 border border-emerald-200 text-xs font-bold text-emerald-800">
                            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Packages</span>
                            <span><?= $pkgCount ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <a href="/portals/admin/tests.php" class="mt-4 w-full flex items-center justify-center gap-2 py-3 px-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs sm:text-sm transition-all shadow-md">
                <i class="fa-solid fa-plus"></i>
                <span>Add New Test or Package</span>
            </a>
        </div>

    </div>

</div>
<?php
$content = ob_get_clean();

render_page('Admin Dashboard', 'admin', 'dashboard', $content);
