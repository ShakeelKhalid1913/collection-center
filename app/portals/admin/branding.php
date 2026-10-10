<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

// Admin uses same branding store — for client customization per org
$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';
$s = branding_settings($orgId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = save_branding_request($orgId);
    $message = $result['ok'] ? flash_success($result['message']) : flash_error($result['message']);
    $s = branding_settings($orgId);
}

$content = page_header(
    'Client Branding',
    'Primary place to set a lab’s letterhead when onboarding a client. Same data appears on all bills & reports.'
);
$content .= $message;
$content .= '<div class="mb-4 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">'
    . '<strong>Note:</strong> There is only <em>one</em> letterhead per lab. Staff can also tweak it under Collection Center → Header / Footer or Laboratory → Header / Footer — it is the same settings, not a separate copy.'
    . '</div>';
$content .= card(
    '<form method="post" enctype="multipart/form-data" class="space-y-4 p-4 sm:p-6">' .
    form_field('Lab / client name', 'name', 'text', $s['name']) .
    form_field('Address', 'address', 'text', $s['address']) .
    form_field('Phone', 'phone', 'text', $s['phone']) .
    form_field('Email', 'email', 'email', $s['email']) .
    branding_header_image_field($s) .
    form_field('Report header', 'header', 'text', $s['header']) .
    form_field('Report footer', 'footer', 'text', $s['footer']) .
    form_field('Bill header', 'bill_header', 'text', $s['bill_header'], '', true) .
    form_field('Bill footer', 'bill_footer', 'text', $s['bill_footer'], '', true) .
    form_field('Logo text', 'logo', 'text', $s['logo_text']) .
    select_field('Lab Report Font (Below Patient Info)', 'report_font', array_combine(array_keys(supported_document_fonts()), array_column(supported_document_fonts(), 'name')), $s['report_font'] ?? 'times_bold_italic') .
    select_field('Billing / Receipt Font (Below Patient Info)', 'bill_font', array_combine(array_keys(supported_document_fonts()), array_column(supported_document_fonts(), 'name')), $s['bill_font'] ?? 'times_bold_italic') .
    btn_submit('Save client branding') .
    '</form>'
);

render_page('Client Branding', 'admin', 'branding', $content);
