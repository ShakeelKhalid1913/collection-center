<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

require_auth('admin');

$isSuper = is_super_admin();
$currentOrgId = current_organization_id();
$currentOrgName = current_organization_name();
$currentUserId = $_SESSION['user']['id'] ?? '';
$msg = '';

// Load organizations for Super Admin selector
$organizations = [];
if ($isSuper) {
    try {
        $organizations = db()->fetchAll("SELECT id, name FROM organizations ORDER BY name ASC");
    } catch (\Throwable) {}
}

$selectedOrgFilter = $isSuper ? trim((string)($_GET['org_id'] ?? '')) : $currentOrgId;

// Load branches for dropdowns
$branches = [];
try {
    if ($isSuper) {
        $branches = db()->fetchAll("SELECT id, organization_id, name, code, branch_type FROM branches ORDER BY name ASC");
    } else {
        $branches = db()->fetchAll("SELECT id, organization_id, name, code, branch_type FROM branches WHERE organization_id = :org ORDER BY name ASC", ['org' => $currentOrgId]);
    }
} catch (\Throwable) {}

// Available permission toggles
$allPermissions = [
    'header_footer_edit' => 'Header / Footer Customization',
    'rates_edit' => 'Rate Lists & Price Modification',
    'report_edit' => 'Report & Result Outcome Editing',
    'view_results' => 'View Completed Results',
    'process_billing' => 'Process Billing & Record Payments',
    'transit_dispatch' => 'Sample Transit Dispatch & Barcode Status',
    'lock_records' => 'Lock Bills & Reports (Security Lock)',
    'unlock_records' => 'Unlock Locked Records (Admin Override)',
];

// Handle new user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $email = trim(strtolower((string)($_POST['email'] ?? '')));
    $name = trim((string)($_POST['name'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $portal = trim((string)($_POST['portal'] ?? 'collection_center'));
    if ($portal === 'admin' && !$isSuper) {
        $portal = 'collection_center';
    }
    $branch = trim((string)($_POST['branch'] ?? ''));

    // Super Admin can assign user to any organization; Lab Admin is locked to their own org
    $targetOrgId = $isSuper ? trim((string)($_POST['target_org_id'] ?? $currentOrgId)) : $currentOrgId;
    if ($targetOrgId === '') {
        $targetOrgId = $currentOrgId;
    }
    
    // Collect toggles
    $perms = [];
    foreach (array_keys($allPermissions) as $pKey) {
        $perms[$pKey] = !empty($_POST['perm_' . $pKey]) ? 1 : 0;
    }

    $res = user_repo()->createUser([
        'organization_id' => $targetOrgId,
        'branch_id' => $branch !== '' ? $branch : null,
        'email' => $email,
        'name' => $name,
        'password' => $password,
        'portal' => $portal,
        'permissions' => $perms,
    ]);

    if (!empty($res['success'])) {
        audit_log('CREATE_USER', 'users', $email, "Created user account for {$name} ({$portal}) in {$targetOrgId}");
        $msg = flash_success("User {$name} ({$email}) created successfully with configured credentials.");
    } else {
        $msg = flash_error($res['error'] ?? 'Failed to create user account.');
    }
}

// Handle editing existing user details & password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $targetUserId = trim((string)($_POST['user_id'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim(strtolower((string)($_POST['email'] ?? '')));
    $portal = trim((string)($_POST['portal'] ?? 'collection_center'));
    $branch = trim((string)($_POST['branch'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $targetUser = user_repo()->findById($targetUserId);
    if (!$targetUser) {
        $msg = flash_error('User not found.');
    } elseif (!$isSuper && ($targetUser['organization_id'] !== $currentOrgId || portal_from_db((string)$targetUser['portal']) === 'admin')) {
        $msg = flash_error('Unauthorized to edit this user account.');
    } else {
        $updateData = [
            'name' => $name,
            'email' => $email,
            'portal' => $portal,
            'branch_id' => $branch !== '' ? $branch : null,
        ];

        if ($isSuper && !empty($_POST['target_org_id'])) {
            $updateData['organization_id'] = trim((string)$_POST['target_org_id']);
        }

        if ($password !== '') {
            $updateData['password'] = $password;
        }

        $res = user_repo()->updateUser($targetUserId, $updateData);
        if (!empty($res['success'])) {
            audit_log('EDIT_USER', 'users', $targetUserId, "Updated account details and credentials for user {$targetUserId}");
            $msg = flash_success("User '{$name}' (ID: {$targetUserId}) updated successfully.");
        } else {
            $msg = flash_error($res['error'] ?? 'Failed to update user account.');
        }
    }
}

// Handle deleting a user ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $targetUserId = trim((string)($_POST['user_id'] ?? ''));
    $targetUser = user_repo()->findById($targetUserId);

    if (!$targetUser) {
        $msg = flash_error('User not found.');
    } elseif ($targetUserId === $currentUserId) {
        $msg = flash_error('You cannot delete your own account while currently logged in.');
    } elseif (!empty($targetUser['is_super_admin']) && !$isSuper) {
        $msg = flash_error('Unauthorized to delete a Super Administrator ID.');
    } elseif (!$isSuper && ($targetUser['organization_id'] !== $currentOrgId || portal_from_db((string)$targetUser['portal']) === 'admin')) {
        $msg = flash_error('Unauthorized to delete this user.');
    } else {
        $res = user_repo()->deleteUser($targetUserId);
        if (!empty($res['success'])) {
            audit_log('DELETE_USER', 'users', $targetUserId, "Deleted user ID {$targetUserId} ({$targetUser['email']})");
            $msg = flash_success("User ID '{$targetUserId}' ({$targetUser['name']}) has been permanently deleted.");
        } else {
            $msg = flash_error($res['error'] ?? 'Failed to delete user.');
        }
    }
}

// Handle updating existing user permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_permissions') {
    $targetUserId = trim((string)($_POST['user_id'] ?? ''));
    if ($targetUserId !== '') {
        $perms = [];
        foreach (array_keys($allPermissions) as $pKey) {
            $perms[$pKey] = !empty($_POST['user_perm_' . $targetUserId . '_' . $pKey]) ? 1 : 0;
        }
        user_repo()->updateUserPermissions($targetUserId, $perms);
        audit_log('UPDATE_PERMISSIONS', 'users', $targetUserId, "Updated granular permission toggles");
        $msg = flash_success("Permissions updated successfully for user ID {$targetUserId}.");
    }
}

// Handle activating/deactivating user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $targetUserId = trim((string)($_POST['user_id'] ?? ''));
    $newStatus = (int)($_POST['status'] ?? 1);
    if ($targetUserId !== '') {
        user_repo()->toggleUserStatus($targetUserId, $newStatus);
        audit_log('TOGGLE_USER_STATUS', 'users', $targetUserId, "Set active status to {$newStatus}");
        $msg = flash_success("User status successfully updated.");
    }
}

// Fetch users
if ($isSuper && $selectedOrgFilter === '') {
    $rawUsers = user_repo()->getAll();
} else {
    $filterId = ($isSuper && $selectedOrgFilter !== '') ? $selectedOrgFilter : $currentOrgId;
    $rawUsers = user_repo()->getAll($filterId);
}

// If Lab Admin, filter out admin accounts so they only manage their staff
if (!$isSuper) {
    $users = array_values(array_filter($rawUsers, static function (array $u): bool {
        $p = portal_from_db((string)($u['portal'] ?? ''));
        return $p !== 'admin' && empty($u['is_super_admin']);
    }));
} else {
    $users = $rawUsers;
}

$userRows = [];
foreach ($users as $u) {
    $uId = (string)$u['id'];
    $portal = portal_from_db((string)($u['portal'] ?? ''));
    $initial = strtoupper(substr((string)($u['name'] ?? 'U'), 0, 1));
    $plainPass = (string)($u['password_plain'] ?? '1913');

    if (!empty($u['permissions'])) {
        $userPerms = is_array($u['permissions']) ? $u['permissions'] : (json_decode((string)$u['permissions'], true) ?: []);
    } else {
        // Safe role-based defaults if empty
        if ($portal === 'collection_center' || $portal === 'collection-center') {
            $userPerms = ['view_results' => 1, 'process_billing' => 1, 'transit_dispatch' => 1, 'header_footer_edit' => 0, 'rates_edit' => 0, 'report_edit' => 0];
        } elseif ($portal === 'admin') {
            $userPerms = ['view_results' => 1, 'process_billing' => 1, 'transit_dispatch' => 1, 'report_edit' => 1, 'rates_edit' => 1, 'header_footer_edit' => 1];
        } else {
            $userPerms = ['view_results' => 1, 'process_billing' => 1, 'transit_dispatch' => 1, 'report_edit' => 1, 'rates_edit' => 0, 'header_footer_edit' => 0];
        }
    }

    // Build permission checkboxes form
    if ($portal === 'admin' || !empty($u['is_super_admin'])) {
        $checkboxesHtml = '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-900 text-[#c2f13c] text-xs font-bold"><i class="fa-solid fa-shield-halved text-[10px]"></i> Full Unrestricted Admin Access</span>';
    } else {
        $checkboxesHtml = '<form method="post" class="space-y-1.5">';
        $checkboxesHtml .= '<input type="hidden" name="action" value="update_permissions">';
        $checkboxesHtml .= '<input type="hidden" name="user_id" value="' . e($uId) . '">';
        $checkboxesHtml .= '<div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 text-xs text-slate-700">';

        foreach ($allPermissions as $pKey => $pLabel) {
            $isChecked = !empty($userPerms[$pKey]);
            $checkedAttr = $isChecked ? ' checked' : '';
            $checkboxesHtml .= '<label class="inline-flex items-center gap-1.5 cursor-pointer bg-slate-50 hover:bg-slate-100 p-1 rounded border border-slate-200/70 select-none">'
                . '<input type="checkbox" name="user_perm_' . e($uId) . '_' . e($pKey) . '" value="1"' . $checkedAttr . ' class="rounded text-slate-900 focus:ring-0">'
                . '<span class="truncate">' . e($pLabel) . '</span>'
                . '</label>';
        }
        $checkboxesHtml .= '</div>';
        $checkboxesHtml .= '<button type="submit" class="btn btn-secondary text-xs py-1 px-2.5 mt-1 font-semibold text-slate-800 hover:bg-slate-100"><i class="fa-solid fa-save mr-1"></i> Save Toggles</button>';
        $checkboxesHtml .= '</form>';
    }

    $isAct = !empty($u['is_active']);
    $nextStatus = $isAct ? 0 : 1;
    $btnText = $isAct ? 'Deactivate' : 'Activate';
    $btnClass = $isAct ? 'text-amber-600 hover:bg-amber-50 border-amber-200' : 'text-emerald-600 hover:bg-emerald-50 border-emerald-200';
    $statusBtn = '<form method="post" class="inline m-0">'
        . '<input type="hidden" name="action" value="toggle_status">'
        . '<input type="hidden" name="user_id" value="' . e($uId) . '">'
        . '<input type="hidden" name="status" value="' . $nextStatus . '">'
        . '<button type="submit" class="btn btn-secondary text-xs py-1 px-2 font-semibold border ' . $btnClass . '" title="Toggle Account Status">' . $btnText . '</button>'
        . '</form>';

    // Edit button with user JSON payload
    $userDataPayload = htmlspecialchars(json_encode([
        'id' => $uId,
        'name' => $u['name'],
        'email' => $u['email'],
        'portal' => $portal,
        'branch_id' => $u['branch_id'] ?? '',
        'organization_id' => $u['organization_id'] ?? '',
        'password' => $plainPass
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');

    $editBtn = '<button type="button" onclick=\'openEditUserModal(' . $userDataPayload . ')\' class="btn btn-secondary text-xs py-1 px-2.5 font-semibold text-blue-600 hover:bg-blue-50 border border-blue-200" title="Edit User Details & Password">'
        . '<i class="fa-solid fa-pen-to-square mr-1"></i> Edit'
        . '</button>';

    // Delete button
    if ($uId === $currentUserId) {
        $deleteBtn = '<span class="text-[11px] text-slate-400 font-medium italic px-1">You</span>';
    } else {
        $deleteBtn = '<form method="post" class="inline m-0" onsubmit="return confirm(\'Are you sure you want to permanently delete user ID ' . e($uId) . ' (' . e(addslashes($u['name'])) . ')? This action cannot be undone.\');">'
            . '<input type="hidden" name="action" value="delete_user">'
            . '<input type="hidden" name="user_id" value="' . e($uId) . '">'
            . '<button type="submit" class="btn btn-secondary text-xs py-1 px-2 font-semibold text-rose-600 hover:bg-rose-50 border border-rose-200" title="Delete ID">'
            . '<i class="fa-solid fa-trash mr-1"></i> Delete'
            . '</button>'
            . '</form>';
    }

    $actionsHtml = '<div class="flex items-center gap-1.5 flex-wrap">' . $editBtn . $statusBtn . $deleteBtn . '</div>';

    // Show Password Cell with interactive Eye reveal & Copy button
    $passSafeAttr = htmlspecialchars($plainPass, ENT_QUOTES, 'UTF-8');
    $passwordHtml = '<div class="flex items-center gap-1.5 font-mono text-xs select-none">'
        . '<span id="pass-span-' . e($uId) . '" class="bg-slate-100 text-slate-700 px-2 py-1 rounded-md border border-slate-200 tracking-wider font-bold transition-all inline-block min-w-[75px] text-center">••••••••</span>'
        . '<button type="button" onclick="togglePassVisibility(this, \'pass-span-' . e($uId) . '\', \'' . e(addslashes($plainPass)) . '\')" class="p-1.5 text-slate-500 hover:text-slate-900 hover:bg-slate-200 rounded-md transition" title="Show/Hide Password">'
        . '<i class="fa-solid fa-eye text-xs"></i>'
        . '</button>'
        . '<button type="button" onclick="copyPassToClipboard(\'' . e(addslashes($plainPass)) . '\', this)" class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-md transition" title="Copy Password">'
        . '<i class="fa-solid fa-copy text-xs"></i>'
        . '</button>'
        . '</div>';

    $rowItem = [];
    $rowItem[] = '<div class="flex items-center gap-2.5">' .
        '<span class="w-8 h-8 rounded-full bg-slate-900 text-[#c2f13c] flex items-center justify-center font-bold text-xs flex-shrink-0">' . e($initial) . '</span>' .
        '<div>' .
        '<div class="flex items-center gap-1.5"><span class="font-bold text-slate-900">' . e($u['name']) . '</span><span class="font-mono text-[10px] text-slate-500 font-semibold bg-slate-100 border border-slate-200 px-1.5 py-0.2 rounded">' . e($uId) . '</span></div>' .
        '<div class="text-xs text-slate-500 font-mono">' . e($u['email']) . '</div>' .
        '</div></div>';

    $rowItem[] = $passwordHtml;

    if ($isSuper) {
        $rowItem[] = '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800 border border-slate-200"><i class="fa-solid fa-hospital text-[10px]"></i> ' . e((string)($u['org_name'] ?? $u['organization_id'] ?? 'ORG-001')) . '</span>';
    }

    $rowItem[] = '<span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">' . e(PORTALS[$portal]['label'] ?? $portal) . '</span>';
    $rowItem[] = '<span class="text-xs text-slate-600 font-medium">' . e((string)($u['branch_name'] ?? $u['branch_id'] ?? '—')) . '</span>';
    $rowItem[] = status_badge(!empty($u['is_active']) ? 'active' : 'pending');
    $rowItem[] = $checkboxesHtml;
    $rowItem[] = $actionsHtml;

    $userRows[] = $rowItem;
}

$tableHeaders = $isSuper 
    ? ['User ID & Staff Member', 'Password (Reveal)', 'Organization / Lab', 'Portal Role', 'Branch', 'Status', 'Configured Permissions', 'Actions']
    : ['User ID & Staff Member', 'Password (Reveal)', 'Portal Role', 'Branch', 'Status', 'Configured Permissions', 'Actions'];

// Create User Modal / Panel Form
ob_start();
?>
<div class="mb-6 p-5 bg-white border border-slate-200/90 rounded-3xl shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <div>
            <h3 class="text-base font-extrabold text-slate-900 mb-1 flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-slate-900"></i> Add New Staff Account &amp; Permissions
            </h3>
            <p class="text-xs text-slate-500">
                <?= $isSuper ? 'Create staff or operational users across client laboratories.' : 'Create staff accounts for ' . e($currentOrgName) . ' with custom module access.' ?>
            </p>
        </div>
        <?php if ($isSuper && !empty($organizations)): ?>
        <form method="get" class="flex items-center gap-2 text-xs">
            <label class="font-bold text-slate-600">Filter by Lab:</label>
            <select name="org_id" onchange="this.form.submit()" class="field text-xs py-1 px-2.5 bg-white border border-slate-300 rounded-lg">
                <option value="">All Laboratories (<?= count($users) ?> Users)</option>
                <?php foreach ($organizations as $org): ?>
                    <option value="<?= e($org['id']) ?>" <?= $selectedOrgFilter === $org['id'] ? 'selected' : '' ?>>
                        <?= e($org['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>
    
    <form method="post" class="space-y-4">
        <input type="hidden" name="action" value="create_user">
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-<?= $isSuper ? '6' : '5' ?> gap-3">
            <?php if ($isSuper): ?>
            <div>
                <label class="field-label" for="target_org_id">Assign to Lab</label>
                <select id="target_org_id" name="target_org_id" class="field text-sm">
                    <?php foreach ($organizations as $org): ?>
                        <option value="<?= e($org['id']) ?>" <?= $selectedOrgFilter === $org['id'] ? 'selected' : '' ?>>
                            <?= e($org['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div>
                <label class="field-label" for="u_name">Full Name</label>
                <input type="text" id="u_name" name="name" class="field text-sm" placeholder="e.g. Asif Mehmood" required>
            </div>
            <div>
                <label class="field-label" for="u_email">Email / Login ID</label>
                <input type="email" id="u_email" name="email" class="field text-sm" placeholder="staff@citylab.pk" required>
            </div>
            <div>
                <label class="field-label" for="u_pass">Initial Password</label>
                <input type="text" id="u_pass" name="password" class="field text-sm font-mono" placeholder="e.g. 1913" value="1913" required>
            </div>
            <div>
                <label class="field-label" for="u_portal">Portal Assignment</label>
                <select id="u_portal" name="portal" class="field text-sm" required>
                    <option value="collection_center">Collection Center (Satellite)</option>
                    <option value="main_lab">Laboratory (HQ Core)</option>
                    <option value="imaging">Diagnostic Center (X-Ray, CT, USG)</option>
                    <?php if ($isSuper): ?>
                    <option value="admin">Lab Administrator</option>
                    <?php endif; ?>
                </select>
            </div>
            <div>
                <label class="field-label" for="u_branch">Branch Assignment</label>
                <select id="u_branch" name="branch" class="field text-sm">
                    <option value="">-- Main Campus / Default --</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= e($br['id']) ?>"><?= e($br['name']) ?> (<?= e($br['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="border-t border-slate-100 pt-3">
            <label class="field-label text-xs font-bold text-slate-700 mb-2">Granular Permission Toggles</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                <?php foreach ($allPermissions as $pK => $pL): 
                    $checkedDefault = in_array($pK, ['view_results', 'process_billing', 'transit_dispatch'], true) ? ' checked' : '';
                ?>
                <label class="inline-flex items-center gap-2 p-2 bg-slate-50 hover:bg-slate-100 rounded-xl border border-slate-200/80 text-xs text-slate-700 cursor-pointer select-none">
                    <input type="checkbox" name="perm_<?= e($pK) ?>" value="1"<?= $checkedDefault ?> class="rounded text-slate-900">
                    <span><?= e($pL) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="btn btn-primary text-sm font-bold"><i class="fa-solid fa-user-check mr-1"></i> Create Account</button>
        </div>
    </form>
</div>

<!-- Edit User Modal Dialog -->
<div id="editUserModal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-2xl max-w-lg w-full relative animate-in fade-in zoom-in duration-150">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <h3 class="font-extrabold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-user-pen text-indigo-600"></i> Edit Staff Account &amp; Credentials
            </h3>
            <button type="button" onclick="closeEditUserModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        
        <form method="post" class="space-y-4">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="modal_edit_user_id" value="">

            <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80 text-xs flex items-center justify-between">
                <span class="text-slate-500 font-semibold">User ID:</span>
                <span id="modal_edit_display_id" class="font-mono font-bold text-slate-800 bg-white px-2 py-0.5 rounded border border-slate-300"></span>
            </div>

            <?php if ($isSuper && !empty($organizations)): ?>
            <div>
                <label class="field-label text-xs" for="modal_edit_target_org_id">Client Laboratory</label>
                <select id="modal_edit_target_org_id" name="target_org_id" class="field text-sm">
                    <?php foreach ($organizations as $org): ?>
                        <option value="<?= e($org['id']) ?>"><?= e($org['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div>
                <label class="field-label text-xs" for="modal_edit_name">Full Name</label>
                <input type="text" id="modal_edit_name" name="name" class="field text-sm" required>
            </div>

            <div>
                <label class="field-label text-xs" for="modal_edit_email">Email / Login ID</label>
                <input type="email" id="modal_edit_email" name="email" class="field text-sm font-mono" required>
            </div>

            <div>
                <label class="field-label text-xs flex items-center justify-between" for="modal_edit_password">
                    <span>Password (Plain / Editable)</span>
                    <span class="text-[10px] text-slate-400 font-normal">Leave as is or type new</span>
                </label>
                <div class="relative">
                    <input type="text" id="modal_edit_password" name="password" class="field text-sm font-mono pr-10" placeholder="New password" required>
                    <button type="button" onclick="generateRandomPass('modal_edit_password')" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-indigo-600" title="Generate New Password">
                        <i class="fa-solid fa-wand-magic-sparkles text-xs"></i>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="field-label text-xs" for="modal_edit_portal">Portal Role</label>
                    <select id="modal_edit_portal" name="portal" class="field text-sm" required>
                        <option value="collection_center">Collection Center</option>
                        <option value="main_lab">Laboratory (HQ)</option>
                        <option value="imaging">Diagnostic Center</option>
                        <?php if ($isSuper): ?>
                        <option value="admin">Lab Administrator</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label class="field-label text-xs" for="modal_edit_branch">Branch</label>
                    <select id="modal_edit_branch" name="branch" class="field text-sm">
                        <option value="">-- Main Campus / Default --</option>
                        <?php foreach ($branches as $br): ?>
                            <option value="<?= e($br['id']) ?>"><?= e($br['name']) ?> (<?= e($br['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeEditUserModal()" class="btn btn-secondary text-xs py-2 px-3.5 font-bold text-slate-700">Cancel</button>
                <button type="submit" class="btn btn-primary text-xs py-2 px-4 font-bold"><i class="fa-solid fa-save mr-1"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePassVisibility(btn, spanId, plainPass) {
    const span = document.getElementById(spanId);
    if (!span) return;
    const isMasked = span.textContent.trim() === '••••••••';
    if (isMasked) {
        span.textContent = plainPass || '(none)';
        span.classList.remove('bg-slate-100', 'text-slate-700');
        span.classList.add('bg-amber-100', 'text-amber-900');
        btn.querySelector('i').className = 'fa-solid fa-eye-slash text-xs text-amber-700';
    } else {
        span.textContent = '••••••••';
        span.classList.remove('bg-amber-100', 'text-amber-900');
        span.classList.add('bg-slate-100', 'text-slate-700');
        btn.querySelector('i').className = 'fa-solid fa-eye text-xs text-slate-500';
    }
}

function copyPassToClipboard(text, btn) {
    if (!navigator.clipboard) {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    } else {
        navigator.clipboard.writeText(text);
    }
    const icon = btn.querySelector('i');
    const oldClass = icon.className;
    icon.className = 'fa-solid fa-check text-xs text-emerald-600';
    setTimeout(() => {
        icon.className = oldClass;
    }, 1500);
}

function openEditUserModal(user) {
    document.getElementById('modal_edit_user_id').value = user.id || '';
    document.getElementById('modal_edit_display_id').textContent = user.id || '';
    document.getElementById('modal_edit_name').value = user.name || '';
    document.getElementById('modal_edit_email').value = user.email || '';
    document.getElementById('modal_edit_password').value = user.password || '';
    
    const portalSelect = document.getElementById('modal_edit_portal');
    if (portalSelect) portalSelect.value = user.portal || 'collection_center';
    
    const branchSelect = document.getElementById('modal_edit_branch');
    if (branchSelect) branchSelect.value = user.branch_id || '';
    
    const orgSelect = document.getElementById('modal_edit_target_org_id');
    if (orgSelect && user.organization_id) orgSelect.value = user.organization_id;

    document.getElementById('editUserModal').classList.remove('hidden');
}

function closeEditUserModal() {
    document.getElementById('editUserModal').classList.add('hidden');
}

function generateRandomPass(elementId) {
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
    let pass = '';
    for (let i = 0; i < 8; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById(elementId).value = pass;
}
</script>
<?php
$createForm = ob_get_clean();

$pageTitle = $isSuper ? 'Global Users & Role-Based Permissions' : 'Staff & Permissions (RBAC)';
$pageSubtitle = $isSuper
    ? 'Master user directory, credentials visibility, and access permissions across all client laboratories.'
    : 'Manage staff accounts, credentials, and access permissions for ' . e($currentOrgName) . '.';

$content = $msg . page_header($pageTitle, $pageSubtitle);
$content .= $createForm;
$content .= card(
    data_table($tableHeaders, $userRows),
    'overflow-hidden'
);

render_page($pageTitle, 'admin', 'users', $content);
