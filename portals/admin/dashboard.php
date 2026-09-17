<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$content = page_header('Admin Dashboard', 'Organizations, branches, users, and catalog.');
$content .= '<div class="stat-grid stat-grid--4">';
$content .= stat_card('Organizations', '1', 'Multi-lab ready', 'teal', 'fa-solid fa-hospital');
$content .= stat_card('Branches', '4', 'Main + 2 CC + Imaging', 'blue', 'fa-solid fa-sitemap');
$content .= stat_card('Active Users', '24', 'All portals', 'violet', 'fa-solid fa-users');
$content .= stat_card('Tests in catalog', '128', 'Incl. packages', 'amber', 'fa-solid fa-flask');
$content .= '</div>';

$content .= '<div class="mt-6">' . card(
    '<div class="p-4 sm:p-6 text-sm text-slate-700">' .
    '<p class="font-semibold text-slate-900">MVP scope</p>' .
    '<p class="mt-2">Every record will carry <code class="rounded bg-slate-100 px-1">organization_id</code>, <code class="rounded bg-slate-100 px-1">branch_id</code>, <code class="rounded bg-slate-100 px-1">department_id</code>, and <code class="rounded bg-slate-100 px-1">created_by</code> when MySQL is connected.</p>' .
    '</div>'
) . '</div>';

render_page('Dashboard', 'admin', 'dashboard', $content);
