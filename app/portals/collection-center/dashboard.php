<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$entries = mock('mock_lab_entries');
$rows = [];
foreach (array_slice($entries, 0, 12) as $e) {
    $labQ = urlencode($e['lab_no']);
    $rows[] = [
        e($e['lab_no']),
        e($e['patient']),
        e($e['tests']),
        status_badge($e['status']),
        e(format_money((float) $e['amount'])),
        '<div class="flex flex-wrap gap-2">' .
        btn_secondary('/portals/collection-center/receipts.php?lab_no=' . $labQ, 'Receipt') .
        btn_secondary('/portals/collection-center/reports/preview.php?lab_no=' . $labQ, 'Report') .
        '</div>',
    ];
}

$patientsToday = lab_repo()->countPatientsToday();
$testsToday = lab_repo()->countToday();
$pendingSamples = lab_repo()->countPendingSamples();
$pendingReports = lab_repo()->countByStatuses(['pending', 'collected', 'processing', 'received']);
$collection = lab_repo()->sumPaidToday();

$content = page_header(
    'Dashboard',
    'Collection center overview for today.',
    btn_primary('/portals/collection-center/lab-entries/new.php', 'New lab entry')
);

$content .= '<div class="stat-grid stat-grid--5">';
$content .= stat_card("Today's Patients", (string)$patientsToday, 'Distinct patients', 'teal', 'fa-solid fa-user-injured');
$content .= stat_card("Today's Entries", (string)$testsToday, 'Lab orders today', 'blue', 'fa-solid fa-flask');
$content .= stat_card('Pending Samples', (string)$pendingSamples, 'Awaiting collection', 'amber', 'fa-solid fa-vial');
$content .= stat_card('Open Orders', (string)$pendingReports, 'Not yet verified', 'violet', 'fa-solid fa-file-medical');
$content .= stat_card("Today's Collection", format_money($collection), 'Paid amount', 'rose', 'fa-solid fa-coins');
$content .= '</div>';

$content .= '<div class="mt-4">' . card(
    panel_head('Recent entries') .
    data_table(['Lab No', 'Patient', 'Tests', 'Status', 'Amount', 'Actions'], $rows),
    'overflow-hidden'
) . '</div>';

render_page('Dashboard', 'collection-center', 'dashboard', $content);
