<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$patientOpts = ['' => '— Search / select patient —'];
foreach (mock('mock_patients') as $p) {
    $patientOpts[$p['id']] = $p['id'] . ' · ' . trim(($p['title'] ?? '') . ' ' . $p['name']) . ' · ' . $p['phone'];
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
    'New Lab Entry',
    'Existing patient → referral/routing → tests & packages → discount & payment.',
    btn_primary('/portals/collection-center/patients/quick-register.php', 'Quick registration (new patient)')
);

$content .= '<div class="grid gap-5 xl:grid-cols-3">';
$content .= '<div class="xl:col-span-2 space-y-5">';

$content .= card(
    panel_head('Patient & referral') .
    '<div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">' .
    select_field('Patient', 'patient_id', $patientOpts) .
    form_field('Or scan / enter patient ID', 'patient_scan', 'text', null, 'P-xxxxx', true) .
    select_field('Referring doctor', 'doctor_id', $doctorOpts) .
    form_field('External referral', 'ext_doctor', 'text', null, 'Outside doctor name', true) .
    select_field('Route to', 'route_id', $routeOpts, 'RT-MAIN') .
    select_field('Priority', 'priority', [
        'Normal' => 'Normal',
        'Urgent' => 'Urgent (same day)',
        'STAT' => 'STAT / Critical',
    ]) .
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
    btn_submit('Save entry & print receipt') .
    btn_secondary('/portals/collection-center/receipts.php', 'Receipt preview') .
    '</div>'
);

$content .= '</div>';

render_page('New Lab Entry', 'collection-center', 'new-entry', $content);
