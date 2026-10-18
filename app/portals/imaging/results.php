<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
$scanNo = trim((string)($_GET['scan_no'] ?? $_POST['scan_no'] ?? ''));
$labNo = trim((string)($_GET['lab_no'] ?? $_POST['lab_no'] ?? ''));
$scan = $scanNo !== '' ? result_repo()->findImagingScan($scanNo) : null;

if (!$scan && $labNo !== '') {
    $scan = db()->fetchOne("SELECT * FROM imaging_scans WHERE lab_no = :lab_no ORDER BY id DESC LIMIT 1", ['lab_no' => $labNo]);
}

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$allScans = result_repo()->getImagingScans($orgId);

if (!$scan) {
    $pending = array_values(array_filter(
        $allScans,
        static fn($s) => in_array($s['status'] ?? '', ['pending', 'in_progress'], true)
    ));
    $scan = $pending[0] ?? ($allScans[0] ?? null);
}

// Dynamic doctors list
$doctorOpts = [];
foreach (mock('mock_doctors') as $d) {
    $spec = !empty($d['specialty']) ? ' (' . $d['specialty'] . ')' : '';
    $doctorOpts[$d['name']] = $d['name'] . $spec;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !empty($_POST['scan_no'])) {
    $radCustom = trim((string)($_POST['rad_custom'] ?? ''));
    $radSelected = trim((string)($_POST['rad'] ?? ''));
    $finalRad = $radCustom !== '' ? $radCustom : ($radSelected !== '' ? resolve_doctor_name($radSelected) : 'Dr. Imran Sheikh');

    $ok = result_repo()->saveImagingFindings($_POST['scan_no'], [
        'radiologist' => $finalRad,
        'findings' => $_POST['findings'] ?? '',
        'impression' => $_POST['impression'] ?? '',
        'status' => 'reported',
    ]);
    if ($ok) {
        header('Location: /portals/imaging/reports.php?scan_no=' . urlencode($_POST['scan_no']));
        exit;
    }
    $message = flash_error('Could not save findings.');
    $scan = result_repo()->findImagingScan($_POST['scan_no']);
}

$titleHint = $scan
    ? (($scan['scan_no'] ?? '') . ' · ' . ($scan['patient'] ?? '') . ' · ' . ($scan['study'] ?? ''))
    : 'No studies yet';

$content = page_header('Results & Clinical Findings', $titleHint, null, 'fa-solid fa-notes-medical');
$content .= $message;

if (!$scan) {
    $content .= card(
        '<p class="p-6 text-sm text-slate-600">No imaging studies found. Book a study from New Study first.</p>' .
        '<div class="p-6 pt-0">' . btn_primary('/portals/main-lab/lab-entries/new.php', 'New study') . '</div>'
    );
} else {
    $currentRad = $scan['radiologist'] ?? ($scan['doctor'] ?? 'Dr. Imran Sheikh');
    $deptMeta = get_department_meta((string)($scan['study'] ?? ''), (string)($scan['modality'] ?? ''));

    // Scan picker dropdown for switching between scans
    $scanPickerOpts = '';
    foreach ($allScans as $sc) {
        $sel = ($sc['scan_no'] === $scan['scan_no']) ? ' selected' : '';
        $scDept = get_department_meta((string)($sc['study'] ?? ''), (string)($sc['modality'] ?? ''));
        $scanPickerOpts .= '<option value="' . e($sc['scan_no']) . '"' . $sel . '>'
            . e($sc['scan_no']) . ' — ' . e($sc['patient']) . ' [' . e($scDept['short']) . ': ' . e($sc['study']) . '] (' . e(ucfirst($sc['status'])) . ')</option>';
    }

    $radSelectHtml = '<option value="">— Select Doctor —</option>';
    foreach ($doctorOpts as $docName => $docLabel) {
        $sel = (strcasecmp($docName, $currentRad) === 0) ? ' selected' : '';
        $radSelectHtml .= '<option value="' . e($docName) . '"' . $sel . '>' . e($docLabel) . '</option>';
    }

    $allTemplates = get_all_imaging_templates();
    $templatesJson = json_encode($allTemplates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    // Group templates into optgroups
    $groupedTemplates = [
        'ultrasound' => ['label' => '🌊 Ultrasound Examinations', 'items' => []],
        'xray' => ['label' => '🩻 Digital X-Ray Examinations', 'items' => []],
        'ecg' => ['label' => '💓 12-Lead ECG Examinations', 'items' => []],
        'ct' => ['label' => '🖥️ CT Scan Examinations', 'items' => []],
        'mri' => ['label' => '🧲 MRI Scan Examinations', 'items' => []],
    ];
    foreach ($allTemplates as $k => $t) {
        $deptKey = $t['dept'] ?? 'ultrasound';
        if (isset($groupedTemplates[$deptKey])) {
            $groupedTemplates[$deptKey]['items'][$k] = $t['title'];
        }
    }

    $templateSelectHtml = '<option value="">— Select Pre-loaded Normal Template —</option>';
    foreach ($groupedTemplates as $grp) {
        if (!empty($grp['items'])) {
            $templateSelectHtml .= '<optgroup label="' . e($grp['label']) . '">';
            foreach ($grp['items'] as $tplKey => $tplTitle) {
                $templateSelectHtml .= '<option value="' . e($tplKey) . '">' . e($tplTitle) . '</option>';
            }
            $templateSelectHtml .= '</optgroup>';
        }
    }

    // Auto-detect default template if findings are currently empty
    $defTemplate = get_default_imaging_template((string)($scan['study'] ?? ''), $deptMeta['id']);
    $initialFindings = (string)($scan['findings'] ?? '');
    $initialImpression = (string)($scan['impression'] ?? '');
    $isAutoLoaded = false;
    if ($initialFindings === '' && $initialImpression === '') {
        $initialFindings = $defTemplate['findings'];
        $initialImpression = $defTemplate['impression'];
        $isAutoLoaded = true;
    }

    $content .= <<<HTML
<div class="mb-5 no-print p-4 bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-3">
    <div class="flex-1 min-w-[16rem]">
        <label class="field-label text-xs">Switch Study / Patient Worklist</label>
        <select class="field text-sm font-semibold" onchange="if(this.value) location.href='/portals/imaging/results.php?scan_no='+encodeURIComponent(this.value)">
            {$scanPickerOpts}
        </select>
    </div>
    <div class="flex items-center gap-2">
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border {$deptMeta['badge_class']}">
            <i class="{$deptMeta['icon']} {$deptMeta['icon_color']}"></i>
            <span>Department: {$deptMeta['name']}</span>
        </span>
        <a href="/portals/imaging/receipt.php?scan_no={$scan['scan_no']}" class="btn btn-secondary text-xs">
            <i class="fa-solid fa-receipt mr-1"></i> Print Slip
        </a>
        <a href="/portals/imaging/reports.php?scan_no={$scan['scan_no']}" class="btn btn-secondary text-xs">
            <i class="fa-solid fa-file-waveform mr-1"></i> View Report
        </a>
    </div>
</div>
HTML;

    $templateBarHtml = <<<HTML
<div class="p-4 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 text-white rounded-2xl border border-slate-700 shadow-md space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-xl bg-[#c2f13c] text-slate-950 flex items-center justify-center text-sm font-black shadow-sm">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </span>
            <div>
                <strong class="text-white text-xs sm:text-sm font-extrabold uppercase tracking-wide block">
                    Diagnostic Report Normal Template Library
                </strong>
                <span class="text-[11px] text-slate-300">Shared clinical format across Ultrasound, X-Ray, ECG, CT &amp; MRI. Select template to populate or edit.</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <label for="img-universal-report-type" class="text-[11px] font-bold text-[#c2f13c] uppercase">Load Template:</label>
            <select id="img-universal-report-type" onchange="if(this.value) applyImagingTemplate(this.value)" class="field text-xs font-semibold py-1.5 px-3 bg-white text-slate-900 border-slate-300 rounded-xl">
                {$templateSelectHtml}
            </select>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-slate-700 text-xs">
        <span class="font-bold text-[#c2f13c] uppercase tracking-wide text-[10px] mr-1">Fast Quick Select:</span>
        <button type="button" onclick="applyImagingTemplate('us_whole_abd')" class="px-2.5 py-1 rounded-lg bg-teal-900/60 hover:bg-teal-800 text-teal-200 border border-teal-500/40 font-semibold text-[11px] transition-colors">
            🌊 USG Abdomen
        </button>
        <button type="button" onclick="applyImagingTemplate('us_kub')" class="px-2.5 py-1 rounded-lg bg-teal-900/60 hover:bg-teal-800 text-teal-200 border border-teal-500/40 font-semibold text-[11px] transition-colors">
            🌊 KUB &amp; Prostate
        </button>
        <button type="button" onclick="applyImagingTemplate('us_obstetric')" class="px-2.5 py-1 rounded-lg bg-teal-900/60 hover:bg-teal-800 text-teal-200 border border-teal-500/40 font-semibold text-[11px] transition-colors">
            🌊 Pregnancy (Obs)
        </button>
        <button type="button" onclick="applyImagingTemplate('xr_chest_pa')" class="px-2.5 py-1 rounded-lg bg-blue-900/60 hover:bg-blue-800 text-blue-200 border border-blue-500/40 font-semibold text-[11px] transition-colors">
            🩻 Chest X-Ray
        </button>
        <button type="button" onclick="applyImagingTemplate('xr_ls_spine')" class="px-2.5 py-1 rounded-lg bg-blue-900/60 hover:bg-blue-800 text-blue-200 border border-blue-500/40 font-semibold text-[11px] transition-colors">
            🩻 L/S Spine X-Ray
        </button>
        <button type="button" onclick="applyImagingTemplate('xr_knee')" class="px-2.5 py-1 rounded-lg bg-blue-900/60 hover:bg-blue-800 text-blue-200 border border-blue-500/40 font-semibold text-[11px] transition-colors">
            🩻 Knee X-Ray
        </button>
        <button type="button" onclick="applyImagingTemplate('ecg_12lead')" class="px-2.5 py-1 rounded-lg bg-rose-900/60 hover:bg-rose-800 text-rose-200 border border-rose-500/40 font-semibold text-[11px] transition-colors">
            💓 12-Lead ECG
        </button>
        <button type="button" onclick="applyImagingTemplate('ct_brain_plain')" class="px-2.5 py-1 rounded-lg bg-purple-900/60 hover:bg-purple-800 text-purple-200 border border-purple-500/40 font-semibold text-[11px] transition-colors">
            🖥️ Brain CT Scan
        </button>
        <button type="button" onclick="applyImagingTemplate('ct_hrct_chest')" class="px-2.5 py-1 rounded-lg bg-purple-900/60 hover:bg-purple-800 text-purple-200 border border-purple-500/40 font-semibold text-[11px] transition-colors">
            🖥️ HRCT Chest
        </button>
        <button type="button" onclick="applyImagingTemplate('mri_brain_plain')" class="px-2.5 py-1 rounded-lg bg-indigo-900/60 hover:bg-indigo-800 text-indigo-200 border border-indigo-500/40 font-semibold text-[11px] transition-colors">
            🧲 Brain MRI
        </button>
        <button type="button" onclick="applyImagingTemplate('mri_lumbar')" class="px-2.5 py-1 rounded-lg bg-indigo-900/60 hover:bg-indigo-800 text-indigo-200 border border-indigo-500/40 font-semibold text-[11px] transition-colors">
            🧲 Lumbar Spine MRI
        </button>
        <button type="button" onclick="clearImagingFields()" class="px-2.5 py-1 rounded-lg bg-white/10 hover:bg-rose-950 text-slate-300 hover:text-rose-300 border border-white/20 text-[11px] font-semibold ml-auto transition-colors">
            <i class="fa-solid fa-trash-can mr-1"></i> Clear Fields
        </button>
    </div>
</div>
HTML;

    $autoNotice = $isAutoLoaded
        ? '<div class="p-2.5 bg-emerald-50 text-emerald-800 text-xs font-semibold rounded-xl border border-emerald-200 flex items-center gap-2"><i class="fa-solid fa-circle-check text-emerald-600"></i> Auto-loaded standard normal template for <strong>' . e($scan['study']) . '</strong>. Review findings and edit any abnormal lines before saving.</div>'
        : '';

    $content .= card(
        '<form method="post" class="space-y-4 p-4 sm:p-6">' .
        '<input type="hidden" name="scan_no" value="' . e($scan['scan_no']) . '">' .
        '<div class="grid gap-4 sm:grid-cols-2">' .
        '<div><label class="field-label">Reporting Doctor / Consultant Radiologist</label><select name="rad" class="field text-sm font-semibold">' . $radSelectHtml . '</select></div>' .
        form_field('Or Custom Doctor Name (Switch Doctor)', 'rad_custom', 'text', null, 'Type any doctor name to switch', true) .
        '</div>' .
        '<div class="grid gap-4 sm:grid-cols-3">' .
        '<div><label class="field-label">Patient</label><p class="text-xs text-slate-800 font-bold bg-slate-50 p-2.5 rounded-lg border border-slate-200 m-0">' . e($scan['patient']) . ' (' . e($scan['patient_id'] ?: 'Walk-in') . ')</p></div>' .
        '<div><label class="field-label">Examination Study</label><p class="text-xs text-slate-800 font-bold bg-slate-50 p-2.5 rounded-lg border border-slate-200 m-0">' . e($scan['study']) . '</p></div>' .
        '<div><label class="field-label">Clinical Indication / History</label><p class="text-xs text-slate-600 bg-slate-50 p-2.5 rounded-lg border border-slate-200 m-0">' . e($scan['clinical_notes'] ?: 'Routine diagnostic evaluation.') . '</p></div>' .
        '</div>' .
        $templateBarHtml .
        $autoNotice .
        '<div><label class="field-label font-bold text-slate-900">Radiological &amp; Clinical Findings</label><textarea id="imaging-findings-ta" name="findings" rows="14" class="field text-sm font-mono leading-relaxed" placeholder="Describe findings (Organ dimensions, parenchymal echotexture, lung fields, bone alignment, signal intensities...)">' . e($initialFindings) . '</textarea></div>' .
        '<div><label class="field-label font-bold text-slate-900">Impression / Conclusion</label><textarea id="imaging-impression-ta" name="impression" rows="3" class="field text-sm font-semibold" placeholder="Diagnostic impression (e.g. Normal study, no focal lesion seen.)">' . e($initialImpression) . '</textarea></div>' .
        '<div class="pt-2 flex flex-wrap items-center gap-3">' .
        btn_submit('Save findings & generate report') .
        '<a href="/portals/imaging/pending-scans.php" class="btn btn-secondary">Back to pending</a>' .
        '</div>' .
        '</form>'
    );

    $content .= <<<SCRIPT
<script>
window.__IMAGING_FINDINGS_PRESETS__ = {$templatesJson};

function applyImagingTemplate(key) {
    const fTa = document.getElementById('imaging-findings-ta');
    const iTa = document.getElementById('imaging-impression-ta');
    const sel = document.getElementById('img-universal-report-type');
    if (!fTa || !iTa) return;

    if (sel && sel.value !== key) {
        sel.value = key;
    }

    const tpl = window.__IMAGING_FINDINGS_PRESETS__[key];
    if (!tpl) return;

    if ((fTa.value.trim() || iTa.value.trim()) && !confirm('Replace current findings & impression with the selected Normal Report template?')) {
        return;
    }

    fTa.value = tpl.findings;
    iTa.value = tpl.impression;
    fTa.focus();
}

function clearImagingFields() {
    const fTa = document.getElementById('imaging-findings-ta');
    const iTa = document.getElementById('imaging-impression-ta');
    if (confirm('Clear findings and impression?')) {
        if (fTa) fTa.value = '';
        if (iTa) iTa.value = '';
    }
}
</script>
SCRIPT;
}

render_page('Results', 'imaging', 'results', $content);
