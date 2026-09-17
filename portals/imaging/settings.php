<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$content = page_header('Settings', 'Imaging department defaults.');
$content .= card(
    '<form class="grid gap-4 p-4 sm:max-w-lg sm:p-6">' .
    form_field('Department name', 'dept', 'text', 'Radiology & CT') .
    form_field('Default report footer', 'footer', 'text', 'Digital radiology report — not for medico-legal use without signature.') .
    btn_submit('Save') . '</form>'
);

render_page('Settings', 'imaging', 'settings', $content);
