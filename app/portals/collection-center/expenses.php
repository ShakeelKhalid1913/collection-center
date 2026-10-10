<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$branch = current_user()['branch_id'] ?? 'CC-01';
$message = '';

if (isset($_GET['delete'])) {
    $delId = trim((string)$_GET['delete']);
    if (expense_repo()->delete($delId, $orgId)) {
        header('Location: /portals/collection-center/expenses.php?deleted=1');
        exit;
    }
    $message = flash_error('Could not delete expense record.');
}

if (isset($_GET['deleted'])) {
    $message = flash_success('Expense record deleted successfully.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);

    if ($title === '' || $amount <= 0) {
        $message = flash_error('Please enter a valid title and amount.');
    } else {
        $ok = expense_repo()->create([
            'organization_id' => $orgId,
            'branch' => $branch,
            'title' => $title,
            'category' => $_POST['category'] ?? 'Daily Petty Cash',
            'amount' => $amount,
            'payment_mode' => $_POST['payment_mode'] ?? 'Cash',
            'receipt_no' => trim($_POST['receipt_no'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
            'expense_date' => !empty($_POST['expense_date']) ? $_POST['expense_date'] : date('Y-m-d'),
            'created_by' => current_user()['id'] ?? null,
        ]);

        if ($ok) {
            $message = flash_success("Expense of PKR " . number_format($amount, 2) . " recorded.");
        } else {
            $message = flash_error('Failed to save expense.');
        }
    }
}

$expenses = expense_repo()->getAll($orgId, ['branch' => $branch]);
$stats = expense_repo()->getStats($orgId);

$categories = [
    'Daily Petty Cash' => 'Daily Petty Cash',
    'Courier & Sample Transit' => 'Courier & Sample Transit',
    'Utilities & Tea/Refreshment' => 'Utilities & Tea/Refreshment',
    'Stationery & Printing' => 'Stationery & Printing',
    'Consumables (Syringes/Lancets)' => 'Consumables (Syringes/Lancets)',
    'Other' => 'Other',
];

$rows = [];
$totalSpent = 0.0;
foreach ($expenses as $exp) {
    $totalSpent += (float)$exp['amount'];
    $delUrl = '/portals/collection-center/expenses.php?delete=' . urlencode($exp['id']);
    $rows[] = [
        '<span class="font-mono text-xs font-semibold text-slate-600">' . e(date('d-M-Y', strtotime($exp['expense_date']))) . '</span>',
        '<strong class="text-slate-900">' . e($exp['title']) . '</strong>',
        '<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">' . e($exp['category']) . '</span>',
        '<span class="font-mono text-xs text-slate-500">' . e($exp['receipt_no'] ?: '—') . '</span>',
        '<strong class="text-rose-600 font-bold font-mono text-sm">PKR ' . number_format((float)$exp['amount'], 2) . '</strong>',
        '<a href="' . $delUrl . '" onclick="return confirm(\'Delete this expense?\')" class="text-rose-600 hover:text-rose-800 text-xs font-bold p-1"><i class="fa-solid fa-trash-can"></i></a>',
    ];
}

$addForm = card(
    panel_head('Record Collection Center Expense', 'Petty cash, courier, refreshments & stationery') .
    '<form method="post" class="p-4 sm:p-6">' .
    '<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">' .
    form_field('Expense Title', 'title', 'text', null, 'e.g. Courier dispatch to HQ') .
    select_field('Category', 'category', $categories, 'Daily Petty Cash') .
    form_field('Amount (PKR)', 'amount', 'number', null, '0.00', false, 'step="0.01"') .
    select_field('Payment Mode', 'payment_mode', ['Cash' => 'Cash', 'Online' => 'Online', 'JazzCash' => 'JazzCash'], 'Cash') .
    form_field('Voucher No', 'receipt_no', 'text', null, 'Optional', true) .
    form_field('Expense Date', 'expense_date', 'date', date('Y-m-d')) .
    '<div class="sm:col-span-2">' .
    form_field('Notes / Remarks', 'notes', 'text', null, 'Optional rider name / receipt notes', true) .
    '</div>' .
    '</div>' .
    '<div class="mt-4 flex justify-end">' .
    btn_submit('Record Expense', '', 'fa-solid fa-plus') .
    '</div>' .
    '</form>'
);

$content = page_header('Center Expenses', 'Manage petty cash, sample transport costs, and daily expenses for this collection center.');
$content .= $message . '<div class="mb-6">' . $addForm . '</div>' . card(
    panel_head('Expense Entries (Total: PKR ' . number_format($totalSpent, 2) . ')') .
    (empty($rows)
        ? '<div class="p-6 text-center text-slate-500">No expenses recorded for this collection center yet.</div>'
        : data_table(['Date', 'Title', 'Category', 'Voucher', 'Amount', ''], $rows))
);

render_page('Expenses', 'collection-center', 'expenses', $content);
