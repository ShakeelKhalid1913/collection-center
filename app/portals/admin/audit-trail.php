<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

require_auth('admin');

$orgId = current_user()['organization_id'] ?? 'ORG-001';

// Fetch audit logs
$logs = db()->fetchAll(
    "SELECT * FROM audit_logs WHERE organization_id = :org ORDER BY created_at DESC LIMIT 150",
    ['org' => $orgId]
);

$rows = [];
foreach ($logs as $l) {
    $rows[] = [
        '<span class="text-xs font-mono text-slate-500 whitespace-nowrap">' . e((string)($l['created_at'] ?? '')) . '</span>',
        '<div class="font-bold text-slate-900 text-xs">' . e((string)($l['user_name'] ?? 'System')) . '</div>' .
        '<span class="text-[10px] font-mono text-slate-400">' . e((string)($l['user_id'] ?? '')) . '</span>',
        '<span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">' . e((string)($l['portal'] ?? '—')) . '</span>',
        '<span class="font-mono text-xs font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded">' . e((string)$l['action']) . '</span>',
        '<span class="text-xs font-semibold text-slate-800">' . e((string)$l['entity_type']) . ' ' . (e((string)($l['entity_id'] ?? ''))) . '</span>',
        '<span class="text-xs text-slate-600">' . e((string)($l['details'] ?? '—')) . '</span>',
        '<span class="text-xs font-mono text-slate-400">' . e((string)($l['ip_address'] ?? '')) . '</span>',
    ];
}

$content = page_header(
    'Centralized Audit Log & Activity Trail',
    'Real-time oversight for satellite collection points and diagnostic center operations.'
);

$content .= card(
    data_table(['Timestamp', 'Staff Member', 'Portal', 'Action', 'Target Entity', 'Details', 'IP Address'], $rows),
    'overflow-hidden'
);

render_page('Audit Trail', 'admin', 'audit-trail', $content);
