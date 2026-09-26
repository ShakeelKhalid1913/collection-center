<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';
$s = branding_settings($orgId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = setting_repo()->save([
        'name' => $_POST['name'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'email' => $_POST['email'] ?? '',
        'address' => $_POST['address'] ?? '',
        'header' => $_POST['header'] ?? '',
        'footer' => $_POST['footer'] ?? '',
        'logo_text' => $_POST['logo'] ?? 'HLP',
        'bill_header' => $_POST['bill_header'] ?? '',
        'bill_footer' => $_POST['bill_footer'] ?? '',
    ], $orgId);
    $message = $ok
        ? flash_success('Branding saved — used on bills and lab reports.')
        : flash_error('Could not save branding. If columns are missing, re-run /database/setup.php once.');
    $s = branding_settings($orgId);
}

$content = page_header(
    'Lab Branding',
    'Per-client letterhead: custom header & footer for printable bills and lab reports. Configure once per lab.'
);
$content .= $message;
$content .= card(
    '<form method="post" class="space-y-5 p-4 sm:p-6">' .
    '<div class="grid gap-4 sm:grid-cols-2">' .
    form_field('Lab / clinic name', 'name', 'text', $s['name']) .
    form_field('Logo text (placeholder)', 'logo', 'text', $s['logo_text']) .
    form_field('Phone', 'phone', 'text', $s['phone']) .
    form_field('Email', 'email', 'email', $s['email']) .
    '</div>' .
    form_field('Address (shown on report & bill header)', 'address', 'text', $s['address']) .
    '<hr class="border-slate-200">' .
    '<p class="text-sm font-semibold text-slate-800">Lab report letterhead</p>' .
    form_field('Report header line', 'header', 'text', $s['header'], 'e.g. Quality Diagnostics — Pathology Reports') .
    form_field('Report footer', 'footer', 'text', $s['footer'], 'e.g. Electronically verified. Call reception for queries.') .
    '<hr class="border-slate-200">' .
    '<p class="text-sm font-semibold text-slate-800">Cash bill / receipt letterhead</p>' .
    '<p class="text-xs text-slate-500">Leave blank to reuse report header/footer.</p>' .
    form_field('Bill header line', 'bill_header', 'text', $s['bill_header'], 'Optional distinct bill header', true) .
    form_field('Bill footer', 'bill_footer', 'text', $s['bill_footer'], 'Optional distinct bill footer', true) .
    '<div class="flex flex-wrap gap-2">' .
    btn_submit('Save branding') .
    btn_secondary('/portals/collection-center/reports/preview.php', 'Preview report') .
    btn_secondary('/portals/collection-center/receipts.php', 'Preview receipt') .
    '</div></form>'
);

render_page('Lab Branding', 'collection-center', 'branding', $content);
