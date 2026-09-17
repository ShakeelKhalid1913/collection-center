<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$settings = mock('mock_lab_settings');

$content = page_header('Settings', 'Collection center profile (branch-scoped).');
$content .= card(
    '<form class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">' .
    form_field('Center name', 'center_name', 'text', 'Gulberg Collection Point') .
    form_field('Branch code', 'branch_code', 'text', 'CC-01') .
    form_field('Phone', 'phone', 'text', $settings['phone'] ?? '') .
    form_field('Address', 'address', 'text', $settings['address'] ?? '') .
    '<div class="sm:col-span-2">' . btn_submit('Save settings') . '</div>' .
    '</form>'
);

render_page('Settings', 'collection-center', 'settings', $content);
