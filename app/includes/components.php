<?php

declare(strict_types=1);

function card(string $content, string $class = ''): string
{
    return '<div class="app-panel ' . e($class) . '">' . $content . '</div>';
}

function panel_head(string $title, ?string $subtitle = null): string
{
    $sub = $subtitle ? '<span class="muted"> — ' . e($subtitle) . '</span>' : '';
    return '<div class="app-panel-head">' . e($title) . $sub . '</div>';
}

function data_table(array $headers, array $rows): string
{
    $thead = '';
    foreach ($headers as $h) {
        $thead .= '<th>' . e($h) . '</th>';
    }

    $tbody = '';
    foreach ($rows as $row) {
        $tbody .= '<tr>';
        foreach ($row as $cell) {
            $tbody .= '<td>' . $cell . '</td>';
        }
        $tbody .= '</tr>';
    }

    return <<<HTML
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr>{$thead}</tr></thead>
            <tbody>{$tbody}</tbody>
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

function catalog_picker(string $mode = 'pathology'): string
{
    $search = '<div class="mb-3"><input type="search" id="catalog-search" class="field" placeholder="Search test / package by name or code…" data-catalog-search></div>';

    $packages = '';
    foreach (mock('mock_packages') as $pkg) {
        $packages .= '<label class="catalog-item" data-catalog-item data-name="' . e(strtolower($pkg['name'] . ' ' . $pkg['code'])) . '">'
            . '<input type="checkbox" name="tests[]" value="' . e($pkg['name']) . '" class="catalog-check" data-price="' . (int) $pkg['price'] . '" data-code="' . e($pkg['code']) . '">'
            . '<span class="catalog-item__body">'
            . '<span class="catalog-item__title">' . e($pkg['name']) . ' <span class="catalog-code">' . e($pkg['code']) . '</span></span>'
            . '<span class="catalog-item__meta">Package · ' . e($pkg['tests']) . '</span>'
            . '</span>'
            . '<span class="catalog-item__price">' . e(format_money((float) $pkg['price'])) . '</span>'
            . '</label>';
    }

    $tests = '';
    foreach (mock('mock_tests') as $t) {
        if ($mode === 'pathology' && $t['category'] === 'Radiology') {
            continue;
        }
        $tests .= '<label class="catalog-item" data-catalog-item data-name="' . e(strtolower($t['name'] . ' ' . $t['code'] . ' ' . $t['category'])) . '">'
            . '<input type="checkbox" name="tests[]" value="' . e($t['name']) . '" class="catalog-check" data-price="' . (int) $t['price'] . '" data-code="' . e($t['code']) . '">'
            . '<span class="catalog-item__body">'
            . '<span class="catalog-item__title">' . e($t['name']) . ' <span class="catalog-code">' . e($t['code']) . '</span></span>'
            . '<span class="catalog-item__meta">' . e($t['category']) . ' · Sample: ' . e($t['sample']) . '</span>'
            . '</span>'
            . '<span class="catalog-item__price">' . e(format_money((float) $t['price'])) . '</span>'
            . '</label>';
    }

    return $search
        . '<div class="catalog-section"><p class="catalog-section__title">Packages</p><div class="catalog-list">' . $packages . '</div></div>'
        . '<div class="catalog-section mt-4"><p class="catalog-section__title">Individual tests</p><div class="catalog-list">' . $tests . '</div></div>';
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

function report_actions(string $patientPhone, string $reportUrl = '/portals/main-lab/reports/preview.php'): string
{
    $waPhone = preg_replace('/\D/', '', $patientPhone);
    if (str_starts_with($waPhone, '0')) {
        $waPhone = '92' . substr($waPhone, 1);
    }
    $waText = rawurlencode('Your lab report is ready. Download: ' . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . $reportUrl);
    $waLink = 'https://wa.me/' . $waPhone . '?text=' . $waText;

    return <<<HTML
    <div class="no-print flex flex-wrap gap-2">
        <button type="button" onclick="window.print()" class="btn btn-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button>
        <a href="{$reportUrl}" class="btn btn-secondary"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Download PDF</a>
        <a href="{$waLink}" target="_blank" rel="noopener" class="btn btn-whatsapp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp</a>
    </div>
    HTML;
}

function render_report_document(array $settings, array $patient, array $resultLines): string
{
    $logoImg = brand_logo('brand-logo brand-logo--report');
    $name = e($settings['name']);
    $header = e($settings['header']);
    $address = e($settings['address']);
    $phone = e($settings['phone']);
    $footer = e($settings['footer']);
    $pname = e($patient['name']);
    $pid = e($patient['id']);
    $page = e((string) $patient['age']);
    $pgender = e($patient['gender']);
    $pphone = e($patient['phone']);

    $rows = '';
    foreach ($resultLines as $line) {
        $flag = ($line['flag'] ?? '') === 'critical'
            ? '<span class="font-bold text-black">HIGH</span>'
            : e($line['flag'] ?? '—');
        $rows .= '<tr class="border-b border-black">
            <td class="py-2 pr-4 text-sm">' . e($line['test']) . '</td>
            <td class="py-2 pr-4 text-sm font-semibold">' . e($line['result']) . '</td>
            <td class="py-2 pr-4 text-sm">' . e($line['unit']) . '</td>
            <td class="py-2 pr-4 text-sm">' . e($line['range']) . '</td>
            <td class="py-2 text-sm">' . $flag . '</td>
        </tr>';
    }

    return <<<HTML
    <div class="print-area mx-auto max-w-3xl border-2 border-black bg-white p-6 text-black sm:p-8">
        <div class="border-b-2 border-black pb-4">
            <div class="flex items-start justify-between gap-4">
                <div class="h-14 w-14 overflow-hidden border-2 border-black">{$logoImg}</div>
                <div class="text-right text-sm leading-snug">
                    <p class="text-lg font-bold">{$name}</p>
                    <p>{$header}</p>
                    <p>{$address}</p>
                    <p>{$phone}</p>
                </div>
            </div>
        </div>

        <div class="mt-4 grid gap-2 border border-black p-4 text-sm sm:grid-cols-2">
            <p><strong>Patient:</strong> {$pname}</p>
            <p><strong>Patient ID:</strong> {$pid}</p>
            <p><strong>Age / Sex:</strong> {$page} / {$pgender}</p>
            <p><strong>Phone:</strong> {$pphone}</p>
            <p><strong>Lab No:</strong> L-2026-0891</p>
            <p><strong>Report Date:</strong> 16 Sep 2026</p>
        </div>

        <table class="mt-6 w-full border-collapse text-left">
            <thead>
                <tr class="border-b-2 border-black">
                    <th class="pb-2 text-xs font-bold uppercase">Test</th>
                    <th class="pb-2 text-xs font-bold uppercase">Result</th>
                    <th class="pb-2 text-xs font-bold uppercase">Unit</th>
                    <th class="pb-2 text-xs font-bold uppercase">Reference</th>
                    <th class="pb-2 text-xs font-bold uppercase">Flag</th>
                </tr>
            </thead>
            <tbody>{$rows}</tbody>
        </table>

        <div class="mt-8 border-t border-black pt-4 text-sm">
            <p class="font-semibold">Verified by: Dr. Imran Sheikh (Pathologist)</p>
            <p class="mt-4 text-xs leading-relaxed">{$footer}</p>
        </div>
    </div>
    HTML;
}
