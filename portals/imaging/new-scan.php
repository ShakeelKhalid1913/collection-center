<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$content = page_header('New Scan', 'Create imaging entry and assign radiologist.');
$content .= card(
    '<form class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">' .
    select_field('Patient', 'patient', array_column(mock('mock_patients'), 'name', 'id')) .
    select_field('Modality', 'modality', ['xray' => 'X-Ray', 'ct' => 'CT Scan']) .
    select_field('Study', 'study', ['cxr' => 'Chest PA', 'brain' => 'Brain Plain CT']) .
    select_field('Radiologist', 'rad', array_column(mock('mock_doctors'), 'name', 'id')) .
    '<div class="sm:col-span-2">' . btn_submit('Create scan entry') . '</div></form>'
);

render_page('New Scan', 'imaging', 'new-scan', $content);
