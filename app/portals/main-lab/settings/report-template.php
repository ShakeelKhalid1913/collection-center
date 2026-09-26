<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';
$s = branding_settings($orgId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = save_branding_request($orgId);
    $message = $result['ok'] ? flash_success($result['message']) : flash_error($result['message']);
    $s = branding_settings($orgId);
}

$content = page_header(
    'Header / Footer',
    'Lab letterhead for bills and reports (same settings as Admin → Client Branding).'
);
$content .= $message;
$content .= '<div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">'
    . '<strong>Same branding for everyone.</strong> Not a separate staff letterhead — edits here update the shared lab header/footer used on all printouts.'
    . '</div>';
$content .= card(
    '<form method="post" enctype="multipart/form-data" class="space-y-4 p-4 sm:p-6">' .
    form_field('Lab name', 'name', 'text', $s['name']) .
    form_field('Address', 'address', 'text', $s['address']) .
    form_field('Phone', 'phone', 'text', $s['phone']) .
    form_field('Email', 'email', 'email', $s['email']) .
    branding_header_image_field($s) .
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
