<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [];
foreach (mock('mock_lab_entries') as $e) {
    $rows[] = [e($e['lab_no']), e($e['tests']), status_badge($e['status']), e(format_date($e['date']))];
}

$content = page_header('Patient History', 'Ayesha Khan · P-10482');
$content .= card(data_table(['Lab No', 'Tests', 'Status', 'Date'], $rows), 'overflow-hidden');

render_page('Patient History', 'main-lab', 'history', $content);
