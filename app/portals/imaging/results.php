<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
$scanNo = trim((string)($_GET['scan_no'] ?? $_POST['scan_no'] ?? ''));
$scan = $scanNo !== '' ? result_repo()->findImagingScan($scanNo) : null;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['scan_no'])) {
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

$content = page_header('Results / Findings', $titleHint);
$content .= $message;

if (!$scan) {
    $content .= card(
        '<p class="p-6 text-sm text-slate-600">No imaging studies found. Book a study from New Study first.</p>' .
        '<div class="p-6 pt-0">' . btn_primary('/portals/imaging/new-scan.php', 'New study') . '</div>'
    );
} else {
    $currentRad = $scan['radiologist'] ?? ($scan['doctor'] ?? 'Dr. Imran Sheikh');

    // Scan picker dropdown for switching between scans
    $scanPickerOpts = '';
    foreach ($allScans as $sc) {
        $sel = ($sc['scan_no'] === $scan['scan_no']) ? ' selected' : '';
        $scanPickerOpts .= '<option value="' . e($sc['scan_no']) . '"' . $sel . '>'
            . e($sc['scan_no']) . ' — ' . e($sc['patient']) . ' (' . e($sc['study']) . ') [' . e(ucfirst($sc['status'])) . ']</option>';
    }

    $radSelectHtml = '<option value="">— Select Doctor —</option>';
    foreach ($doctorOpts as $docName => $docLabel) {
        $sel = (strcasecmp($docName, $currentRad) === 0) ? ' selected' : '';
        $radSelectHtml .= '<option value="' . e($docName) . '"' . $sel . '>' . e($docLabel) . '</option>';
    }

    $content .= <<<HTML
<div class="mb-5 no-print p-4 bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-3">
    <div class="flex-1 min-w-[16rem]">
        <label class="field-label text-xs">Switch Study / Patient</label>
        <select class="field text-sm font-semibold" onchange="if(this.value) location.href='/portals/imaging/results.php?scan_no='+encodeURIComponent(this.value)">
            {$scanPickerOpts}
        </select>
    </div>
    <div class="flex items-center gap-2">
        <a href="/portals/imaging/receipt.php?scan_no={$scan['scan_no']}" class="btn btn-secondary text-xs">
            <i class="fa-solid fa-receipt mr-1"></i> Print Slip
        </a>
        <a href="/portals/imaging/reports.php?scan_no={$scan['scan_no']}" class="btn btn-secondary text-xs">
            <i class="fa-solid fa-file-waveform mr-1"></i> View Report
        </a>
    </div>
</div>
HTML;

    $usSectionHtml = <<<HTML
<div class="p-4 bg-gradient-to-r from-teal-50 via-cyan-50 to-emerald-50 rounded-2xl border border-teal-200 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <span class="w-7 h-7 rounded-lg bg-teal-600 text-white flex items-center justify-center text-xs">
                <i class="fa-solid fa-shapes"></i>
            </span>
            <strong class="text-teal-950 text-xs sm:text-sm font-extrabold uppercase tracking-wide">
                Ultrasound Report Type Section
            </strong>
        </div>
        <div class="flex items-center gap-2">
            <label for="img-us-report-type" class="text-[11px] font-bold text-teal-900 uppercase">Report Type:</label>
            <select id="img-us-report-type" onchange="if(this.value) applyImagingUsType(this.value)" class="field text-xs font-semibold py-1 px-2.5 bg-white border-teal-300">
                <option value="">— Choose Ultrasound Report Type —</option>
                <optgroup label="🩺 Abdomen &amp; Urinary Tract">
                    <option value="us_whole_abd">Ultrasound Whole Abdomen &amp; Pelvis</option>
                    <option value="us_upper_abd">Ultrasound Upper Abdomen (Hepatobiliary)</option>
                    <option value="us_kub">Ultrasound KUB &amp; Prostate (Kidneys, Bladder, Prostate)</option>
                </optgroup>
                <optgroup label="🌸 Gynaecological &amp; Pelvic">
                    <option value="us_pelvis">Ultrasound Pelvis (Female / Gynaecological)</option>
                    <option value="us_tvs">Ultrasound TVS (Transvaginal Sonography)</option>
                    <option value="us_follicular">Ultrasound Follicular Tracking / Study</option>
                    <option value="us_breast">Ultrasound Breast (Bilateral / Sonomammography)</option>
                </optgroup>
                <optgroup label="👶 Obstetrics &amp; Pregnancy">
                    <option value="us_obstetric">Ultrasound Obstetric / Antenatal (Routine Pregnancy)</option>
                    <option value="us_anomaly">Ultrasound 2nd Trimester Fetal Anomaly Scan</option>
                </optgroup>
                <optgroup label="🔬 Doppler &amp; Superficial Parts">
                    <option value="us_scrotal">Ultrasound Scrotum &amp; Testicular Color Doppler</option>
                    <option value="us_thyroid">Ultrasound Thyroid &amp; Neck</option>
                    <option value="us_soft_tissue">Ultrasound Soft Tissue / Musculoskeletal Swelling</option>
                </optgroup>
                <optgroup label="🩻 Other Radiology Modalities">
                    <option value="xray_chest">Chest X-Ray PA View</option>
                    <option value="ecg_12">12-Lead Electrocardiogram (ECG)</option>
                </optgroup>
            </select>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-teal-200/70 text-xs">
        <span class="font-bold text-teal-900 uppercase tracking-wide text-[10px] mr-1">Quick Select:</span>
        <button type="button" onclick="applyImagingUsType('us_whole_abd')" class="px-2 py-0.5 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs text-[11px]">
            Whole Abdomen
        </button>
        <button type="button" onclick="applyImagingUsType('us_kub')" class="px-2 py-0.5 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs text-[11px]">
            KUB &amp; Prostate
        </button>
        <button type="button" onclick="applyImagingUsType('us_pelvis')" class="px-2 py-0.5 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs text-[11px]">
            Female Pelvis
        </button>
        <button type="button" onclick="applyImagingUsType('us_obstetric')" class="px-2 py-0.5 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs text-[11px]">
            Pregnancy (Obs)
        </button>
        <button type="button" onclick="applyImagingUsType('us_anomaly')" class="px-2 py-0.5 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs text-[11px]">
            Anomaly Scan
        </button>
        <button type="button" onclick="applyImagingUsType('us_scrotal')" class="px-2 py-0.5 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs text-[11px]">
            Scrotal Doppler
        </button>
        <button type="button" onclick="applyImagingUsType('us_thyroid')" class="px-2 py-0.5 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs text-[11px]">
            Thyroid &amp; Neck
        </button>
        <button type="button" onclick="applyImagingUsType('us_breast')" class="px-2 py-0.5 rounded-lg bg-white hover:bg-teal-100 text-teal-900 border border-teal-200 font-semibold shadow-2xs text-[11px]">
            Breast
        </button>
        <button type="button" onclick="applyImagingUsType('xray_chest')" class="px-2 py-0.5 rounded-lg bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-semibold shadow-2xs text-[11px]">
            Chest X-Ray
        </button>
        <button type="button" onclick="clearImagingFields()" class="px-2 py-0.5 rounded-lg bg-white hover:bg-rose-50 text-slate-500 hover:text-rose-700 border border-slate-200 text-[11px] font-semibold ml-auto">
            Clear
        </button>
    </div>
</div>
HTML;

    $content .= card(
        '<form method="post" class="space-y-4 p-4 sm:p-6">' .
        '<input type="hidden" name="scan_no" value="' . e($scan['scan_no']) . '">' .
        '<div class="grid gap-4 sm:grid-cols-2">' .
        '<div><label class="field-label">Reporting Doctor / Radiologist</label><select name="rad" class="field text-sm">' . $radSelectHtml . '</select></div>' .
        form_field('Or Custom Doctor Name (Switch Doctor)', 'rad_custom', 'text', null, 'Type any doctor name to switch', true) .
        '</div>' .
        '<div><label class="field-label">Clinical Indication / History</label><p class="text-xs text-slate-600 bg-slate-50 p-2.5 rounded-lg border border-slate-200 m-0">' . e($scan['clinical_notes'] ?: 'None provided.') . '</p></div>' .
        $usSectionHtml .
        '<div><label class="field-label">Radiological Findings</label><textarea id="imaging-findings-ta" name="findings" rows="12" class="field text-sm font-mono leading-relaxed" placeholder="Describe findings (e.g. Bone alignment, soft tissues, lung fields, organ dimensions...)">' . e($scan['findings'] ?? '') . '</textarea></div>' .
        '<div><label class="field-label">Impression / Conclusion</label><textarea id="imaging-impression-ta" name="impression" rows="3" class="field text-sm font-semibold" placeholder="Diagnostic impression (e.g. Normal study, no focal lesion seen.)">' . e($scan['impression'] ?? '') . '</textarea></div>' .
        '<div class="pt-2 flex flex-wrap gap-2">' .
        btn_submit('Save findings & generate report') .
        '<a href="/portals/imaging/pending-scans.php" class="btn btn-secondary">Back to pending</a>' .
        '</div>' .
        '</form>'
    );

    $content .= <<<'SCRIPT'
<script>
window.__IMAGING_FINDINGS_PRESETS__ = {
    us_whole_abd: {
        findings: "LIVER: Normal in size, smooth contour and uniform parenchymal echotexture. No focal solid or cystic mass lesion seen. Intrahepatic biliary tree and portal vein are normal in caliber.\n\nGALLBLADDER: Normal in size and shape. Wall thickness is normal (< 3 mm). Acoustic lumen is completely clear; no calculus, sludge or mass lesion seen. Common bile duct (CBD) is normal.\n\nPANCREAS: Visualized head, body and tail appear normal in size and echogenicity. Main pancreatic duct is not dilated.\n\nSPLEEN: Normal in size and parenchymal echotexture. No splenomegaly or focal lesion noted.\n\nKIDNEYS: Both kidneys are normal in size, shape and cortical thickness. Corticomedullary differentiation is well preserved. No calculus or hydronephrosis seen bilaterally.\n\nURINARY BLADDER: Well distended with thin, smooth walls and clear lumen. No intraluminal calculus or mass.\n\nPELVIS / PROSTATE: Visualized pelvic structures are unremarkable. No free fluid in abdomen or pelvis.",
        impression: "NORMAL ULTRASOUND EXAMINATION OF WHOLE ABDOMEN & PELVIS."
    },
    us_upper_abd: {
        findings: "LIVER: Normal in size, shape and parenchymal echotexture. No focal mass, abscess, or nodule seen. Intrahepatic ducts are not dilated. Portal vein is normal in caliber.\n\nGALLBLADDER: Normal lumen without gallstones, sludge, or wall thickening. Common bile duct (CBD) measures within normal limits.\n\nPANCREAS: Visualized portions appear normal in size and echogenicity without mass or calcification.\n\nSPLEEN: Normal in size with homogenous parenchymal echotexture. No focal lesion or splenomegaly.\n\nKIDNEYS: Both kidneys are normal in size, shape, and cortical thickness. No calculus or hydronephrosis seen.",
        impression: "NORMAL ULTRASOUND EXAMINATION OF UPPER ABDOMEN."
    },
    us_kub: {
        findings: "RIGHT KIDNEY: Measures normal in size and anatomical position. Corticomedullary differentiation is well preserved. Normal parenchymal thickness. No evidence of renal calculus, hydronephrosis, or focal lesion.\n\nLEFT KIDNEY: Measures normal in size and anatomical position. Corticomedullary differentiation is well preserved. Normal parenchymal thickness. No evidence of renal calculus, hydronephrosis, or focal lesion.\n\nURETERS: Both ureters are not dilated; no obstructive ureteric calculus visualized.\n\nURINARY BLADDER: Adequately distended with clear acoustic lumen. Bladder wall thickness is smooth and uniform (< 3 mm). No intraluminal calculus, mass, or diverticulum seen.\n- Pre-void volume: Adequate.\n- Post-void residual (PVR): Insignificant (< 20 ml).\n\nPROSTATE: Normal in size, shape, and echotexture with smooth capsule. Estimated volume is within normal age-related limits. Median lobe is not enlarged.",
        impression: "NORMAL ULTRASOUND KUB & PROSTATE. NO EVIDENCE OF NEPHROLITHIASIS OR URINARY TRACT OBSTRUCTION."
    },
    us_pelvis: {
        findings: "URINARY BLADDER: Well distended with clear lumen and smooth walls.\n\nUTERUS: Anteverted, normal in size and regular in contour. Myometrium is homogeneous without fibroids or adenomyosis. Endometrium is central, regular, and well-defined measuring within normal limits.\n\nCERVIX: Normal in appearance.\n\nRIGHT OVARY: Normal in size with physiological follicular pattern. No cyst or mass.\n\nLEFT OVARY: Normal in size with physiological follicular pattern. No cyst or mass.\n\nADNEXAE & POD: Clear. No adnexal mass or free fluid in Pouch of Douglas.",
        impression: "NORMAL PELVIC ULTRASOUND EXAMINATION. NORMAL APPEARANCE OF UTERUS AND BOTH OVARIES."
    },
    us_obstetric: {
        findings: "FETAL VIABILITY: Single live intrauterine fetus in cephalic presentation. Regular cardiac activity (FHR: ~144 bpm) and active somatic movements.\n\nFETAL BIOMETRY:\n- BPD, FL, AC, HC correspond to clinical gestational age.\n- EGA & EDD consistent with biometry.\n\nAMNIOTIC FLUID: Adequate in volume (normal AFI).\n\nPLACENTA: Anterior / Fundal, Grade I maturity. Clear of internal cervical os. No hematoma.",
        impression: "SINGLE LIVE INTRAUTERINE PREGNANCY CORRESPONDING TO GESTATIONAL AGE WITH ACTIVE FETAL MOVEMENTS AND NORMAL LIQUOR / PLACENTAL POSITION."
    },
    us_anomaly: {
        findings: "Single live fetus in cephalic presentation with regular cardiac activity.\n\nANATOMICAL SURVEY:\n- Head & Brain: Intact skull contour, normal lateral ventricles, cavum septum pellucidum & posterior fossa.\n- Face: Intact orbits, nasal bone, and upper lip.\n- Spine: Intact vertebrae from cervical to sacral region.\n- Heart: Normal 4-chamber view, normal outflow tracts (LVOT/RVOT).\n- Abdomen: Intact wall, normal stomach bubble, intact cord insertion, both kidneys & bladder.\n- Limbs: Bilateral upper & lower limbs morphologically intact.\n- Amniotic fluid: Adequate.\n- Placenta: Normal position, 3-vessel umbilical cord.",
        impression: "TARGETED ANOMALY SCAN REVEALS NO GROSS CONGENITAL OR STRUCTURAL ANOMALY DETECTED AT PRESENT GESTATIONAL AGE."
    },
    us_tvs: {
        findings: "UTERUS: Anteverted, homogeneous myometrium without focal fibroids. Trilaminar endometrial stripe of normal thickness.\n\nOVARIES: Both ovaries are normal in size with physiological follicles. No complex ovarian mass.\n\nCUL-DE-SAC: Clear of free fluid or endometriotic deposits.",
        impression: "NORMAL TRANSVAGINAL ULTRASOUND STUDY (TVS)."
    },
    us_scrotal: {
        findings: "BILATERAL TESTES: Normal in size, contour, and homogeneous echotexture. Symmetrical normal color and spectral Doppler flow. No intratesticular lesion.\n\nEPIDIDYMIDES: Bilateral epididymides are normal.\n\nPAMPINIFORM PLEXUS: Normal diameter bilaterally (< 2 mm). No retrograde venous reflux on Valsalva maneuver.",
        impression: "NORMAL SCROTAL AND TESTICULAR COLOR DOPPLER STUDY. NO EVIDENCE OF EPIDIDYMO-ORCHITIS, HYDROCELE, OR VARICOCELE."
    },
    us_thyroid: {
        findings: "RIGHT & LEFT THYROID LOBES: Normal in dimensions and parenchymal echogenicity. No focal solid nodule, cystic lesion, or calcification identified. Normal vascularity on color Doppler.\n\nISTHMUS: Normal in thickness (< 3 mm).\n\nCERVICAL LYMPH NODES: No suspicious lymphadenopathy identified.",
        impression: "NORMAL ULTRASOUND EXAMINATION OF THYROID GLAND. NO EVIDENCE OF GOITRE, NODULE, OR CYSTIC LESION."
    },
    us_breast: {
        findings: "BILATERAL BREASTS: Normal fibroglandular parenchyma with normal ductal pattern. No focal solid mass lesion, cyst, or architectural distortion seen. Retroareolar ducts are not dilated.\n\nAXILLARY REGIONS: Preserved normal lymph nodes with fatty hilum.\n\nACR BI-RADS ASSESSMENT: CATEGORY 1 (NEGATIVE / NORMAL STUDY).",
        impression: "NORMAL BILATERAL BREAST ULTRASOUND (BI-RADS CATEGORY 1)."
    },
    xray_chest: {
        findings: "LUNG FIELDS: Clear with normal bronchovascular markings. No consolidation, mass, or cavity.\n\nCOSTOPHRENIC ANGLES: Sharp and clear bilaterally.\n\nCARDIAC SILHOUETTE: Normal in size and contour (CTR < 50%).\n\nHILAR & MEDIASTINUM: Unremarkable. Bony thorax intact.",
        impression: "NORMAL RADIOLOGICAL STUDY OF CHEST."
    },
    ecg_12: {
        findings: "Rhythm: Normal Sinus Rhythm\nHeart Rate: 72 bpm\nPR Interval: 0.16 sec\nQRS Duration: 0.08 sec\nQTc: 410 ms\nAxis: Normal cardiac axis (+45°)\nST-T Changes: None.",
        impression: "NORMAL 12-LEAD ELECTROCARDIOGRAM (ECG)."
    }
};

function applyImagingUsType(key) {
    const fTa = document.getElementById('imaging-findings-ta');
    const iTa = document.getElementById('imaging-impression-ta');
    const sel = document.getElementById('img-us-report-type');
    if (!fTa || !iTa) return;

    if (sel && sel.value !== key) {
        sel.value = key;
    }

    const tpl = window.__IMAGING_FINDINGS_PRESETS__[key];
    if (!tpl) return;

    if ((fTa.value.trim() || iTa.value.trim()) && !confirm('Replace current findings & impression with the selected Ultrasound Report Type template?')) {
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
