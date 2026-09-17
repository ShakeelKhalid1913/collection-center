<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$rows = [];
foreach (mock('mock_collection_centers') as $c) {
    $rows[] = [e($c['code']), e($c['name']), e((string) $c['patients_today']), status_badge('active')];
}

$content = page_header('Collection Centers', 'Branches linked to main lab.');
$content .= card(data_table(['Code', 'Name', "Today's patients", 'Status'], $rows), 'overflow-hidden');

render_page('Collection Centers', 'main-lab', 'collection-centers', $content);
