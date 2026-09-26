<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$rows = [];
foreach (user_repo()->getAll() as $u) {
    $portal = portal_from_db((string)($u['portal'] ?? ''));
    $rows[] = [
        e($u['email']),
        e($u['name']),
        e(PORTALS[$portal]['label'] ?? $portal),
        e($u['branch_name'] ?? $u['branch_id'] ?? '—'),
        status_badge(!empty($u['is_active']) ? 'active' : 'pending'),
    ];
}

$content = page_header('Users & Permissions', 'Portal access is locked per account — cross-portal URLs return 403.');
$content .= card(data_table(['Email', 'Name', 'Portal role', 'Branch', 'Status'], $rows), 'overflow-hidden');

render_page('Users', 'admin', 'users', $content);
