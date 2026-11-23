<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

require_auth();

if (!is_admin_user()) {
    http_response_code(403);
    $content = card(
        '<div class="p-8 text-center text-rose-600 font-bold">' .
        '<i class="fa-solid fa-shield-halved text-4xl mb-3 text-rose-500 block"></i>' .
        'Access Denied: You do not have administrator permissions to view staff accounts.' .
        '</div>'
    );
    render_page('Access Denied', 'main-lab', 'dashboard', $content);
    exit;
}

$orgId = current_organization_id();
$orgName = current_organization_name();
$isSuper = is_super_admin();

$users = $isSuper ? user_repo()->getAll() : user_repo()->getAll($orgId);

$rows = [];
foreach ($users as $u) {
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

$content = page_header('Users', 'Staff accounts for ' . e($orgName) . ' (organization-isolated).');
$content .= card(data_table(['ID', 'Name', 'Role', 'Portal', 'Branch', 'Status'], $rows), 'overflow-hidden');

render_page('Users', 'main-lab', 'settings-users', $content);
