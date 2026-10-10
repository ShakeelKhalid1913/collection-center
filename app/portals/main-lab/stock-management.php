<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$message = '';
$tab = $_GET['tab'] ?? 'stock';

// Handle Stock Adjustment (+/-)
if (isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
    $itemId = trim($_POST['item_id'] ?? '');
    $adj = (int)($_POST['adjustment'] ?? 0);
    if ($itemId !== '' && $adj !== 0) {
        if (stock_repo()->adjustStock($itemId, $adj, $orgId)) {
            $message = flash_success("Stock adjusted successfully (" . ($adj > 0 ? "+{$adj}" : "{$adj}") . ").");
        } else {
            $message = flash_error('Failed to adjust stock.');
        }
    }
}

// Handle Add Item
if (isset($_POST['action']) && $_POST['action'] === 'add_item') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $message = flash_error('Item / Reagent name is required.');
    } else {
        $ok = stock_repo()->addItem([
            'organization_id' => $orgId,
            'name' => $name,
            'category' => $_POST['category'] ?? 'Hematology Reagents',
            'unit' => $_POST['unit'] ?? 'Units',
            'quantity' => (int)($_POST['quantity'] ?? 0),
            'min_level' => (int)($_POST['min_level'] ?? 10),
            'unit_price' => (float)($_POST['unit_price'] ?? 0),
            'supplier' => trim($_POST['supplier'] ?? ''),
            'expiry_date' => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
        ]);
        if ($ok) {
            $message = flash_success("New stock item '{$name}' added to inventory.");
        } else {
            $message = flash_error('Could not save item.');
        }
    }
}

// Handle Purchase Return
if (isset($_POST['action']) && $_POST['action'] === 'create_return') {
    $itemId = trim($_POST['item_id'] ?? '');
    $item = stock_repo()->getItemById($itemId, $orgId);
    $qty = max(1, (int)($_POST['quantity'] ?? 1));

    if (!$item) {
        $message = flash_error('Please select an item from stock.');
    } elseif ($qty > $item['quantity']) {
        $message = flash_error("Cannot return {$qty} items; only {$item['quantity']} currently in stock.");
    } else {
        $refund = (float)($_POST['refund_amount'] ?? ($qty * (float)$item['unit_price']));
        $ok = stock_repo()->createReturn([
            'organization_id' => $orgId,
            'item_id' => $item['id'],
            'item_name' => $item['name'],
            'quantity' => $qty,
            'reason' => $_POST['reason'] ?? 'Damaged / Defective',
            'return_date' => !empty($_POST['return_date']) ? $_POST['return_date'] : date('Y-m-d'),
            'refund_amount' => $refund,
            'supplier' => trim($_POST['supplier'] ?? ($item['supplier'] ?? '')),
            'created_by' => current_user()['id'] ?? null,
        ]);

        if ($ok) {
            $tab = 'returns';
            $message = flash_success("Purchase return recorded for {$qty} x {$item['name']}. Stock deducted automatically.");
        } else {
            $message = flash_error('Failed to log purchase return.');
        }
    }
}

$stats = stock_repo()->getStats($orgId);
$search = trim($_GET['q'] ?? '');
$categoryFilter = trim($_GET['cat'] ?? '');
$items = stock_repo()->getItems($orgId, $search, $categoryFilter);
$returns = stock_repo()->getReturns($orgId);

$categories = [
    'Hematology Reagents' => 'Hematology Reagents',
    'Biochemistry' => 'Biochemistry',
    'Consumables' => 'Consumables (Lancets, Strips, Tips)',
    'Vials & Tubes' => 'Vials & Tubes',
    'Serology / Rapid Kits' => 'Serology / Rapid Kits',
    'Microbiology Media' => 'Microbiology Media',
    'Other Supplies' => 'Other Supplies',
];

$reasons = [
    'Damaged / Broken packaging' => 'Damaged / Broken packaging',
    'Expired before delivery / Short expiry' => 'Expired before delivery / Short expiry',
    'Failed Quality Control / Inaccurate controls' => 'Failed Quality Control / Inaccurate controls',
    'Wrong Item / Specification mismatch' => 'Wrong Item / Specification mismatch',
    'Overstocked / Excess order' => 'Overstocked / Excess order',
    'Recalled by manufacturer' => 'Recalled by manufacturer',
];

// Stats KPI banner
$kpis = <<<HTML
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-card">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Stock Items</span>
            <div class="w-9 h-9 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center text-sm font-bold"><i class="fa-solid fa-boxes-stacked"></i></div>
        </div>
        <p class="text-2xl font-black text-slate-900 tracking-tight mt-2">{$stats['total_items']}</p>
        <span class="text-[11px] font-semibold text-slate-400">Active reagents & consumables</span>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-card">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Stock Value</span>
            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-bold"><i class="fa-solid fa-vault"></i></div>
        </div>
        <p class="text-2xl font-black text-emerald-700 tracking-tight mt-2">PKR {$stats['total_stock_value']}</p>
        <span class="text-[11px] font-semibold text-slate-400">Estimated inventory value</span>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-card">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Low Stock Alerts</span>
            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm font-bold"><i class="fa-solid fa-triangle-exclamation"></i></div>
        </div>
        <p class="text-2xl font-black text-amber-600 tracking-tight mt-2">{$stats['low_stock_count']}</p>
        <span class="text-[11px] font-semibold text-slate-400">Items at or below minimum level</span>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-card">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Purchase Returns</span>
            <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-sm font-bold"><i class="fa-solid fa-rotate-left"></i></div>
        </div>
        <p class="text-2xl font-black text-rose-600 tracking-tight mt-2">{$stats['total_returns']}</p>
        <span class="text-[11px] font-semibold text-slate-400">PKR {$stats['total_refunded']} refunded/credited</span>
    </div>
</div>
HTML;

// Navigation Tabs
$tabStockActive = $tab === 'stock' ? 'border-slate-900 text-slate-900 bg-white font-extrabold shadow-sm' : 'border-transparent text-slate-500 hover:text-slate-800';
$tabReturnsActive = $tab === 'returns' ? 'border-slate-900 text-slate-900 bg-white font-extrabold shadow-sm' : 'border-transparent text-slate-500 hover:text-slate-800';

$tabsNav = <<<HTML
<div class="flex items-center gap-2 p-1.5 bg-slate-100 rounded-2xl mb-6 max-w-md border border-slate-200">
    <a href="/portals/main-lab/stock-management.php?tab=stock" class="flex-1 text-center py-2 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all {$tabStockActive}">
        <i class="fa-solid fa-boxes-stacked mr-1.5"></i> Inventory & Reagents
    </a>
    <a href="/portals/main-lab/stock-management.php?tab=returns" class="flex-1 text-center py-2 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all {$tabReturnsActive}">
        <i class="fa-solid fa-rotate-left mr-1.5"></i> Purchase Returns ({$stats['total_returns']})
    </a>
</div>
HTML;

if ($tab === 'returns') {
    // Return Rows
    $returnRows = [];
    foreach ($returns as $ret) {
        $returnRows[] = [
            '<span class="font-mono text-xs font-bold text-slate-900">' . e($ret['id']) . '</span>',
            '<span class="font-mono text-xs text-slate-600">' . e(date('d-M-Y', strtotime($ret['return_date']))) . '</span>',
            '<strong class="text-slate-900">' . e($ret['item_name']) . '</strong>',
            '<span class="font-bold text-rose-600 font-mono text-xs">' . (int)$ret['quantity'] . ' units</span>',
            '<span class="text-xs text-slate-600">' . e($ret['reason']) . '</span>',
            '<span class="text-xs text-slate-500">' . e($ret['supplier'] ?: '—') . '</span>',
            '<span class="font-bold text-emerald-700 font-mono text-xs">PKR ' . number_format((float)$ret['refund_amount'], 2) . '</span>',
            '<span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">Completed</span>',
        ];
    }

    $itemOpts = ['' => '— Select item to return —'];
    foreach ($items as $it) {
        $itemOpts[$it['id']] = $it['name'] . ' (In stock: ' . $it['quantity'] . ' ' . $it['unit'] . ')';
    }

    $returnForm = card(
        panel_head('Process New Purchase Return', 'Return damaged, expired, or defective reagents to supplier') .
        '<form method="post" class="p-4 sm:p-6">' .
        '<input type="hidden" name="action" value="create_return">' .
        '<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">' .
        select_field('Select Inventory Item', 'item_id', $itemOpts) .
        form_field('Quantity to Return', 'quantity', 'number', '1', '1', false, 'min="1"') .
        select_field('Return Reason', 'reason', $reasons, 'Damaged / Broken packaging') .
        form_field('Supplier / Vendor', 'supplier', 'text', null, 'e.g. Roche / BD Vacutainer', true) .
        form_field('Return Date', 'return_date', 'date', date('Y-m-d')) .
        form_field('Refund / Credit (PKR)', 'refund_amount', 'number', null, '0.00', true, 'step="0.01"') .
        '</div>' .
        '<div class="mt-4 flex justify-end">' .
        btn_submit('Record Purchase Return & Deduct Stock', '', 'fa-solid fa-rotate-left') .
        '</div>' .
        '</form>'
    );

    $tabContent = '<div class="mb-6">' . $returnForm . '</div>' . card(
        panel_head('Purchase Return History') .
        (empty($returnRows)
            ? '<div class="p-8 text-center text-slate-500 font-medium">No purchase returns recorded.</div>'
            : data_table(['Return ID', 'Date', 'Item Returned', 'Quantity', 'Reason', 'Supplier', 'Credit/Refund', 'Status'], $returnRows))
    );
} else {
    // Inventory Rows
    $itemRows = [];
    foreach ($items as $it) {
        $qty = (int)$it['quantity'];
        $min = (int)$it['min_level'];
        $price = (float)$it['unit_price'];
        $val = $qty * $price;

        $badge = '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">' . $qty . ' ' . e($it['unit']) . '</span>';
        if ($qty === 0) {
            $badge = '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800">Out of Stock (0)</span>';
        } elseif ($qty <= $min) {
            $badge = '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 font-mono"><i class="fa-solid fa-triangle-exclamation mr-1"></i> ' . $qty . ' (Low)</span>';
        }

        $expDisplay = '—';
        if (!empty($it['expiry_date'])) {
            $daysLeft = (int)round((strtotime($it['expiry_date']) - time()) / 86400);
            $expDateFormatted = date('d-M-Y', strtotime($it['expiry_date']));
            if ($daysLeft < 0) {
                $expDisplay = '<span class="text-xs font-bold text-rose-600"><i class="fa-solid fa-circle-xmark mr-1"></i> Expired (' . $expDateFormatted . ')</span>';
            } elseif ($daysLeft <= 90) {
                $expDisplay = '<span class="text-xs font-bold text-amber-600"><i class="fa-solid fa-clock mr-1"></i> ' . $expDateFormatted . ' (' . $daysLeft . 'd)</span>';
            } else {
                $expDisplay = '<span class="text-xs font-medium text-slate-600">' . $expDateFormatted . '</span>';
            }
        }

        $adjustForm = '<form method="post" class="flex items-center gap-1.5">' .
            '<input type="hidden" name="action" value="adjust_stock">' .
            '<input type="hidden" name="item_id" value="' . e($it['id']) . '">' .
            '<button type="submit" name="adjustment" value="1" title="Add 1 Unit" class="w-6 h-6 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold flex items-center justify-center">+</button>' .
            '<button type="submit" name="adjustment" value="-1" title="Deduct 1 Unit" class="w-6 h-6 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold flex items-center justify-center">-</button>' .
            '</form>';

        $itemRows[] = [
            '<span class="font-mono text-xs font-bold text-slate-700">' . e($it['id']) . '</span>',
            '<div><strong class="text-slate-900 block">' . e($it['name']) . '</strong><span class="text-[11px] text-slate-400">' . e($it['category']) . '</span></div>',
            $badge,
            '<span class="font-mono text-xs text-slate-500">' . $min . '</span>',
            '<span class="font-mono text-xs text-slate-700">PKR ' . number_format($price, 2) . '</span>',
            '<span class="font-mono text-xs font-bold text-slate-900">PKR ' . number_format($val, 2) . '</span>',
            '<span class="text-xs text-slate-600">' . e($it['supplier'] ?: '—') . '</span>',
            $expDisplay,
            $adjustForm,
        ];
    }

    $addItemForm = card(
        panel_head('Add New Reagent / Consumable Item', 'Track quantity, reorder alerts, and expiry') .
        '<form method="post" class="p-4 sm:p-6">' .
        '<input type="hidden" name="action" value="add_item">' .
        '<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">' .
        form_field('Item / Reagent Name', 'name', 'text', null, 'e.g. EDTA Lavender Tubes') .
        select_field('Category', 'category', $categories, 'Hematology Reagents') .
        form_field('Packaging / Unit', 'unit', 'text', 'Tests', 'e.g. Kits / Bottles / Trays') .
        form_field('Initial Quantity', 'quantity', 'number', '10', '0') .
        form_field('Minimum Alert Level', 'min_level', 'number', '5', 'Low stock alert threshold') .
        form_field('Unit Cost Price (PKR)', 'unit_price', 'number', '0.00', '0.00', false, 'step="0.01"') .
        form_field('Supplier / Company', 'supplier', 'text', null, 'e.g. Sysmex / BD', true) .
        form_field('Expiry Date', 'expiry_date', 'date', null, '', true) .
        '</div>' .
        '<div class="mt-4 flex justify-end">' .
        btn_submit('Add Item to Stock', '', 'fa-solid fa-plus') .
        '</div>' .
        '</form>'
    );

    $catFilterOpts = ['' => 'All Categories'] + $categories;
    $filterBar = filter_bar([
        form_field('Search Reagent', 'q', 'search', $search, 'Search name, supplier, ID...'),
        select_field('Category', 'cat', $catFilterOpts, $categoryFilter),
    ]);

    $tabContent = '<div class="mb-6">' . $addItemForm . '</div>' . card(
        $filterBar .
        (empty($itemRows)
            ? '<div class="p-8 text-center text-slate-500 font-medium">No stock items found.</div>'
            : data_table(['Code', 'Item Name', 'In Stock', 'Min Alert', 'Unit Cost', 'Total Value', 'Supplier', 'Expiry', 'Quick Adj'], $itemRows))
    );
}

$content = page_header('Stock Management & Purchase Returns', 'Track laboratory reagents, collection tubes, consumables, low-stock alerts, and purchase returns.');
$content .= $message . $kpis . $tabsNav . $tabContent;

render_page('Stock Management', 'main-lab', 'stock', $content);
