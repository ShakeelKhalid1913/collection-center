<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$content = page_header('Dashboard', 'X-Ray / CT workflow — shared patient database.');
$content .= '<div class="stat-grid stat-grid--4">';
$content .= stat_card("Today's Patients", '11', 'Imaging desk', 'teal', 'fa-solid fa-users');
$content .= stat_card('Pending Scans', '5', 'Waiting room / schedule', 'amber', 'fa-solid fa-x-ray');
$content .= stat_card('Pending Reports', '3', 'Radiologist queue', 'violet', 'fa-solid fa-file-medical');
$content .= stat_card('Completed Reports', '8', 'Ready to deliver', 'blue', 'fa-solid fa-circle-check');
$content .= '</div>';

render_page('Dashboard', 'imaging', 'dashboard', $content);
