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
    $typedDoc = trim((string)($_POST['doctor'] ?? ''));
    $selectedDoc = resolve_doctor_name($_POST['doctor_id'] ?? '');
    $doctor = $typedDoc !== '' ? $typedDoc : $selectedDoc;
    if ($doctor === '') {
        $doctor = 'Walk-in / Self';
    }

    $typedRoute = trim((string)($_POST['route'] ?? ''));
    $selectedRoute = resolve_route_label($_POST['route_id'] ?? '');
    $route = $typedRoute !== '' ? $typedRoute : $selectedRoute;
    if ($route === '') {
        $route = 'Laboratory — Pathology (HQ)';
    }

    $res = lab_repo()->create([
        'patient_id' => $patientId ?: 'WALK-IN',
        'patient_name' => $patientName,
        'tests' => $catalog['tests'],
        'doctor' => $doctor,
        'route' => $route,
        'priority' => $_POST['priority'] ?? 'Normal',
        'status' => 'pending',
        'sample_status' => $_POST['sample_status'] ?? 'pending',
        'amount' => $catalog['amount'] > 0 ? $catalog['amount'] : $catalog['gross'],
        'paid' => $catalog['paid'],
        'discount' => $catalog['discount'],
        'clinical_notes' => $_POST['clinical'] ?? '',
        'created_at' => !empty($_POST['entry_time']) ? $_POST['entry_time'] : date('Y-m-d H:i:s'),
        'branch' => 'CC-01',
        'branch_id' => current_user()['branch_id'] ?? 'BR-GULBERG',
        'organization_id' => current_user()['organization_id'] ?? 'ORG-001',
    ]);

    if ($res['success']) {
        header('Location: /portals/collection-center/entry-done.php?lab_no=' . urlencode($res['lab_no']));
        exit;
    }
    $message = flash_error('Could not save lab entry.');
}

$patientOpts = ['' => '— Select patient —'];
foreach (mock('mock_patients') as $p) {
    $patientOpts[$p['id']] = $p['id'] . ' · ' . trim(($p['title'] ?? '') . ' ' . $p['name']) . ' · ' . $p['phone'];
}

$doctorOpts = ['' => '— Referring doctor —'];
foreach (mock('mock_doctors') as $d) {
    $doctorOpts[$d['id']] = $d['name'] . ' (' . $d['specialty'] . ')';
}

$selectedPatientId = trim((string)($_GET['patient_id'] ?? ''));

$content = page_header(
    'New Lab Entry',
    'Patient → pick test type → choose tests → discount & save.',
    btn_primary('/portals/collection-center/patients/quick-register.php', 'Quick registration (new patient)')
);

$content .= $message;
$content .= '<form method="post" data-billing class="space-y-4">';

$content .= card(
    '<div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3 sm:p-5">' .
    select_field('Patient', 'patient_id', $patientOpts, $selectedPatientId) .
    form_field('Or MR / Patient ID', 'patient_scan', 'text', $selectedPatientId, 'P-xxxxx', true) .
    doctor_input_field('Referring doctor', 'doctor', 'Walk-in / Self') .
    route_input_field('Route to', 'route', 'Laboratory — Pathology (HQ)') .
    form_field('Entry Date & Time', 'entry_time', 'datetime-local', date('Y-m-d\TH:i'), '', true) .
    select_field('Priority', 'priority', [
        'Normal' => 'Normal',
        'Urgent' => 'Urgent',
        'STAT' => 'STAT',
    ], 'Normal', true) .
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
        btn_submit('Save & preview receipt / report') .
        '</div>'
    ) .
    '</div>';

$content .= '</div></form>';

render_page('New Lab Entry', 'collection-center', 'new-entry', $content);
