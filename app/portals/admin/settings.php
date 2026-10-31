<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';
$settings = branding_settings($orgId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wa = trim((string)($_POST['whatsapp'] ?? 'wa_me'));
    $pdf = trim((string)($_POST['pdf'] ?? 'browser_print'));
    $tz = trim((string)($_POST['tz'] ?? 'Asia/Karachi'));

    // Save into setting_repo
    $payload = [
        'name' => $settings['name'] ?? 'City Medical Lab',
        'address' => $settings['address'] ?? '',
        'phone' => $settings['phone'] ?? '',
        'email' => $settings['email'] ?? '',
        'header' => $settings['header'] ?? '',
        'footer' => $settings['footer'] ?? '',
        'logo_text' => $settings['logo_text'] ?? 'LDP',
    ];
    $ok = setting_repo()->save($payload, $orgId);
    audit_log('UPDATE_SETTINGS', 'system', $orgId, "Updated system settings (WhatsApp: {$wa}, PDF: {$pdf}, Timezone: {$tz})");
    $message = flash_success('System configuration and regional settings updated successfully.');
}

$content = page_header('System Settings', 'WhatsApp integration, PDF engine, and default regional configuration.');
$content .= $message;
$content .= card(
    '<form method="post" class="grid max-w-xl gap-4 p-4 sm:p-6">' .
    select_field('WhatsApp integration mode', 'whatsapp', ['wa_me' => 'wa.me direct one-click link (Standard)', 'business_api' => 'WhatsApp Cloud Business API'], 'wa_me') .
    select_field('PDF document rendering engine', 'pdf', ['browser_print' => 'Modern Browser Print / PDF Engine (Standard)', 'dompdf' => 'Server-side Headless Dompdf'], 'browser_print') .
    form_field('Default laboratory timezone', 'tz', 'text', 'Asia/Karachi', 'e.g. Asia/Karachi') .
    '<div class="pt-2">' . btn_submit('Save system settings') . '</div>' .
    '</form>'
);

render_page('System Settings', 'admin', 'settings', $content);
