<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$userId = current_user()['id'] ?? null;
$message = '';

// Handle Payment Collection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'collect_payment') {
    $labNo = trim($_POST['lab_no'] ?? '');
    $payAmount = (float)($_POST['pay_amount'] ?? 0);
    $payMode = $_POST['payment_mode'] ?? 'Cash';
    $notes = trim($_POST['notes'] ?? '');

    $res = dues_repo()->collectDue($labNo, $payAmount, $payMode, $notes, $userId, $orgId);
    if ($res['success']) {
        $message = flash_success("Payment of PKR " . number_format($res['paid'], 2) . " received for case {$labNo}! Receipt No: <strong>{$res['receipt_no']}</strong> (Remaining Due: PKR " . number_format($res['remaining_due'], 2) . ")");
    } else {
        $message = flash_error($res['error'] ?? 'Could not record due payment.');
    }
}

$search = trim($_GET['q'] ?? '');
$branch = trim($_GET['branch'] ?? '');
$tab = $_GET['tab'] ?? 'pending';

$dues = dues_repo()->getDuesList($orgId, $search, $branch);
$stats = dues_repo()->getDuesStats($orgId);
$history = dues_repo()->getPaymentHistory($orgId, 50);

$kpis = <<<HTML
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-card">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Outstanding Dues</span>
            <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-sm font-bold"><i class="fa-solid fa-hand-holding-dollar"></i></div>
        </div>
        <p class="text-2xl font-black text-rose-600 tracking-tight mt-2">PKR {$stats['total_due']}</p>
        <span class="text-[11px] font-semibold text-slate-400">Total unpaid patient receivables</span>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-card">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Pending Patients / Cases</span>
            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm font-bold"><i class="fa-solid fa-users"></i></div>
        </div>
        <p class="text-2xl font-black text-slate-900 tracking-tight mt-2">{$stats['pending_cases']}</p>
        <span class="text-[11px] font-semibold text-slate-400">Cases with balance > PKR 0</span>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-card">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Recovered Today</span>
            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-bold"><i class="fa-solid fa-circle-check"></i></div>
        </div>
        <p class="text-2xl font-black text-emerald-700 tracking-tight mt-2">PKR {$stats['recovered_today']}</p>
        <span class="text-[11px] font-semibold text-slate-400">Collected today from pending dues</span>
    </div>
</div>
HTML;

$tabPendingActive = $tab === 'pending' ? 'border-slate-900 text-slate-900 bg-white font-extrabold shadow-sm' : 'border-transparent text-slate-500 hover:text-slate-800';
$tabHistoryActive = $tab === 'history' ? 'border-slate-900 text-slate-900 bg-white font-extrabold shadow-sm' : 'border-transparent text-slate-500 hover:text-slate-800';

$tabsNav = <<<HTML
<div class="flex items-center gap-2 p-1.5 bg-slate-100 rounded-2xl mb-6 max-w-sm border border-slate-200">
    <a href="/portals/main-lab/patient-dues.php?tab=pending" class="flex-1 text-center py-2 px-3 rounded-xl text-xs sm:text-sm font-bold transition-all {$tabPendingActive}">
        <i class="fa-solid fa-clock-rotate-left mr-1.5"></i> Pending Dues ({$stats['pending_cases']})
    </a>
    <a href="/portals/main-lab/patient-dues.php?tab=history" class="flex-1 text-center py-2 px-3 rounded-xl text-xs sm:text-sm font-bold transition-all {$tabHistoryActive}">
        <i class="fa-solid fa-receipt mr-1.5"></i> Recovery Log
    </a>
</div>
HTML;

if ($tab === 'history') {
    $histRows = [];
    foreach ($history as $h) {
        $histRows[] = [
            '<span class="font-mono text-xs font-bold text-slate-900">' . e($h['receipt_no']) . '</span>',
            '<span class="font-mono text-xs font-semibold text-slate-600">' . e(date('d-M-Y H:i', strtotime($h['payment_date']))) . '</span>',
            '<strong class="text-teal-800 font-mono text-xs">' . e($h['lab_no']) . '</strong>',
            '<span class="font-bold text-slate-900">' . e($h['patient_name']) . '</span>',
            '<span class="font-mono text-xs text-slate-500">PKR ' . number_format((float)$h['previous_due'], 2) . '</span>',
            '<strong class="text-emerald-700 font-bold font-mono text-xs">PKR ' . number_format((float)$h['amount_paid'], 2) . '</strong>',
            '<span class="font-mono text-xs font-bold text-slate-700">PKR ' . number_format((float)$h['remaining_due'], 2) . '</span>',
            '<span class="text-xs text-slate-600 font-medium">' . e($h['payment_mode']) . '</span>',
        ];
    }

    $tabContent = card(
        panel_head('Due Payments Collection Log') .
        (empty($histRows)
            ? '<div class="p-8 text-center text-slate-500 font-medium">No due recovery payments recorded yet.</div>'
            : data_table(['Receipt No', 'Date & Time', 'Case No', 'Patient', 'Previous Due', 'Paid Now', 'Remaining', 'Mode'], $histRows))
    );
} else {
    $rows = [];
    foreach ($dues as $d) {
        $mr = (string)($d['patient_no'] ?: $d['patient_id']);
        $pName = trim(($d['patient_title'] ?? '') . ' ' . $d['patient_name']);
        $balance = (float)$d['balance_due'];
        $gross = (float)$d['amount'];
        $paid = (float)$d['paid'];
        $disc = (float)$d['discount'];

        $collectForm = <<<HTML
        <form method="post" class="flex items-center gap-1.5" onsubmit="return confirm('Confirm payment collection of PKR ' + this.pay_amount.value + ' for {$d['lab_no']}?');">
            <input type="hidden" name="action" value="collect_payment">
            <input type="hidden" name="lab_no" value="{$d['lab_no']}">
            <input type="number" name="pay_amount" value="{$balance}" max="{$balance}" min="1" step="0.01" class="w-24 px-2 py-1 text-xs font-mono font-bold border border-slate-300 rounded-lg focus:outline-none focus:border-slate-900">
            <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold whitespace-nowrap shadow-sm">
                <i class="fa-solid fa-check text-[#c2f13c] mr-1"></i> Collect
            </button>
            <a href="/portals/main-lab/receipts.php?lab_no={$d['lab_no']}" class="p-1 text-slate-500 hover:text-slate-800 text-xs" title="View Bill">
                <i class="fa-solid fa-receipt"></i>
            </a>
        </form>
HTML;

        $rows[] = [
            '<strong class="text-teal-800 font-mono text-xs">' . e($d['lab_no']) . '</strong>',
            '<div><strong class="text-slate-900 block">' . e($pName) . '</strong><span class="text-[11px] font-mono text-slate-400">MR: ' . e($mr) . ($d['phone'] ? ' &middot; ' . e($d['phone']) : '') . '</span></div>',
            '<span class="text-xs text-slate-600 font-mono">' . e(date('d-M-Y', strtotime($d['created_at'] ?? 'now'))) . '</span>',
            '<span class="font-mono text-xs text-slate-600">PKR ' . number_format($gross, 2) . '</span>',
            '<span class="font-mono text-xs text-slate-400">PKR ' . number_format($disc, 2) . '</span>',
            '<span class="font-mono text-xs text-emerald-700">PKR ' . number_format($paid, 2) . '</span>',
            '<strong class="text-rose-600 font-bold font-mono text-sm">PKR ' . number_format($balance, 2) . '</strong>',
            $collectForm,
        ];
    }

    $filterBar = filter_bar([
        form_field('Search Patient / Case', 'q', 'search', $search, 'Search name, phone, MR, lab no...'),
        form_field('Branch', 'branch', 'text', $branch, 'Filter by branch code', true),
    ]);

    $tabContent = card(
        $filterBar .
        (empty($rows)
            ? '<div class="p-8 text-center text-slate-500 font-medium">All patient dues are cleared! No outstanding receivables found.</div>'
            : data_table(['Case No', 'Patient & MR', 'Date', 'Gross', 'Discount', 'Paid', 'Pending Due', 'Collect Payment'], $rows))
    );
}

$content = page_header('Patient Dues Management', 'Track outstanding balances, patient payment receivables, and recover pending dues.');
$content .= $message . $kpis . $tabsNav . $tabContent;

render_page('Patient Dues', 'main-lab', 'patient-dues', $content);
