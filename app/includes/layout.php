<?php

declare(strict_types=1);

require_once __DIR__ . '/head.php';

function portal_nav(string $portal, string $activeKey): string
{
    $base = '/portals/' . $portal;
    $badges = nav_badge_counts();

    $menus = [
        'collection-center' => [
            nav_group('Overview', [
                nav_item("{$base}/dashboard.php", 'Dashboard', $activeKey, 'dashboard', null, 'fa-solid fa-gauge-high'),
            ]),
            nav_group('Patients', [
                nav_item("{$base}/patients/quick-register.php", 'Quick Registration', $activeKey, 'quick-register', null, 'fa-solid fa-bolt'),
                nav_item("{$base}/patients/register.php", 'Register Patient', $activeKey, 'register', null, 'fa-solid fa-user-plus'),
                nav_item("{$base}/patients/records.php", 'Patient Records', $activeKey, 'records', null, 'fa-solid fa-address-book'),
            ]),
            nav_group('Lab Entries', [
                nav_item("{$base}/lab-entries/new.php", 'New Entry', $activeKey, 'new-entry', null, 'fa-solid fa-file-circle-plus'),
                nav_item("{$base}/lab-entries/pending.php", 'Pending', $activeKey, 'pending', $badges['cc_pending'] ?: null, 'fa-solid fa-clock'),
                nav_item("{$base}/lab-entries/history.php", 'History', $activeKey, 'history', null, 'fa-solid fa-clock-rotate-left'),
            ]),
            nav_group('More', [
                nav_item("{$base}/receipts.php", 'Receipts', $activeKey, 'receipts', null, 'fa-solid fa-receipt'),
                nav_item("{$base}/reports.php", 'Reports', $activeKey, 'reports', null, 'fa-solid fa-file-medical'),
                nav_item("{$base}/branding.php", 'Header / Footer', $activeKey, 'branding', null, 'fa-solid fa-heading'),
                nav_item("{$base}/settings.php", 'Settings', $activeKey, 'settings', null, 'fa-solid fa-gear'),
            ]),
        ],
        'main-lab' => [
            nav_group('', [
                nav_item("{$base}/dashboard.php", 'Dashboard', $activeKey, 'dashboard', null, 'fa-solid fa-gauge-high'),
            ]),
            nav_group('Primary', [
                nav_item("{$base}/patients/register.php", 'Register Patient', $activeKey, 'register', null, 'fa-solid fa-user-plus'),
                nav_item("{$base}/results/entry.php", 'Result Entry', $activeKey, 'results-entry', null, 'fa-solid fa-keyboard'),
                nav_item("{$base}/reports/history.php", 'Report History', $activeKey, 'reports-history', null, 'fa-solid fa-folder-open'),
            ]),
            nav_group('Operations', [
                nav_item("{$base}/waste-record.php", 'Waste Record', $activeKey, 'waste-record', null, 'fa-solid fa-trash-can'),
                nav_item("{$base}/delete-entry.php", 'Delete Entry', $activeKey, 'delete-entry', null, 'fa-solid fa-user-xmark'),
                nav_item("{$base}/doctors-share.php", "Doctor's Share", $activeKey, 'doctors-share', null, 'fa-solid fa-user-doctor'),
            ]),
            nav_group('Catalog', [
                nav_item("{$base}/tests/index.php", 'Add Test', $activeKey, 'tests', null, 'fa-solid fa-flask'),
                nav_item("{$base}/tests/prices.php", 'Add / Update Price', $activeKey, 'prices', null, 'fa-solid fa-tags'),
            ]),
            nav_group('Settings', [
                nav_item("{$base}/settings/report-template.php", 'Header & Footer', $activeKey, 'settings-report', null, 'fa-solid fa-heading'),
                nav_item("{$base}/archive.php", 'All Dates History', $activeKey, 'archive', null, 'fa-solid fa-clock-rotate-left'),
            ]),
            nav_group('More', [
                nav_item("{$base}/patients/records.php", 'Patient Records', $activeKey, 'records', null, 'fa-solid fa-address-book'),
                nav_item("{$base}/receipts.php", 'Patient Bill', $activeKey, 'receipts', null, 'fa-solid fa-receipt'),
                nav_item("{$base}/settings/profile.php", 'Lab Profile', $activeKey, 'settings-profile', null, 'fa-solid fa-hospital'),
                nav_item("{$base}/settings/users.php", 'Users', $activeKey, 'settings-users', null, 'fa-solid fa-users'),
            ]),
        ],
        'imaging' => [
            nav_group('Overview', [
                nav_item("{$base}/dashboard.php", 'Dashboard', $activeKey, 'dashboard', null, 'fa-solid fa-gauge-high'),
            ]),
            nav_group('Modalities', [
                nav_item("{$base}/new-scan.php?modality=xray", 'X-Ray', $activeKey, 'modality-xray', null, 'fa-solid fa-x-ray'),
                nav_item("{$base}/new-scan.php?modality=ct", 'CT Scan', $activeKey, 'modality-ct', null, 'fa-solid fa-laptop-medical'),
                nav_item("{$base}/new-scan.php?modality=us", 'Ultrasound', $activeKey, 'modality-us', null, 'fa-solid fa-wave-square'),
                nav_item("{$base}/new-scan.php?modality=ecg", 'ECG', $activeKey, 'modality-ecg', null, 'fa-solid fa-heart-pulse'),
            ]),
            nav_group('Workflow', [
                nav_item("{$base}/patients.php", 'Patients', $activeKey, 'patients', null, 'fa-solid fa-users'),
                nav_item("{$base}/new-scan.php", 'New Study', $activeKey, 'new-scan', null, 'fa-solid fa-file-circle-plus'),
                nav_item("{$base}/pending-scans.php", 'Pending Studies', $activeKey, 'pending-scans', $badges['imaging_pending'] ?: null, 'fa-solid fa-clock'),
                nav_item("{$base}/results.php", 'Findings / Results', $activeKey, 'results', null, 'fa-solid fa-notes-medical'),
            ]),
            nav_group('Reports', [
                nav_item("{$base}/reports.php", 'Reports', $activeKey, 'reports', null, 'fa-solid fa-file-medical'),
                nav_item("{$base}/report-history.php", 'Report History', $activeKey, 'report-history', null, 'fa-solid fa-folder-open'),
            ]),
            nav_group('', [nav_item("{$base}/settings.php", 'Settings', $activeKey, 'settings', null, 'fa-solid fa-gear')]),
        ],
        'admin' => [
            nav_group('Overview', [
                nav_item("{$base}/dashboard.php", 'Dashboard', $activeKey, 'dashboard', null, 'fa-solid fa-gauge-high'),
            ]),
            nav_group('Management', [
                nav_item("{$base}/labs.php", 'Labs & Branches', $activeKey, 'labs', null, 'fa-solid fa-sitemap'),
                nav_item("{$base}/users.php", 'Users & Permissions', $activeKey, 'users', null, 'fa-solid fa-user-shield'),
                nav_item("{$base}/tests.php", 'Tests Catalog', $activeKey, 'tests', null, 'fa-solid fa-flask'),
                nav_item("{$base}/packages.php", 'Test Packages', $activeKey, 'packages', null, 'fa-solid fa-boxes-stacked'),
                nav_item("{$base}/branding.php", 'Client Branding', $activeKey, 'branding', null, 'fa-solid fa-heading'),
            ]),
            nav_group('', [nav_item("{$base}/settings.php", 'System Settings', $activeKey, 'settings', null, 'fa-solid fa-sliders')]),
        ],
    ];

    return implode('', $menus[$portal] ?? []);
}

function render_layout(string $title, string $portal, string $activeKey, string $content, bool $hideAppCredit = false): void
{
    $user = current_user();
    $portalLabel = PORTALS[$portal]['label'] ?? ucfirst($portal);
    $nav = portal_nav($portal, $activeKey);
    $userName = e($user['name'] ?? 'User');
    $userRole = e($user['role'] ?? '');
    $pageTitle = e($title);
    $today = date('l, d M Y');
    $initials = e(user_initials($user['name'] ?? 'User'));
    $branchHint = e($user['branch_id'] ?? 'ORG-001');

    $logo = brand_logo('brand-logo brand-logo--sidebar');
    $wa = floating_whatsapp_button('+92 306 5193582'); // CLIENT lab WhatsApp only

    // Hide page credit on receipt/report preview — credit lives inside the print box only
    $appCredit = $hideAppCredit ? '' : (
        '<footer class="software-credit-bar software-credit-bar--app no-print">' .
        '<p class="software-credit">Software created by <strong>Shakeel Khalid</strong>' .
        ' · <a href="https://wa.me/923283070070" target="_blank" rel="noopener">WhatsApp 0328-3070070</a></p>' .
        '</footer>'
    );

    echo '<!DOCTYPE html><html lang="en" class="h-full"><head>';
    render_head($pageTitle . ' · ' . $portalLabel);
    echo '</head><body class="app-body h-full">';

    echo <<<HTML
<div id="app-shell" class="min-h-full">
    <div id="sidebar-backdrop" class="fixed inset-0 z-40 hidden bg-slate-900/40 backdrop-blur-sm lg:hidden" aria-hidden="true"></div>

    <aside id="sidebar" class="app-sidebar fixed inset-y-0 left-0 z-50 flex w-[var(--sidebar-width)] -translate-x-full flex-col transition-transform duration-200 lg:translate-x-0">
        <div class="app-brand">
            <div class="app-brand__row">
                <div class="app-brand__icon">{$logo}</div>
                <div>
                    <p class="app-brand__title">Health LMS Pro</p>
                    <p class="app-brand__portal">{$portalLabel}</p>
                </div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto py-2">{$nav}</nav>
        <div class="app-sidebar__user">
            <div class="app-sidebar__user-row">
                <div class="app-sidebar__avatar">{$initials}</div>
                <div>
                    <p class="app-sidebar__name">{$userName}</p>
                    <p class="app-sidebar__role">{$userRole}</p>
                </div>
            </div>
            <a href="/logout.php" class="app-sidebar__logout"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Sign out</a>
        </div>
    </aside>

    <div class="lg:pl-[var(--sidebar-width)]">
        <header class="app-topbar">
            <button type="button" id="sidebar-toggle" class="topbar-menu-btn lg:hidden" aria-label="Open menu">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
            <h1 class="app-topbar__title">{$pageTitle}</h1>
            <div class="app-topbar__meta">
                <span><i class="fa-regular fa-calendar" aria-hidden="true"></i> {$today}</span>
                <span class="chip chip--live"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {$branchHint}</span>
            </div>
        </header>

        <main class="app-main">
            {$content}
            {$appCredit}
        </main>
    </div>
</div>
HTML;
    echo $wa;
    echo '<script src="/assets/js/app.js"></script></body></html>';
}

function render_page(string $title, string $portal, string $activeKey, string $content, bool $hideAppCredit = false): void
{
    require_auth($portal);
    render_layout($title, $portal, $activeKey, $content, $hideAppCredit);
}
