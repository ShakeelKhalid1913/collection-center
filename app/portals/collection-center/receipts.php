<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$labNo = trim($_GET['lab_no'] ?? '');
$ctx = load_document_context($labNo !== '' ? $labNo : null);
$settings = $ctx['settings'];
$patient = $ctx['patient'];
$entry = $ctx['entry'] ?? ['lab_no' => '—', 'tests' => '—', 'amount' => 0, 'paid' => 0, 'discount' => 0];

$reportUrl = '/portals/collection-center/reports/preview.php?lab_no=' . urlencode((string)($entry['lab_no'] ?? ''));

$content = page_header(
    'Receipts',
    'Printable cash receipt with your lab header / footer.',
    btn_secondary($reportUrl, 'Open report preview')
);
$content .= report_actions($patient['phone'] ?? '', $reportUrl);
$content .= '<div class="mt-4">' . render_receipt_document($settings, $entry, $patient) . '</div>';

render_page('Receipts', 'collection-center', 'receipts', $content, true);
