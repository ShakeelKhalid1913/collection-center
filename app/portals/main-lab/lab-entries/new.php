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

$patientOpts = ['' => '— Select patient —'];
foreach (patient_repo()->getAll(current_user()['organization_id'] ?? 'ORG-001') as $p) {
    $pid = $p['patient_no'] ?? $p['id'];
    $patientOpts[$pid] = $pid . ' · ' . trim(($p['title'] ?? '') . ' ' . ($p['full_name'] ?? $p['name'] ?? '')) . ' · ' . ($p['phone'] ?? '');
}

$doctorOpts = ['' => '— Referring doctor —'];
foreach (mock('mock_doctors') as $d) {
    $doctorOpts[$d['id']] = $d['name'] . ' (' . $d['specialty'] . ')';
}

$routeOpts = [];
foreach (mock('mock_routes') as $r) {
    $routeOpts[$r['id']] = $r['label'];
}

$selectedPatientId = trim((string)($_GET['patient_id'] ?? ''));

$content = page_header(
    'Book Tests',
    'Patient → pick test type → choose tests → discount & save.',
    btn_primary('/portals/main-lab/patients/register.php', 'Register new patient')
);

$content .= $message;
$content .= '<form method="post" data-billing class="space-y-4">';

$content .= card(
    '<div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4 sm:p-5">' .
    select_field('Patient', 'patient_id', $patientOpts, $selectedPatientId) .
    form_field('Or MR / Patient ID', 'patient_scan', 'text', $selectedPatientId, 'P-xxxxx', true) .
    select_field('Referring doctor', 'doctor_id', $doctorOpts) .
    select_field('Priority', 'priority', [
        'Normal' => 'Normal',
        'Urgent' => 'Urgent',
        'STAT' => 'STAT',
    ], 'Normal', true) .
    '<input type="hidden" name="route_id" value="RT-MAIN">' .
    '<input type="hidden" name="sample_status" value="pending">' .
    '</div>'
);

$content .= '<div class="book-layout">';
$content .= card(
    panel_head('Tests') .
    '<div class="p-3 sm:p-4">' . catalog_picker('pathology') . '</div>',
    'overflow-hidden'
);

$content .= '<div class="book-layout__side space-y-3">' .
    card(
        panel_head('Discount & payment') .
        '<div class="p-4">' . billing_panel() . '</div>' .
        '<div class="px-4 pb-4">' .
        form_field('Notes (optional)', 'clinical', 'text', null, '', true) .
        '</div>' .
        '<div class="sticky-actions border-t border-slate-100">' .
        btn_submit('Save & enter results') .
        '</div>'
    ) .
    '</div>';

$content .= '</div></form>';

render_page('Book Tests', 'main-lab', 'records', $content);
