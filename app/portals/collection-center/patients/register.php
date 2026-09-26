<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? 'Mr');
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($fullName !== '') {
        $res = patient_repo()->create([
            'title' => $title,
            'full_name' => $fullName,
            'relation' => $_POST['relation'] ?? 'Self',
            'relation_of' => $_POST['relation_of'] ?? '',
            'phone' => $phone,
            'phone_alt' => $_POST['phone2'] ?? '',
            'age' => (int)($_POST['age'] ?? 0),
            'gender' => $_POST['gender'] ?? 'Male',
            'cnic' => $_POST['cnic'] ?? '',
            'blood_group' => $_POST['blood_group'] ?? '',
            'email' => $_POST['email'] ?? '',
            'dob' => $_POST['dob'] ?? null,
            'address' => $_POST['address'] ?? '',
            'city' => $_POST['city'] ?? '',
            'emergency_name' => $_POST['emergency_name'] ?? '',
            'emergency_phone' => $_POST['emergency_phone'] ?? '',
            'notes' => $_POST['notes'] ?? '',
            'patient_type' => $_POST['ptype'] ?? 'Walk-in',
            'panel_code' => $_POST['panel'] ?? '',
            'branch' => 'CC-01',
            'created_by' => current_user()['id'] ?? null,
            'organization_id' => current_user()['organization_id'] ?? 'ORG-001',
        ]);

        if ($res['success']) {
            header('Location: /portals/collection-center/patients/records.php?registered=1');
            exit;
        }
        $message = flash_error('Failed to save patient to database.');
    } else {
        $message = flash_error('Please provide patient full name.');
    }
}

$titles = [
    'Mr' => 'Mr', 'Mrs' => 'Mrs', 'Ms' => 'Ms', 'Miss' => 'Miss',
    'Dr' => 'Dr', 'Baby' => 'Baby', 'Master' => 'Master',
];

$relations = [
    'Self' => 'Self', 'Father of' => 'Father of', 'Mother of' => 'Mother of',
    'Son of' => 'Son of', 'Daughter of' => 'Daughter of', 'Husband of' => 'Husband of',
    'Wife of' => 'Wife of', 'Guardian of' => 'Guardian of', 'Other' => 'Other',
];

$blood = [
    '' => '— Select —', 'A+' => 'A+', 'A-' => 'A-', 'B+' => 'B+', 'B-' => 'B-',
    'AB+' => 'AB+', 'AB-' => 'AB-', 'O+' => 'O+', 'O-' => 'O-', 'Unknown' => 'Unknown',
];

$form = '<form method="post">' . card(
    panel_head('Patient registration') .
    '<p class="form-section-note">Full demographic record for the shared patient database. Quick Registration also creates a lab entry; this screen is patient master only.</p>' .
    '<div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3 sm:p-6">' .
    select_field('Title', 'title', $titles, 'Mr') .
    form_field('Full name', 'full_name', 'text', null, 'Legal / CNIC name') .
    select_field('Relation', 'relation', $relations, 'Self') .
    form_field('Relation of', 'relation_of', 'text', null, 'Parent / spouse name', true) .
    form_field('Date of birth', 'dob', 'date', null, '', true) .
    form_field('Age', 'age', 'number', null, 'Years') .
    select_field('Gender', 'gender', ['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other']) .
    form_field('CNIC / B-Form / Passport', 'cnic', 'text', null, 'xxxxx-xxxxxxx-x', true) .
    select_field('Blood group', 'blood_group', $blood, '', true) .
    form_field('Mobile (primary)', 'phone', 'tel', null, '03xx-xxxxxxx') .
    form_field('Mobile (alt)', 'phone2', 'tel', null, '', true) .
    form_field('Email', 'email', 'email', null, '', true) .
    form_field('Emergency contact name', 'emergency_name', 'text', null, '', true) .
    form_field('Emergency contact phone', 'emergency_phone', 'tel', null, '', true) .
    '<div class="sm:col-span-2 lg:col-span-3">' .
    form_field('Address', 'address', 'text', null, 'House, street, area, city', true) .
    '</div>' .
    select_field('City', 'city', [
        'Faisalabad' => 'Faisalabad',
        'Islamabad' => 'Islamabad',
        'Karachi' => 'Karachi',
        'Other' => 'Other',
    ], 'Faisalabad', true) .
    select_field('Patient type', 'ptype', [
        'Walk-in' => 'Walk-in',
        'Corporate' => 'Corporate',
        'Insurance' => 'Insurance',
        'VIP' => 'VIP',
    ], 'Walk-in') .
    form_field('Corporate / panel code', 'panel', 'text', null, '', true) .
    '<div class="sm:col-span-2 lg:col-span-3">' .
    textarea_field('Internal notes', 'notes', null, 'Visible to staff only — allergies, special handling…', 3, true) .
    '</div>' .
    '</div>' .
    '<div class="sticky-actions">' .
    btn_submit('Save patient') .
    btn_primary('/portals/collection-center/patients/quick-register.php', 'Save & open Quick Reg') .
    btn_secondary('/portals/collection-center/patients/records.php', 'Back to records') .
    '</div>'
) . '</form>';

$content = page_header('Register Patient', 'Master patient file — title, relation, CNIC, blood group, contacts, address, notes.');
$content .= $message . $form;

render_page('Register Patient', 'collection-center', 'register', $content);
