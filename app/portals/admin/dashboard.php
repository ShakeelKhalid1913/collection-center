<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgCount = 1;
$branchCount = 3;
$userCount = user_repo()->countActive();
$testCount = test_repo()->countTests() + test_repo()->countPackages();

$content = page_header('Admin Dashboard', 'Organizations, branches, users, and catalog.');
$content .= '<div class="stat-grid stat-grid--4">';
$content .= stat_card('Organizations', (string)$orgCount, 'Multi-lab ready', 'teal', 'fa-solid fa-hospital');
$content .= stat_card('Branches', (string)$branchCount, 'Main + CC + Imaging', 'blue', 'fa-solid fa-sitemap');
$content .= stat_card('Active Users', (string)$userCount, 'All portals', 'violet', 'fa-solid fa-users');
$content .= stat_card('Tests in catalog', (string)$testCount, 'Incl. packages', 'amber', 'fa-solid fa-flask');
$content .= '</div>';

$content .= '<div class="mt-6">' . card(
    '<div class="p-4 sm:p-6 text-sm text-slate-700">' .
    '<p class="font-semibold text-slate-900">Access control</p>' .
    '<p class="mt-2">Each user is locked to one portal (<code class="rounded bg-slate-100 px-1">collection-center</code>, <code class="rounded bg-slate-100 px-1">main-lab</code>, <code class="rounded bg-slate-100 px-1">imaging</code>, or <code class="rounded bg-slate-100 px-1">admin</code>). Cross-portal URLs return 403.</p>' .
    '</div>'
) . '</div>';

render_page('Dashboard', 'admin', 'dashboard', $content);
