<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';
$q = trim((string)($_GET['q'] ?? ''));
$dept = trim((string)($_GET['dept'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_price') {
    require_permission('rates_edit');
    $testId = trim((string)($_POST['test_id'] ?? ''));
    $price = (float)($_POST['price'] ?? 0);
    if ($testId === '') {
        $message = flash_error('Test is required.');
    } else {
        $ok = test_repo()->updateTest($testId, ['price' => $price]);
        $message = $ok
            ? flash_success('Price updated to ' . format_money($price) . '.')
            : flash_error('Could not update price.');
    }
}

$tests = test_repo()->getTests($orgId);
$rows = [];
foreach ($tests as $t) {
    $name = (string)($t['name'] ?? '');
    $code = (string)($t['code'] ?? '');
    $category = (string)($t['category'] ?? '');
    if ($q !== '') {
        $hay = mb_strtolower($name . ' ' . $code . ' ' . $category);
        if (!str_contains($hay, mb_strtolower($q))) {
            continue;
        }
    }
    if ($dept !== '' && strcasecmp($category, $dept) !== 0) {
        continue;
    }

    $id = (string)$t['id'];
    $rows[] = [
        '<span class="font-mono text-teal-800">' . e($code) . '</span>',
        '<strong>' . e($name) . '</strong>',
        e($category !== '' ? $category : '—'),
        '<form method="post" class="flex flex-wrap items-center gap-2">' .
            '<input type="hidden" name="action" value="update_price">' .
            '<input type="hidden" name="test_id" value="' . e($id) . '">' .
            '<input type="number" name="price" class="field !w-28" min="0" step="1" value="' . e((string)(int)round((float)($t['price'] ?? 0))) . '">' .
            '<button type="submit" class="btn btn-primary text-xs"><i class="fa-solid fa-floppy-disk mr-1"></i> Save</button>' .
            '</form>',
    ];
}

$deptOpts = ['' => 'All departments'];
foreach (test_departments() as $k => $label) {
    $deptOpts[$k] = $label;
}

$content = page_header(
    'Add / Update Price',
    'Quickly edit laboratory test pricing. Use Add Test for new catalog items.',
    btn_primary('/portals/main-lab/tests/index.php', 'Add new test', 'fa-solid fa-flask')
);
$content .= $message;
$content .= card(
    '<form method="get" class="flex flex-wrap items-end gap-3 p-4">' .
    '<div class="min-w-[200px] flex-1"><label class="field-label" for="q">Search</label>' .
    '<input type="search" id="q" name="q" class="field" value="' . e($q) . '" placeholder="Code or test name"></div>' .
    select_field('Department', 'dept', $deptOpts, $dept, true) .
    '<button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>' .
    '</form>'
);

$content .= '<div class="mt-4">' . card(
    panel_head('Test prices (' . count($rows) . ')') .
    (count($rows) > 0
        ? data_table(['Code', 'Test', 'Department', 'Price (Rs.)'], $rows)
        : '<p class="p-4 text-sm text-slate-500">No tests match your filters.</p>'),
    'overflow-hidden'
) . '</div>';

render_page('Add / Update Price', 'main-lab', 'prices', $content);
