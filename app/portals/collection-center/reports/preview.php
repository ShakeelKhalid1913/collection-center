<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

require_permission('view_results');

$labNo = trim($_GET['lab_no'] ?? '');
$requestedOrg = trim((string)($_GET['org'] ?? ''));
$ctx = load_document_context($labNo !== '' ? $labNo : null, $requestedOrg !== '' ? $requestedOrg : null);

$format = trim((string)($_GET['format'] ?? $_COOKIE['preferred_report_format'] ?? 'graph'));
if (isset($_GET['format'])) {
    @setcookie('preferred_report_format', $format, time() + 86400 * 30, '/');
}
$useGraph = ($format !== 'simple');

$previewUrl = '/portals/reports/preview.php' . ($labNo !== '' ? '?lab_no=' . urlencode($labNo) . '&format=' . urlencode($format) : '');

$entryOrgId = (string)($ctx['entry']['organization_id'] ?? current_organization_id());
$allEntriesForSwitch = lab_repo()->getEntriesWithPatients($entryOrgId, 250);
$reportSelectOpts = '';
foreach ($allEntriesForSwitch as $ae) {
    $aeLab = (string)($ae['lab_no'] ?? '');
    $sel = ($aeLab === $labNo) ? ' selected' : '';
    $pName = (string)($ae['patient_name'] ?? 'Walk-in');
    $pTests = normalize_tests_list((string)($ae['tests'] ?? ''));
    if (strlen($pTests) > 30) {
        $pTests = substr($pTests, 0, 28) . '…';
    }
    $pDate = !empty($ae['created_at']) ? date('d M', strtotime((string)$ae['created_at'])) : '';
    $reportSelectOpts .= '<option value="' . e($aeLab) . '"' . $sel . '>'
        . e($aeLab) . ' — ' . e($pName) . ' (' . e($pTests) . ') [' . $pDate . ']</option>';
}

$content = '<div class="no-print">' . page_header(
    'Report Preview',
    'Collection Center print/PDF preview — uses lab branding header, address & footer. Optional CNIC / blood group / email only if entered.'
) . '</div>';

$content .= <<<HTML
<!-- Report Switcher & Quick Slip Search Toolbar -->
<div class="no-print mb-4 p-3.5 bg-white rounded-2xl border border-slate-200/90 shadow-2xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 flex-1 min-w-0">
        <label class="text-xs font-bold text-slate-700 whitespace-nowrap flex items-center gap-1.5">
            <i class="fa-solid fa-file-waveform text-teal-600"></i> Select Report:
        </label>
        <select class="field text-xs font-bold py-2 px-3 bg-slate-50 hover:bg-slate-100 text-slate-800 border border-slate-300 rounded-xl cursor-pointer w-full sm:max-w-md shadow-2xs focus:ring-2 focus:ring-teal-500 focus:bg-white" onchange="if(this.value) location.href='/portals/reports/preview.php?lab_no='+encodeURIComponent(this.value)">
            <option value="">— Choose / Switch Patient Report —</option>
            {$reportSelectOpts}
        </select>
    </div>

    <form method="get" action="/portals/reports/preview.php" class="flex items-center gap-2 shrink-0">
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <input type="text" name="lab_no" placeholder="Slip # (e.g. 3235)..." class="field text-xs font-mono font-bold pl-8 pr-3 py-2 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white w-48 sm:w-56 shadow-2xs">
        </div>
        <button type="submit" class="btn btn-primary text-xs font-bold py-2 px-3.5 rounded-xl shadow-2xs whitespace-nowrap">
            Open Report
        </button>
    </form>
</div>
HTML;
$content .= report_actions($ctx['patient']['phone'] ?? '', $previewUrl, 'report-' . ($labNo !== '' ? $labNo : 'preview'), $format);
$content .= '<div class="mt-6 no-print flex flex-wrap gap-2">' .
    btn_secondary('/portals/receipts.php?lab_no=' . urlencode((string)($ctx['entry']['lab_no'] ?? '')), 'Receipt') .
    btn_secondary('/portals/branding.php', 'Edit header / footer') .
    '</div>';
$content .= render_report_document(
    $ctx['settings'],
    $ctx['patient'],
    $ctx['lines'],
    $ctx['entry'],
    $ctx['report_title'] ?? 'DIAGNOSTIC REPORT',
    $ctx['signatories'] ?? [],
    $ctx['specimen'] ?? 'Serum',
    ['graph_mode' => $useGraph]
);

render_page('Report Preview', 'collection-center', 'reports', $content, true);
