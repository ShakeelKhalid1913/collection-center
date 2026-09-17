<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$rows = [
    [e('admin@citylab.pk'), e('Super Admin'), e('Admin'), e('All branches'), status_badge('active')],
    [e('lab@citylab.pk'), e('Sara Malik'), e('Main Lab'), e('Main Lab'), status_badge('active')],
    [e('cc@citylab.pk'), e('Ahmed Raza'), e('Collection Center'), e('Gulberg CC-01'), status_badge('active')],
    [e('rad@citylab.pk'), e('Dr. Imran'), e('Imaging'), e('Radiology'), status_badge('active')],
];

$content = page_header('Users & Permissions', 'Portal access by role.');
$content .= card(data_table(['Email', 'Name', 'Portal role', 'Branch', 'Status'], $rows), 'overflow-hidden');

render_page('Users', 'admin', 'users', $content);
