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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($labNo !== '')) {
    if (isset($_POST['toggle_bill_lock'])) {
        $existing = lab_repo()->findByLabNo($labNo);
        if ($existing) {
            $lockAction = (string)$_POST['toggle_bill_lock'];
            if ($lockAction === 'lock') {
                lab_repo()->lockBill($labNo, current_user()['name'] ?? 'Staff');
                audit_log('LOCK_BILL', 'lab_entries', $labNo, "Bill locked by " . (current_user()['name'] ?? 'Staff'));
                header('Location: /portals/receipts.php?lab_no=' . urlencode($labNo) . '&bill_locked=1');
                exit;
            } elseif ($lockAction === 'unlock') {
                if (!is_admin_user()) {
                    $payMessage = flash_error('Security Restriction: Only an Admin account can unlock a locked bill.');
                } else {
                    lab_repo()->unlockBill($labNo);
                    audit_log('UNLOCK_BILL', 'lab_entries', $labNo, "Bill unlocked by Admin " . (current_user()['name'] ?? 'Admin'));
                    header('Location: /portals/receipts.php?lab_no=' . urlencode($labNo) . '&bill_unlocked=1');
                    exit;
                }
            }
        }
    }

    if (isset($_POST['save_payment'])) {
        require_permission('process_billing');
        $existing = lab_repo()->findByLabNo($labNo);
        if ($existing) {
            $isBillLocked = !empty($existing['is_bill_locked']);
            if ($isBillLocked && !is_admin_user()) {
                $payMessage = flash_error('Security Lock: This bill is locked. Only an Admin account can modify payments or unlock this bill.');
            } else {
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
                $redirect = '/portals/receipts.php?lab_no=' . urlencode($labNo);
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
        } else {
            $payMessage = flash_error('Could not save payment — visit not found.');
        }
    }
}

$ctx = load_document_context($labNo !== '' ? $labNo : null, $orgId);
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
            header('Location: /portals/receipts.php?lab_no=' . urlencode((string)$patientVisits[0]['lab_no']) . '&q=' . urlencode($q));
            exit;
        }
    }
}

$reportUrl = '/portals/reports/preview.php?lab_no=' . urlencode($currentLab);
$entryUrl = '/portals/results/entry.php?lab_no=' . urlencode($currentLab);
$qVal = e($q);

$content = workflow_stepper('billing', $currentLab !== '' ? $currentLab : null, $patientId !== '' ? $patientId : null);
$content .= page_header(
    'Patient Bill / Receipt',
    '',
    $currentLab !== '' ? '<a class="btn btn-secondary text-xs" href="' . e($reportUrl) . '"><i class="fa-solid fa-file-medical mr-1"></i> Lab Report</a>' : ''
);

$content .= <<<HTML
<div class="mb-6 no-print space-y-4 p-5 bg-white border border-slate-200/90 rounded-3xl shadow-sm">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[16rem]">
            <label class="field-label" for="bill-q">Search patient</label>
            <input type="search" id="bill-q" name="q" class="field" value="{$qVal}" placeholder="Name, phone, MR No, or Lab No…" autofocus>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass mr-1"></i> Search</button>
        <a href="/portals/receipts.php" class="btn btn-secondary">Clear</a>
    </form>
    <div class="flex flex-wrap gap-3 items-end border-t border-slate-100 pt-3">
        <div class="flex-1 min-w-[16rem]">
            <label class="field-label" for="bill-visit">Or open visit from list</label>
            <select id="bill-visit" class="field text-sm" onchange="if(this.value) location.href='/portals/receipts.php?lab_no='+encodeURIComponent(this.value)">
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
        $hitRows .= '<tr class="border-b border-slate-100 hover:bg-slate-50/80 transition-colors">'
            . '<td class="px-4 py-3"><span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md text-xs">' . e($pid) . '</span></td>'
            . '<td class="px-4 py-3 font-bold text-slate-800">' . e($name) . '</td>'
            . '<td class="px-4 py-3 text-slate-600">' . e((string)($p['phone'] ?? '—')) . '</td>'
            . '<td class="px-4 py-3"><a class="btn btn-primary text-xs py-1.5 px-3" href="/portals/receipts.php?patient_id=' . urlencode($pid) . '&q=' . urlencode($q) . '">Show visits</a></td>'
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
        $visitRows .= '<tr class="border-b border-slate-100 hover:bg-slate-50/80 transition-colors">'
            . '<td class="px-4 py-3"><span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md text-xs">' . e($ln) . '</span></td>'
            . '<td class="px-4 py-3 font-bold text-slate-800">' . e((string)($ae['patient_name'] ?? '')) . '</td>'
            . '<td class="px-4 py-3 text-xs text-slate-600 font-medium">' . e(normalize_tests_list((string)($ae['tests'] ?? ''))) . '</td>'
            . '<td class="px-4 py-3 text-xs font-semibold text-slate-900">' . e(format_money((float)($ae['amount'] ?? 0))) . '</td>'
            . '<td class="px-4 py-3"><a class="btn btn-primary text-xs py-1.5 px-3" href="/portals/receipts.php?lab_no=' . urlencode($ln) . '&q=' . urlencode($q) . '">Open bill</a></td>'
            . '</tr>';
    }

    if ($hitRows === '' && $visitRows === '') {
        $content .= card('<p class="p-6 text-slate-600">No patient or visit found for <strong>' . e($q) . '</strong>.</p>');
    } else {
        if ($hitRows !== '') {
            $content .= card(
                panel_head('Patients matching "' . $q . '"') .
                '<div class="overflow-x-auto"><table class="w-full text-left border-collapse text-sm">'
                . '<thead class="bg-slate-50 border-b border-slate-100 text-[11px] font-extrabold uppercase tracking-wider text-slate-400"><tr>'
                . '<th class="px-4 py-3.5">MR No</th><th class="px-4 py-3.5">Name</th><th class="px-4 py-3.5">Phone</th><th class="px-4 py-3.5">Action</th>'
                . '</tr></thead><tbody class="divide-y divide-slate-100">' . $hitRows . '</tbody></table></div>',
                'overflow-hidden mb-4'
            );
        }
        if ($visitRows !== '') {
            $content .= card(
                panel_head('Visits matching "' . $q . '"') .
                '<div class="overflow-x-auto"><table class="w-full text-left border-collapse text-sm">'
                . '<thead class="bg-slate-50 border-b border-slate-100 text-[11px] font-extrabold uppercase tracking-wider text-slate-400"><tr>'
                . '<th class="px-4 py-3.5">Lab No</th><th class="px-4 py-3.5">Patient</th><th class="px-4 py-3.5">Tests</th><th class="px-4 py-3.5">Amount</th><th class="px-4 py-3.5">Action</th>'
                . '</tr></thead><tbody class="divide-y divide-slate-100">' . $visitRows . '</tbody></table></div>',
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
        $visitRows .= '<tr class="border-b border-slate-100 hover:bg-slate-50/80 transition-colors">'
            . '<td class="px-4 py-3"><span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md text-xs">' . e($ln) . '</span></td>'
            . '<td class="px-4 py-3 text-xs text-slate-600 font-medium">' . e(normalize_tests_list((string)($ae['tests'] ?? ''))) . '</td>'
            . '<td class="px-4 py-3 text-xs font-semibold text-slate-900">' . e(format_money((float)($ae['amount'] ?? 0))) . '</td>'
            . '<td class="px-4 py-3 text-xs text-slate-500">' . e(format_date($ae['created_at'] ?? null)) . '</td>'
            . '<td class="px-4 py-3"><a class="btn btn-primary text-xs py-1.5 px-3" href="/portals/receipts.php?lab_no=' . urlencode($ln) . '&patient_id=' . urlencode($patientId) . '&q=' . urlencode($q) . '">Open bill</a></td>'
            . '</tr>';
    }
    if ($visitRows === '') {
        $content .= card('<p class="p-6 text-slate-600">No lab visits found for <strong>' . $pname . '</strong>.</p>');
    } else {
        $content .= card(
            panel_head('Visits for ' . ($selectedPatient['full_name'] ?? $patientId)) .
            '<div class="overflow-x-auto"><table class="w-full text-left border-collapse text-sm">'
            . '<thead class="bg-slate-50 border-b border-slate-100 text-[11px] font-extrabold uppercase tracking-wider text-slate-400"><tr>'
            . '<th class="px-4 py-3.5">Lab No</th><th class="px-4 py-3.5">Tests</th><th class="px-4 py-3.5">Amount</th><th class="px-4 py-3.5">Date</th><th class="px-4 py-3.5">Action</th>'
            . '</tr></thead><tbody class="divide-y divide-slate-100">' . $visitRows . '</tbody></table></div>',
            'overflow-hidden'
        );
    }
}

if ($currentLab !== '' && $entry) {
    if (isset($_GET['paid_saved'])) {
        $payMessage = flash_success('Payment saved. Bill updated.');
    }
    if (isset($_GET['bill_locked'])) {
        $payMessage = flash_success('Bill has been locked. Employees can no longer modify this invoice.');
    }
    if (isset($_GET['bill_unlocked'])) {
        $payMessage = flash_success('Bill has been unlocked by Admin.');
    }
    $content .= $payMessage;

    $isBillLocked = !empty($entry['is_bill_locked']);
    $isAdmin = is_admin_user();
    $billLockedBy = e((string)($entry['bill_locked_by'] ?? 'Staff'));
    $billLockedAt = !empty($entry['bill_locked_at']) ? date('d M Y, h:i A', strtotime($entry['bill_locked_at'])) : '';

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

    $curPct = ($catalogSub > 0 && $curDiscount > 0) ? (int)round(($curDiscount / $catalogSub) * 100) : 0;
    $rawSubtotal = (int)round($catalogSub > 0 ? $catalogSub : ($curAmount + $curDiscount));

    $lockBannerHtml = '';
    if ($isBillLocked) {
        $adminActionHtml = $isAdmin
            ? '<button type="submit" name="toggle_bill_lock" value="unlock" class="btn btn-secondary text-xs font-bold text-rose-800 bg-white border border-rose-300 hover:bg-rose-100 shadow-2xs py-2 px-3.5"><i class="fa-solid fa-lock-open mr-1.5 text-rose-600"></i> Unlock Bill (Admin)</button>'
            : '<span class="inline-flex items-center gap-1.5 text-xs font-bold text-rose-800 bg-white px-3 py-2 rounded-xl border border-rose-200 shadow-2xs"><i class="fa-solid fa-shield-halved text-rose-600"></i> Locked (Admin Only)</span>';

        $lockSubtext = $isAdmin
            ? 'Locked on ' . $billLockedAt . ' by <strong>' . $billLockedBy . '</strong>. You are logged in as Admin — you can edit values or unlock.'
            : 'Locked on ' . $billLockedAt . ' by <strong>' . $billLockedBy . '</strong>. To prevent unauthorized tampering, employee edits are disabled. Only an Admin account can unlock.';

        $lockBannerHtml = <<<HTML
        <div class="mb-4 p-3.5 bg-rose-50 border-2 border-rose-300 rounded-2xl flex flex-wrap items-center justify-between gap-3 text-rose-900 shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-rose-600 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                    <i class="fa-solid fa-lock"></i>
                </span>
                <div>
                    <div class="text-sm font-extrabold text-rose-950 flex items-center gap-1.5">
                        BILL LOCKED <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full bg-rose-200 text-rose-900">Protected</span>
                    </div>
                    <div class="text-xs text-rose-800">{$lockSubtext}</div>
                </div>
            </div>
            <div>{$adminActionHtml}</div>
        </div>
HTML;
    }

    $inputsDisabled = ($isBillLocked && !$isAdmin) ? 'disabled' : '';
    $saveBtnHtml = ($isBillLocked && !$isAdmin)
        ? '<button type="button" disabled class="btn btn-secondary px-6 py-2.5 font-bold text-sm opacity-60 cursor-not-allowed text-slate-400 bg-slate-100 border-slate-200"><i class="fa-solid fa-lock mr-1.5"></i> Bill Locked (Admin Only)</button>'
        : '<button type="submit" name="save_payment" value="1" class="btn btn-primary px-6 py-2.5 font-bold shadow-md text-sm"><i class="fa-solid fa-floppy-disk mr-1.5"></i> Save &amp; Update Bill</button>';

    $lockBtnHtml = (!$isBillLocked)
        ? '<button type="submit" name="toggle_bill_lock" value="lock" onclick="return confirm(\'Lock this bill? Once locked, only an Admin ID can unlock or modify it.\');" class="btn btn-secondary text-xs py-2 px-3.5 font-bold text-amber-800 border border-amber-300 hover:bg-amber-100 shadow-2xs" title="Lock bill to prevent any tampering by employees"><i class="fa-solid fa-lock mr-1.5 text-amber-600"></i> Lock Bill</button>'
        : '';

    $content .= <<<HTML
    <div class="no-print mb-5 bg-white rounded-3xl p-5 sm:p-6 border border-slate-200/90 shadow-card">
        {$lockBannerHtml}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100 mb-4">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-calculator text-blue-600"></i> Bill Settlement &amp; Discount Management
                </h3>
                <p class="text-xs text-slate-500">Apply custom concessions, 30% / 50% discounts, and record payments.</p>
            </div>
            <div class="text-right">
                <span class="text-xs text-slate-400 font-medium">Gross Subtotal:</span>
                <span class="text-base font-black text-slate-900 ml-1">Rs {$rawSubtotal}</span>
            </div>
        </div>

        <form method="post" id="receipt-bill-form" class="space-y-4">
            <input type="hidden" name="lab_no" value="{$currentLab}">
            <input type="hidden" name="q" value="{$q}">
            <input type="hidden" name="patient_id" value="{$patientId}">
            <input type="hidden" id="raw-subtotal" value="{$rawSubtotal}">

            <!-- Quick Preset Discount Buttons (10%, 20%, 30%, 50%, Reset) -->
            <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-2xl flex flex-wrap items-center justify-between gap-2.5">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-tag text-rose-500"></i> Quick Discount:
                    </span>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button type="button" onclick="setReceiptDiscountPct(10)" {$inputsDisabled} class="px-3 py-1 rounded-xl text-xs font-bold bg-white hover:bg-slate-900 hover:text-white border border-slate-300 transition-all shadow-2xs text-slate-700 disabled:opacity-50">10%</button>
                        <button type="button" onclick="setReceiptDiscountPct(20)" {$inputsDisabled} class="px-3 py-1 rounded-xl text-xs font-bold bg-white hover:bg-slate-900 hover:text-white border border-slate-300 transition-all shadow-2xs text-slate-700 disabled:opacity-50">20%</button>
                        <button type="button" onclick="setReceiptDiscountPct(30)" {$inputsDisabled} class="px-3.5 py-1 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-900 hover:text-white border border-slate-300 text-slate-900 transition-all shadow-2xs disabled:opacity-50">30%</button>
                        <button type="button" onclick="setReceiptDiscountPct(50)" {$inputsDisabled} class="px-3.5 py-1 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-900 hover:text-white border border-slate-300 text-slate-900 transition-all shadow-2xs disabled:opacity-50">50%</button>
                        <button type="button" onclick="setReceiptDiscountPct(0)" {$inputsDisabled} class="px-2.5 py-1 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-500 transition-all disabled:opacity-50">0% Reset</button>
                    </div>
                </div>
                <div class="text-xs text-slate-500 font-medium hidden sm:block">
                    Click <strong>30%</strong> or <strong>50%</strong> for instant automatic calculation.
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 items-end">
                <div>
                    <label class="field-label flex items-center justify-between" for="receipt-discount-pct">
                        <span>Discount %</span>
                        <span class="text-[10px] text-slate-400 font-normal">Percentage</span>
                    </label>
                    <div class="relative">
                        <input type="number" id="receipt-discount-pct" class="field font-bold text-blue-700 text-base disabled:bg-slate-100 disabled:cursor-not-allowed" min="0" max="100" step="1" value="{$curPct}" placeholder="0" {$inputsDisabled} oninput="onReceiptDiscountPctChange(this.value)">
                        <span class="absolute right-3 top-2.5 text-xs font-bold text-slate-400 pointer-events-none">%</span>
                    </div>
                </div>

                <div>
                    <label class="field-label flex items-center justify-between" for="bill-discount">
                        <span>Discount (Rs.)</span>
                        <span class="text-[10px] text-rose-600 font-bold" id="disc-summary-pill">- Rs {$curDiscount}</span>
                    </label>
                    <input type="number" id="bill-discount" name="discount" class="field font-bold text-rose-600 text-base disabled:bg-slate-100 disabled:cursor-not-allowed" min="0" step="1" value="{$curDiscount}" {$inputsDisabled} oninput="onReceiptDiscountRsChange(this.value)">
                </div>

                <div>
                    <label class="field-label flex items-center justify-between" for="bill-amount">
                        <span>Net Payable Total</span>
                        <span class="text-[10px] text-emerald-600 font-bold">After Discount</span>
                    </label>
                    <input type="number" id="bill-amount" name="amount" class="field font-black text-slate-900 text-base bg-slate-50 disabled:bg-slate-100 disabled:cursor-not-allowed" min="0" step="1" value="{$curAmount}" {$inputsDisabled} oninput="onReceiptNetChange()">
                </div>

                <div>
                    <label class="field-label flex items-center justify-between" for="bill-paid">
                        <span>Amount Paid (Rs.)</span>
                        <span id="due-badge" class="text-[11px] font-bold px-2 py-0.5 rounded-full"></span>
                    </label>
                    <input type="number" id="bill-paid" name="paid" class="field font-bold text-emerald-600 text-base disabled:bg-slate-100 disabled:cursor-not-allowed" min="0" step="1" value="{$curPaid}" {$inputsDisabled} oninput="onReceiptPaidChange()">
                </div>
            </div>

            <!-- Quick Pay Action Bar -->
            <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold text-slate-500">Quick Pay:</span>
                    <button type="button" onclick="setReceiptPaidQuick('full')" {$inputsDisabled} class="btn btn-secondary text-xs py-1.5 px-3 font-bold bg-emerald-50 text-emerald-800 hover:bg-emerald-600 hover:text-white border-emerald-300 disabled:opacity-50">
                        <i class="fa-solid fa-check-double mr-1"></i> Full Paid
                    </button>
                    <button type="button" onclick="setReceiptPaidQuick('half')" {$inputsDisabled} class="btn btn-secondary text-xs py-1.5 px-3 font-bold bg-amber-50 text-amber-800 hover:bg-amber-600 hover:text-white border-amber-300 disabled:opacity-50">
                        <i class="fa-solid fa-fraction mr-1"></i> 50% Advance
                    </button>
                    <button type="button" onclick="setReceiptPaidQuick('zero')" {$inputsDisabled} class="btn btn-secondary text-xs py-1.5 px-3 font-bold bg-slate-100 text-slate-600 hover:bg-slate-200 disabled:opacity-50">
                        Zero Advance
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    {$lockBtnHtml}
                    {$saveBtnHtml}
                </div>
            </div>
        </form>
    </div>

    <script>
    function getSubtotal() {
        return parseFloat(document.getElementById('raw-subtotal').value) || 0;
    }

    function setReceiptDiscountPct(pct) {
        document.getElementById('receipt-discount-pct').value = pct;
        onReceiptDiscountPctChange(pct);
    }

    function onReceiptDiscountPctChange(pctVal) {
        const sub = getSubtotal();
        const pct = Math.max(0, Math.min(100, parseFloat(pctVal) || 0));
        const discRs = Math.round((sub * pct) / 100);
        document.getElementById('bill-discount').value = discRs;
        updateReceiptTotals(sub, discRs);
    }

    function onReceiptDiscountRsChange(rsVal) {
        const sub = getSubtotal();
        const discRs = Math.max(0, Math.min(sub, parseFloat(rsVal) || 0));
        const pct = sub > 0 ? Math.round((discRs / sub) * 100) : 0;
        document.getElementById('receipt-discount-pct').value = pct;
        updateReceiptTotals(sub, discRs);
    }

    function updateReceiptTotals(sub, discRs) {
        const net = Math.max(0, sub - discRs);
        document.getElementById('bill-amount').value = net;
        document.getElementById('disc-summary-pill').innerText = '- Rs ' + discRs.toLocaleString();
        onReceiptPaidChange();
    }

    function onReceiptPaidChange() {
        const net = parseFloat(document.getElementById('bill-amount').value) || 0;
        const paid = parseFloat(document.getElementById('bill-paid').value) || 0;
        const due = Math.max(0, net - paid);
        const badge = document.getElementById('due-badge');
        if (due <= 0) {
            badge.className = 'text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800';
            badge.innerText = 'Fully Paid';
        } else {
            badge.className = 'text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800';
            badge.innerText = 'Due: Rs ' + Math.round(due).toLocaleString();
        }
    }

    function onReceiptNetChange() {
        onReceiptPaidChange();
    }

    function setReceiptPaidQuick(mode) {
        const net = parseFloat(document.getElementById('bill-amount').value) || 0;
        const paidInput = document.getElementById('bill-paid');
        if (mode === 'full') {
            paidInput.value = Math.round(net);
        } else if (mode === 'half') {
            paidInput.value = Math.round(net / 2);
        } else if (mode === 'zero') {
            paidInput.value = 0;
        }
        onReceiptPaidChange();
    }

    document.addEventListener('DOMContentLoaded', onReceiptPaidChange);
    setTimeout(onReceiptPaidChange, 50);
    </script>
HTML;

    $qCurrentLab = urlencode($currentLab);
    $content .= <<<HTML
    <div class="no-print mb-4 p-3.5 bg-emerald-50/90 border border-emerald-200/90 rounded-2xl flex flex-wrap items-center justify-between gap-3 shadow-2xs">
        <div class="flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-xl bg-emerald-600 text-white inline-flex items-center justify-center font-bold text-sm shadow-2xs">
                <i class="fa-solid fa-receipt"></i>
            </span>
            <div>
                <div class="text-xs font-bold text-emerald-950">Patient Bill &amp; Token Slip · {$currentLab}</div>
                <div class="text-[11px] text-emerald-800/80">Next: Record laboratory findings or print tube barcode label</div>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{$entryUrl}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-all">
                <i class="fa-solid fa-square-poll-horizontal"></i> Proceed to Enter Results &rarr;
            </a>
            <a href="/portals/sample-collection.php?lab_no={$qCurrentLab}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs border border-slate-300 shadow-2xs transition-all">
                <i class="fa-solid fa-barcode text-slate-500"></i> Barcode
            </a>
            <a href="/portals/lab-entries/new.php" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-2xs transition-all">
                <i class="fa-solid fa-plus text-[#c2f13c]"></i> + New Booking
            </a>
        </div>
    </div>
HTML;

    $content .= '<div class="no-print mb-3 flex flex-wrap gap-2 items-center">'
        . report_actions($patient['phone'] ?? '', $reportUrl, 'bill-' . $currentLab)
        . '<a class="btn btn-secondary text-sm" href="' . e($entryUrl) . '">Enter Results</a>'
        . '</div>';
    $content .= '<div class="lab-bill-container">' . render_receipt_document($settings, $entry, $patient) . '</div>';
} elseif ($q === '' && $patientId === '') {
    $content .= card('<p class="p-6 text-slate-600">Use <strong>Search</strong> or the <strong>visit dropdown</strong> above to open a patient bill.</p>');
}

$portal = current_portal();
$activeKey = ($portal === 'imaging') ? 'receipt' : 'receipts';
render_page('Patient Bill', $portal, $activeKey, $content, true);
