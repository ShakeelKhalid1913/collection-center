<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';

// Handle Actions (create, update, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $testId = $_POST['test_id'] ?? '';
        if ($testId !== '') {
            $ok = test_repo()->deleteTest($testId);
            $message = $ok ? flash_success('Test deleted successfully.') : flash_error('Failed to delete test.');
        }
    } elseif ($action === 'create' || $action === 'update') {
        $category = trim($_POST['category'] ?? '');
        if ($category !== '' && !isset(TEST_DEPARTMENTS[$category])) {
            $message = flash_error('Please select a valid department, or leave it blank.');
        } else {
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $name = trim($_POST['name'] ?? '');

            if ($code === '' || $name === '') {
                $message = flash_error('Code and Name are required.');
            } else {
                $testData = [
                    'code' => $code,
                    'name' => $name,
                    'category' => $category !== '' ? $category : 'General',
                    'price' => (float)($_POST['price'] ?? 0),
                    'sample_type' => $_POST['sample'] ?? 'Blood',
                    'unit' => $_POST['unit'] ?? '',
                    'normal_range' => $_POST['reference_value'] ?? ($_POST['range'] ?? '—'),
                    'normal_value' => $_POST['normal_value'] ?? '',
                    'reference_value' => $_POST['reference_value'] ?? '',
                    'methodology' => $_POST['methodology'] ?? '',
                    'organization_id' => $orgId,
                ];

                $testId = '';
                $success = false;

                if ($action === 'update') {
                    $testId = $_POST['test_id'] ?? '';
                    $success = test_repo()->updateTest($testId, $testData);
                } else {
                    $res = test_repo()->createTest($testData);
                    $success = (bool)($res['success'] ?? false);
                    $testId = $res['id'] ?? '';
                }

                if ($success && $testId !== '') {
                    // Process Parameters
                    $pSections = $_POST['param_section'] ?? [];
                    $pNames = $_POST['param_name'] ?? [];
                    $pUnits = $_POST['param_unit'] ?? [];
                    $pNormals = $_POST['param_normal'] ?? [];
                    $pRanges = $_POST['param_range'] ?? [];

                    $paramsToSave = [];
                    foreach ($pNames as $i => $pName) {
                        $pName = trim((string)$pName);
                        if ($pName === '') continue;
                        $paramsToSave[] = [
                            'section' => trim((string)($pSections[$i] ?? '')),
                            'name' => $pName,
                            'unit' => trim((string)($pUnits[$i] ?? '')),
                            'normal_value' => trim((string)($pNormals[$i] ?? '')),
                            'reference_range' => trim((string)($pRanges[$i] ?? '')),
                        ];
                    }

                    test_repo()->saveParameters($testId, $paramsToSave);
                    $message = flash_success($action === 'update' ? "Test '{$name}' updated successfully." : "Test '{$name}' created successfully with " . count($paramsToSave) . " parameter(s).");
                } else {
                    $message = flash_error('Failed to save test.');
                }
            }
        }
    }
}

// Check if Editing a Test
$editingTest = null;
$editingParams = [];
if (!empty($_GET['edit'])) {
    $editId = trim((string)$_GET['edit']);
    $editingTest = test_repo()->findById($editId) ?: test_repo()->findByCode($editId, $orgId);
    if ($editingTest) {
        $editingParams = test_repo()->getParameters($editingTest['id']);
    }
}

$deptOpts = ['' => '— Optional: select department —'] + TEST_DEPARTMENTS;
$resultTypeOpts = ['Numeric' => 'Numeric', 'Text' => 'Text', 'Options' => 'Options'];

// Build Parameters HTML for Edit mode
$existingParamsHtml = '';
if (!empty($editingParams)) {
    foreach ($editingParams as $ep) {
        $pSec = e($ep['section'] ?? '');
        $pNm = e($ep['name'] ?? '');
        $pUn = e($ep['unit'] ?? '');
        $pNorm = e($ep['normal_value'] ?? '');
        $pRef = e($ep['reference_range'] ?? '');
        $existingParamsHtml .= <<<HTML
        <div class="grid gap-2 items-end p-2 bg-slate-50 border border-slate-200 rounded" style="grid-template-columns: 0.9fr 1.1fr 0.5fr 0.7fr 0.7fr auto;">
            <div>
                <label class="field-label text-xs">Section / Group</label>
                <input type="text" name="param_section[]" class="field text-sm" value="{$pSec}" placeholder="e.g. ERYTHROCYTES">
            </div>
            <div>
                <label class="field-label text-xs">Parameter Name</label>
                <input type="text" name="param_name[]" class="field text-sm" value="{$pNm}" placeholder="e.g. Hemoglobin (HB)" required>
            </div>
            <div>
                <label class="field-label text-xs">Unit</label>
                <input type="text" name="param_unit[]" class="field text-sm" value="{$pUn}" placeholder="g/dl">
            </div>
            <div>
                <label class="field-label text-xs">Normal Value</label>
                <input type="text" name="param_normal[]" class="field text-sm" value="{$pNorm}" placeholder="12.0 - 16.5">
            </div>
            <div>
                <label class="field-label text-xs">Reference Range</label>
                <input type="text" name="param_range[]" class="field text-sm" value="{$pRef}" placeholder="12.0 - 16.5">
            </div>
            <div class="flex items-end pb-1">
                <button type="button" class="btn btn-secondary" style="padding:0.4rem 0.6rem;color:#dc2626" data-remove-param title="Remove">&times;</button>
            </div>
        </div>
        HTML;
    }
}

$isEdit = $editingTest !== null;
$formTitle = $isEdit ? 'Edit Test: ' . e($editingTest['name']) . ' (' . e($editingTest['code']) . ')' : 'Add New Test &amp; Parameters';
$formAction = $isEdit ? 'update' : 'create';
$testIdHidden = $isEdit ? '<input type="hidden" name="test_id" value="' . e($editingTest['id']) . '">' : '';
$cancelBtn = $isEdit ? '<a href="/portals/main-lab/tests/index.php" class="btn btn-secondary ml-2">Cancel / Add New</a>' : '';

$valCode = e($editingTest['code'] ?? '');
$valName = e($editingTest['name'] ?? '');
$valDept = $editingTest['category'] ?? '';
$valPrice = (string)($editingTest['price'] ?? '0');
$valSample = e($editingTest['sample_type'] ?? 'Blood');
$valUnit = e($editingTest['unit'] ?? '');
$valNorm = e($editingTest['normal_value'] ?? '');
$valRef = e($editingTest['reference_value'] ?? ($editingTest['normal_range'] ?? ''));
$valMethod = e($editingTest['methodology'] ?? '');

$formHtml = <<<HTML
<form method="post" class="grid gap-3 p-4 sm:grid-cols-3 sm:p-6 border-b border-slate-200">
    <input type="hidden" name="action" value="{$formAction}">
    {$testIdHidden}
    
    <div>
        <label class="field-label">Code <span class="req"></span></label>
        <input type="text" name="code" class="field font-semibold" value="{$valCode}" placeholder="e.g. CBC, UA" required>
    </div>
    <div>
        <label class="field-label">Test Name <span class="req"></span></label>
        <input type="text" name="name" class="field font-semibold" value="{$valName}" placeholder="e.g. Complete Blood Count" required>
    </div>
HTML;

$formHtml .= select_field('Department', 'category', $deptOpts, $valDept, true);
$formHtml .= form_field('Price (Rs.)', 'price', 'number', $valPrice);
$formHtml .= form_field('Sample Type', 'sample', 'text', $valSample);
$formHtml .= form_field('Unit (general)', 'unit', 'text', $valUnit, 'e.g. g/dl, mg/dl', true);
$formHtml .= form_field('Normal Value', 'normal_value', 'text', $valNorm, 'e.g. 12.0 - 16.5', true);
$formHtml .= form_field('Reference Range', 'reference_value', 'text', $valRef, 'e.g. 12.0 - 16.5', true);
$formHtml .= select_field('Result Type', 'result_type', $resultTypeOpts, 'Numeric', true);

$btnText = $isEdit ? 'Update Test' : 'Add Test';

$formHtml .= <<<HTML
    <div class="sm:col-span-3">
        <label class="field-label">Methodology <span class="text-slate-400 font-normal">(optional)</span></label>
        <textarea name="methodology" rows="2" class="field" placeholder="e.g. Fully Automated Chemiluminescent Analyzer...">{$valMethod}</textarea>
    </div>

    <div class="sm:col-span-3 mt-2 border-t border-slate-200 pt-4">
        <div class="flex flex-wrap justify-between items-center mb-3">
            <div>
                <h3 class="font-bold text-slate-800 text-sm">Test Parameters &amp; Section Grouping</h3>
                <p class="text-xs text-slate-500">For multi-parameter tests (e.g. CBC, LFT, Lipid Profile). Give a Section name (e.g. ERYTHROCYTES, ABSOLUTE VALUES) to group parameters on the report.</p>
            </div>
            <button type="button" data-add-param class="btn btn-secondary text-sm font-semibold text-teal-800 bg-teal-50 border-teal-300 hover:bg-teal-100">
                <i class="fa-solid fa-plus mr-1"></i> Add Parameter Row
            </button>
        </div>
        <div data-param-list class="grid gap-2">
            {$existingParamsHtml}
        </div>
    </div>

    <div class="sm:col-span-3 flex items-center mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-floppy-disk mr-1"></i> {$btnText}
        </button>
        {$cancelBtn}
    </div>
</form>
HTML;

$q = trim((string)($_GET['q'] ?? ''));
$deptFilter = trim((string)($_GET['dept'] ?? ''));
$sampleFilter = trim((string)($_GET['sample'] ?? ''));

$allTests = array_values(array_filter(
    test_repo()->getTests($orgId),
    static function (array $t) use ($q, $deptFilter, $sampleFilter): bool {
        if (($t['category'] ?? '') === 'Radiology') {
            return false;
        }
        if ($deptFilter !== '' && strcasecmp((string)($t['category'] ?? ''), $deptFilter) !== 0) {
            return false;
        }
        if ($sampleFilter !== '' && strcasecmp((string)($t['sample_type'] ?? ''), $sampleFilter) !== 0) {
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

$sampleOpts = ['' => 'All specimens'];
foreach (test_repo()->getTests($orgId) as $t) {
    if (($t['category'] ?? '') === 'Radiology') {
        continue;
    }
    $s = trim((string)($t['sample_type'] ?? ''));
    if ($s !== '' && $s !== '—') {
        $sampleOpts[$s] = $s;
    }
}
ksort($sampleOpts, SORT_NATURAL | SORT_FLAG_CASE);

$perPage = 50;
$totalTests = count($allTests);
$totalPages = max(1, (int)ceil($totalTests / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;
$tests = array_slice($allTests, $offset, $perPage);

$testIds = array_column($tests, 'id');
$paramsGrouped = test_repo()->getParametersByTestIds($testIds);

$queryBase = array_filter([
    'q' => $q !== '' ? $q : null,
    'dept' => $deptFilter !== '' ? $deptFilter : null,
    'sample' => $sampleFilter !== '' ? $sampleFilter : null,
    'edit' => $isEdit ? (string)$editingTest['id'] : null,
], static fn($v) => $v !== null && $v !== '');

$buildUrl = static function (int $p) use ($queryBase): string {
    $qs = http_build_query(array_merge($queryBase, ['page' => $p]));
    return '/portals/main-lab/tests/index.php' . ($qs !== '' ? '?' . $qs : '');
};

$rows = [];
foreach ($tests as $t) {
    $pCount = count($paramsGrouped[$t['id']] ?? []);
    $badgeClass = $pCount > 0 ? 'bg-teal-100 text-teal-800' : 'bg-slate-100 text-slate-600';
    $pCountBadge = '<span class="inline-block px-2 py-0.5 text-xs font-semibold rounded ' . $badgeClass . '">' . $pCount . ' parameter' . ($pCount === 1 ? '' : 's') . '</span>';

    $editQs = http_build_query(array_merge($queryBase, ['edit' => $t['id'], 'page' => $page]));
    $editUrl = '/portals/main-lab/tests/index.php?' . $editQs;
    $delForm = '<form method="post" class="inline" style="display:inline;" onsubmit="return confirm(\'Delete test ' . e($t['name']) . '?\');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="test_id" value="' . e($t['id']) . '">
        <button type="submit" class="text-red-600 hover:text-red-800 hover:underline border-0 bg-transparent cursor-pointer p-0 text-xs font-medium ml-2">Delete</button>
    </form>';

    $actions = '<a href="' . e($editUrl) . '" class="text-teal-700 hover:text-teal-900 font-semibold hover:underline text-xs"><i class="fa-solid fa-pen-to-square"></i> Edit</a>' . $delForm;

    $rows[] = [
        '<strong class="text-slate-800">' . e($t['code']) . '</strong>',
        e($t['name']),
        '<span class="chip">' . e($t['category']) . '</span>',
        e(format_money((float) $t['price'])),
        e($t['sample_type'] ?? 'Blood'),
        e($t['reference_value'] ?: ($t['normal_range'] ?? '—')),
        $pCountBadge,
        $actions,
    ];
}

$from = $totalTests === 0 ? 0 : $offset + 1;
$to = min($offset + $perPage, $totalTests);
$listHeading = 'Configured Tests (' . $totalTests . ' match' . ($totalTests === 1 ? '' : 'es') . ') — showing ' . $from . '–' . $to;

$filterDeptOpts = ['' => 'All departments'] + TEST_DEPARTMENTS;
$qVal = e($q);
$filterHtml = '<form method="get" class="grid gap-3 p-4 sm:grid-cols-4 border-b border-slate-200 bg-slate-50/80">'
    . ($isEdit ? '<input type="hidden" name="edit" value="' . e((string)$editingTest['id']) . '">' : '')
    . '<div class="sm:col-span-2"><label class="field-label" for="q">Search</label>'
    . '<input type="search" id="q" name="q" class="field" value="' . $qVal . '" placeholder="Code or test name (e.g. CBC, sugar)">'
    . '</div>'
    . select_field('Department', 'dept', $filterDeptOpts, $deptFilter, true)
    . select_field('Specimen', 'sample', $sampleOpts, $sampleFilter, true)
    . '<div class="sm:col-span-4 flex flex-wrap gap-2">'
    . '<button type="submit" class="btn btn-primary text-sm"><i class="fa-solid fa-magnifying-glass mr-1"></i> Filter</button>'
    . '<a href="/portals/main-lab/tests/index.php" class="btn btn-secondary text-sm">Clear</a>'
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
        . '<span class="text-xs text-slate-500">Page ' . $page . ' of ' . $totalPages . ' · ' . $perPage . ' per page</span>'
        . '<div class="flex flex-wrap items-center gap-1.5">' . implode('', $links) . '</div>'
        . '</div>';
}

$content = page_header(
    'Tests Catalog',
    'Configure pathology tests, multiple parameters (e.g. CBC), reference ranges, and department classification.'
);
$content .= $message;
$content .= card(
    panel_head($formTitle) .
    $formHtml .
    panel_head($listHeading) .
    $filterHtml .
    data_table(['Code', 'Name', 'Department', 'Price', 'Sample', 'Reference Range', 'Parameters', 'Actions'], $rows) .
    $paginationHtml,
    'overflow-hidden'
);

render_page('Tests Catalog', 'main-lab', 'tests', $content);

