<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$rows = [];
foreach (mock('mock_lab_entries') as $e) {
    if (!in_array($e['status'], ['completed', 'critical'], true)) {
        continue;
    }
    $rows[] = [
        e($e['lab_no']),
        e($e['patient']),
        e($e['tests']),
        e($e['doctor'] ?? '—'),
        status_badge($e['status']),
        e($e['branch'] ?? '—'),
        e(format_date($e['date'])),
        '<div class="flex flex-wrap gap-2">' .
        btn_secondary('/portals/main-lab/reports/preview.php', 'Open') .
        btn_secondary('/portals/collection-center/receipts.php', 'Receipt') .
        '</div>',
    ];
}

$filters = filter_bar([
    form_field('Search', 'q', 'search', null, 'Lab no, patient…'),
    select_field('Status', 'status', ['' => 'All', 'completed' => 'Completed', 'critical' => 'Critical']),
    select_field('Date', 'date', ['' => 'Any', 'today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days']),
    select_field('Branch', 'branch', ['' => 'All', 'CC-01' => 'CC-01', 'CC-02' => 'CC-02']),
    select_field('Doctor', 'doctor', ['' => 'All', 'walkin' => 'Walk-in', 'referred' => 'Referred']),
    select_field('Delivery', 'delivery', ['' => 'Any', 'printed' => 'Printed', 'whatsapp' => 'WhatsApp sent', 'pending' => 'Not sent']),
]);

$content = page_header('Reports', 'Completed / critical reports — filter then print or WhatsApp.');
$content .= card(
    $filters .
    data_table(['Lab No', 'Patient', 'Tests', 'Doctor', 'Status', 'Branch', 'Date', 'Actions'], $rows),
    'overflow-hidden'
);

render_page('Reports', 'collection-center', 'reports', $content);
