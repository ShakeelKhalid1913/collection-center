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

function catalog_picker(string $mode = 'pathology', array $selectedNames = []): string
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
        if ($mode === 'pathology' && ($t['category'] ?? '') === 'Radiology') {
            continue;
        }
        $cat = (string)($t['category'] ?? 'Other');
        $items[] = [
            'dept' => strtolower($cat),
            'dept_label' => $cat,
            'name' => $t['name'],
            'code' => $t['code'],
            'meta' => $cat . ' · Sample: ' . ($t['sample'] ?? '—'),
            'price' => (int)$t['price'],
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
        if ($mode === 'pathology' && $dk === 'radiology') {
            continue;
        }
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
        $searchHits .= '<button type="button" class="catalog-hit" data-catalog-hit'
            . ' data-name="' . e($it['name']) . '"'
            . ' data-dept="' . e($it['dept']) . '"'
            . ' data-search="' . e($it['search']) . '"'
            . ' data-price="' . $it['price'] . '">'
            . '<span class="catalog-hit__body">'
            . '<span class="catalog-hit__title">' . e($it['name']) . ' <span class="catalog-code">' . e($it['code']) . '</span></span>'
            . '<span class="catalog-hit__meta">' . e($it['meta']) . '</span>'
            . '</span>'
            . '<span class="catalog-hit__price">' . e(format_money((float)$it['price'])) . '</span>'
            . '</button>';

        $isSelected = isset($selectedLookup[strtolower($it['name'])]);
        $checked = $isSelected ? ' checked' : '';
        $rowHidden = $isSelected ? '' : ' hidden';

        $hiddenChecks .= '<label class="catalog-item catalog-item--selected' . $rowHidden . '" data-catalog-item data-dept="' . e($it['dept']) . '" data-name="' . e($it['search']) . '" data-price="' . $it['price'] . '" data-code="' . e($it['code']) . '" data-meta="' . e($it['meta']) . '">'
            . '<input type="checkbox" name="tests[]" value="' . e($it['name']) . '" class="catalog-check" data-price="' . $it['price'] . '" data-code="' . e($it['code']) . '"' . $checked . '>'
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



function billing_panel(): string
{
    return <<<HTML
    <div class="billing-panel" data-billing>
        <input type="hidden" name="amount" value="0" data-amount-input>
        <div class="billing-row"><span>Subtotal</span><strong data-subtotal>Rs. 0</strong></div>
        <div class="billing-fields">
            <div>
                <label class="field-label" for="discount">Discount (Rs.)</label>
                <input type="number" id="discount" name="discount" class="field" value="0" min="0" data-discount>
            </div>
            <div>
                <label class="field-label" for="discount_pct">Discount %</label>
                <input type="number" id="discount_pct" name="discount_pct" class="field" value="0" min="0" max="100" data-discount-pct>
            </div>
        </div>
        <div class="billing-row billing-row--total"><span>Net total</span><strong data-total>Rs. 0</strong></div>
        <div class="billing-fields">
            <div>
                <label class="field-label" for="paid">Paid amount (Rs.)</label>
                <input type="number" id="paid" name="paid" class="field" value="0" min="0" data-paid>
            </div>
            <div>
                <label class="field-label" for="pay_mode">Payment mode</label>
                <select id="pay_mode" name="pay_mode" class="field">
                    <option>Cash</option>
                    <option>Card</option>
                    <option>Bank transfer</option>
                    <option>JazzCash / EasyPaisa</option>
                    <option>Credit / Due</option>
                </select>
            </div>
        </div>
        <div class="billing-row"><span>Remaining</span><strong data-remaining class="text-amber-700">Rs. 0</strong></div>
        <p class="billing-hint">Remaining updates as you change discount and paid amount.</p>
    </div>
    HTML;
}

function report_actions(string $patientPhone, string $reportUrl = '', string $pdfFilename = 'lab-report'): string
{
    if ($reportUrl === '') {
        $portal = current_portal();
        $reportUrl = $portal === 'collection-center'
            ? '/portals/collection-center/reports/preview.php'
            : ($portal === 'imaging'
                ? '/portals/imaging/reports.php'
                : '/portals/main-lab/reports/preview.php');
    }
    $waPhone = preg_replace('/\D/', '', $patientPhone);
    if (str_starts_with($waPhone, '0')) {
        $waPhone = '92' . substr($waPhone, 1);
    }
    $waText = rawurlencode('Your lab report is ready. Download: ' . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . $reportUrl);
    $waLink = 'https://wa.me/' . $waPhone . '?text=' . $waText;
    $file = e(preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $pdfFilename) ?: 'lab-report');

    return <<<HTML
    <div class="no-print flex flex-wrap gap-3 items-center" data-print-toolbar>
        <button type="button" onclick="window.print()" class="btn btn-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button>
        <button type="button" class="btn btn-secondary" data-download-pdf data-pdf-name="{$file}"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Download PDF</button>
        <a href="{$waLink}" target="_blank" rel="noopener" class="btn btn-whatsapp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp</a>
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

