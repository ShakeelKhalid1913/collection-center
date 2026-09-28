<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = trim($_POST['patient_id'] ?? $_POST['patient_scan'] ?? '');
    $patientName = 'Walk-in Patient';

    if ($patientId !== '') {
        $p = patient_repo()->findById($patientId);
        if ($p) {
            $patientName = $p['full_name'] ?? $p['name'] ?? $patientId;
            $patientId = $p['id'] ?? $patientId;
        }
    }

    $catalog = resolve_catalog_from_post($_POST);
    $doctor = resolve_doctor_name($_POST['doctor_id'] ?? '');

    $res = lab_repo()->create([
        'patient_id' => $patientId ?: 'WALK-IN',
        'patient_name' => $patientName,
        'tests' => $catalog['tests'],
        'doctor' => $doctor,
        'route' => resolve_route_label($_POST['route_id'] ?? ''),
        'priority' => $_POST['priority'] ?? 'Normal',
        'status' => 'pending',
        'sample_status' => $_POST['sample_status'] ?? 'pending',
        'amount' => $catalog['amount'] > 0 ? $catalog['amount'] : $catalog['gross'],
        'paid' => $catalog['paid'],
        'discount' => $catalog['discount'],
        'clinical_notes' => $_POST['clinical'] ?? '',
        'branch' => 'MAIN-LAB',
        'branch_id' => current_user()['branch_id'] ?? 'BR-MAIN',
        'organization_id' => current_user()['organization_id'] ?? 'ORG-001',
    ]);

    if ($res['success']) {
        header('Location: /portals/main-lab/results/entry.php?lab_no=' . urlencode($res['lab_no']));
        exit;
    }
    $message = flash_error('Could not save lab entry.');
}

$patientOpts = ['' => '— Search / select patient —'];
foreach (patient_repo()->getAll(current_user()['organization_id'] ?? 'ORG-001') as $p) {
    $pid = $p['patient_no'] ?? $p['id'];
    $patientOpts[$pid] = $pid . ' · ' . trim(($p['title'] ?? '') . ' ' . ($p['full_name'] ?? $p['name'] ?? '')) . ' · ' . ($p['phone'] ?? '');
}

$doctorOpts = ['' => '— Select referring doctor —'];
foreach (mock('mock_doctors') as $d) {
    $doctorOpts[$d['id']] = $d['name'] . ' (' . $d['specialty'] . ')';
}

$routeOpts = [];
foreach (mock('mock_routes') as $r) {
    $routeOpts[$r['id']] = $r['label'];
}

$content = page_header(
    'Book / Assign Tests',
    'Select patient → referral → tests & packages → discount & payment.',
    btn_primary('/portals/main-lab/patients/register.php', 'Register new patient')
);

$content .= $message;
$content .= '<form method="post"><div class="grid gap-5 xl:grid-cols-3">';
$content .= '<div class="xl:col-span-2 space-y-5">';

$selectedPatientId = trim((string)($_GET['patient_id'] ?? ''));

$content .= card(
    panel_head('Patient & referral') .
    '<div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">' .
    select_field('Patient', 'patient_id', $patientOpts, $selectedPatientId) .
    form_field('Or scan / enter patient ID', 'patient_scan', 'text', $selectedPatientId, 'P-xxxxx / MR-xxxxx', true) .
    select_field('Referring doctor', 'doctor_id', $doctorOpts) .
    select_field('Route to', 'route_id', $routeOpts, 'RT-MAIN') .
    select_field('Priority', 'priority', [
        'Normal' => 'Normal',
        'Urgent' => 'Urgent (same day)',
        'STAT' => 'STAT / Critical',
    ], 'Normal', true) .
    select_field('Sample status', 'sample_status', [
        'pending' => 'Pending collection',
        'collected' => 'Collected now',
        'home' => 'Home collection',
    ]) .
    form_field('Clinical remarks', 'clinical', 'text', null, '', true) .
    '</div>'
);

$content .= card(
    panel_head('Tests & packages') .
    '<div class="p-4 sm:p-6">' . catalog_picker('pathology') . '</div>'
);

$content .= '</div>';

$content .= card(
    panel_head('Discount & payment') .
    '<div class="p-4 sm:p-6">' . billing_panel() . '</div>' .
    '<div class="sticky-actions">' .
    btn_submit('Save & open results entry') .
    '</div>'
);

$content .= '</div></form>';

render_page('Book / Assign Tests', 'main-lab', 'records', $content);
