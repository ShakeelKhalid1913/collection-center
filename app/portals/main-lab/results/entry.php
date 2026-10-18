<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';

$labNo = trim((string)($_GET['lab_no'] ?? $_GET['id'] ?? $_POST['lab_no'] ?? ''));
$activeTest = trim((string)($_GET['test'] ?? $_POST['active_test'] ?? ''));

$entry = null;
if ($labNo !== '') {
    $entry = lab_repo()->findByLabNo($labNo);
    if (!$entry) {
        $singleRes = result_repo()->findResult($labNo);
        if ($singleRes && !empty($singleRes['lab_no'])) {
            $labNo = $singleRes['lab_no'];
            $entry = lab_repo()->findByLabNo($labNo);
        }
    }
}

if (!$entry) {
    $allEntries = lab_repo()->getAll();
    foreach ($allEntries as $e) {
        if (($e['status'] ?? '') !== 'verified' && ($e['status'] ?? '') !== 'completed') {
            $entry = $e;
            $labNo = $e['lab_no'];
            break;
        }
    }
    if (!$entry && !empty($allEntries[0])) {
        $entry = $allEntries[0];
        $labNo = $entry['lab_no'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['lab_no'])) {
    require_permission('report_edit');
    $postLabNo = trim($_POST['lab_no']);
    $activeTest = trim((string)($_POST['active_test'] ?? $activeTest));
    $resItems = $_POST['results'] ?? [];
    $notes = trim((string)($_POST['clinical_notes'] ?? ''));

    // Automatically advance transit lifecycle to result_entered
    lab_repo()->updateTransitStatus($postLabNo, 'result_entered');
    audit_log('UPDATE_RESULTS', 'results', $postLabNo, "Updated test results for active test '{$activeTest}'");

    $batchData = [];
    foreach ($resItems as $rId => $item) {
        $val = trim((string)($item['value'] ?? ''));
        $flag = trim((string)($item['flag'] ?? ''));
        $range = trim((string)($item['range'] ?? ''));
        $isVisible = isset($item['show']) ? 1 : 0;

        if ($val === '') {
            $flag = '';
        } else {
            $auto = compute_result_flag($val, $range);
            // Auto-only: prefer computed flag whenever range is numeric
            if ($auto !== '' || is_numeric($val)) {
                $flag = $auto;
            }
        }

        $subTable = array_key_exists('sub_table', $item) ? trim((string)$item['sub_table']) : null;
        $resultNote = array_key_exists('result_note', $item) ? trim((string)$item['result_note']) : null;

        $batchData[] = [
            'id' => $rId,
            'value' => $val,
            'unit' => isset($item['unit']) ? trim((string)$item['unit']) : null,
            'reference_range' => isset($item['range']) ? trim((string)$item['range']) : null,
            'flag' => $flag,
            'is_visible' => $isVisible,
            'sub_table' => $subTable,
            'result_note' => $resultNote,
        ];
    }

    if (!empty($batchData)) {
        result_repo()->saveResultsBatch($postLabNo, $batchData);
    }

    $pageMap = [];
    if (!empty($_POST['page_map_test']) && is_array($_POST['page_map_test'])) {
        $pageMap = \App\Repositories\ResultRepository::pageMapFromParallel(
            $_POST['page_map_test'],
            $_POST['page_map_page'] ?? []
        );
    } elseif (!empty($_POST['page_map']) && is_array($_POST['page_map'])) {
        $pageMap = $_POST['page_map'];
    }
    if ($pageMap !== []) {
        result_repo()->assignPrintPages($postLabNo, $pageMap);
    }

    if ($notes !== '') {
        lab_repo()->updateEntry($postLabNo, ['clinical_notes' => $notes]);
    }

    if (isset($_POST['test_methodology'])) {
        $testMethodology = trim((string)$_POST['test_methodology']);
        $testToUpdateId = trim((string)($_POST['active_test_id'] ?? ''));
        if ($testToUpdateId !== '') {
            test_repo()->updateMethodology($testToUpdateId, $testMethodology);
        } elseif ($activeTest !== '') {
            $tObj = test_repo()->findByCode($activeTest, $orgId);
            if ($tObj && !empty($tObj['id'])) {
                test_repo()->updateMethodology((string)$tObj['id'], $testMethodology);
            }
        }
    }

    if (!empty($_POST['send_verify']) || !empty($_POST['goto_preview'])) {
        if (!empty($_POST['send_verify'])) {
            result_repo()->verifyAllByLabNo($postLabNo, current_user()['name'] ?? 'Laboratory Staff');
        }
        header('Location: /portals/main-lab/reports/preview.php?lab_no=' . urlencode($postLabNo));
        exit;
    }

    $message = flash_success('Results saved. Unchecked rows stay off the printed report.');
    $entry = lab_repo()->findByLabNo($postLabNo);
    $labNo = $postLabNo;
}

if (!$entry) {
    $content = page_header('Enter Results', 'No patient tests found yet.');
    $content .= card(
        '<p class="p-6 text-slate-600">Register a patient and book tests first, then open this screen to type results.</p>' .
        '<div class="px-6 pb-6"><a class="btn btn-primary" href="/portals/main-lab/patients/records.php">Open patient list</a></div>'
    );
    $portal = current_portal();
    $activeKey = ($portal === 'imaging') ? 'station-lab' : 'results-entry';
    render_page('Enter Results', $portal, $activeKey, $content);
    exit;
}

$patient = patient_repo()->findById((string)($entry['patient_id'] ?? '')) ?: [];
$mrNo = e((string)($patient['patient_no'] ?? ($entry['patient_id'] ?? '—')));
$patientName = e((string)($entry['patient_name'] ?? ($patient['full_name'] ?? '—')));
$testsOrdered = e(normalize_tests_list((string)($entry['tests'] ?? '')));
$doctor = e((string)($entry['doctor'] ?? 'Walk-in / Self'));
$status = e((string)($entry['status'] ?? 'pending'));
$age = e((string)($patient['age'] ?? '—'));
$gender = e((string)($patient['gender'] ?? '—'));
$phone = e((string)($patient['phone'] ?? '—'));
$regAt = e(format_datetime_report($entry['created_at'] ?? null));
$notes = e((string)($entry['clinical_notes'] ?? ''));
$labNoSafe = e($labNo);

$resultRows = result_repo()->ensureResultsInitialized(
    $labNo,
    (string)($entry['tests'] ?? ''),
    (string)($entry['patient_name'] ?? ($patient['full_name'] ?? '')),
    $orgId
);

// Group rows by test name (CBC, LFT, …)
$byTest = [];
foreach ($resultRows as $r) {
    $tName = trim((string)($r['test'] ?? 'Test'));
    if ($tName === '') {
        $tName = 'Test';
    }
    $byTest[$tName][] = $r;
}
$testNames = array_keys($byTest);
if ($activeTest === '' || !isset($byTest[$activeTest])) {
    $activeTest = $testNames[0] ?? '';
}
$testCount = count($testNames);
$testIndex = max(0, array_search($activeTest, $testNames, true));
$prevTest = $testIndex > 0 ? $testNames[$testIndex - 1] : null;
$nextTest = $testIndex < $testCount - 1 ? $testNames[$testIndex + 1] : null;

$baseUrl = '/portals/main-lab/results/entry.php?lab_no=' . urlencode($labNo);
$previewLink = '/portals/main-lab/reports/preview.php?lab_no=' . urlencode($labNo);
$addTestLink = '/portals/main-lab/lab-entries/edit.php?lab_no=' . urlencode($labNo);
$historyMr = (string)($patient['patient_no'] ?? ($entry['patient_id'] ?? ''));
$historyLink = '/portals/main-lab/patients/history.php?id=' . urlencode($historyMr);

// Patient switcher & live search dataset (searches by Lab No, MR No, or Name in real-time)
$allEntries = lab_repo()->getEntriesWithPatients($orgId, 300);
$switcherOpts = '';
$searchVisits = [];
foreach ($allEntries as $ae) {
    $sel = $ae['lab_no'] === $labNo ? ' selected' : '';
    $testsLabel = normalize_tests_list((string)($ae['tests'] ?? ''));
    $switcherOpts .= '<option value="' . e($ae['lab_no']) . '"' . $sel . '>'
        . e($ae['lab_no']) . ' — ' . e($ae['patient_name']) . ' (' . e($testsLabel) . ')</option>';

    $searchVisits[] = [
        'lab_no' => (string)$ae['lab_no'],
        'mr_no' => (string)($ae['mr_no'] ?? ''),
        'patient_name' => (string)($ae['patient_name'] ?? $ae['full_name'] ?? ''),
        'tests' => $testsLabel,
        'status' => (string)($ae['status'] ?? 'pending'),
        'age' => (string)($ae['age'] ?? ''),
        'gender' => (string)($ae['gender'] ?? ''),
        'doctor' => (string)($ae['doctor'] ?? ''),
        'date' => format_date($ae['created_at'] ?? null),
        'is_current' => ($ae['lab_no'] === $labNo),
    ];
}
$searchVisitsJson = json_encode($searchVisits, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

$testSelectOpts = '';
foreach ($testNames as $i => $tn) {
    $sel = $tn === $activeTest ? ' selected' : '';
    $testSelectOpts .= '<option value="' . e($tn) . '"' . $sel . '>' . e($tn) . '</option>';
}

$activeRows = $byTest[$activeTest] ?? [];

// Resolve catalog meta for dropdown options and methodology on the active test
$activeTestMeta = test_repo()->findByCode($activeTest, $orgId);
$activeTestId = (string)($activeTestMeta['id'] ?? '');
$activeTestMethodology = (string)($activeTestMeta['methodology'] ?? '');
$testResultType = strtolower((string)($activeTestMeta['result_type'] ?? 'numeric'));
$testResultOptions = parse_result_options((string)($activeTestMeta['result_options'] ?? ''));
if ($testResultOptions === null && $testResultType === 'options') {
    $testResultOptions = parse_result_options((string)($activeTestMeta['normal_range'] ?? $activeTestMeta['reference_value'] ?? ''));
}

$isImagingActive = is_narrative_imaging_test($activeTest, $activeTestMeta['category'] ?? null);
$imagingTemplates = template_repo()->getAll($orgId);
$tplSelectOpts = '';
$tplJsonMap = [];
foreach ($imagingTemplates as $tpl) {
    $tId = (string)$tpl['id'];
    $tTitle = (string)$tpl['title'];
    $tplSelectOpts .= '<option value="' . e($tId) . '">' . e($tTitle) . '</option>';
    $tplJsonMap[$tId] = (string)$tpl['content'];
}
$tplJsonMapEncoded = json_encode($tplJsonMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

$imagingRow = $activeRows[0] ?? null;
$imagingResId = (string)($imagingRow['id'] ?? 'RES-IMG-' . bin2hex(random_bytes(4)));
$currentImagingVal = (string)($imagingRow['value'] ?? '');
if ($currentImagingVal === 'Pending' || $currentImagingVal === '-' || $currentImagingVal === '—') {
    $currentImagingVal = '';
}
$currentImagingValSafe = e($currentImagingVal);

// Page grouping state (per test)
$pageAssignments = [];
$maxPage = 1;
foreach ($resultRows as $rr) {
    $tn = trim((string)($rr['test'] ?? 'Test'));
    $pg = max(1, (int)($rr['print_page'] ?? 1));
    $pageAssignments[$tn] = $pg;
    $maxPage = max($maxPage, $pg);
}
foreach ($testNames as $tn) {
    if (!isset($pageAssignments[$tn])) {
        $pageAssignments[$tn] = 1;
    }
}

$tableBody = '';
$currentSection = null;
$firstInput = true;

foreach ($activeRows as $r) {
    $sec = trim((string)($r['section'] ?? ''));
    if ($sec !== '' && $sec !== $currentSection) {
        $currentSection = $sec;
        $tableBody .= '<tr class="bg-slate-100"><td colspan="5" class="px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-700">'
            . e($sec) . '</td></tr>';
    }

    $rId = e($r['id']);
    $paramName = e($r['parameter'] ?: ($r['test'] ?? 'Test'));
    $val = e($r['value'] ?? '');
    $unit = e($r['unit'] ?? '');
    $rangeRaw = (string)($r['reference_range'] ?: ($r['range'] ?? '—'));
    $range = e($rangeRaw);
    $flag = strtolower((string)($r['flag'] ?? ''));
    if ($flag === '' && ($r['value'] ?? '') !== '') {
        $flag = strtolower(compute_result_flag((string)$r['value'], $rangeRaw));
    }
    $shown = !isset($r['is_visible']) || (int)$r['is_visible'] === 1;
    $showChecked = $shown ? ' checked' : '';
    $rowDim = $shown ? '' : ' opacity-50';
    $autofocus = $firstInput ? ' autofocus' : '';
    $firstInput = false;

    $valClass = 'field text-sm font-bold w-28 text-center result-value-input';
    if ($flag === 'l' || $flag === 'h' || $flag === 'critical') {
        $valClass .= ' text-blue-700 border-blue-400';
    }

    $flagDisplay = '—';
    if ($flag === 'l') {
        $flagDisplay = '<span class="font-bold" style="color:#2563eb" title="Low">↓ Low</span>';
    } elseif ($flag === 'h') {
        $flagDisplay = '<span class="font-bold" style="color:#dc2626" title="High">↑ High</span>';
    } elseif ($flag === 'critical') {
        $flagDisplay = '<span class="font-bold" style="color:#dc2626" title="Critical">! Critical</span>';
    } elseif (($r['value'] ?? '') !== '' && is_numeric((string)$r['value'])) {
        $flagDisplay = '<span class="font-bold" style="color:#16a34a" title="Normal">✓ Normal</span>';
    }

    $subValRaw = (string)($r['sub_table'] ?? '');
    $subHtml = criteria_table_builder_field('results[' . $rId . '][sub_table]', $subValRaw);
    $resultNote = e($r['result_note'] ?? '');

    $rowOptions = parse_result_options($rangeRaw);
    if ($rowOptions === null && count($activeRows) === 1 && $testResultOptions !== null) {
        $rowOptions = $testResultOptions;
    }

    if ($rowOptions !== null) {
        $optHtml = '<option value="">—</option>';
        foreach ($rowOptions as $opt) {
            $sel = strcasecmp((string)($r['value'] ?? ''), $opt) === 0 ? ' selected' : '';
            $optHtml .= '<option value="' . e($opt) . '"' . $sel . '>' . e($opt) . '</option>';
        }
        $valueControl = '<select name="results[' . $rId . '][value]" class="field text-sm w-full result-value-input" data-result-input' . $autofocus . '>'
            . $optHtml . '</select>';
    } else {
        $valueControl = '<input type="text" name="results[' . $rId . '][value]" value="' . $val . '" class="' . $valClass . ' w-full" placeholder="—" data-result-input data-ref-range="' . $range . '"' . $autofocus . '>';
    }

    $tableBody .= <<<HTML
    <tr class="border-b border-slate-100{$rowDim}" data-result-row>
        <td class="px-2.5 py-1.5 align-top">
            <label class="inline-flex items-start gap-2 cursor-pointer">
                <input type="checkbox" name="results[{$rId}][show]" value="1" class="mt-1"{$showChecked}>
                <span class="w-full">
                    <span class="block text-[10px] uppercase text-slate-400 font-semibold">Show</span>
                    <span class="font-semibold text-slate-900 text-sm results-entry-param">{$paramName}</span>
                    {$subHtml}
                </span>
            </label>
            <input type="hidden" name="results[{$rId}][flag]" value="{$flag}" data-auto-flag>
        </td>
        <td class="px-2 py-1.5 text-sm align-top text-right">
            <input type="text" name="results[{$rId}][range]" value="{$range}" class="field text-xs w-32 py-1 px-2 font-mono text-right" placeholder="—" title="Reference range / number (e.g. 12-16)" data-ref-range-input>
        </td>
        <td class="px-2 py-1.5 text-sm align-top text-right">
            <input type="text" name="results[{$rId}][unit]" value="{$unit}" class="field text-xs w-20 py-1 px-2 text-right" placeholder="—" title="Unit of measurement (e.g. g/dL, mg/dL)">
        </td>
        <td class="px-2 py-1.5 align-top min-w-[8.5rem] text-right">
            {$valueControl}
            <div class="mt-1">
                <textarea name="results[{$rId}][result_note]" rows="2" class="field text-xs w-full py-1 px-2 text-slate-700 placeholder:text-slate-400 text-right" placeholder="Note / sub-result" title="Extra finding or note shown directly below result on report">{$resultNote}</textarea>
            </div>
        </td>
        <td class="px-2 py-1.5 align-top text-sm text-center" data-flag-display>{$flagDisplay}</td>
    </tr>
    HTML;
}

if ($tableBody === '') {
    $tableBody = '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No parameters for this test yet. Add the test from the catalog, or pick another test on the left.</td></tr>';
}

// Build page-grouping board
$pageBoard = '';
for ($p = 1; $p <= $maxPage; $p++) {
    $chips = '';
    foreach ($testNames as $tn) {
        if ((int)$pageAssignments[$tn] !== $p) {
            continue;
        }
        $chips .= '<div class="page-group-chip flex items-center justify-between gap-2 bg-white border border-slate-200 rounded px-2 py-1.5 text-xs cursor-grab" draggable="true" data-test-name="' . e($tn) . '">'
            . '<span class="font-semibold text-slate-800 truncate">' . e($tn) . '</span>'
            . '<input type="hidden" name="page_map_test[]" value="' . e($tn) . '">'
            . '<input type="hidden" name="page_map_page[]" value="' . $p . '" data-page-map>'
            . '</div>';
    }
    if ($chips === '') {
        $chips = '<div class="text-[11px] text-slate-400 italic px-1 py-2">Drop tests here</div>';
    }
    $pageBoard .= '<div class="page-group-page border border-dashed border-slate-300 rounded-lg p-2 bg-slate-50 space-y-1.5" data-page="' . $p . '">'
        . '<div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Page ' . $p . '</div>'
        . '<div class="page-group-list space-y-1.5 min-h-[2rem]" data-page-list="' . $p . '">' . $chips . '</div>'
        . '</div>';
}

$prevBtn = $prevTest
    ? '<a class="btn btn-secondary text-xs flex-1" href="' . e($baseUrl . '&test=' . urlencode($prevTest)) . '">← Previous</a>'
    : '<span class="btn btn-secondary text-xs flex-1 opacity-40 pointer-events-none">← Previous</span>';
$nextBtn = $nextTest
    ? '<a class="btn btn-secondary text-xs flex-1" href="' . e($baseUrl . '&test=' . urlencode($nextTest)) . '">Next →</a>'
    : '<span class="btn btn-secondary text-xs flex-1 opacity-40 pointer-events-none">Next →</span>';

$activeTestLabel = e($activeTest);
$activeTestIdSafe = e($activeTestId);
$activeTestMethodologySafe = e($activeTestMethodology);
$posLabel = $testCount > 0 ? (($testIndex + 1) . ' of ' . $testCount) : '0 of 0';
$baseUrlJs = e($baseUrl);

$bSettings = branding_settings();
$reportFontKey = $bSettings['report_font'] ?? 'times_bold_italic';
$allFonts = supported_document_fonts();
$fontConf = $allFonts[$reportFontKey] ?? $allFonts['times_bold_italic'];
$entryFontFamily = $fontConf['family'];
$entryFontWeight = $fontConf['weight'];
$entryFontStyle = $fontConf['style'];
$entryStyleAttr = htmlspecialchars("--report-body-font: {$entryFontFamily}; --report-body-weight: {$entryFontWeight}; --report-body-style: {$entryFontStyle};", ENT_COMPAT, 'UTF-8');

$content = page_header(
    'Enter Results',
    'Type values like the printed report. Tick Show to include a row on the report. Enter jumps to the next row.'
);
$content .= $message;

$content .= <<<HTML
<style>
.results-entry-table,
.results-entry-table th,
.results-entry-table td,
.results-entry-table input,
.results-entry-table select,
.results-entry-table textarea {
    font-family: {$entryFontFamily} !important;
}
.results-entry-table td,
.results-entry-table .results-entry-param,
.results-entry-table input {
    font-weight: {$entryFontWeight} !important;
    font-style: {$entryFontStyle} !important;
}
</style>
<form method="post" id="results-workspace" data-results-entry style="{$entryStyleAttr}">
<input type="hidden" name="lab_no" value="{$labNoSafe}">
<input type="hidden" name="active_test" value="{$activeTestLabel}">

<div class="mb-5 bg-white border border-slate-200/90 rounded-3xl p-4 sm:p-5 shadow-card">
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-3">
        <!-- Live Real-Time Google-Style Patient Search -->
        <div class="relative flex-1" id="patient-search-container">
            <div class="relative flex items-center">
                <i class="fa-solid fa-magnifying-glass absolute left-4 text-slate-400 text-sm pointer-events-none"></i>
                <input 
                    type="text" 
                    id="patient-search-input" 
                    class="w-full pl-11 pr-24 py-2.5 text-sm rounded-2xl border border-slate-200 focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10 transition-all font-semibold placeholder:text-slate-400 outline-none bg-slate-50/50 focus:bg-white" 
                    placeholder="Search patient by Lab No (e.g. 1004), MR No (e.g. MR0747), or Name…" 
                    autocomplete="off"
                    spellcheck="false"
                >
                <div class="absolute right-3 flex items-center gap-1.5">
                    <button type="button" id="patient-search-clear" class="hidden text-slate-400 hover:text-slate-600 p-1 text-xs" title="Clear search">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <span class="hidden sm:inline-block px-2 py-0.5 text-[10px] font-mono font-bold text-slate-400 bg-slate-100 border border-slate-200 rounded-lg">Press /</span>
                </div>
            </div>

            <!-- Instant Autocomplete Dropdown List -->
            <div 
                id="patient-search-dropdown" 
                class="hidden absolute left-0 right-0 top-full mt-2 bg-white border border-slate-200 rounded-2xl shadow-2xl z-50 overflow-hidden max-h-[420px] overflow-y-auto"
            >
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider flex justify-between items-center">
                    <span id="patient-search-header">Recent Patient Visits</span>
                    <span class="text-[10px] text-slate-400 font-normal">Use ↑ ↓ arrows &amp; Enter to jump</span>
                </div>
                <div id="patient-search-list" class="divide-y divide-slate-100">
                    <!-- Dynamic live cards -->
                </div>
            </div>
        </div>

        <!-- Quick Switcher Dropdown & Patient Links -->
        <div class="flex items-center gap-2 text-xs shrink-0 flex-wrap">
            <span class="text-slate-400 hidden xl:inline font-bold">or visit:</span>
            <select class="field text-xs py-2 max-w-[200px] text-slate-700 font-semibold" onchange="if(this.value) location.href='/portals/main-lab/results/entry.php?lab_no='+encodeURIComponent(this.value)">
                {$switcherOpts}
            </select>
            <a href="{$addTestLink}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-2xl bg-indigo-50 hover:bg-indigo-100 text-indigo-800 font-bold text-xs border border-indigo-200 whitespace-nowrap"><i class="fa-solid fa-user-pen mr-1 text-indigo-600"></i> Edit Entry &amp; Bill</a>
            <a href="/portals/main-lab/receipts.php?lab_no={$labNoSafe}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs border border-slate-200 whitespace-nowrap"><i class="fa-solid fa-receipt mr-1"></i> Patient Slip</a>
            <a href="{$previewLink}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs border border-slate-200 whitespace-nowrap"><i class="fa-solid fa-eye mr-1"></i> Preview Report</a>
            <a href="{$historyLink}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs border border-slate-200 whitespace-nowrap"><i class="fa-solid fa-clock-rotate-left mr-1"></i> History</a>
        </div>
    </div>
</div>

<div class="grid gap-5 lg:grid-cols-[300px_minmax(0,1fr)] items-start">
    <aside class="space-y-4 lg:sticky lg:top-4">
        <div class="bg-white border border-slate-200/90 rounded-3xl p-5 shadow-card space-y-2.5">
            <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs sm:text-sm shadow-md border border-slate-700/60 transition-all"><i class="fa-solid fa-floppy-disk text-[#c2f13c]"></i> Save Results</button>
            <button type="submit" name="send_verify" value="1" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm shadow-sm transition-all"><i class="fa-solid fa-circle-check"></i> Save &amp; Verify</button>
            <button type="submit" name="goto_preview" value="1" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs sm:text-sm border border-slate-200 transition-all"><i class="fa-solid fa-file-pdf"></i> Save &amp; View / Print</button>
            <a href="{$addTestLink}" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs sm:text-sm border border-slate-200 transition-all"><i class="fa-solid fa-plus"></i> Add / Change Tests</a>
            <a href="/portals/main-lab/archive.php" class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-slate-500 hover:text-slate-800 font-bold text-xs transition-all">Back to History</a>
        </div>

        <div class="bg-white border border-slate-200/90 rounded-3xl p-5 shadow-card space-y-3">
            <div>
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Current test</div>
                <div class="font-extrabold text-slate-900 text-base leading-snug mt-1">{$activeTestLabel}</div>
                <div class="text-xs text-slate-500 font-medium mt-1">{$posLabel} on this visit</div>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-700 block mb-1">Jump to test</label>
                <select class="field text-xs w-full font-semibold" onchange="if(this.value) location.href='{$baseUrlJs}&amp;test='+encodeURIComponent(this.value)">
                    {$testSelectOpts}
                </select>
            </div>
            <div class="flex gap-2 pt-1">{$prevBtn}{$nextBtn}</div>
            <p class="text-[11px] text-slate-500 leading-relaxed font-medium">Each booked test has its own parameter sheet. Flags auto-calculate from reference ranges. Press Enter to move down.</p>
        </div>

        <div class="bg-white border border-slate-200/90 rounded-3xl p-5 shadow-card space-y-3" data-page-grouping>
            <div class="flex items-center justify-between gap-2">
                <div>
                    <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Page grouping</div>
                    <p class="text-[11px] text-slate-500 mt-0.5">Drag tests onto pages</p>
                </div>
                <button type="button" class="btn btn-secondary text-xs px-2.5 py-1 rounded-xl" data-add-page>+ Page</button>
            </div>
            <div class="space-y-2" data-page-board>{$pageBoard}</div>
        </div>

        <div class="bg-white border border-slate-200/90 rounded-3xl p-5 shadow-card text-xs text-slate-600 space-y-1.5">
            <div><span class="font-bold text-slate-800">Status:</span> {$status}</div>
            <div><span class="font-bold text-slate-800">Doctor:</span> {$doctor}</div>
            <div><span class="font-bold text-slate-800">All tests:</span> {$testsOrdered}</div>
        </div>
    </aside>
HTML;

    $testContentHtml = '';
    if ($isImagingActive) {
        $testContentHtml = <<<HTML
        <!-- Dedicated Ultrasound & Radiology Report Type Section -->
        <div class="px-5 py-4 bg-gradient-to-r from-teal-50 via-cyan-50 to-emerald-50 border-b border-teal-200">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-teal-600 text-white shadow-sm">
                        <i class="fa-solid fa-shapes text-sm"></i>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <strong class="text-teal-950 text-base font-extrabold tracking-wide">{$activeTestLabel}</strong>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-teal-100 text-teal-800 border border-teal-200">
                                Ultrasound Report Type Section
                            </span>
                        </div>
                        <p class="text-xs text-teal-800 font-medium mt-0.5">
                            Select ultrasound study type below to auto-load clinical anatomical headings and normal findings
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1.5 bg-white border border-teal-300 rounded-xl px-2.5 py-1 shadow-2xs">
                        <label for="us-report-type-select" class="text-[11px] font-bold text-teal-900 uppercase tracking-wide">Report Type:</label>
                        <select id="us-report-type-select" onchange="if(this.value) applyUltrasoundReportType(this.value)" class="text-xs font-semibold text-slate-800 bg-transparent border-none focus:ring-0 py-0.5 pl-1 pr-6 cursor-pointer">
                            <option value="">— Select Ultrasound Report Type —</option>
                            <optgroup label="🩺 Abdomen &amp; Urinary Tract">
                                <option value="us_whole_abd">Ultrasound Whole Abdomen &amp; Pelvis</option>
                                <option value="us_upper_abd">Ultrasound Upper Abdomen (Hepatobiliary)</option>
                                <option value="us_kub">Ultrasound KUB &amp; Prostate (Kidneys, Bladder, Prostate)</option>
                            </optgroup>
                            <optgroup label="🌸 Gynaecological &amp; Pelvic">
                                <option value="us_pelvis">Ultrasound Pelvis (Female / Gynaecological)</option>
                                <option value="us_tvs">Ultrasound TVS (Transvaginal Sonography)</option>
                                <option value="us_follicular">Ultrasound Follicular Tracking / Study</option>
                                <option value="us_breast">Ultrasound Breast (Bilateral / Sonomammography)</option>
                            </optgroup>
                            <optgroup label="👶 Obstetrics &amp; Pregnancy">
                                <option value="us_obstetric">Ultrasound Obstetric / Antenatal (Routine Pregnancy)</option>
                                <option value="us_anomaly">Ultrasound 2nd Trimester Fetal Anomaly Scan</option>
                            </optgroup>
                            <optgroup label="🔬 Doppler &amp; Superficial Parts">
                                <option value="us_scrotal">Ultrasound Scrotum &amp; Testicular Color Doppler</option>
                                <option value="us_thyroid">Ultrasound Thyroid &amp; Neck</option>
                                <option value="us_soft_tissue">Ultrasound Soft Tissue / Musculoskeletal Swelling</option>
                            </optgroup>
                            <optgroup label="🩻 Other Radiology Modalities">
                                <option value="xray_chest">Chest X-Ray PA View</option>
                                <option value="ecg_12">12-Lead Electrocardiogram (ECG)</option>
                            </optgroup>
                        </select>
                    </div>

                    <select id="tpl-picker" class="field text-xs py-1.5 px-3 bg-white font-semibold">
                        <option value="">— Saved Custom Templates —</option>
                        {$tplSelectOpts}
                    </select>
                    <button type="button" onclick="insertTplFromSelect()" class="btn btn-secondary text-xs font-bold py-1.5 px-3 bg-white text-teal-800 border-teal-300 hover:bg-teal-50">
                        <i class="fa-solid fa-paste text-teal-600 mr-1"></i> Paste Saved
                    </button>
                </div>
            </div>

            <!-- Quick-Pick Ultrasound Report Type Buttons -->
            <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-teal-200/70 text-xs">
                <span class="font-bold text-teal-900 uppercase tracking-wide text-[10px] mr-1">Quick Select:</span>
                <button type="button" onclick="applyUltrasoundReportType('us_whole_abd')" class="px-2.5 py-1 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs transition-colors flex items-center gap-1.5" title="Liver, Gallbladder, Spleen, Pancreas, Kidneys, Bladder, Pelvis">
                    <i class="fa-solid fa-lungs text-teal-600"></i> Whole Abdomen &amp; Pelvis
                </button>
                <button type="button" onclick="applyUltrasoundReportType('us_kub')" class="px-2.5 py-1 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs transition-colors flex items-center gap-1.5" title="Kidneys, Ureters, Bladder & Prostate (Pre/Post-void)">
                    <i class="fa-solid fa-droplet text-blue-600"></i> KUB &amp; Prostate
                </button>
                <button type="button" onclick="applyUltrasoundReportType('us_pelvis')" class="px-2.5 py-1 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs transition-colors flex items-center gap-1.5" title="Uterus, Endometrium, Ovaries, Adnexa, Pouch of Douglas">
                    <i class="fa-solid fa-venus text-pink-600"></i> Female Pelvis
                </button>
                <button type="button" onclick="applyUltrasoundReportType('us_obstetric')" class="px-2.5 py-1 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs transition-colors flex items-center gap-1.5" title="Single live fetus, BPD, FL, AC, Liquor, Placenta, EDD">
                    <i class="fa-solid fa-baby text-amber-600"></i> Pregnancy (Obs)
                </button>
                <button type="button" onclick="applyUltrasoundReportType('us_anomaly')" class="px-2.5 py-1 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs transition-colors flex items-center gap-1.5" title="2nd Trimester complete fetal anatomical survey">
                    <i class="fa-solid fa-magnifying-glass-chart text-indigo-600"></i> Anomaly Scan
                </button>
                <button type="button" onclick="applyUltrasoundReportType('us_scrotal')" class="px-2.5 py-1 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs transition-colors flex items-center gap-1.5" title="Testes, Epididymides, Pampiniform plexus, Color Doppler">
                    <i class="fa-solid fa-heart-pulse text-rose-600"></i> Scrotal Doppler
                </button>
                <button type="button" onclick="applyUltrasoundReportType('us_thyroid')" class="px-2.5 py-1 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs transition-colors flex items-center gap-1.5" title="Bilateral thyroid lobes, Isthmus, Cervical nodes">
                    <i class="fa-solid fa-shield-halved text-cyan-700"></i> Thyroid &amp; Neck
                </button>
                <button type="button" onclick="applyUltrasoundReportType('us_breast')" class="px-2.5 py-1 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs transition-colors flex items-center gap-1.5" title="Bilateral breast parenchyma & BI-RADS Category">
                    <i class="fa-solid fa-ribbon text-pink-500"></i> Breast (BI-RADS)
                </button>
                <button type="button" onclick="applyUltrasoundReportType('us_tvs')" class="px-2.5 py-1 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs transition-colors flex items-center gap-1.5" title="Transvaginal Sonography (TVS)">
                    <i class="fa-solid fa-microscope text-purple-600"></i> TVS Scan
                </button>
                <button type="button" onclick="applyUltrasoundReportType('xray_chest')" class="px-2.5 py-1 rounded-lg bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-semibold shadow-2xs transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-x-ray text-slate-600"></i> Chest X-Ray
                </button>
                <button type="button" onclick="applyUltrasoundReportType('ecg_12')" class="px-2.5 py-1 rounded-lg bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-semibold shadow-2xs transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-wave-square text-emerald-600"></i> 12-Lead ECG
                </button>
                <button type="button" onclick="clearImagingBox()" class="px-2.5 py-1 rounded-lg bg-white hover:bg-rose-50 text-slate-500 hover:text-rose-700 border border-slate-200 text-xs font-semibold transition-colors ml-auto">
                    <i class="fa-solid fa-eraser mr-1"></i> Clear Text
                </button>
            </div>
        </div>

        <div class="p-5 bg-white space-y-3">
            <input type="hidden" name="results[{$imagingResId}][id]" value="{$imagingResId}">
            <input type="hidden" name="results[{$imagingResId}][show]" value="1">
            <input type="hidden" name="results[{$imagingResId}][unit]" value="">
            <input type="hidden" name="results[{$imagingResId}][range]" value="">
            <input type="hidden" name="results[{$imagingResId}][flag]" value="">

            <div class="flex items-center justify-between text-xs text-slate-500 px-1">
                <span class="font-bold text-slate-700 flex items-center gap-1.5">
                    <i class="fa-solid fa-feather text-teal-600"></i> Report Body &amp; Clinical Findings (Full Page Narrative):
                </span>
                <span id="active-study-indicator" class="text-xs font-semibold text-teal-700 bg-teal-50 px-2 py-0.5 rounded border border-teal-200">
                    Ultrasound Narrative Mode
                </span>
            </div>

            <textarea id="imaging-narrative-textarea" name="results[{$imagingResId}][value]" rows="20" class="field w-full font-mono text-sm leading-relaxed p-4 border-2 border-teal-300 rounded-2xl shadow-inner focus:border-teal-500 focus:ring-2 focus:ring-teal-200" placeholder="Type or paste the full clinical narrative report here...&#10;&#10;e.g.&#10;LIVER: Normal in size and echotexture...&#10;GALLBLADDER: Normal lumen, no calculus...&#10;&#10;IMPRESSION: Normal study.">{$currentImagingValSafe}</textarea>
            <p class="text-[11px] text-slate-500 flex items-center justify-between">
                <span><i class="fa-solid fa-circle-info text-teal-600 mr-1"></i> This narrative prints directly as a full-page clean report under <strong>{$activeTestLabel}</strong> without reference columns.</span>
                <span class="text-slate-400">Doctors can edit organs, dimensions, and conclusion before saving.</span>
            </p>
        </div>
HTML;
    } else {
        $testContentHtml = <<<HTML
        <div class="px-5 py-3 bg-blue-50/60 border-b border-blue-100 flex flex-wrap items-center justify-between gap-2">
            <strong class="text-blue-950 text-sm tracking-wide font-extrabold">{$activeTestLabel}</strong>
            <span class="text-xs text-blue-800 font-semibold">Untick <em>Show</em> to hide a line · Flag is auto-calculated</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm results-entry-table">
                <thead class="bg-slate-800 text-white text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-3 py-2.5 text-left" style="width: 47%;">Parameter</th>
                        <th class="px-2.5 py-2.5 text-right" style="width: 20%;">Reference Range</th>
                        <th class="px-2.5 py-2.5 text-right" style="width: 15%;">Unit</th>
                        <th class="px-2.5 py-2.5 text-right" style="width: 13%;">Result</th>
                        <th class="px-2.5 py-2.5 text-center" style="width: 5%;">Flag</th>
                    </tr>
                </thead>
                <tbody>
                    {$tableBody}
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 p-4 bg-slate-50/70 space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-800">
                        <i class="fa-solid fa-microscope text-teal-700 mr-1.5"></i>
                        Test Methodology &amp; Clinical Notes ({$activeTestLabel})
                    </label>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        Belongs to the <strong>{$activeTestLabel}</strong> test entity in the test catalog. Automatically prints as a clinical narrative box under this test on the patient report.
                    </p>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-teal-100 text-teal-800">
                    tests.methodology
                </span>
            </div>
            <textarea name="test_methodology" rows="5" class="field w-full font-mono text-xs leading-relaxed" placeholder="Enter methodology paragraphs, kit details, principle, interpretation, comments... (e.g. Methodologies: ..., Comments: ..., Interpretation: ...)">{$activeTestMethodologySafe}</textarea>
            <input type="hidden" name="active_test_id" value="{$activeTestIdSafe}">
        </div>
HTML;
    }

    $content .= <<<HTML
    <div class="bg-white border border-slate-200/90 rounded-3xl shadow-card overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50/70 p-5">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-xs font-medium">
                <div><span class="block text-[10px] uppercase text-slate-400 font-extrabold tracking-wider">Lab No</span><strong class="font-mono text-base font-black text-slate-900">{$labNoSafe}</strong></div>
                <div><span class="block text-[10px] uppercase text-slate-400 font-extrabold tracking-wider">MR No</span><strong class="font-mono text-sm font-bold text-slate-800">{$mrNo}</strong></div>
                <div><span class="block text-[10px] uppercase text-slate-400 font-extrabold tracking-wider">Patient</span><strong class="text-sm font-bold text-slate-900">{$patientName}</strong></div>
                <div><span class="block text-[10px] uppercase text-slate-400 font-extrabold tracking-wider">Age / Sex</span><strong class="text-slate-800">{$age} yrs / {$gender}</strong></div>
                <div><span class="block text-[10px] uppercase text-slate-400 font-extrabold tracking-wider">Phone</span><span class="text-slate-700">{$phone}</span></div>
                <div><span class="block text-[10px] uppercase text-slate-400 font-extrabold tracking-wider">Registered</span><span class="text-slate-700">{$regAt}</span></div>
                <div class="sm:col-span-2"><span class="block text-[10px] uppercase text-slate-400 font-extrabold tracking-wider">Referring doctor</span><span class="text-slate-800 font-semibold">{$doctor}</span></div>
            </div>
        </div>

        {$testContentHtml}

        <div class="border-t border-slate-200 p-4 space-y-2">
            <label class="block text-xs font-bold uppercase tracking-wide text-rose-700">Notes for report</label>
            <textarea name="clinical_notes" rows="3" class="field w-full text-sm" placeholder="Optional note for the doctor (e.g. correlate with clinical picture)…">{$notes}</textarea>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-4 py-3 flex flex-wrap gap-2 justify-between">
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk mr-1"></i> Save Results</button>
                <button type="submit" name="send_verify" value="1" class="btn btn-secondary"><i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> Save &amp; Verify</button>
            </div>
            <button type="submit" name="goto_preview" value="1" class="btn btn-secondary"><i class="fa-solid fa-print mr-1"></i> Save &amp; Print Report</button>
        </div>
    </div>
</div>
</form>
HTML;

$content .= '<script>window.__LAB_SEARCH_VISITS__ = ' . $searchVisitsJson . ';</script>';
$content .= <<<'SCRIPT'
<script>
(function() {
    const visits = window.__LAB_SEARCH_VISITS__ || [];
    const container = document.getElementById('patient-search-container');
    const input = document.getElementById('patient-search-input');
    const dropdown = document.getElementById('patient-search-dropdown');
    const list = document.getElementById('patient-search-list');
    const header = document.getElementById('patient-search-header');
    const clearBtn = document.getElementById('patient-search-clear');
    
    if (!container || !input || !dropdown || !list) return;

    let selectedIndex = -1;
    let currentFiltered = [];

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function highlightMatch(text, query) {
        if (!text) return '';
        const safe = escapeHtml(text);
        if (!query) return safe;
        const qSafe = escapeRegex(query.trim());
        if (!qSafe) return safe;
        const reg = new RegExp('(' + qSafe + ')', 'gi');
        return safe.replace(reg, '<mark class="bg-amber-200 text-amber-950 font-bold px-0.5 rounded">$1</mark>');
    }

    function statusBadge(status) {
        const s = (status || '').toLowerCase();
        if (s === 'verified' || s === 'completed') {
            return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
        }
        if (s === 'in-progress' || s === 'partial') {
            return 'bg-blue-100 text-blue-800 border border-blue-200';
        }
        if (s === 'sample-collected') {
            return 'bg-purple-100 text-purple-800 border border-purple-200';
        }
        return 'bg-amber-100 text-amber-800 border border-amber-200';
    }

    function renderResults(query) {
        const q = (query || '').trim().toLowerCase();
        if (clearBtn) {
            clearBtn.classList.toggle('hidden', q === '');
        }

        if (q === '') {
            currentFiltered = visits.slice(0, 10);
            if (header) header.textContent = 'Recent Patient Visits (' + currentFiltered.length + ')';
        } else {
            currentFiltered = visits.filter(v => {
                const lab = (v.lab_no || '').toLowerCase();
                const mr = (v.mr_no || '').toLowerCase();
                const name = (v.patient_name || '').toLowerCase();
                return lab.includes(q) || mr.includes(q) || name.includes(q);
            });
            if (header) {
                header.textContent = currentFiltered.length > 0 
                    ? 'Found ' + currentFiltered.length + ' matching patient' + (currentFiltered.length === 1 ? '' : 's')
                    : 'No matching patients';
            }
        }

        selectedIndex = -1;

        if (currentFiltered.length === 0) {
            list.innerHTML = `
                <div class="p-6 text-center text-slate-500">
                    <i class="fa-solid fa-magnifying-glass text-2xl text-slate-300 mb-2 block"></i>
                    <div class="text-sm font-semibold text-slate-700">No matching patients found</div>
                    <p class="text-xs text-slate-400 mt-1">
                        No match for "<span class="font-mono font-medium text-slate-600">${escapeHtml(query)}</span>" on Lab No, MR No, or Patient Name.
                    </p>
                </div>
            `;
            return;
        }

        let html = '';
        currentFiltered.forEach((item, idx) => {
            const isCurrent = item.is_current;
            html += `
                <div 
                    class="patient-search-item px-3.5 py-2.5 cursor-pointer transition-colors flex items-center justify-between gap-3 text-left ${isCurrent ? 'bg-teal-50/40' : 'hover:bg-slate-50'}"
                    data-index="${idx}"
                    data-lab-no="${escapeHtml(item.lab_no)}"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-slate-900 text-sm item-patient-name">${highlightMatch(item.patient_name, q)}</span>
                            ${isCurrent ? '<span class="text-[9px] uppercase font-bold tracking-wider px-1.5 py-0.5 bg-teal-100 text-teal-800 rounded">Current</span>' : ''}
                            <span class="text-xs text-slate-400 font-normal">
                                ${item.age ? item.age + ' yrs' : ''} ${item.gender ? '· ' + item.gender : ''}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 mt-1 flex-wrap text-xs text-slate-600">
                            <span class="inline-flex items-center font-mono font-bold text-teal-800 bg-teal-50 border border-teal-200 px-1.5 py-0.5 rounded text-[11px]">
                                <i class="fa-solid fa-flask-vial mr-1 text-[10px] text-teal-600"></i>${highlightMatch(item.lab_no, q)}
                            </span>
                            ${item.mr_no ? `
                                <span class="inline-flex items-center font-mono text-slate-700 bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded text-[11px]">
                                    <span class="text-slate-400 mr-1 text-[9px] font-sans font-bold uppercase">MR</span>${highlightMatch(item.mr_no, q)}
                                </span>
                            ` : ''}
                            <span class="text-slate-500 truncate max-w-xs text-[11px]" title="${escapeHtml(item.tests)}">
                                <i class="fa-solid fa-vial text-slate-400 mr-1"></i>${escapeHtml(item.tests)}
                            </span>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full uppercase tracking-wider ${statusBadge(item.status)}">
                            ${escapeHtml(item.status)}
                        </span>
                        <div class="text-[10px] text-slate-400 mt-1">${escapeHtml(item.date || '')}</div>
                    </div>
                </div>
            `;
        });
        list.innerHTML = html;

        list.querySelectorAll('.patient-search-item').forEach(el => {
            el.addEventListener('click', () => {
                const lab = el.getAttribute('data-lab-no');
                if (lab) navigateToPatient(lab);
            });
            el.addEventListener('mouseenter', () => {
                const idx = parseInt(el.getAttribute('data-index'), 10);
                setSelectedIndex(idx, false);
            });
        });
    }

    function setSelectedIndex(idx, scrollTo) {
        const items = list.querySelectorAll('.patient-search-item');
        items.forEach(el => el.classList.remove('bg-teal-100/70', 'ring-1', 'ring-teal-400'));
        selectedIndex = idx;
        if (selectedIndex >= 0 && selectedIndex < items.length) {
            const item = items[selectedIndex];
            item.classList.add('bg-teal-100/70', 'ring-1', 'ring-teal-400');
            if (scrollTo) {
                item.scrollIntoView({ block: 'nearest' });
            }
        }
    }

    function navigateToPatient(labNo) {
        if (!labNo) return;
        window.location.href = '/portals/main-lab/results/entry.php?lab_no=' + encodeURIComponent(labNo);
    }

    function showDropdown() {
        renderResults(input.value);
        dropdown.classList.remove('hidden');
    }

    function hideDropdown() {
        dropdown.classList.add('hidden');
        selectedIndex = -1;
    }

    input.addEventListener('focus', showDropdown);
    input.addEventListener('input', () => {
        showDropdown();
    });

    input.addEventListener('keydown', (e) => {
        if (dropdown.classList.contains('hidden')) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                showDropdown();
                e.preventDefault();
                return;
            }
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const count = currentFiltered.length;
            if (count > 0) {
                const next = selectedIndex < count - 1 ? selectedIndex + 1 : 0;
                setSelectedIndex(next, true);
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const count = currentFiltered.length;
            if (count > 0) {
                const prev = selectedIndex > 0 ? selectedIndex - 1 : count - 1;
                setSelectedIndex(prev, true);
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && currentFiltered[selectedIndex]) {
                navigateToPatient(currentFiltered[selectedIndex].lab_no);
            } else if (currentFiltered.length > 0) {
                navigateToPatient(currentFiltered[0].lab_no);
            }
        } else if (e.key === 'Escape') {
            hideDropdown();
            input.blur();
        }
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            input.value = '';
            input.focus();
            showDropdown();
        });
    }

    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) {
            hideDropdown();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
            e.preventDefault();
            input.focus();
            input.select();
        }
    });
})();
</script>
SCRIPT;

$content .= '<script>';
$content .= 'window.__IMAGING_TEMPLATES_MAP__ = ' . $tplJsonMapEncoded . ';';
$content .= <<<'JSSCRIPT'
window.__IMAGING_PRESETS__ = {
    us_whole_abd: "EXAMINATION: ULTRASOUND WHOLE ABDOMEN & PELVIS\n\nLIVER:\nNormal in size, smooth contour and uniform parenchymal echotexture. No focal solid or cystic mass lesion seen. Intrahepatic biliary tree and portal vein are normal in caliber.\n\nGALLBLADDER:\nNormal in size and shape. Wall thickness is normal (< 3 mm). Acoustic lumen is completely clear; no calculus, sludge or mass lesion seen. Common bile duct (CBD) is normal.\n\nPANCREAS:\nVisualized head, body and tail appear normal in size and echogenicity. Main pancreatic duct is not dilated.\n\nSPLEEN:\nNormal in size and parenchymal echotexture. No splenomegaly or focal lesion noted.\n\nKIDNEYS:\nBoth kidneys are normal in size, shape and cortical thickness. Corticomedullary differentiation is well preserved. No calculus or hydronephrosis seen bilaterally.\n\nURINARY BLADDER:\nWell distended with thin, smooth walls and clear lumen. No intraluminal calculus or mass.\n\nPELVIS / PROSTATE:\nVisualized pelvic structures are unremarkable. No free fluid in abdomen or pelvis.\n\nIMPRESSION:\nNORMAL ULTRASOUND EXAMINATION OF WHOLE ABDOMEN & PELVIS.",

    us_upper_abd: "EXAMINATION: ULTRASOUND UPPER ABDOMEN\n\nLIVER:\nNormal in size, shape and parenchymal echotexture. No focal mass, abscess, or nodule seen. Intrahepatic ducts are not dilated. Portal vein is normal in caliber.\n\nGALLBLADDER:\nNormal lumen without gallstones, sludge, or wall thickening. Common bile duct (CBD) measures within normal limits.\n\nPANCREAS:\nVisualized portions appear normal in size and echogenicity without mass or calcification.\n\nSPLEEN:\nNormal in size with homogenous parenchymal echotexture. No focal lesion or splenomegaly.\n\nKIDNEYS:\nBoth kidneys are normal in size, shape, and cortical thickness. No calculus or hydronephrosis seen.\n\nIMPRESSION:\nNORMAL ULTRASOUND EXAMINATION OF UPPER ABDOMEN.",

    us_kub: "EXAMINATION: ULTRASOUND KUB & PROSTATE\n\nRIGHT KIDNEY:\nMeasures normal in size and anatomical position. Corticomedullary differentiation is well preserved. Normal parenchymal thickness. No evidence of renal calculus, hydronephrosis, or focal lesion.\n\nLEFT KIDNEY:\nMeasures normal in size and anatomical position. Corticomedullary differentiation is well preserved. Normal parenchymal thickness. No evidence of renal calculus, hydronephrosis, or focal lesion.\n\nURETERS:\nBoth ureters are not dilated; no obstructive ureteric calculus visualized.\n\nURINARY BLADDER:\nAdequately distended with clear acoustic lumen. Bladder wall thickness is smooth and uniform (< 3 mm). No intraluminal calculus, mass, or diverticulum seen.\n- Pre-void volume: Adequate.\n- Post-void residual (PVR): Insignificant (< 20 ml).\n\nPROSTATE:\nNormal in size, shape, and echotexture with smooth capsule. Estimated volume is within normal age-related limits. Median lobe is not enlarged.\n\nIMPRESSION:\nNORMAL ULTRASOUND KUB & PROSTATE. NO EVIDENCE OF NEPHROLITHIASIS OR URINARY TRACT OBSTRUCTION.",

    us_pelvis: "EXAMINATION: ULTRASOUND PELVIS (FEMALE / GYNAECOLOGICAL)\n\nURINARY BLADDER:\nWell distended with clear lumen and smooth walls.\n\nUTERUS:\n- Position: Anteverted.\n- Size & Contour: Normal in size and regular in contour.\n- Myometrium: Homogeneous echotexture. No focal leiomyoma (fibroid), adenomyosis, or localized mass lesion seen.\n- Endometrium: Central, regular, and well-defined endometrial stripe measuring within normal physiological limits for cycle phase.\n\nCERVIX:\nNormal in appearance. No Nabothian cyst or mass identified.\n\nRIGHT OVARY:\nVisualized and measures normal in size with normal follicular pattern. No cystic or solid mass lesion seen.\n\nLEFT OVARY:\nVisualized and measures normal in size with normal follicular pattern. No cystic or solid mass lesion seen.\n\nADNEXAE & POUCH OF DOUGLAS (POD):\nBoth adnexal regions are free. No adnexal mass, localized collection, or free fluid in the Pouch of Douglas.\n\nIMPRESSION:\nNORMAL PELVIC ULTRASOUND EXAMINATION. NORMAL APPEARANCE OF UTERUS AND BOTH OVARIES.",

    us_obstetric: "EXAMINATION: OBSTETRIC ULTRASOUND SCAN (ANTENATAL)\n\nFETAL VIABILITY & NUMBER:\n- Number of fetuses: Single live intrauterine fetus.\n- Cardiac Activity: Present and regular (Fetal Heart Rate: ~144 bpm).\n- Fetal Movements: Active somatic and limb movements noted.\n- Presentation: Cephalic / Vertex.\n\nFETAL BIOMETRY:\n- Biparietal Diameter (BPD): Normal for gestational age.\n- Femur Length (FL): Normal for gestational age.\n- Abdominal Circumference (AC): Normal for gestational age.\n- Head Circumference (HC): Normal for gestational age.\n- Average Gestational Age (EGA): Consistent with dates.\n- Expected Date of Delivery (EDD): Consistent with biometry.\n\nAMNIOTIC FLUID (LIQUOR):\nAdequate in quantity. Amniotic fluid index (AFI) is within normal limits.\n\nPLACENTA:\n- Position: Anterior / Fundal, Grade I maturity.\n- Relation to Os: Lower margin is well clear of the internal cervical os. No retroplacental hematoma seen.\n\nIMPRESSION:\nSINGLE LIVE INTRAUTERINE PREGNANCY CORRESPONDING TO GESTATIONAL AGE WITH ACTIVE FETAL MOVEMENTS AND NORMAL LIQUOR / PLACENTAL POSITION.",

    us_anomaly: "EXAMINATION: TARGETED FETAL ANOMALY SCAN (2ND TRIMESTER)\n\nFETAL BIOMETRY & VIABILITY:\n- Single live fetus in cephalic presentation.\n- Normal regular fetal cardiac activity and somatic movements.\n- Fetal biometry (BPD, HC, AC, FL) corresponds to clinical gestational age.\n\nANATOMICAL SURVEY:\n- Head & Brain: Intact skull contour, symmetrical lateral ventricles, cavum septum pellucidum visualized, normal posterior fossa & cerebellum.\n- Face: Both orbits, lens, nasal bone, and intact upper lip visualized.\n- Spine: Complete and intact vertebrae from cervical to sacral region in sagittal and transverse planes.\n- Thorax & Heart: Intact diaphragm, normal 4-chamber cardiac view, normal outflow tracts (LVOT/RVOT), and normal cardiac rhythm.\n- Abdomen: Intact anterior abdominal wall, normal stomach bubble, intact cord insertion. Both kidneys and bladder visualized.\n- Limbs: Bilateral upper and lower long bones, hands, and feet visualized and appear normal.\n- Amniotic Fluid: Adequate in volume.\n- Placenta: Normal position and echotexture, clear of internal os. 3-vessel umbilical cord identified.\n\nIMPRESSION:\nTARGETED ANOMALY SCAN REVEALS NO GROSS CONGENITAL OR STRUCTURAL ANOMALY DETECTED AT PRESENT GESTATIONAL AGE.",

    us_tvs: "EXAMINATION: TRANSVAGINAL ULTRASOUND SCAN (TVS)\n\nUTERUS:\n- Position: Anteverted.\n- Myometrium: Homogeneous echotexture throughout. No focal intramural, subserosal, or submucosal fibroid.\n- Endometrium: Distinct trilaminar / secretory endometrial stripe measuring normal in thickness. Cavity is empty with regular margins.\n- Cervix: Normal length and internal os closed.\n\nRIGHT OVARY:\nNormal in volume and location. Follicles are peripheral and physiological in size. No complex ovarian mass.\n\nLEFT OVARY:\nNormal in volume and location. Follicles are peripheral and physiological in size. No complex ovarian mass.\n\nPOUCH OF DOUGLAS:\nFree of fluid, localized collection, or endometriotic deposits.\n\nIMPRESSION:\nNORMAL TRANSVAGINAL ULTRASOUND STUDY (TVS). UNREMARKABLE PELVIC ORGANS.",

    us_scrotal: "EXAMINATION: ULTRASOUND SCROTUM & COLOR DOPPLER\n\nRIGHT TESTIS:\nNormal in size, shape, and homogenous echotexture. Normal intratesticular vascular flow demonstrated on Color and Spectral Doppler. No focal intratesticular mass or calcification.\n- Right Epididymis: Normal size and echogenicity.\n- Tunica Vaginalis: No hydrocele.\n\nLEFT TESTIS:\nNormal in size, shape, and homogenous echotexture. Normal intratesticular vascular flow demonstrated on Color and Spectral Doppler. No focal intratesticular mass or calcification.\n- Left Epididymis: Normal size and echogenicity.\n- Tunica Vaginalis: No hydrocele.\n\nPAMPINIFORM PLEXUS:\nVenous channels demonstrate normal diameter bilaterally (< 2.0 mm). No retrograde venous reflux elicited during Valsalva maneuver. No evidence of varicocele bilaterally.\n\nIMPRESSION:\nNORMAL SCROTAL AND TESTICULAR COLOR DOPPLER STUDY. NO EVIDENCE OF EPIDIDYMO-ORCHITIS, HYDROCELE, OR VARICOCELE.",

    us_thyroid: "EXAMINATION: ULTRASOUND THYROID & NECK\n\nRIGHT THYROID LOBE:\nNormal in dimensions and parenchymal echogenicity. No focal solid nodule, cystic lesion, or calcification identified. Normal vascularity on color Doppler.\n\nLEFT THYROID LOBE:\nNormal in dimensions and parenchymal echogenicity. No focal solid nodule, cystic lesion, or calcification identified. Normal vascularity on color Doppler.\n\nISTHMUS:\nNormal in thickness (< 3 mm) and uniform in texture.\n\nCERVICAL LYMPH NODES:\nNo pathologically enlarged or suspicious cervical lymph nodes identified bilaterally.\n\nIMPRESSION:\nNORMAL ULTRASOUND EXAMINATION OF THYROID GLAND. NO EVIDENCE OF GOITRE, NODULE, OR CYSTIC LESION.",

    us_breast: "EXAMINATION: BILATERAL BREAST ULTRASOUND (SONOMAMMOGRAPHY)\n\nRIGHT BREAST:\nNormal fibroglandular parenchyma with normal ductal pattern. No focal solid mass lesion, cyst, or architectural distortion seen. Retroareolar ducts are not dilated.\n\nLEFT BREAST:\nNormal fibroglandular parenchyma with normal ductal pattern. No focal solid mass lesion, cyst, or architectural distortion seen. Retroareolar ducts are not dilated.\n\nAXILLARY REGIONS:\nVisualized bilateral axillary lymph nodes show normal preserved fatty hilum with benign sonographic morphology. No suspicious lymphadenopathy.\n\nACR BI-RADS ASSESSMENT: CATEGORY 1 (NEGATIVE / NORMAL STUDY).\n\nIMPRESSION:\nNORMAL BILATERAL BREAST ULTRASOUND (BI-RADS CATEGORY 1). NO SONOGRAPHIC EVIDENCE OF SOLID OR CYSTIC LESION.",

    us_follicular: "EXAMINATION: ULTRASOUND FOLLICULAR STUDY (FOLLICULOMETRY)\n\nDAY OF CYCLE: Day _____\n\nENDOMETRIUM:\n- Thickness: _____ mm.\n- Echopattern: Triple-line pattern (Trilaminar) / Secretory.\n\nRIGHT OVARY:\n- Dominant Follicle: _____ x _____ mm.\n- Secondary Follicles: Multiple small developing follicles.\n\nLEFT OVARY:\n- Dominant Follicle: _____ x _____ mm.\n- Secondary Follicles: Multiple small developing follicles.\n\nFREE FLUID IN POUCH OF DOUGLAS:\nNone / Minimal physiological fluid.\n\nIMPRESSION:\nFOLLICULAR STUDY SHOWING MATURING DOMINANT FOLLICLE AS NOTED ABOVE.",

    us_soft_tissue: "EXAMINATION: ULTRASOUND SOFT TISSUE / SWELLING\n\nSITE OF EXAMINATION: ____________________\n\nFINDINGS:\n- High-frequency sonographic examination of the region demonstrates normal subcutaneous tissue and muscular planes.\n- No localized cystic lesion, solid mass, abscess, or hematoma identified.\n- Overlying skin and deep fascia appear intact.\n- Color Doppler shows normal physiological vascularity without hypervascularity.\n\nIMPRESSION:\nNORMAL ULTRASOUND EVALUATION OF SOFT TISSUES. NO FOCAL MASS LESION OR FLUID COLLECTION NOTED.",

    xray_chest: "EXAMINATION: CHEST X-RAY PA VIEW\n\nCHEST PA VIEW FINDINGS:\n- Lung fields: Clear with normal bronchovascular markings. No focal airspace consolidation, mass, or cavity noted.\n- Costophrenic & cardiophrenic angles: Sharp and clear bilaterally.\n- Cardiac silhouette: Normal in size and contour. Cardiothoracic ratio is within normal limits (< 50%).\n- Hilar structures & mediastinum: Normal appearance.\n- Bony cage & soft tissues: Intact.\n\nIMPRESSION: Normal radiological study of chest.",

    ecg_12: "EXAMINATION: 12-LEAD ELECTROCARDIOGRAM (ECG)\n\n12-LEAD ECG FINDINGS:\n- Rhythm: Normal Sinus Rhythm\n- Heart Rate: 72 bpm\n- PR Interval: 0.16 sec (Normal: 0.12 - 0.20s)\n- QRS Duration: 0.08 sec (Normal: < 0.10s)\n- QTc: 410 ms\n- Axis: Normal cardiac axis (+45°)\n- ST-T wave changes: No significant ST elevation/depression or T wave inversion.\n\nIMPRESSION: Normal 12-lead Electrocardiogram (ECG)."
};

// Aliases for backwards compatibility
window.__IMAGING_PRESETS__.us_abd = window.__IMAGING_PRESETS__.us_whole_abd;

function applyUltrasoundReportType(key) {
    const ta = document.getElementById('imaging-narrative-textarea');
    const select = document.getElementById('us-report-type-select');
    const indicator = document.getElementById('active-study-indicator');
    if (!ta) return;

    if (select && select.value !== key) {
        select.value = key;
    }

    const text = window.__IMAGING_PRESETS__[key];
    if (!text) return;

    if (ta.value.trim() && !confirm('Replace current report content with the selected Ultrasound Report Type template?')) {
        return;
    }

    ta.value = text;
    if (indicator) {
        const selectedOpt = select ? select.options[select.selectedIndex] : null;
        const label = selectedOpt ? selectedOpt.text : 'Ultrasound';
        indicator.textContent = label + ' Loaded';
    }
    ta.focus();
}

function insertTplFromSelect() {
    const picker = document.getElementById('tpl-picker');
    const ta = document.getElementById('imaging-narrative-textarea');
    if (!picker || !ta) return;
    const val = picker.value;
    if (!val || !window.__IMAGING_TEMPLATES_MAP__ || !window.__IMAGING_TEMPLATES_MAP__[val]) return;
    if (ta.value.trim() && !confirm('Replace current report content with the selected template?')) {
        return;
    }
    ta.value = window.__IMAGING_TEMPLATES_MAP__[val];
    ta.focus();
}

function loadImagingPreset(key) {
    applyUltrasoundReportType(key);
}

function clearImagingBox() {
    const ta = document.getElementById('imaging-narrative-textarea');
    if (!ta) return;
    if (confirm('Clear report narrative text?')) {
        ta.value = '';
        ta.focus();
    }
}
JSSCRIPT;
$content .= '</script>';

$portal = current_portal();
$activeKey = ($portal === 'imaging') ? 'station-lab' : 'results-entry';
render_page('Enter Results', $portal, $activeKey, $content);
