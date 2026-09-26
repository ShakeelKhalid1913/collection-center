<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
$s = mock('mock_lab_settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = setting_repo()->save([
        'name' => $s['name'] ?? '',
        'phone' => $s['phone'] ?? '',
        'email' => $s['email'] ?? '',
        'address' => $s['address'] ?? '',
        'header' => $_POST['header'] ?? '',
        'footer' => $_POST['footer'] ?? '',
        'logo_text' => $_POST['logo'] ?? 'HLP',
    ], current_user()['organization_id'] ?? 'ORG-001');
    $message = $ok ? flash_success('Report template saved.') : flash_error('Could not save.');
    $s = mock('mock_lab_settings');
}

$content = page_header('Report Header / Footer', 'Per-lab report template — used on print preview.');
$content .= $message;
$content .= card(
    '<form method="post" class="space-y-4 p-4 sm:p-6">' .
    form_field('Header line', 'header', 'text', $s['header']) .
    form_field('Footer note', 'footer', 'text', $s['footer']) .
    form_field('Logo text (placeholder)', 'logo', 'text', $s['logo_text']) .
    '<div class="flex flex-wrap gap-2">' .
    btn_submit('Save template') .
    btn_secondary('/portals/main-lab/reports/preview.php', 'Preview sample report') .
    '</div></form>'
);

render_page('Report Template', 'main-lab', 'settings-report', $content);
