<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [];
foreach (user_repo()->getAll() as $u) {
    $portal = portal_from_db((string)($u['portal'] ?? ''));
    $rows[] = [
        e($u['id']),
        e($u['name']),
        e($u['role']),
        e(PORTALS[$portal]['label'] ?? $portal),
        e($u['branch_name'] ?? $u['branch_id'] ?? '—'),
        status_badge(!empty($u['is_active']) ? 'active' : 'pending'),
    ];
}

$content = page_header('Users', 'Staff accounts from MariaDB (portal-locked).');
$content .= card(data_table(['ID', 'Name', 'Role', 'Portal', 'Branch', 'Status'], $rows), 'overflow-hidden');

render_page('Users', 'main-lab', 'settings-users', $content);
