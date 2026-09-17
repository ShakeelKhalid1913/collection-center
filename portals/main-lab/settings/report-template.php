<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$s = mock('mock_lab_settings');

$content = page_header('Report Header / Footer', 'Per-lab report template — not hardcoded in PDF engine.');
$content .= card(
    '<form class="space-y-4 p-4 sm:p-6">' .
    form_field('Header line', 'header', 'text', $s['header']) .
    form_field('Footer note', 'footer', 'text', $s['footer']) .
    form_field('Logo text (placeholder)', 'logo', 'text', $s['logo_text']) .
    btn_secondary('/portals/main-lab/reports/preview.php', 'Preview sample report') .
    '</form>'
);

render_page('Report Template', 'main-lab', 'settings-report', $content);
