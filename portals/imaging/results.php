<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$content = page_header('Results / Findings', 'XR-2026-120 · Hassan Raza · Chest PA');
$content .= card(
    '<div class="space-y-4 p-4 sm:p-6">' .
    select_field('Radiologist', 'rad', array_column(mock('mock_doctors'), 'name', 'id'), 'D-01') .
    '<div><label class="field-label">Findings</label><textarea rows="6" class="field">Heart size normal. Lungs clear. No pleural effusion.</textarea></div>' .
    '<div><label class="field-label">Impression</label><textarea rows="3" class="field">Normal chest radiograph.</textarea></div>' .
    btn_primary('/portals/imaging/reports.php', 'Generate report') .
    '</div>'
);

render_page('Results', 'imaging', 'results', $content);
