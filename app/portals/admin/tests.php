<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = test_repo()->createTest([
        'code' => $_POST['code'] ?? '',
        'name' => $_POST['name'] ?? '',
        'category' => $_POST['category'] ?? 'General',
        'price' => (float)($_POST['price'] ?? 0),
        'sample' => $_POST['sample'] ?? 'Blood',
        'unit' => $_POST['unit'] ?? '—',
        'range' => $_POST['range'] ?? '—',
        'organization_id' => current_user()['organization_id'] ?? 'ORG-001',
    ]);
    $message = $res['success'] ? flash_success('Test added.') : flash_error($res['error'] ?? 'Failed.');
}

$rows = [];
foreach (mock('mock_tests') as $t) {
    $rows[] = [e($t['code']), e($t['name']), e($t['category']), e(format_money((float) $t['price'])), e($t['sample'])];
}

$content = page_header('Tests Catalog', 'Global test definitions for all branches.');
$content .= $message;
$content .= card(
    '<form method="post" class="grid gap-3 p-4 sm:grid-cols-3 sm:p-6 border-b border-slate-200">' .
    form_field('Code', 'code', 'text', null, 'CBC') .
    form_field('Name', 'name', 'text', null, 'Complete Blood Count') .
    form_field('Category', 'category', 'text', 'Hematology') .
    form_field('Price', 'price', 'number', '0') .
    form_field('Sample', 'sample', 'text', 'Blood') .
    '<div class="flex items-end">' . btn_submit('Add test') . '</div>' .
    '</form>' .
    data_table(['Code', 'Name', 'Category', 'Price', 'Sample type'], $rows),
    'overflow-hidden'
);

render_page('Tests Catalog', 'admin', 'tests', $content);
