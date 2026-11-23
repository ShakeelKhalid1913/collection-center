<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

require_auth('admin');

$isSuper = is_super_admin();
$orgId = current_organization_id();
$orgName = current_organization_name();

// -------------------------------------------------------------
// SUPER ADMIN METRICS (Platform-Wide Across All Client Labs)
// -------------------------------------------------------------
if ($isSuper) {
    $orgCount = 1;
    try {
        $row = db()->fetchOne("SELECT COUNT(*) as c FROM organizations");
        $orgCount = !empty($row['c']) ? (int)$row['c'] : 1;
    } catch (\Throwable) {}

    $globalBranchCount = 3;
    try {
        $row = db()->fetchOne("SELECT COUNT(*) as c FROM branches");
        $globalBranchCount = !empty($row['c']) ? (int)$row['c'] : 3;
    } catch (\Throwable) {}

    $globalUserCount = 5;
    try {
        $row = db()->fetchOne("SELECT COUNT(*) as c FROM users");
        $globalUserCount = !empty($row['c']) ? (int)$row['c'] : 5;
    } catch (\Throwable) {}

    $globalAuditCount = 0;
    try {
        $row = db()->fetchOne("SELECT COUNT(*) as c FROM audit_logs");
        $globalAuditCount = !empty($row['c']) ? (int)$row['c'] : 0;
    } catch (\Throwable) {}
} else {
    // -------------------------------------------------------------
    // LAB ADMIN METRICS (Strictly Scoped to Single Lab: $orgId)
    // -------------------------------------------------------------
    $labUserCount = 0;
    try {
        $row = db()->fetchOne(
            "SELECT COUNT(*) as c FROM users WHERE organization_id = :org AND (portal != 'admin' OR portal IS NULL)",
            ['org' => $orgId]
        );
        $labUserCount = !empty($row['c']) ? (int)$row['c'] : 0;
    } catch (\Throwable) {}

    $labBranchCount = 0;
    try {
        $row = db()->fetchOne("SELECT COUNT(*) as c FROM branches WHERE organization_id = :org", ['org' => $orgId]);
        $labBranchCount = !empty($row['c']) ? (int)$row['c'] : 0;
    } catch (\Throwable) {}

    $labAuditCount = 0;
    try {
        $row = db()->fetchOne("SELECT COUNT(*) as c FROM audit_logs WHERE organization_id = :org", ['org' => $orgId]);
        $labAuditCount = !empty($row['c']) ? (int)$row['c'] : 0;
    } catch (\Throwable) {}

    $todayBookings = 0;
    try {
        $row = db()->fetchOne(
            "SELECT COUNT(*) as c FROM lab_entries WHERE organization_id = :org AND DATE(created_at) = CURRENT_DATE",
            ['org' => $orgId]
        );
        $todayBookings = !empty($row['c']) ? (int)$row['c'] : 0;
    } catch (\Throwable) {}
}

ob_start();
?>
<div class="space-y-6">

    <?php if ($isSuper): ?>
    <!-- ========================================================
         VIEW A: SUPER ADMIN (PLATFORM OWNER / SYSTEM OWNER)
         ======================================================== -->

    <!-- Super Admin Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Platform Master Console</h1>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-slate-900 text-[#c2f13c] text-[10px] font-black uppercase tracking-wider shadow-xs">
                    <i class="fa-solid fa-crown text-[9px]"></i> Super Admin
                </span>
            </div>
            <p class="text-xs text-slate-500 font-medium">SaaS Multi-Tenant Operations &bull; Client Laboratories, Multi-Branch Routing &amp; System Infrastructure</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="/portals/locked-records.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-sm transition-colors">
                <i class="fa-solid fa-lock text-white text-xs"></i>
                <span>Locked Bills &amp; Reports</span>
            </a>
            <a href="/portals/organizations.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition-colors">
                <i class="fa-solid fa-plus text-[#c2f13c] text-xs"></i>
                <span>Onboard New Lab</span>
            </a>
            <a href="/portals/audit-trail.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white hover:bg-slate-100 text-slate-700 font-bold text-xs border border-slate-300 shadow-2xs transition-colors">
                <i class="fa-solid fa-shield-halved text-xs text-slate-500"></i>
                <span>Global Audit Logs</span>
            </a>
            <a href="/database/setup.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white hover:bg-slate-100 text-slate-700 font-bold text-xs border border-slate-300 shadow-2xs transition-colors">
                <i class="fa-solid fa-database text-xs text-slate-500"></i>
                <span>DB Health</span>
            </a>
        </div>
    </div>

    <!-- Super Admin Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-building-columns"></i>
            </div>
            <div>
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Client Laboratories</p>
                <p class="text-2xl font-black text-slate-900 leading-tight"><?= $orgCount ?></p>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-sky-100 text-sky-800 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-sitemap"></i>
            </div>
            <div>
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Network Branches</p>
                <p class="text-2xl font-black text-slate-900 leading-tight"><?= $globalBranchCount ?></p>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-purple-100 text-purple-800 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total System Users</p>
                <p class="text-2xl font-black text-slate-900 leading-tight"><?= $globalUserCount ?></p>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div>
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Security Audit Logs</p>
                <p class="text-2xl font-black text-slate-900 leading-tight"><?= $globalAuditCount ?></p>
            </div>
        </div>
    </div>

    <!-- ========================================================
         SUPER ADMIN COLORFUL WORKSTATION TILES
         ======================================================== -->
    <div class="space-y-8">
        
        <!-- GROUP 1: SAAS CLIENT LABS & LICENSING -->
        <div class="space-y-3">
            <div class="pb-1.5 border-b border-slate-200">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-building-columns text-emerald-600"></i> SaaS Client Laboratories &amp; Multi-Tenancy
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <!-- 1. Client Laboratories -->
                <a href="/portals/organizations.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#86efac] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-green-400/40 min-h-[88px]">
                    <i class="fa-solid fa-building-columns text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Client Laboratories</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">کلائنٹ لیبارٹریز اور تنظیمیں</div>
                    </div>
                </a>

                <!-- 2. Cross-Lab Branches -->
                <a href="/portals/labs.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#38bdf8] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-sky-400/40 min-h-[88px]">
                    <i class="fa-solid fa-sitemap text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">All Network Branches</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">تمام نیٹ ورک برانچز اور کلیکشن پوائنٹس</div>
                    </div>
                </a>

                <!-- 3. Global Users & Admins -->
                <a href="/portals/users.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#c084fc] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-purple-400/40 min-h-[88px]">
                    <i class="fa-solid fa-users text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Global Staff &amp; Admins</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">تمام لیبز کے صارفین اور ایڈمنز</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- GROUP 2: PLATFORM SECURITY & INFRASTRUCTURE -->
        <div class="space-y-3">
            <div class="pb-1.5 border-b border-slate-200">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved text-amber-600"></i> Platform Security &amp; Infrastructure
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <!-- 1. Global Audit Trail -->
                <a href="/portals/audit-trail.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fbbf24] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-amber-400/40 min-h-[88px]">
                    <i class="fa-solid fa-clock-rotate-left text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Global Audit Trail</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">سسٹم وائیڈ سیکیورٹی اور لاگز آڈٹ</div>
                    </div>
                </a>

                <!-- 2. Database Health -->
                <a href="/database/setup.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#2dd4bf] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-teal-400/40 min-h-[88px]">
                    <i class="fa-solid fa-database text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Database &amp; Schema Health</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">ڈیٹا بیس ہیلتھ اور اسکیما اپڈیٹ</div>
                    </div>
                </a>

                <!-- 3. Platform Settings -->
                <a href="/portals/settings.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fb7185] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-rose-400/40 min-h-[88px]">
                    <i class="fa-solid fa-sliders text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Platform System Settings</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">پلیٹ فارم اور سرور سیٹنگز</div>
                    </div>
                </a>

                <!-- 4. Locked Bills & Reports -->
                <a href="/portals/locked-records.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#f43f5e] text-white hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-rose-500/40 min-h-[88px]">
                    <i class="fa-solid fa-lock text-3xl text-white shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-white leading-tight flex items-center gap-1.5">
                            Locked Bills &amp; Reports
                            <span class="px-1.5 py-0.5 rounded-md bg-white text-rose-700 text-[9px] font-black uppercase">Admin</span>
                        </div>
                        <div class="text-xs text-rose-100 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">تمام لاک شدہ بل اور رپورٹ ان لاک کنٹرول</div>
                    </div>
                </a>
            </div>
        </div>

    </div>

    <?php else: ?>
    <!-- ========================================================
         VIEW B: LAB ADMIN (SINGLE LABORATORY OWNER / MANAGER)
         ======================================================== -->

    <!-- Lab Admin Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Laboratory Admin Console</h1>
                <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-slate-900 text-[#c2f13c] text-[11px] font-bold uppercase tracking-wider">
                    <i class="fa-solid fa-hospital text-[10px]"></i> <?= e($orgName) ?>
                </span>
            </div>
            <p class="text-xs text-slate-500 font-medium">Operations Management &bull; Staff Accounts (RBAC), Collection Branches, Branding &amp; Financial Closing</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="/portals/locked-records.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-sm transition-colors">
                <i class="fa-solid fa-lock text-white text-xs"></i>
                <span>Locked Bills &amp; Reports</span>
            </a>
            <a href="/portals/users.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition-colors">
                <i class="fa-solid fa-user-plus text-[#c2f13c] text-xs"></i>
                <span>Add Staff User</span>
            </a>
            <a href="/portals/labs.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white hover:bg-slate-100 text-slate-700 font-bold text-xs border border-slate-300 shadow-2xs transition-colors">
                <i class="fa-solid fa-sitemap text-xs text-slate-500"></i>
                <span>Add Collection Point</span>
            </a>
            <a href="/portals/financial-closing.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white hover:bg-slate-100 text-slate-700 font-bold text-xs border border-slate-300 shadow-2xs transition-colors">
                <i class="fa-solid fa-calculator text-xs text-slate-500"></i>
                <span>Financial Closing</span>
            </a>
        </div>
    </div>

    <!-- Lab Admin Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-sky-100 text-sky-800 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-user-shield"></i>
            </div>
            <div>
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Our Active Staff</p>
                <p class="text-2xl font-black text-slate-900 leading-tight"><?= $labUserCount ?></p>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-sitemap"></i>
            </div>
            <div>
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Our Branches &amp; Points</p>
                <p class="text-2xl font-black text-slate-900 leading-tight"><?= $labBranchCount ?></p>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-purple-100 text-purple-800 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-flask-vial"></i>
            </div>
            <div>
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Today's Lab Orders</p>
                <p class="text-2xl font-black text-slate-900 leading-tight"><?= $todayBookings ?></p>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div>
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Lab Activity Logs</p>
                <p class="text-2xl font-black text-slate-900 leading-tight"><?= $labAuditCount ?></p>
            </div>
        </div>
    </div>

    <!-- ========================================================
         LAB ADMIN COLORFUL WORKSTATION TILES
         ======================================================== -->
    <div class="space-y-8">

        <!-- GROUP 1: OUR LAB STAFF & ACCESS CONTROL -->
        <div class="space-y-3">
            <div class="pb-1.5 border-b border-slate-200">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-user-shield text-sky-600"></i> Our Lab Staff &amp; Access Control
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <!-- 1. Users & Permissions -->
                <a href="/portals/users.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#38bdf8] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-sky-400/40 min-h-[88px]">
                    <i class="fa-solid fa-user-shield text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Staff &amp; Permissions (RBAC)</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">لیب اور کلیکشن سنٹر سٹاف کے اختیارات کا انتظام</div>
                    </div>
                </a>

                <!-- 2. Audit Trail & Logs -->
                <a href="/portals/audit-trail.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#c084fc] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-purple-400/40 min-h-[88px]">
                    <i class="fa-solid fa-clock-rotate-left text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Lab Security Logs</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">سٹاف کی سرگرمی اور ڈیلیٹ / ایڈٹ لاگز</div>
                    </div>
                </a>

                <!-- 3. Locked Bills & Reports (Admin Unlock) -->
                <a href="/portals/locked-records.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#f43f5e] text-white hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-rose-500/40 min-h-[88px]">
                    <i class="fa-solid fa-lock text-3xl text-white shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-white leading-tight flex items-center gap-1.5">
                            Locked Bills &amp; Reports
                            <span class="px-1.5 py-0.5 rounded-md bg-white text-rose-700 text-[9px] font-black uppercase">Admin</span>
                        </div>
                        <div class="text-xs text-rose-100 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">لاک بل اور رپورٹ ان لاک کنٹرول</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- GROUP 2: LABS & BRANCH NETWORK -->
        <div class="space-y-3">
            <div class="pb-1.5 border-b border-slate-200">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-network-wired text-emerald-600"></i> Our Branches &amp; Collection Points
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-3.5">
                <!-- 1. Labs & Branches -->
                <a href="/portals/labs.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#86efac] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-green-400/40 min-h-[88px]">
                    <i class="fa-solid fa-sitemap text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Labs &amp; Collection Centers</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">ہمارے کلیکشن سنٹرز اور برانچز کا اندراج</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- GROUP 3: ADMINISTRATIVE AUDITS & REPORTS -->
        <div class="space-y-3">
            <div class="pb-1.5 border-b border-slate-200">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-chart-line text-amber-600"></i> Financial Audits &amp; Daily Closing
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-3.5">
                <!-- 1. Summary Reports Engine -->
                <a href="/portals/summary-reports.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fbbf24] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-amber-400/40 min-h-[88px]">
                    <i class="fa-solid fa-chart-pie text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Revenue &amp; Shift Reports</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">لیب اور کلیکشن سنٹر کی آمدن سمری</div>
                    </div>
                </a>

                <!-- 2. Financial Closing Audit -->
                <a href="/portals/financial-closing.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#2dd4bf] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-teal-400/40 min-h-[88px]">
                    <i class="fa-solid fa-calculator text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Daily Financial Closing</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">روزانہ کیش کلوزنگ اور ڈسکاؤنٹ آڈٹ</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- GROUP 4: BRANDING & LAB PREFERENCES -->
        <div class="space-y-3">
            <div class="pb-1.5 border-b border-slate-200">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-rose-600"></i> Lab Branding &amp; Settings
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-3.5">
                <!-- 1. Client Branding & Letterhead -->
                <a href="/portals/branding.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fb7185] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-rose-400/40 min-h-[88px]">
                    <i class="fa-solid fa-heading text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Lab Branding &amp; Letterhead</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">رپورٹ ہیڈر، لوگو اور رابطہ نمبر</div>
                    </div>
                </a>

                <!-- 2. System Settings -->
                <a href="/portals/settings.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#c2f13c] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-lime-400/40 min-h-[88px]">
                    <i class="fa-solid fa-sliders text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Lab Preferences &amp; Config</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">لیب اور رپورٹ فارمیٹ کی سیٹنگز</div>
                    </div>
                </a>
            </div>
        </div>

    </div>
    <?php endif; ?>

</div>
<?php
$content = ob_get_clean();

$pageTitle = $isSuper ? 'Platform Master Console' : 'Lab Admin Dashboard';
render_page($pageTitle, 'admin', 'dashboard', $content);
