<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$labNo = trim($_GET['lab_no'] ?? '');
if ($labNo !== '') {
    lab_repo()->updateTransitStatus($labNo, 'verified');
    audit_log('GENERATE_REPORT', 'lab_entries', $labNo, 'Generated/Viewed verified diagnostic report');
}
$ctx = load_document_context($labNo !== '' ? $labNo : null);
$previewUrl = '/portals/main-lab/reports/preview.php' . ($labNo !== '' ? '?lab_no=' . urlencode($labNo) : '');

$content = '<div class="no-print">' . page_header('Report Preview', 'High-contrast layout for print and PDF export.') . '</div>';
$content .= '<div class="no-print mb-4">';
$content .= report_actions($ctx['patient']['phone'] ?? '', $previewUrl, 'report-' . ($labNo !== '' ? $labNo : 'preview'));
$content .= '</div>';
$content .= render_report_document(
    $ctx['settings'],
    $ctx['patient'],
    $ctx['lines'],
    $ctx['entry'],
    $ctx['report_title'] ?? 'DEPARTMENT OF LABORATORY MEDICINE',
    $ctx['signatories'] ?? [],
    $ctx['specimen'] ?? 'Serum'
);

render_page('Report Preview', 'main-lab', 'reports-generate', $content, true);
