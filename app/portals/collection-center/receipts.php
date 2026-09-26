<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$settings = mock('mock_lab_settings');
$entries = mock('mock_lab_entries');
$entry = $entries[0];

$receipt = <<<HTML
<div class="print-area mx-auto max-w-lg border-2 border-black bg-white p-6 text-black">
    <div class="text-center border-b border-black pb-3">
        <p class="text-lg font-bold">{$settings['name']}</p>
        <p class="text-sm">{$settings['address']}</p>
        <p class="text-sm">{$settings['phone']}</p>
        <p class="mt-2 text-xs font-bold uppercase tracking-widest">Cash Receipt</p>
    </div>
    <div class="mt-4 space-y-1 text-sm">
        <p><strong>Receipt #:</strong> R-2026-7781</p>
        <p><strong>Lab No:</strong> {$entry['lab_no']}</p>
        <p><strong>Patient:</strong> {$entry['patient']}</p>
        <p><strong>Tests:</strong> {$entry['tests']}</p>
        <p><strong>Date:</strong> 16 Sep 2026</p>
    </div>
    <div class="mt-4 border-t border-black pt-3 text-sm">
        <p class="flex justify-between"><span>Total</span><strong>{$entry['amount']}</strong></p>
        <p class="flex justify-between"><span>Paid</span><strong>{$entry['paid']}</strong></p>
    </div>
</div>
HTML;

$content = page_header('Receipts', 'High-contrast receipt for thermal / laser printing.');
$content .= report_actions('0300-1122334');
$content .= '<div class="mt-4">' . $receipt . '</div>';

render_page('Receipts', 'collection-center', 'receipts', $content);
