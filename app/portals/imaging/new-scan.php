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
    'other' => 'Other',
];

$studies = [
    'Chest X-Ray PA' => 'Chest X-Ray PA',
    'Brain Plain CT' => 'Brain Plain CT',
    'Abdomen Ultrasound' => 'Abdomen Ultrasound',
    'Obstetric Ultrasound' => 'Obstetric Ultrasound',
    '12-Lead ECG' => '12-Lead ECG',
];

$patientOpts = [];
foreach (mock('mock_patients') as $p) {
    $patientOpts[$p['id']] = $p['name'];
}

$doctorOpts = [];
foreach (mock('mock_doctors') as $d) {
    $doctorOpts[$d['id']] = $d['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = $_POST['patient'] ?? '';
    $patientName = $patientOpts[$patientId] ?? 'Unknown';
    $modalityKey = $_POST['modality'] ?? 'xray';
    $res = result_repo()->createImagingScan([
        'patient' => $patientName,
        'patient_id' => $patientId,
        'modality' => $modalityLabels[$modalityKey] ?? 'X-Ray',
        'study' => $_POST['study'] ?? '',
        'radiologist' => resolve_doctor_name($_POST['rad'] ?? ''),
        'clinical_notes' => $_POST['clinical'] ?? '',
        'status' => 'pending',
        'scan_date' => date('Y-m-d'),
    ]);
    if ($res['success']) {
        header('Location: /portals/imaging/pending-scans.php?created=1');
        exit;
    }
    $message = flash_error('Could not create study.');
}

$content = page_header('New Study', 'Diagnostic Center — X-Ray, CT, Ultrasound, ECG and related modalities.');
$content .= $message;
$content .= card(
    '<form method="post" class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">' .
    select_field('Patient', 'patient', $patientOpts) .
    select_field('Modality', 'modality', [
        'xray' => 'X-Ray',
        'ct' => 'CT Scan',
        'us' => 'Ultrasound',
        'ecg' => 'ECG',
        'other' => 'Other (add later)',
    ], $preselect) .
    select_field('Study / exam', 'study', $studies) .
    select_field('Reporting doctor', 'rad', $doctorOpts) .
    form_field('Clinical notes', 'clinical', 'text', null, 'Indication / history', true) .
    '<div class="sm:col-span-2">' . btn_submit('Create study entry') . '</div></form>'
);

render_page('New Study', 'imaging', $modalityActive === 'modality-xray' && !isset($_GET['modality']) ? 'new-scan' : $modalityActive, $content);
