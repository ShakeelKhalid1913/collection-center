<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    if ($fullName === '') {
        $message = flash_error('Patient full name is required.');
    } else {
        $patientRes = patient_repo()->create([
            'patient_no' => trim($_POST['patient_no'] ?? ''),
            'title' => $_POST['title'] ?? 'Mr',
            'full_name' => $fullName,
            'phone' => $_POST['phone'] ?? '',
            'age' => (int)($_POST['age'] ?? 0),
            'gender' => $_POST['gender'] ?? 'Male',
            'cnic' => $_POST['cnic'] ?? '',
            'blood_group' => $_POST['blood_group'] ?? '',
            'email' => $_POST['email'] ?? '',
            'doctor' => resolve_doctor_name($_POST['doctor_id'] ?? ''),
            'address' => $_POST['address'] ?? '',
            'notes' => $_POST['notes'] ?? '',
            'branch' => 'CC-01',
            'created_by' => current_user()['id'] ?? null,
            'organization_id' => current_user()['organization_id'] ?? 'ORG-001',
        ]);

        if (!$patientRes['success']) {
            $message = flash_error('Could not save patient.');
        } else {
            $catalog = resolve_catalog_from_post($_POST);
            $doctor = resolve_doctor_name($_POST['doctor_id'] ?? '');

            $labRes = lab_repo()->create([
                'patient_id' => $patientRes['id'],
                'patient_name' => $fullName,
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
                'branch' => 'CC-01',
                'branch_id' => current_user()['branch_id'] ?? 'BR-GULBERG',
                'organization_id' => current_user()['organization_id'] ?? 'ORG-001',
            ]);

            if ($labRes['success']) {
                header('Location: /portals/collection-center/entry-done.php?lab_no=' . urlencode($labRes['lab_no']));
                exit;
            }
            $message = flash_error('Patient saved, but lab entry failed.');
        }
    }
}

$titles = [
    'Mr' => 'Mr', 'Mrs' => 'Mrs', 'Ms' => 'Ms', 'Miss' => 'Miss',
    'Dr' => 'Dr', 'Baby' => 'Baby', 'Master' => 'Master',
];

$blood = [
    '' => '— Select —', 'A+' => 'A+', 'A-' => 'A-', 'B+' => 'B+', 'B-' => 'B-',
    'AB+' => 'AB+', 'AB-' => 'AB-', 'O+' => 'O+', 'O-' => 'O-', 'Unknown' => 'Unknown',
];

$doctorOpts = ['' => '— Select referring doctor —'];
foreach (mock('mock_doctors') as $d) {
    $doctorOpts[$d['id']] = $d['name'] . ' (' . $d['specialty'] . ')';
}

$routeOpts = [];
foreach (mock('mock_routes') as $r) {
    $routeOpts[$r['id']] = $r['label'];
}

$patientBlock = card(
    panel_head('1. Patient details') .
    '<p class="form-section-note">Required: Title, Full name, Mobile, Gender. CNIC, blood group and email are optional — they appear on the report only when filled. Address prints on the report header area.</p>' .
    '<div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3 sm:p-6">' .
    form_field('MR Number', 'patient_no', 'text', null, 'e.g. MR-10482 (leave blank to auto-generate)', true) .
    select_field('Title', 'title', $titles, 'Mr') .
    form_field('Full name', 'full_name', 'text', null, 'As on CNIC / slip') .
    form_field('Mobile', 'phone', 'tel', null, '03xx-xxxxxxx') .
    form_field('Age', 'age', 'number', null, 'Years', true) .
    select_field('Gender', 'gender', ['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other']) .
    form_field('CNIC / B-Form', 'cnic', 'text', null, 'xxxxx-xxxxxxx-x', true) .
    select_field('Blood group', 'blood_group', $blood, '', true) .
    form_field('Email', 'email', 'email', null, 'patient@email.com', true) .
    '<div class="sm:col-span-2 lg:col-span-3">' .
    form_field('Address', 'address', 'text', null, 'House / area / city (shown on report header)', true) .
    '</div>' .
    '<div class="sm:col-span-2 lg:col-span-3">' .
    textarea_field('Internal notes', 'notes', null, 'Staff only — allergies, draw instructions…', 2, true) .
    '</div>' .
    '</div>'
);

$routingBlock = card(
    panel_head('2. Routing & referral') .
    '<p class="form-section-note">Referring doctor and laboratory route.</p>' .
    '<div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">' .
    select_field('Referring doctor', 'doctor_id', $doctorOpts) .
    select_field('Route to', 'route_id', $routeOpts, 'RT-MAIN') .
    select_field('Priority', 'priority', [
        'Normal' => 'Normal',
        'Urgent' => 'Urgent (same day)',
        'STAT' => 'STAT / Critical',
    ], 'Normal', true) .
    select_field('Sample collection', 'sample_status', [
        'pending' => 'Pending collection',
        'collected' => 'Collected now',
        'home' => 'Home collection requested',
    ], 'pending') .
    form_field('Clinical remarks', 'clinical', 'text', null, 'Fasting / suspected diagnosis…', true) .
    '</div>'
);

$catalogBlock = card(
    panel_head('3. Tests & packages') .
    '<p class="form-section-note">Search and tick packages or individual tests (incl. Uric Acid). Billing updates automatically. Missing a test? Ask Admin to add it under Tests Catalog, or open Admin → Tests.</p>' .
    '<div class="p-4 sm:p-6">' . catalog_picker('pathology') . '</div>'
);

$billingBlock = card(
    panel_head('4. Discount & payment') .
    '<div class="p-4 sm:p-6">' .
    billing_panel() .
    '</div>' .
    '<div class="sticky-actions">' .
    btn_submit('Save & preview receipt / report') .
    btn_secondary('/portals/collection-center/patients/register.php', 'Full registration only') .
    '</div>'
);

$content = page_header(
    'Quick Registration',
    'Walk-in intake: patient + tests + referral + payment — then receipt & report preview.'
);

$content .= $message;
$content .= '<form method="post"><div class="grid gap-5 xl:grid-cols-3">';
$content .= '<div class="xl:col-span-2 space-y-5">' . $patientBlock . $routingBlock . $catalogBlock . '</div>';
$content .= '<div class="space-y-5 xl:sticky xl:top-20 xl:self-start">' . $billingBlock . '</div>';
$content .= '</div></form>';

render_page('Quick Registration', 'collection-center', 'quick-register', $content);
