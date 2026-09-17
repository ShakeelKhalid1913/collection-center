<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$titles = [
    'Mr' => 'Mr',
    'Mrs' => 'Mrs',
    'Ms' => 'Ms',
    'Miss' => 'Miss',
    'Dr' => 'Dr',
    'Baby' => 'Baby',
    'Master' => 'Master',
];

$relations = [
    'Self' => 'Self',
    'Father of' => 'Father of',
    'Mother of' => 'Mother of',
    'Son of' => 'Son of',
    'Daughter of' => 'Daughter of',
    'Husband of' => 'Husband of',
    'Wife of' => 'Wife of',
    'Guardian of' => 'Guardian of',
    'Other' => 'Other',
];

$blood = [
    '' => '— Select —',
    'A+' => 'A+',
    'A-' => 'A-',
    'B+' => 'B+',
    'B-' => 'B-',
    'AB+' => 'AB+',
    'AB-' => 'AB-',
    'O+' => 'O+',
    'O-' => 'O-',
    'Unknown' => 'Unknown',
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
    '<p class="form-section-note">Required fields marked with *. Title, relation, CNIC, blood group, email, photo and internal notes are available here — not a name-only form.</p>' .
    '<div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3 sm:p-6">' .
    select_field('Title', 'title', $titles, 'Mr') .
    form_field('Full name', 'full_name', 'text', null, 'As on CNIC / slip') .
    select_field('Relation', 'relation', $relations, 'Self') .
    form_field('Relation of (name)', 'relation_of', 'text', null, 'If not Self', true) .
    form_field('Mobile', 'phone', 'tel', null, '03xx-xxxxxxx') .
    form_field('Age', 'age', 'number', null, 'Years') .
    select_field('Gender', 'gender', ['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other']) .
    form_field('CNIC / B-Form', 'cnic', 'text', null, 'xxxxx-xxxxxxx-x', true) .
    select_field('Blood group', 'blood_group', $blood, '', true) .
    form_field('Email', 'email', 'email', null, 'patient@email.com', true) .
    form_field('Address', 'address', 'text', null, 'House / area / city', true) .
    '<div class="sm:col-span-2 lg:col-span-3">' .
    textarea_field('Internal notes', 'notes', null, 'Allergies, corporate account, draw instructions…', 2, true) .
    '</div>' .
    '<div class="sm:col-span-2 lg:col-span-3">' .
    '<label class="field-label">Patient photo <span class="field-optional">(optional)</span></label>' .
    '<div class="photo-upload">' .
    '<div class="photo-upload__preview">Photo</div>' .
    '<div><strong>Upload or capture</strong><br>JPG / PNG · max 2 MB</div>' .
    '<input type="file" accept="image/*" capture="user" class="field" style="max-width:16rem">' .
    '</div></div>' .
    '</div>'
);

$routingBlock = card(
    panel_head('2. Routing & referral') .
    '<p class="form-section-note">Where this order goes after collection, and who referred the patient.</p>' .
    '<div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">' .
    select_field('Referring doctor', 'doctor_id', $doctorOpts) .
    form_field('Referral / external doctor', 'ext_doctor', 'text', null, 'If not in list', true) .
    select_field('Route to', 'route_id', $routeOpts, 'RT-MAIN') .
    select_field('Priority', 'priority', [
        'Normal' => 'Normal',
        'Urgent' => 'Urgent (same day)',
        'STAT' => 'STAT / Critical',
    ], 'Normal') .
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
    '<p class="form-section-note">Search and tick packages or individual tests. Billing updates automatically.</p>' .
    '<div class="p-4 sm:p-6">' . catalog_picker('pathology') . '</div>'
);

$billingBlock = card(
    panel_head('4. Discount & payment') .
    '<div class="p-4 sm:p-6">' .
    billing_panel() .
    '</div>' .
    '<div class="sticky-actions">' .
    btn_submit('Save patient + entry & print receipt') .
    btn_secondary('/portals/collection-center/receipts.php', 'Preview receipt') .
    btn_secondary('/portals/collection-center/patients/register.php', 'Full registration only') .
    '</div>'
);

$content = page_header(
    'Quick Registration',
    'Walk-in intake: demographics + tests/packages + referral/routing + discount/payment in one screen.'
);

$content .= '<div class="grid gap-5 xl:grid-cols-3">';
$content .= '<div class="xl:col-span-2 space-y-5">' . $patientBlock . $routingBlock . $catalogBlock . '</div>';
$content .= '<div class="space-y-5 xl:sticky xl:top-20 xl:self-start">' . $billingBlock . '</div>';
$content .= '</div>';

render_page('Quick Registration', 'collection-center', 'quick-register', $content);
