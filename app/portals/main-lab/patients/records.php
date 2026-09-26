<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [];
foreach (mock('mock_patients') as $p) {
    $rows[] = [e($p['id']), e($p['name']), e($p['phone']), btn_secondary('/portals/main-lab/patients/history.php', 'History')];
}

$content = page_header('Patient Records', 'Search patients across organization.');
$content .= card(data_table(['ID', 'Name', 'Phone', 'History'], $rows), 'overflow-hidden');

render_page('Patient Records', 'main-lab', 'records', $content);
