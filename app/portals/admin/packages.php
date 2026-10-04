<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = test_repo()->createPackage([
        'code' => $_POST['code'] ?? '',
        'name' => $_POST['name'] ?? '',
        'tests' => $_POST['tests'] ?? '',
        'price' => (float)($_POST['price'] ?? 0),
        'regular' => (float)($_POST['regular'] ?? $_POST['price'] ?? 0),
        'organization_id' => current_user()['organization_id'] ?? 'ORG-001',
    ]);
    $message = $res['success'] ? flash_success('Package added.') : flash_error($res['error'] ?? 'Failed.');
}

$rows = [];
foreach (mock('mock_packages') as $pkg) {
    $rows[] = [
        '<span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md text-xs">' . e($pkg['code']) . '</span>',
        '<span class="font-bold text-slate-800">' . e($pkg['name']) . '</span>',
        '<span class="text-xs text-slate-600 font-medium">' . e($pkg['tests']) . '</span>',
        '<span class="font-bold text-emerald-600">' . e(format_money((float)$pkg['price'])) . '</span>',
        '<span class="text-xs text-slate-400 line-through">' . e(format_money((float)$pkg['regular'])) . '</span>',
    ];
}

$content = page_header('Test Packages', 'Manage bundled offerings.');
$content .= $message;
$content .= card(
    '<form method="post" class="grid gap-3 p-4 sm:grid-cols-2 sm:p-6 border-b border-slate-200">' .
    form_field('Code', 'code', 'text', null, 'PKG-BASIC') .
    form_field('Name', 'name', 'text', null, 'Basic Health Package') .
    form_field('Tests included', 'tests', 'text', null, 'CBC, FBS, LFT') .
    form_field('Package price', 'price', 'number', '0') .
    form_field('Regular price', 'regular', 'number', '0') .
    '<div class="flex items-end">' . btn_submit('Add package') . '</div>' .
    '</form>' .
    data_table(['Code', 'Name', 'Tests', 'Price', 'Regular'], $rows),
    'overflow-hidden'
);

render_page('Packages', 'admin', 'packages', $content);
