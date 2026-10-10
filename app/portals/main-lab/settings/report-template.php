<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_permission('header_footer_edit');
    if (isset($_POST['action']) && $_POST['action'] === 'save_signatories') {
        $sigs = [];
        for ($i = 0; $i < 5; $i++) {
            $sigs[] = [
                'name' => $_POST['sig_name'][$i] ?? '',
                'qualifications' => $_POST['sig_quals'][$i] ?? '',
                'designation' => $_POST['sig_desig'][$i] ?? '',
            ];
        }
        $ok = setting_repo()->saveSignatories($orgId, $sigs);
        $message = $ok ? flash_success('Doctor signatories saved successfully.') : flash_error('Could not save signatories.');
    } else {
        $result = save_branding_request($orgId);
        $message = $result['ok'] ? flash_success($result['message']) : flash_error($result['message']);
    }
}

$s = branding_settings($orgId);
$signatories = setting_repo()->getSignatories($orgId);

$content = page_header(
    'Report & Bill Letterhead',
    'Design and customize your laboratory header, footer, contact branding, and doctor signatories.',
    '<a href="/portals/main-lab/reports/preview.php" class="btn btn-secondary text-sm"><i class="fa-solid fa-eye mr-1.5"></i> Preview Sample Report</a>'
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

$supportedFonts = supported_document_fonts();
$currentReportFont = $s['report_font'] ?? 'times_bold_italic';
$currentBillFont = $s['bill_font'] ?? 'times_bold_italic';

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

$fontMapJson = json_encode($supportedFonts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

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
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex items-center gap-2.5">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-100 text-indigo-700 font-bold text-sm">
                <i class="fa-solid fa-quote-left"></i>
            </span>
            <div>
                <h2 class="text-sm font-bold text-slate-800">Print Notes &amp; Custom Messages</h2>
                <p class="text-xs text-slate-500">Optional greetings, notes, and disclaimers printed on reports and patient bills</p>
            </div>
        </div>
        <div class="p-5 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="field-label text-xs">Report Header Note <span class="text-slate-400 font-normal">(optional)</span></label>
                <input type="text" name="header" class="field text-sm" value="{$sHeader}" placeholder="e.g. ISO 9001:2015 Certified Diagnostic Center">
            </div>
            <div>
                <label class="field-label text-xs">Bill Header Note <span class="text-slate-400 font-normal">(optional)</span></label>
                <input type="text" name="bill_header" class="field text-sm" value="{$sBillHeader}" placeholder="e.g. Official Patient Receipt">
            </div>
            <div>
                <label class="field-label text-xs">Report Footer Message</label>
                <input type="text" name="footer" class="field text-sm" value="{$sFooter}" placeholder="e.g. Get well soon.">
            </div>
            <div>
                <label class="field-label text-xs">Bill Footer Message</label>
                <input type="text" name="bill_footer" class="field text-sm" value="{$sBillFooter}" placeholder="e.g. Get well soon.">
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
                <select name="report_font" id="report_font_select" class="field text-sm font-semibold" onchange="updateFontPreviews()">
                    {$reportFontOpts}
                </select>
                <div class="p-3 bg-white rounded-lg border border-slate-200 text-xs text-slate-700">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Live Preview Sample</span>
                    <div id="report-font-preview-text" class="text-sm text-slate-900 transition-all p-1">CBC / Hemoglobin: 14.2 g/dL &nbsp;|&nbsp; Ref: 12.0 - 16.0 &nbsp;|&nbsp; Unit: g/dL</div>
                </div>
            </div>

            <div class="p-4 bg-slate-50/80 rounded-xl border border-slate-200 space-y-3">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice text-emerald-600"></i>
                    <label class="field-label text-xs font-bold text-slate-800 mb-0">Billing / Receipt Font (Below Patient Info)</label>
                </div>
                <p class="text-xs text-slate-500 leading-normal">Applied to the billed tests table, prices, discounts, and payment summary on patient bills/receipts.</p>
                <select name="bill_font" id="bill_font_select" class="field text-sm font-semibold" onchange="updateFontPreviews()">
                    {$billFontOpts}
                </select>
                <div class="p-3 bg-white rounded-lg border border-slate-200 text-xs text-slate-700">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Live Preview Sample</span>
                    <div id="bill-font-preview-text" class="text-sm text-slate-900 transition-all p-1">Complete Blood Count: Rs 900 &nbsp;|&nbsp; Total Due: Rs 0 (PAID)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit Branding Buttons -->
    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button type="submit" class="btn btn-primary text-sm py-2.5 px-5 shadow-sm">
            <i class="fa-solid fa-floppy-disk mr-1.5"></i> Save Branding &amp; Letterhead
        </button>
        <a href="/portals/main-lab/reports/preview.php" class="btn btn-secondary text-sm py-2.5 px-4">
            <i class="fa-solid fa-eye mr-1.5"></i> Preview Sample Report
        </a>
    </div>
</form>
HTML;

// Section 5: Doctor Signatories Form
$sigFormFields = '';
for ($i = 0; $i < 5; $i++) {
    $sig = $signatories[$i] ?? ['name' => '', 'qualifications' => '', 'designation' => ''];
    $slotNum = $i + 1;
    $sDocName = e($sig['name']);
    $sQuals = e($sig['qualifications']);
    $sDesig = e($sig['designation']);

    $sigFormFields .= <<<HTML
    <div class="p-4 border border-slate-200 rounded-xl bg-white shadow-xs space-y-3 relative">
        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-700">
                <i class="fa-solid fa-user-doctor text-teal-600"></i> Doctor Signatory #{$slotNum}
            </span>
            <span class="text-[10px] uppercase font-bold text-slate-400 bg-slate-50 px-2 py-0.5 rounded">Slot {$slotNum}</span>
        </div>
        <div>
            <label class="field-label text-xs">Doctor Name</label>
            <input type="text" name="sig_name[]" class="field text-sm font-semibold" value="{$sDocName}" placeholder="e.g. Dr. John Doe">
        </div>
        <div>
            <label class="field-label text-xs">Qualifications <span class="text-slate-400 font-normal">(optional)</span></label>
            <textarea name="sig_quals[]" rows="2" class="field text-xs" placeholder="e.g. M.B.B.S, M.Phil, F.C.P.S">{$sQuals}</textarea>
        </div>
        <div>
            <label class="field-label text-xs">Designation <span class="text-slate-400 font-normal">(optional)</span></label>
            <input type="text" name="sig_desig[]" class="field text-xs" value="{$sDesig}" placeholder="e.g. Consultant Pathologist">
        </div>
    </div>
    HTML;
}

$content .= <<<HTML
<div class="mt-8 bg-white border border-slate-200/90 rounded-2xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 font-bold text-sm">
                <i class="fa-solid fa-signature"></i>
            </span>
            <div>
                <h2 class="text-sm font-bold text-slate-800">Report Footer — Doctor Signatories</h2>
                <p class="text-xs text-slate-500">Up to 5 verified pathologist or consultant signature blocks on reports</p>
            </div>
        </div>
        <span class="badge badge-emerald">Sign-off Blocks</span>
    </div>
    <form method="post" class="p-5 space-y-4">
        <input type="hidden" name="action" value="save_signatories">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {$sigFormFields}
        </div>
        <div class="pt-2">
            <button type="submit" class="btn btn-primary text-sm py-2 px-4 shadow-sm">
                <i class="fa-solid fa-check mr-1.5"></i> Save Signatories
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    const fontMap = {$fontMapJson};

    window.updateFontPreviews = function() {
        const rSelect = document.getElementById('report_font_select');
        const rPreview = document.getElementById('report-font-preview-text');
        if (rSelect && rPreview && fontMap[rSelect.value]) {
            const f = fontMap[rSelect.value];
            rPreview.style.fontFamily = f.family;
            rPreview.style.fontWeight = f.weight;
            rPreview.style.fontStyle = f.style;
        }

        const bSelect = document.getElementById('bill_font_select');
        const bPreview = document.getElementById('bill-font-preview-text');
        if (bSelect && bPreview && fontMap[bSelect.value]) {
            const f = fontMap[bSelect.value];
            bPreview.style.fontFamily = f.family;
            bPreview.style.fontWeight = f.weight;
            bPreview.style.fontStyle = f.style;
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
