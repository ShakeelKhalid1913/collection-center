<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$content = page_header('Dashboard', 'Laboratory workflow — samples, results, verification, reports.');
$content .= '<div class="stat-grid stat-grid--5">';
$content .= stat_card('Samples Received', (string)result_repo()->countSamplesToday(), 'Today', 'blue', 'fa-solid fa-inbox');
$content .= stat_card('Pending Results', (string)result_repo()->countPendingResults(), 'Awaiting entry', 'amber', 'fa-solid fa-pen');
$content .= stat_card('Pending Verification', (string)result_repo()->countPendingVerification(), 'Pathologist queue', 'violet', 'fa-solid fa-user-check');
$content .= stat_card('Completed Reports', (string)result_repo()->countVerifiedToday(), 'Verified today', 'teal', 'fa-solid fa-circle-check');
$content .= stat_card('Critical Results', (string)result_repo()->countCritical(), 'Requires callback', 'rose', 'fa-solid fa-triangle-exclamation');
$content .= '</div>';

$content .= '<div class="mt-4 grid gap-4 lg:grid-cols-2">';
$content .= card(
    panel_head('Workflow') .
    '<ol class="space-y-2 p-4 text-[13px] text-gray-800">' .
    '<li><strong>Collection Center</strong> → Patient + tests</li>' .
    '<li><strong>Sample received</strong> at main lab</li>' .
    '<li><strong>Results entry</strong> by technician</li>' .
    '<li><strong>Verification</strong> by pathologist</li>' .
    '<li><strong>Report</strong> → Print / PDF / WhatsApp</li>' .
    '</ol>'
);
$content .= card(
    panel_head('Quick actions') .
    '<div class="flex flex-wrap gap-2 p-4">' .
    btn_primary('/portals/main-lab/results/entry.php', 'Results entry') .
    btn_secondary('/portals/main-lab/samples/pending.php', 'Pending samples') .
    btn_secondary('/portals/main-lab/reports/generate.php', 'Generate report') .
    '</div>'
);
$content .= '</div>';

render_page('Dashboard', 'main-lab', 'dashboard', $content);
