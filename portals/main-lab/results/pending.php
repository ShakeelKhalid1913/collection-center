<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [];
foreach (mock('mock_results_pending') as $r) {
    $rows[] = [e($r['lab_no']), e($r['patient']), e($r['test']), e($r['due']), btn_primary('/portals/main-lab/results/entry.php', 'Enter')];
}

$content = page_header('Pending Results', 'Tests waiting for result values.');
$content .= card(data_table(['Lab No', 'Patient', 'Test', 'Due', 'Action'], $rows), 'overflow-hidden');

render_page('Pending Results', 'main-lab', 'results-pending', $content);
