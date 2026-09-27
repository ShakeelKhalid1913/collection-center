<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $testId = $_POST['test_id'] ?? '';
        if ($testId !== '') {
            $ok = test_repo()->deleteTest($testId);
            $message = $ok ? flash_success('Test deleted.') : flash_error('Failed to delete test.');
        }
    } elseif ($action === 'create') {
        $category = trim($_POST['category'] ?? '');
        if ($category !== '' && !isset(TEST_DEPARTMENTS[$category])) {
            $message = flash_error('Please select a valid department, or leave it blank.');
        } else {
            $res = test_repo()->createTest([
                'code' => $_POST['code'] ?? '',
                'name' => $_POST['name'] ?? '',
                'category' => $category !== '' ? $category : 'General',
                'price' => (float)($_POST['price'] ?? 0),
                'sample' => $_POST['sample'] ?? 'Blood',
                'unit' => $_POST['unit'] ?? '',
                'range' => $_POST['reference_value'] ?? '', // Mapping reference value to range for backward compat in insert
                'normal_value' => $_POST['normal_value'] ?? '',
                'reference_value' => $_POST['reference_value'] ?? '',
                'methodology' => $_POST['methodology'] ?? '',
                'organization_id' => current_user()['organization_id'] ?? 'ORG-001',
            ]);
            
            if ($res['success']) {
                $testId = $res['id'];
                
                $pNames = $_POST['param_name'] ?? [];
                $pUnits = $_POST['param_unit'] ?? [];
                $pNormals = $_POST['param_normal'] ?? [];
                $pRanges = $_POST['param_range'] ?? [];
                
                $paramsToSave = [];
                foreach ($pNames as $i => $name) {
                    $name = trim($name);
                    if ($name === '') continue;
                    $paramsToSave[] = [
                        'name' => $name,
                        'unit' => $pUnits[$i] ?? '',
                        'normal_value' => $pNormals[$i] ?? '',
                        'reference_range' => $pRanges[$i] ?? '',
                    ];
                }
                
                if (!empty($paramsToSave)) {
                    test_repo()->saveParameters($testId, $paramsToSave);
                }
                
                $message = flash_success('Test added.');
            } else {
                $message = flash_error($res['error'] ?? 'Failed.');
            }
        }
    }
}

$deptOpts = ['' => '— Optional: select department —'] + TEST_DEPARTMENTS;
$resultTypeOpts = ['Numeric' => 'Numeric', 'Text' => 'Text', 'Options' => 'Options'];

$tests = test_repo()->getTests(current_user()['organization_id'] ?? 'ORG-001');
$testIds = array_column($tests, 'id');
$paramsGrouped = test_repo()->getParametersByTestIds($testIds);

$rows = [];
foreach ($tests as $t) {
    if ($t['category'] === 'Radiology') {
        continue;
    }

    $pCount = count($paramsGrouped[$t['id']] ?? []);
    $pCountStr = $pCount . ' params';
    
    $delForm = '<form method="post" class="inline" style="display:inline;" onsubmit="return confirm(\'Are you sure you want to delete this test?\');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="test_id" value="' . e($t['id']) . '">
        <button type="submit" class="text-red-600 hover:underline border-0 bg-transparent cursor-pointer p-0">Delete</button>
    </form>';
    
    $actions = '<a href="#" class="text-blue-600 hover:underline mr-2">Edit</a> ' . $delForm;

    $rows[] = [
        e($t['code']), 
        e($t['name']), 
        e($t['category']), 
        e(format_money((float) $t['price'])), 
        e($t['sample_type'] ?? $t['sample'] ?? ''), 
        e($t['normal_range'] ?? $t['reference_value'] ?? ''), 
        e($pCountStr), 
        $actions
    ];
}

$formHtml = '<form method="post" class="grid gap-3 p-4 sm:grid-cols-3 sm:p-6 border-b border-slate-200">
    <input type="hidden" name="action" value="create">
    ' . form_field('Code', 'code', 'text', null, 'UA') . '
    ' . form_field('Name', 'name', 'text', null, 'Uric Acid') . '
    ' . select_field('Department', 'category', $deptOpts, '', true) . '
    
    ' . form_field('Price', 'price', 'number', '0') . '
    ' . form_field('Sample Type', 'sample', 'text', 'Blood') . '
    ' . form_field('Unit', 'unit', 'text', '') . '
    
    ' . form_field('Normal Value', 'normal_value', 'text', '', '', true) . '
    ' . form_field('Reference Range/Value', 'reference_value', 'text', '', '', true) . '
    ' . select_field('Result Type', 'result_type', $resultTypeOpts, 'Numeric', true) . '
    
    <div class="sm:col-span-3">
        ' . textarea_field('Methodology', 'methodology', '', 'Optional methodology...', 2, true) . '
    </div>

    <div class="sm:col-span-3 mt-2 border-t border-slate-200 pt-4">
        <div class="flex justify-between items-center mb-2">
            <h3 class="font-medium">Dynamic Parameters</h3>
            <button type="button" data-add-param class="btn btn-secondary text-sm">+ Add Parameter</button>
        </div>
        <div data-param-list class="grid gap-2">
            <!-- parameters will be added here via JS -->
        </div>
    </div>

    <div class="sm:col-span-3 flex items-end mt-4">
        ' . btn_submit('Add Test') . '
    </div>
</form>';

$content = page_header(
    'Tests',
    'Departments (optional filter): Hematology · Chemistry · Biochemistry · Special Chemistry · Histopathology · Microbiology.'
);
$content .= $message;
$content .= card(
    $formHtml .
    data_table(['Code', 'Name', 'Department', 'Price', 'Sample', 'Normal Range', 'Parameters', 'Actions'], $rows),
    'overflow-hidden'
);

render_page('Tests', 'main-lab', 'tests', $content);
