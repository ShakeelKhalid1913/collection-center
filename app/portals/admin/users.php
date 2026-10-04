<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$rows = [];
foreach (user_repo()->getAll() as $u) {
    $portal = portal_from_db((string)($u['portal'] ?? ''));
    $initial = strtoupper(substr((string)($u['name'] ?? 'U'), 0, 1));
    $rows[] = [
        '<div class="flex items-center gap-2.5">' .
        '<span class="w-7 h-7 rounded-full bg-slate-800 text-[#c2f13c] flex items-center justify-center font-bold text-xs flex-shrink-0">' . e($initial) . '</span>' .
        '<span class="font-bold text-slate-800">' . e($u['name']) . '</span>' .
        '</div>',
        '<span class="text-xs font-mono text-slate-500">' . e($u['email']) . '</span>',
        '<span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">' . e(PORTALS[$portal]['label'] ?? $portal) . '</span>',
        '<span class="text-xs text-slate-600 font-medium">' . e((string)($u['branch_name'] ?? $u['branch_id'] ?? '—')) . '</span>',
        status_badge(!empty($u['is_active']) ? 'active' : 'pending'),
    ];
}

$content = page_header('Users & Permissions', 'Portal access is locked per account — cross-portal URLs return 403.');
$content .= card(data_table(['Email', 'Name', 'Portal role', 'Branch', 'Status'], $rows), 'overflow-hidden');

render_page('Users', 'admin', 'users', $content);
