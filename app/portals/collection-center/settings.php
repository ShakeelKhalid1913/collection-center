<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
$settings = mock('mock_lab_settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = setting_repo()->save([
        'name' => $_POST['center_name'] ?? $settings['name'],
        'phone' => $_POST['phone'] ?? '',
        'address' => $_POST['address'] ?? '',
        'email' => $settings['email'] ?? '',
        'header' => $settings['header'] ?? '',
        'footer' => $settings['footer'] ?? '',
        'logo_text' => $settings['logo_text'] ?? 'HLP',
    ], current_user()['organization_id'] ?? 'ORG-001');
    $message = $ok ? flash_success('Settings saved.') : flash_error('Could not save settings.');
    $settings = mock('mock_lab_settings');
}

$content = page_header('Settings', 'Collection center profile (branch-scoped).');
$content .= $message;
$content .= card(
    '<form method="post" class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">' .
    form_field('Center name', 'center_name', 'text', $_POST['center_name'] ?? ($settings['name'] ?? 'Gulberg Collection Point')) .
    form_field('Branch code', 'branch_code', 'text', 'CC-01') .
    form_field('Phone', 'phone', 'text', $_POST['phone'] ?? ($settings['phone'] ?? '')) .
    form_field('Address', 'address', 'text', $_POST['address'] ?? ($settings['address'] ?? '')) .
    '<div class="sm:col-span-2">' . btn_submit('Save settings') . '</div>' .
    '</form>'
);

render_page('Settings', 'collection-center', 'settings', $content);
