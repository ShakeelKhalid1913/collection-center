<?php

declare(strict_types=1);

require_once __DIR__ . '/head.php';

function portal_nav(string $portal, string $activeKey): string
{
    $base = '/portals';
    $badges = nav_badge_counts();

    $menus = [
        'collection-center' => [
            nav_group('Overview', [
                nav_item("{$base}/dashboard.php", 'Dashboard', $activeKey, 'dashboard', null, 'fa-solid fa-gauge-high'),
            ]),
            nav_group('Patients', [
                nav_item("{$base}/patients/register.php", 'Register Patient', $activeKey, 'register', null, 'fa-solid fa-user-plus'),
                nav_item("{$base}/patients/records.php", 'Patient Records', $activeKey, 'records', null, 'fa-solid fa-address-book'),
                nav_item("{$base}/patients/card.php", 'Patient Card', $activeKey, 'patient-card', null, 'fa-solid fa-id-card'),
            ]),
            nav_group('Lab Entries', [
                nav_item("{$base}/lab-entries/new.php", 'Book Tests', $activeKey, 'new-entry', null, 'fa-solid fa-flask-vial'),
                nav_item("{$base}/lab-entries/pending.php", 'Pending', $activeKey, 'pending', $badges['cc_pending'] ?: null, 'fa-solid fa-clock'),
                nav_item("{$base}/sample-tracking.php", 'Sample Tracking & Barcode', $activeKey, 'sample-tracking', null, 'fa-solid fa-barcode'),
                nav_item("{$base}/lab-entries/history.php", 'History', $activeKey, 'history', null, 'fa-solid fa-clock-rotate-left'),
            ]),
            nav_group('Billing & Finance', [
                nav_item("{$base}/financial-closing.php", 'Daily Closing Slip', $activeKey, 'financial-closing', null, 'fa-solid fa-calculator'),
                nav_item("{$base}/summary-reports.php", 'Summary Reports Engine', $activeKey, 'summary-reports', null, 'fa-solid fa-chart-pie text-indigo-500'),
                nav_item("{$base}/patient-dues.php", 'Patient Dues', $activeKey, 'patient-dues', null, 'fa-solid fa-hand-holding-dollar'),
                nav_item("{$base}/expenses.php", 'Expenses', $activeKey, 'expenses', null, 'fa-solid fa-receipt'),
                nav_item("{$base}/receipts.php", 'Receipts', $activeKey, 'receipts', null, 'fa-solid fa-file-invoice'),
            ]),
            nav_group('More', [
                nav_item("{$base}/rate-list.php", 'Rate List (PDF)', $activeKey, 'rate-list', null, 'fa-solid fa-file-pdf'),
                nav_item("{$base}/tests/packages.php", 'Test Packages', $activeKey, 'packages', null, 'fa-solid fa-boxes-packing'),
                nav_item("{$base}/reports.php", 'Reports', $activeKey, 'reports', null, 'fa-solid fa-file-medical'),
                nav_item("{$base}/branding.php", 'Header / Footer', $activeKey, 'branding', null, 'fa-solid fa-heading'),
                nav_item("{$base}/settings.php", 'Settings', $activeKey, 'settings', null, 'fa-solid fa-gear'),
            ]),
        ],
        'main-lab' => [
            nav_group('', [
                nav_item("{$base}/dashboard.php", 'Dashboard', $activeKey, 'dashboard', null, 'fa-solid fa-gauge-high'),
            ]),
            nav_group('Lab Entries & Reports', [
                nav_item("{$base}/lab-entries/new.php", 'Book Tests', $activeKey, 'new-entry', null, 'fa-solid fa-flask-vial'),
                nav_item("{$base}/lab-entries/pending.php", 'Pending Orders', $activeKey, 'pending', $badges['cc_pending'] ?: null, 'fa-solid fa-clock'),
                nav_item("{$base}/lab-entries/edit.php", 'Edit Entry & Bill', $activeKey, 'edit-entry', null, 'fa-solid fa-user-pen'),
                nav_item("{$base}/sample-collection.php", 'Sample Tracking & Collection', $activeKey, 'sample-tracking', null, 'fa-solid fa-vial'),
                nav_item("{$base}/results/entry.php", 'Report Edit', $activeKey, 'results-entry', null, 'fa-solid fa-file-pen'),
                nav_item("{$base}/reports/history.php", 'Report History', $activeKey, 'reports-history', null, 'fa-solid fa-folder-open'),
                nav_item("{$base}/reports/radiology.php", 'Ultrasound & Radiology', $activeKey, 'radiology-reports', null, 'fa-solid fa-file-waveform'),
                nav_item("{$base}/tests/estimate.php", 'Test Estimate', $activeKey, 'test-estimate', null, 'fa-solid fa-calculator'),
            ]),
            nav_group('Patients', [
                nav_item("{$base}/patients/register.php", 'Register Patient', $activeKey, 'register', null, 'fa-solid fa-user-plus'),
                nav_item("{$base}/patients/records.php", 'Patient Records', $activeKey, 'records', null, 'fa-solid fa-address-book'),
                nav_item("{$base}/patients/card.php", 'Print Patient Card', $activeKey, 'patient-card', null, 'fa-solid fa-id-card'),
            ]),
            nav_group('Operations & Finance', [
                nav_item("{$base}/financial-closing.php", 'Daily Closing Slip', $activeKey, 'financial-closing', null, 'fa-solid fa-calculator'),
                nav_item("{$base}/summary-reports.php", 'Summary Reports Engine', $activeKey, 'summary-reports', null, 'fa-solid fa-chart-pie text-indigo-500'),
                nav_item("{$base}/collection-centers.php", 'Collection Centers', $activeKey, 'collection-centers', null, 'fa-solid fa-code-branch'),
                nav_item("{$base}/staff/index.php", 'Staff Directory', $activeKey, 'staff-docs', null, 'fa-solid fa-id-badge'),
                nav_item("{$base}/staff/attendance.php", 'Staff Attendance', $activeKey, 'staff-attendance', null, 'fa-solid fa-camera'),
                nav_item("{$base}/patient-dues.php", 'Patient Dues', $activeKey, 'patient-dues', null, 'fa-solid fa-hand-holding-dollar'),
                nav_item("{$base}/expenses.php", 'Expenses', $activeKey, 'expenses', null, 'fa-solid fa-file-invoice-dollar'),
                nav_item("{$base}/stock-management.php", 'Stock & Returns', $activeKey, 'stock', null, 'fa-solid fa-boxes-stacked'),
                nav_item("{$base}/doctors-share.php", "Doctor's Share", $activeKey, 'doctors-share', null, 'fa-solid fa-user-doctor'),
                nav_item("{$base}/sample-tracking.php", 'Sample Transit & Tracking', $activeKey, 'sample-tracking', null, 'fa-solid fa-barcode'),
                nav_item("{$base}/waste-record.php", 'Waste Record', $activeKey, 'waste-record', null, 'fa-solid fa-trash-can'),
                nav_item("{$base}/delete-entry.php", 'Delete Entry', $activeKey, 'delete-entry', null, 'fa-solid fa-user-xmark'),
            ]),
            nav_group('Catalog & Templates', [
                nav_item("{$base}/tests/index.php", 'Add Test', $activeKey, 'tests', null, 'fa-solid fa-flask'),
                nav_item("{$base}/tests/prices.php", 'Add / Update Price', $activeKey, 'prices', null, 'fa-solid fa-tags'),
                nav_item("{$base}/tests/rate-list.php", 'Rate List (Print/PDF)', $activeKey, 'rate-list', null, 'fa-solid fa-file-pdf'),
                nav_item("{$base}/tests/packages.php", 'Test Packages', $activeKey, 'packages', null, 'fa-solid fa-boxes-packing'),
                nav_item("{$base}/tests/b2b-rates.php", 'B2B Wholesale Rates', $activeKey, 'b2b-rates', null, 'fa-solid fa-handshake'),
                nav_item("{$base}/templates.php", 'Report Templates', $activeKey, 'templates', null, 'fa-solid fa-bookmark'),
            ]),
            nav_group('Settings', [
                nav_item("{$base}/settings/report-template.php", 'Header & Footer', $activeKey, 'settings-report', null, 'fa-solid fa-heading'),
                nav_item("{$base}/archive.php", 'All Dates History', $activeKey, 'archive', null, 'fa-solid fa-clock-rotate-left'),
            ]),
            nav_group('More', array_values(array_filter([
                nav_item("{$base}/receipts.php", 'Patient Bill', $activeKey, 'receipts', null, 'fa-solid fa-receipt'),
                nav_item("{$base}/settings/profile.php", 'Lab Profile', $activeKey, 'settings-profile', null, 'fa-solid fa-hospital'),
                is_admin_user() ? nav_item("{$base}/settings/users.php", 'Users', $activeKey, 'settings-users', null, 'fa-solid fa-users') : null,
            ]))),
        ],
        'imaging' => [
            nav_group('Overview', [
                nav_item("{$base}/dashboard.php", 'Dashboard', $activeKey, 'dashboard', null, 'fa-solid fa-gauge-high'),
            ]),
            nav_group('Laboratory Department', [
                nav_item("{$base}/laboratory.php", 'Laboratory Station', $activeKey, 'station-lab', null, 'fa-solid fa-flask text-emerald-500'),
                nav_item("{$base}/lab-entries/new.php", 'Book Tests', $activeKey, 'new-entry', null, 'fa-solid fa-flask-vial text-emerald-500'),
                nav_item("{$base}/lab-entries/pending.php", 'Pending Lab Orders', $activeKey, 'pending', $badges['cc_pending'] ?: null, 'fa-solid fa-clock text-amber-500'),
                nav_item("{$base}/sample-collection.php", 'Sample Collection', $activeKey, 'sample-collection', null, 'fa-solid fa-vial text-teal-500'),
                nav_item("{$base}/results/entry.php", 'Lab Results Entry', $activeKey, 'results-entry', null, 'fa-solid fa-vials text-indigo-500'),
                nav_item("{$base}/reports/generate.php", 'Generate Lab Report', $activeKey, 'reports-generate', null, 'fa-solid fa-file-pdf text-blue-500'),
                nav_item("{$base}/reports/history.php", 'Lab Reports History', $activeKey, 'reports-history', null, 'fa-solid fa-box-archive text-slate-500'),
            ]),
            nav_group('Radiology Department', [
                nav_item("{$base}/pending-scans.php", 'Radiology Workstation', $activeKey, 'pending-scans', null, 'fa-solid fa-x-ray text-indigo-500'),
                nav_item("{$base}/new-scan.php", 'Book Radiology Study', $activeKey, 'new-scan', null, 'fa-solid fa-calendar-plus text-blue-500'),
                nav_item("{$base}/results.php", 'Enter / Edit Findings', $activeKey, 'results', null, 'fa-solid fa-notes-medical text-teal-500'),
                nav_item("{$base}/reports.php", 'Radiology Reports & Print', $activeKey, 'reports', null, 'fa-solid fa-file-waveform text-emerald-500'),
                nav_item("{$base}/templates.php", 'Radiology Report Templates', $activeKey, 'templates', null, 'fa-solid fa-bookmark text-amber-500'),
                nav_item("{$base}/report-history.php", 'Radiology Archive & History', $activeKey, 'report-history', null, 'fa-solid fa-folder-open text-slate-500'),
            ]),
            nav_group('Reception & Billing', [
                nav_item("{$base}/receipt.php", 'Patient Bill / Slip', $activeKey, 'receipt', null, 'fa-solid fa-receipt'),
                nav_item("{$base}/patients.php", 'Patients Directory', $activeKey, 'patients', null, 'fa-solid fa-users'),
                nav_item("{$base}/patients/card.php", 'Patient MR Card', $activeKey, 'patient-card', null, 'fa-solid fa-id-card'),
                nav_item("{$base}/financial-closing.php", 'Daily / Monthly Closing', $activeKey, 'financial-closing', null, 'fa-solid fa-calculator'),
                nav_item("{$base}/summary-reports.php", 'Summary Reports Engine', $activeKey, 'summary-reports', null, 'fa-solid fa-chart-pie text-indigo-500'),
            ]),
            nav_group('Hospital & OPD', [
                nav_item("{$base}/opd-slip.php", 'Doctor OPD Slip', $activeKey, 'opd-slip', null, 'fa-solid fa-user-doctor'),
                nav_item("{$base}/hospital-files.php", 'Hospital Files & Charts', $activeKey, 'hospital-files', null, 'fa-solid fa-file-medical'),
            ]),
            nav_group('Operations & Finance', [
                nav_item("{$base}/expenses.php", 'Expenses', $activeKey, 'expenses', null, 'fa-solid fa-file-invoice-dollar'),
                nav_item("{$base}/doctors-share.php", "Doctor's Share", $activeKey, 'doctors-share', null, 'fa-solid fa-user-doctor'),
                nav_item("{$base}/stock-management.php", 'Stock & Supplies', $activeKey, 'stock', null, 'fa-solid fa-boxes-stacked'),
                nav_item("{$base}/waste-record.php", 'Waste Record', $activeKey, 'waste-record', null, 'fa-solid fa-trash-can'),
                nav_item("{$base}/delete-entry.php", 'Delete History / Entry', $activeKey, 'delete-entry', null, 'fa-solid fa-user-xmark'),
            ]),
            nav_group('Staff & Settings', [
                nav_item("{$base}/staff/index.php", 'Staff Directory', $activeKey, 'staff-docs', null, 'fa-solid fa-id-badge'),
                nav_item("{$base}/staff/attendance.php", 'Staff Attendance', $activeKey, 'staff-attendance', null, 'fa-solid fa-camera'),
                nav_item("{$base}/settings/report-template.php", 'Header & Footer Branding', $activeKey, 'settings-report', null, 'fa-solid fa-heading'),
                nav_item("{$base}/settings.php", 'Department Settings', $activeKey, 'settings', null, 'fa-solid fa-gear'),
            ]),
        ],
        'admin' => is_super_admin() ? [
            nav_group('Platform Master', [
                nav_item("{$base}/dashboard.php", 'Platform Console', $activeKey, 'dashboard', null, 'fa-solid fa-crown text-[#c2f13c]'),
            ]),
            nav_group('SaaS Client Labs', [
                nav_item("{$base}/organizations.php", 'Client Laboratories', $activeKey, 'organizations', null, 'fa-solid fa-building-columns'),
                nav_item("{$base}/labs.php", 'All Network Branches', $activeKey, 'labs', null, 'fa-solid fa-sitemap'),
                nav_item("{$base}/users.php", 'Global Staff & Admins', $activeKey, 'users', null, 'fa-solid fa-users'),
                nav_item("{$base}/locked-records.php", 'Locked Bills & Reports', $activeKey, 'locked-records', null, 'fa-solid fa-lock text-rose-500'),
            ]),
            nav_group('Platform Security', [
                nav_item("{$base}/audit-trail.php", 'Global Audit Trail', $activeKey, 'audit-trail', null, 'fa-solid fa-clock-rotate-left'),
                nav_item("/database/setup.php", 'Database & System Health', $activeKey, 'db-setup', null, 'fa-solid fa-database'),
                nav_item("{$base}/settings.php", 'Platform Settings', $activeKey, 'settings', null, 'fa-solid fa-sliders'),
            ]),
        ] : [
            nav_group('Overview', [
                nav_item("{$base}/dashboard.php", 'Lab Dashboard', $activeKey, 'dashboard', null, 'fa-solid fa-gauge-high'),
            ]),
            nav_group('Staff & Operations', [
                nav_item("{$base}/users.php", 'Staff & Permissions (RBAC)', $activeKey, 'users', null, 'fa-solid fa-user-shield'),
                nav_item("{$base}/locked-records.php", 'Locked Bills & Reports', $activeKey, 'locked-records', null, 'fa-solid fa-lock text-rose-500'),
                nav_item("{$base}/labs.php", 'Labs & Collection Centers', $activeKey, 'labs', null, 'fa-solid fa-sitemap'),
                nav_item("{$base}/audit-trail.php", 'Lab Security Logs', $activeKey, 'audit-trail', null, 'fa-solid fa-clock-rotate-left'),
            ]),
            nav_group('Financials & Branding', [
                nav_item("{$base}/summary-reports.php", 'Revenue & Shift Reports', $activeKey, 'summary-reports', null, 'fa-solid fa-chart-pie'),
                nav_item("{$base}/financial-closing.php", 'Daily Financial Closing', $activeKey, 'financial-closing', null, 'fa-solid fa-calculator'),
                nav_item("{$base}/branding.php", 'Lab Branding & Letterhead', $activeKey, 'branding', null, 'fa-solid fa-heading'),
                nav_item("{$base}/settings.php", 'Lab Settings', $activeKey, 'settings', null, 'fa-solid fa-sliders'),
            ]),
        ],
    ];

    return implode('', $menus[$portal] ?? []);
}

function render_layout(string $title, string $portal, string $activeKey, string $content, bool $hideAppCredit = false): void
{
    $user = current_user();
    $userPortal = current_portal();

    // Preserve the user's active portal sidebar so clicking shared features NEVER flips or resets their menu!
    $navPortal = (!empty($userPortal) && isset(PORTALS[$userPortal])) ? $userPortal : $portal;
    $portalLabel = PORTALS[$navPortal]['label'] ?? ucfirst($navPortal);
    $nav = portal_nav($navPortal, $activeKey);
    $userName = e($user['name'] ?? 'User');
    $userRole = e($user['role'] ?? '');
    $pageTitle = e($title);
    $today = date('l, d M Y');
    $initials = e(user_initials($user['name'] ?? 'User'));
    $branchHint = e($user['branch_id'] ?? 'ORG-001');

    $isDashboard = ($activeKey === 'dashboard');
    $backBtnHtml = '';
    if (!$isDashboard) {
        $backBtnHtml = <<<HTML
        <a href="/portals/dashboard.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 font-medium text-xs transition-colors shadow-2xs group shrink-0" title="Back to Dashboard">
            <i class="fa-solid fa-arrow-left text-xs group-hover:-translate-x-0.5 transition-transform text-slate-500"></i>
            <span class="hidden sm:inline">Dashboard</span>
        </a>
        HTML;
    }

    $logo = brand_logo('brand-logo brand-logo--sidebar');
    $wa = floating_whatsapp_button('+92 306 5193582'); // CLIENT lab WhatsApp only

    // Hide page credit on receipt/report preview â€” credit lives inside the print box only
    $appCredit = $hideAppCredit ? '' : (
        '<footer class="software-credit-bar software-credit-bar--app no-print">' .
        '<p class="software-credit" style="white-space:nowrap!important;text-align:center!important;display:block!important;width:100%!important;">Software created by <strong>Shakeel Khalid</strong>' .
        ' &middot; <a href="https://wa.me/923283070070" target="_blank" rel="noopener">WhatsApp 0328-3070070</a></p>' .
        '</footer>'
    );

    echo '<!DOCTYPE html><html lang="en" class="h-full"><head>';
    render_head($pageTitle . ' - ' . $portalLabel);
    echo '</head><body class="app-body h-full">';

    echo <<<HTML
<div id="app-shell" class="min-h-full sidebar-collapsed">
    <div id="sidebar-backdrop" class="fixed inset-0 z-40 hidden bg-slate-950/60 backdrop-blur-sm lg:hidden" aria-hidden="true"></div>

    <aside id="sidebar" class="app-sidebar no-print fixed inset-y-0 left-0 z-50 flex w-[var(--sidebar-width)] -translate-x-full lg:translate-x-0 flex-col transition-all duration-200 shadow-2xl">
        <div class="app-brand flex items-center justify-between pr-3">
            <div class="app-brand__row">
                <div class="app-brand__icon">{$logo}</div>
                <div class="app-brand__text">
                    <p class="app-brand__title">Lab Dash Pro</p>
                    <p class="app-brand__portal">{$portalLabel}</p>
                </div>
            </div>
            <!-- Collapse button when expanded (desktop) -->
            <button type="button" id="sidebar-collapse-btn" class="sidebar-toggle-btn p-1.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors hidden lg:flex items-center justify-center" title="Collapse sidebar to icon rail">
                <i class="fa-solid fa-angles-left text-sm"></i>
            </button>
            <!-- Close button (mobile) -->
            <button type="button" id="sidebar-close-btn" class="p-1.5 text-slate-400 hover:text-white rounded-lg transition-colors lg:hidden" title="Close sidebar">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <!-- Prominent Expand Button shown in collapsed rail mode (desktop) -->
        <div class="sidebar-rail-expand px-2 py-2 border-b border-slate-800/80">
            <button type="button" class="sidebar-toggle-btn w-full py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-[#c2f13c] hover:text-white flex items-center justify-center text-xs font-bold transition-all shadow-sm" title="Expand Sidebar (Show full menu)">
                <i class="fa-solid fa-angles-right text-sm"></i>
            </button>
        </div>

        <nav class="sidebar-nav flex-1 overflow-y-auto py-2">{$nav}</nav>
        <div class="app-sidebar__user">
            <div class="app-sidebar__user-row">
                <div class="app-sidebar__avatar">{$initials}</div>
                <div class="app-sidebar__user-details min-w-0 flex-1">
                    <p class="app-sidebar__name truncate">{$userName}</p>
                    <p class="app-sidebar__role truncate">{$userRole}</p>
                </div>
            </div>
            <a href="/logout.php" class="app-sidebar__logout" title="Sign out"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> <span class="app-sidebar__logout-text">Sign out</span></a>
        </div>
    </aside>

    <div id="main-content-wrapper" class="main-content-wrapper min-w-0 flex-1">
        <header class="app-topbar no-print flex items-center justify-between px-4 sm:px-6 py-3 border-b border-slate-200/80 bg-white/85 backdrop-blur-md sticky top-0 z-30">
            <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                {$backBtnHtml}
                <div class="min-w-0">
                    <h1 class="text-base sm:text-lg font-bold text-slate-800 tracking-tight truncate">{$pageTitle}</h1>
                </div>
            </div>
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <button type="button" onclick="openFeaturePalette()" class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 hover:border-slate-300 text-slate-500 hover:text-slate-800 text-xs transition-colors shadow-2xs cursor-pointer" title="Quick Search all features (Ctrl+K or /)">
                    <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
                    <span class="font-medium text-slate-600 hidden md:inline">Search features...</span>
                    <kbd class="font-mono text-[10px] bg-white border border-slate-300 rounded px-1.5 py-0.5 text-slate-500 font-semibold shadow-2xs">Ctrl K</kbd>
                </button>
                <div class="hidden md:flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-xs font-medium text-slate-600">
                    <i class="fa-regular fa-calendar text-slate-400"></i>
                    <span>{$today}</span>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-xs font-semibold text-slate-700">
                    <i class="fa-solid fa-location-dot text-slate-500"></i> {$branchHint}
                </span>
                <div class="flex items-center gap-2 pl-2 border-l border-slate-200">
                    <div class="w-8 h-8 rounded-full bg-slate-900 text-[#c2f13c] font-bold text-xs flex items-center justify-center shadow-sm border border-slate-700">
                        {$initials}
                    </div>
                </div>
            </div>
        </header>

        <main class="app-main min-h-[calc(100vh-4rem)] p-4 sm:p-6 lg:p-8 bg-[#f4f6fb]">
            {$content}
            {$appCredit}
        </main>
    </div>
</div>

<!-- Global Feature Command Palette Modal (Ctrl+K or /) -->
<div id="feature-palette-modal" class="fixed inset-0 z-50 hidden bg-slate-950/60 backdrop-blur-sm p-4 overflow-y-auto" onclick="if(event.target===this)closeFeaturePalette()">
    <div class="max-w-xl mx-auto mt-12 sm:mt-20 bg-white rounded-3xl shadow-2xl border border-slate-200/90 overflow-hidden transform transition-all animate-in fade-in zoom-in-95 duration-150">
        <!-- Search Bar Header -->
        <div class="p-4 border-b border-slate-200/80 flex items-center gap-3 bg-slate-50/70">
            <span class="w-8 h-8 rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center text-sm font-bold shrink-0">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" id="feature-search-input" class="w-full bg-transparent text-sm sm:text-base font-semibold text-slate-900 placeholder:text-slate-400 outline-none" placeholder="Search any feature, test, slip, or page... (or press /)" autocomplete="off">
            <kbd class="hidden sm:inline-block font-mono text-[11px] bg-white border border-slate-200 text-slate-500 px-2 py-0.5 rounded shadow-2xs font-semibold">ESC</kbd>
            <button type="button" onclick="closeFeaturePalette()" class="sm:hidden text-slate-400 hover:text-slate-600 p-1 text-base">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Quick Suggestions / Results -->
        <div id="feature-results-container" class="max-h-[60vh] overflow-y-auto divide-y divide-slate-100 p-2">
            <!-- Populated dynamically by JavaScript -->
        </div>

        <!-- Modal Footer Help -->
        <div class="px-4 py-2.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
            <div class="flex items-center gap-3">
                <span><kbd class="font-mono bg-white border border-slate-200 px-1.5 py-0.5 rounded text-[10px]">â†‘</kbd> <kbd class="font-mono bg-white border border-slate-200 px-1.5 py-0.5 rounded text-[10px]">â†“</kbd> navigate</span>
                <span><kbd class="font-mono bg-white border border-slate-200 px-1.5 py-0.5 rounded text-[10px]">Enter</kbd> open</span>
                <span><kbd class="font-mono bg-white border border-slate-200 px-1.5 py-0.5 rounded text-[10px]">/</kbd> shortcut</span>
            </div>
            <span class="font-semibold text-teal-700">Lab Dash Pro</span>
        </div>
    </div>
</div>

<script>
(function() {
    const features = [
        // Primary & Overview
        { title: "Dashboard", category: "Overview", icon: "fa-gauge-high", url: "/portals/dashboard.php", keywords: "home overview statistics revenue biomarkers" },
        { title: "Quick Patient Registration", category: "Patients", icon: "fa-bolt", url: "/portals/patients/quick-register.php", keywords: "fast patient intake rapid entry counter" },
        { title: "Register Patient", category: "Patients", icon: "fa-user-plus", url: "/portals/patients/register.php", keywords: "patient cnic phone add new mr number" },
        { title: "Patient Records Directory", category: "Patients", icon: "fa-address-book", url: "/portals/patients/records.php", keywords: "all patients history search phone" },
        { title: "Print Patient Card", category: "Patients", icon: "fa-id-card", url: "/portals/patients/card.php", keywords: "mr card barcode plastic badge" },

        // Clinical Lab Entries & Results
        { title: "Report Edit (Findings & Results)", category: "Laboratory", icon: "fa-file-pen", url: "/portals/results/entry.php", keywords: "report edit results enter values verify cbc lft ultrasound xray ecg findings" },
        { title: "Edit Entry & Bill Slip", category: "Laboratory", icon: "fa-user-pen", url: "/portals/lab-entries/edit.php", keywords: "edit age slip bill patient details name change demographics doctor" },
        { title: "Pending Lab Orders", category: "Laboratory", icon: "fa-clock", url: "/portals/lab-entries/pending.php", keywords: "pending orders queue lab tests" },
        { title: "Report History & Archive", category: "Laboratory", icon: "fa-folder-open", url: "/portals/reports/history.php", keywords: "previous reports print pdf completed" },
        { title: "Book Tests / Lab Entry", category: "Laboratory", icon: "fa-flask-vial", url: "/portals/lab-entries/new.php", keywords: "book test billing walkin entry" },
        { title: "Sample Collection & Tracking", category: "Logistics", icon: "fa-vial", url: "/portals/sample-collection.php", keywords: "courier dispatch receive vials tubes transit status" },
        { title: "Test Estimate & Cost Calculator", category: "Laboratory", icon: "fa-calculator", url: "/portals/tests/estimate.php", keywords: "estimate quotation price calculator test rates estimate slip inquiry discount" },
        { title: "Delete / Cancel Lab Entry", category: "Laboratory", icon: "fa-user-xmark", url: "/portals/delete-entry.php", keywords: "void remove mistake refund cancel" },

        // Finance & Day End Closing
        { title: "Summary Reports Engine (All Reports)", category: "Analytics & Finance", icon: "fa-chart-pie", url: "/portals/summary-reports.php", keywords: "summary reports gross business registration user cashier non-business dues test volume doctor referral daily rate list expenses pnl" },
        { title: "Daily / Weekly / Monthly Closing Slip", category: "Finance", icon: "fa-calculator", url: "/portals/financial-closing.php", keywords: "shift day end summary closing cash reconciliation balance modality daily weekly monthly" },
        { title: "Patient Dues & Recovery", category: "Finance", icon: "fa-hand-holding-dollar", url: "/portals/patient-dues.php", keywords: "pending payment balance collect money dues" },
        { title: "Expenses & Outflow", category: "Finance", icon: "fa-file-invoice-dollar", url: "/portals/expenses.php", keywords: "bills rent electricity tea reagents expense cash" },
        { title: "Doctor's Share & Commission", category: "Finance", icon: "fa-user-doctor", url: "/portals/doctors-share.php", keywords: "referral cut payout doctors commission percentage" },
        { title: "Patient Bill & Receipts", category: "Finance", icon: "fa-receipt", url: "/portals/receipt.php", keywords: "print receipt invoice bill payment" },

        // Modalities (Diagnostic Center + Hospital)
        { title: "Echocardiography (Echo) Station", category: "Modalities", icon: "fa-heart-pulse", url: "/portals/echo.php", keywords: "echocardiography echo doppler cardiac ejection fraction" },
        { title: "Doctor OPD Consultation Slip", category: "Hospital", icon: "fa-user-doctor", url: "/portals/opd-slip.php", keywords: "opd slip doctor consultation prescription token clinic" },
        { title: "Clinical Hospital Files & Charts", category: "Hospital", icon: "fa-file-medical", url: "/portals/hospital-files.php", keywords: "blood performa transfusion medicine chart mar vitals viral" },
        { title: "Ultrasound (US) Department", category: "Modalities", icon: "fa-wave-square", url: "/portals/ultrasound.php", keywords: "ultrasound pelvis abdomen scan study" },
        { title: "X-Ray / Radiography", category: "Modalities", icon: "fa-x-ray", url: "/portals/xray.php", keywords: "chest xray bones digital radiography" },
        { title: "ECG Department", category: "Modalities", icon: "fa-heart-pulse", url: "/portals/ecg.php", keywords: "heart ecg tracing rhythm" },
        { title: "CT Scan Department", category: "Modalities", icon: "fa-laptop-medical", url: "/portals/ct.php", keywords: "computed tomography scan brain chest" },
        { title: "MRI Scan Department", category: "Modalities", icon: "fa-magnet", url: "/portals/mri.php", keywords: "mri magnetic resonance scan spine brain" },

        // Staff & Documents
        { title: "Staff Management & Directory", category: "Staff & Docs", icon: "fa-id-badge", url: "/portals/staff/index.php", keywords: "employees workers doctors upload photo degree edit staff" },
        { title: "Worker Attendance Tracking", category: "Staff & Docs", icon: "fa-camera", url: "/portals/staff/attendance.php", keywords: "attendance present absent late checkin daily" },

        // Catalog & B2B Wholesale
        { title: "Add New Test to Catalog", category: "Catalog", icon: "fa-flask", url: "/portals/tests/index.php", keywords: "catalog new parameter reference range test" },
        { title: "Update Retail Test Prices", category: "Catalog", icon: "fa-tags", url: "/portals/tests/prices.php", keywords: "rates price change walkin fees" },
        { title: "B2B Wholesale Rates & Partner Labs", category: "B2B", icon: "fa-handshake", url: "/portals/tests/b2b-rates.php", keywords: "lab to lab wholesale contract referring partners discount" },
        { title: "Report Templates & Findings", category: "Catalog", icon: "fa-bookmark", url: "/portals/templates.php", keywords: "ultrasound templates radiology pathology formats predefined" },

        // Multi-Center & Multi-Modality
        { title: "Child Collection Centers / Labs", category: "Multi-Tenant", icon: "fa-code-branch", url: "/portals/collection-centers.php", keywords: "child branches points network data sync" },
        { title: "Stock Management & Supplies", category: "Inventory", icon: "fa-boxes-stacked", url: "/portals/stock-management.php", keywords: "reagents inventory supplies stock expired return" },
        { title: "Waste Record Disposal", category: "Operations", icon: "fa-trash-can", url: "/portals/waste-record.php", keywords: "biohazard waste disposal sharp yellow bag" },
        { title: "All Dates History Archive", category: "Archive", icon: "fa-clock-rotate-left", url: "/portals/archive.php", keywords: "search past entries all time archive" },

        // Settings & Branding
        { title: "Header & Footer Letterhead Settings", category: "Settings", icon: "fa-heading", url: "/portals/settings/report-template.php", keywords: "header footer logo letterhead font typography preview" },
        { title: "Lab Profile & Contact", category: "Settings", icon: "fa-hospital", url: "/portals/settings/profile.php", keywords: "lab name phone address profile" },
        { title: "System Users & Logins", category: "Settings", icon: "fa-users", url: "/portals/settings/users.php", keywords: "accounts passwords staff users permissions" }
    ];

    let activeIdx = 0;
    let filtered = [];

    window.openFeaturePalette = function() {
        const modal = document.getElementById('feature-palette-modal');
        const input = document.getElementById('feature-search-input');
        if (!modal || !input) return;
        modal.classList.remove('hidden');
        input.value = '';
        renderResults('');
        setTimeout(() => input.focus(), 50);
    };

    window.closeFeaturePalette = function() {
        const modal = document.getElementById('feature-palette-modal');
        if (modal) modal.classList.add('hidden');
    };

    const activePortal = "{$portal}";

    function renderResults(q) {
        const container = document.getElementById('feature-results-container');
        if (!container) return;
        const query = (q || '').trim().toLowerCase();

        filtered = features.filter(f => {
            if (activePortal === 'collection-center') {
                if (f.category === 'Modalities' || f.category === 'Hospital' || f.category === 'Staff & Docs') return false;
            }
            if (!query) return true;
            return f.title.toLowerCase().includes(query) ||
                   f.category.toLowerCase().includes(query) ||
                   f.keywords.toLowerCase().includes(query);
        });

        if (filtered.length === 0) {
            container.innerHTML = '<div class="py-12 text-center text-slate-400 text-xs"><i class="fa-solid fa-magnifying-glass text-3xl mb-2 text-slate-300"></i><p>No matching features found for "<strong>' + encodeURIComponent(query) + '</strong>".</p></div>';
            return;
        }

        activeIdx = 0;
        let html = '';
        filtered.forEach((f, idx) => {
            const isSel = idx === 0 ? 'bg-teal-50/80 border-l-4 border-teal-600' : 'hover:bg-slate-50 border-l-4 border-transparent';
            html += '<a href="' + f.url + '" class="palette-item flex items-center justify-between p-3 rounded-xl transition-all cursor-pointer ' + isSel + '" data-idx="' + idx + '">' +
                '<div class="flex items-center gap-3 min-w-0">' +
                    '<span class="w-8 h-8 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center text-sm shrink-0">' +
                        '<i class="fa-solid ' + f.icon + '"></i>' +
                    '</span>' +
                    '<div class="min-w-0">' +
                        '<div class="text-sm font-bold text-slate-900 truncate">' + f.title + '</div>' +
                        '<div class="text-[11px] text-slate-400 truncate">' + f.keywords + '</div>' +
                    '</div>' +
                '</div>' +
                '<span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 shrink-0 ml-2">' + f.category + '</span>' +
            '</a>';
        });
        container.innerHTML = html;
    }

    function updateSelection() {
        const items = document.querySelectorAll('.palette-item');
        items.forEach((el, idx) => {
            if (idx === activeIdx) {
                el.classList.add('bg-teal-50/80', 'border-teal-600');
                el.classList.remove('hover:bg-slate-50', 'border-transparent');
                el.scrollIntoView({ block: 'nearest' });
            } else {
                el.classList.remove('bg-teal-50/80', 'border-teal-600');
                el.classList.add('hover:bg-slate-50', 'border-transparent');
            }
        });
    }

    // Keyboard shortcuts: Ctrl+K or /
    document.addEventListener('keydown', function(e) {
        const modal = document.getElementById('feature-palette-modal');
        const isOpen = modal && !modal.classList.contains('hidden');

        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            if (isOpen) closeFeaturePalette();
            else openFeaturePalette();
            return;
        }

        if (e.key === '/' && !isOpen) {
            const tag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
            if (tag !== 'input' && tag !== 'textarea' && tag !== 'select') {
                e.preventDefault();
                openFeaturePalette();
                return;
            }
        }

        if (isOpen) {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeFeaturePalette();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (activeIdx < filtered.length - 1) {
                    activeIdx++;
                    updateSelection();
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (activeIdx > 0) {
                    activeIdx--;
                    updateSelection();
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (filtered[activeIdx]) {
                    window.location.href = filtered[activeIdx].url;
                }
            }
        }
    });

    const searchInput = document.getElementById('feature-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            renderResults(e.target.value);
        });
    }
})();
</script>
HTML;
    echo $wa;
    echo '<script src="/assets/js/app.js"></script></body></html>';
}

function render_page(string $title, string $portal, string $activeKey, string $content, bool $hideAppCredit = false): void
{
    require_auth($portal);
    render_layout($title, $portal, $activeKey, $content, $hideAppCredit);
}
