<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$content = page_header('Generate Report', 'Select verified lab entry to render branded PDF.');
$content .= card(
    '<form class="grid max-w-xl gap-4 p-4 sm:p-6">' .
    form_field('Lab number', 'lab_no', 'text', 'L-2026-0891') .
    select_field('Template', 'template', ['default' => 'Default pathology template', 'cbc' => 'CBC panel']) .
    btn_primary('/portals/main-lab/reports/preview.php', 'Preview report') .
    '</form>'
);

render_page('Generate Report', 'main-lab', 'reports-generate', $content);
