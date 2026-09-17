<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [];
foreach (mock('mock_samples') as $s) {
    if ($s['status'] === 'completed') {
        continue;
    }
    $rows[] = [e($s['id']), e($s['lab_no']), e($s['patient']), e($s['sample']), status_badge($s['status']), e($s['received_at']), link_action('Mark received')];
}

$content = page_header('Pending Samples', 'Samples awaiting receipt or processing.');
$content .= card(data_table(['Sample ID', 'Lab No', 'Patient', 'Type', 'Status', 'Received', 'Action'], $rows), 'overflow-hidden');

render_page('Pending Samples', 'main-lab', 'samples-pending', $content);
