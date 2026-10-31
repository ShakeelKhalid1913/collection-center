<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_branch') {
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $name = trim((string)($_POST['name'] ?? ''));
    $type = trim((string)($_POST['branch_type'] ?? 'collection_center'));
    $address = trim((string)($_POST['address'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));

    if ($code === '' || $name === '') {
        $message = flash_error('Branch code and name are required.');
    } else {
        $id = 'BR-' . bin2hex(random_bytes(4));
        try {
            $ok = db()->execute(
                "INSERT INTO branches (id, organization_id, code, name, branch_type, address, phone)
                 VALUES (:id, :org, :code, :name, :type, :addr, :phone)",
                [
                    'id' => $id,
                    'org' => $orgId,
                    'code' => $code,
                    'name' => $name,
                    'type' => $type,
                    'addr' => $address,
                    'phone' => $phone,
                ]
            );
            if ($ok) {
                audit_log('CREATE_BRANCH', 'branches', $id, "Added branch {$name} ({$code})");
                $message = flash_success("Branch '{$name}' created successfully.");
            } else {
                $message = flash_error('Could not save branch.');
            }
        } catch (\Throwable $e) {
            $message = flash_error('Database error: ' . $e->getMessage());
        }
    }
}

// Fetch branches from database
$branches = [];
try {
    $branches = db()->fetchAll(
        "SELECT b.*, o.name AS org_name
         FROM branches b
         LEFT JOIN organizations o ON b.organization_id = o.id
         WHERE b.organization_id = :org
         ORDER BY b.created_at ASC",
        ['org' => $orgId]
    );
} catch (\Throwable) {}

// Fallback if branches table is empty
if (empty($branches)) {
    $branches = [
        ['id' => 'BR-MAIN-LAB', 'code' => 'HQ-01', 'name' => 'Main Laboratory Central', 'branch_type' => 'main_lab', 'address' => 'Central Lab Campus', 'phone' => '+92 42 111 222 333'],
        ['id' => 'BR-CC-01', 'code' => 'CC-01', 'name' => 'Gulberg Collection Point', 'branch_type' => 'collection_center', 'address' => 'Gulberg III, Main Market', 'phone' => '+92 42 35789000'],
        ['id' => 'BR-IMG', 'code' => 'IMG-01', 'name' => 'Diagnostic Center + Hospital', 'branch_type' => 'imaging', 'address' => 'Imaging & Radiology Complex', 'phone' => '+92 42 35789001'],
    ];
}

$rows = [];
foreach ($branches as $b) {
    $typeLabel = match ($b['branch_type'] ?? '') {
        'main_lab' => 'Laboratory (HQ)',
        'collection_center' => 'Collection Center',
        'imaging' => 'Diagnostic Center + Hospital',
        default => ucfirst(str_replace('_', ' ', (string)($b['branch_type'] ?? 'Branch'))),
    };

    $rows[] = [
        '<span class="font-mono font-bold text-teal-800 bg-teal-50 px-2 py-0.5 rounded-md text-xs">' . e((string)($b['code'] ?? $b['id'])) . '</span>',
        '<strong class="text-slate-900 block">' . e((string)$b['name']) . '</strong><span class="text-xs text-slate-500">' . e((string)($b['address'] ?? '—')) . '</span>',
        '<span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">' . e($typeLabel) . '</span>',
        e((string)($b['phone'] ?? '—')),
        status_badge('active'),
    ];
}

$formHtml = <<<HTML
<div class="p-6 bg-slate-50 border-b border-slate-200">
    <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider mb-4"><i class="fa-solid fa-plus-circle text-teal-600 mr-1.5"></i> Add New Branch or Collection Point</h3>
    <form method="post" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
        <input type="hidden" name="action" value="add_branch">
        <div>
            <label class="field-label" for="code">Branch Code</label>
            <input type="text" id="code" name="code" required class="field text-xs uppercase" placeholder="e.g. CC-02">
        </div>
        <div>
            <label class="field-label" for="name">Branch / Center Name</label>
            <input type="text" id="name" name="name" required class="field text-xs" placeholder="e.g. Cantt Collection Center">
        </div>
        <div>
            <label class="field-label" for="branch_type">Type</label>
            <select id="branch_type" name="branch_type" class="field text-xs">
                <option value="collection_center">Collection Center</option>
                <option value="main_lab">Main Laboratory</option>
                <option value="imaging">Diagnostic Center + Hospital</option>
            </select>
        </div>
        <div>
            <label class="field-label" for="phone">Phone</label>
            <input type="text" id="phone" name="phone" class="field text-xs" placeholder="+92 300 0000000">
        </div>
        <div>
            <button type="submit" class="btn btn-primary w-full text-xs font-bold py-2"><i class="fa-solid fa-check mr-1"></i> Save Branch</button>
        </div>
    </form>
</div>
HTML;

$content = page_header('Labs & Branches', 'Branch network and collection points hierarchy.');
$content .= $message;
$content .= card($formHtml . data_table(['Code', 'Name & Location', 'Type', 'Phone', 'Status'], $rows), 'overflow-hidden');

render_page('Labs & Branches', 'admin', 'labs', $content);
