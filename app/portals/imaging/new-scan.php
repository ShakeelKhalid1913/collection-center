<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
$preselect = $_GET['modality'] ?? 'xray';
$modalityActive = match ($preselect) {
    'ct' => 'modality-ct',
    'us' => 'modality-us',
    'ecg' => 'modality-ecg',
    default => 'modality-xray',
};

$modalityLabels = [
    'xray' => 'X-Ray',
    'ct' => 'CT Scan',
    'us' => 'Ultrasound',
    'ecg' => 'ECG',
    'other' => 'Other Modality',
];

$orgId = current_user()['organization_id'] ?? 'ORG-001';

// Dynamic patients
$patientOpts = ['' => '— Walk-in / Select Patient —'];
foreach (patient_repo()->getAll($orgId) as $p) {
    $pid = $p['patient_no'] ?? $p['id'];
    $patientOpts[$pid] = $pid . ' · ' . trim(($p['title'] ?? '') . ' ' . ($p['full_name'] ?? $p['name'] ?? '')) . ' · ' . ($p['phone'] ?? '');
}

// Dynamic doctors from signatories, shares, employees
$doctorOpts = ['' => '— Select Doctor / Radiologist —'];
foreach (mock('mock_doctors') as $d) {
    $spec = !empty($d['specialty']) ? ' (' . $d['specialty'] . ')' : '';
    $doctorOpts[$d['name']] = $d['name'] . $spec;
}

// Dynamic studies from catalog
$studies = [];
try {
    $testRows = db()->fetchAll(
        "SELECT name, code, price, category FROM tests 
         WHERE category = 'Radiology' 
            OR name LIKE '%X-Ray%' 
            OR name LIKE '%Ultrasound%' 
            OR name LIKE '%ECG%' 
            OR name LIKE '%CT%' 
            OR name LIKE '%Doppler%' 
            OR name LIKE '%Scan%' 
         ORDER BY name ASC"
    );
    foreach ($testRows as $tr) {
        $studies[$tr['name']] = $tr['name'] . ' (Rs. ' . number_format((float)$tr['price'], 0) . ')';
    }
} catch (\Throwable $e) {}

$defaultImagingStudies = [
    'Ultrasound Whole Abdomen & Pelvis' => 'Ultrasound Whole Abdomen & Pelvis (Rs. 2,000)',
    'Ultrasound KUB & Prostate' => 'Ultrasound KUB & Prostate (Rs. 1,800)',
    'Ultrasound Pelvis (Female)' => 'Ultrasound Pelvis (Female) (Rs. 1,800)',
    'Ultrasound Obstetric / Pregnancy' => 'Ultrasound Obstetric / Pregnancy (Rs. 2,000)',
    'Ultrasound 2nd Trimester Anomaly Scan' => 'Ultrasound 2nd Trimester Anomaly Scan (Rs. 3,000)',
    'Ultrasound Upper Abdomen' => 'Ultrasound Upper Abdomen (Rs. 1,800)',
    'Ultrasound Scrotum & Doppler' => 'Ultrasound Scrotum & Doppler (Rs. 2,500)',
    'Ultrasound Thyroid & Neck' => 'Ultrasound Thyroid & Neck (Rs. 2,000)',
    'Ultrasound Breast (Bilateral)' => 'Ultrasound Breast (Bilateral) (Rs. 2,500)',
    'Ultrasound TVS (Transvaginal)' => 'Ultrasound TVS (Transvaginal) (Rs. 2,500)',
    'Ultrasound Follicular Tracking' => 'Ultrasound Follicular Tracking (Rs. 1,500)',
    'Ultrasound Soft Tissue / Swelling' => 'Ultrasound Soft Tissue / Swelling (Rs. 1,500)',
    'Chest X-Ray PA View' => 'Chest X-Ray PA View (Rs. 1,000)',
    '12-Lead ECG' => '12-Lead ECG (Rs. 800)',
    'Brain Plain CT Scan' => 'Brain Plain CT Scan (Rs. 6,500)',
    'Cervical Spine X-Ray AP/LAT' => 'Cervical Spine X-Ray AP/LAT (Rs. 1,500)',
];

if (empty($studies)) {
    $studies = $defaultImagingStudies;
} else {
    // Ensure all standard ultrasound types are available
    foreach ($defaultImagingStudies as $dName => $dLabel) {
        if (!isset($studies[$dName])) {
            $studies[$dName] = $dLabel;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = trim((string)($_POST['patient'] ?? ''));
    $patientName = trim((string)($_POST['walkin_patient'] ?? ''));
    
    if ($patientId !== '') {
        $p = patient_repo()->findById($patientId);
        if ($p) {
            $patientName = $p['full_name'] ?? $p['name'] ?? $patientId;
            $patientId = $p['id'] ?? $p['patient_no'] ?? $patientId;
        }
    }
    if ($patientName === '') {
        $patientName = 'Walk-in Patient';
    }

    $modalityKey = $_POST['modality'] ?? 'xray';
    $modalityLabel = $modalityLabels[$modalityKey] ?? ucfirst($modalityKey);
    $studyName = trim((string)($_POST['study_custom'] ?? ''));
    if ($studyName === '') {
        $studyName = trim((string)($_POST['study'] ?? 'Standard Scan'));
    }

    $docTyped = trim((string)($_POST['doctor_custom'] ?? ''));
    $docSelected = trim((string)($_POST['rad'] ?? ''));
    $doctor = $docTyped !== '' ? $docTyped : ($docSelected !== '' ? resolve_doctor_name($docSelected) : 'Dr. Imran Sheikh');

    $amount = (float)($_POST['amount'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);
    $paid = (float)($_POST['paid'] ?? 0);

    if ($amount <= 0) {
        $tMeta = test_repo()->findByCode($studyName);
        $amount = $tMeta ? (float)($tMeta['price'] ?? 1500) : 1500.0;
    }

    // 1. Create unified lab_entries token invoice
    $labRes = lab_repo()->create([
        'patient_id' => $patientId ?: 'WALK-IN',
        'patient_name' => $patientName,
        'tests' => $studyName,
        'doctor' => $doctor,
        'route' => 'Diagnostic Center',
        'priority' => $_POST['priority'] ?? 'Normal',
        'status' => 'pending',
        'sample_status' => 'received',
        'amount' => $amount,
        'paid' => $paid,
        'discount' => $discount,
        'clinical_notes' => $_POST['clinical'] ?? '',
        'organization_id' => $orgId,
        'branch' => 'IMG-01',
        'branch_id' => current_user()['branch_id'] ?? 'BR-IMAGING',
    ]);

    $labNo = $labRes['lab_no'] ?? ('L-' . date('Y') . '-' . sprintf('%04d', rand(1000, 9999)));

    // 2. Create study in imaging_scans linked to this lab_no
    $res = result_repo()->createImagingScan([
        'lab_no' => $labNo,
        'patient' => $patientName,
        'patient_id' => $patientId,
        'modality' => $modalityLabel,
        'study' => $studyName,
        'radiologist' => $doctor,
        'doctor' => $doctor,
        'clinical_notes' => $_POST['clinical'] ?? '',
        'status' => 'pending',
        'scan_date' => date('Y-m-d'),
        'amount' => $amount,
        'paid' => $paid,
        'discount' => $discount,
        'organization_id' => $orgId,
    ]);

    if ($res['success']) {
        header('Location: /portals/imaging/receipt.php?scan_no=' . urlencode($res['scan_no']) . '&lab_no=' . urlencode($labNo) . '&created=1');
        exit;
    }
    $message = flash_error('Could not create study.');
}

$content = page_header('New Study', 'Diagnostic Center — Book X-Ray, CT Scan, Ultrasound, ECG & generate official token slip.');
$content .= $message;

$studySelectOptions = '<option value="">— Select from catalog —</option>';
foreach ($studies as $k => $v) {
    $studySelectOptions .= '<option value="' . e($k) . '">' . e($v) . '</option>';
}

$doctorSelectOptions = '';
foreach ($doctorOpts as $k => $v) {
    $doctorSelectOptions .= '<option value="' . e($k) . '">' . e($v) . '</option>';
}

$content .= card(
    '<form method="post" class="p-4 sm:p-6 space-y-5">' .
    '<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">' .
    select_field('Registered Patient', 'patient', $patientOpts) .
    form_field('Or Walk-in Patient Name', 'walkin_patient', 'text', null, 'Patient full name if not registered', true) .
    select_field('Modality', 'modality', [
        'xray' => 'X-Ray',
        'us' => 'Ultrasound',
        'ct' => 'CT Scan',
        'ecg' => 'ECG',
        'other' => 'Other Modality',
    ], $preselect) .
    '<div><label class="field-label">Study / Exam (Catalog)</label><select name="study" id="study-select" class="field text-sm">' . $studySelectOptions . '</select></div>' .
    form_field('Or Custom Study Name', 'study_custom', 'text', null, 'e.g. Right Knee AP/LAT', true) .
    '<div><label class="field-label">Doctor / Radiologist</label><select name="rad" class="field text-sm">' . $doctorSelectOptions . '</select></div>' .
    form_field('Or Type Doctor Name (Unlock / Switch)', 'doctor_custom', 'text', null, 'e.g. Dr. Mujeeb Ur Rehman', true) .
    form_field('Clinical Notes / Indication', 'clinical', 'text', null, 'Reason / patient history', true) .
    select_field('Priority', 'priority', ['Normal' => 'Normal', 'Urgent' => 'Urgent', 'STAT' => 'STAT'], 'Normal', true) .
    '</div>' .

    '<div class="mt-4 pt-4 border-t border-slate-200">' .
    '<h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Billing &amp; Token Slip</h3>' .
    '<div class="grid gap-4 sm:grid-cols-3">' .
    form_field('Study Fee (Rs.)', 'amount', 'number', '1500', '1500', true) .
    form_field('Discount (Rs.)', 'discount', 'number', '0', '0', true) .
    form_field('Amount Paid (Rs.)', 'paid', 'number', '1500', '1500', true) .
    '</div></div>' .

    '<div class="pt-2 flex flex-wrap items-center gap-3">' .
    btn_submit('Create study & generate token slip') .
    '<a href="/portals/imaging/pending-scans.php" class="btn btn-secondary">Cancel</a>' .
    '</div>' .
    '</form>'
);

render_page('New Study', 'imaging', $modalityActive === 'modality-xray' && !isset($_GET['modality']) ? 'new-scan' : $modalityActive, $content);
