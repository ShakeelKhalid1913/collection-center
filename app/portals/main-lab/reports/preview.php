<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$labNo = trim($_GET['lab_no'] ?? '');
$ctx = load_document_context($labNo !== '' ? $labNo : null);
$previewUrl = '/portals/main-lab/reports/preview.php' . ($labNo !== '' ? '?lab_no=' . urlencode($labNo) : '');

$content = page_header('Report Preview', 'High-contrast layout for print and PDF export.');
$content .= '<div class="no-print mb-4 flex flex-wrap gap-2 items-center justify-between">';
$content .= report_actions($ctx['patient']['phone'] ?? '', $previewUrl, 'report-' . ($labNo !== '' ? $labNo : 'preview'));
if ($labNo !== '') {
    $content .= '<a href="/portals/main-lab/results/entry.php?lab_no=' . urlencode($labNo) . '" class="btn btn-primary"><i class="fa-solid fa-pen-to-square mr-1"></i> Edit Test Results for this Patient</a>';
}
$content .= '</div>';
$content .= '<div class="mt-4">' . render_report_document(
    $ctx['settings'],
    $ctx['patient'],
    $ctx['lines'],
    $ctx['entry'],
    $ctx['report_title'] ?? 'DEPARTMENT OF LABORATORY MEDICINE',
    $ctx['signatories'] ?? [],
    $ctx['specimen'] ?? 'Serum'
) . '</div>';

render_page('Report Preview', 'main-lab', 'reports-generate', $content, true);
