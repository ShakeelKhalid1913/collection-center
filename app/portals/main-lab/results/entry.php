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
    $postLabNo = trim($_POST['lab_no']);
    $activeTest = trim((string)($_POST['active_test'] ?? $activeTest));
    $resItems = $_POST['results'] ?? [];
    $notes = trim((string)($_POST['clinical_notes'] ?? ''));

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

        $batchData[] = [
            'id' => $rId,
            'value' => $val,
            'unit' => $item['unit'] ?? null,
            'reference_range' => $item['range'] ?? null,
            'flag' => $flag,
            'is_visible' => $isVisible,
            'sub_table' => $subTable,
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
    render_page('Enter Results', 'main-lab', 'results-entry', $content);
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
$regAt = e(format_date($entry['created_at'] ?? null));
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

// Patient switcher (other lab numbers)
$allEntries = lab_repo()->getAll();
$switcherOpts = '';
foreach ($allEntries as $ae) {
    $sel = $ae['lab_no'] === $labNo ? ' selected' : '';
    $testsLabel = normalize_tests_list((string)($ae['tests'] ?? ''));
    $switcherOpts .= '<option value="' . e($ae['lab_no']) . '"' . $sel . '>'
        . e($ae['lab_no']) . ' — ' . e($ae['patient_name']) . ' (' . e($testsLabel) . ')</option>';
}

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
    $hasSub = trim($subValRaw) !== '';
    $subSummaryText = $hasSub ? 'Reference Criteria Table (' . strlen($subValRaw) . ' chars)' : '+ Add Reference Criteria Table';
    $subHtml = '<details class="mt-1 text-xs text-slate-500"' . ($hasSub ? ' open' : '') . '>'
        . '<summary class="cursor-pointer font-medium text-slate-500 hover:text-slate-700 inline-flex items-center gap-1 py-0.5">'
        . '<i class="fa-solid fa-table-list text-[10px]"></i> ' . e($subSummaryText)
        . '</summary>'
        . '<div class="mt-1 p-2 bg-slate-50 rounded border border-slate-200 space-y-1">'
        . '<label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider">Criteria / Sub-table (Criteria: Range):</label>'
        . '<textarea name="results[' . $rId . '][sub_table]" class="field text-xs font-mono w-full rounded border border-slate-300 p-1.5 leading-normal" rows="2" placeholder="e.g. 0-2 yrs: 10-20&#10;>2 yrs: 20-40">' . e($subValRaw) . '</textarea>'
        . '</div>'
        . '</details>';

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
        $valueControl = '<select name="results[' . $rId . '][value]" class="field text-sm w-36 result-value-input" data-result-input' . $autofocus . '>'
            . $optHtml . '</select>';
    } else {
        $valueControl = '<input type="text" name="results[' . $rId . '][value]" value="' . $val . '" class="' . $valClass . '" placeholder="—" data-result-input data-ref-range="' . $range . '"' . $autofocus . '>';
    }

    $tableBody .= <<<HTML
    <tr class="border-b border-slate-100{$rowDim}" data-result-row>
        <td class="px-3 py-2 align-top">
            <label class="inline-flex items-start gap-2 cursor-pointer">
                <input type="checkbox" name="results[{$rId}][show]" value="1" class="mt-1"{$showChecked}>
                <span>
                    <span class="block text-[10px] uppercase text-slate-400 font-semibold">Show</span>
                    <span class="font-semibold text-slate-900 text-sm">{$paramName}</span>
                    {$subHtml}
                </span>
            </label>
            <input type="hidden" name="results[{$rId}][unit]" value="{$unit}">
            <input type="hidden" name="results[{$rId}][range]" value="{$range}">
            <input type="hidden" name="results[{$rId}][flag]" value="{$flag}" data-auto-flag>
        </td>
        <td class="px-3 py-2 text-sm text-slate-600 align-top">{$unit}</td>
        <td class="px-3 py-2 text-sm text-slate-600 font-mono align-top">{$range}</td>
        <td class="px-3 py-2 align-top">{$valueControl}</td>
        <td class="px-3 py-2 align-top text-sm" data-flag-display>{$flagDisplay}</td>
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

$content = page_header(
    'Enter Results',
    'Type values like the printed report. Tick Show to include a row on the report. Enter jumps to the next row.'
);
$content .= $message;

$content .= <<<HTML
<form method="post" id="results-workspace" data-results-entry>
<input type="hidden" name="lab_no" value="{$labNoSafe}">
<input type="hidden" name="active_test" value="{$activeTestLabel}">

<div class="mb-4 flex flex-wrap items-center gap-3 p-3 bg-white border border-slate-200 rounded-lg">
    <label class="text-sm font-semibold text-slate-700">Open patient visit:</label>
    <select class="field text-sm max-w-xl" onchange="if(this.value) location.href='/portals/main-lab/results/entry.php?lab_no='+encodeURIComponent(this.value)">
        {$switcherOpts}
    </select>
    <a href="{$historyLink}" class="text-sm font-semibold text-teal-700 hover:underline ml-auto">Patient history</a>
</div>

<div class="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)] items-start">
    <aside class="space-y-3 lg:sticky lg:top-4">
        <div class="bg-white border border-slate-200 rounded-lg p-3 space-y-2">
            <button type="submit" class="btn btn-primary w-full justify-center"><i class="fa-solid fa-floppy-disk mr-1"></i> Save Results</button>
            <button type="submit" name="send_verify" value="1" class="btn btn-secondary w-full justify-center"><i class="fa-solid fa-circle-check mr-1 text-emerald-600"></i> Save &amp; Verify</button>
            <button type="submit" name="goto_preview" value="1" class="btn btn-secondary w-full justify-center"><i class="fa-solid fa-file-pdf mr-1"></i> Save &amp; View / Print</button>
            <a href="{$addTestLink}" class="btn btn-secondary w-full justify-center"><i class="fa-solid fa-plus mr-1"></i> Add / Change Tests</a>
            <a href="/portals/main-lab/results/pending.php" class="btn btn-secondary w-full justify-center">Back to list</a>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg p-3 space-y-3">
            <div>
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wide">Current test</div>
                <div class="font-bold text-slate-900 text-sm leading-snug mt-0.5">{$activeTestLabel}</div>
                <div class="text-xs text-slate-500 mt-1">{$posLabel} on this visit</div>
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600 block mb-1">Jump to test</label>
                <select class="field text-sm w-full" onchange="if(this.value) location.href='{$baseUrlJs}&amp;test='+encodeURIComponent(this.value)">
                    {$testSelectOpts}
                </select>
            </div>
            <div class="flex gap-2">{$prevBtn}{$nextBtn}</div>
            <p class="text-xs text-slate-500 leading-relaxed">Each booked test has its own parameter sheet. Flags auto-calculate from reference ranges. Press Enter to move down.</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg p-3 space-y-2" data-page-grouping>
            <div class="flex items-center justify-between gap-2">
                <div>
                    <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wide">Page grouping</div>
                    <p class="text-[11px] text-slate-500 mt-0.5">Drag tests onto pages, then click <strong>Save</strong> or <strong>Save &amp; View / Print</strong></p>
                </div>
                <button type="button" class="btn btn-secondary text-xs" data-add-page>+ Add Page</button>
            </div>
            <div class="space-y-2" data-page-board>{$pageBoard}</div>
        </div>

        <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs text-slate-600 space-y-1">
            <div><span class="font-semibold text-slate-800">Status:</span> {$status}</div>
            <div><span class="font-semibold text-slate-800">Doctor:</span> {$doctor}</div>
            <div><span class="font-semibold text-slate-800">All tests:</span> {$testsOrdered}</div>
        </div>
    </aside>

    <div class="bg-white border border-slate-300 rounded-lg shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                <div><span class="block text-[10px] uppercase text-slate-400 font-bold">Lab No</span><strong class="font-mono text-teal-800">{$labNoSafe}</strong></div>
                <div><span class="block text-[10px] uppercase text-slate-400 font-bold">MR No</span><strong class="font-mono">{$mrNo}</strong></div>
                <div><span class="block text-[10px] uppercase text-slate-400 font-bold">Patient</span><strong>{$patientName}</strong></div>
                <div><span class="block text-[10px] uppercase text-slate-400 font-bold">Age / Sex</span><strong>{$age} yrs / {$gender}</strong></div>
                <div><span class="block text-[10px] uppercase text-slate-400 font-bold">Phone</span>{$phone}</div>
                <div><span class="block text-[10px] uppercase text-slate-400 font-bold">Registered</span>{$regAt}</div>
                <div class="sm:col-span-2"><span class="block text-[10px] uppercase text-slate-400 font-bold">Referring doctor</span>{$doctor}</div>
            </div>
        </div>

        <div class="px-4 py-2 bg-indigo-50 border-b border-indigo-100 flex flex-wrap items-center justify-between gap-2">
            <strong class="text-indigo-950 text-sm tracking-wide">{$activeTestLabel}</strong>
            <span class="text-xs text-indigo-800">Untick <em>Show</em> to hide a line · Flag is auto ↑/↓</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-800 text-white text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-3 py-2.5 text-left">Parameter</th>
                        <th class="px-3 py-2.5 text-left">Unit</th>
                        <th class="px-3 py-2.5 text-left">Reference Range</th>
                        <th class="px-3 py-2.5 text-left">Result</th>
                        <th class="px-3 py-2.5 text-left">Flag</th>
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

render_page('Enter Results', 'main-lab', 'results-entry', $content);
