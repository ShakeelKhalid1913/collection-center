<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$message = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $delId = trim((string)$_GET['delete']);
    if (expense_repo()->delete($delId, $orgId)) {
        header('Location: /portals/main-lab/expenses.php?deleted=1');
        exit;
    }
    $message = flash_error('Could not delete expense record.');
}

if (isset($_GET['deleted'])) {
    $message = flash_success('Expense record deleted successfully.');
}

// Handle Add Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);

    if ($title === '' || $amount <= 0) {
        $message = flash_error('Please enter a valid title and amount greater than 0.');
    } else {
        $ok = expense_repo()->create([
            'organization_id' => $orgId,
            'branch' => trim($_POST['branch'] ?? 'Main Lab'),
            'title' => $title,
            'category' => $_POST['category'] ?? 'Other',
            'amount' => $amount,
            'payment_mode' => $_POST['payment_mode'] ?? 'Cash',
            'receipt_no' => trim($_POST['receipt_no'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
            'expense_date' => !empty($_POST['expense_date']) ? $_POST['expense_date'] : date('Y-m-d'),
            'created_by' => current_user()['id'] ?? null,
        ]);

        if ($ok) {
            $message = flash_success("Expense of PKR " . number_format($amount, 2) . " for '{$title}' recorded successfully.");
        } else {
            $message = flash_error('Failed to save expense record.');
        }
    }
}

// Filter handling
$filters = [
    'category' => trim($_GET['category'] ?? ''),
    'from_date' => trim($_GET['from_date'] ?? ''),
    'to_date' => trim($_GET['to_date'] ?? ''),
    'q' => trim($_GET['q'] ?? ''),
];

$expenses = expense_repo()->getAll($orgId, $filters);
$stats = expense_repo()->getStats($orgId);

$categories = [
    '' => 'All Categories',
    'Lab Reagents & Consumables' => 'Lab Reagents & Consumables',
    'Utilities & Electricity' => 'Utilities & Electricity',
    'Staff Salaries & Overtime' => 'Staff Salaries & Overtime',
    'Clinic/Lab Rent' => 'Clinic/Lab Rent',
    'Machine Maintenance & Servicing' => 'Machine Maintenance & Servicing',
    'Courier & Sample Transit' => 'Courier & Sample Transit',
    'Daily Petty Cash' => 'Daily Petty Cash',
    'Marketing & Printing' => 'Marketing & Printing',
    'Other' => 'Other',
];

$paymentModes = [
    'Cash' => 'Cash',
    'Bank Transfer' => 'Bank Transfer',
    'EasyPaisa / JazzCash' => 'EasyPaisa / JazzCash',
    'Cheque' => 'Cheque',
];

$rows = [];
$totalFiltered = 0.0;
foreach ($expenses as $exp) {
    $totalFiltered += (float)$exp['amount'];
    $delUrl = '/portals/main-lab/expenses.php?delete=' . urlencode($exp['id']);
    
    $rows[] = [
        '<span class="font-mono text-xs font-semibold text-slate-600">' . e(date('d-M-Y', strtotime($exp['expense_date']))) . '</span>',
        '<div class="font-bold text-slate-900">' . e($exp['title']) . ($exp['notes'] ? '<p class="text-[11px] text-slate-400 font-normal truncate max-w-xs">' . e($exp['notes']) . '</p>' : '') . '</div>',
        '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">' . e($exp['category']) . '</span>',
        '<span class="font-mono text-xs text-slate-500">' . e($exp['receipt_no'] ?: '—') . '</span>',
        '<span class="text-xs text-slate-600 font-medium">' . e($exp['payment_mode']) . '</span>',
        '<strong class="text-rose-600 font-bold font-mono text-sm">PKR ' . number_format((float)$exp['amount'], 2) . '</strong>',
        '<a href="' . $delUrl . '" onclick="return confirm(\'Are you sure you want to delete this expense entry?\')" class="text-rose-600 hover:text-rose-800 text-xs font-bold p-1"><i class="fa-solid fa-trash-can"></i></a>',
    ];
}

$statCards = <<<HTML
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-card">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Today's Expenses</span>
            <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-sm font-bold"><i class="fa-solid fa-receipt"></i></div>
        </div>
        <p class="text-2xl font-black text-rose-600 tracking-tight mt-2">PKR {$stats['today_total']}</p>
        <span class="text-[11px] font-semibold text-slate-400">Total spent today</span>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-card">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">This Month</span>
            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm font-bold"><i class="fa-solid fa-calendar-days"></i></div>
        </div>
        <p class="text-2xl font-black text-slate-900 tracking-tight mt-2">PKR {$stats['month_total']}</p>
        <span class="text-[11px] font-semibold text-slate-400">{$stats['month_count']} expense entries recorded</span>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-card">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Top Category</span>
            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm font-bold"><i class="fa-solid fa-chart-pie"></i></div>
        </div>
        <p class="text-lg font-black text-slate-900 tracking-tight mt-2 truncate">{$stats['top_category']}</p>
        <span class="text-[11px] font-semibold text-slate-400">Highest expenditure</span>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-card">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Filtered Total</span>
            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-bold"><i class="fa-solid fa-calculator"></i></div>
        </div>
        <p class="text-2xl font-black text-emerald-700 tracking-tight mt-2">PKR {$totalFiltered}</p>
        <span class="text-[11px] font-semibold text-slate-400">Sum of listed rows below</span>
    </div>
</div>
HTML;

$addForm = card(
    panel_head('Record New Expense', 'Add daily operating cost or reagent purchase voucher') .
    '<form method="post" class="p-4 sm:p-6">' .
    '<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">' .
    form_field('Expense Title', 'title', 'text', null, 'e.g. Centrifuge servicing / Reagents') .
    select_field('Category', 'category', array_slice($categories, 1), 'Lab Reagents & Consumables') .
    form_field('Amount (PKR)', 'amount', 'number', null, '0.00', false, 'step="0.01"') .
    select_field('Payment Mode', 'payment_mode', $paymentModes, 'Cash') .
    form_field('Voucher / Slip No', 'receipt_no', 'text', null, 'e.g. VCH-9921', true) .
    form_field('Expense Date', 'expense_date', 'date', date('Y-m-d')) .
    form_field('Branch / Center', 'branch', 'text', 'Main Lab', 'Branch name', true) .
    form_field('Notes / Remarks', 'notes', 'text', null, 'Optional detail / supplier name', true) .
    '</div>' .
    '<div class="mt-4 flex justify-end">' .
    btn_submit('Save Expense Entry', '', 'fa-solid fa-plus') .
    '</div>' .
    '</form>'
);

$filterBar = filter_bar([
    form_field('Search', 'q', 'search', $filters['q'], 'Search title, slip, notes...'),
    select_field('Category', 'category', $categories, $filters['category']),
    form_field('From Date', 'from_date', 'date', $filters['from_date']),
    form_field('To Date', 'to_date', 'date', $filters['to_date']),
]);

$tableCard = card(
    $filterBar .
    (empty($rows)
        ? '<div class="p-8 text-center text-slate-500 font-medium">No expense records found matching current filters.</div>'
        : data_table(['Date', 'Title & Notes', 'Category', 'Slip / Voucher', 'Payment', 'Amount', ''], $rows))
);

$content = page_header('Lab Expenses', 'Track reagents, utility bills, maintenance, petty cash, and daily lab operational costs.');
$content .= $message . $statCards . '<div class="mb-6">' . $addForm . '</div>' . $tableCard;

render_page('Expenses', 'main-lab', 'expenses', $content);
