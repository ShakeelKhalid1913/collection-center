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

$content = '<div class="no-print">' . page_header('Report Preview', 'Unified multi-department diagnostic dossier for Laboratory, Ultrasound, X-Ray, ECG, CT Scan, and MRI.') . '</div>';

$entryTests = (string)($ctx['entry']['tests'] ?? '');
if ($entryTests !== '') {
    $deptBadges = render_department_badges_html($entryTests, $labNo);
    $qLab = urlencode($labNo);
    $content .= <<<HTML
    <div class="no-print mb-4 p-3 bg-white rounded-2xl border border-slate-200/90 shadow-2xs flex flex-wrap items-center justify-between gap-2.5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                <i class="fa-solid fa-layer-group text-slate-400"></i> Order Departments:
            </span>
            {$deptBadges}
        </div>
        <div class="flex items-center gap-2 text-xs">
            <a href="/portals/main-lab/receipts.php?lab_no={$qLab}" class="btn btn-secondary py-1.5 px-3 font-semibold text-slate-700" title="View Patient Payment & Token Slip">
                <i class="fa-solid fa-receipt mr-1 text-slate-500"></i> Patient Bill
            </a>
            <a href="/portals/main-lab/results/entry.php?lab_no={$qLab}" class="btn btn-secondary py-1.5 px-3 font-semibold text-blue-700 hover:bg-blue-50" title="Edit Laboratory Biomarkers">
                <i class="fa-solid fa-flask-vial text-blue-600 mr-1"></i> Lab Entry
            </a>
            <a href="/portals/imaging/results.php?lab_no={$qLab}" class="btn btn-secondary py-1.5 px-3 font-semibold text-teal-700 hover:bg-teal-50" title="Edit Radiology & Imaging Narrative">
                <i class="fa-solid fa-wave-square text-teal-600 mr-1"></i> Imaging Findings
            </a>
        </div>
    </div>
HTML;
}

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

$portal = current_portal();
$activeKey = ($portal === 'imaging') ? 'report-history' : 'reports-generate';
render_page('Report Preview', $portal, $activeKey, $content, true);
