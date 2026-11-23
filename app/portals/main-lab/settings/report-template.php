<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

require_permission('header_footer_edit');

$message = '';
$isSuper = is_admin_user();
$userOrgId = current_user()['organization_id'] ?? 'ORG-001';

$requestedOrgId = trim((string)($_GET['org'] ?? $_POST['org_id'] ?? ''));
if ($isSuper && $requestedOrgId !== '') {
    $orgId = $requestedOrgId;
} else {
    $orgId = $userOrgId;
}

$allOrgs = $isSuper ? org_repo()->getAll() : [];
$currentOrgName = 'Diagnostic Organization';
$orgOptions = '';
if ($isSuper) {
    $found = false;
    foreach ($allOrgs as $o) {
        $oid = (string)$o['id'];
        $oname = (string)$o['name'];
        if ($oid === $orgId) {
            $currentOrgName = $oname;
            $found = true;
        }
        $sel = ($oid === $orgId) ? ' selected' : '';
        $orgOptions .= '<option value="' . e($oid) . '"' . $sel . '>' . e($oname) . ' (' . e($oid) . ')</option>';
    }
    if (!$found && !empty($allOrgs[0])) {
        $orgId = (string)$allOrgs[0]['id'];
        $currentOrgName = (string)$allOrgs[0]['name'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Save branding & letterhead settings
    $result = save_branding_request($orgId);

    // 2. Save Laboratory Signatories
    if (isset($_POST['sig_name']) && is_array($_POST['sig_name'])) {
        $labSigs = [];
        for ($i = 0; $i < 5; $i++) {
            $labSigs[] = [
                'name' => trim((string)($_POST['sig_name'][$i] ?? '')),
                'qualifications' => trim((string)($_POST['sig_quals'][$i] ?? '')),
                'designation' => trim((string)($_POST['sig_desig'][$i] ?? '')),
            ];
        }
        setting_repo()->saveSignatories($orgId, $labSigs, 'laboratory');
    }

    // 3. Save Diagnostic Imaging Signatories
    if (isset($_POST['imaging_sig_name']) && is_array($_POST['imaging_sig_name'])) {
        $imgSigs = [];
        for ($i = 0; $i < 5; $i++) {
            $imgSigs[] = [
                'name' => trim((string)($_POST['imaging_sig_name'][$i] ?? '')),
                'qualifications' => trim((string)($_POST['imaging_sig_quals'][$i] ?? '')),
                'designation' => trim((string)($_POST['imaging_sig_desig'][$i] ?? '')),
            ];
        }
        setting_repo()->saveSignatories($orgId, $imgSigs, 'imaging');
    }

    $message = $result['ok']
        ? flash_success('Letterhead branding and doctor signatories saved successfully for ' . $currentOrgName . ' (' . $orgId . ').')
        : flash_error($result['message']);
}

$s = branding_settings($orgId);
$labSignatories = setting_repo()->getSignatories($orgId, 'laboratory');
$imagingSignatories = setting_repo()->getSignatories($orgId, 'imaging');

$headerBuilderHtml = branding_header_image_field($s);
$footerBuilderHtml = branding_footer_image_field($s);

$sName = e($s['name']);
$sPhone = e($s['phone']);
$sEmail = e($s['email']);
$sAddress = e($s['address']);
$sLogo = e($s['logo_text']);
$sHeader = e($s['header']);
$sBillHeader = e($s['bill_header']);
$sFooter = e($s['footer']);
$sBillFooter = e($s['bill_footer']);
$sImagingFooter = e($s['imaging_footer'] ?? '');
$sImagingDisclaimer = e($s['imaging_disclaimer'] ?? '');

$supportedFonts = supported_document_fonts();
$currentReportFont = $s['report_font'] ?? 'times_bold_italic';
$currentBillFont = $s['bill_font'] ?? 'times_bold_italic';

$supportedFontSizes = supported_document_font_sizes();
$currentReportFontSize = $s['report_font_size'] ?? 'normal';
$currentBillFontSize = $s['bill_font_size'] ?? 'normal';

$reportFontOpts = '';
foreach ($supportedFonts as $key => $f) {
    $sel = $key === $currentReportFont ? ' selected' : '';
    $style = 'font-family: ' . e($f['family']) . '; font-weight: ' . e($f['weight']) . '; font-style: ' . e($f['style']) . ';';
    $reportFontOpts .= '<option value="' . e($key) . '" style="' . $style . '"' . $sel . '>' . e($f['name']) . '</option>';
}

$billFontOpts = '';
foreach ($supportedFonts as $key => $f) {
    $sel = $key === $currentBillFont ? ' selected' : '';
    $style = 'font-family: ' . e($f['family']) . '; font-weight: ' . e($f['weight']) . '; font-style: ' . e($f['style']) . ';';
    $billFontOpts .= '<option value="' . e($key) . '" style="' . $style . '"' . $sel . '>' . e($f['name']) . '</option>';
}

$reportFontSizeOpts = '';
foreach ($supportedFontSizes as $key => $sz) {
    $sel = $key === $currentReportFontSize ? ' selected' : '';
    $reportFontSizeOpts .= '<option value="' . e($key) . '"' . $sel . '>' . e($sz['name']) . '</option>';
}

$billFontSizeOpts = '';
foreach ($supportedFontSizes as $key => $sz) {
    $sel = $key === $currentBillFontSize ? ' selected' : '';
    $billFontSizeOpts .= '<option value="' . e($key) . '"' . $sel . '>' . e($sz['name']) . '</option>';
}

$showSingleHeadersChecked = !empty($s['show_single_test_headers']) ? ' checked' : '';
$hidePendingTestsChecked = (!isset($s['hide_pending_tests']) || !empty($s['hide_pending_tests'])) ? ' checked' : '';
$sReportHeadingTitle = e((string)($s['report_heading_title'] ?? 'DIAGNOSTIC REPORT'));
$hideReportTitleBarChecked = !empty($s['hide_report_title_bar']) ? ' checked' : '';

$currentReportParamSize = $s['report_param_size'] ?? '15px';
$currentReportParamSizeNum = (float)str_replace('px', '', $currentReportParamSize);
if ($currentReportParamSizeNum < 11 || $currentReportParamSizeNum > 24) {
    $currentReportParamSizeNum = 15.0;
}
$currentReportParamWeight = $s['report_param_weight'] ?? '700';

$weightOptions = [
    '300' => 'Thin / Light (300)',
    '400' => 'Normal / Regular (400)',
    '500' => 'Medium (500)',
    '600' => 'Semi-Bold (600)',
    '700' => 'Bold (700) — Standard',
    '800' => 'Extra Bold (800)',
    '900' => 'Black / Heavy (900)',
    '700_italic' => 'Bold Italic (700 Italic)',
    'italic' => 'Normal Italic (400 Italic)',
];

$reportParamWeightOpts = '';
foreach ($weightOptions as $wKey => $wLabel) {
    $wKeyStr = (string)$wKey;
    $sel = $wKeyStr === (string)$currentReportParamWeight ? ' selected' : '';
    $reportParamWeightOpts .= '<option value="' . e($wKeyStr) . '"' . $sel . '>' . e((string)$wLabel) . '</option>';
}

$fontMapJson = json_encode($supportedFonts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$fontSizeMapJson = json_encode($supportedFontSizes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// Lab signatories fields
$labSigFormFields = '';
for ($i = 0; $i < 5; $i++) {
    $sig = $labSignatories[$i] ?? ['name' => '', 'qualifications' => '', 'designation' => ''];
    $slotNum = $i + 1;
    $sDocName = e($sig['name']);
    $sQuals = e($sig['qualifications']);
    $sDesig = e($sig['designation']);

    $labSigFormFields .= <<<HTML
    <div class="p-3.5 border border-slate-200 rounded-xl bg-slate-50/50 space-y-2.5">
        <div class="flex items-center justify-between pb-1.5 border-b border-slate-200/70">
            <span class="text-xs font-bold text-slate-700">Doctor Slot {$slotNum}</span>
            <span class="text-[10px] font-semibold text-slate-400">Pathology</span>
        </div>
        <div>
            <label class="field-label text-[11px] mb-1">Doctor Name</label>
            <input type="text" name="sig_name[]" class="field text-xs font-semibold bg-white" value="{$sDocName}" placeholder="e.g. Dr. A. K. Khan">
        </div>
        <div>
            <label class="field-label text-[11px] mb-1">Qualifications</label>
            <input type="text" name="sig_quals[]" class="field text-xs bg-white" value="{$sQuals}" placeholder="e.g. M.B.B.S, F.C.P.S (Pathology)">
        </div>
        <div>
            <label class="field-label text-[11px] mb-1">Designation</label>
            <input type="text" name="sig_desig[]" class="field text-xs bg-white" value="{$sDesig}" placeholder="e.g. Consultant Pathologist">
        </div>
    </div>
    HTML;
}

// Imaging signatories fields
$imagingSigFormFields = '';
for ($i = 0; $i < 5; $i++) {
    $sig = $imagingSignatories[$i] ?? ['name' => '', 'qualifications' => '', 'designation' => ''];
    $slotNum = $i + 1;
    $sDocName = e($sig['name']);
    $sQuals = e($sig['qualifications']);
    $sDesig = e($sig['designation']);

    $imagingSigFormFields .= <<<HTML
    <div class="p-3.5 border border-slate-200 rounded-xl bg-slate-50/50 space-y-2.5">
        <div class="flex items-center justify-between pb-1.5 border-b border-slate-200/70">
            <span class="text-xs font-bold text-slate-700">Specialist Slot {$slotNum}</span>
            <span class="text-[10px] font-semibold text-slate-400">Imaging / Scan</span>
        </div>
        <div>
            <label class="field-label text-[11px] mb-1">Doctor / Specialist Name</label>
            <input type="text" name="imaging_sig_name[]" class="field text-xs font-semibold bg-white" value="{$sDocName}" placeholder="e.g. Dr. Imran Sheikh">
        </div>
        <div>
            <label class="field-label text-[11px] mb-1">Qualifications</label>
            <input type="text" name="imaging_sig_quals[]" class="field text-xs bg-white" value="{$sQuals}" placeholder="e.g. M.B.B.S, F.C.P.S (Radiology)">
        </div>
        <div>
            <label class="field-label text-[11px] mb-1">Designation</label>
            <input type="text" name="imaging_sig_desig[]" class="field text-xs bg-white" value="{$sDesig}" placeholder="e.g. Consultant Radiologist">
        </div>
    </div>
    HTML;
}

$orgSwitcherHtml = '';
if ($isSuper && count($allOrgs) > 1) {
    $orgSwitcherHtml = <<<HTML
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-4 rounded-3xl shadow-md flex flex-wrap items-center justify-between gap-3 border border-indigo-800/40">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-indigo-500/20 text-indigo-300 flex items-center justify-center font-bold text-lg border border-indigo-400/30">
                <i class="fa-solid fa-building-columns"></i>
            </div>
            <div>
                <div class="text-[10px] uppercase tracking-wider text-indigo-300 font-extrabold">Diagnostic Multi-Tenant Letterhead Studio</div>
                <div class="text-sm font-bold text-white flex items-center gap-2">
                    Configuring Company: <span class="text-emerald-400">{$currentOrgName}</span> <code class="text-xs bg-slate-800 px-1.5 py-0.5 rounded text-indigo-200">{$orgId}</code>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <label for="super_org_switcher" class="text-xs font-semibold text-slate-300">Switch Company / Lab:</label>
            <select id="super_org_switcher" onchange="if(this.value) location.href='/portals/branding.php?org='+encodeURIComponent(this.value)" class="text-xs font-bold text-slate-900 bg-white rounded-xl py-2 px-3 border-0 shadow-sm cursor-pointer focus:ring-2 focus:ring-indigo-400">
                {$orgOptions}
            </select>
        </div>
    </div>
HTML;
}

$reportPreviewUrl = '/portals/reports/preview.php' . ($isSuper ? '?org=' . urlencode($orgId) : '');
$receiptPreviewUrl = '/portals/receipts.php' . ($isSuper ? '?org=' . urlencode($orgId) : '');

$content = <<<HTML
<div class="space-y-5">
    {$message}
    {$orgSwitcherHtml}

    <!-- Top Action / Navigation Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-200">
        <div>
            <h1 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">Header &amp; Footer Settings</h1>
            <p class="text-xs text-slate-500 font-medium">Letterhead branding, report footers, doctor signatories, and typography for <strong>{$currentOrgName}</strong></p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{$reportPreviewUrl}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold text-xs transition-colors shadow-2xs">
                <i class="fa-solid fa-file-pdf text-slate-500 text-xs"></i>
                <span>Preview Report</span>
            </a>
            <a href="{$receiptPreviewUrl}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold text-xs transition-colors shadow-2xs">
                <i class="fa-solid fa-receipt text-slate-500 text-xs"></i>
                <span>Preview Receipt</span>
            </a>
            <button type="submit" form="master-branding-form" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition-colors">
                <i class="fa-solid fa-floppy-disk text-xs"></i>
                <span>Save All Changes</span>
            </button>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" id="master-branding-form" class="space-y-6">
        <input type="hidden" name="action" value="save_branding">
        <input type="hidden" name="org_id" value="{$orgId}">

        <!-- Modern Tabs -->
        <div class="border-b border-slate-200 flex items-center gap-2 text-xs font-bold overflow-x-auto">
            <button type="button" onclick="switchSettingsTab('header')" id="tab-btn-header" class="px-4 py-2.5 border-b-2 border-slate-900 text-slate-900 transition-colors whitespace-nowrap">
                <i class="fa-solid fa-heading mr-1.5 text-slate-600"></i> Header &amp; Logo
            </button>
            <button type="button" onclick="switchSettingsTab('footer')" id="tab-btn-footer" class="px-4 py-2.5 border-b-2 border-transparent text-slate-500 hover:text-slate-800 transition-colors whitespace-nowrap">
                <i class="fa-solid fa-shoe-prints mr-1.5 text-slate-400"></i> Footer &amp; Disclaimers
            </button>
            <button type="button" onclick="switchSettingsTab('signatories')" id="tab-btn-signatories" class="px-4 py-2.5 border-b-2 border-transparent text-slate-500 hover:text-slate-800 transition-colors whitespace-nowrap">
                <i class="fa-solid fa-signature mr-1.5 text-slate-400"></i> Doctor Signatories
            </button>
            <button type="button" onclick="switchSettingsTab('organization')" id="tab-btn-organization" class="px-4 py-2.5 border-b-2 border-transparent text-slate-500 hover:text-slate-800 transition-colors whitespace-nowrap">
                <i class="fa-solid fa-hospital mr-1.5 text-slate-400"></i> Contact &amp; Typography
            </button>
        </div>

        <!-- TAB 1: HEADER & LOGO -->
        <div id="tab-panel-header" class="space-y-5">
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label text-xs">Report Header Subtitle / Tagline</label>
                        <input type="text" name="header" class="field text-sm" value="{$sHeader}" placeholder="e.g. Quality Diagnostics &bull; Pathology &bull; Ultrasound">
                    </div>
                    <div>
                        <label class="field-label text-xs">Patient Bill Header Note</label>
                        <input type="text" name="bill_header" class="field text-sm" value="{$sBillHeader}" placeholder="e.g. Official Receipt">
                    </div>
                </div>

                <div class="pt-2">
                    {$headerBuilderHtml}
                </div>
            </div>
        </div>

        <!-- TAB 2: FOOTER & DISCLAIMERS -->
        <div id="tab-panel-footer" class="space-y-5 hidden">
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label text-xs">Laboratory Report Footer</label>
                        <input type="text" name="footer" class="field text-sm" value="{$sFooter}" placeholder="e.g. Pathology findings should be correlated clinically.">
                    </div>
                    <div>
                        <label class="field-label text-xs">Diagnostic Imaging Footer</label>
                        <input type="text" name="imaging_footer" class="field text-sm" value="{$sImagingFooter}" placeholder="e.g. Ultrasound &amp; X-Ray reports require clinical correlation.">
                    </div>
                    <div>
                        <label class="field-label text-xs">Patient Bill Footer</label>
                        <input type="text" name="bill_footer" class="field text-sm" value="{$sBillFooter}" placeholder="e.g. Thank you for choosing our center.">
                    </div>
                    <div>
                        <label class="field-label text-xs">Imaging Clinical Disclaimer (Optional)</label>
                        <input type="text" name="imaging_disclaimer" class="field text-sm" value="{$sImagingDisclaimer}" placeholder="Leave blank for automatic default">
                    </div>
                </div>

                <div class="pt-2">
                    {$footerBuilderHtml}
                </div>
            </div>
        </div>

        <!-- TAB 3: DOCTOR SIGNATORIES -->
        <div id="tab-panel-signatories" class="space-y-5 hidden">
            <!-- Sub-switcher between Lab and Imaging Signatories -->
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">Laboratory Medicine Signatories</h2>
                        <p class="text-xs text-slate-500">Printed at the bottom of Pathology &amp; Laboratory Medicine reports</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
                    {$labSigFormFields}
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">Diagnostic Imaging Signatories</h2>
                        <p class="text-xs text-slate-500">Printed at the bottom of Ultrasound, X-Ray, ECG, Echo, CT &amp; MRI reports</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
                    {$imagingSigFormFields}
                </div>
            </div>
        </div>

        <!-- TAB 4: CONTACT & TYPOGRAPHY -->
        <div id="tab-panel-organization" class="space-y-5 hidden">
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="field-label text-xs">Center / Hospital Name</label>
                        <input type="text" name="name" class="field text-sm font-semibold" value="{$sName}" required>
                    </div>
                    <div>
                        <label class="field-label text-xs">Phone Number</label>
                        <input type="text" name="phone" class="field text-sm" value="{$sPhone}">
                    </div>
                    <div>
                        <label class="field-label text-xs">Email Address</label>
                        <input type="email" name="email" class="field text-sm" value="{$sEmail}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label text-xs">Physical Address</label>
                        <input type="text" name="address" class="field text-sm" value="{$sAddress}">
                    </div>
                    <div>
                        <label class="field-label text-xs">Monogram / Abbreviation</label>
                        <input type="text" name="logo" class="field text-sm font-mono" value="{$sLogo}">
                    </div>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-4">
                <h2 class="text-sm font-bold text-slate-800 pb-2 border-b border-slate-100">Document Typography &amp; Font Sizes</h2>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="p-3.5 bg-slate-50/60 rounded-xl border border-slate-200 space-y-2.5">
                        <label class="field-label text-xs font-bold text-slate-800 mb-0">Lab Report Body Font</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Font Family</label>
                                <select name="report_font" id="report_font_select" class="field text-xs font-semibold" onchange="updateFontPreviews()">
                                    {$reportFontOpts}
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Font Size</label>
                                <select name="report_font_size" id="report_font_size_select" class="field text-xs font-semibold" onchange="updateFontPreviews()">
                                    {$reportFontSizeOpts}
                                </select>
                            </div>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200 text-xs">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">Sample</span>
                            <div id="report-font-preview-text" class="text-slate-900 p-0.5">CBC / Hemoglobin: 14.2 g/dL &nbsp;|&nbsp; Ref: 12.0 - 16.0</div>
                        </div>
                    </div>

                    <div class="p-3.5 bg-slate-50/60 rounded-xl border border-slate-200 space-y-2.5">
                        <label class="field-label text-xs font-bold text-slate-800 mb-0">Patient Bill / Receipt Font</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Font Family</label>
                                <select name="bill_font" id="bill_font_select" class="field text-xs font-semibold" onchange="updateFontPreviews()">
                                    {$billFontOpts}
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Font Size</label>
                                <select name="bill_font_size" id="bill_font_size_select" class="field text-xs font-semibold" onchange="updateFontPreviews()">
                                    {$billFontSizeOpts}
                                </select>
                            </div>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200 text-xs">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">Sample</span>
                            <div id="bill-font-preview-text" class="text-slate-900 p-0.5">Complete Blood Count: Rs 900 &nbsp;|&nbsp; PAID</div>
                        </div>
                    </div>

                    <!-- Main Body Test Parameters & Results Typography (Dedicated Slider & Weight) -->
                    <div class="p-4 bg-indigo-50/70 rounded-2xl border-2 border-indigo-200 space-y-3.5 sm:col-span-2 shadow-2xs">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <label class="field-label text-xs font-bold text-indigo-950 mb-0 flex items-center gap-1.5">
                                    <i class="fa-solid fa-sliders text-indigo-600"></i> Main Body Test Parameters &amp; Results Font Size &amp; Weight
                                </label>
                                <p class="text-[11px] text-indigo-800/80 mt-0.5">Use the slider and weight dropdown below to adjust the main test parameters and results text. <strong>Only affects the main test body table rows</strong> (does not alter letterhead, patient demographics, or footers).</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] uppercase font-bold text-indigo-900 tracking-wider">Active Body Size:</span>
                                <span id="param_size_badge" class="font-mono font-black text-xs bg-indigo-600 text-white px-2.5 py-1 rounded-lg shadow-xs">{$currentReportParamSize}</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                            <!-- Range Slider -->
                            <div class="bg-white p-3.5 rounded-xl border border-indigo-200/90 shadow-2xs space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-slate-700">Body Font Size Slider</span>
                                    <span class="text-[10px] text-slate-400 font-mono">11px &longleftrightarrow; 22px</span>
                                </div>
                                <input 
                                    type="range" 
                                    name="report_param_size" 
                                    id="report_param_size_slider" 
                                    min="11" 
                                    max="22" 
                                    step="0.5" 
                                    value="{$currentReportParamSizeNum}" 
                                    class="w-full accent-indigo-600 cursor-pointer h-2 bg-slate-200 rounded-lg"
                                    oninput="updateParamSizeSlider(this.value)"
                                >
                                <div class="flex justify-between text-[10px] text-slate-400 font-medium px-0.5">
                                    <span>11px (Fine)</span>
                                    <span>15px (Standard)</span>
                                    <span>18px (Large)</span>
                                    <span>22px (Max)</span>
                                </div>
                            </div>

                            <!-- Weight Dropdown -->
                            <div class="bg-white p-3.5 rounded-xl border border-indigo-200/90 shadow-2xs space-y-2">
                                <label class="text-xs font-bold text-slate-700 block">Font Weight &amp; Style (Thin, Bold, Italic)</label>
                                <select name="report_param_weight" id="report_param_weight_select" class="field text-xs font-semibold w-full" onchange="updateParamFontPreview()">
                                    {$reportParamWeightOpts}
                                </select>
                                <span class="text-[10px] text-slate-400 block">Bold (700) or Extra Bold (800) is recommended for clear reading by doctors.</span>
                            </div>
                        </div>

                        <!-- Live Interactive Table Sample -->
                        <div class="bg-white p-3 rounded-xl border border-indigo-200/90 shadow-2xs space-y-1">
                            <span class="text-[10px] uppercase font-bold text-indigo-700 tracking-wider block">Live Test Table Preview</span>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="border-b border-slate-200 text-[10px] text-slate-400 font-bold uppercase tracking-wider">
                                            <th class="py-1 px-2">Test / Parameter</th>
                                            <th class="py-1 px-2 text-right">Reference Range</th>
                                            <th class="py-1 px-2 text-right">Unit</th>
                                            <th class="py-1 px-2 text-right">Result</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="border-b border-slate-100">
                                            <td id="sample_param_name" class="py-1.5 px-2 text-slate-900 font-bold" style="font-size: {$currentReportParamSize};">Hemoglobin (HB)</td>
                                            <td id="sample_param_range" class="py-1.5 px-2 text-right text-slate-900" style="font-size: calc({$currentReportParamSize} - 1px);">12.0 - 16.5</td>
                                            <td id="sample_param_unit" class="py-1.5 px-2 text-right text-slate-900" style="font-size: calc({$currentReportParamSize} - 1px);">g/dl</td>
                                            <td id="sample_param_result" class="py-1.5 px-2 text-right text-slate-900 font-bold" style="font-size: {$currentReportParamSize};">14.2</td>
                                        </tr>
                                        <tr>
                                            <td id="sample_param_name2" class="py-1.5 px-2 text-slate-900 font-bold" style="font-size: {$currentReportParamSize};">ANTI HIV SCREENING</td>
                                            <td id="sample_param_range2" class="py-1.5 px-2 text-right text-slate-900" style="font-size: calc({$currentReportParamSize} - 1px);">Negative</td>
                                            <td id="sample_param_unit2" class="py-1.5 px-2 text-right text-slate-900" style="font-size: calc({$currentReportParamSize} - 1px);">—</td>
                                            <td id="sample_param_result2" class="py-1.5 px-2 text-right text-slate-900 font-bold" style="font-size: {$currentReportParamSize};">Negative</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-slate-50/80 rounded-xl border border-slate-200 mt-4">
                    <label class="inline-flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" name="show_single_test_headers" value="1" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900 w-4 h-4"{$showSingleHeadersChecked}>
                        <div>
                            <span class="text-xs font-bold text-slate-800">Section Banner Headings Above Single Tests</span>
                            <p class="text-[11px] text-slate-500 mt-0.5">When unchecked (default &amp; recommended), single tests like Anti-HCV, Hepatitis B (HBsAg), Anti-HIV, and Blood Sugar print cleanly without redundant section banners. Check this only if you want individual banners above every test.</p>
                        </div>
                    </label>
                </div>

                <div class="p-4 bg-emerald-50/60 rounded-xl border border-emerald-200 mt-3">
                    <label class="inline-flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" name="hide_pending_tests" value="1" class="rounded border-emerald-400 text-emerald-600 focus:ring-emerald-600 w-4 h-4"{$hidePendingTestsChecked}>
                        <div>
                            <span class="text-xs font-bold text-slate-900 flex items-center gap-2">
                                <span>Only Print Tests with Entered Results (Hide "Pending" Tests)</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Recommended</span>
                            </span>
                            <p class="text-[11px] text-slate-600 mt-0.5">When checked (default &amp; recommended), tests whose results have not been entered yet will not appear on the patient report. This allows handing over clean partial reports without displaying "Pending" for unentered tests. When unchecked, all booked tests are printed with "Pending" status.</p>
                        </div>
                    </label>
                </div>

                <div class="p-4 bg-slate-50/80 rounded-xl border border-slate-200 mt-4 space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-800 mb-1">
                            Report Sub-Heading Title (Bar Below Patient Info)
                        </label>
                        <p class="text-[11px] text-slate-500 mb-2">
                            The title bar printed directly below the patient metadata block (e.g. <code>DIAGNOSTIC REPORT</code>). You can customize this to your preferred title or your lab's name, or click the quick presets below. (Previously showed "DEPARTMENT OF LABORATORY MEDICINE").
                        </p>
                        <div class="flex items-center gap-2">
                            <input type="text" name="report_heading_title" id="report_heading_title_input" value="{$sReportHeadingTitle}" class="w-full max-w-md px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-slate-900 focus:border-slate-900 font-semibold" placeholder="e.g. DIAGNOSTIC REPORT or LABORATORY REPORT">
                        </div>
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            <span class="text-[11px] font-semibold text-slate-400 py-0.5">Quick Presets:</span>
                            <button type="button" class="px-2 py-0.5 text-[11px] font-bold rounded-md bg-white border border-slate-300 hover:bg-slate-100 text-slate-700" onclick="document.getElementById('report_heading_title_input').value='DIAGNOSTIC REPORT'">DIAGNOSTIC REPORT (Standard)</button>
                            <button type="button" class="px-2 py-0.5 text-[11px] font-bold rounded-md bg-white border border-slate-300 hover:bg-slate-100 text-slate-700" onclick="document.getElementById('report_heading_title_input').value='LABORATORY REPORT'">LABORATORY REPORT</button>
                            <button type="button" class="px-2 py-0.5 text-[11px] font-bold rounded-md bg-white border border-slate-300 hover:bg-slate-100 text-slate-700" onclick="document.getElementById('report_heading_title_input').value='PATHOLOGY REPORT'">PATHOLOGY REPORT</button>
                            <button type="button" class="px-2 py-0.5 text-[11px] font-bold rounded-md bg-white border border-slate-300 hover:bg-slate-100 text-slate-700" onclick="document.getElementById('report_heading_title_input').value='auto'">Auto (Per Department)</button>
                        </div>
                    </div>
                    <div class="pt-2.5 border-t border-slate-200">
                        <label class="inline-flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" name="hide_report_title_bar" value="1" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900 w-4 h-4"{$hideReportTitleBarChecked}>
                            <span class="text-xs font-semibold text-slate-700">Hide title bar completely (do not print any sub-heading bar below patient info)</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky Save Action Bar -->
        <div class="sticky bottom-4 z-20 flex items-center justify-between p-3.5 bg-white/95 backdrop-blur-md border border-slate-300 rounded-2xl shadow-lg">
            <span class="text-xs text-slate-500">All changes across tabs will be saved at once.</span>
            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-primary text-xs py-2 px-5 font-bold shadow-sm">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i> Save All Changes
                </button>
            </div>
        </div>
    </form>
</div>

<script>
(function() {
    window.switchSettingsTab = function(tabName) {
        ['header', 'footer', 'signatories', 'organization'].forEach(function(t) {
            const panel = document.getElementById('tab-panel-' + t);
            const btn = document.getElementById('tab-btn-' + t);
            if (panel && btn) {
                if (t === tabName) {
                    panel.classList.remove('hidden');
                    btn.className = 'px-4 py-2.5 border-b-2 border-slate-900 text-slate-900 transition-colors whitespace-nowrap';
                } else {
                    panel.classList.add('hidden');
                    btn.className = 'px-4 py-2.5 border-b-2 border-transparent text-slate-500 hover:text-slate-800 transition-colors whitespace-nowrap';
                }
            }
        });
    };

    const fontMap = {$fontMapJson};
    const fontSizeMap = {$fontSizeMapJson};

    window.updateFontPreviews = function() {
        const rSelect = document.getElementById('report_font_select');
        const rSizeSelect = document.getElementById('report_font_size_select');
        const rPreview = document.getElementById('report-font-preview-text');
        if (rSelect && rPreview && fontMap[rSelect.value]) {
            const f = fontMap[rSelect.value];
            rPreview.style.fontFamily = f.family;
            rPreview.style.fontWeight = f.weight;
            rPreview.style.fontStyle = f.style;
        }
        if (rSizeSelect && rPreview && fontSizeMap[rSizeSelect.value]) {
            const sz = fontSizeMap[rSizeSelect.value];
            rPreview.style.fontSize = sz.size;
            rPreview.style.lineHeight = sz.line_height;
        }

        const bSelect = document.getElementById('bill_font_select');
        const bSizeSelect = document.getElementById('bill_font_size_select');
        const bPreview = document.getElementById('bill-font-preview-text');
        if (bSelect && bPreview && fontMap[bSelect.value]) {
            const f = fontMap[bSelect.value];
            bPreview.style.fontFamily = f.family;
            bPreview.style.fontWeight = f.weight;
            bPreview.style.fontStyle = f.style;
        }
        if (bSizeSelect && bPreview && fontSizeMap[bSizeSelect.value]) {
            const sz = fontSizeMap[bSizeSelect.value];
            bPreview.style.fontSize = sz.size;
            bPreview.style.lineHeight = sz.line_height;
        }
    };

    window.updateParamSizeSlider = function(val) {
        const sizeStr = val + 'px';
        const badge = document.getElementById('param_size_badge');
        if (badge) badge.textContent = sizeStr;
        window.updateParamFontPreview();
    };

    window.updateParamFontPreview = function() {
        const slider = document.getElementById('report_param_size_slider');
        const weightSel = document.getElementById('report_param_weight_select');
        if (!slider || !weightSel) return;

        const size = slider.value + 'px';
        const subSize = (parseFloat(slider.value) - 1) + 'px';
        const weightVal = weightSel.value;
        
        let weight = '700';
        let style = 'normal';
        if (weightVal.endsWith('_italic') || weightVal === 'italic') {
            style = 'italic';
            weight = weightVal === 'italic' ? '400' : weightVal.replace('_italic', '');
        } else {
            weight = weightVal;
        }

        const els = ['sample_param_name', 'sample_param_result', 'sample_param_name2', 'sample_param_result2'];
        els.forEach(function(id) {
            const el = document.getElementById(id);
            if (el) {
                el.style.fontSize = size;
                el.style.fontWeight = weight;
                el.style.fontStyle = style;
            }
        });

        const subEls = ['sample_param_range', 'sample_param_unit', 'sample_param_range2', 'sample_param_unit2'];
        subEls.forEach(function(id) {
            const el = document.getElementById(id);
            if (el) {
                el.style.fontSize = subSize;
                el.style.fontWeight = weight;
                el.style.fontStyle = style;
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            updateFontPreviews();
            updateParamFontPreview();
        });
    } else {
        updateFontPreviews();
        updateParamFontPreview();
    }
})();
</script>
HTML;

$portal = current_portal();
$activeKey = ($portal === 'imaging') ? 'settings-report' : 'settings-report';
render_page('Header & Footer Settings', $portal, $activeKey, $content);
