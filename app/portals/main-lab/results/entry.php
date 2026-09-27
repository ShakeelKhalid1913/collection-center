<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';

// Identify lab entry
$labNo = trim((string)($_GET['lab_no'] ?? $_GET['id'] ?? $_POST['lab_no'] ?? ''));

// If an ID was passed, check if it's a result ID or a lab_no
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

// Fallback to first pending lab entry or first entry
if (!$entry) {
    $allEntries = lab_repo()->getAll();
    foreach ($allEntries as $e) {
        if ($e['status'] !== 'verified' && $e['status'] !== 'completed') {
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

// Handle Results Save POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['lab_no'])) {
    $postLabNo = trim($_POST['lab_no']);
    $resItems = $_POST['results'] ?? [];

    $batchData = [];
    foreach ($resItems as $rId => $item) {
        $val = trim((string)($item['value'] ?? ''));
        $flag = trim((string)($item['flag'] ?? ''));
        
        // Auto-flag if flag is empty and numeric value is out of range
        $range = trim((string)($item['range'] ?? ''));
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
        ];
    }

    if (!empty($batchData)) {
        result_repo()->saveResultsBatch($postLabNo, $batchData);
    }

    if (!empty($_POST['send_verify'])) {
        result_repo()->verifyAllByLabNo($postLabNo, current_user()['name'] ?? 'Laboratory Staff');
        header('Location: /portals/main-lab/reports/preview.php?lab_no=' . urlencode($postLabNo));
        exit;
    }

    $message = flash_success('Results saved successfully. Click "Save & Verify Report" when finished to generate the final report.');
    $entry = lab_repo()->findByLabNo($postLabNo);
    $labNo = $postLabNo;
}

if (!$entry) {
    $content = page_header('Results Entry', 'No lab entries found.');
    $content .= card('<p class="p-6 text-slate-600">No lab entries available. Create an entry from Collection Center first.</p>');
    render_page('Results Entry', 'main-lab', 'results-entry', $content);
    exit;
}

// Load patient
$patient = patient_repo()->findById((string)($entry['patient_id'] ?? '')) ?: [];
$mrNo = $patient['patient_no'] ?? ($entry['patient_id'] ?? '—');
$patientName = $entry['patient_name'] ?? '—';
$testsOrdered = $entry['tests'] ?? '—';
$doctor = $entry['doctor'] ?? 'Walk-in / Self';
$status = $entry['status'] ?? 'pending';

// Ensure results are initialized with all parameters (e.g. CBC 13 parameters)
$resultRows = result_repo()->ensureResultsInitialized(
    $labNo,
    $testsOrdered,
    $patientName,
    $orgId
);

// All lab entries for quick switcher
$allEntries = lab_repo()->getAll();
$switcherOpts = '';
foreach ($allEntries as $ae) {
    $sel = $ae['lab_no'] === $labNo ? ' selected' : '';
    $stBadge = strtoupper($ae['status']);
    $switcherOpts .= '<option value="' . e($ae['lab_no']) . '"' . $sel . '>' . e($ae['lab_no']) . ' — ' . e($ae['patient_name']) . ' (' . e($ae['tests']) . ') [' . $stBadge . ']</option>';
}

// Build Result Entry Form Rows Grouped by Section
$tableRows = '';
$currentSection = null;
$firstInput = true;

foreach ($resultRows as $r) {
    $sec = trim((string)($r['section'] ?? ''));
    if ($sec !== '' && $sec !== $currentSection) {
        $currentSection = $sec;
        $tableRows .= '<tr class="bg-indigo-50 border-y border-indigo-200"><td colspan="5" class="px-4 py-2 font-bold text-xs uppercase tracking-wider text-indigo-900"><i class="fa-solid fa-layer-group mr-1.5 opacity-70"></i>' . e($sec) . '</td></tr>';
    }

    $rId = e($r['id']);
    $paramName = e($r['parameter'] ?: ($r['test'] ?? 'Test'));
    $val = e($r['value'] ?? '');
    $unit = e($r['unit'] ?? '');
    $range = e($r['reference_range'] ?: ($r['range'] ?? '—'));
    $flag = strtolower((string)($r['flag'] ?? ''));

    $autofocus = $firstInput ? ' autofocus' : '';
    $firstInput = false;

    $flagOpts = '
        <option value="">Normal</option>
        <option value="L"' . ($flag === 'l' ? ' selected' : '') . '>Low (L ↓)</option>
        <option value="H"' . ($flag === 'h' ? ' selected' : '') . '>High (H ↑)</option>
        <option value="critical"' . ($flag === 'critical' ? ' selected' : '') . '>Critical (!)</option>
    ';

    $tableRows .= <<<HTML
    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
        <td class="px-4 py-3 font-semibold text-slate-800 text-sm">
            {$paramName}
            <input type="hidden" name="results[{$rId}][unit]" value="{$unit}">
            <input type="hidden" name="results[{$rId}][range]" value="{$range}">
        </td>
        <td class="px-4 py-3 text-slate-600 text-sm">
            {$unit}
        </td>
        <td class="px-4 py-3 text-slate-600 text-sm font-mono">
            {$range}
        </td>
        <td class="px-4 py-3">
            <input type="text" name="results[{$rId}][value]" value="{$val}" class="field text-sm font-bold text-slate-900 w-36" placeholder="Result"{$autofocus}>
        </td>
        <td class="px-4 py-3">
            <select name="results[{$rId}][flag]" class="field text-sm w-32">
                {$flagOpts}
            </select>
        </td>
    </tr>
    HTML;
}

$previewLink = '/portals/main-lab/reports/preview.php?lab_no=' . urlencode($labNo);

$content = page_header(
    'Results Entry',
    "Lab No: {$labNo} · Patient: {$patientName} · MR No: {$mrNo}"
);
$content .= $message;

// Quick Switcher Header
$content .= <<<HTML
<div class="mb-4 flex flex-wrap items-center justify-between gap-3 p-4 bg-white rounded-lg border border-slate-200 shadow-sm">
    <div class="flex items-center gap-2">
        <label class="text-sm font-semibold text-slate-700 whitespace-nowrap"><i class="fa-solid fa-list-check text-teal-600 mr-1"></i> Switch Entry:</label>
        <select class="field text-sm max-w-md" onchange="if(this.value) window.location.href='/portals/main-lab/results/entry.php?lab_no=' + encodeURIComponent(this.value);">
            {$switcherOpts}
        </select>
    </div>
    <div class="flex items-center gap-2">
        <a href="{$previewLink}" class="btn btn-secondary text-xs">
            <i class="fa-solid fa-eye mr-1"></i> Preview Report
        </a>
    </div>
</div>
HTML;

// Patient & Test Summary Banner
$content .= <<<HTML
<div class="mb-6 grid gap-4 sm:grid-cols-4 p-4 bg-slate-100 rounded-lg border border-slate-200 text-sm">
    <div><span class="text-slate-500 block text-xs font-semibold uppercase">Patient Name</span><strong class="text-slate-900 text-base">{$patientName}</strong></div>
    <div><span class="text-slate-500 block text-xs font-semibold uppercase">MR Number</span><strong class="text-slate-900 font-mono">{$mrNo}</strong></div>
    <div><span class="text-slate-500 block text-xs font-semibold uppercase">Lab Number</span><strong class="text-teal-700 font-mono text-base">{$labNo}</strong></div>
    <div><span class="text-slate-500 block text-xs font-semibold uppercase">Ordered Tests</span><span class="inline-block px-2 py-0.5 font-bold rounded bg-teal-100 text-teal-900">{$testsOrdered}</span></div>
</div>
HTML;

// Results Table Form
$content .= card(
    '<form method="post">' .
    '<input type="hidden" name="lab_no" value="' . e($labNo) . '">' .
    '<div class="overflow-x-auto">' .
    '<table class="min-w-full text-sm">' .
    '<thead class="bg-slate-800 text-white uppercase text-xs font-bold tracking-wider">' .
    '<tr>' .
    '<th class="px-4 py-3 text-left">Parameter / Test</th>' .
    '<th class="px-4 py-3 text-left">Unit</th>' .
    '<th class="px-4 py-3 text-left">Reference Range</th>' .
    '<th class="px-4 py-3 text-left">Result Value</th>' .
    '<th class="px-4 py-3 text-left">Flag</th>' .
    '</tr>' .
    '</thead>' .
    '<tbody>' .
    $tableRows .
    '</tbody>' .
    '</table>' .
    '</div>' .
    '<div class="border-t border-slate-200 p-4 bg-slate-50 flex flex-wrap items-center justify-between gap-3">' .
    '<div class="flex gap-2">' .
    '<button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk mr-1"></i> Save Draft Results</button>' .
    '<button type="submit" name="send_verify" value="1" class="btn btn-secondary font-semibold"><i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> Save &amp; Verify Report</button>' .
    '</div>' .
    '<a href="' . $previewLink . '" class="text-sm font-semibold text-teal-700 hover:underline"><i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> View Printed Report</a>' .
    '</div>' .
    '</form>',
    'overflow-hidden'
);

render_page('Results Entry', 'main-lab', 'results-entry', $content);

