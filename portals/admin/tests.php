<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$rows = [];
foreach (mock('mock_tests') as $t) {
    $rows[] = [e($t['code']), e($t['name']), e($t['category']), e(format_money((float) $t['price'])), e($t['sample'])];
}

$content = page_header('Tests Catalog', 'Global test definitions for all branches.');
$content .= card(data_table(['Code', 'Name', 'Category', 'Price', 'Sample type'], $rows), 'overflow-hidden');

render_page('Tests Catalog', 'admin', 'tests', $content);
