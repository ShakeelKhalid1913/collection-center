<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$content = page_header(
    'Diagnostic Center',
    'X-Ray · CT Scan · Ultrasound · ECG — shared patient database with Laboratory & Collection Center.'
);
$content .= '<div class="stat-grid stat-grid--4">';
$content .= stat_card("Today's Studies", (string)result_repo()->countImagingToday(), 'All modalities', 'teal', 'fa-solid fa-users');
$content .= stat_card('Pending Studies', (string)result_repo()->countImagingPending(), 'X-Ray / CT / US / ECG', 'amber', 'fa-solid fa-x-ray');
$content .= stat_card('Pending Reports', (string)result_repo()->countImagingPending(), 'Doctor queue', 'violet', 'fa-solid fa-file-medical');
$content .= stat_card('Completed Reports', (string)result_repo()->countImagingReported(), 'Ready to deliver', 'blue', 'fa-solid fa-circle-check');
$content .= '</div>';

$content .= '<div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">';
foreach (['X-Ray' => 'fa-x-ray', 'CT Scan' => 'fa-laptop-medical', 'Ultrasound' => 'fa-wave-square', 'ECG' => 'fa-heart-pulse'] as $label => $icon) {
    $content .= card(
        '<div class="p-4 text-center">' .
        '<i class="fa-solid ' . $icon . ' text-2xl text-teal-700"></i>' .
        '<p class="mt-2 font-semibold text-slate-900">' . e($label) . '</p>' .
        '<p class="text-xs text-slate-500">Under Diagnostic Center</p>' .
        '</div>'
    );
}
$content .= '</div>';

$content .= '<div class="mt-5 flex flex-wrap gap-2">' .
    btn_primary('/portals/imaging/new-scan.php', 'New study', 'fa-solid fa-file-circle-plus') .
    btn_secondary('/portals/imaging/pending-scans.php', 'Pending studies', 'fa-solid fa-clock') .
    '</div>';

render_page('Dashboard', 'imaging', 'dashboard', $content);
