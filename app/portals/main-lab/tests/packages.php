<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

require_auth(['main_lab', 'imaging', 'admin']);
$orgId = current_organization_id();

$dbPackages = test_repo()->getPackages($orgId);
$pkgList = !empty($dbPackages) ? array_map(static fn($p) => [
    'code' => $p['code'],
    'name' => $p['name'],
    'tests' => $p['tests_included'] ?? $p['tests'] ?? '',
    'price' => (float)($p['price'] ?? 0),
    'regular' => (float)($p['regular_price'] ?? $p['regular'] ?? $p['price'] ?? 0),
], $dbPackages) : mock('mock_packages');

$rows = [];
foreach ($pkgList as $pkg) {
    $rows[] = [
        '<span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md text-xs">' . e($pkg['code']) . '</span>',
        '<span class="font-bold text-slate-800">' . e($pkg['name']) . '</span>',
        '<span class="text-xs text-slate-600 font-medium">' . e($pkg['tests']) . '</span>',
        '<span class="font-bold text-emerald-600">' . e(format_money((float)$pkg['price'])) . '</span>',
        '<span class="text-xs text-slate-400 line-through">' . e(format_money((float)$pkg['regular'])) . '</span>',
        '<span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">' . e(format_money((float)$pkg['regular'] - (float)$pkg['price'])) . '</span>',
        '<a href="/portals/lab-entries/new.php" class="btn btn-secondary text-xs px-2.5 py-1">Order</a>',
    ];
}

$filters = filter_bar([
    form_field('Search', 'q', 'search', null, 'Package name or code…'),
    select_field('Status', 'status', ['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive']),
    select_field('Price', 'price', ['' => 'Any', 'low' => 'Under 3,000', 'mid' => '3,000–5,000', 'high' => 'Above 5,000']),
]);

$headerBtn = is_super_admin() || current_portal() === 'admin'
    ? btn_primary('/portals/packages.php', 'Manage Packages')
    : btn_primary('/portals/lab-entries/new.php', 'Create Order');

$content = page_header('Test Packages', 'Bundled offerings used in Quick Registration and New Lab Orders.', $headerBtn);
$content .= card(
    $filters .
    data_table(['Code', 'Package', 'Includes', 'Package price', 'Regular', 'Saving', 'Action'], $rows),
    'overflow-hidden'
);

render_page('Test Packages', 'main-lab', 'packages', $content);
