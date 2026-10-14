<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';
require_once __DIR__ . '/../../../includes/documents.php';

require_auth();

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$settings = branding_settings($orgId);

$allTests = test_repo()->getTests($orgId);
$allPackages = test_repo()->getPackages($orgId);

// Build catalog JSON for instant client-side lookup & filtering
$catalogList = [];
foreach ($allTests as $t) {
    $catalogList[] = [
        'id' => (string)$t['id'],
        'code' => (string)($t['code'] ?? ''),
        'name' => (string)($t['name'] ?? ''),
        'category' => (string)($t['category'] ?? 'Pathology'),
        'price' => (float)($t['price'] ?? 0),
        'type' => 'test',
    ];
}
foreach ($allPackages as $p) {
    $catalogList[] = [
        'id' => (string)$p['id'],
        'code' => (string)($p['code'] ?? 'PKG'),
        'name' => (string)($p['name'] ?? ''),
        'category' => 'Profile / Package',
        'price' => (float)($p['price'] ?? 0),
        'type' => 'package',
    ];
}

// Fallback catalog if DB has few tests
if (empty($catalogList)) {
    $defaultCatalog = [
        ['id' => 'T-CBC', 'code' => 'CBC', 'name' => 'CBC (Complete Blood Count)', 'category' => 'Hematology', 'price' => 600, 'type' => 'test'],
        ['id' => 'T-LFT', 'code' => 'LFT', 'name' => 'LFT (Liver Function Tests)', 'category' => 'Biochemistry', 'price' => 1200, 'type' => 'test'],
        ['id' => 'T-RFT', 'code' => 'RFT', 'name' => 'RFT (Renal Function Tests)', 'category' => 'Biochemistry', 'price' => 900, 'type' => 'test'],
        ['id' => 'T-LIPID', 'code' => 'LIPID', 'name' => 'Lipid Profile', 'category' => 'Biochemistry', 'price' => 1400, 'type' => 'test'],
        ['id' => 'T-BSF', 'code' => 'BSF', 'name' => 'Blood Sugar (Fasting / Random)', 'category' => 'Biochemistry', 'price' => 150, 'type' => 'test'],
        ['id' => 'T-URINE', 'code' => 'URINE', 'name' => 'Urine Complete Examination (R/E)', 'category' => 'Clinical Pathology', 'price' => 300, 'type' => 'test'],
        ['id' => 'T-CREAT', 'code' => 'CREAT', 'name' => 'Serum Creatinine', 'category' => 'Biochemistry', 'price' => 350, 'type' => 'test'],
        ['id' => 'T-HBA1C', 'code' => 'HBA1C', 'name' => 'HbA1c (Glycated Hemoglobin)', 'category' => 'Special Chemistry', 'price' => 1000, 'type' => 'test'],
        ['id' => 'T-TSH', 'code' => 'TSH', 'name' => 'Thyroid Stimulating Hormone (TSH)', 'category' => 'Special Chemistry', 'price' => 800, 'type' => 'test'],
        ['id' => 'T-US-ABD', 'code' => 'US-ABD', 'name' => 'Ultrasound Whole Abdomen & Pelvis', 'category' => 'Radiology', 'price' => 2000, 'type' => 'test'],
        ['id' => 'T-US-KUB', 'code' => 'US-KUB', 'name' => 'Ultrasound KUB & Prostate', 'category' => 'Radiology', 'price' => 1800, 'type' => 'test'],
        ['id' => 'T-US-OBS', 'code' => 'US-OBS', 'name' => 'Ultrasound Obstetric / Pregnancy', 'category' => 'Radiology', 'price' => 2000, 'type' => 'test'],
        ['id' => 'T-XRAY', 'code' => 'XRAY', 'name' => 'Chest X-Ray PA View', 'category' => 'Radiology', 'price' => 1000, 'type' => 'test'],
        ['id' => 'T-ECG', 'code' => 'ECG', 'name' => '12-Lead Electrocardiogram (ECG)', 'category' => 'Cardiology', 'price' => 800, 'type' => 'test'],
    ];
    $catalogList = $defaultCatalog;
}

$catalogJson = json_encode($catalogList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

$headerActions = <<<HTML
<div class="flex items-center gap-2">
    <a href="/portals/main-lab/lab-entries/new.php" class="btn btn-secondary text-xs py-2 px-3">
        <i class="fa-solid fa-file-circle-plus mr-1"></i> New Lab Entry
    </a>
    <a href="/portals/main-lab/tests/prices.php" class="btn btn-secondary text-xs py-2 px-3">
        <i class="fa-solid fa-tags mr-1"></i> Update Prices
    </a>
</div>
HTML;

$content = page_header(
    'Test Estimate & Rate Checker',
    'Instant test rate lookup for walk-in reception inquiries. Select any test to view its exact cost and calculate total fees without typing patient details.',
    $headerActions,
    'fa-solid fa-calculator'
);

$todayHuman = date('d-M-Y');
$labName = e($settings['name'] ?? 'Diagnostic Center');
$labPhone = e($settings['phone'] ?? '');
$labAddress = e($settings['address'] ?? '');

$content .= <<<HTML
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <!-- Left Column: Fast Test Rate Finder & Selector -->
    <div class="lg:col-span-7 space-y-5">
        <!-- Main Test Picker Box -->
        <div class="bg-white border border-slate-200 rounded-3xl p-5 sm:p-6 shadow-card space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-2xl bg-teal-600 text-white flex items-center justify-center font-bold text-base shadow-sm">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900 tracking-tight">Search &amp; Pick Tests</h3>
                        <p class="text-xs text-slate-500 font-medium">Type any test name (e.g. CBC, LFT, Ultrasound) to see its rate</p>
                    </div>
                </div>
                <span id="selected-badge" class="px-2.5 py-1 rounded-full text-xs font-bold bg-teal-50 text-teal-800 border border-teal-200">
                    0 Tests Selected
                </span>
            </div>

            <!-- Instant Search Box -->
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" id="test-search-box" class="field text-sm font-semibold pl-10 pr-10 py-2.5 w-full border-2 border-teal-300 focus:border-teal-500 rounded-2xl shadow-inner placeholder:text-slate-400" placeholder="Type test name or code (e.g. CBC, LFT, Sugar, Ultrasound)..." autocomplete="off" autofocus>
                <button type="button" id="clear-search-btn" onclick="clearSearchInput()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-sm hidden">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Filtered suggestions container -->
            <div id="catalog-suggestions" class="max-h-72 overflow-y-auto border border-teal-200 rounded-2xl divide-y divide-slate-100 bg-white shadow-lg hidden">
                <!-- Dynamically populated -->
            </div>

            <!-- Fast Quick-Add Buttons (With Price Badges) -->
            <div class="pt-2">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">One-Click Popular Tests (With Rates):</span>
                    <span class="text-[10px] text-teal-700 font-bold">Click to add to estimate</span>
                </div>
                <div class="flex flex-wrap gap-2" id="quick-pills">
                    <!-- Loaded dynamically from top catalog items -->
                </div>
            </div>
        </div>

        <!-- Optional Patient Inquiry Details (Collapsed by default so no time is wasted) -->
        <details class="group bg-white border border-slate-200 rounded-2xl p-4 shadow-card text-xs">
            <summary class="cursor-pointer font-bold text-slate-700 hover:text-teal-700 flex items-center justify-between select-none">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-user-tag text-teal-600"></i>
                    <span>Optional: Add Patient Name or Phone (Only needed for quotation slips)</span>
                </span>
                <span class="text-[11px] font-semibold text-slate-400 group-open:rotate-180 transition-transform">
                    <i class="fa-solid fa-chevron-down"></i>
                </span>
            </summary>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-4 mt-2 border-t border-slate-100">
                <div>
                    <label class="field-label text-xs">Patient Name</label>
                    <input type="text" id="est-patient-name" class="field text-xs py-1.5" placeholder="e.g. Muhammad Ali">
                </div>
                <div>
                    <label class="field-label text-xs">Phone Number</label>
                    <input type="text" id="est-patient-phone" class="field text-xs py-1.5" placeholder="0300 1234567">
                </div>
                <div>
                    <label class="field-label text-xs">Referring Doctor</label>
                    <input type="text" id="est-doctor" class="field text-xs py-1.5" placeholder="Self / Dr. Tariq" value="Self">
                </div>
            </div>
        </details>
    </div>

    <!-- Right Column: Live Estimate & Rates Summary -->
    <div class="lg:col-span-5 space-y-5">
        <div class="bg-white border border-slate-200 rounded-3xl p-5 sm:p-6 shadow-card flex flex-col justify-between space-y-4">
            <div>
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-sm">
                            <i class="fa-solid fa-receipt"></i>
                        </span>
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-900">Estimated Cost Breakdown</h3>
                            <p class="text-[11px] text-slate-500 font-medium">Selected tests with rates and grand total</p>
                        </div>
                    </div>
                    <button type="button" onclick="clearEstimate()" class="text-xs text-slate-400 hover:text-rose-600 font-bold transition-colors" title="Clear All Tests">
                        <i class="fa-solid fa-trash-can mr-1"></i> Clear
                    </button>
                </div>

                <!-- Selected Items List -->
                <div class="border border-slate-200 rounded-2xl overflow-hidden mb-4 bg-slate-50/50">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="bg-slate-100/80 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="py-2.5 px-3">Test / Investigation</th>
                                <th class="py-2.5 px-3 text-right">Price / Rate</th>
                                <th class="py-2.5 px-2 text-center w-8"></th>
                            </tr>
                        </thead>
                        <tbody id="selected-tests-table" class="divide-y divide-slate-100 bg-white">
                            <tr>
                                <td colspan="3" class="py-8 text-center text-slate-400 italic">
                                    <i class="fa-solid fa-hand-pointer text-slate-300 text-lg block mb-1"></i>
                                    No tests added yet. Click any test above to display its price.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Financial Calculation Card -->
                <div class="bg-slate-50 border border-slate-200/90 rounded-2xl p-4 space-y-3">
                    <div class="flex items-center justify-between text-xs text-slate-600">
                        <span class="font-bold">Total Retail Rates:</span>
                        <span id="subtotal-val" class="font-extrabold text-slate-900 text-sm font-mono">Rs 0</span>
                    </div>

                    <!-- Discount Control -->
                    <div class="pt-2 border-t border-slate-200/70">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <span class="text-xs font-bold text-slate-700">Concession / Discount:</span>
                            <div class="inline-flex rounded-lg border border-slate-300 p-0.5 bg-white text-[11px]">
                                <button type="button" id="disc-mode-rs" onclick="setDiscountMode('rs')" class="px-2 py-0.5 rounded font-bold bg-teal-600 text-white transition-all">Rs</button>
                                <button type="button" id="disc-mode-pct" onclick="setDiscountMode('pct')" class="px-2 py-0.5 rounded font-medium text-slate-600 transition-all">%</button>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="number" id="discount-input" min="0" value="0" step="any" class="field text-xs font-bold font-mono py-1.5 px-2 text-right" placeholder="0" oninput="calculateTotals()">
                            <span id="discount-calc-display" class="text-xs font-mono font-bold text-rose-600 w-24 text-right">- Rs 0</span>
                        </div>
                    </div>

                    <!-- Net Payable (High Visibility) -->
                    <div class="pt-3 border-t-2 border-slate-300 flex items-center justify-between bg-emerald-50/80 -mx-4 -mb-4 p-4 rounded-b-2xl border-t border-emerald-200">
                        <span class="text-xs sm:text-sm font-black text-emerald-950 uppercase tracking-wide">
                            Total Estimate:
                        </span>
                        <span id="net-total-val" class="text-xl sm:text-2xl font-black text-emerald-800 font-mono tracking-tight">
                            Rs 0
                        </span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-2 pt-2">
                <button type="button" onclick="printQuotationSlip()" class="btn btn-secondary w-full py-2.5 text-xs font-bold flex items-center justify-center gap-2">
                    <i class="fa-solid fa-print text-slate-600"></i> Print Estimate Slip
                </button>
                <button type="button" onclick="convertToLabEntry()" class="btn btn-primary w-full py-2.5 text-xs font-bold flex items-center justify-center gap-2 bg-slate-900 text-white">
                    <i class="fa-solid fa-arrow-right-to-bracket text-[#c2f13c]"></i> Book Patient Now (New Lab Entry)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Printable Quotation Slip -->
<div id="print-quotation-wrap" class="hidden">
    <div class="printable-quotation" style="font-family: Arial, sans-serif; color: #000; padding: 20px; max-width: 720px; margin: 0 auto; line-height: 1.4;">
        <!-- Header -->
        <div style="border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="font-size: 20px; font-weight: 800; text-transform: uppercase; margin: 0; color: #0f172a;">{$labName}</h1>
                <div style="font-size: 11px; color: #475569; margin-top: 2px;">{$labAddress} &middot; {$labPhone}</div>
            </div>
            <div style="text-align: right;">
                <span style="display: inline-block; background: #0f172a; color: #fff; font-size: 11px; font-weight: 800; padding: 3px 10px; border-radius: 3px; text-transform: uppercase; letter-spacing: 0.5px;">FEE ESTIMATE / QUOTATION</span>
                <div style="font-size: 11px; font-weight: 700; margin-top: 4px;" id="slip-date">{$todayHuman}</div>
            </div>
        </div>

        <!-- Patient Demographics -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 12px;">
            <tr>
                <td style="padding: 4px 0; width: 15%; font-weight: bold; color: #64748b;">Patient Name:</td>
                <td style="padding: 4px 0; width: 35%; font-weight: bold;" id="slip-pname">—</td>
                <td style="padding: 4px 0; width: 15%; font-weight: bold; color: #64748b;">Phone:</td>
                <td style="padding: 4px 0; width: 35%; font-weight: bold;" id="slip-phone">—</td>
            </tr>
            <tr>
                <td style="padding: 4px 0; font-weight: bold; color: #64748b;">Referring Dr:</td>
                <td style="padding: 4px 0; font-weight: bold;" id="slip-doctor">Self</td>
                <td style="padding: 4px 0; font-weight: bold; color: #64748b;">Validity:</td>
                <td style="padding: 4px 0; font-weight: bold; color: #0f172a;">7 Days from date</td>
            </tr>
        </table>

        <!-- Itemized Table -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 12px;">
            <thead>
                <tr style="border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; background: #f8fafc;">
                    <th style="padding: 6px 8px; text-align: left; width: 40px;">#</th>
                    <th style="padding: 6px 8px; text-align: left;">Test / Investigation</th>
                    <th style="padding: 6px 8px; text-align: left; width: 160px;">Department</th>
                    <th style="padding: 6px 8px; text-align: right; width: 110px;">Price / Rate</th>
                </tr>
            </thead>
            <tbody id="slip-items-body"></tbody>
        </table>

        <!-- Totals -->
        <div style="display: flex; justify-content: flex-end; margin-bottom: 25px;">
            <table style="width: 260px; border-collapse: collapse; font-size: 12px;">
                <tr>
                    <td style="padding: 4px; font-weight: bold; text-align: right;">Total Price:</td>
                    <td style="padding: 4px; font-weight: bold; text-align: right; font-family: monospace;" id="slip-subtotal">Rs 0</td>
                </tr>
                <tr>
                    <td style="padding: 4px; font-weight: bold; text-align: right; color: #dc2626;">Discount:</td>
                    <td style="padding: 4px; font-weight: bold; text-align: right; color: #dc2626; font-family: monospace;" id="slip-discount">- Rs 0</td>
                </tr>
                <tr style="border-top: 1.5px solid #000;">
                    <td style="padding: 6px 4px; font-size: 14px; font-weight: 800; text-align: right; text-transform: uppercase;">Net Estimated:</td>
                    <td style="padding: 6px 4px; font-size: 15px; font-weight: 900; text-align: right; font-family: monospace;" id="slip-net">Rs 0</td>
                </tr>
            </table>
        </div>

        <div style="border-top: 1px dashed #cbd5e1; padding-top: 10px; font-size: 10.5px; color: #64748b; line-height: 1.45;">
            <p style="margin: 0;"><strong>Notice:</strong> This test fee estimation is provided for informational and booking preparation purposes. Actual tests performed may vary based on attending physician prescription. This is an inquiry estimate slip and not a tax invoice or final receipt.</p>
        </div>
        
        <div style="text-align: center; margin-top: 18px; font-size: 10px; color: #94a3b8;">
            Software created by <strong>Shakeel Khalid</strong> &middot; WhatsApp 0328-3070070
        </div>
    </div>
</div>
HTML;

// Append JavaScript in a separate nowdoc (<<<'SCRIPT') so PHP does not parse JS template literals or $ variables
$content .= '<script>';
$content .= 'const CATALOG = ' . $catalogJson . ';';
$content .= <<<'SCRIPT'
let selectedTests = [];
let discountMode = 'rs'; // 'rs' or 'pct'

// Top popular tests for quick-add pills with rates
const popularKeywords = ['CBC', 'LFT', 'RFT', 'Lipid', 'Sugar', 'Ultrasound', 'Urine', 'X-Ray', 'Thyroid', 'HbA1c', 'ECG', 'Creatinine'];
const quickPillsContainer = document.getElementById('quick-pills');
const addedPills = new Set();

popularKeywords.forEach(kw => {
    const match = CATALOG.find(c => c.name.toLowerCase().includes(kw.toLowerCase()) || c.code.toLowerCase().includes(kw.toLowerCase()));
    if (match && !addedPills.has(match.id)) {
        addedPills.add(match.id);
        const pill = document.createElement('button');
        pill.type = 'button';
        pill.className = 'text-xs bg-slate-100 hover:bg-teal-50 hover:border-teal-300 text-slate-800 font-bold py-1.5 px-3 rounded-xl border border-slate-200 transition-colors flex items-center gap-1.5 shadow-2xs';
        pill.innerHTML = '<i class="fa-solid fa-plus text-[10px] text-teal-600"></i> ' + escapeHtml(match.name) + ' <span class="font-mono text-emerald-700 bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200 font-extrabold">Rs ' + Number(match.price).toLocaleString() + '</span>';
        pill.onclick = () => addTestToEstimate(match);
        quickPillsContainer.appendChild(pill);
    }
});

// Search functionality
const searchBox = document.getElementById('test-search-box');
const suggestionsBox = document.getElementById('catalog-suggestions');
const clearSearchBtn = document.getElementById('clear-search-btn');

function clearSearchInput() {
    searchBox.value = '';
    suggestionsBox.classList.add('hidden');
    clearSearchBtn.classList.add('hidden');
    searchBox.focus();
}

searchBox.addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    if (q.length < 1) {
        suggestionsBox.classList.add('hidden');
        suggestionsBox.innerHTML = '';
        clearSearchBtn.classList.add('hidden');
        return;
    }
    clearSearchBtn.classList.remove('hidden');

    const matches = CATALOG.filter(t => 
        t.name.toLowerCase().includes(q) || 
        t.code.toLowerCase().includes(q) || 
        t.category.toLowerCase().includes(q)
    ).slice(0, 15);

    if (matches.length === 0) {
        suggestionsBox.innerHTML = '<div class="p-3 text-center text-xs text-slate-400 italic">No matching tests found in catalog.</div>';
        suggestionsBox.classList.remove('hidden');
        return;
    }

    let html = '';
    matches.forEach(m => {
        const isAdded = selectedTests.some(st => st.id === m.id);
        const btnBadge = isAdded 
            ? '<span class="text-teal-700 font-bold text-xs"><i class="fa-solid fa-check"></i> Added</span>' 
            : '<span class="text-slate-400 text-xs font-semibold">+ Add to estimate</span>';
        
        html += '<div onclick="addTestById(\'' + escapeHtml(m.id) + '\')" class="p-3 flex items-center justify-between hover:bg-teal-50/70 cursor-pointer transition-colors ' + (isAdded ? 'bg-teal-50/40' : '') + '">';
        html += '  <div>';
        html += '    <div class="text-xs font-bold text-slate-900">' + escapeHtml(m.name) + '</div>';
        html += '    <div class="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5">';
        html += '      <span class="font-mono text-teal-700 font-bold bg-teal-50 px-1.5 py-0.5 rounded">' + escapeHtml(m.code) + '</span>';
        html += '      &middot;';
        html += '      <span>' + escapeHtml(m.category) + '</span>';
        html += '    </div>';
        html += '  </div>';
        html += '  <div class="text-right">';
        html += '    <div class="font-mono font-black text-sm text-emerald-800">Rs ' + Number(m.price).toLocaleString() + '</div>';
        html += '    ' + btnBadge;
        html += '  </div>';
        html += '</div>';
    });

    suggestionsBox.innerHTML = html;
    suggestionsBox.classList.remove('hidden');
});

// Close suggestions on outside click
document.addEventListener('click', function(e) {
    if (!searchBox.contains(e.target) && !suggestionsBox.contains(e.target)) {
        suggestionsBox.classList.add('hidden');
    }
});

function addTestById(id) {
    const test = CATALOG.find(c => c.id === id);
    if (test) {
        addTestToEstimate(test);
    }
}

function addTestToEstimate(test) {
    if (selectedTests.some(t => t.id === test.id)) {
        // If already added, just flash
        return;
    }
    selectedTests.push(test);
    renderSelectedTestsTable();
    calculateTotals();
    searchBox.value = '';
    clearSearchBtn.classList.add('hidden');
    suggestionsBox.classList.add('hidden');
}

function removeTest(idx) {
    selectedTests.splice(idx, 1);
    renderSelectedTestsTable();
    calculateTotals();
}

function clearEstimate() {
    if (selectedTests.length === 0) return;
    if (confirm('Clear all tests from this estimate?')) {
        selectedTests = [];
        document.getElementById('discount-input').value = '0';
        renderSelectedTestsTable();
        calculateTotals();
    }
}

function setDiscountMode(mode) {
    discountMode = mode;
    const btnRs = document.getElementById('disc-mode-rs');
    const btnPct = document.getElementById('disc-mode-pct');
    if (mode === 'rs') {
        btnRs.className = 'px-2 py-0.5 rounded font-bold bg-teal-600 text-white transition-all';
        btnPct.className = 'px-2 py-0.5 rounded font-medium text-slate-600 transition-all';
    } else {
        btnPct.className = 'px-2 py-0.5 rounded font-bold bg-teal-600 text-white transition-all';
        btnRs.className = 'px-2 py-0.5 rounded font-medium text-slate-600 transition-all';
    }
    calculateTotals();
}

function renderSelectedTestsTable() {
    const tbody = document.getElementById('selected-tests-table');
    const badge = document.getElementById('selected-badge');
    badge.innerText = selectedTests.length + ' Tests Selected';

    if (selectedTests.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="py-8 text-center text-slate-400 italic"><i class="fa-solid fa-hand-pointer text-slate-300 text-lg block mb-1"></i>No tests added yet. Click any test above to display its price.</td></tr>';
        return;
    }

    let html = '';
    selectedTests.forEach((t, idx) => {
        html += '<tr class="hover:bg-slate-50 transition-colors">';
        html += '  <td class="py-2.5 px-3 font-semibold text-slate-900">';
        html += '    <div>' + escapeHtml(t.name) + '</div>';
        html += '    <div class="text-[10px] text-slate-400 font-mono">' + escapeHtml(t.code) + ' &middot; ' + escapeHtml(t.category) + '</div>';
        html += '  </td>';
        html += '  <td class="py-2.5 px-3 text-right font-mono font-black text-sm text-emerald-800 whitespace-nowrap">Rs ' + Number(t.price).toLocaleString() + '</td>';
        html += '  <td class="py-2.5 px-2 text-center">';
        html += '    <button type="button" onclick="removeTest(' + idx + ')" class="text-slate-400 hover:text-rose-600 p-1" title="Remove Test">';
        html += '      <i class="fa-solid fa-xmark"></i>';
        html += '    </button>';
        html += '  </td>';
        html += '</tr>';
    });
    tbody.innerHTML = html;
}

function calculateTotals() {
    const gross = selectedTests.reduce((sum, t) => sum + Number(t.price || 0), 0);
    const discValRaw = parseFloat(document.getElementById('discount-input').value) || 0;
    
    let discountAmount = 0;
    if (discountMode === 'pct') {
        discountAmount = (gross * Math.min(100, Math.max(0, discValRaw))) / 100.0;
    } else {
        discountAmount = Math.min(gross, Math.max(0, discValRaw));
    }
    
    const net = Math.max(0, gross - discountAmount);

    document.getElementById('subtotal-val').innerText = 'Rs ' + Math.round(gross).toLocaleString();
    document.getElementById('discount-calc-display').innerText = '- Rs ' + Math.round(discountAmount).toLocaleString();
    document.getElementById('net-total-val').innerText = 'Rs ' + Math.round(net).toLocaleString();

    return { gross, discountAmount, net };
}

function convertToLabEntry() {
    if (selectedTests.length === 0) {
        alert('Please select at least one test to book.');
        return;
    }
    const totals = calculateTotals();
    const testNames = selectedTests.map(t => t.name).join(', ');
    const pName = encodeURIComponent(document.getElementById('est-patient-name').value.trim());
    const pPhone = encodeURIComponent(document.getElementById('est-patient-phone').value.trim());
    const pDoctor = encodeURIComponent(document.getElementById('est-doctor').value.trim());
    const testsParam = encodeURIComponent(testNames);

    const targetUrl = '/portals/main-lab/lab-entries/new.php?pre_tests=' + testsParam + '&pre_discount=' + Math.round(totals.discountAmount) + '&pre_name=' + pName + '&pre_phone=' + pPhone + '&pre_doctor=' + pDoctor;
    window.location.href = targetUrl;
}

function printQuotationSlip() {
    if (selectedTests.length === 0) {
        alert('Please select at least one test before printing quotation.');
        return;
    }
    const totals = calculateTotals();
    const pName = document.getElementById('est-patient-name').value.trim() || 'Inquiry / Walk-in';
    const pPhone = document.getElementById('est-patient-phone').value.trim() || '—';
    const pDoctor = document.getElementById('est-doctor').value.trim() || 'Self';

    const pNameEl = document.getElementById('slip-pname');
    const pPhoneEl = document.getElementById('slip-phone');
    const pDoctorEl = document.getElementById('slip-doctor');
    if (pNameEl) pNameEl.innerText = pName;
    if (pPhoneEl) pPhoneEl.innerText = pPhone;
    if (pDoctorEl) pDoctorEl.innerText = pDoctor;

    let itemsHtml = '';
    selectedTests.forEach((t, i) => {
        itemsHtml += '<tr style="border-bottom: 1px solid #e2e8f0;">';
        itemsHtml += '  <td style="padding: 6px 8px; color: #64748b;">' + (i + 1) + '</td>';
        itemsHtml += '  <td style="padding: 6px 8px; font-weight: bold; color: #0f172a;">' + escapeHtml(t.name) + '</td>';
        itemsHtml += '  <td style="padding: 6px 8px; color: #64748b;">' + escapeHtml(t.category) + '</td>';
        itemsHtml += '  <td style="padding: 6px 8px; text-align: right; font-family: monospace; font-weight: bold;">Rs ' + Number(t.price).toLocaleString() + '</td>';
        itemsHtml += '</tr>';
    });
    
    const itemsBody = document.getElementById('slip-items-body');
    if (itemsBody) itemsBody.innerHTML = itemsHtml;
    
    document.getElementById('slip-subtotal').innerText = 'Rs ' + Math.round(totals.gross).toLocaleString();
    document.getElementById('slip-discount').innerText = '- Rs ' + Math.round(totals.discountAmount).toLocaleString();
    document.getElementById('slip-net').innerText = 'Rs ' + Math.round(totals.net).toLocaleString();

    const slipWrap = document.getElementById('print-quotation-wrap');
    if (!slipWrap) return;
    const slipContent = slipWrap.innerHTML;

    const printWin = window.open('', '_blank', 'width=800,height=700');
    if (!printWin) {
        alert('Please allow popups to print quotation slip.');
        return;
    }
    printWin.document.open();
    printWin.document.write('<!DOCTYPE html><html><head><title>Test Quotation - ' + escapeHtml(pName) + '</title><style>@media print { body { margin: 0; padding: 15px; font-family: Arial, sans-serif; } @page { size: A4; margin: 12mm; } }</style></head><body onload="window.print(); window.close();">' + slipContent + '</body></html>');
    printWin.document.close();
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
SCRIPT;
$content .= '</script>';

render_page('Test Estimate & Rate Checker', 'main-lab', 'test-estimate', $content);
