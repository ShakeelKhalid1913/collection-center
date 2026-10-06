<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
$patientId = trim((string)($_GET['id'] ?? $_GET['patient_no'] ?? $_POST['id'] ?? ''));
$patient = $patientId !== '' ? patient_repo()->findById($patientId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $patient) {
    $fullName = trim($_POST['full_name'] ?? '');
    if ($fullName === '') {
        $message = flash_error('Patient full name is required.');
    } else {
        $doctor = trim($_POST['doctor'] ?? '');
        $ok = patient_repo()->update($patient['id'], [
            'title' => $_POST['title'] ?? 'Mr',
            'full_name' => $fullName,
            'relation_of' => trim($_POST['relation_of'] ?? ''),
            'phone' => $_POST['phone'] ?? '',
            'age' => (int)($_POST['age'] ?? 0),
            'gender' => $_POST['gender'] ?? 'Male',
            'cnic' => $_POST['cnic'] ?? '',
            'blood_group' => $_POST['blood_group'] ?? '',
            'email' => $_POST['email'] ?? '',
            'dob' => !empty($_POST['dob']) ? $_POST['dob'] : null,
            'doctor' => $doctor,
            'referring_doctor' => $doctor,
            'address' => $_POST['address'] ?? '',
            'city' => $_POST['city'] ?? '',
            'notes' => $_POST['notes'] ?? '',
        ]);

        if ($ok) {
            $patient = patient_repo()->findById($patient['id']);
            $message = flash_success('Patient details and referring doctor updated successfully! Any associated reports and bills have been updated.');
        } else {
            $message = flash_error('Failed to update patient details.');
        }
    }
}

if (!$patient) {
    $content = page_header('Edit Patient', 'Patient record not found.');
    $content .= card('<div class="p-6 text-slate-600"><p class="mb-4">No patient was found for the requested ID.</p>' . btn_secondary('/portals/collection-center/patients/records.php', 'Back to Patient Records') . '</div>');
    render_page('Edit Patient', 'collection-center', 'records', $content);
    exit;
}

$titles = [
    'Mr' => 'Mr', 'Mrs' => 'Mrs', 'Ms' => 'Ms', 'Miss' => 'Miss',
    'Dr' => 'Dr', 'Baby' => 'Baby', 'Master' => 'Master',
];

$blood = [
    '' => '— Select —', 'A+' => 'A+', 'A-' => 'A-', 'B+' => 'B+', 'B-' => 'B-',
    'AB+' => 'AB+', 'AB-' => 'AB-', 'O+' => 'O+', 'O-' => 'O-', 'Unknown' => 'Unknown',
];

$docVal = $patient['referring_doctor'] ?? $patient['emergency_name'] ?? '';
$mrNo = $patient['patient_no'] ?? $patient['id'];

$form = '<form method="post">' .
    '<input type="hidden" name="id" value="' . e($patient['id']) . '">' .
    card(
        panel_head('Edit patient details', 'MR No: ' . $mrNo) .
        '<p class="form-section-note">Update patient demographic info or referring doctor. Changes to doctor will automatically sync to this patient’s lab reports.</p>' .
        '<div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3 sm:p-6">' .
        form_field('MR Number', 'patient_no_display', 'text', $mrNo, '', true, 'readonly disabled style="background:#f1f5f9;cursor:not-allowed;"') .
        select_field('Title', 'title', $titles, $patient['title'] ?? 'Mr') .
        form_field('Full name', 'full_name', 'text', $patient['full_name'] ?? $patient['name'] ?? '', 'Legal / CNIC name') .
        form_field('Father / Husband Name', 'relation_of', 'text', $patient['relation_of'] ?? '', 'F/H name', true) .
        form_field('Mobile (primary)', 'phone', 'tel', $patient['phone'] ?? '', '03xx-xxxxxxx') .
        form_field('Age', 'age', 'number', (string)($patient['age'] ?? ''), 'Years', true) .
        select_field('Gender', 'gender', ['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'], $patient['gender'] ?? 'Male') .
        form_field('Date of birth', 'dob', 'date', $patient['dob'] ?? '', '', true) .
        form_field('CNIC / B-Form / Passport', 'cnic', 'text', $patient['cnic'] ?? '', 'xxxxx-xxxxxxx-x', true) .
        select_field('Blood group', 'blood_group', $blood, $patient['blood_group'] ?? '', true) .
        form_field('Email', 'email', 'email', $patient['email'] ?? '', '', true) .
        form_field("Doctor's Name / Referring Doctor", 'doctor', 'text', $docVal, 'e.g. Dr. Fatima Noor / Walk-in / Self', true) .
        '<div class="sm:col-span-2 lg:col-span-3">' .
        form_field('Address', 'address', 'text', $patient['address'] ?? '', 'House, street, area, city', true) .
        '</div>' .
        select_field('City', 'city', [
            'Faisalabad' => 'Faisalabad',
            'Islamabad' => 'Islamabad',
            'Karachi' => 'Karachi',
            'Lahore' => 'Lahore',
            'Other' => 'Other',
        ], $patient['city'] ?? 'Faisalabad', true) .
        '<div class="sm:col-span-2 lg:col-span-3">' .
        textarea_field('Internal notes', 'notes', $patient['internal_notes'] ?? $patient['notes'] ?? '', 'Staff only', 3, true) .
        '</div>' .
        '</div>' .
        '<div class="sticky-actions">' .
        btn_submit('Save Changes', '', 'fa-solid fa-check') .
        btn_secondary('/portals/collection-center/patients/history.php?id=' . urlencode($mrNo), 'View History', 'fa-solid fa-clock-rotate-left') .
        btn_secondary('/portals/collection-center/patients/records.php', 'Back to Records', 'fa-solid fa-arrow-left') .
        '</div>'
    ) . '</form>';

$content = page_header('Edit Patient', 'Update patient profile and referring doctor for ' . ($patient['full_name'] ?? $patient['name'] ?? $mrNo));
$content .= $message . $form;

render_page('Edit Patient', 'collection-center', 'records', $content);
