<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$rows = [];
foreach (mock('mock_patients') as $p) {
    $rows[] = [e($p['id']), e($p['name']), e($p['phone']), btn_primary('/portals/imaging/new-scan.php', 'New scan')];
}

$content = page_header('Patients', 'Register or search — same patient IDs as pathology.');
$content .= card(data_table(['ID', 'Name', 'Phone', 'Action'], $rows), 'overflow-hidden');

render_page('Patients', 'imaging', 'patients', $content);
