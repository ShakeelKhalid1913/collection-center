<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $message = flash_error('Full name is required.');
    } else {
        $res = patient_repo()->create([
            'patient_no' => trim($_POST['patient_no'] ?? ''),
            'full_name' => $name,
            'phone' => $_POST['phone'] ?? '',
            'age' => (int)($_POST['age'] ?? 0),
            'gender' => $_POST['gender'] ?? 'Female',
            'blood_group' => trim((string)($_POST['blood_group'] ?? '')),
            'relation_of' => trim($_POST['relation_of'] ?? ''),
            'doctor' => trim($_POST['doctor'] ?? ''),
            'referring_doctor' => trim($_POST['doctor'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'branch' => 'LAB-01',
            'created_by' => current_user()['id'] ?? null,
            'created_at' => !empty($_POST['entry_time']) ? $_POST['entry_time'] : date('Y-m-d H:i:s'),
            'organization_id' => current_user()['organization_id'] ?? 'ORG-001',
        ]);
        if ($res['success']) {
            header('Location: /portals/main-lab/patients/records.php?registered=1');
            exit;
        }
        $message = flash_error('Could not save patient.');
    }
}

$blood = [
    '' => '— Optional —',
    'A+' => 'A+', 'A-' => 'A-',
    'B+' => 'B+', 'B-' => 'B-',
    'AB+' => 'AB+', 'AB-' => 'AB-',
    'O+' => 'O+', 'O-' => 'O-',
    'Unknown' => 'Unknown',
];

$content = page_header('Register Patient', 'Shared patient database across all portals.');
$content .= $message;
$content .= card(
    '<form method="post" class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">' .
    form_field('MR Number', 'patient_no', 'text', null, 'e.g. MR-10482 (leave blank to auto-generate)', true) .
    form_field('Full name', 'name') .
    form_field('Father / Husband Name', 'relation_of', 'text', null, 'F/H name', true) .
    form_field('Mobile', 'phone') .
    form_field('Age', 'age', 'number') .
    select_field('Gender', 'gender', ['Female' => 'Female', 'Male' => 'Male', 'Other' => 'Other']) .
    select_field('Blood group', 'blood_group', $blood, '', true) .
    form_field("Doctor's Name", 'doctor', 'text', null, 'e.g. Dr. Fatima Noor / Walk-in / Self', true) .
    form_field('Registration Date & Time', 'entry_time', 'datetime-local', date('Y-m-d\TH:i'), '', true) .
    '<div class="sm:col-span-2">' .
    form_field('Address', 'address', 'text', null, 'House, street, area, city', true) .
    '</div>' .
    '<div class="sm:col-span-2">' . btn_submit('Save') . '</div></form>'
);

render_page('Register Patient', 'main-lab', 'register', $content);
