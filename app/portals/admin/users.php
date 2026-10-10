<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

require_auth('admin');

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$msg = '';

// Available permission toggles
$allPermissions = [
    'header_footer_edit' => 'Header / Footer Customization',
    'rates_edit' => 'Rate Lists & Price Modification',
    'report_edit' => 'Report & Result Outcome Editing',
    'view_results' => 'View Completed Results',
    'process_billing' => 'Process Billing & Record Payments',
    'transit_dispatch' => 'Sample Transit Dispatch & Barcode Status',
];

// Handle new user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $email = trim((string)($_POST['email'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $portal = trim((string)($_POST['portal'] ?? 'collection_center'));
    $branch = trim((string)($_POST['branch'] ?? 'BR-GULBERG'));
    
    // Collect toggles
    $perms = [];
    foreach (array_keys($allPermissions) as $pKey) {
        $perms[$pKey] = !empty($_POST['perm_' . $pKey]) ? 1 : 0;
    }

    $res = user_repo()->createUser([
        'organization_id' => $orgId,
        'branch_id' => $branch,
        'email' => $email,
        'name' => $name,
        'password' => $password,
        'portal' => $portal,
        'permissions' => $perms,
    ]);

    if (!empty($res['success'])) {
        audit_log('CREATE_USER', 'users', $email, "Created user account for {$name} ({$portal})");
        $msg = flash_success("User {$name} ({$email}) created successfully with configured permissions.");
    } else {
        $msg = flash_error($res['error'] ?? 'Failed to create user account.');
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

$users = user_repo()->getAll($orgId);

$userRows = [];
foreach ($users as $u) {
    $uId = (string)$u['id'];
    $portal = portal_from_db((string)($u['portal'] ?? ''));
    $initial = strtoupper(substr((string)($u['name'] ?? 'U'), 0, 1));
    $userPerms = !empty($u['permissions']) ? (is_array($u['permissions']) ? $u['permissions'] : (json_decode((string)$u['permissions'], true) ?: [])) : [];

    // Build permission checkboxes form
    $checkboxesHtml = '<form method="post" class="space-y-1.5">';
    $checkboxesHtml .= '<input type="hidden" name="action" value="update_permissions">';
    $checkboxesHtml .= '<input type="hidden" name="user_id" value="' . e($uId) . '">';
    $checkboxesHtml .= '<div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 text-xs text-slate-700">';

    foreach ($allPermissions as $pKey => $pLabel) {
        $isChecked = !empty($userPerms[$pKey]) || ($portal === 'admin');
        $checkedAttr = $isChecked ? ' checked' : '';
        $disabledAttr = ($portal === 'admin') ? ' disabled' : '';
        $checkboxesHtml .= '<label class="inline-flex items-center gap-1.5 cursor-pointer bg-slate-50 hover:bg-slate-100 p-1 rounded border border-slate-200/70 select-none">'
            . '<input type="checkbox" name="user_perm_' . e($uId) . '_' . e($pKey) . '" value="1"' . $checkedAttr . $disabledAttr . ' class="rounded text-blue-600 focus:ring-0">'
            . '<span class="truncate">' . e($pLabel) . '</span>'
            . '</label>';
    }
    $checkboxesHtml .= '</div>';
    if ($portal !== 'admin') {
        $checkboxesHtml .= '<button type="submit" class="btn btn-secondary text-xs py-1 px-2.5 mt-1 font-semibold text-blue-700 hover:bg-blue-50"><i class="fa-solid fa-save mr-1"></i> Save Toggles</button>';
    }
    $checkboxesHtml .= '</form>';

    $statusBtn = '';
    if ($portal !== 'admin') {
        $isAct = !empty($u['is_active']);
        $nextStatus = $isAct ? 0 : 1;
        $btnText = $isAct ? 'Deactivate' : 'Activate';
        $btnClass = $isAct ? 'text-rose-600 hover:bg-rose-50' : 'text-emerald-600 hover:bg-emerald-50';
        $statusBtn = '<form method="post" class="inline m-0">'
            . '<input type="hidden" name="action" value="toggle_status">'
            . '<input type="hidden" name="user_id" value="' . e($uId) . '">'
            . '<input type="hidden" name="status" value="' . $nextStatus . '">'
            . '<button type="submit" class="btn btn-secondary text-xs py-1 px-2 font-semibold ' . $btnClass . '">' . $btnText . '</button>'
            . '</form>';
    }

    $userRows[] = [
        '<div class="flex items-center gap-2.5">' .
        '<span class="w-8 h-8 rounded-full bg-slate-800 text-[#c2f13c] flex items-center justify-center font-bold text-xs flex-shrink-0">' . e($initial) . '</span>' .
        '<div>' .
        '<div class="font-bold text-slate-900">' . e($u['name']) . '</div>' .
        '<div class="text-xs text-slate-500 font-mono">' . e($u['email']) . '</div>' .
        '</div></div>',
        '<span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">' . e(PORTALS[$portal]['label'] ?? $portal) . '</span>',
        '<span class="text-xs text-slate-600 font-medium">' . e((string)($u['branch_name'] ?? $u['branch_id'] ?? '—')) . '</span>',
        status_badge(!empty($u['is_active']) ? 'active' : 'pending'),
        $checkboxesHtml,
        $statusBtn,
    ];
}

// Create User Modal / Panel Form
$createForm = <<<HTML
<div class="mb-6 p-5 bg-white border border-slate-200/90 rounded-3xl shadow-sm">
    <h3 class="text-base font-extrabold text-slate-900 mb-2"><i class="fa-solid fa-user-plus text-blue-600 mr-1.5"></i> Add New User &amp; Granular Permissions</h3>
    <p class="text-xs text-slate-500 mb-4">Create staff accounts for Collection Centers, Main Laboratory, or Diagnostic Imaging with custom module access.</p>
    
    <form method="post" class="space-y-4">
        <input type="hidden" name="action" value="create_user">
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="field-label" for="u_name">Full Name</label>
                <input type="text" id="u_name" name="name" class="field text-sm" placeholder="e.g. Asif Mehmood" required>
            </div>
            <div>
                <label class="field-label" for="u_email">Email Address</label>
                <input type="email" id="u_email" name="email" class="field text-sm" placeholder="asif@citylab.pk" required>
            </div>
            <div>
                <label class="field-label" for="u_pass">Initial Password</label>
                <input type="password" id="u_pass" name="password" class="field text-sm" placeholder="••••••••" required>
            </div>
            <div>
                <label class="field-label" for="u_portal">Portal Assignment</label>
                <select id="u_portal" name="portal" class="field text-sm" required>
                    <option value="collection_center">Collection Center (Satellite)</option>
                    <option value="main_lab">Laboratory (HQ Core)</option>
                    <option value="imaging">Diagnostic Center (X-Ray, CT, USG)</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
        </div>

        <div class="border-t border-slate-100 pt-3">
            <label class="field-label text-xs font-bold text-slate-700 mb-2">Granular Permission Toggles</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
HTML;

foreach ($allPermissions as $pK => $pL) {
    $checkedDefault = in_array($pK, ['view_results', 'process_billing', 'transit_dispatch'], true) ? ' checked' : '';
    $createForm .= <<<HTML
    <label class="inline-flex items-center gap-2 p-2 bg-slate-50 hover:bg-slate-100 rounded-xl border border-slate-200/80 text-xs text-slate-700 cursor-pointer select-none">
        <input type="checkbox" name="perm_{$pK}" value="1"{$checkedDefault} class="rounded text-blue-600">
        <span>{$pL}</span>
    </label>
    HTML;
}

$createForm .= <<<HTML
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="btn btn-primary text-sm font-bold"><i class="fa-solid fa-user-check mr-1"></i> Create Account</button>
        </div>
    </form>
</div>
HTML;

$content = $msg . page_header(
    'Role-Based Access Control (RBAC)',
    'Create user accounts and configure granular permission toggles for satellite centers and diagnostic departments.'
);

$content .= $createForm;
$content .= card(
    data_table(['User Details', 'Portal Role', 'Branch', 'Status', 'Configured Permissions', 'Actions'], $userRows),
    'overflow-hidden'
);

render_page('Users & Permissions', 'admin', 'users', $content);
