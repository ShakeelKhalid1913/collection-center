<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [
    [e('U-01'), e('Sara Malik'), e('Technician'), e('Main Lab'), status_badge('active')],
    [e('U-02'), e('Dr. Imran'), e('Pathologist'), e('Main Lab'), status_badge('active')],
    [e('U-03'), e('Ahmed CC'), e('Reception'), e('Gulberg CC'), status_badge('active')],
];

$content = page_header('Users', 'Role and branch assignment (MySQL + auth later).');
$content .= card(data_table(['ID', 'Name', 'Role', 'Branch', 'Status'], $rows), 'overflow-hidden');

render_page('Users', 'main-lab', 'settings-users', $content);
