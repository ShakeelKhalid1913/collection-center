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

        if ($flag === '' && $val !== '' && is_numeric($val) && preg_match('/^([0-9\.]+)\s*[\-–]\s*([0-9\.]+)$/', $range, $m)) {
            $numVal = (float)$val;
            $low = (float)$m[1];
            $high = (float)$m[2];
            if ($numVal < $low) {
                $flag = 'L';
            } elseif ($numVal > $high) {
                $flag = 'H';
            }
        }

        $batchData[] = [
            'id' => $rId,
            'value' => $val,
            'unit' => $item['unit'] ?? null,
            'reference_range' => $item['range'] ?? null,
            'flag' => $flag,
            'is_visible' => $isVisible,
        ];
    }

    if (!empty($batchData)) {
        result_repo()->saveResultsBatch($postLabNo, $batchData);
    }

    if ($notes !== '') {
        lab_repo()->updateEntry($postLabNo, ['clinical_notes' => $notes]);
    }

    if (!empty($_POST['send_verify'])) {
        result_repo()->verifyAllByLabNo($postLabNo, current_user()['name'] ?? 'Laboratory Staff');
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
$testsOrdered = e((string)($entry['tests'] ?? ''));
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
    $switcherOpts .= '<option value="' . e($ae['lab_no']) . '"' . $sel . '>'
        . e($ae['lab_no']) . ' — ' . e($ae['patient_name']) . ' (' . e($ae['tests']) . ')</option>';
}

$testSelectOpts = '';
foreach ($testNames as $i => $tn) {
    $sel = $tn === $activeTest ? ' selected' : '';
    $testSelectOpts .= '<option value="' . e($tn) . '"' . $sel . '>' . e($tn) . '</option>';
}

$activeRows = $byTest[$activeTest] ?? [];
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
    $range = e($r['reference_range'] ?: ($r['range'] ?? '—'));
    $flag = strtolower((string)($r['flag'] ?? ''));
    $shown = !isset($r['is_visible']) || (int)$r['is_visible'] === 1;
    $showChecked = $shown ? ' checked' : '';
    $rowDim = $shown ? '' : ' opacity-50';
    $autofocus = $firstInput ? ' autofocus' : '';
    $firstInput = false;

    $valClass = 'field text-sm font-bold w-28 text-center';
    if ($flag === 'l' || $flag === 'h' || $flag === 'critical') {
        $valClass .= ' text-blue-700 border-blue-400';
    }

    $flagOpts = '<option value="">—</option>'
        . '<option value="L"' . ($flag === 'l' ? ' selected' : '') . '>Low</option>'
        . '<option value="H"' . ($flag === 'h' ? ' selected' : '') . '>High</option>'
        . '<option value="critical"' . ($flag === 'critical' ? ' selected' : '') . '>Critical</option>';

    $subHtml = '';
    if (!empty($r['sub_table'])) {
        $subHtml = '<details class="mt-1 text-xs text-slate-500"><summary class="cursor-pointer text-teal-700 font-semibold">Reference table</summary>'
            . '<pre class="mt-1 whitespace-pre-wrap font-mono bg-slate-50 border border-slate-200 rounded p-2">'
            . e((string)$r['sub_table']) . '</pre></details>';
    }

    $tableBody .= <<<HTML
    <tr class="border-b border-slate-100{$rowDim}">
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
        </td>
        <td class="px-3 py-2 text-sm text-slate-600 align-top">{$unit}</td>
        <td class="px-3 py-2 text-sm text-slate-600 font-mono align-top">{$range}</td>
        <td class="px-3 py-2 align-top">
            <input type="text" name="results[{$rId}][value]" value="{$val}" class="{$valClass}" placeholder="—"{$autofocus}>
        </td>
        <td class="px-3 py-2 align-top">
            <select name="results[{$rId}][flag]" class="field text-xs w-24">{$flagOpts}</select>
        </td>
    </tr>
    HTML;
}

if ($tableBody === '') {
    $tableBody = '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No parameters for this test yet. Add the test from the catalog, or pick another test on the left.</td></tr>';
}

$prevBtn = $prevTest
    ? '<a class="btn btn-secondary text-xs flex-1" href="' . e($baseUrl . '&test=' . urlencode($prevTest)) . '">← Previous</a>'
    : '<span class="btn btn-secondary text-xs flex-1 opacity-40 pointer-events-none">← Previous</span>';
$nextBtn = $nextTest
    ? '<a class="btn btn-secondary text-xs flex-1" href="' . e($baseUrl . '&test=' . urlencode($nextTest)) . '">Next →</a>'
    : '<span class="btn btn-secondary text-xs flex-1 opacity-40 pointer-events-none">Next →</span>';

$activeTestLabel = e($activeTest);
$posLabel = $testCount > 0 ? (($testIndex + 1) . ' of ' . $testCount) : '0 of 0';
$baseUrlJs = e($baseUrl);

$content = page_header(
    'Enter Results',
    'Type values like the printed report. Tick Show to include a row on the report.'
);
$content .= $message;

$content .= <<<HTML
<form method="post" id="results-workspace">
<input type="hidden" name="lab_no" value="{$labNoSafe}">
<input type="hidden" name="active_test" value="{$activeTestLabel}">

<div class="mb-4 flex flex-wrap items-center gap-3 p-3 bg-white border border-slate-200 rounded-lg">
    <label class="text-sm font-semibold text-slate-700">Open patient visit:</label>
    <select class="field text-sm max-w-xl" onchange="if(this.value) location.href='/portals/main-lab/results/entry.php?lab_no='+encodeURIComponent(this.value)">
        {$switcherOpts}
    </select>
    <a href="{$historyLink}" class="text-sm font-semibold text-teal-700 hover:underline ml-auto">Patient history</a>
</div>

<div class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)] items-start">
    <aside class="space-y-3 lg:sticky lg:top-4">
        <div class="bg-white border border-slate-200 rounded-lg p-3 space-y-2">
            <button type="submit" class="btn btn-primary w-full justify-center"><i class="fa-solid fa-floppy-disk mr-1"></i> Save Results</button>
            <button type="submit" name="send_verify" value="1" class="btn btn-secondary w-full justify-center"><i class="fa-solid fa-circle-check mr-1 text-emerald-600"></i> Save &amp; Verify</button>
            <a href="{$previewLink}" class="btn btn-secondary w-full justify-center"><i class="fa-solid fa-file-pdf mr-1"></i> View / Print Report</a>
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
            <p class="text-xs text-slate-500 leading-relaxed">Each booked test (CBC, sugar, etc.) has its own parameter sheet. Move through them here — same patient, same lab number.</p>
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
            <span class="text-xs text-indigo-800">Untick <em>Show</em> to hide a line on the printed report</span>
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

        <div class="border-t border-slate-200 p-4 space-y-2">
            <label class="block text-xs font-bold uppercase tracking-wide text-rose-700">Notes for report</label>
            <textarea name="clinical_notes" rows="4" class="field w-full text-sm" placeholder="Optional note for the doctor (e.g. correlate with clinical picture)…">{$notes}</textarea>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-4 py-3 flex flex-wrap gap-2 justify-between">
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk mr-1"></i> Save Results</button>
                <button type="submit" name="send_verify" value="1" class="btn btn-secondary"><i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> Save &amp; Verify</button>
            </div>
            <a href="{$previewLink}" class="btn btn-secondary"><i class="fa-solid fa-print mr-1"></i> Print Report</a>
        </div>
    </div>
</div>
</form>
HTML;

render_page('Enter Results', 'main-lab', 'results-entry', $content);
