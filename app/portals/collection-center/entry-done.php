<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$labNo = trim($_GET['lab_no'] ?? '');
$ctx = load_document_context($labNo !== '' ? $labNo : null);
$entry = $ctx['entry'];
$labNo = $entry['lab_no'] ?? $labNo;

$receiptUrl = '/portals/collection-center/receipts.php?lab_no=' . urlencode((string)$labNo);
$reportUrl = '/portals/collection-center/reports/preview.php?lab_no=' . urlencode((string)$labNo);

$content = page_header(
    'Entry saved',
    'Lab No ' . ($labNo ?: '—') . ' · choose receipt or report preview (Collection Center — no Laboratory login needed).'
);

$content .= flash_success('Patient / lab entry saved successfully.');
$content .= card(
    '<div class="p-4 sm:p-6 space-y-4">' .
    '<p class="text-sm text-slate-700"><strong>Patient:</strong> ' . e($ctx['patient']['name'] ?? '') . '<br>' .
    '<strong>Tests:</strong> ' . e($entry['tests'] ?? '') . '</p>' .
    '<div class="flex flex-wrap gap-2">' .
    btn_primary($receiptUrl, 'Preview / print receipt', 'fa-solid fa-receipt') .
    btn_primary($reportUrl, 'Preview / print report', 'fa-solid fa-file-medical') .
    btn_secondary('/portals/collection-center/lab-entries/new.php', 'New entry') .
    btn_secondary('/portals/collection-center/patients/quick-register.php', 'Quick registration') .
    '</div>' .
    '<p class="text-xs text-slate-500">Report shows pending tests until Laboratory enters &amp; verifies results. Header/footer come from <a href="/portals/collection-center/branding.php" class="text-teal-700 font-semibold">Branding</a>.</p>' .
    '</div>'
);

render_page('Entry saved', 'collection-center', 'new-entry', $content);
