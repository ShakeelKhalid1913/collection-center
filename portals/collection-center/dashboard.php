<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$entries = mock('mock_lab_entries');
$rows = [];
foreach ($entries as $e) {
    $remaining = max(0, $e['amount'] - $e['paid']);
    $payStatus = $remaining === 0 ? 'paid' : 'partial';
    $rows[] = [
        e($e['lab_no']),
        e($e['patient']),
        e($e['tests']),
        status_badge($e['status']),
        e(format_money((float) $e['amount'])),
        btn_secondary('/portals/collection-center/lab-entries/history.php', 'View'),
    ];
}

$content = page_header(
    'Dashboard',
    'Collection center overview for today.',
    btn_primary('/portals/collection-center/lab-entries/new.php', 'New lab entry')
);

$content .= '<div class="stat-grid stat-grid--5">';
$content .= stat_card("Today's Patients", '32', '+6 vs yesterday', 'teal', 'fa-solid fa-user-injured');
$content .= stat_card("Today's Tests", '78', 'Across all entries', 'blue', 'fa-solid fa-flask');
$content .= stat_card('Pending Samples', '14', 'Awaiting collection', 'amber', 'fa-solid fa-vial');
$content .= stat_card('Pending Reports', '9', 'Main lab processing', 'violet', 'fa-solid fa-file-medical');
$content .= stat_card("Today's Collection", format_money(42500), 'Paid + partial', 'rose', 'fa-solid fa-coins');
$content .= '</div>';

$content .= '<div class="mt-4">' . card(
    panel_head('Recent entries') .
    data_table(['Lab No', 'Patient', 'Tests', 'Status', 'Amount', 'Action'], $rows),
    'overflow-hidden'
) . '</div>';

render_page('Dashboard', 'collection-center', 'dashboard', $content);
