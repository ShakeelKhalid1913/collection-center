<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
$s = mock('mock_lab_settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = setting_repo()->save([
        'name' => $_POST['name'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'email' => $_POST['email'] ?? '',
        'address' => $_POST['address'] ?? '',
        'header' => $s['header'] ?? '',
        'footer' => $s['footer'] ?? '',
        'logo_text' => $s['logo_text'] ?? 'HLP',
    ], current_user()['organization_id'] ?? 'ORG-001');
    $message = $ok ? flash_success('Lab profile saved.') : flash_error('Could not save.');
    $s = mock('mock_lab_settings');
}

$content = page_header('Lab Profile', 'Organization-level branding and contact.');
$content .= $message;
$content .= card(
    '<form method="post" class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">' .
    form_field('Lab name', 'name', 'text', $s['name']) .
    form_field('Phone', 'phone', 'text', $s['phone']) .
    form_field('Email', 'email', 'text', $s['email']) .
    form_field('Address', 'address', 'text', $s['address']) .
    '<div class="sm:col-span-2">' . btn_submit('Save profile') . '</div></form>'
);

render_page('Lab Profile', 'main-lab', 'settings-profile', $content);
