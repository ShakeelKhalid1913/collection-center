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
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Laboratory Dashboard</h1>
            <p class="text-xs text-slate-500 font-medium">Desktop Workstation &bull; Direct access to all features</p>
        </div>
        <div class="flex items-center gap-2">
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
         DESKTOP SOFTWARE WORKSTATION TILES (100% Matching Sidebar Options)
         ======================================================== -->
    <div class="space-y-8">

        <!-- GROUP 1: LAB ENTRIES & REPORTS -->
        <div class="space-y-3">
            <div class="pb-1.5 border-b border-slate-200">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-file-waveform text-sky-600"></i> Lab Entries &amp; Reports
                </h3>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                <!-- 1. Register Patient -->
                <a href="/portals/patients/register.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#38bdf8] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-sky-400/40 min-h-[88px]">
                    <i class="fa-solid fa-user-plus text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Register Patient</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">نیا مریض رجسٹر کریں (مرحلہ 1)</div>
                    </div>
                </a>

                <!-- 2. Pending Orders -->
                <a href="/portals/lab-entries/pending.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#c084fc] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-purple-400/40 min-h-[88px]">
                    <i class="fa-solid fa-clock text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Pending Orders</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">زیر التواء آرڈرز</div>
                    </div>
                </a>

                <!-- 3. Edit Entry & Bill -->
                <a href="/portals/lab-entries/edit.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#2dd4bf] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-teal-400/40 min-h-[88px]">
                    <i class="fa-solid fa-user-pen text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Edit Entry &amp; Bill</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">اینٹری اور بل میں ترمیم</div>
                    </div>
                </a>

                <!-- 4. Sample Tracking & Collection -->
                <a href="/portals/sample-collection.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#86efac] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-green-400/40 min-h-[88px]">
                    <i class="fa-solid fa-vial text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Sample Tracking &amp; Collection</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">سیمپل کلیکشن اور ٹریکنگ</div>
                    </div>
                </a>

                <!-- 5. Report Edit -->
                <a href="/portals/results/entry.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#60a5fa] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-blue-400/40 min-h-[88px]">
                    <i class="fa-solid fa-file-pen text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Report Edit</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">رپورٹ ایڈیٹنگ اور رزلٹ</div>
                    </div>
                </a>

                <!-- 6. Report History -->
                <a href="/portals/reports/history.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fbbf24] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-amber-400/40 min-h-[88px]">
                    <i class="fa-solid fa-folder-open text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Report History</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">رپورٹ ہسٹری اور تلاش</div>
                    </div>
                </a>

                <!-- 7. Ultrasound & Radiology -->
                <a href="/portals/reports/radiology.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fb7185] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-rose-400/40 min-h-[88px]">
                    <i class="fa-solid fa-file-waveform text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Ultrasound &amp; Radiology</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">الٹراساؤنڈ اور ریڈیالوجی</div>
                    </div>
                </a>

                <!-- 8. Test Estimate -->
                <a href="/portals/tests/estimate.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fde047] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-yellow-400/40 min-h-[88px]">
                    <i class="fa-solid fa-calculator text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Test Estimate</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">ٹیسٹ ایسٹیمیٹ / کوٹیشن</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- GROUP 2: PATIENTS -->
        <div class="space-y-3">
            <div class="pb-1.5 border-b border-slate-200">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-address-book text-emerald-600"></i> Patients
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                <!-- 1. Patient Records -->
                <a href="/portals/patients/records.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#38bdf8] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-sky-400/40 min-h-[88px]">
                    <i class="fa-solid fa-address-book text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Patient Records</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">مریضوں کا مکمل ریکارڈ</div>
                    </div>
                </a>

                <!-- 2. Print Patient Card -->
                <a href="/portals/patients/card.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#c084fc] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-purple-400/40 min-h-[88px]">
                    <i class="fa-solid fa-id-card text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Print Patient Card</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">مریض کا کارڈ پرنٹ کریں</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- GROUP 3: OPERATIONS & FINANCE -->
        <div class="space-y-3">
            <div class="pb-1.5 border-b border-slate-200">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-calculator text-amber-600"></i> Operations &amp; Finance
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                <!-- 1. Daily Closing Slip -->
                <a href="/portals/financial-closing.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fed7aa] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-orange-400/40 min-h-[88px]">
                    <i class="fa-solid fa-calculator text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Daily Closing Slip</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">روزانہ کیش کلوزنگ سلپ</div>
                    </div>
                </a>

                <!-- 2. Summary Reports Engine -->
                <a href="/portals/summary-reports.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fbbf24] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-amber-400/40 min-h-[88px]">
                    <i class="fa-solid fa-chart-pie text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Summary Reports Engine</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">خلاصہ رپورٹس انجن</div>
                    </div>
                </a>

                <!-- 3. Collection Centers -->
                <a href="/portals/collection-centers.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#2dd4bf] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-teal-400/40 min-h-[88px]">
                    <i class="fa-solid fa-code-branch text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Collection Centers</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">کلیکشن سینٹرز</div>
                    </div>
                </a>

                <!-- 4. Staff Directory -->
                <a href="/portals/staff/index.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#86efac] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-green-400/40 min-h-[88px]">
                    <i class="fa-solid fa-id-badge text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Staff Directory</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">سٹاف ڈائریکٹری</div>
                    </div>
                </a>

                <!-- 5. Staff Attendance -->
                <a href="/portals/staff/attendance.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fde047] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-yellow-400/40 min-h-[88px]">
                    <i class="fa-solid fa-camera text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Staff Attendance</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">سٹاف کی حاضری</div>
                    </div>
                </a>

                <!-- 6. Patient Dues -->
                <a href="/portals/patient-dues.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#a7f3d0] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-emerald-300/40 min-h-[88px]">
                    <i class="fa-solid fa-hand-holding-dollar text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Patient Dues</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">مریضوں کے بقایا جات</div>
                    </div>
                </a>

                <!-- 7. Expenses -->
                <a href="/portals/expenses.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fca5a5] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-red-300/40 min-h-[88px]">
                    <i class="fa-solid fa-file-invoice-dollar text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Expenses</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">روزانہ اخراجات</div>
                    </div>
                </a>

                <!-- 8. Stock & Returns -->
                <a href="/portals/stock-management.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#cbd5e1] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-slate-300/40 min-h-[88px]">
                    <i class="fa-solid fa-boxes-stacked text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Stock &amp; Returns</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">میڈیسن / لیب اسٹاک</div>
                    </div>
                </a>

                <!-- 9. Doctor's Share -->
                <a href="/portals/doctors-share.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#bfdbfe] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-blue-300/40 min-h-[88px]">
                    <i class="fa-solid fa-user-doctor text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Doctor's Share</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">ڈاکٹر کا شیئر / کمیشن</div>
                    </div>
                </a>

                <!-- 10. Sample Transit & Tracking -->
                <a href="/portals/sample-tracking.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#c084fc] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-purple-400/40 min-h-[88px]">
                    <i class="fa-solid fa-barcode text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Sample Transit &amp; Tracking</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">سیمپل ترسیل اور ٹریکنگ</div>
                    </div>
                </a>

                <!-- 11. Waste Record -->
                <a href="/portals/waste-record.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fed7aa] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-orange-400/40 min-h-[88px]">
                    <i class="fa-solid fa-trash-can text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Waste Record</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">ویسٹ اور ضیاع ریکارڈ</div>
                    </div>
                </a>

                <!-- 12. Delete Entry -->
                <a href="/portals/delete-entry.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fda4af] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-rose-300/40 min-h-[88px]">
                    <i class="fa-solid fa-user-xmark text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Delete Entry</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">اینٹری حذف کریں</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- GROUP 4: CATALOG & TEMPLATES -->
        <div class="space-y-3">
            <div class="pb-1.5 border-b border-slate-200">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-flask text-purple-600"></i> Catalog &amp; Templates
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                <!-- 1. Add Test -->
                <a href="/portals/tests/index.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#99f6e4] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-teal-300/40 min-h-[88px]">
                    <i class="fa-solid fa-flask text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Add Test</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">نیا ٹیسٹ شامل کریں</div>
                    </div>
                </a>

                <!-- 2. Add / Update Price -->
                <a href="/portals/tests/prices.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fef08a] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-yellow-300/40 min-h-[88px]">
                    <i class="fa-solid fa-tags text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Add / Update Price</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">ٹیسٹ ریٹس اور قیمتیں</div>
                    </div>
                </a>

                <!-- 2b. Rate List (Printable & PDF) -->
                <a href="/portals/tests/rate-list.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fed7aa] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-orange-300/40 min-h-[88px]">
                    <i class="fa-solid fa-file-pdf text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Rate List (PDF)</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">پرنٹ ایبل ریٹ لسٹ</div>
                    </div>
                </a>

                <!-- 2c. Test Packages -->
                <a href="/portals/tests/packages.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#dcfce7] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-emerald-300/40 min-h-[88px]">
                    <i class="fa-solid fa-boxes-packing text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Test Packages</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">ٹیسٹ پیکجز بنائیں</div>
                    </div>
                </a>

                <!-- 3. B2B Wholesale Rates -->
                <a href="/portals/tests/b2b-rates.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#bae6fd] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-sky-300/40 min-h-[88px]">
                    <i class="fa-solid fa-handshake text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">B2B Wholesale Rates</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">بی ٹو بی ہول سیل ریٹس</div>
                    </div>
                </a>

                <!-- 4. Report Templates -->
                <a href="/portals/templates.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#e9d5ff] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-purple-300/40 min-h-[88px]">
                    <i class="fa-solid fa-bookmark text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Report Templates</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">رپورٹ ٹیمپلیٹس</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- GROUP 5: SETTINGS & MORE -->
        <div class="space-y-3">
            <div class="pb-1.5 border-b border-slate-200">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-gear text-slate-600"></i> Settings &amp; More
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
                <!-- 1. Header & Footer -->
                <a href="/portals/settings/report-template.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#e9d5ff] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-purple-300/40 min-h-[88px]">
                    <i class="fa-solid fa-heading text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Header &amp; Footer</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">ہیڈر اور فٹر سیٹنگز</div>
                    </div>
                </a>

                <!-- 2. All Dates History -->
                <a href="/portals/archive.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#bae6fd] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-sky-300/40 min-h-[88px]">
                    <i class="fa-solid fa-clock-rotate-left text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">All Dates History</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">تاریخ وار مکمل ہسٹری</div>
                    </div>
                </a>

                <!-- 3. Patient Bill -->
                <a href="/portals/receipts.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#86efac] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-green-400/40 min-h-[88px]">
                    <i class="fa-solid fa-receipt text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Patient Bill</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">مریض کا انوائس اور بل</div>
                    </div>
                </a>

                <!-- 4. Lab Profile -->
                <a href="/portals/settings/profile.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#fde047] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-yellow-400/40 min-h-[88px]">
                    <i class="fa-solid fa-hospital text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Lab Profile</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">لیبارٹری پروفائل سیٹنگز</div>
                    </div>
                </a>

                <?php if (is_admin_user()): ?>
                <!-- 5. Users -->
                <a href="/portals/settings/users.php" class="group flex items-center gap-3.5 p-4 rounded-2xl bg-[#60a5fa] text-slate-900 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border border-blue-400/40 min-h-[88px]">
                    <i class="fa-solid fa-users text-3xl text-slate-900 shrink-0 group-hover:scale-105 transition-transform"></i>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm sm:text-base text-slate-900 leading-tight">Users</div>
                        <div class="text-xs text-slate-800/80 font-medium mt-1" dir="rtl" style="font-family: 'Noto Nastaliq Urdu', 'Segoe UI', Tahoma, sans-serif;">اکاؤنٹ سیٹنگز اور صارفین</div>
                    </div>
                </a>
                <?php endif; ?>
            </div>
        </div>

    </div>



</div>

<?php
$content = ob_get_clean();

render_page('Dashboard', 'main-lab', 'dashboard', $content);
