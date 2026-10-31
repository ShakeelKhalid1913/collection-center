<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_permission('header_footer_edit');
    if (isset($_POST['action']) && in_array($_POST['action'], ['save_signatories', 'save_imaging_signatories'], true)) {
        $dept = ($_POST['action'] === 'save_imaging_signatories' || ($_POST['sig_department'] ?? '') === 'imaging') ? 'imaging' : 'laboratory';
        $sigs = [];
        $prefix = $dept === 'imaging' ? 'imaging_sig_' : 'sig_';
        for ($i = 0; $i < 5; $i++) {
            $name = $_POST[$prefix . 'name'][$i] ?? ($_POST['sig_name'][$i] ?? '');
            $quals = $_POST[$prefix . 'quals'][$i] ?? ($_POST['sig_quals'][$i] ?? '');
            $desig = $_POST[$prefix . 'desig'][$i] ?? ($_POST['sig_desig'][$i] ?? '');
            $sigs[] = [
                'name' => $name,
                'qualifications' => $quals,
                'designation' => $desig,
            ];
        }
        $ok = setting_repo()->saveSignatories($orgId, $sigs, $dept);
        $label = $dept === 'imaging' ? 'Diagnostic Center / Imaging' : 'Laboratory';
        $message = $ok ? flash_success("{$label} doctor signatories saved successfully.") : flash_error('Could not save signatories.');
    } else {
        $result = save_branding_request($orgId);
        $message = $result['ok'] ? flash_success($result['message']) : flash_error($result['message']);
    }
}

$s = branding_settings($orgId);
$labSignatories = setting_repo()->getSignatories($orgId, 'laboratory');
$imagingSignatories = setting_repo()->getSignatories($orgId, 'imaging');

$content = page_header(
    'Report & Bill Letterhead',
    'Design and customize your laboratory header, footer, contact branding, and doctor signatories.',
    '<a href="/portals/reports/preview.php" class="btn btn-secondary text-sm"><i class="fa-solid fa-eye mr-1.5"></i> Preview Sample Report</a>'
);
$content .= $message;

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

$fontMapJson = json_encode($supportedFonts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$fontSizeMapJson = json_encode($supportedFontSizes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$content .= <<<HTML
<form method="post" enctype="multipart/form-data" class="space-y-6">
    <input type="hidden" name="action" value="save_branding">

    <!-- Section 1: Lab Organization Details -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-teal-100 text-teal-700 font-bold text-sm">
                    <i class="fa-solid fa-hospital"></i>
                </span>
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Lab Contact &amp; Organization Details</h2>
                    <p class="text-xs text-slate-500">Contact information displayed on letterheads and reports</p>
                </div>
            </div>
            <span class="badge badge-teal">Shared Branding</span>
        </div>
        <div class="p-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="field-label text-xs">Laboratory Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" class="field text-sm font-semibold" value="{$sName}" required>
            </div>
            <div>
                <label class="field-label text-xs">Contact Phone</label>
                <input type="text" name="phone" class="field text-sm" value="{$sPhone}" placeholder="+92 300 1234567">
            </div>
            <div>
                <label class="field-label text-xs">Email Address</label>
                <input type="email" name="email" class="field text-sm" value="{$sEmail}" placeholder="info@lab.com">
            </div>
            <div class="sm:col-span-2">
                <label class="field-label text-xs">Laboratory Physical Address</label>
                <input type="text" name="address" class="field text-sm" value="{$sAddress}" placeholder="Main Boulevard, City, Country">
            </div>
            <div>
                <label class="field-label text-xs">Logo Monogram / Short Text</label>
                <input type="text" name="logo" class="field text-sm font-mono" value="{$sLogo}" placeholder="e.g. LDP">
            </div>
        </div>
    </div>

    <!-- Section 2: Header Builder -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex items-center gap-2.5">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-teal-100 text-teal-700 font-bold text-sm">
                <i class="fa-solid fa-heading"></i>
            </span>
            <div>
                <h2 class="text-sm font-bold text-slate-800">Header Letterhead &amp; Logo Design</h2>
                <p class="text-xs text-slate-500">Drag and position your logo and QR verification stamp</p>
            </div>
        </div>
        <div class="p-5">
            {$headerBuilderHtml}
        </div>
    </div>

    <!-- Section 3: Footer Builder -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex items-center gap-2.5">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-sky-100 text-sky-700 font-bold text-sm">
                <i class="fa-solid fa-shoe-prints"></i>
            </span>
            <div>
                <h2 class="text-sm font-bold text-slate-800">Footer Letterhead &amp; Graphic Banner</h2>
                <p class="text-xs text-slate-500">Upload and position custom footer image banners across reports and bills</p>
            </div>
        </div>
        <div class="p-5">
            {$footerBuilderHtml}
        </div>
    </div>

    <!-- Section 4: Header & Footer Text Messages -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-100 text-indigo-700 font-bold text-sm">
                    <i class="fa-solid fa-quote-left"></i>
                </span>
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Print Notes &amp; Custom Messages</h2>
                    <p class="text-xs text-slate-500">Shared header note with department-specific footers for Laboratory vs. Diagnostic Imaging</p>
                </div>
            </div>
            <span class="badge badge-purple">Header &amp; Footer Notes</span>
        </div>
        <div class="p-5 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="field-label text-xs">Report Header Note <span class="text-slate-400 font-normal">(Top Header — Identical Across All Departments)</span></label>
                <input type="text" name="header" class="field text-sm" value="{$sHeader}" placeholder="e.g. ISO 9001:2015 Certified Diagnostic Center">
                <span class="text-[11px] text-slate-400 mt-0.5 block">Appears on Lab, Ultrasound, X-Ray, ECG, Echo, CT &amp; MRI reports</span>
            </div>
            <div>
                <label class="field-label text-xs">Patient Bill Header Note <span class="text-slate-400 font-normal">(Receipts &amp; Tokens)</span></label>
                <input type="text" name="bill_header" class="field text-sm" value="{$sBillHeader}" placeholder="e.g. Official Patient Receipt">
            </div>
            <div class="p-3 bg-teal-50/50 rounded-xl border border-teal-200/60">
                <label class="field-label text-xs font-bold text-teal-900"><i class="fa-solid fa-flask-vial mr-1 text-teal-600"></i> Laboratory Report Footer Message</label>
                <input type="text" name="footer" class="field text-sm bg-white" value="{$sFooter}" placeholder="e.g. Get well soon.">
                <span class="text-[11px] text-teal-700/80 mt-1 block">Printed exclusively at the bottom of Pathology &amp; Lab Medicine reports</span>
            </div>
            <div class="p-3 bg-sky-50/50 rounded-xl border border-sky-200/60">
                <label class="field-label text-xs font-bold text-sky-900"><i class="fa-solid fa-wave-square mr-1 text-sky-600"></i> Diagnostic Imaging / Modality Footer Message</label>
                <input type="text" name="imaging_footer" class="field text-sm bg-white" value="{$sImagingFooter}" placeholder="e.g. Diagnostic Center &amp; Imaging — For scan inquiries contact reception.">
                <span class="text-[11px] text-sky-700/80 mt-1 block">Printed at the bottom of Ultrasound, X-Ray, ECG, Echo, CT &amp; MRI reports</span>
            </div>
            <div>
                <label class="field-label text-xs">Patient Bill Footer Message</label>
                <input type="text" name="bill_footer" class="field text-sm" value="{$sBillFooter}" placeholder="e.g. Get well soon.">
            </div>
            <div>
                <label class="field-label text-xs">Custom Imaging Clinical Disclaimer <span class="text-slate-400 font-normal">(optional override)</span></label>
                <input type="text" name="imaging_disclaimer" class="field text-sm" value="{$sImagingDisclaimer}" placeholder="Leave blank for automatic modality disclaimer">
                <span class="text-[11px] text-slate-400 mt-0.5 block">Overrides default clinical correlation note for imaging</span>
            </div>
        </div>
    </div>

    <!-- Section 5: Typography & Font Selection (Below Patient Info) -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-purple-100 text-purple-700 font-bold text-sm">
                    <i class="fa-solid fa-font"></i>
                </span>
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Typography &amp; Document Fonts (Below Patient Info)</h2>
                    <p class="text-xs text-slate-500">Choose the font styling used across the test results, reference ranges, and billing table</p>
                </div>
            </div>
            <span class="badge badge-purple">Typography</span>
        </div>
        <div class="p-5 grid gap-6 sm:grid-cols-2">
            <div class="p-4 bg-slate-50/80 rounded-xl border border-slate-200 space-y-3">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-file-medical text-blue-600"></i>
                    <label class="field-label text-xs font-bold text-slate-800 mb-0">Lab Report Font (Below Patient Info)</label>
                </div>
                <p class="text-xs text-slate-500 leading-normal">Applied to the test parameters, reference values, units, and clinical findings on generated lab reports.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div>
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block mb-1">Font Family &amp; Style</label>
                        <select name="report_font" id="report_font_select" class="field text-sm font-semibold" onchange="updateFontPreviews()">
                            {$reportFontOpts}
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block mb-1">Text Size</label>
                        <select name="report_font_size" id="report_font_size_select" class="field text-sm font-semibold" onchange="updateFontPreviews()">
                            {$reportFontSizeOpts}
                        </select>
                    </div>
                </div>
                <div class="p-3 bg-white rounded-lg border border-slate-200 text-xs text-slate-700">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Live Preview Sample</span>
                    <div id="report-font-preview-text" class="text-slate-900 transition-all p-1">CBC / Hemoglobin: 14.2 g/dL &nbsp;|&nbsp; Ref: 12.0 - 16.0 &nbsp;|&nbsp; Unit: g/dL</div>
                </div>
            </div>

            <div class="p-4 bg-slate-50/80 rounded-xl border border-slate-200 space-y-3">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice text-emerald-600"></i>
                    <label class="field-label text-xs font-bold text-slate-800 mb-0">Billing / Receipt Font (Below Patient Info)</label>
                </div>
                <p class="text-xs text-slate-500 leading-normal">Applied to the billed tests table, prices, discounts, and payment summary on patient bills/receipts.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div>
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block mb-1">Font Family &amp; Style</label>
                        <select name="bill_font" id="bill_font_select" class="field text-sm font-semibold" onchange="updateFontPreviews()">
                            {$billFontOpts}
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block mb-1">Text Size</label>
                        <select name="bill_font_size" id="bill_font_size_select" class="field text-sm font-semibold" onchange="updateFontPreviews()">
                            {$billFontSizeOpts}
                        </select>
                    </div>
                </div>
                <div class="p-3 bg-white rounded-lg border border-slate-200 text-xs text-slate-700">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Live Preview Sample</span>
                    <div id="bill-font-preview-text" class="text-slate-900 transition-all p-1">Complete Blood Count: Rs 900 &nbsp;|&nbsp; Total Due: Rs 0 (PAID)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit Branding Buttons -->
    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button type="submit" class="btn btn-primary text-sm py-2.5 px-5 shadow-sm">
            <i class="fa-solid fa-floppy-disk mr-1.5"></i> Save Branding &amp; Letterhead
        </button>
        <a href="/portals/reports/preview.php" class="btn btn-secondary text-sm py-2.5 px-4">
            <i class="fa-solid fa-eye mr-1.5"></i> Preview Sample Report
        </a>
    </div>
</form>
HTML;

// Section 6A: Laboratory Medicine Signatories Form
$labSigFormFields = '';
for ($i = 0; $i < 5; $i++) {
    $sig = $labSignatories[$i] ?? ['name' => '', 'qualifications' => '', 'designation' => ''];
    $slotNum = $i + 1;
    $sDocName = e($sig['name']);
    $sQuals = e($sig['qualifications']);
    $sDesig = e($sig['designation']);

    $labSigFormFields .= <<<HTML
    <div class="p-4 border border-teal-200/80 rounded-xl bg-teal-50/20 shadow-xs space-y-3 relative">
        <div class="flex items-center justify-between border-b border-teal-100 pb-2">
            <span class="inline-flex items-center gap-1.5 text-xs font-bold text-teal-800">
                <i class="fa-solid fa-flask-vial text-teal-600"></i> Lab Signatory #{$slotNum}
            </span>
            <span class="text-[10px] uppercase font-bold text-teal-700 bg-teal-100 px-2 py-0.5 rounded">Slot {$slotNum}</span>
        </div>
        <div>
            <label class="field-label text-xs">Pathologist / Doctor Name</label>
            <input type="text" name="sig_name[]" class="field text-sm font-semibold bg-white" value="{$sDocName}" placeholder="e.g. Dr. A. K. Khan">
        </div>
        <div>
            <label class="field-label text-xs">Qualifications <span class="text-slate-400 font-normal">(optional)</span></label>
            <textarea name="sig_quals[]" rows="2" class="field text-xs bg-white" placeholder="e.g. M.B.B.S, M.Phil, F.C.P.S (Pathology)">{$sQuals}</textarea>
        </div>
        <div>
            <label class="field-label text-xs">Designation <span class="text-slate-400 font-normal">(optional)</span></label>
            <input type="text" name="sig_desig[]" class="field text-xs bg-white" value="{$sDesig}" placeholder="e.g. Consultant Pathologist">
        </div>
    </div>
    HTML;
}

// Section 6B: Diagnostic Center & Imaging Signatories Form
$imagingSigFormFields = '';
for ($i = 0; $i < 5; $i++) {
    $sig = $imagingSignatories[$i] ?? ['name' => '', 'qualifications' => '', 'designation' => ''];
    $slotNum = $i + 1;
    $sDocName = e($sig['name']);
    $sQuals = e($sig['qualifications']);
    $sDesig = e($sig['designation']);

    $imagingSigFormFields .= <<<HTML
    <div class="p-4 border border-sky-200/80 rounded-xl bg-sky-50/20 shadow-xs space-y-3 relative">
        <div class="flex items-center justify-between border-b border-sky-100 pb-2">
            <span class="inline-flex items-center gap-1.5 text-xs font-bold text-sky-900">
                <i class="fa-solid fa-user-doctor text-sky-600"></i> Imaging Specialist #{$slotNum}
            </span>
            <span class="text-[10px] uppercase font-bold text-sky-700 bg-sky-100 px-2 py-0.5 rounded">Slot {$slotNum}</span>
        </div>
        <div>
            <label class="field-label text-xs">Radiologist / Sonologist Name</label>
            <input type="text" name="imaging_sig_name[]" class="field text-sm font-semibold bg-white" value="{$sDocName}" placeholder="e.g. Dr. Imran Sheikh">
        </div>
        <div>
            <label class="field-label text-xs">Qualifications <span class="text-slate-400 font-normal">(optional)</span></label>
            <textarea name="imaging_sig_quals[]" rows="2" class="field text-xs bg-white" placeholder="e.g. M.B.B.S, F.C.P.S (Radiology)">{$sQuals}</textarea>
        </div>
        <div>
            <label class="field-label text-xs">Designation <span class="text-slate-400 font-normal">(optional)</span></label>
            <input type="text" name="imaging_sig_desig[]" class="field text-xs bg-white" value="{$sDesig}" placeholder="e.g. Consultant Radiologist / Sonologist">
        </div>
    </div>
    HTML;
}

$content .= <<<HTML
<!-- Section 6A: Laboratory Medicine Signatories -->
<div class="mt-8 bg-white border border-slate-200/90 rounded-2xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-teal-100 text-teal-700 font-bold text-sm">
                <i class="fa-solid fa-flask-vial"></i>
            </span>
            <div>
                <h2 class="text-sm font-bold text-slate-800">Laboratory Medicine Footer — Doctor Signatories</h2>
                <p class="text-xs text-slate-500">Signatures printed exclusively at the bottom of Pathology &amp; Laboratory Medicine reports</p>
            </div>
        </div>
        <span class="badge badge-teal">Lab Sign-offs</span>
    </div>
    <form method="post" class="p-5 space-y-4">
        <input type="hidden" name="action" value="save_signatories">
        <input type="hidden" name="sig_department" value="laboratory">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {$labSigFormFields}
        </div>
        <div class="pt-2">
            <button type="submit" class="btn btn-primary text-sm py-2 px-4 shadow-sm">
                <i class="fa-solid fa-check mr-1.5"></i> Save Laboratory Signatories
            </button>
        </div>
    </form>
</div>

<!-- Section 6B: Diagnostic Center & Imaging Signatories -->
<div class="mt-6 bg-white border border-slate-200/90 rounded-2xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-sky-100 text-sky-700 font-bold text-sm">
                <i class="fa-solid fa-wave-square"></i>
            </span>
            <div>
                <h2 class="text-sm font-bold text-slate-800">Diagnostic Center &amp; Imaging Footer — Specialist Signatories</h2>
                <p class="text-xs text-slate-500">Signatures printed exclusively at the bottom of Ultrasound, X-Ray, ECG, Echo, CT Scan &amp; MRI reports</p>
            </div>
        </div>
        <span class="badge badge-sky">Imaging Sign-offs</span>
    </div>
    <form method="post" class="p-5 space-y-4">
        <input type="hidden" name="action" value="save_imaging_signatories">
        <input type="hidden" name="sig_department" value="imaging">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {$imagingSigFormFields}
        </div>
        <div class="pt-2">
            <button type="submit" class="btn btn-primary text-sm py-2 px-4 shadow-sm bg-sky-600 hover:bg-sky-700 border-sky-600">
                <i class="fa-solid fa-check mr-1.5"></i> Save Diagnostic Imaging Signatories
            </button>
        </div>
    </form>
</div>

<script>
(function() {
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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateFontPreviews);
    } else {
        updateFontPreviews();
    }
})();
</script>
HTML;

render_page('Report Template', 'main-lab', 'settings-report', $content);
