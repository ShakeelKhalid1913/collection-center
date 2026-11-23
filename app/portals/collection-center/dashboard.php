<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';

ob_start();
?>
<div class="space-y-6">

    <!-- Minimal Clean Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-200">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight">Collection Center Hub</h1>
            <p class="text-xs text-slate-500 font-medium">Desktop Workstation &bull; Intake, Billing &amp; Logistics</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="/portals/patients/quick-register.php" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-200 shadow-2xs transition-colors" title="Fast intake / rapid counter">
                <i class="fa-solid fa-bolt text-xs text-amber-500"></i>
                <span class="hidden sm:inline">Quick</span>
            </a>
            <a href="/portals/patients/register.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-colors">
                <i class="fa-solid fa-user-plus text-xs"></i>
                <span>Register Patient</span>
            </a>
            <a href="/portals/lab-entries/new.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition-colors">
                <i class="fa-solid fa-flask-vial text-xs text-[#c2f13c]"></i>
                <span>Book Tests</span>
            </a>
            <a href="/portals/results/entry.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white hover:bg-slate-100 text-slate-700 font-bold text-xs border border-slate-300 shadow-sm transition-colors">
                <i class="fa-solid fa-keyboard text-xs"></i>
                <span>Result Entry</span>
            </a>
            <a href="/portals/reports/preview.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs shadow-sm transition-colors" title="Final Diagnostic Report Preview & Print">
                <i class="fa-solid fa-file-medical text-xs"></i>
                <span>Final Report</span>
            </a>
        </div>
    </div>

    <!-- Patient Quick Search Bar -->
    <div class="p-3 sm:p-4 bg-white rounded-2xl border border-slate-200/90 shadow-2xs">
        <form method="get" action="/portals/reports/history.php" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="q" 
                       placeholder="Search patient through Slip #, MR Number, Name, Phone Number, or Lab Number..." 
                       class="w-full pl-10 pr-4 py-2.5 text-sm font-semibold rounded-xl border border-slate-300 bg-slate-50/70 focus:bg-white focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-600 transition-all placeholder:text-slate-400" 
                       required>
            </div>
            <button type="submit" class="btn btn-primary text-xs font-bold px-5 py-2.5 rounded-xl whitespace-nowrap shadow-sm">
                <i class="fa-solid fa-search mr-1.5"></i> Search Patient
            </button>
        </form>
    </div>

    <!-- ========================================================
         DESKTOP SOFTWARE WORKSTATION TILES (Matching Collection Center Sidebar)
         ======================================================== -->
    <div class="space-y-8">

        <!-- GROUP 1: PATIENTS -->
        <div class="space-y-3">
            <div class="pb-1.5 border-b border-slate-200">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-address-book text-emerald-600"></i> Patients
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <!-- 1. Register Patient -->
                <a href="/portals/patients/register.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#38bdf8] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-sky-400/40 min-h-[88px]">
                    <i class="fa-solid fa-user-plus text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Register Patient</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">نیا مریض رجسٹر کریں</div>
                    </div>
                </a>

                <!-- 2. Patient Records -->
                <a href="/portals/patients/records.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#c084fc] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-purple-400/40 min-h-[88px]">
                    <i class="fa-solid fa-address-book text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Patient Records</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">مریضوں کا مکمل ریکارڈ</div>
                    </div>
                </a>

                <!-- 3. Patient Card -->
                <a href="/portals/patients/card.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#2dd4bf] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-teal-400/40 min-h-[88px]">
                    <i class="fa-solid fa-id-card text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Patient Card</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">مریض کا ٹوکن / کارڈ</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- GROUP 2: LAB ENTRIES -->
        <div class="space-y-3">
            <div class="pb-1.5 border-b border-slate-200">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-file-waveform text-sky-600"></i> Lab Entries
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                <!-- 1. Book Tests -->
                <a href="/portals/lab-entries/new.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#86efac] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-green-400/40 min-h-[88px]">
                    <i class="fa-solid fa-flask-vial text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Book Tests</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">ٹیسٹ کی بکنگ اور بل</div>
                    </div>
                </a>

                <!-- 2. Pending -->
                <a href="/portals/lab-entries/pending.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fbbf24] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-amber-400/40 min-h-[88px]">
                    <i class="fa-solid fa-clock text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Pending</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">زیر التواء سیمپل اور آرڈرز</div>
                    </div>
                </a>

                <!-- 3. Sample Tracking & Barcode -->
                <a href="/portals/sample-tracking.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#60a5fa] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-blue-400/40 min-h-[88px]">
                    <i class="fa-solid fa-barcode text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Sample Tracking &amp; Barcode</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">سیمپل ٹریکنگ اور بارکوڈ</div>
                    </div>
                </a>

                <!-- 4. History -->
                <a href="/portals/lab-entries/history.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fde047] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-yellow-400/40 min-h-[88px]">
                    <i class="fa-solid fa-clock-rotate-left text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">History</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">سابقہ اینٹریز اور ہسٹری</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- GROUP 3: BILLING & FINANCE -->
        <div class="space-y-3">
            <div class="pb-1.5 border-b border-slate-200">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-calculator text-amber-600"></i> Billing &amp; Finance
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
                <!-- 1. Daily Closing Slip -->
                <a href="/portals/financial-closing.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fed7aa] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-orange-400/40 min-h-[88px]">
                    <i class="fa-solid fa-calculator text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Daily Closing Slip</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">روزانہ کیش کلوزنگ سلپ</div>
                    </div>
                </a>

                <!-- 2. Summary Reports Engine -->
                <a href="/portals/summary-reports.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#bae6fd] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-sky-300/40 min-h-[88px]">
                    <i class="fa-solid fa-chart-pie text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Summary Reports Engine</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">خلاصہ رپورٹس انجن</div>
                    </div>
                </a>

                <!-- 3. Patient Dues -->
                <a href="/portals/patient-dues.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#a7f3d0] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-emerald-300/40 min-h-[88px]">
                    <i class="fa-solid fa-hand-holding-dollar text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Patient Dues</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">مریضوں کے بقایا جات</div>
                    </div>
                </a>

                <!-- 4. Expenses -->
                <a href="/portals/expenses.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fca5a5] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-red-300/40 min-h-[88px]">
                    <i class="fa-solid fa-receipt text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Expenses</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">روزانہ اخراجات</div>
                    </div>
                </a>

                <!-- 5. Receipts -->
                <a href="/portals/receipts.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#e9d5ff] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-purple-300/40 min-h-[88px]">
                    <i class="fa-solid fa-file-invoice text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Receipts</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">رسیدیں اور بل</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- GROUP 4: MORE & SETTINGS -->
        <div class="space-y-3">
            <div class="pb-1.5 border-b border-slate-200">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-ellipsis text-slate-600"></i> More
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
                <!-- 1. Reports -->
                <a href="/portals/reports.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#99f6e4] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-teal-300/40 min-h-[88px]">
                    <i class="fa-solid fa-file-medical text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Reports</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">ٹیسٹ رپورٹس اور نتائج</div>
                    </div>
                </a>

                <!-- 2. Rate List (Print/PDF) -->
                <a href="/portals/rate-list.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fed7aa] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-orange-300/40 min-h-[88px]">
                    <i class="fa-solid fa-file-pdf text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Rate List (PDF)</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">پرنٹ ایبل ریٹ لسٹ</div>
                    </div>
                </a>

                <!-- 3. Test Packages -->
                <a href="/portals/tests/packages.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#dcfce7] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-emerald-300/40 min-h-[88px]">
                    <i class="fa-solid fa-boxes-packing text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Test Packages</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">ٹیسٹ پیکجز لسٹ</div>
                    </div>
                </a>

                <!-- 4. Header / Footer -->
                <a href="/portals/branding.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fef08a] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-yellow-300/40 min-h-[88px]">
                    <i class="fa-solid fa-heading text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Header / Footer</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">ہیڈر اور فٹر سیٹنگز</div>
                    </div>
                </a>

                <!-- 5. Settings -->
                <a href="/portals/settings.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#cbd5e1] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-slate-300/40 min-h-[88px]">
                    <i class="fa-solid fa-gear text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-slate-900 leading-tight">Settings</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Sans Arabic', 'Noto Nastaliq Urdu', Tahoma, sans-serif;">سینٹر سیٹنگز اور کنفیگریشن</div>
                    </div>
                </a>
            </div>
        </div>

    </div>

</div>

<?php
$content = ob_get_clean();

render_page('Dashboard', 'collection-center', 'dashboard', $content);
