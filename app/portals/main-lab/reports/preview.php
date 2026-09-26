<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$labNo = trim($_GET['lab_no'] ?? '');
$ctx = load_document_context($labNo !== '' ? $labNo : null);
$previewUrl = '/portals/main-lab/reports/preview.php' . ($labNo !== '' ? '?lab_no=' . urlencode($labNo) : '');

$content = page_header('Report Preview', 'High-contrast layout for print and PDF export.');
$content .= report_actions($ctx['patient']['phone'] ?? '', $previewUrl);
$content .= '<div class="mt-6">' . render_report_document($ctx['settings'], $ctx['patient'], $ctx['lines'], $ctx['entry']) . '</div>';

render_page('Report Preview', 'main-lab', 'reports-generate', $content, true);
