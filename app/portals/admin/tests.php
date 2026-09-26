<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'] ?? '';
    if (!isset(TEST_DEPARTMENTS[$category])) {
        $message = flash_error('Please select a valid department / category.');
    } else {
        $res = test_repo()->createTest([
            'code' => $_POST['code'] ?? '',
            'name' => $_POST['name'] ?? '',
            'category' => $category,
            'price' => (float)($_POST['price'] ?? 0),
            'sample' => $_POST['sample'] ?? 'Blood',
            'unit' => $_POST['unit'] ?? '—',
            'range' => $_POST['range'] ?? '—',
            'organization_id' => current_user()['organization_id'] ?? 'ORG-001',
        ]);
        $message = $res['success'] ? flash_success('Test added under ' . $category . '.') : flash_error($res['error'] ?? 'Failed.');
    }
}

$rows = [];
foreach (mock('mock_tests') as $t) {
    $rows[] = [e($t['code']), e($t['name']), e($t['category']), e(format_money((float) $t['price'])), e($t['sample'])];
}

$content = page_header(
    'Tests Catalog',
    'Add tests under departments: Hematology, Chemistry, Biochemistry, Special Chemistry, Histopathology.'
);
$content .= $message;
$content .= card(
    '<form method="post" class="grid gap-3 p-4 sm:grid-cols-3 sm:p-6 border-b border-slate-200">' .
    form_field('Code', 'code', 'text', null, 'e.g. UA') .
    form_field('Name', 'name', 'text', null, 'e.g. Uric Acid') .
    select_field('Department / Category', 'category', TEST_DEPARTMENTS, 'Biochemistry') .
    form_field('Price', 'price', 'number', '0') .
    form_field('Sample', 'sample', 'text', 'Blood') .
    form_field('Unit', 'unit', 'text', '—', '', true) .
    form_field('Normal range', 'range', 'text', '—', '', true) .
    '<div class="flex items-end">' . btn_submit('Add test') . '</div>' .
    '</form>' .
    data_table(['Code', 'Name', 'Department', 'Price', 'Sample type'], $rows),
    'overflow-hidden'
);

render_page('Tests Catalog', 'admin', 'tests', $content);
