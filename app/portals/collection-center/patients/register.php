<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    if ($fullName !== '') {
        $res = patient_repo()->create([
            'patient_no' => trim($_POST['patient_no'] ?? ''),
            'title' => $_POST['title'] ?? 'Mr',
            'full_name' => $fullName,
            'relation_of' => trim($_POST['relation_of'] ?? ''),
            'phone' => $_POST['phone'] ?? '',
            'age' => (int)($_POST['age'] ?? 0),
            'gender' => $_POST['gender'] ?? 'Male',
            'cnic' => $_POST['cnic'] ?? '',
            'blood_group' => $_POST['blood_group'] ?? '',
            'email' => $_POST['email'] ?? '',
            'dob' => $_POST['dob'] ?? null,
            'doctor' => trim($_POST['doctor'] ?? ''),
            'referring_doctor' => trim($_POST['doctor'] ?? ''),
            'address' => $_POST['address'] ?? '',
            'city' => $_POST['city'] ?? '',
            'notes' => $_POST['notes'] ?? '',
            'branch' => 'CC-01',
            'created_by' => current_user()['id'] ?? null,
            'created_at' => !empty($_POST['entry_time']) ? $_POST['entry_time'] : date('Y-m-d H:i:s'),
            'organization_id' => current_user()['organization_id'] ?? 'ORG-001',
        ]);
        if ($res['success']) {
            header('Location: /portals/collection-center/patients/records.php?registered=1');
            exit;
        }
        $message = flash_error('Failed to save patient.');
    } else {
        $message = flash_error('Please provide patient full name.');
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

$form = '<form method="post">' . card(
    panel_head('Patient registration') .
    '<p class="form-section-note">Title, name, mobile, gender required. CNIC / blood group / email optional (shown on report only when filled). Address prints with the lab header.</p>' .
    '<div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3 sm:p-6">' .
    form_field('MR Number', 'patient_no', 'text', null, 'e.g. MR-10482 (leave blank to auto-generate)', true) .
    select_field('Title', 'title', $titles, 'Mr') .
    form_field('Full name', 'full_name', 'text', null, 'Legal / CNIC name') .
    form_field('Father / Husband Name', 'relation_of', 'text', null, 'F/H name', true) .
    form_field('Mobile (primary)', 'phone', 'tel', null, '03xx-xxxxxxx') .
    form_field('Age', 'age', 'number', null, 'Years', true) .
    select_field('Gender', 'gender', ['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other']) .
    form_field('Date of birth', 'dob', 'date', null, '', true) .
    form_field('CNIC / B-Form / Passport', 'cnic', 'text', null, 'xxxxx-xxxxxxx-x', true) .
    select_field('Blood group', 'blood_group', $blood, '', true) .
    doctor_input_field('Referring Doctor', 'doctor', 'Walk-in / Self') .
    form_field('Registration Date & Time', 'entry_time', 'datetime-local', date('Y-m-d\TH:i'), '', true) .
    '<div class="sm:col-span-2 lg:col-span-3">' .
    form_field('Address', 'address', 'text', null, 'House, street, area, city', true) .
    '</div>' .
    select_field('City', 'city', [
        'Faisalabad' => 'Faisalabad',
        'Islamabad' => 'Islamabad',
        'Karachi' => 'Karachi',
        'Other' => 'Other',
    ], 'Faisalabad', true) .
    '<div class="sm:col-span-2 lg:col-span-3">' .
    textarea_field('Internal notes', 'notes', null, 'Staff only', 3, true) .
    '</div>' .
    '</div>' .
    '<div class="sticky-actions">' .
    btn_submit('Save patient') .
    btn_primary('/portals/collection-center/patients/quick-register.php', 'Open Quick Reg') .
    btn_secondary('/portals/collection-center/patients/records.php', 'Back to records') .
    '</div>'
) . '</form>';

$content = page_header('Register Patient', 'Patient master file for the shared database.');
$content .= $message . $form;

render_page('Register Patient', 'collection-center', 'register', $content);
