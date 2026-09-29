<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$content = page_header('Dashboard', 'Laboratory workflow — register, result entry, reports, and catalog.');
$content .= '<div class="stat-grid stat-grid--5">';
$content .= stat_card('Samples Received', (string)result_repo()->countSamplesToday(), 'Today', 'blue', 'fa-solid fa-inbox');
$content .= stat_card('Pending Results', (string)result_repo()->countPendingResults(), 'Awaiting entry', 'amber', 'fa-solid fa-pen');
$content .= stat_card('Pending Verification', (string)result_repo()->countPendingVerification(), 'Pathologist queue', 'violet', 'fa-solid fa-user-check');
$content .= stat_card('Completed Reports', (string)result_repo()->countVerifiedToday(), 'Verified today', 'teal', 'fa-solid fa-circle-check');
$content .= stat_card('Critical Results', (string)result_repo()->countCritical(), 'Requires callback', 'rose', 'fa-solid fa-triangle-exclamation');
$content .= '</div>';

$content .= '<div class="mt-4 grid gap-4 lg:grid-cols-2">';
$content .= card(
    panel_head('Primary workflow') .
    '<ol class="space-y-2 p-4 text-[13px] text-gray-800">' .
    '<li><strong>1. Register Patient</strong> — create visit / assign tests</li>' .
    '<li><strong>2. Result Entry</strong> — enter lab values</li>' .
    '<li><strong>3. Report History</strong> — PDF / WhatsApp reports</li>' .
    '</ol>'
);
$content .= card(
    panel_head('Quick actions') .
    '<div class="flex flex-wrap gap-2 p-4">' .
    btn_primary('/portals/main-lab/patients/register.php', 'Register Patient', 'fa-solid fa-user-plus') .
    btn_secondary('/portals/main-lab/results/entry.php', 'Result Entry', 'fa-solid fa-keyboard') .
    btn_secondary('/portals/main-lab/reports/history.php', 'Report History', 'fa-solid fa-folder-open') .
    btn_secondary('/portals/main-lab/archive.php', 'All Dates History', 'fa-solid fa-clock-rotate-left') .
    '</div>'
);
$content .= '</div>';

render_page('Dashboard', 'main-lab', 'dashboard', $content);
