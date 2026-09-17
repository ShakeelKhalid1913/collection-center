<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [];
foreach (mock('mock_samples') as $s) {
    $rows[] = [e($s['id']), e($s['lab_no']), e($s['patient']), status_badge($s['status']), e($s['received_at'])];
}

$content = page_header('Sample Tracking', 'End-to-end sample status.');
$content .= card(data_table(['Sample ID', 'Lab No', 'Patient', 'Status', 'Timeline'], $rows), 'overflow-hidden');

render_page('Sample Tracking', 'main-lab', 'samples-tracking', $content);
