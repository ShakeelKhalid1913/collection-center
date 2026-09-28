<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category'] ?? '');
    // Department is optional — empty is allowed
    if ($category !== '' && !isset(TEST_DEPARTMENTS[$category])) {
        $message = flash_error('Please select a valid department, or leave it blank.');
    } else {
        $res = test_repo()->createTest([
            'code' => $_POST['code'] ?? '',
            'name' => $_POST['name'] ?? '',
            'category' => $category !== '' ? $category : 'General',
            'price' => (float)($_POST['price'] ?? 0),
            'sample' => $_POST['sample'] ?? 'Blood',
            'unit' => $_POST['unit'] ?? '—',
            'range' => $_POST['range'] ?? '—',
            'organization_id' => current_user()['organization_id'] ?? 'ORG-001',
        ]);
        $message = $res['success']
            ? flash_success('Test added' . ($category !== '' ? ' under ' . $category : '') . '.')
            : flash_error($res['error'] ?? 'Failed.');
    }
}

$deptOpts = ['' => '— Optional: select department —'] + TEST_DEPARTMENTS;

$q = trim((string)($_GET['q'] ?? ''));
$deptFilter = trim((string)($_GET['dept'] ?? ''));

$allTests = array_values(array_filter(
    mock('mock_tests'),
    static function (array $t) use ($q, $deptFilter): bool {
        if ($deptFilter !== '' && strcasecmp((string)($t['category'] ?? ''), $deptFilter) !== 0) {
            return false;
        }
        if ($q !== '') {
            $hay = strtolower(($t['code'] ?? '') . ' ' . ($t['name'] ?? ''));
            if (!str_contains($hay, strtolower($q))) {
                return false;
            }
        }
        return true;
    }
));

$perPage = 50;
$totalTests = count($allTests);
$totalPages = max(1, (int)ceil($totalTests / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;
$pageTests = array_slice($allTests, $offset, $perPage);

$rows = [];
foreach ($pageTests as $t) {
    $rows[] = [e($t['code']), e($t['name']), e($t['category']), e(format_money((float) $t['price'])), e($t['sample'])];
}

$from = $totalTests === 0 ? 0 : $offset + 1;
$to = min($offset + $perPage, $totalTests);

$queryBase = array_filter([
    'q' => $q !== '' ? $q : null,
    'dept' => $deptFilter !== '' ? $deptFilter : null,
], static fn($v) => $v !== null && $v !== '');

$buildUrl = static function (int $p) use ($queryBase): string {
    $qs = http_build_query(array_merge($queryBase, ['page' => $p]));
    return '/portals/admin/tests.php' . ($qs !== '' ? '?' . $qs : '');
};

$filterDeptOpts = ['' => 'All departments'] + TEST_DEPARTMENTS;
$filterHtml = '<form method="get" class="grid gap-3 p-4 sm:grid-cols-3 border-b border-slate-200 bg-slate-50/80">'
    . '<div class="sm:col-span-2"><label class="field-label" for="q">Search</label>'
    . '<input type="search" id="q" name="q" class="field" value="' . e($q) . '" placeholder="Code or test name">'
    . '</div>'
    . select_field('Department', 'dept', $filterDeptOpts, $deptFilter, true)
    . '<div class="sm:col-span-3 flex flex-wrap gap-2">'
    . '<button type="submit" class="btn btn-primary text-sm"><i class="fa-solid fa-magnifying-glass mr-1"></i> Filter</button>'
    . '<a href="/portals/admin/tests.php" class="btn btn-secondary text-sm">Clear</a>'
    . '</div></form>';

$paginationHtml = '';
if ($totalPages > 1) {
    $links = [];
    if ($page > 1) {
        $links[] = '<a class="btn btn-secondary text-xs" href="' . e($buildUrl($page - 1)) . '">&larr; Prev</a>';
    }
    for ($p = 1; $p <= $totalPages; $p++) {
        if ($p === $page) {
            $links[] = '<span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded bg-teal-700 text-white">' . $p . '</span>';
        } else {
            $links[] = '<a class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded border border-slate-200 text-slate-700 hover:bg-slate-50" href="' . e($buildUrl($p)) . '">' . $p . '</a>';
        }
    }
    if ($page < $totalPages) {
        $links[] = '<a class="btn btn-secondary text-xs" href="' . e($buildUrl($page + 1)) . '">Next &rarr;</a>';
    }
    $paginationHtml = '<div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-t border-slate-200 bg-slate-50">'
        . '<span class="text-xs text-slate-500">Showing ' . $from . '–' . $to . ' of ' . $totalTests . ' · Page ' . $page . '/' . $totalPages . '</span>'
        . '<div class="flex flex-wrap items-center gap-1.5">' . implode('', $links) . '</div>'
        . '</div>';
}

$content = page_header(
    'Tests Catalog',
    'Departments (optional): Hematology, Chemistry, Biochemistry, Special Chemistry, Histopathology, Microbiology.'
);
$content .= $message;
$content .= card(
    '<form method="post" class="grid gap-3 p-4 sm:grid-cols-3 sm:p-6 border-b border-slate-200">' .
    form_field('Code', 'code', 'text', null, 'e.g. C/S') .
    form_field('Name', 'name', 'text', null, 'e.g. Culture & Sensitivity') .
    select_field('Department / Category', 'category', $deptOpts, '', true) .
    form_field('Price', 'price', 'number', '0') .
    form_field('Sample', 'sample', 'text', 'Blood') .
    form_field('Unit', 'unit', 'text', '—', '', true) .
    form_field('Normal range', 'range', 'text', '—', '', true) .
    '<div class="flex items-end">' . btn_submit('Add test') . '</div>' .
    '</form>' .
    $filterHtml .
    data_table(['Code', 'Name', 'Department', 'Price', 'Sample type'], $rows) .
    $paginationHtml,
    'overflow-hidden'
);

render_page('Tests Catalog', 'admin', 'tests', $content);
