<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$labNo = trim($_GET['lab_no'] ?? '');
$ctx = load_document_context($labNo !== '' ? $labNo : null);
$entry = $ctx['entry'];
$labNo = $entry['lab_no'] ?? $labNo;

$receiptUrl = '/portals/receipts.php?lab_no=' . urlencode((string)$labNo);
$reportUrl = '/portals/reports/preview.php?lab_no=' . urlencode((string)$labNo);
$barcodeUrl = '/portals/barcode-label.php?lab_no=' . urlencode((string)$labNo);

$content = workflow_stepper('booking', (string)$labNo, $ctx['patient']['id'] ?? null);
$content .= page_header(
    'Entry Saved · Order #' . ($labNo ?: '—'),
    'Booking confirmed. Print receipt, barcode label, or continue to next patient.'
);

$content .= card(
    '<div class="p-4 sm:p-6 space-y-4">' .
    '<div class="p-4 bg-slate-50 border border-slate-200 rounded-xl flex flex-wrap items-center justify-between gap-3">' .
    '<div><div class="text-sm font-bold text-slate-900">' . e($ctx['patient']['name'] ?? 'Walk-in') . ' · MR: ' . e($ctx['patient']['mr'] ?? $ctx['patient']['id'] ?? '—') . '</div>' .
    '<div class="text-xs text-slate-500 mt-0.5"><strong>Tests:</strong> ' . e($entry['tests'] ?? '—') . '</div></div>' .
    '</div>' .
    '<div class="flex flex-wrap gap-2.5 pt-1">' .
    btn_primary($receiptUrl, 'Print Patient Bill', 'fa-solid fa-receipt') .
    btn_primary($barcodeUrl, 'Print Barcode Label', 'fa-solid fa-barcode') .
    btn_secondary($reportUrl, 'Preview Report', 'fa-solid fa-file-medical') .
    btn_secondary('/portals/lab-entries/new.php', '+ Next Booking', 'fa-solid fa-plus') .
    '</div>' .
    '</div>'
);

render_page('Entry saved', 'collection-center', 'new-entry', $content);
