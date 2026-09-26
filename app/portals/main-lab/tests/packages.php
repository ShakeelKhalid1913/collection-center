<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [];
foreach (mock('mock_packages') as $pkg) {
    $rows[] = [
        e($pkg['code']),
        e($pkg['name']),
        e($pkg['tests']),
        e(format_money((float) $pkg['price'])),
        e(format_money((float) $pkg['regular'])),
        e(format_money((float) $pkg['regular'] - (float) $pkg['price'])),
        link_action('Edit'),
    ];
}

$filters = filter_bar([
    form_field('Search', 'q', 'search', null, 'Package name or code…'),
    select_field('Status', 'status', ['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive']),
    select_field('Price', 'price', ['' => 'Any', 'low' => 'Under 3,000', 'mid' => '3,000–5,000', 'high' => 'Above 5,000']),
]);

$content = page_header('Test Packages', 'Bundled offerings used in Quick Registration and New Entry.', btn_primary('#', 'Add package'));
$content .= card(
    $filters .
    data_table(['Code', 'Package', 'Includes', 'Package price', 'Regular', 'Saving', 'Action'], $rows),
    'overflow-hidden'
);

render_page('Test Packages', 'main-lab', 'packages', $content);
