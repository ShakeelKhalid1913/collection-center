<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$labNo = trim($_GET['lab_no'] ?? '');
$ctx = load_document_context($labNo !== '' ? $labNo : null);

$previewUrl = '/portals/collection-center/reports/preview.php' . ($labNo !== '' ? '?lab_no=' . urlencode($labNo) : '');

$content = page_header(
    'Report Preview',
    'Collection Center print/PDF preview — uses lab branding header, address & footer. Optional CNIC / blood group / email only if entered.'
);
$content .= report_actions($ctx['patient']['phone'] ?? '', $previewUrl, 'report-' . ($labNo !== '' ? $labNo : 'preview'));
$content .= '<div class="mt-6 no-print flex flex-wrap gap-2">' .
    btn_secondary('/portals/collection-center/receipts.php?lab_no=' . urlencode((string)($ctx['entry']['lab_no'] ?? '')), 'Receipt') .
    btn_secondary('/portals/collection-center/branding.php', 'Edit header / footer') .
    '</div>';
$content .= render_report_document(
    $ctx['settings'],
    $ctx['patient'],
    $ctx['lines'],
    $ctx['entry'],
    $ctx['report_title'] ?? 'DEPARTMENT OF LABORATORY MEDICINE',
    $ctx['signatories'] ?? [],
    $ctx['specimen'] ?? 'Serum'
);

render_page('Report Preview', 'collection-center', 'reports', $content, true);
