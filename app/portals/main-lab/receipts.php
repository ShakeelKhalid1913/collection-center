<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$labNo = trim((string)($_GET['lab_no'] ?? $_POST['lab_no'] ?? ''));
$q = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));
$patientId = trim((string)($_GET['patient_id'] ?? $_POST['patient_id'] ?? ''));
$payMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($labNo !== '') && isset($_POST['save_payment'])) {
    $existing = lab_repo()->findByLabNo($labNo);
    if ($existing) {
        $amount = (float)($_POST['amount'] ?? $existing['amount'] ?? 0);
        $discount = max(0, (float)($_POST['discount'] ?? 0));
        $paid = max(0, (float)($_POST['paid'] ?? 0));
        if ($amount <= 0) {
            $lines = receipt_line_items($existing, $orgId);
            $amount = max(0, (float)$lines['subtotal'] - $discount);
        }
        lab_repo()->updateEntry($labNo, [
            'amount' => $amount,
            'discount' => $discount,
            'paid' => $paid,
        ]);
        $redirect = '/portals/main-lab/receipts.php?lab_no=' . urlencode($labNo);
        if ($q !== '') {
            $redirect .= '&q=' . urlencode($q);
        }
        if ($patientId !== '') {
            $redirect .= '&patient_id=' . urlencode($patientId);
        }
        $redirect .= '&paid_saved=1';
        header('Location: ' . $redirect);
        exit;
    }
    $payMessage = flash_error('Could not save payment — visit not found.');
}

$ctx = load_document_context($labNo !== '' ? $labNo : null);
$settings = $ctx['settings'];
$patient = $ctx['patient'];
$entry = $ctx['entry'];
$currentLab = (string)($entry['lab_no'] ?? '');

$allEntries = lab_repo()->getAll($orgId, 120);
$dropdownOpts = '';
foreach ($allEntries as $ae) {
    $sel = (($ae['lab_no'] ?? '') === $currentLab) ? ' selected' : '';
    $dropdownOpts .= '<option value="' . e((string)$ae['lab_no']) . '"' . $sel . '>'
        . e((string)$ae['lab_no']) . ' — ' . e((string)($ae['patient_name'] ?? ''))
        . ' (' . e(normalize_tests_list((string)($ae['tests'] ?? ''))) . ')</option>';
}

$searchHits = [];
$matchedVisits = [];
$patientVisits = [];

if ($q !== '') {
    try {
        $searchHits = patient_repo()->search($q, $orgId);
    } catch (Throwable $e) {
        $searchHits = [];
    }
    $ql = strtolower($q);
    foreach ($allEntries as $ae) {
        $blob = strtolower(
            ($ae['lab_no'] ?? '') . ' ' .
            ($ae['patient_name'] ?? '') . ' ' .
            ($ae['patient_id'] ?? '') . ' ' .
            ($ae['tests'] ?? '')
        );
        if (str_contains($blob, $ql)) {
            $matchedVisits[] = $ae;
        }
    }
}

if ($patientId !== '') {
    $selectedPatient = patient_repo()->findById($patientId);
    if ($selectedPatient) {
        $patientVisits = lab_repo()->getByPatientId((string)($selectedPatient['id'] ?? ''));
        if ($patientVisits === [] && !empty($selectedPatient['patient_no'])) {
            $patientVisits = lab_repo()->getByPatientId((string)$selectedPatient['patient_no']);
        }
        if ($patientVisits === []) {
            $name = strtolower(trim((string)($selectedPatient['full_name'] ?? $selectedPatient['name'] ?? '')));
            foreach ($allEntries as $ae) {
                if ($name !== '' && str_contains(strtolower((string)($ae['patient_name'] ?? '')), $name)) {
                    $patientVisits[] = $ae;
                }
            }
        }
        if ($labNo === '' && count($patientVisits) === 1) {
            header('Location: /portals/main-lab/receipts.php?lab_no=' . urlencode((string)$patientVisits[0]['lab_no']) . '&q=' . urlencode($q));
            exit;
        }
    }
}

$reportUrl = '/portals/main-lab/reports/preview.php?lab_no=' . urlencode($currentLab);
$entryUrl = '/portals/main-lab/results/entry.php?lab_no=' . urlencode($currentLab);
$qVal = e($q);

$content = page_header(
    'Patient Bill / Receipt',
    'Search a patient or pick a visit from the list, then print the bill.',
    $currentLab !== '' ? '<a class="btn btn-secondary" href="' . e($reportUrl) . '">Lab Report</a>' : ''
);

$content .= <<<HTML
<div class="mb-4 no-print space-y-3 p-4 bg-white border border-slate-200 rounded-lg">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[16rem]">
            <label class="field-label" for="bill-q">Search patient</label>
            <input type="search" id="bill-q" name="q" class="field" value="{$qVal}" placeholder="Name, phone, MR No, or Lab No…" autofocus>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass mr-1"></i> Search</button>
        <a href="/portals/main-lab/receipts.php" class="btn btn-secondary">Clear</a>
    </form>
    <div class="flex flex-wrap gap-3 items-end border-t border-slate-100 pt-3">
        <div class="flex-1 min-w-[16rem]">
            <label class="field-label" for="bill-visit">Or open visit from list</label>
            <select id="bill-visit" class="field text-sm" onchange="if(this.value) location.href='/portals/main-lab/receipts.php?lab_no='+encodeURIComponent(this.value)">
                <option value="">— Select lab number / patient —</option>
                {$dropdownOpts}
            </select>
        </div>
    </div>
</div>
HTML;

if ($q !== '' && $labNo === '' && $patientId === '') {
    $hitRows = '';
    $seenPatients = [];
    foreach ($searchHits as $p) {
        $pid = (string)($p['patient_no'] ?? $p['id'] ?? '');
        if ($pid === '' || isset($seenPatients[$pid])) {
            continue;
        }
        $seenPatients[$pid] = true;
        $name = trim(($p['title'] ?? '') . ' ' . ($p['full_name'] ?? $p['name'] ?? ''));
        $hitRows .= '<tr class="border-b border-slate-100">'
            . '<td class="px-3 py-2 font-mono text-teal-800 font-semibold">' . e($pid) . '</td>'
            . '<td class="px-3 py-2 font-semibold">' . e($name) . '</td>'
            . '<td class="px-3 py-2">' . e((string)($p['phone'] ?? '—')) . '</td>'
            . '<td class="px-3 py-2"><a class="btn btn-primary text-xs" href="/portals/main-lab/receipts.php?patient_id=' . urlencode($pid) . '&q=' . urlencode($q) . '">Show visits</a></td>'
            . '</tr>';
    }

    $visitRows = '';
    $seenLabs = [];
    foreach ($matchedVisits as $ae) {
        $ln = (string)($ae['lab_no'] ?? '');
        if ($ln === '' || isset($seenLabs[$ln])) {
            continue;
        }
        $seenLabs[$ln] = true;
        $visitRows .= '<tr class="border-b border-slate-100">'
            . '<td class="px-3 py-2 font-mono text-teal-800 font-semibold">' . e($ln) . '</td>'
            . '<td class="px-3 py-2 font-semibold">' . e((string)($ae['patient_name'] ?? '')) . '</td>'
            . '<td class="px-3 py-2 text-sm">' . e(normalize_tests_list((string)($ae['tests'] ?? ''))) . '</td>'
            . '<td class="px-3 py-2 text-sm">' . e(format_money((float)($ae['amount'] ?? 0))) . '</td>'
            . '<td class="px-3 py-2"><a class="btn btn-primary text-xs" href="/portals/main-lab/receipts.php?lab_no=' . urlencode($ln) . '&q=' . urlencode($q) . '">Open bill</a></td>'
            . '</tr>';
    }

    if ($hitRows === '' && $visitRows === '') {
        $content .= card('<p class="p-6 text-slate-600">No patient or visit found for <strong>' . e($q) . '</strong>.</p>');
    } else {
        if ($hitRows !== '') {
            $content .= card(
                panel_head('Patients matching "' . $q . '"') .
                '<div class="overflow-x-auto"><table class="min-w-full text-sm">'
                . '<thead class="bg-slate-800 text-white text-xs uppercase"><tr>'
                . '<th class="px-3 py-2 text-left">MR No</th><th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2 text-left">Phone</th><th class="px-3 py-2 text-left">Action</th>'
                . '</tr></thead><tbody>' . $hitRows . '</tbody></table></div>',
                'overflow-hidden mb-4'
            );
        }
        if ($visitRows !== '') {
            $content .= card(
                panel_head('Visits matching "' . $q . '"') .
                '<div class="overflow-x-auto"><table class="min-w-full text-sm">'
                . '<thead class="bg-slate-800 text-white text-xs uppercase"><tr>'
                . '<th class="px-3 py-2 text-left">Lab No</th><th class="px-3 py-2 text-left">Patient</th><th class="px-3 py-2 text-left">Tests</th><th class="px-3 py-2 text-left">Amount</th><th class="px-3 py-2 text-left">Action</th>'
                . '</tr></thead><tbody>' . $visitRows . '</tbody></table></div>',
                'overflow-hidden'
            );
        }
    }
}

if ($patientId !== '' && $labNo === '') {
    $selectedPatient = patient_repo()->findById($patientId);
    $pname = e(trim(($selectedPatient['title'] ?? '') . ' ' . ($selectedPatient['full_name'] ?? $selectedPatient['name'] ?? $patientId)));
    $visitRows = '';
    foreach ($patientVisits as $ae) {
        $ln = (string)($ae['lab_no'] ?? '');
        $visitRows .= '<tr class="border-b border-slate-100">'
            . '<td class="px-3 py-2 font-mono text-teal-800 font-semibold">' . e($ln) . '</td>'
            . '<td class="px-3 py-2 text-sm">' . e(normalize_tests_list((string)($ae['tests'] ?? ''))) . '</td>'
            . '<td class="px-3 py-2 text-sm">' . e(format_money((float)($ae['amount'] ?? 0))) . '</td>'
            . '<td class="px-3 py-2 text-sm">' . e(format_date($ae['created_at'] ?? null)) . '</td>'
            . '<td class="px-3 py-2"><a class="btn btn-primary text-xs" href="/portals/main-lab/receipts.php?lab_no=' . urlencode($ln) . '&patient_id=' . urlencode($patientId) . '&q=' . urlencode($q) . '">Open bill</a></td>'
            . '</tr>';
    }
    if ($visitRows === '') {
        $content .= card('<p class="p-6 text-slate-600">No lab visits found for <strong>' . $pname . '</strong>.</p>');
    } else {
        $content .= card(
            panel_head('Visits for ' . ($selectedPatient['full_name'] ?? $patientId)) .
            '<div class="overflow-x-auto"><table class="min-w-full text-sm">'
            . '<thead class="bg-slate-800 text-white text-xs uppercase"><tr>'
            . '<th class="px-3 py-2 text-left">Lab No</th><th class="px-3 py-2 text-left">Tests</th><th class="px-3 py-2 text-left">Amount</th><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-left">Action</th>'
            . '</tr></thead><tbody>' . $visitRows . '</tbody></table></div>',
            'overflow-hidden'
        );
    }
}

if ($currentLab !== '' && $entry) {
    if (isset($_GET['paid_saved'])) {
        $payMessage = flash_success('Payment saved. Bill updated.');
    }
    $content .= $payMessage;

    $billLines = receipt_line_items($entry, $orgId);
    $catalogSub = (float)$billLines['subtotal'];
    $curDiscount = (float)($entry['discount'] ?? 0);
    $curPaid = (float)($entry['paid'] ?? 0);
    $curAmount = (float)($entry['amount'] ?? 0);
    $netSuggest = $catalogSub > 0 ? max(0, $catalogSub - $curDiscount) : $curAmount;
    if ($curAmount <= 0 && $netSuggest > 0) {
        $curAmount = $netSuggest;
    }
    $dueNow = max(0, $curAmount - $curPaid);

    $content .= '<div class="no-print mb-4">'
        . card(
            panel_head('Record payment') .
            '<form method="post" class="p-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end">'
            . '<input type="hidden" name="lab_no" value="' . e($currentLab) . '">'
            . '<input type="hidden" name="q" value="' . e($q) . '">'
            . '<input type="hidden" name="patient_id" value="' . e($patientId) . '">'
            . '<div><label class="field-label" for="bill-amount">Bill total (Rs.)</label>'
            . '<input type="number" id="bill-amount" name="amount" class="field" min="0" step="1" value="' . e((string)(int)round($curAmount)) . '"></div>'
            . '<div><label class="field-label" for="bill-discount">Discount (Rs.)</label>'
            . '<input type="number" id="bill-discount" name="discount" class="field" min="0" step="1" value="' . e((string)(int)round($curDiscount)) . '"></div>'
            . '<div><label class="field-label" for="bill-paid">Amount paid (Rs.)</label>'
            . '<input type="number" id="bill-paid" name="paid" class="field" min="0" step="1" value="' . e((string)(int)round($curPaid)) . '"></div>'
            . '<div class="flex flex-wrap gap-2">'
            . '<button type="submit" name="save_payment" value="1" class="btn btn-primary"><i class="fa-solid fa-floppy-disk mr-1"></i> Save payment</button>'
            . '<button type="submit" name="save_payment" value="1" class="btn btn-secondary" onclick="document.getElementById(\'bill-paid\').value=document.getElementById(\'bill-amount\').value;">Mark fully paid</button>'
            . '</div>'
            . '<p class="sm:col-span-2 lg:col-span-4 text-xs text-slate-500 m-0">Catalog subtotal: <strong>' . e(format_money($catalogSub)) . '</strong>'
            . ' · Due now: <strong class="' . ($dueNow > 0 ? 'text-amber-700' : 'text-emerald-700') . '">' . e(format_money($dueNow)) . '</strong>'
            . ' · Tip: click <em>Mark fully paid</em> then Save if they paid the full bill.</p>'
            . '</form>',
            'overflow-hidden'
        )
        . '</div>';

    $content .= '<div class="no-print mb-3 flex flex-wrap gap-2 items-center">'
        . report_actions($patient['phone'] ?? '', $reportUrl, 'bill-' . $currentLab)
        . '<a class="btn btn-secondary text-sm" href="' . e($entryUrl) . '">Enter Results</a>'
        . '</div>';
    $content .= '<div class="mt-2">' . render_receipt_document($settings, $entry, $patient) . '</div>';
} elseif ($q === '' && $patientId === '') {
    $content .= card('<p class="p-6 text-slate-600">Use <strong>Search</strong> or the <strong>visit dropdown</strong> above to open a patient bill.</p>');
}

render_page('Patient Bill', 'main-lab', 'receipts', $content, true);
