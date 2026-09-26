<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
$settings = mock('mock_lab_settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = setting_repo()->save([
        'name' => $_POST['dept'] ?? 'Diagnostic Center',
        'footer' => $_POST['footer'] ?? '',
        'phone' => $settings['phone'] ?? '',
        'email' => $settings['email'] ?? '',
        'address' => $settings['address'] ?? '',
        'header' => $settings['header'] ?? '',
        'logo_text' => $settings['logo_text'] ?? 'HLP',
    ], current_user()['organization_id'] ?? 'ORG-001');
    $message = $ok ? flash_success('Settings saved.') : flash_error('Could not save.');
    $settings = mock('mock_lab_settings');
}

$content = page_header('Settings', 'Diagnostic Center defaults (X-Ray, CT, Ultrasound, ECG).');
$content .= $message;
$content .= card(
    '<form method="post" class="grid gap-4 p-4 sm:max-w-lg sm:p-6">' .
    form_field('Department name', 'dept', 'text', $_POST['dept'] ?? ($settings['name'] ?? 'Diagnostic Center')) .
    form_field('Default report footer', 'footer', 'text', $_POST['footer'] ?? ($settings['footer'] ?? 'Diagnostic report — X-Ray / CT / Ultrasound / ECG.')) .
    btn_submit('Save') . '</form>'
);

render_page('Settings', 'imaging', 'settings', $content);
