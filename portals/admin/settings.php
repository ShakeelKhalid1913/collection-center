<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$content = page_header('System Settings', 'WhatsApp provider, PDF engine, and defaults.');
$content .= card(
    '<form class="grid max-w-xl gap-4 p-4 sm:p-6">' .
    select_field('WhatsApp integration', 'whatsapp', ['wa_me' => 'wa.me link (MVP)', 'business_api' => 'WhatsApp Business API (later)'], 'wa_me') .
    select_field('PDF engine', 'pdf', ['browser_print' => 'Browser print → PDF (MVP)', 'dompdf' => 'Dompdf (later)'], 'browser_print') .
    form_field('Default timezone', 'tz', 'text', 'Asia/Karachi') .
    btn_submit('Save') . '</form>'
);

render_page('System Settings', 'admin', 'settings', $content);
