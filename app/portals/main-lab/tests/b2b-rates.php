<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

require_auth('main-lab');

$user = current_user();
$orgId = $user['organization_id'] ?? 'ORG-001';
$message = '';
$q = trim((string)($_GET['q'] ?? ''));
$tab = trim((string)($_GET['tab'] ?? 'rates'));

// Handle B2B actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_permission('rates_edit');
    $action = $_POST['action'] ?? '';

    if ($action === 'update_b2b_price') {
        $testId = trim((string)($_POST['test_id'] ?? ''));
        $b2bPrice = (float)($_POST['b2b_price'] ?? 0);
        if ($testId !== '') {
            $ok = b2b_repo()->updateTestB2BPrice($testId, $b2bPrice, $orgId);
            $message = $ok ? flash_success('B2B wholesale rate updated successfully.') : flash_error('Could not update rate.');
        }
    } elseif ($action === 'bulk_b2b_discount') {
        $discountPct = (float)($_POST['discount_percent'] ?? 25.0);
        $ok = b2b_repo()->updateBulkDiscount($discountPct, $orgId);
        $message = flash_success("Applied {$discountPct}% wholesale discount to all catalog tests.");
    } elseif ($action === 'save_b2b_client') {
        $ok = b2b_repo()->saveClient($_POST, $orgId);
        $message = $ok ? flash_success('Partner referring lab saved successfully.') : flash_error('Could not save partner lab.');
        $tab = 'partners';
    } elseif ($action === 'delete_b2b_client') {
        $clientId = trim((string)($_POST['client_id'] ?? ''));
        if ($clientId !== '') {
            $ok = b2b_repo()->deleteClient($clientId, $orgId);
            $message = $ok ? flash_success('Partner referring lab removed.') : flash_error('Could not remove partner lab.');
            $tab = 'partners';
        }
    }
}

// B2B Rates tab
$rateRows = [];
$tests = b2b_repo()->getTestRates($orgId, $q);
foreach ($tests as $t) {
    $code = (string)$t['code'];
    $name = (string)$t['name'];
    $cat = (string)($t['category'] ?? 'General');
    $reg = (float)$t['price'];
    $b2b = (float)$t['b2b_price'];
    $id = (string)$t['id'];

    $savingPct = $reg > 0 ? round((($reg - $b2b) / $reg) * 100, 1) : 0;

    $rateRows[] = [
        '<span class="font-mono text-teal-800 font-bold bg-teal-50 border border-teal-200 px-2 py-0.5 rounded text-xs">' . e($code) . '</span>',
        '<div><strong class="text-slate-900 text-sm">' . e($name) . '</strong><div class="text-xs text-slate-500">' . e($cat) . '</div></div>',
        '<span class="text-slate-600 line-through text-xs font-semibold">Rs ' . number_format($reg, 0) . '</span>',
        '<span class="badge badge-teal text-xs font-bold">' . $savingPct . '% Off</span>',
        '<form method="post" class="flex flex-wrap items-center gap-2">' .
            '<input type="hidden" name="action" value="update_b2b_price">' .
            '<input type="hidden" name="test_id" value="' . e($id) . '">' .
            '<div class="relative flex items-center">' .
                '<span class="absolute left-2.5 text-xs text-slate-400 font-bold">Rs</span>' .
                '<input type="number" name="b2b_price" class="field !w-28 !pl-7 text-sm font-bold text-slate-900" min="0" step="1" value="' . (int)round($b2b) . '" required>' .
            '</div>' .
            '<button type="submit" class="btn btn-primary text-xs py-1.5 px-3"><i class="fa-solid fa-floppy-disk mr-1"></i> Update</button>' .
        '</form>',
    ];
}

// Partners tab
$clients = b2b_repo()->getAllClients($orgId);
$partnerRows = [];
foreach ($clients as $c) {
    $cid = (string)$c['id'];
    $disc = (float)$c['discount_percent'];
    $partnerRows[] = [
        '<span class="font-mono text-xs font-bold text-slate-700">' . e((string)($c['code'] ?: '—')) . '</span>',
        '<div><strong class="text-slate-900 text-sm">' . e((string)$c['name']) . '</strong><div class="text-xs text-slate-500">' . e((string)($c['address'] ?: '—')) . '</div></div>',
        '<div><span class="text-xs font-semibold text-slate-800">' . e((string)($c['contact_person'] ?: '—')) . '</span><div class="text-xs text-slate-500">' . e((string)($c['phone'] ?: '—')) . '</div></div>',
        '<span class="badge badge-purple text-xs font-bold">' . $disc . '% Default Discount</span>',
        '<form method="post" onsubmit="return confirm(\'Delete this partner lab?\');" class="inline">' .
            '<input type="hidden" name="action" value="delete_b2b_client">' .
            '<input type="hidden" name="client_id" value="' . e($cid) . '">' .
            '<button type="submit" class="btn btn-danger text-xs py-1 px-2.5"><i class="fa-solid fa-trash mr-1"></i> Remove</button>' .
        '</form>',
    ];
}

$headerActions = '<div class="flex items-center gap-2">'
    . '<a href="/portals/main-lab/tests/prices.php" class="btn btn-secondary text-xs"><i class="fa-solid fa-tags mr-1"></i> Walk-in Retail Rates</a>'
    . '<a href="/portals/main-lab/lab-entries/new.php?type=b2b" class="btn btn-primary text-xs"><i class="fa-solid fa-plus mr-1"></i> New B2B Entry</a>'
    . '</div>';

$content = page_header('B2B (Lab-to-Lab) Rates & Contracts', 'Dedicated wholesale test pricing for partner referring laboratories and hospital contracts.', $headerActions);
$content .= $message;

// Tabs
$ratesActive = $tab === 'rates' ? 'border-b-2 border-teal-600 text-teal-700 font-bold' : 'text-slate-500 hover:text-slate-800 font-medium';
$partnersActive = $tab === 'partners' ? 'border-b-2 border-teal-600 text-teal-700 font-bold' : 'text-slate-500 hover:text-slate-800 font-medium';
$clientCount = count($clients);

$content .= <<<HTML
<div class="flex items-center gap-6 border-b border-slate-200 mb-6 text-sm">
    <a href="?tab=rates" class="pb-3 px-1 transition-all {$ratesActive}">
        <i class="fa-solid fa-receipt mr-1.5"></i> B2B Wholesale Rate List
    </a>
    <a href="?tab=partners" class="pb-3 px-1 transition-all {$partnersActive}">
        <i class="fa-solid fa-handshake mr-1.5"></i> Partner Referring Labs ({$clientCount})
    </a>
</div>
HTML;

if ($tab === 'partners') {
    // Partner Labs View
    $content .= <<<HTML
    <div class="mb-6 bg-white border border-slate-200 rounded-2xl p-5 shadow-card">
        <h3 class="text-sm font-bold text-slate-800 mb-3 flex items-center gap-2">
            <i class="fa-solid fa-plus text-teal-600"></i> Add New Partner Referring Lab
        </h3>
        <form method="post" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <input type="hidden" name="action" value="save_b2b_client">
            <div>
                <label class="field-label text-xs">Partner Lab Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" class="field text-sm" placeholder="e.g. Shifa Clinical Lab" required>
            </div>
            <div>
                <label class="field-label text-xs">Partner Code</label>
                <input type="text" name="code" class="field text-sm font-mono uppercase" placeholder="e.g. SHIFA-01">
            </div>
            <div>
                <label class="field-label text-xs">Contact Person</label>
                <input type="text" name="contact_person" class="field text-sm" placeholder="e.g. Dr. Tariq">
            </div>
            <div>
                <label class="field-label text-xs">Phone Number</label>
                <input type="text" name="phone" class="field text-sm" placeholder="e.g. 0300 9876543">
            </div>
            <div class="sm:col-span-2">
                <label class="field-label text-xs">Address / Location</label>
                <input type="text" name="address" class="field text-sm" placeholder="e.g. Hospital Road, Faisalabad">
            </div>
            <div>
                <label class="field-label text-xs">Default Discount %</label>
                <input type="number" name="discount_percent" class="field text-sm" min="0" max="100" step="1" value="20">
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn btn-primary text-sm py-2 px-4 w-full">
                    <i class="fa-solid fa-check mr-1.5"></i> Save Partner Lab
                </button>
            </div>
        </form>
    </div>
    HTML;

    $content .= card(data_table(['Code', 'Partner Lab & Address', 'Contact Person & Phone', 'Contract Discount', 'Actions'], $partnerRows), 'overflow-hidden');
} else {
    // B2B Rates View
    $clearBtn = ($q !== '') ? '<a href="?tab=rates" class="btn btn-secondary text-xs">Clear</a>' : '';
    $content .= <<<HTML
    <div class="mb-5 flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
        <form method="get" class="flex items-center gap-2 flex-1 max-w-md">
            <input type="hidden" name="tab" value="rates">
            <div class="relative w-full">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                <input type="text" name="q" value="{$q}" class="field pl-8 text-xs w-full" placeholder="Search test name or code…">
            </div>
            <button type="submit" class="btn btn-secondary text-xs shrink-0">Search</button>
            {$clearBtn}
        </form>

        <!-- Bulk updater -->
        <form method="post" onsubmit="return confirm('Apply this discount across all tests in catalog?');" class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl p-2 shrink-0">
            <input type="hidden" name="action" value="bulk_b2b_discount">
            <span class="text-xs font-semibold text-slate-700">Bulk Discount:</span>
            <input type="number" name="discount_percent" class="field !w-16 text-xs text-center font-bold" min="5" max="80" value="25">
            <span class="text-xs font-bold text-slate-500">%</span>
            <button type="submit" class="btn btn-secondary text-xs py-1 px-2.5">Apply to All Tests</button>
        </form>
    </div>
    HTML;

    $content .= card(data_table(['Test Code', 'Test Name & Category', 'Retail Price', 'B2B Discount', 'Wholesale B2B Rate'], $rateRows), 'overflow-hidden');
}

render_page('B2B Rates', 'main-lab', 'tests-b2b', $content);
