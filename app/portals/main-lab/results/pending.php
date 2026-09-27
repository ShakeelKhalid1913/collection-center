<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [];
foreach (mock('mock_results_pending') as $r) {
    $actions = '<a href="/portals/main-lab/results/entry.php?lab_no=' . urlencode($r['lab_no']) . '" class="btn btn-primary text-xs mr-2"><i class="fa-solid fa-pen-to-square mr-1"></i> Enter Results</a>';
    $actions .= '<a href="/portals/main-lab/reports/preview.php?lab_no=' . urlencode($r['lab_no']) . '" class="btn btn-secondary text-xs"><i class="fa-solid fa-eye mr-1"></i> View Report</a>';
    $rows[] = [
        e($r['lab_no']),
        e($r['patient']),
        e($r['test']),
        e($r['due'] ?? 'Today'),
        $actions,
    ];
}

$content = page_header('Pending Results', 'Tests waiting for result values.');
$content .= card(data_table(['Lab No', 'Patient', 'Test', 'Due', 'Action'], $rows), 'overflow-hidden');

render_page('Pending Results', 'main-lab', 'results-pending', $content);
