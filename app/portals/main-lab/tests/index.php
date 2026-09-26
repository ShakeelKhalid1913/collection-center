<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [];
foreach (mock('mock_tests') as $t) {
    if ($t['category'] === 'Radiology') {
        continue;
    }
    $rows[] = [e($t['code']), e($t['name']), e($t['category']), e(format_money((float) $t['price'])), e($t['sample']), e($t['range'])];
}

$content = page_header('Tests', 'Catalog: name, code, category, price, sample type, ranges.', btn_primary('#', 'Add test'));
$content .= card(data_table(['Code', 'Name', 'Category', 'Price', 'Sample', 'Normal range'], $rows), 'overflow-hidden');

render_page('Tests', 'main-lab', 'tests', $content);
