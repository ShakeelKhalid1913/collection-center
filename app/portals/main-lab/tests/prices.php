<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

require_permission('rates_edit');

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
        $ok = test_repo()->updateOrganizationPrice($orgId, $testId, $price);
        $message = $ok
            ? flash_success('Price updated to ' . format_money($price) . ' for this organization.')
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
    $customBadge = !empty($t['has_custom_price'])
        ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200 ml-1.5">Custom Org Rate</span>'
        : '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-500 ml-1.5">Master Default</span>';

    $rows[] = [
        '<span class="font-mono text-teal-800">' . e($code) . '</span>',
        '<div><strong>' . e($name) . '</strong>' . $customBadge . '</div>',
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

$headerActions = <<<HTML
<div class="flex items-center gap-2">
    <a href="/portals/tests/rate-list.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-2xl bg-white hover:bg-slate-50 text-slate-800 font-bold text-xs sm:text-sm border border-slate-300 shadow-2xs transition-all">
        <i class="fa-solid fa-file-pdf text-rose-600"></i> Print / PDF Rate List
    </a>
    <a href="/portals/tests/packages.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-2xl bg-indigo-50 hover:bg-indigo-100 text-indigo-800 font-bold text-xs sm:text-sm border border-indigo-200 transition-all">
        <i class="fa-solid fa-boxes-packing text-indigo-600"></i> Test Packages
    </a>
    <a href="/portals/tests/index.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs sm:text-sm shadow-md transition-all">
        <i class="fa-solid fa-flask text-[#c2f13c]"></i> + Add new test
    </a>
</div>
HTML;

$content = page_header('Test Prices & Tariff', '', $headerActions);
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
