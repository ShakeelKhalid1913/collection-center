<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgCount = 1;
$branchCount = 3;
$userCount = user_repo()->countActive();
$testCount = test_repo()->countTests() + test_repo()->countPackages();

$content = page_header('Admin Dashboard', 'Organizations, users, tests catalog, and client branding.');
$content .= '<div class="stat-grid stat-grid--4">';
$content .= stat_card('Organizations', (string)$orgCount, 'Active org', 'teal', 'fa-solid fa-hospital');
$content .= stat_card('Branches', (string)$branchCount, 'Main + CC + Imaging', 'blue', 'fa-solid fa-sitemap');
$content .= stat_card('Active Users', (string)$userCount, 'All portals', 'violet', 'fa-solid fa-users');
$content .= stat_card('Tests in catalog', (string)$testCount, 'Incl. packages', 'amber', 'fa-solid fa-flask');
$content .= '</div>';

$content .= '<div class="mt-6 grid gap-4 lg:grid-cols-2">';
$content .= card(
    panel_head('Quick actions') .
    '<div class="flex flex-wrap gap-2 p-4">' .
    btn_primary('/portals/admin/users.php', 'Users', 'fa-solid fa-user-shield') .
    btn_secondary('/portals/admin/tests.php', 'Add / manage tests', 'fa-solid fa-flask') .
    btn_secondary('/portals/admin/packages.php', 'Packages', 'fa-solid fa-boxes-stacked') .
    btn_secondary('/portals/admin/branding.php', 'Client branding', 'fa-solid fa-heading') .
    '</div>'
);
$content .= card(
    panel_head('Tips') .
    '<ul class="space-y-2 p-4 text-sm text-slate-700 list-disc pl-8">' .
    '<li>Add missing tests from <strong>Tests Catalog</strong> (code, name, price).</li>' .
    '<li>Set each lab’s header/footer under <strong>Client Branding</strong>.</li>' .
    '<li>Staff sign in with their own account — each account opens only its assigned portal.</li>' .
    '</ul>'
);
$content .= '</div>';

render_page('Dashboard', 'admin', 'dashboard', $content);
