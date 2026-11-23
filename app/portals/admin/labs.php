<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

require_auth('admin');

$isSuper = is_super_admin();
$currentOrgId = current_organization_id();
$currentOrgName = current_organization_name();
$message = '';

// For Super Admin: load all organizations for selector
$organizations = [];
if ($isSuper) {
    try {
        $organizations = db()->fetchAll("SELECT id, name FROM organizations ORDER BY name ASC");
    } catch (\Throwable) {}
}

$selectedOrgFilter = $isSuper ? trim((string)($_GET['org_id'] ?? '')) : $currentOrgId;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_branch') {
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $name = trim((string)($_POST['name'] ?? ''));
    $type = trim((string)($_POST['branch_type'] ?? 'collection_center'));
    $address = trim((string)($_POST['address'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    
    // Super admin can choose org; lab admin is strictly locked to their own org
    $targetOrgId = $isSuper ? trim((string)($_POST['target_org_id'] ?? $currentOrgId)) : $currentOrgId;
    if ($targetOrgId === '') {
        $targetOrgId = $currentOrgId;
    }

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
                    'org' => $targetOrgId,
                    'code' => $code,
                    'name' => $name,
                    'type' => $type,
                    'addr' => $address,
                    'phone' => $phone,
                ]
            );
            if ($ok) {
                audit_log('CREATE_BRANCH', 'branches', $id, "Added branch {$name} ({$code}) for org {$targetOrgId}");
                $message = flash_success("Branch '{$name}' created successfully.");
            } else {
                $message = flash_error('Could not save branch.');
            }
        } catch (\Throwable $e) {
            $message = flash_error('Database error: ' . $e->getMessage());
        }
    }
}

// Edit Branch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_branch') {
    $branchId = trim((string)($_POST['branch_id'] ?? ''));
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $name = trim((string)($_POST['name'] ?? ''));
    $type = trim((string)($_POST['branch_type'] ?? 'collection_center'));
    $address = trim((string)($_POST['address'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));

    if ($branchId === '' || $code === '' || $name === '') {
        $message = flash_error('Branch ID, code, and name are required.');
    } else {
        try {
            // Check ownership if not super admin
            $existing = db()->fetchOne("SELECT organization_id FROM branches WHERE id = :id", ['id' => $branchId]);
            if (!$existing) {
                $message = flash_error('Branch not found.');
            } elseif (!$isSuper && $existing['organization_id'] !== $currentOrgId) {
                $message = flash_error('Unauthorized to edit this branch.');
            } else {
                $params = [
                    'id' => $branchId,
                    'code' => $code,
                    'name' => $name,
                    'type' => $type,
                    'addr' => $address,
                    'phone' => $phone,
                ];
                $sql = "UPDATE branches SET code = :code, name = :name, branch_type = :type, address = :addr, phone = :phone";
                if ($isSuper && !empty($_POST['target_org_id'])) {
                    $sql .= ", organization_id = :org";
                    $params['org'] = trim((string)$_POST['target_org_id']);
                }
                $sql .= " WHERE id = :id";
                db()->execute($sql, $params);
                audit_log('EDIT_BRANCH', 'branches', $branchId, "Updated branch {$name} ({$code})");
                $message = flash_success("Branch '{$name}' updated successfully.");
            }
        } catch (\Throwable $e) {
            $message = flash_error('Database error: ' . $e->getMessage());
        }
    }
}

// Delete Branch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_branch') {
    $branchId = trim((string)($_POST['branch_id'] ?? ''));
    if ($branchId === '') {
        $message = flash_error('Branch ID is required.');
    } else {
        try {
            $existing = db()->fetchOne("SELECT * FROM branches WHERE id = :id", ['id' => $branchId]);
            if (!$existing) {
                $message = flash_error('Branch not found.');
            } elseif (!$isSuper && $existing['organization_id'] !== $currentOrgId) {
                $message = flash_error('Unauthorized to delete this branch.');
            } else {
                db()->execute("DELETE FROM branches WHERE id = :id", ['id' => $branchId]);
                audit_log('DELETE_BRANCH', 'branches', $branchId, "Deleted branch {$existing['name']} ({$branchId})");
                $message = flash_success("Branch '{$existing['name']}' (ID: {$branchId}) deleted successfully.");
            }
        } catch (\Throwable $e) {
            $message = flash_error('Database error: ' . $e->getMessage());
        }
    }
}

// Fetch branches from database
$branches = [];
try {
    if ($isSuper && $selectedOrgFilter === '') {
        // Super Admin viewing all branches across all labs
        $branches = db()->fetchAll(
            "SELECT b.*, o.name AS org_name
             FROM branches b
             LEFT JOIN organizations o ON b.organization_id = o.id
             ORDER BY o.name ASC, b.created_at ASC"
        );
    } else {
        // Scoped to specific organization
        $filterId = ($isSuper && $selectedOrgFilter !== '') ? $selectedOrgFilter : $currentOrgId;
        $branches = db()->fetchAll(
            "SELECT b.*, o.name AS org_name
             FROM branches b
             LEFT JOIN organizations o ON b.organization_id = o.id
             WHERE b.organization_id = :org
             ORDER BY b.created_at ASC",
            ['org' => $filterId]
        );
    }
} catch (\Throwable) {}

$rows = [];
foreach ($branches as $b) {
    $typeLabel = match ($b['branch_type'] ?? '') {
        'main_lab' => 'Laboratory (HQ)',
        'collection_center' => 'Collection Center',
        'imaging' => 'Diagnostic Center + Hospital',
        default => ucfirst(str_replace('_', ' ', (string)($b['branch_type'] ?? 'Branch'))),
    };

    $bId = (string)$b['id'];
    $bDataJson = htmlspecialchars(json_encode([
        'id' => $bId,
        'code' => $b['code'] ?? '',
        'name' => $b['name'] ?? '',
        'branch_type' => $b['branch_type'] ?? 'collection_center',
        'address' => $b['address'] ?? '',
        'phone' => $b['phone'] ?? '',
        'organization_id' => $b['organization_id'] ?? ''
    ]), ENT_QUOTES, 'UTF-8');

    $actionsHtml = '<div class="flex items-center gap-1.5 justify-end">';
    $actionsHtml .= '<button type="button" onclick=\'openEditBranchModal(' . $bDataJson . ')\' class="btn btn-secondary text-xs py-1 px-2.5 font-semibold text-blue-600 hover:bg-blue-50 border border-blue-200" title="Edit Branch"><i class="fa-solid fa-pen-to-square mr-1"></i> Edit</button>';
    $actionsHtml .= '<form method="post" class="inline m-0" onsubmit="return confirm(\'Are you sure you want to delete branch ' . e(addslashes($b['name'])) . ' (' . e($bId) . ')?\');">';
    $actionsHtml .= '<input type="hidden" name="action" value="delete_branch">';
    $actionsHtml .= '<input type="hidden" name="branch_id" value="' . e($bId) . '">';
    $actionsHtml .= '<button type="submit" class="btn btn-secondary text-xs py-1 px-2 font-semibold text-rose-600 hover:bg-rose-50 border border-rose-200" title="Delete Branch ID"><i class="fa-solid fa-trash mr-1"></i> Delete</button>';
    $actionsHtml .= '</form>';
    $actionsHtml .= '</div>';

    $rowItem = [];
    $rowItem[] = '<div class="flex flex-col"><span class="font-mono font-bold text-slate-800 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-md text-xs w-max">' . e((string)($b['code'] ?? $b['id'])) . '</span><span class="text-[10px] text-slate-400 font-mono mt-0.5">' . e($bId) . '</span></div>';
    
    if ($isSuper) {
        $rowItem[] = '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-900 text-[#c2f13c]"><i class="fa-solid fa-hospital text-[10px]"></i> ' . e((string)($b['org_name'] ?? 'Default Lab')) . '</span>';
    }

    $rowItem[] = '<strong class="text-slate-900 block">' . e((string)$b['name']) . '</strong><span class="text-xs text-slate-500">' . e((string)($b['address'] ?? '—')) . '</span>';
    $rowItem[] = '<span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">' . e($typeLabel) . '</span>';
    $rowItem[] = e((string)($b['phone'] ?? '—'));
    $rowItem[] = status_badge('active');
    $rowItem[] = $actionsHtml;

    $rows[] = $rowItem;
}

$tableHeaders = $isSuper 
    ? ['Branch Code & ID', 'Client Laboratory', 'Branch / Center Name', 'Type', 'Phone', 'Status', 'Actions']
    : ['Branch Code & ID', 'Branch / Center Name', 'Type', 'Phone', 'Status', 'Actions'];

// Build form HTML
ob_start();
?>
<div class="p-6 bg-slate-50 border-b border-slate-200">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
            <i class="fa-solid fa-circle-plus text-slate-900"></i> Add New Branch or Collection Point
        </h3>
        <?php if ($isSuper && !empty($organizations)): ?>
        <form method="get" class="flex items-center gap-2 text-xs">
            <label class="font-bold text-slate-600">Filter by Lab:</label>
            <select name="org_id" onchange="this.form.submit()" class="field text-xs py-1 px-2.5 bg-white border border-slate-300 rounded-lg">
                <option value="">All Client Labs (<?= count($branches) ?> Branches)</option>
                <?php foreach ($organizations as $org): ?>
                    <option value="<?= e($org['id']) ?>" <?= $selectedOrgFilter === $org['id'] ? 'selected' : '' ?>>
                        <?= e($org['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>

    <form method="post" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-<?= $isSuper ? '6' : '5' ?> gap-3 items-end">
        <input type="hidden" name="action" value="add_branch">

        <?php if ($isSuper): ?>
        <div>
            <label class="field-label" for="target_org_id">Assign to Lab</label>
            <select id="target_org_id" name="target_org_id" class="field text-xs">
                <?php foreach ($organizations as $org): ?>
                    <option value="<?= e($org['id']) ?>" <?= $selectedOrgFilter === $org['id'] ? 'selected' : '' ?>>
                        <?= e($org['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

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
            <button type="submit" class="btn btn-primary w-full text-xs font-bold py-2">
                <i class="fa-solid fa-check mr-1"></i> Save Branch
            </button>
        </div>
    </form>
</div>

<!-- Edit Branch Modal -->
<div id="editBranchModal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-2xl max-w-lg w-full relative">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <h3 class="font-extrabold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-indigo-600"></i> Edit Branch Details
            </h3>
            <button type="button" onclick="closeEditBranchModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        
        <form method="post" class="space-y-4">
            <input type="hidden" name="action" value="edit_branch">
            <input type="hidden" name="branch_id" id="modal_edit_branch_id" value="">

            <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80 text-xs flex items-center justify-between">
                <span class="text-slate-500 font-semibold">Branch ID:</span>
                <span id="modal_edit_branch_display_id" class="font-mono font-bold text-slate-800 bg-white px-2 py-0.5 rounded border border-slate-300"></span>
            </div>

            <?php if ($isSuper && !empty($organizations)): ?>
            <div>
                <label class="field-label text-xs" for="modal_branch_target_org_id">Assign to Lab</label>
                <select id="modal_branch_target_org_id" name="target_org_id" class="field text-sm">
                    <?php foreach ($organizations as $org): ?>
                        <option value="<?= e($org['id']) ?>"><?= e($org['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="field-label text-xs" for="modal_branch_code">Branch Code</label>
                    <input type="text" id="modal_branch_code" name="code" required class="field text-sm uppercase">
                </div>
                <div>
                    <label class="field-label text-xs" for="modal_branch_type">Type</label>
                    <select id="modal_branch_type" name="branch_type" class="field text-sm">
                        <option value="collection_center">Collection Center</option>
                        <option value="main_lab">Main Laboratory</option>
                        <option value="imaging">Diagnostic Center + Hospital</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="field-label text-xs" for="modal_branch_name">Branch / Center Name</label>
                <input type="text" id="modal_branch_name" name="name" required class="field text-sm">
            </div>

            <div>
                <label class="field-label text-xs" for="modal_branch_phone">Phone</label>
                <input type="text" id="modal_branch_phone" name="phone" class="field text-sm">
            </div>

            <div>
                <label class="field-label text-xs" for="modal_branch_address">Address / Location</label>
                <input type="text" id="modal_branch_address" name="address" class="field text-sm">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeEditBranchModal()" class="btn btn-secondary text-xs py-2 px-3.5 font-bold text-slate-700">Cancel</button>
                <button type="submit" class="btn btn-primary text-xs py-2 px-4 font-bold"><i class="fa-solid fa-save mr-1"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditBranchModal(b) {
    document.getElementById('modal_edit_branch_id').value = b.id || '';
    document.getElementById('modal_edit_branch_display_id').textContent = b.id || '';
    document.getElementById('modal_branch_code').value = b.code || '';
    document.getElementById('modal_branch_name').value = b.name || '';
    document.getElementById('modal_branch_type').value = b.branch_type || 'collection_center';
    document.getElementById('modal_branch_phone').value = b.phone || '';
    document.getElementById('modal_branch_address').value = b.address || '';
    
    const orgSelect = document.getElementById('modal_branch_target_org_id');
    if (orgSelect && b.organization_id) orgSelect.value = b.organization_id;

    document.getElementById('editBranchModal').classList.remove('hidden');
}

function closeEditBranchModal() {
    document.getElementById('editBranchModal').classList.add('hidden');
}
</script>
<?php
$formHtml = ob_get_clean();

$pageTitle = $isSuper ? 'All Network Branches & Laboratories' : 'Labs & Collection Centers';
$pageSubtitle = $isSuper 
    ? 'Master multi-tenant directory of central labs and collection points across all client organizations.'
    : 'Collection centers and satellite sample collection points connected to ' . $currentOrgName . '.';

$content = page_header($pageTitle, $pageSubtitle);
$content .= $message;
$content .= card($formHtml . data_table($tableHeaders, $rows), 'overflow-hidden');

render_page($pageTitle, 'admin', 'labs', $content);
