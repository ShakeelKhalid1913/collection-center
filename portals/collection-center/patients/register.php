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

$form = card(
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
    select_field('Preferred language', 'lang', [
        'Urdu' => 'Urdu',
        'English' => 'English',
        'Punjabi' => 'Punjabi',
    ], 'Urdu', true) .
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
    '<div class="sm:col-span-2 lg:col-span-3">' .
    '<label class="field-label">Patient photo <span class="field-optional">(optional)</span></label>' .
    '<div class="photo-upload">' .
    '<div class="photo-upload__preview">Photo</div>' .
    '<div>Upload JPG / PNG for ID confirmation at collection</div>' .
    '<input type="file" accept="image/*" class="field" style="max-width:16rem">' .
    '</div></div>' .
    '</div>' .
    '<div class="sticky-actions">' .
    btn_submit('Save patient') .
    btn_primary('/portals/collection-center/patients/quick-register.php', 'Save & open Quick Reg') .
    btn_secondary('/portals/collection-center/patients/records.php', 'Back to records') .
    '</div>'
);

$content = page_header('Register Patient', 'Master patient file — title, relation, CNIC, blood group, contacts, address, notes, photo.');
$content .= $form;

render_page('Register Patient', 'collection-center', 'register', $content);
