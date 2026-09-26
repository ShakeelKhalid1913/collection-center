<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';
$s = branding_settings($orgId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = setting_repo()->save([
        'name' => $_POST['name'] ?? $s['name'],
        'phone' => $_POST['phone'] ?? $s['phone'],
        'email' => $_POST['email'] ?? $s['email'],
        'address' => $_POST['address'] ?? $s['address'],
        'header' => $_POST['header'] ?? '',
        'footer' => $_POST['footer'] ?? '',
        'logo_text' => $_POST['logo'] ?? 'HLP',
        'bill_header' => $_POST['bill_header'] ?? '',
        'bill_footer' => $_POST['bill_footer'] ?? '',
    ], $orgId);
    $message = $ok ? flash_success('Header / footer branding saved for bills and reports.') : flash_error('Could not save.');
    $s = branding_settings($orgId);
}

$content = page_header('Header / Footer Branding', 'Per-lab letterhead for printable bills and pathology reports.');
$content .= $message;
$content .= card(
    '<form method="post" class="space-y-4 p-4 sm:p-6">' .
    form_field('Lab name', 'name', 'text', $s['name']) .
    form_field('Address', 'address', 'text', $s['address']) .
    form_field('Phone', 'phone', 'text', $s['phone']) .
    form_field('Email', 'email', 'email', $s['email']) .
    form_field('Report header', 'header', 'text', $s['header']) .
    form_field('Report footer', 'footer', 'text', $s['footer']) .
    form_field('Bill header (optional)', 'bill_header', 'text', $s['bill_header'], '', true) .
    form_field('Bill footer (optional)', 'bill_footer', 'text', $s['bill_footer'], '', true) .
    form_field('Logo text', 'logo', 'text', $s['logo_text']) .
    '<div class="flex flex-wrap gap-2">' .
    btn_submit('Save branding') .
    btn_secondary('/portals/main-lab/reports/preview.php', 'Preview sample report') .
    '</div></form>'
);

render_page('Report Template', 'main-lab', 'settings-report', $content);
