<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

require_auth(['main_lab', 'imaging', 'admin', 'collection_center']);
$orgId = current_organization_id();
$message = '';

// Handle Package Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'create_package') {
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $price = (float)($_POST['price'] ?? 0);
        $regular = (float)($_POST['regular_price'] ?? 0);
        
        $selectedTests = $_POST['selected_tests'] ?? [];
        if (is_string($selectedTests)) {
            $selectedTests = array_filter(array_map('trim', explode(',', $selectedTests)));
        }
        $testsString = implode(', ', array_unique($selectedTests));

        if ($code === '' || $name === '') {
            $message = flash_error('Package Code and Package Name are required.');
        } elseif ($testsString === '') {
            $message = flash_error('Please select at least one test to include in the package.');
        } else {
            $res = test_repo()->createPackage([
                'code' => $code,
                'name' => $name,
                'tests' => $testsString,
                'price' => $price,
                'regular_price' => $regular > 0 ? $regular : $price,
                'organization_id' => $orgId,
            ]);

            if ($res['success']) {
                $message = flash_success("Test Package '{$name}' ({$code}) created successfully.");
            } else {
                $message = flash_error($res['error'] ?? 'Could not create package.');
            }
        }
    } elseif ($action === 'update_package') {
        $id = trim((string)($_POST['id'] ?? ''));
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $price = (float)($_POST['price'] ?? 0);
        $regular = (float)($_POST['regular_price'] ?? 0);

        $selectedTests = $_POST['selected_tests'] ?? [];
        if (is_string($selectedTests)) {
            $selectedTests = array_filter(array_map('trim', explode(',', $selectedTests)));
        }
        $testsString = implode(', ', array_unique($selectedTests));

        if ($id === '' || $code === '' || $name === '') {
            $message = flash_error('Package ID, Code, and Name are required.');
        } elseif ($testsString === '') {
            $message = flash_error('Please select at least one test for the package.');
        } else {
            $ok = test_repo()->updatePackage($id, [
                'code' => $code,
                'name' => $name,
                'tests' => $testsString,
                'price' => $price,
                'regular_price' => $regular > 0 ? $regular : $price,
            ], $orgId);

            $message = $ok
                ? flash_success("Package '{$name}' updated successfully.")
                : flash_error('Could not update package.');
        }
    } elseif ($action === 'delete_package') {
        $id = trim((string)($_POST['id'] ?? ''));
        if ($id !== '') {
            $ok = test_repo()->deletePackage($id, $orgId);
            $message = $ok
                ? flash_success('Package deleted successfully.')
                : flash_error('Could not delete package.');
        }
    }
}

// Fetch all catalog tests for the picker
$allTests = test_repo()->getTests($orgId);
$testCatalogJson = [];
$categories = [];
foreach ($allTests as $t) {
    $c = trim((string)($t['category'] ?? 'General'));
    if ($c === '') $c = 'General';
    $categories[$c] = true;
    $testCatalogJson[] = [
        'id' => (string)($t['id'] ?? ''),
        'code' => (string)($t['code'] ?? ''),
        'name' => (string)($t['name'] ?? ''),
        'category' => $c,
        'price' => (float)($t['price'] ?? 0),
    ];
}
$categoriesList = array_keys($categories);
sort($categoriesList);

// Fetch existing packages
$dbPackages = test_repo()->getPackages($orgId);
$pkgList = !empty($dbPackages) ? array_map(static fn($p) => [
    'id' => (string)($p['id'] ?? $p['code']),
    'code' => (string)$p['code'],
    'name' => (string)$p['name'],
    'tests' => (string)($p['tests_included'] ?? $p['tests'] ?? ''),
    'price' => (float)($p['price'] ?? 0),
    'regular' => (float)($p['regular_price'] ?? $p['regular'] ?? $p['price'] ?? 0),
], $dbPackages) : mock('mock_packages');

// Search & Filter
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $pkgList = array_filter($pkgList, function($pkg) use ($q) {
        $hay = mb_strtolower($pkg['name'] . ' ' . $pkg['code'] . ' ' . $pkg['tests']);
        return str_contains($hay, mb_strtolower($q));
    });
}

// Render Package Rows
$rows = [];
foreach ($pkgList as $pkg) {
    $pkgId = (string)$pkg['id'];
    $pkgCode = (string)$pkg['code'];
    $pkgName = (string)$pkg['name'];
    $testsRaw = (string)$pkg['tests'];
    $price = (float)$pkg['price'];
    $regular = (float)$pkg['regular'];
    $saving = max(0, $regular - $price);
    $savingPct = ($regular > 0 && $saving > 0) ? round(($saving / $regular) * 100) : 0;

    $testsArr = array_filter(array_map('trim', explode(',', $testsRaw)));
    $testCount = count($testsArr);
    
    $pillsHtml = '<div class="flex flex-wrap gap-1 max-w-md">';
    foreach (array_slice($testsArr, 0, 4) as $tName) {
        $pillsHtml .= '<span class="inline-block px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[11px] font-semibold border border-slate-200">' . e($tName) . '</span>';
    }
    if ($testCount > 4) {
        $rem = $testCount - 4;
        $pillsHtml .= '<span class="inline-block px-1.5 py-0.5 rounded-md bg-indigo-50 text-indigo-700 text-[10px] font-bold border border-indigo-200" title="' . e($testsRaw) . '">+' . $rem . ' more</span>';
    }
    $pillsHtml .= '</div>';

    $orderUrl = '/portals/lab-entries/new.php?pre_tests=' . urlencode($testsRaw) . '&pre_discount=' . urlencode((string)$saving);
    $testsJsonAttr = htmlspecialchars((string)json_encode(array_values($testsArr)), ENT_QUOTES, 'UTF-8');
    $pkgIdEsc = htmlspecialchars($pkgId, ENT_QUOTES, 'UTF-8');
    $pkgCodeEsc = htmlspecialchars($pkgCode, ENT_QUOTES, 'UTF-8');
    $pkgNameEsc = htmlspecialchars($pkgName, ENT_QUOTES, 'UTF-8');

    $actions = <<<HTML
    <div class="flex items-center gap-1.5 whitespace-nowrap">
        <a href="{$orderUrl}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-2xs transition-all" title="Order this package for a patient">
            <i class="fa-solid fa-flask-vial text-[11px]"></i> Order
        </a>
        <button type="button" 
                data-pkg-id="{$pkgIdEsc}"
                data-pkg-code="{$pkgCodeEsc}"
                data-pkg-name="{$pkgNameEsc}"
                data-pkg-price="{$price}"
                data-pkg-regular="{$regular}"
                data-pkg-tests="{$testsJsonAttr}"
                onclick="handleEditPackageClick(this)" 
                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs border border-slate-200 transition-all cursor-pointer" title="Edit Package">
            <i class="fa-solid fa-pen-to-square text-slate-500 text-[11px]"></i> Edit
        </button>
        <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete package \'{$pkgNameEsc}\'?');">
            <input type="hidden" name="action" value="delete_package">
            <input type="hidden" name="id" value="{$pkgIdEsc}">
            <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition-all cursor-pointer" title="Delete Package">
                <i class="fa-solid fa-trash-can text-xs"></i>
            </button>
        </form>
    </div>
HTML;

    $rows[] = [
        '<span class="font-mono font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-md text-xs border border-indigo-200">' . e($pkgCode) . '</span>',
        '<div><strong class="text-slate-900 text-sm block">' . e($pkgName) . '</strong><span class="text-[11px] text-slate-500">' . $testCount . ' bundled investigations</span></div>',
        $pillsHtml,
        '<div class="text-sm font-extrabold text-emerald-700">' . e(format_money($price)) . '</div>',
        '<div class="text-xs text-slate-400 line-through">' . e(format_money($regular)) . '</div>',
        $saving > 0 
            ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 text-xs font-bold border border-emerald-200"><i class="fa-solid fa-tag text-[10px]"></i> ' . e(format_money($saving)) . ' (' . $savingPct . '% OFF)</span>'
            : '<span class="text-xs text-slate-400">—</span>',
        $actions,
    ];
}

$headerActions = <<<HTML
<div class="flex items-center gap-2">
    <button type="button" onclick="openCreatePackageModal()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm transition-all shadow-md cursor-pointer">
        <i class="fa-solid fa-plus text-emerald-200"></i> + Create New Package
    </button>
    <a href="/portals/tests/rate-list.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-white hover:bg-slate-50 text-slate-800 font-bold text-xs sm:text-sm transition-all border border-slate-300 shadow-2xs">
        <i class="fa-solid fa-file-pdf text-rose-600"></i> Rate List (PDF)
    </a>
</div>
HTML;

$content = page_header('Test Packages', 'Create and manage bundled health panels, select included tests, and configure discounted pricing.', $headerActions);
$content .= $message;

// Search Bar
$content .= card(
    '<form method="get" class="flex flex-wrap items-center gap-3 p-4">' .
    '<div class="flex-1 min-w-[240px]">' .
    '<input type="search" name="q" value="' . e($q) . '" placeholder="Search packages by code, name, or included tests…" class="field text-sm" autofocus>' .
    '</div>' .
    '<button type="submit" class="btn btn-primary text-xs"><i class="fa-solid fa-magnifying-glass mr-1"></i> Search</button>' .
    ($q !== '' ? '<a href="/portals/tests/packages.php" class="btn btn-secondary text-xs">Clear</a>' : '') .
    '</form>'
);

// Packages Table
$content .= '<div class="mt-4">' . card(
    panel_head('All Configured Packages (' . count($rows) . ')') .
    (count($rows) > 0
        ? data_table(['Code', 'Package Name', 'Included Tests', 'Package Price', 'Regular Price', 'Patient Saving', 'Actions'], $rows)
        : '<p class="p-6 text-sm text-slate-500">No test packages found. Click <strong>+ Create New Package</strong> to add your first bundled test package.</p>'),
    'overflow-hidden'
) . '</div>';

// Interactive Create / Edit Modal
$catPillsHtml = '<button type="button" onclick="filterCatalogDept(\'\')" class="pkg-dept-pill active px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-900 text-white transition-all cursor-pointer">All</button>';
foreach ($categoriesList as $c) {
    $catPillsHtml .= '<button type="button" onclick="filterCatalogDept(\'' . e($c) . '\')" class="pkg-dept-pill px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 transition-all cursor-pointer">' . e($c) . '</button>';
}

// JSON encoded properly for direct JS consumption inside <script>
$catalogJsonStr = json_encode($testCatalogJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

$content .= <<<HTML
<!-- Interactive Package Builder Modal -->
<div id="package-modal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-xs overflow-y-auto p-4 sm:p-6 flex items-center justify-center" style="display: none;">
    <div class="relative bg-white border border-slate-200 rounded-3xl shadow-2xl max-w-4xl w-full max-h-[92vh] flex flex-col overflow-hidden animate-scale-in" onclick="event.stopPropagation()">
        
        <!-- Modal Header -->
        <div class="p-4 sm:p-5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-indigo-600 text-white inline-flex items-center justify-center font-bold text-sm shadow-xs">
                    <i class="fa-solid fa-boxes-packing"></i>
                </span>
                <div>
                    <h3 id="modal-title" class="text-base font-bold text-slate-900">Create Test Package</h3>
                    <p class="text-xs text-slate-500">Select investigations from the catalog, auto-calculate regular cost, and set bundle pricing.</p>
                </div>
            </div>
            <button type="button" onclick="closePackageModal()" class="p-2 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-200/60 transition-all cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Modal Body Form -->
        <form method="post" id="package-form" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4">
            <input type="hidden" name="action" id="form-action" value="create_package">
            <input type="hidden" name="id" id="pkg-id" value="">
            <input type="hidden" name="regular_price" id="pkg-regular-input" value="0">

            <!-- Code & Name Inputs -->
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="field-label text-xs font-bold text-slate-700" for="pkg-code">Package Code *</label>
                    <input type="text" id="pkg-code" name="code" class="field text-sm font-mono font-bold uppercase" placeholder="e.g. PKG-DIAB" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label text-xs font-bold text-slate-700" for="pkg-name">Package Name *</label>
                    <input type="text" id="pkg-name" name="name" class="field text-sm font-bold" placeholder="e.g. Diabetic Comprehensive Profile" required>
                </div>
            </div>

            <!-- Pricing Calculator Box -->
            <div class="p-4 bg-emerald-50/70 border border-emerald-200 rounded-2xl grid gap-3 sm:grid-cols-3 items-center">
                <div>
                    <span class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide block">Regular Total (Catalog Sum)</span>
                    <div id="pkg-regular-display" class="text-lg font-mono font-bold text-slate-600 line-through mt-0.5">PKR 0.00</div>
                    <span class="text-[10px] text-slate-500">Auto-sums as tests are selected</span>
                </div>
                <div>
                    <label class="text-[11px] font-bold text-emerald-950 uppercase tracking-wide block" for="pkg-price">Package Offer Price (PKR) *</label>
                    <input type="number" step="1" min="0" id="pkg-price" name="price" oninput="calculateSavings()" class="field text-base font-bold text-emerald-900 border-emerald-300 focus:border-emerald-600 mt-1" placeholder="e.g. 2500" required>
                </div>
                <div>
                    <span class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide block">Patient Savings</span>
                    <div id="pkg-savings-display" class="text-base font-bold text-emerald-700 mt-0.5">Saves PKR 0.00 (0% OFF)</div>
                </div>
            </div>

            <!-- Test Picker Section -->
            <div class="space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <label class="field-label mb-0 text-xs font-bold text-slate-800">
                        <i class="fa-solid fa-list-check text-indigo-600 mr-1"></i> Included Investigations (<span id="selected-count">0</span> selected)
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="search" id="catalog-search" oninput="filterCatalogSearch()" placeholder="Search investigations…" class="field text-xs py-1.5 px-3 w-48 sm:w-64">
                    </div>
                </div>

                <!-- Department Quick Filters -->
                <div class="flex flex-wrap gap-1.5 overflow-x-auto py-1">
                    {$catPillsHtml}
                </div>

                <!-- Active Selected Tags -->
                <div id="selected-tags-box" class="p-2.5 bg-slate-50 border border-slate-200 rounded-xl min-h-[42px] flex flex-wrap gap-1.5 items-center">
                    <span class="text-xs text-slate-400 italic" id="empty-selection-note">No tests selected yet. Check tests below to add them to this package.</span>
                </div>

                <!-- Catalog Grid with Checkboxes -->
                <div class="border border-slate-200 rounded-2xl max-h-56 overflow-y-auto divide-y divide-slate-100 bg-white" id="tests-catalog-list">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>

            <!-- Modal Footer Buttons -->
            <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closePackageModal()" class="btn btn-secondary text-xs sm:text-sm cursor-pointer">Cancel</button>
                <button type="submit" id="modal-submit-btn" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm shadow-md transition-all cursor-pointer">
                    <i class="fa-solid fa-floppy-disk text-emerald-200"></i> Save Package
                </button>
            </div>
        </form>
    </div>
</div>

<script>
window.__TEST_CATALOG__ = {$catalogJsonStr};
let selectedTestNames = new Set();
let activeCategoryFilter = '';

function renderCatalogList() {
    const list = document.getElementById('tests-catalog-list');
    const searchInput = document.getElementById('catalog-search');
    const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : '';
    if (!list) return;

    list.innerHTML = '';
    let visibleCount = 0;

    (window.__TEST_CATALOG__ || []).forEach(t => {
        const matchesCat = (!activeCategoryFilter || t.category === activeCategoryFilter);
        const matchesSearch = (!searchVal || t.name.toLowerCase().includes(searchVal) || t.code.toLowerCase().includes(searchVal) || t.category.toLowerCase().includes(searchVal));

        if (matchesCat && matchesSearch) {
            visibleCount++;
            const isChecked = selectedTestNames.has(t.name);
            
            const label = document.createElement('label');
            label.className = 'flex items-center justify-between p-2.5 hover:bg-slate-50 cursor-pointer transition-all ' + (isChecked ? 'bg-indigo-50/60' : '');
            
            const left = document.createElement('div');
            left.className = 'flex items-center gap-2.5';
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = isChecked;
            checkbox.className = 'w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500 cursor-pointer';
            checkbox.onchange = function() {
                toggleTestSelection(t.name, t.price, this.checked);
            };
            left.appendChild(checkbox);

            const titleDiv = document.createElement('div');
            const nameSpan = document.createElement('span');
            nameSpan.className = 'text-xs font-bold text-slate-800';
            nameSpan.textContent = t.name;
            titleDiv.appendChild(nameSpan);

            const codeSpan = document.createElement('span');
            codeSpan.className = 'text-[10px] text-slate-400 font-mono ml-1.5';
            codeSpan.textContent = t.code;
            titleDiv.appendChild(codeSpan);
            left.appendChild(titleDiv);

            const right = document.createElement('div');
            right.className = 'flex items-center gap-2';

            const catSpan = document.createElement('span');
            catSpan.className = 'text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-medium';
            catSpan.textContent = t.category;
            right.appendChild(catSpan);

            const priceSpan = document.createElement('span');
            priceSpan.className = 'text-xs font-bold text-slate-700 w-16 text-right';
            priceSpan.textContent = 'Rs. ' + Math.round(t.price);
            right.appendChild(priceSpan);

            label.appendChild(left);
            label.appendChild(right);
            list.appendChild(label);
        }
    });

    if (visibleCount === 0) {
        list.innerHTML = '<p class="p-4 text-xs text-slate-400 text-center">No investigations found matching search.</p>';
    }
}

function toggleTestSelection(name, price, isChecked) {
    if (isChecked) {
        selectedTestNames.add(name);
    } else {
        selectedTestNames.delete(name);
    }
    updateSelectedTags();
    calculateRegularTotal();
    renderCatalogList();
}

function updateSelectedTags() {
    const box = document.getElementById('selected-tags-box');
    const countEl = document.getElementById('selected-count');
    if (countEl) countEl.innerText = selectedTestNames.size;

    if (!box) return;

    if (selectedTestNames.size === 0) {
        box.innerHTML = '<span class="text-xs text-slate-400 italic" id="empty-selection-note">No tests selected yet. Check tests below to add them to this package.</span>';
        return;
    }

    box.innerHTML = '';
    selectedTestNames.forEach(name => {
        const span = document.createElement('span');
        span.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-900 text-xs font-bold shadow-2xs';
        
        const textNode = document.createTextNode(name);
        span.appendChild(textNode);

        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'selected_tests[]';
        hiddenInput.value = name;
        span.appendChild(hiddenInput);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'text-indigo-400 hover:text-indigo-700 ml-1 cursor-pointer';
        removeBtn.innerHTML = '<i class="fa-solid fa-xmark text-[11px]"></i>';
        removeBtn.onclick = function() { removeSelectedTag(name); };
        span.appendChild(removeBtn);

        box.appendChild(span);
    });
}

function removeSelectedTag(name) {
    selectedTestNames.delete(name);
    updateSelectedTags();
    renderCatalogList();
    calculateRegularTotal();
}

function calculateRegularTotal() {
    let sum = 0;
    selectedTestNames.forEach(name => {
        const item = (window.__TEST_CATALOG__ || []).find(t => t.name === name);
        if (item) sum += item.price;
    });

    const regInput = document.getElementById('pkg-regular-input');
    const regDisplay = document.getElementById('pkg-regular-display');
    if (regInput) regInput.value = sum;
    if (regDisplay) {
        regDisplay.innerText = 'PKR ' + sum.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    calculateSavings();
}

function calculateSavings() {
    const regular = parseFloat(document.getElementById('pkg-regular-input').value) || 0;
    const price = parseFloat(document.getElementById('pkg-price').value) || 0;
    const savingsDisplay = document.getElementById('pkg-savings-display');
    if (!savingsDisplay) return;

    if (regular > 0 && price > 0 && regular >= price) {
        const diff = regular - price;
        const pct = Math.round((diff / regular) * 100);
        savingsDisplay.innerHTML = '<span class="text-emerald-700 font-extrabold">Saves PKR ' + diff.toLocaleString() + ' (' + pct + '% OFF)</span>';
    } else if (price > regular && regular > 0) {
        savingsDisplay.innerHTML = '<span class="text-slate-500">Package price higher than regular</span>';
    } else {
        savingsDisplay.innerText = 'Saves PKR 0.00 (0% OFF)';
    }
}

function filterCatalogDept(dept) {
    activeCategoryFilter = dept;
    document.querySelectorAll('.pkg-dept-pill').forEach(btn => {
        if (btn.innerText === (dept || 'All')) {
            btn.className = 'pkg-dept-pill active px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-900 text-white transition-all cursor-pointer';
        } else {
            btn.className = 'pkg-dept-pill px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 transition-all cursor-pointer';
        }
    });
    renderCatalogList();
}

function filterCatalogSearch() {
    renderCatalogList();
}

function openCreatePackageModal() {
    const modal = document.getElementById('package-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.style.display = 'flex';

    document.getElementById('modal-title').innerText = 'Create Test Package';
    document.getElementById('form-action').value = 'create_package';
    document.getElementById('pkg-id').value = '';
    document.getElementById('pkg-code').value = '';
    document.getElementById('pkg-name').value = '';
    document.getElementById('pkg-price').value = '';
    document.getElementById('modal-submit-btn').innerHTML = '<i class="fa-solid fa-floppy-disk text-emerald-200"></i> Save Package';
    
    selectedTestNames.clear();
    updateSelectedTags();
    calculateRegularTotal();
    renderCatalogList();
}

function handleEditPackageClick(btn) {
    if (!btn) return;
    const id = btn.getAttribute('data-pkg-id') || '';
    const code = btn.getAttribute('data-pkg-code') || '';
    const name = btn.getAttribute('data-pkg-name') || '';
    const price = parseFloat(btn.getAttribute('data-pkg-price')) || 0;
    const regular = parseFloat(btn.getAttribute('data-pkg-regular')) || 0;
    let testsArr = [];
    try {
        testsArr = JSON.parse(btn.getAttribute('data-pkg-tests') || '[]');
    } catch (e) {
        testsArr = [];
    }
    openEditPackageModal(id, code, name, price, regular, testsArr);
}

function openEditPackageModal(id, code, name, price, regular, testsArr) {
    const modal = document.getElementById('package-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.style.display = 'flex';

    document.getElementById('modal-title').innerText = 'Edit Test Package (' + code + ')';
    document.getElementById('form-action').value = 'update_package';
    document.getElementById('pkg-id').value = id;
    document.getElementById('pkg-code').value = code;
    document.getElementById('pkg-name').value = name;
    document.getElementById('pkg-price').value = price;
    document.getElementById('modal-submit-btn').innerHTML = '<i class="fa-solid fa-floppy-disk text-emerald-200"></i> Update Package';

    selectedTestNames.clear();
    if (Array.isArray(testsArr)) {
        testsArr.forEach(t => {
            if (t) selectedTestNames.add(t);
        });
    }
    updateSelectedTags();
    calculateRegularTotal();
    renderCatalogList();
}

function closePackageModal() {
    const modal = document.getElementById('package-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.style.display = 'none';
}

// Close on backdrop click
document.addEventListener('click', function(e) {
    const modal = document.getElementById('package-modal');
    if (modal && e.target === modal) {
        closePackageModal();
    }
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePackageModal();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    renderCatalogList();
});
</script>
HTML;

render_page('Test Packages', 'main-lab', 'packages', $content);
