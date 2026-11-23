<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

require_auth('admin');

$isSuper = is_super_admin();
$currentOrgId = current_organization_id();
$currentOrgName = current_organization_name();

// Load organizations for Super Admin filter
$organizations = [];
if ($isSuper) {
    try {
        $organizations = db()->fetchAll("SELECT id, name FROM organizations ORDER BY name ASC");
    } catch (\Throwable) {}
}

$selectedOrgFilter = $isSuper ? trim((string)($_GET['org_id'] ?? '')) : $currentOrgId;

// Fetch audit logs
$logs = [];
try {
    if ($isSuper && $selectedOrgFilter === '') {
        $logs = db()->fetchAll(
            "SELECT a.*, o.name AS org_name
             FROM audit_logs a
             LEFT JOIN organizations o ON a.organization_id = o.id
             ORDER BY a.created_at DESC
             LIMIT 200"
        );
    } else {
        $filterId = ($isSuper && $selectedOrgFilter !== '') ? $selectedOrgFilter : $currentOrgId;
        $logs = db()->fetchAll(
            "SELECT a.*, o.name AS org_name
             FROM audit_logs a
             LEFT JOIN organizations o ON a.organization_id = o.id
             WHERE a.organization_id = :org
             ORDER BY a.created_at DESC
             LIMIT 150",
            ['org' => $filterId]
        );
    }
} catch (\Throwable) {}

$rows = [];
foreach ($logs as $l) {
    $rowItem = [];
    $rowItem[] = '<span class="text-xs font-mono text-slate-500 whitespace-nowrap">' . e((string)($l['created_at'] ?? '')) . '</span>';
    
    if ($isSuper) {
        $rowItem[] = '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-800 border border-slate-200"><i class="fa-solid fa-hospital text-[9px]"></i> ' . e((string)($l['org_name'] ?? $l['organization_id'] ?? 'ORG-001')) . '</span>';
    }

    $rowItem[] = '<div class="font-bold text-slate-900 text-xs">' . e((string)($l['user_name'] ?? 'System')) . '</div>' .
        '<span class="text-[10px] font-mono text-slate-400">' . e((string)($l['user_id'] ?? '')) . '</span>';
    $rowItem[] = '<span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">' . e((string)($l['portal'] ?? '—')) . '</span>';
    $rowItem[] = '<span class="font-mono text-xs font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">' . e((string)$l['action']) . '</span>';
    $rowItem[] = '<span class="text-xs font-semibold text-slate-800">' . e((string)$l['entity_type']) . ' ' . (e((string)($l['entity_id'] ?? ''))) . '</span>';
    $rowItem[] = '<span class="text-xs text-slate-600">' . e((string)($l['details'] ?? '—')) . '</span>';
    $rowItem[] = '<span class="text-xs font-mono text-slate-400">' . e((string)($l['ip_address'] ?? '')) . '</span>';

    $rows[] = $rowItem;
}

$tableHeaders = $isSuper
    ? ['Timestamp', 'Client Lab', 'Staff Member', 'Portal', 'Action', 'Target Entity', 'Details', 'IP Address']
    : ['Timestamp', 'Staff Member', 'Portal', 'Action', 'Target Entity', 'Details', 'IP Address'];

// Filter header for Super Admin
$filterBar = '';
if ($isSuper && !empty($organizations)) {
    ob_start();
    ?>
    <div class="mb-4 p-3 bg-white border border-slate-200 rounded-2xl flex items-center justify-between text-xs shadow-2xs">
        <span class="font-bold text-slate-700"><i class="fa-solid fa-filter mr-1 text-slate-500"></i> Scope Audit Trail:</span>
        <form method="get" class="flex items-center gap-2">
            <select name="org_id" onchange="this.form.submit()" class="field text-xs py-1 px-3 bg-white border border-slate-300 rounded-lg">
                <option value="">All Client Labs (Global Audit Trail)</option>
                <?php foreach ($organizations as $org): ?>
                    <option value="<?= e($org['id']) ?>" <?= $selectedOrgFilter === $org['id'] ? 'selected' : '' ?>>
                        <?= e($org['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php
    $filterBar = ob_get_clean();
}

$pageTitle = $isSuper ? 'Global Audit Trail & Security Logs' : 'Lab Security & Activity Trail';
$pageSubtitle = $isSuper
    ? 'Centralized oversight for all client laboratories, satellite collection points and staff actions.'
    : 'Activity logs, record modifications and deletions for ' . e($currentOrgName) . '.';

$content = page_header($pageTitle, $pageSubtitle);
$content .= $filterBar;
$content .= card(
    data_table($tableHeaders, $rows),
    'overflow-hidden'
);

render_page($pageTitle, 'admin', 'audit-trail', $content);
