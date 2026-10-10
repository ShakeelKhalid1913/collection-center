<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';
$s = branding_settings($orgId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = save_branding_request($orgId);
    $message = $result['ok']
        ? flash_success($result['message'])
        : flash_error($result['message']);
    $s = branding_settings($orgId);
}

$content = page_header(
    'Header / Footer',
    'Lab letterhead for bills and reports (same settings as Admin → Client Branding).'
);
$content .= $message;
$content .= '<div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">'
    . '<strong>Same branding for everyone.</strong> Admin uses this when setting up a new client. You can update it here for day-to-day changes — one letterhead drives all receipts and lab reports.'
    . '</div>';
$content .= card(
    '<form method="post" enctype="multipart/form-data" class="space-y-5 p-4 sm:p-6">' .
    '<div class="grid gap-4 sm:grid-cols-2">' .
    form_field('Lab / clinic name', 'name', 'text', $s['name']) .
    form_field('Logo text (placeholder)', 'logo', 'text', $s['logo_text']) .
    form_field('Phone', 'phone', 'text', $s['phone']) .
    form_field('Email', 'email', 'email', $s['email']) .
    '</div>' .
    form_field('Address (shown on report & bill header)', 'address', 'text', $s['address']) .
    branding_header_image_field($s) .
    '<hr class="border-slate-200">' .
    '<p class="text-sm font-semibold text-slate-800">Lab report letterhead</p>' .
    form_field('Report header line', 'header', 'text', $s['header'], 'e.g. Quality Diagnostics — Pathology Reports') .
    form_field('Report footer', 'footer', 'text', $s['footer'], 'e.g. Get well soon. Thank you.') .
    '<hr class="border-slate-200">' .
    '<p class="text-sm font-semibold text-slate-800">Cash bill / receipt letterhead</p>' .
    '<p class="text-xs text-slate-500">Leave blank to reuse report header/footer.</p>' .
    form_field('Bill header line', 'bill_header', 'text', $s['bill_header'], 'Optional distinct bill header', true) .
    '<hr class="border-slate-200">' .
    '<p class="text-sm font-semibold text-slate-800">Typography &amp; Document Fonts (Below Patient Info)</p>' .
    select_field('Lab Report Font (Below Patient Info)', 'report_font', array_combine(array_keys(supported_document_fonts()), array_column(supported_document_fonts(), 'name')), $s['report_font'] ?? 'times_bold_italic') .
    select_field('Billing / Receipt Font (Below Patient Info)', 'bill_font', array_combine(array_keys(supported_document_fonts()), array_column(supported_document_fonts(), 'name')), $s['bill_font'] ?? 'times_bold_italic') .
    '<div class="flex flex-wrap gap-2">' .
    btn_submit('Save branding') .
    btn_secondary('/portals/collection-center/reports/preview.php', 'Preview report') .
    btn_secondary('/portals/collection-center/receipts.php', 'Preview receipt') .
    '</div></form>'
);

render_page('Lab Branding', 'collection-center', 'branding', $content);
