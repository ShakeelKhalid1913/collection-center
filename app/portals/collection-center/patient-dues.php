<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$branch = current_user()['branch_id'] ?? 'CC-01';
$userId = current_user()['id'] ?? null;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'collect_payment') {
    $labNo = trim($_POST['lab_no'] ?? '');
    $payAmount = (float)($_POST['pay_amount'] ?? 0);
    $payMode = $_POST['payment_mode'] ?? 'Cash';
    $notes = trim($_POST['notes'] ?? '');

    $res = dues_repo()->collectDue($labNo, $payAmount, $payMode, $notes, $userId, $orgId);
    if ($res['success']) {
        $message = flash_success("Payment of PKR " . number_format($res['paid'], 2) . " received for case {$labNo}! Receipt: <strong>{$res['receipt_no']}</strong>");
    } else {
        $message = flash_error($res['error'] ?? 'Could not collect due.');
    }
}

$search = trim($_GET['q'] ?? '');
$dues = dues_repo()->getDuesList($orgId, $search, $branch);
$stats = dues_repo()->getDuesStats($orgId);

$rows = [];
foreach ($dues as $d) {
    $mr = (string)($d['patient_no'] ?: $d['patient_id']);
    $pName = trim(($d['patient_title'] ?? '') . ' ' . $d['patient_name']);
    $balance = (float)$d['balance_due'];

    $collectForm = <<<HTML
    <form method="post" class="flex items-center gap-1.5" onsubmit="return confirm('Collect PKR ' + this.pay_amount.value + ' for {$d['lab_no']}?');">
        <input type="hidden" name="action" value="collect_payment">
        <input type="hidden" name="lab_no" value="{$d['lab_no']}">
        <input type="number" name="pay_amount" value="{$balance}" max="{$balance}" min="1" step="0.01" class="w-24 px-2 py-1 text-xs font-mono font-bold border border-slate-300 rounded-lg">
        <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-900 text-white text-xs font-bold whitespace-nowrap shadow-sm">
            <i class="fa-solid fa-check text-[#c2f13c] mr-1"></i> Receive
        </button>
        <a href="/portals/collection-center/receipts.php?lab_no={$d['lab_no']}" class="p-1 text-slate-500 hover:text-slate-800 text-xs" title="Receipt">
            <i class="fa-solid fa-receipt"></i>
        </a>
    </form>
HTML;

    $rows[] = [
        '<strong class="text-teal-800 font-mono text-xs">' . e($d['lab_no']) . '</strong>',
        '<div><strong class="text-slate-900 block">' . e($pName) . '</strong><span class="text-[11px] font-mono text-slate-400">MR: ' . e($mr) . ($d['phone'] ? ' &middot; ' . e($d['phone']) : '') . '</span></div>',
        '<span class="text-xs text-slate-600 font-mono">' . e(date('d-M-Y', strtotime($d['created_at'] ?? 'now'))) . '</span>',
        '<span class="font-mono text-xs text-emerald-700">PKR ' . number_format((float)$d['paid'], 2) . '</span>',
        '<strong class="text-rose-600 font-bold font-mono text-sm">PKR ' . number_format($balance, 2) . '</strong>',
        $collectForm,
    ];
}

$content = page_header('Patient Due Balances', 'Collect and clear outstanding patient balances at this collection center.');
$content .= $message . card(
    filter_bar([form_field('Search Patient / Case', 'q', 'search', $search, 'Search name, MR, phone...')]) .
    (empty($rows)
        ? '<div class="p-8 text-center text-slate-500 font-medium">No pending patient dues at this collection center.</div>'
        : data_table(['Case No', 'Patient & MR', 'Date', 'Paid', 'Pending Due', 'Collect Balance'], $rows))
);

render_page('Patient Dues', 'collection-center', 'patient-dues', $content);
