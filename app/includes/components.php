<?php

declare(strict_types=1);

function card(string $content, string $class = ''): string
{
    return '<div class="app-panel bg-white rounded-3xl border border-slate-200/90 shadow-card overflow-hidden ' . e($class) . '">' . $content . '</div>';
}

function panel_head(string $title, ?string $subtitle = null): string
{
    $sub = $subtitle ? '<span class="text-xs font-semibold text-slate-400"> — ' . e($subtitle) . '</span>' : '';
    return '<div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between"><h3 class="text-base font-extrabold text-slate-900 tracking-tight">' . e($title) . $sub . '</h3></div>';
}

function data_table(array $headers, array $rows): string
{
    $thead = '';
    foreach ($headers as $h) {
        $thead .= '<th class="py-3.5 px-4 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/70 border-b border-slate-100">' . e($h) . '</th>';
    }

    $tbody = '';
    foreach ($rows as $row) {
        $tbody .= '<tr class="hover:bg-slate-50/80 transition-colors border-b border-slate-100 last:border-none">';
        foreach ($row as $cell) {
            $tbody .= '<td class="py-3.5 px-4 text-xs font-medium text-slate-700">' . $cell . '</td>';
        }
        $tbody .= '</tr>';
    }

    return <<<HTML
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead><tr>{$thead}</tr></thead>
            <tbody class="divide-y divide-slate-100">{$tbody}</tbody>
        </table>
    </div>
    HTML;
}

function field_label_html(string $label, bool $optional = false): string
{
    $opt = $optional ? ' <span class="field-optional">(optional)</span>' : '';
    return e($label) . $opt;
}

function form_field(string $label, string $name, string $type = 'text', ?string $value = null, string $placeholder = '', bool $optional = false, string $attrs = ''): string
{
    $val = $value !== null ? ' value="' . e($value) . '"' : '';
    $ph = $placeholder ? ' placeholder="' . e($placeholder) . '"' : '';
    $fc = FIELD_CLASS;
    $labelHtml = field_label_html($label, $optional);
    return <<<HTML
    <div>
        <label class="field-label" for="{$name}">{$labelHtml}</label>
        <input type="{$type}" id="{$name}" name="{$name}" class="{$fc}"{$val}{$ph} {$attrs}>
    </div>
    HTML;
}

function select_field(string $label, string $name, array $options, ?string $selected = null, bool $optional = false, string $attrs = ''): string
{
    $opts = '';
    foreach ($options as $value => $text) {
        $sel = (string) $selected === (string) $value ? ' selected' : '';
        $opts .= '<option value="' . e((string) $value) . '"' . $sel . '>' . e($text) . '</option>';
    }
    $fc = FIELD_CLASS;
    $labelHtml = field_label_html($label, $optional);
    return <<<HTML
    <div>
        <label class="field-label" for="{$name}">{$labelHtml}</label>
        <select id="{$name}" name="{$name}" class="{$fc}" {$attrs}>
            {$opts}
        </select>
    </div>
    HTML;
}

function textarea_field(string $label, string $name, ?string $value = null, string $placeholder = '', int $rows = 3, bool $optional = false): string
{
    $labelHtml = field_label_html($label, $optional);
    $val = e($value ?? '');
    $ph = e($placeholder);
    $fc = FIELD_CLASS;
    return <<<HTML
    <div>
        <label class="field-label" for="{$name}">{$labelHtml}</label>
        <textarea id="{$name}" name="{$name}" rows="{$rows}" class="{$fc}" placeholder="{$ph}">{$val}</textarea>
    </div>
    HTML;
}

function filter_bar(array $fields, string $applyLabel = 'Apply filters'): string
{
    $html = '<div class="filter-bar"><div class="filter-bar__grid">';
    foreach ($fields as $field) {
        $html .= $field;
    }
    $html .= '</div><div class="filter-bar__actions">';
    $html .= '<button type="button" class="btn btn-primary">' . e($applyLabel) . '</button>';
    $html .= '<button type="button" class="btn btn-secondary">Reset</button>';
    $html .= '</div></div>';
    return $html;
}

function catalog_picker(string $mode = 'all', array $selectedNames = []): string
{
    $selectedLookup = [];
    foreach ($selectedNames as $n) {
        $n = trim((string)$n);
        if ($n !== '') {
            $selectedLookup[strtolower($n)] = true;
        }
    }
    $items = [];

    foreach (mock('mock_packages') as $pkg) {
        $items[] = [
            'dept' => 'packages',
            'dept_label' => 'Packages',
            'name' => $pkg['name'],
            'code' => $pkg['code'],
            'meta' => 'Package · ' . $pkg['tests'],
            'price' => (int)$pkg['price'],
            'search' => strtolower($pkg['name'] . ' ' . $pkg['code'] . ' package'),
        ];
    }

    foreach (mock('mock_tests') as $t) {
        $cat = (string)($t['category'] ?? 'Other');
        $b2b = (int)($t['b2b_price'] ?? round(((float)$t['price']) * 0.75));
        $items[] = [
            'dept' => strtolower($cat),
            'dept_label' => $cat,
            'name' => $t['name'],
            'code' => $t['code'],
            'meta' => $cat . ' · Sample: ' . ($t['sample'] ?? '—'),
            'price' => (int)$t['price'],
            'b2b_price' => $b2b,
            'search' => strtolower($t['name'] . ' ' . $t['code'] . ' ' . $cat),
        ];
    }

    $deptCounts = [];
    foreach ($items as $it) {
        $deptCounts[$it['dept']] = ($deptCounts[$it['dept']] ?? 0) + 1;
    }

    $orderedDepts = ['packages' => 'Packages'];
    foreach (TEST_DEPARTMENTS as $key => $label) {
        $dk = strtolower($key);
        if (!empty($deptCounts[$dk])) {
            $orderedDepts[$dk] = $label;
        }
    }
    foreach ($deptCounts as $dk => $count) {
        if (!isset($orderedDepts[$dk])) {
            $orderedDepts[$dk] = ucwords(str_replace('_', ' ', $dk));
        }
    }

    $typeNav = '<button type="button" class="catalog-type-btn is-active" data-catalog-type="">'
        . '<span>All types</span>'
        . '<span class="catalog-type-btn__count">' . count($items) . '</span>'
        . '</button>';
    foreach ($orderedDepts as $dk => $label) {
        $count = (int)($deptCounts[$dk] ?? 0);
        if ($count === 0) {
            continue;
        }
        $typeNav .= '<button type="button" class="catalog-type-btn" data-catalog-type="' . e($dk) . '">'
            . '<span>' . e($label) . '</span>'
            . '<span class="catalog-type-btn__count">' . $count . '</span>'
            . '</button>';
    }

    $searchHits = '';
    $hiddenChecks = '';
    foreach ($items as $it) {
        $b2bPr = $it['b2b_price'] ?? $it['price'];
        $searchHits .= '<button type="button" class="catalog-hit" data-catalog-hit'
            . ' data-name="' . e($it['name']) . '"'
            . ' data-dept="' . e($it['dept']) . '"'
            . ' data-search="' . e($it['search']) . '"'
            . ' data-price="' . $it['price'] . '"'
            . ' data-regular-price="' . $it['price'] . '"'
            . ' data-b2b-price="' . $b2bPr . '">'
            . '<span class="catalog-hit__body">'
            . '<span class="catalog-hit__title">' . e($it['name']) . ' <span class="catalog-code">' . e($it['code']) . '</span></span>'
            . '<span class="catalog-hit__meta">' . e($it['meta']) . '</span>'
            . '</span>'
            . '<span class="catalog-hit__price">' . e(format_money((float)$it['price'])) . '</span>'
            . '</button>';

        $isSelected = isset($selectedLookup[strtolower($it['name'])]);
        $checked = $isSelected ? ' checked' : '';
        $rowHidden = $isSelected ? '' : ' hidden';

        $hiddenChecks .= '<label class="catalog-item catalog-item--selected' . $rowHidden . '" data-catalog-item data-dept="' . e($it['dept']) . '" data-name="' . e($it['search']) . '" data-price="' . $it['price'] . '" data-regular-price="' . $it['price'] . '" data-b2b-price="' . $b2bPr . '" data-code="' . e($it['code']) . '" data-meta="' . e($it['meta']) . '">'
            . '<input type="checkbox" name="tests[]" value="' . e($it['name']) . '" class="catalog-check" data-price="' . $it['price'] . '" data-regular-price="' . $it['price'] . '" data-b2b-price="' . $b2bPr . '" data-code="' . e($it['code']) . '"' . $checked . '>'
            . '<span class="catalog-item__body">'
            . '<span class="catalog-item__title">' . e($it['name']) . ' <span class="catalog-code">' . e($it['code']) . '</span></span>'
            . '<span class="catalog-item__meta">' . e($it['meta']) . '</span>'
            . '</span>'
            . '<span class="catalog-item__price">' . e(format_money((float)$it['price'])) . '</span>'
            . '<button type="button" class="catalog-remove" data-catalog-remove title="Remove">&times;</button>'
            . '</label>';
    }

    return <<<HTML
<div class="catalog-workspace" data-catalog-workspace>
    <aside class="catalog-types" aria-label="Test type">
        <p class="catalog-pane-label">Filter by type</p>
        <div class="catalog-type-list">{$typeNav}</div>
    </aside>
    <div class="catalog-pick">
        <p class="catalog-pane-label">Search &amp; add test</p>
        <div class="catalog-search-wrap">
            <input type="search" class="field catalog-search-input" placeholder="Search any test by name or code (e.g. CBC, sugar, lipid)…" data-catalog-search autocomplete="off">
        </div>
        <div class="catalog-hits" data-catalog-hits>
            {$searchHits}
        </div>
        <p class="catalog-hits-hint" data-catalog-hits-hint>Type to search all tests, or pick a type on the left. Click a result to add it.</p>
        <p class="catalog-pane-label mt-4">Selected for this patient</p>
        <div class="catalog-list catalog-list--selected" data-catalog-selected>
            <p class="catalog-empty" data-catalog-empty>No tests added yet. Search above and click a test to add.</p>
            {$hiddenChecks}
        </div>
    </div>
</div>
HTML;
}



function billing_panel(float $initialDiscount = 0, float $initialPaid = 0): string
{
    $discVal = (int)round($initialDiscount);
    $paidVal = (int)round($initialPaid);
    return <<<HTML
    <div class="billing-panel" data-billing>
        <input type="hidden" name="amount" value="0" data-amount-input>
        <div class="billing-row"><span>Gross Subtotal</span><strong data-subtotal>Rs. 0</strong></div>
        
        <!-- Quick Preset Discount Buttons (30%, 50%, etc.) -->
        <div class="mt-2.5 mb-1.5 p-2 bg-slate-50 border border-slate-200/80 rounded-xl">
            <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider block mb-1">Quick Discount %</span>
            <div class="flex flex-wrap items-center gap-1.5">
                <button type="button" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white hover:bg-slate-900 hover:text-white border border-slate-300 shadow-2xs transition-all text-slate-700" onclick="applyDiscountPreset(10)">10%</button>
                <button type="button" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white hover:bg-slate-900 hover:text-white border border-slate-300 shadow-2xs transition-all text-slate-700" onclick="applyDiscountPreset(20)">20%</button>
                <button type="button" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-900 hover:text-white border border-slate-300 shadow-2xs transition-all text-slate-900" onclick="applyDiscountPreset(30)">30%</button>
                <button type="button" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-900 hover:text-white border border-slate-300 shadow-2xs transition-all text-slate-900" onclick="applyDiscountPreset(50)">50%</button>
                <button type="button" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-500 transition-all ml-auto" onclick="applyDiscountPreset(0)">0% Reset</button>
            </div>
        </div>

        <div class="billing-fields">
            <div>
                <label class="field-label" for="discount_pct">Discount %</label>
                <input type="number" id="discount_pct" name="discount_pct" class="field font-bold text-blue-700 text-sm" value="0" min="0" max="100" step="1" data-discount-pct placeholder="0">
            </div>
            <div>
                <label class="field-label" for="discount">Discount (Rs.)</label>
                <input type="number" id="discount" name="discount" class="field font-bold text-rose-600 text-sm" value="{$discVal}" min="0" step="1" data-discount placeholder="0">
            </div>
        </div>
        <div class="billing-row billing-row--total"><span>Net Payable Total</span><strong data-total class="text-blue-700">Rs. 0</strong></div>
        
        <!-- Quick Pay Helper Chips -->
        <div class="mt-2 mb-1 flex flex-wrap items-center gap-1.5">
            <button type="button" class="px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-800 hover:bg-emerald-600 hover:text-white border border-emerald-200 transition-all shadow-2xs" onclick="applyQuickPay('full')">Full Paid</button>
            <button type="button" class="px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-amber-50 text-amber-800 hover:bg-amber-600 hover:text-white border border-amber-200 transition-all shadow-2xs" onclick="applyQuickPay('half')">50% Advance</button>
            <button type="button" class="px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200 transition-all shadow-2xs" onclick="applyQuickPay('zero')">Zero Advance</button>
        </div>

        <div class="billing-fields">
            <div>
                <label class="field-label" for="paid">Paid amount (Rs.)</label>
                <input type="number" id="paid" name="paid" class="field font-bold text-emerald-600 text-sm" value="{$paidVal}" min="0" step="1" data-paid>
            </div>
            <div>
                <label class="field-label" for="pay_mode">Payment mode</label>
                <select id="pay_mode" name="pay_mode" class="field text-sm">
                    <option>Cash</option>
                    <option>Card</option>
                    <option>Bank transfer</option>
                    <option>JazzCash / EasyPaisa</option>
                    <option>Credit / Due</option>
                </select>
            </div>
        </div>
        <div class="billing-row"><span>Remaining Balance Due</span><strong data-remaining class="text-amber-700">Rs. 0</strong></div>
        <p class="billing-hint text-[11px] text-slate-400">Click 30% or 50% for instant calculation. Balance updates live.</p>
    </div>
    HTML;
}

function report_actions(string $patientPhone, string $reportUrl = '', string $pdfFilename = 'lab-report', string $currentFormat = 'graph'): string
{
    if ($reportUrl === '') {
        $portal = current_portal();
        $reportUrl = $portal === 'imaging'
            ? '/portals/reports.php'
            : '/portals/reports/preview.php';
    }
    $waPhone = preg_replace('/\D/', '', $patientPhone);
    if (str_starts_with($waPhone, '0')) {
        $waPhone = '92' . substr($waPhone, 1);
    }
    $waText = rawurlencode('Your lab report is ready. Download: ' . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . $reportUrl);
    $waLink = 'https://wa.me/' . $waPhone . '?text=' . $waText;
    $barcodeBtn = '';
    $editReportBtn = '';
    $pageGroupingBtn = '';
    if (preg_match('/lab_no=([^&]+)/', $reportUrl, $m)) {
        $bLabNo = urldecode($m[1]);
        $encLab = urlencode($bLabNo);
        $barcodeBtn = '<a href="/portals/barcode-label.php?lab_no=' . $encLab . '" target="_blank" class="btn btn-secondary text-sm font-semibold" title="Print Barcode Specimen Sticker"><i class="fa-solid fa-barcode"></i> Barcode Label</a>';
        $editReportBtn = '<a href="/portals/results/entry.php?lab_no=' . $encLab . '" class="btn btn-secondary text-sm font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 border-amber-300" title="Edit Test Results & Findings"><i class="fa-solid fa-file-pen text-amber-600 mr-1"></i> Edit Results</a>';
        $pageGroupingBtn = '<button type="button" onclick="if(typeof openPageGroupingModal===\'function\'){openPageGroupingModal();}else{window.location.href=\'/portals/results/entry.php?lab_no=' . $encLab . '&open_grouping=1\';}" class="btn btn-secondary text-sm font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border-indigo-200 cursor-pointer" title="Configure or fix page layout and grouping"><i class="fa-solid fa-table-cells-large text-indigo-600 mr-1"></i> Page Grouping</button>';
    }
    $file = e($pdfFilename !== '' ? $pdfFilename : 'lab-report');

    $qsGraph = $_GET ?? [];
    $qsGraph['format'] = 'graph';
    $graphUrl = '?' . http_build_query($qsGraph);

    $qsSimple = $_GET ?? [];
    $qsSimple['format'] = 'simple';
    $simpleUrl = '?' . http_build_query($qsSimple);

    $isSimple = (isset($_GET['format']) && $_GET['format'] === 'simple') || $currentFormat === 'simple';
    $graphClass = !$isSimple ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200';
    $simpleClass = $isSimple ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200';

    $formatSwitcher = <<<HTML
    <div class="inline-flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200" title="Choose Report Style">
        <span class="text-[10px] uppercase font-bold text-slate-400 px-1.5 hidden sm:inline">Format:</span>
        <a href="{$graphUrl}" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all {$graphClass}">
            <i class="fa-solid fa-chart-simple mr-1"></i> Graph Report
        </a>
        <a href="{$simpleUrl}" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all {$simpleClass}">
            <i class="fa-solid fa-table-list mr-1"></i> Simple Report
        </a>
    </div>
    HTML;

    return <<<HTML
    <div class="no-print flex flex-wrap gap-2.5 items-center" data-print-toolbar>
        {$formatSwitcher}
        <button type="button" onclick="window.print()" class="btn btn-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button>
        <button type="button" class="btn btn-secondary" data-download-pdf data-pdf-name="{$file}"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Download PDF</button>
        {$pageGroupingBtn}
        {$barcodeBtn}
        <a href="{$waLink}" target="_blank" rel="noopener" class="btn btn-whatsapp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp</a>
        {$editReportBtn}
        <span class="text-xs text-slate-500" data-pdf-status></span>
        <div class="flex flex-wrap items-center gap-3 ml-auto text-sm text-slate-700 border border-slate-200 bg-white rounded-lg px-3 py-2">
            <span class="text-[10px] uppercase font-bold tracking-wide text-slate-400">Print options</span>
            <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                <input type="checkbox" data-print-toggle="hide-header" class="rounded border-slate-300">
                Hide Header Image
            </label>
            <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                <input type="checkbox" data-print-toggle="hide-qr" class="rounded border-slate-300">
                Hide QR Code
            </label>
            <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                <input type="checkbox" data-print-toggle="hide-footer" class="rounded border-slate-300">
                Hide Footer
            </label>
            <label class="inline-flex items-center gap-1.5 cursor-pointer select-none font-semibold">
                <input type="checkbox" data-print-toggle="hide-all" class="rounded border-slate-300">
                Hide All / Full Blanking
            </label>
        </div>
    </div>
    HTML;
}

/**
 * Render an interactive, table-based Reference Criteria editor.
 * Converts existing lines of "Criteria: Range" into table rows with inputs.
 * Syncs automatically with a hidden textarea with name $fieldName.
 */
function criteria_table_builder_field(string $fieldName, ?string $rawText = '', string $label = 'Reference Criteria Table'): string
{
    $rawText = trim((string)$rawText);
    $rows = [];
    if ($rawText !== '') {
        $lines = explode("\n", $rawText);
        foreach ($lines as $l) {
            $l = trim($l);
            if ($l === '') continue;
            $parts = explode(':', $l, 2);
            $cName = trim($parts[0] ?? '');
            $cRange = trim($parts[1] ?? '');
            if ($cName !== '' || $cRange !== '') {
                $rows[] = ['name' => $cName, 'range' => $cRange];
            }
        }
    }

    $rowCount = count($rows);
    $isOpen = $rowCount > 0 ? ' open' : '';

    $tableRowsHtml = '';
    foreach ($rows as $r) {
        $tableRowsHtml .= '<tr class="border-b border-slate-100 hover:bg-slate-50/80 transition-colors">'
            . '<td class="p-1"><input type="text" class="field text-xs py-1 px-2 w-full criteria-name-input" placeholder="e.g. Adult Male / New born" value="' . e($r['name']) . '"></td>'
            . '<td class="p-1"><input type="text" class="field text-xs py-1 px-2 w-full criteria-range-input font-mono" placeholder="e.g. 15.2 - 23.5" value="' . e($r['range']) . '"></td>'
            . '<td class="p-1 text-center"><button type="button" class="text-red-500 hover:text-red-700 font-bold p-1 leading-none text-base border-0 bg-transparent cursor-pointer" data-remove-criteria-row title="Delete row">&times;</button></td>'
            . '</tr>';
    }

    $rawTextEsc = e($rawText);

    return <<<HTML
    <details class="mt-1.5 text-xs text-slate-600 criteria-table-wrap" data-criteria-builder{$isOpen}>
        <summary class="cursor-pointer font-semibold text-slate-600 hover:text-teal-700 inline-flex items-center gap-1.5 py-0.5 select-none">
            <i class="fa-solid fa-table-list text-teal-600 text-[11px]"></i>
            <span>{$label} (<span data-criteria-count>{$rowCount}</span>)</span>
        </summary>
        <div class="mt-1.5 p-2.5 bg-slate-50 rounded-lg border border-slate-200 space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Criteria &amp; Specific Ranges</span>
                <button type="button" class="btn btn-secondary text-[11px] py-1 px-2.5 text-teal-700 bg-teal-50 border-teal-300 hover:bg-teal-100 font-semibold" data-add-criteria-row>
                    <i class="fa-solid fa-plus mr-1"></i> Add Row
                </button>
            </div>
            <table class="w-full text-xs criteria-table-editor border border-slate-200 rounded overflow-hidden bg-white">
                <thead class="bg-slate-100/90 text-[10px] font-bold text-slate-600 uppercase">
                    <tr>
                        <th class="px-2 py-1 text-left w-7/12">Criteria / Age / Condition</th>
                        <th class="px-2 py-1 text-left w-4/12">Reference Range</th>
                        <th class="px-1 py-1 text-center w-8"></th>
                    </tr>
                </thead>
                <tbody data-criteria-tbody>
                    {$tableRowsHtml}
                </tbody>
            </table>
            <textarea name="{$fieldName}" class="hidden criteria-serialized-textarea">{$rawTextEsc}</textarea>
        </div>
    </details>
    HTML;
}

function doctor_input_field(
    string $label = 'Referring doctor',
    string $name = 'doctor',
    ?string $value = null,
    bool $optional = true,
    string $placeholder = 'Select or type doctor name...'
): string {
    $val = e($value ?? '');
    $labelHtml = field_label_html($label, $optional);
    $fc = FIELD_CLASS;

    $orgId = current_user()['organization_id'] ?? 'ORG-001';

    // 1. Fetch saved / recent clinic doctors from doctor_shares (ordered by updated_at DESC so most recent is at the very top)
    $savedDoctors = [];
    $seen = [];
    try {
        $shares = db()->fetchAll(
            "SELECT doctor_name FROM doctor_shares WHERE organization_id = :org AND is_active = 1 ORDER BY updated_at DESC, created_at DESC",
            ['org' => $orgId]
        );
        foreach ($shares as $s) {
            $d = trim((string)$s['doctor_name']);
            $k = strtolower($d);
            if ($d !== '' && !isset($seen[$k])) {
                $seen[$k] = true;
                $savedDoctors[] = $d;
            }
        }
    } catch (\Throwable) {}

    // 2. Also fetch any distinct doctors recently used in lab_entries
    try {
        $labDocs = db()->fetchAll(
            "SELECT doctor FROM lab_entries WHERE organization_id = :org AND doctor IS NOT NULL AND TRIM(doctor) != '' AND doctor NOT LIKE '%Walk-in%' AND doctor NOT LIKE '%Self%' GROUP BY doctor ORDER BY MAX(created_at) DESC LIMIT 20",
            ['org' => $orgId]
        );
        foreach ($labDocs as $ld) {
            $d = trim((string)$ld['doctor']);
            $k = strtolower($d);
            if ($d !== '' && !isset($seen[$k])) {
                $seen[$k] = true;
                $savedDoctors[] = $d;
            }
        }
    } catch (\Throwable) {}

    // 3. Organization staff doctors / signatories (strictly this organization)
    $staffDoctors = [];
    try {
        $sigs = db()->fetchAll(
            "SELECT name, designation FROM report_signatories WHERE organization_id = :org AND is_active = 1 ORDER BY sort_order ASC, name ASC",
            ['org' => $orgId]
        );
        foreach ($sigs as $s) {
            $d = trim((string)($s['name'] ?? ''));
            $k = strtolower($d);
            if ($d !== '' && $k !== 'walk-in / self' && !isset($seen[$k])) {
                $seen[$k] = true;
                $desig = trim((string)($s['designation'] ?? ''));
                $staffDoctors[] = [
                    'name' => $d,
                    'label' => $d . ($desig !== '' ? ' (' . $desig . ')' : '')
                ];
            }
        }
        $emps = db()->fetchAll(
            "SELECT full_name, designation FROM employees WHERE organization_id = :org AND is_active = 1 AND (full_name LIKE 'Dr%' OR designation LIKE '%Doctor%' OR designation LIKE '%Consultant%' OR designation LIKE '%Pathologist%' OR designation LIKE '%Radiologist%') ORDER BY full_name ASC",
            ['org' => $orgId]
        );
        foreach ($emps as $em) {
            $d = trim((string)($em['full_name'] ?? ''));
            $k = strtolower($d);
            if ($d !== '' && $k !== 'walk-in / self' && !isset($seen[$k])) {
                $seen[$k] = true;
                $desig = trim((string)($em['designation'] ?? ''));
                $staffDoctors[] = [
                    'name' => $d,
                    'label' => $d . ($desig !== '' ? ' (' . $desig . ')' : '')
                ];
            }
        }
    } catch (\Throwable) {}

    $uniqueId = 'doc_' . substr(md5($name . rand()), 0, 6);
    $dropdownId = 'drop_' . $uniqueId;

    // Build the dropdown list items
    $itemsHtml = '';
    
    // Default Walk-in
    $itemsHtml .= '<div class="px-3.5 py-2 hover:bg-slate-100 cursor-pointer text-slate-800 font-semibold border-b border-slate-100 flex items-center justify-between" onclick="selectDocItem(\'' . $uniqueId . '\', \'Walk-in / Self\')">';
    $itemsHtml .= '<span>Walk-in / Self</span><span class="text-[10px] text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded font-normal">Default</span>';
    $itemsHtml .= '</div>';

    if (!empty($savedDoctors)) {
        $itemsHtml .= '<div class="px-3.5 py-1.5 text-[10px] uppercase font-bold text-teal-800 bg-teal-50/80 tracking-wider">⭐ Saved Clinic Doctors (Recently Used)</div>';
        foreach ($savedDoctors as $d) {
            $itemsHtml .= '<div class="px-3.5 py-2 hover:bg-teal-50/60 cursor-pointer text-slate-800 font-medium doc-option-item flex items-center justify-between" onclick="selectDocItem(\'' . $uniqueId . '\', \'' . e(addslashes($d)) . '\')">';
            $itemsHtml .= '<span>' . e($d) . '</span>';
            $itemsHtml .= '</div>';
        }
    }

    if (!empty($staffDoctors)) {
        $itemsHtml .= '<div class="px-3.5 py-1.5 text-[10px] uppercase font-bold text-slate-400 bg-slate-50 tracking-wider">Other Specialists</div>';
        foreach ($staffDoctors as $sd) {
            $itemsHtml .= '<div class="px-3.5 py-2 hover:bg-slate-100 cursor-pointer text-slate-700 doc-option-item flex items-center justify-between" onclick="selectDocItem(\'' . $uniqueId . '\', \'' . e(addslashes($sd['name'])) . '\')">';
            $itemsHtml .= '<span>' . e($sd['label']) . '</span>';
            $itemsHtml .= '</div>';
        }
    }

    return <<<HTML
    <div class="relative" id="container-{$uniqueId}">
        <label class="field-label" for="{$uniqueId}">{$labelHtml}</label>
        <div class="relative">
            <input type="text" id="{$uniqueId}" name="{$name}" class="{$fc} w-full pr-9 font-medium cursor-pointer" value="{$val}" placeholder="{$placeholder}" autocomplete="off"
                   onfocus="openDocDropdown('{$uniqueId}')"
                   onclick="openDocDropdown('{$uniqueId}')"
                   oninput="filterDocDropdown('{$uniqueId}', this.value)"
                   onblur="handleDocInputBlur('{$uniqueId}', this.value)">
            <button type="button" tabindex="-1" onclick="toggleDocDropdown('{$uniqueId}')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-700 cursor-pointer">
                <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200" id="chevron-{$uniqueId}"></i>
            </button>
        </div>
        <!-- Single Unified Dropdown Menu -->
        <div id="{$dropdownId}" class="absolute z-50 left-0 right-0 mt-1 hidden max-h-56 overflow-y-auto bg-white rounded-2xl shadow-xl border border-slate-200 py-1 text-xs">
            {$itemsHtml}
        </div>
        <p class="text-[11px] text-slate-500 mt-1">Select from saved clinic list or freely type any doctor name.</p>
        <script>
        if (typeof window.saveDoctorQuick === 'undefined') {
            window.saveDoctorQuick = function(val) {
                val = (val || '').trim();
                if (!val || val.toLowerCase().indexOf('walk-in') !== -1 || val.toLowerCase() === 'self') return;
                try {
                    fetch('/api/doctors.php?name=' + encodeURIComponent(val), { method: 'POST' }).catch(function(){});
                } catch(e) {}
            };
            window.openDocDropdown = function(id) {
                const el = document.getElementById('drop_' + id);
                const ch = document.getElementById('chevron-' + id);
                if (el) el.classList.remove('hidden');
                if (ch) ch.classList.add('rotate-180');
            };
            window.closeDocDropdown = function(id) {
                const el = document.getElementById('drop_' + id);
                const ch = document.getElementById('chevron-' + id);
                if (el) el.classList.add('hidden');
                if (ch) ch.classList.remove('rotate-180');
            };
            window.toggleDocDropdown = function(id) {
                const el = document.getElementById('drop_' + id);
                if (el && el.classList.contains('hidden')) {
                    window.openDocDropdown(id);
                } else {
                    window.closeDocDropdown(id);
                }
            };
            window.selectDocItem = function(id, val) {
                const input = document.getElementById(id);
                if (input) {
                    input.value = val;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
                window.closeDocDropdown(id);
                window.saveDoctorQuick(val);
            };
            window.filterDocDropdown = function(id, query) {
                window.openDocDropdown(id);
                const drop = document.getElementById('drop_' + id);
                if (!drop) return;
                const q = (query || '').toLowerCase().trim();
                const items = drop.querySelectorAll('.doc-option-item');
                items.forEach(function(item) {
                    const txt = (item.textContent || '').toLowerCase();
                    item.style.display = (q === '' || txt.indexOf(q) !== -1) ? 'flex' : 'none';
                });
            };
            window.handleDocInputBlur = function(id, val) {
                setTimeout(function() {
                    window.closeDocDropdown(id);
                    window.saveDoctorQuick(val);
                }, 250);
            };
            document.addEventListener('click', function(e) {
                if (!e.target.closest('[id^="container-doc_"]')) {
                    document.querySelectorAll('[id^="drop_doc_"]').forEach(function(d) {
                        d.classList.add('hidden');
                    });
                    document.querySelectorAll('[id^="chevron-doc_"]').forEach(function(c) {
                        c.classList.remove('rotate-180');
                    });
                }
            });
        }
        </script>
    </div>
    HTML;
}

function route_input_field(
    string $label = 'Route to',
    string $name = 'route',
    ?string $value = 'Laboratory — Pathology (HQ)',
    bool $optional = true,
    string $placeholder = 'Type route or select from list...'
): string {
    $val = e($value ?? 'Laboratory — Pathology (HQ)');
    $labelHtml = field_label_html($label, $optional);
    $fc = FIELD_CLASS;

    $options = ['' => '— Quick select route —'];
    foreach (mock('mock_routes') as $r) {
        $options[$r['label']] = $r['label'];
    }

    $selectOpts = '';
    foreach ($options as $k => $text) {
        $selectOpts .= '<option value="' . e((string)$k) . '">' . e($text) . '</option>';
    }

    $uniqueId = 'route_' . substr(md5($name . rand()), 0, 6);

    return <<<HTML
    <div>
        <label class="field-label" for="{$uniqueId}">{$labelHtml}</label>
        <div class="flex gap-2">
            <select class="{$fc} text-xs w-2/5 shrink-0" onchange="if(this.value){ document.getElementById('{$uniqueId}').value = this.value; }">
                {$selectOpts}
            </select>
            <input type="text" id="{$uniqueId}" name="{$name}" class="{$fc} flex-1" value="{$val}" placeholder="{$placeholder}">
        </div>
        <p class="text-[11px] text-slate-500 mt-1">Pick bench or freely type any custom route destination.</p>
    </div>
    HTML;
}

function title_input_field(
    string $label = 'Title',
    string $name = 'title',
    ?string $value = null,
    bool $optional = true,
    string $placeholder = 'Optional (Mr, Mrs, Dr...)'
): string {
    $val = e($value ?? '');
    $labelHtml = field_label_html($label, $optional);
    $fc = FIELD_CLASS;

    $presetTitles = [
        'Mr' => 'Mr',
        'Mrs' => 'Mrs',
        'Ms' => 'Ms',
        'Miss' => 'Miss',
        'Dr' => 'Dr',
        'Baby' => 'Baby',
        'Master' => 'Master',
    ];

    $uniqueId = 'title_' . substr(md5($name . rand()), 0, 6);
    $dropdownId = 'drop_' . $uniqueId;

    $itemsHtml = '';
    // Optional / Blank option to easily clear or remove title
    $itemsHtml .= '<div class="px-3 py-2 hover:bg-slate-100 cursor-pointer text-slate-500 font-medium border-b border-slate-100 flex items-center justify-between" onclick="selectTitleItem(\'' . $uniqueId . '\', \'\')">';
    $itemsHtml .= '<span>&mdash; None / Blank (Optional) &mdash;</span><span class="text-[10px] text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">Clear</span>';
    $itemsHtml .= '</div>';

    foreach ($presetTitles as $tKey => $tLabel) {
        $itemsHtml .= '<div class="px-3.5 py-2 hover:bg-teal-50/70 cursor-pointer text-slate-800 font-semibold title-option-item flex items-center justify-between" onclick="selectTitleItem(\'' . $uniqueId . '\', \'' . e(addslashes($tKey)) . '\')">';
        $itemsHtml .= '<span>' . e($tLabel) . '</span>';
        $itemsHtml .= '</div>';
    }

    return <<<HTML
    <div class="relative" id="container-{$uniqueId}">
        <label class="field-label" for="{$uniqueId}">{$labelHtml}</label>
        <div class="relative">
            <input type="text" id="{$uniqueId}" name="{$name}" class="{$fc} w-full pr-9 font-medium" value="{$val}" placeholder="{$placeholder}" autocomplete="off"
                   onfocus="openTitleDropdown('{$uniqueId}')"
                   onclick="openTitleDropdown('{$uniqueId}')"
                   oninput="filterTitleDropdown('{$uniqueId}', this.value)"
                   onblur="handleTitleInputBlur('{$uniqueId}')">
            <button type="button" tabindex="-1" onclick="toggleTitleDropdown('{$uniqueId}')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-700 cursor-pointer" title="Choose title or leave blank">
                <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200" id="chevron-{$uniqueId}"></i>
            </button>
        </div>
        <div id="{$dropdownId}" class="absolute z-50 left-0 right-0 mt-1 hidden max-h-56 overflow-y-auto bg-white rounded-2xl shadow-xl border border-slate-200 py-1 text-xs">
            {$itemsHtml}
        </div>
        <p class="text-[11px] text-slate-500 mt-1">Optional. Select from list, type custom, or leave blank.</p>
        <script>
        if (typeof window.openTitleDropdown === 'undefined') {
            window.openTitleDropdown = function(id) {
                const el = document.getElementById('drop_' + id);
                const ch = document.getElementById('chevron-' + id);
                if (el) el.classList.remove('hidden');
                if (ch) ch.classList.add('rotate-180');
            };
            window.closeTitleDropdown = function(id) {
                const el = document.getElementById('drop_' + id);
                const ch = document.getElementById('chevron-' + id);
                if (el) el.classList.add('hidden');
                if (ch) ch.classList.remove('rotate-180');
            };
            window.toggleTitleDropdown = function(id) {
                const drop = document.getElementById('drop_' + id);
                if (drop && drop.classList.contains('hidden')) {
                    window.openTitleDropdown(id);
                } else {
                    window.closeTitleDropdown(id);
                }
            };
            window.selectTitleItem = function(id, val) {
                const input = document.getElementById(id);
                if (input) {
                    input.value = val;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
                window.closeTitleDropdown(id);
            };
            window.filterTitleDropdown = function(id, query) {
                window.openTitleDropdown(id);
                const drop = document.getElementById('drop_' + id);
                if (!drop) return;
                const q = (query || '').toLowerCase().trim();
                const items = drop.querySelectorAll('.title-option-item');
                items.forEach(function(item) {
                    const txt = (item.textContent || '').toLowerCase();
                    item.style.display = (q === '' || txt.indexOf(q) !== -1) ? 'flex' : 'none';
                });
            };
            window.handleTitleInputBlur = function(id) {
                setTimeout(function() {
                    window.closeTitleDropdown(id);
                }, 250);
            };
            document.addEventListener('click', function(e) {
                if (!e.target.closest('[id^="container-title_"]')) {
                    document.querySelectorAll('[id^="drop_title_"]').forEach(function(d) {
                        d.classList.add('hidden');
                    });
                    document.querySelectorAll('[id^="chevron-title_"]').forEach(function(c) {
                        c.classList.remove('rotate-180');
                    });
                }
            });
        }
        </script>
    </div>
    HTML;
}



