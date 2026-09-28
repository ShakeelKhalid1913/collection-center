<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$labNo = trim((string)($_GET['lab_no'] ?? $_POST['lab_no'] ?? ''));
$q = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $labNo !== '' && isset($_POST['save_payment'])) {
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
        $redirect = '/portals/collection-center/receipts.php?lab_no=' . urlencode($labNo);
        if ($q !== '') {
            $redirect .= '&q=' . urlencode($q);
        }
        header('Location: ' . $redirect . '&paid_saved=1');
        exit;
    }
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

$visitRows = '';
if ($q !== '' && $labNo === '') {
    $seen = [];
    foreach (patient_repo()->search($q, $orgId) as $p) {
        $pid = (string)($p['patient_no'] ?? $p['id'] ?? '');
        $visits = lab_repo()->getByPatientId((string)($p['id'] ?? ''));
        if ($visits === [] && $pid !== '') {
            $visits = lab_repo()->getByPatientId($pid);
        }
        foreach ($visits as $ae) {
            $ln = (string)($ae['lab_no'] ?? '');
            if ($ln === '' || isset($seen[$ln])) {
                continue;
            }
            $seen[$ln] = true;
            $visitRows .= '<tr class="border-b border-slate-100">'
                . '<td class="px-3 py-2 font-mono text-teal-800 font-semibold">' . e($ln) . '</td>'
                . '<td class="px-3 py-2 font-semibold">' . e((string)($ae['patient_name'] ?? ($p['full_name'] ?? ''))) . '</td>'
                . '<td class="px-3 py-2 text-sm">' . e(normalize_tests_list((string)($ae['tests'] ?? ''))) . '</td>'
                . '<td class="px-3 py-2"><a class="btn btn-primary text-xs" href="/portals/collection-center/receipts.php?lab_no=' . urlencode($ln) . '&q=' . urlencode($q) . '">Open bill</a></td>'
                . '</tr>';
        }
    }
    foreach (lab_repo()->getAll($orgId, 200) as $ae) {
        $blob = strtolower(($ae['lab_no'] ?? '') . ' ' . ($ae['patient_name'] ?? '') . ' ' . ($ae['patient_id'] ?? ''));
        if (!str_contains($blob, strtolower($q))) {
            continue;
        }
        $ln = (string)($ae['lab_no'] ?? '');
        if ($ln === '' || isset($seen[$ln])) {
            continue;
        }
        $seen[$ln] = true;
        $visitRows .= '<tr class="border-b border-slate-100">'
            . '<td class="px-3 py-2 font-mono text-teal-800 font-semibold">' . e($ln) . '</td>'
            . '<td class="px-3 py-2 font-semibold">' . e((string)($ae['patient_name'] ?? '')) . '</td>'
            . '<td class="px-3 py-2 text-sm">' . e(normalize_tests_list((string)($ae['tests'] ?? ''))) . '</td>'
            . '<td class="px-3 py-2"><a class="btn btn-primary text-xs" href="/portals/collection-center/receipts.php?lab_no=' . urlencode($ln) . '&q=' . urlencode($q) . '">Open bill</a></td>'
            . '</tr>';
    }
}

$reportUrl = '/portals/collection-center/reports/preview.php?lab_no=' . urlencode($currentLab);

$content = page_header(
    'Patient Bill / Receipt',
    'Search patient → open visit bill with test prices.',
    $currentLab !== '' ? btn_secondary($reportUrl, 'Open lab report') : ''
);

$qVal = e($q);
$content .= <<<HTML
<div class="mb-4 no-print space-y-3 p-4 bg-white border border-slate-200 rounded-lg">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[16rem]">
            <label class="field-label" for="bill-q">Search patient</label>
            <input type="search" id="bill-q" name="q" class="field" value="{$qVal}" placeholder="Name, phone, MR No, or Lab No…" autofocus>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass mr-1"></i> Search</button>
        <a href="/portals/collection-center/receipts.php" class="btn btn-secondary">Clear</a>
    </form>
    <div class="flex flex-wrap gap-3 items-end border-t border-slate-100 pt-3">
        <div class="flex-1 min-w-[16rem]">
            <label class="field-label" for="bill-visit">Or open visit from list</label>
            <select id="bill-visit" class="field text-sm" onchange="if(this.value) location.href='/portals/collection-center/receipts.php?lab_no='+encodeURIComponent(this.value)">
                <option value="">— Select lab number / patient —</option>
                {$dropdownOpts}
            </select>
        </div>
    </div>
</div>
HTML;

if ($q !== '' && $labNo === '') {
    if ($visitRows === '') {
        $content .= card('<p class="p-6 text-slate-600">No visits found for <strong>' . e($q) . '</strong>.</p>');
    } else {
        $content .= card(
            panel_head('Matching visits') .
            '<div class="overflow-x-auto"><table class="min-w-full text-sm">'
            . '<thead class="bg-slate-800 text-white text-xs uppercase"><tr>'
            . '<th class="px-3 py-2 text-left">Lab No</th><th class="px-3 py-2 text-left">Patient</th><th class="px-3 py-2 text-left">Tests</th><th class="px-3 py-2 text-left">Action</th>'
            . '</tr></thead><tbody>' . $visitRows . '</tbody></table></div>',
            'overflow-hidden'
        );
    }
}

if ($currentLab !== '' && $entry) {
    $payMessage = isset($_GET['paid_saved']) ? flash_success('Payment saved. Bill updated.') : '';
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
            . '<div><label class="field-label" for="bill-amount">Bill total (Rs.)</label>'
            . '<input type="number" id="bill-amount" name="amount" class="field" min="0" step="1" value="' . e((string)(int)round($curAmount)) . '"></div>'
            . '<div><label class="field-label" for="bill-discount">Discount (Rs.)</label>'
            . '<input type="number" id="bill-discount" name="discount" class="field" min="0" step="1" value="' . e((string)(int)round($curDiscount)) . '"></div>'
            . '<div><label class="field-label" for="bill-paid">Amount paid (Rs.)</label>'
            . '<input type="number" id="bill-paid" name="paid" class="field" min="0" step="1" value="' . e((string)(int)round($curPaid)) . '"></div>'
            . '<div class="flex flex-wrap gap-2">'
            . '<button type="submit" name="save_payment" value="1" class="btn btn-primary">Save payment</button>'
            . '<button type="submit" name="save_payment" value="1" class="btn btn-secondary" onclick="document.getElementById(\'bill-paid\').value=document.getElementById(\'bill-amount\').value;">Mark fully paid</button>'
            . '</div>'
            . '<p class="sm:col-span-2 lg:col-span-4 text-xs text-slate-500 m-0">Due now: <strong>' . e(format_money($dueNow)) . '</strong></p>'
            . '</form>',
            'overflow-hidden'
        )
        . '</div>';

    $content .= report_actions($patient['phone'] ?? '', $reportUrl, 'bill-' . $currentLab);
    $content .= '<div class="mt-4">' . render_receipt_document($settings, $entry, $patient) . '</div>';
} elseif ($q === '') {
    $content .= card('<p class="p-6 text-slate-600">Search by patient name, phone, MR No, or Lab No to open their bill.</p>');
}

render_page('Patient Bill', 'collection-center', 'receipts', $content, true);
