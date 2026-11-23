<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

require_auth('admin');

// Super Admin access gate
if (!is_super_admin()) {
    header('Location: /portals/dashboard.php');
    exit;
}

$msg = '';

// Handle creating new company
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_org') {
    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $adminName = trim((string)($_POST['admin_name'] ?? ''));
    $adminEmail = trim(strtolower((string)($_POST['admin_email'] ?? '')));
    $adminPass = (string)($_POST['admin_password'] ?? '');

    if ($name === '') {
        $msg = flash_error('Company / Diagnostic center name is required.');
    } else {
        $res = org_repo()->createOrganization([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'admin_name' => $adminName !== '' ? $adminName : ($name . ' Admin'),
            'admin_email' => $adminEmail,
            'admin_password' => $adminPass,
        ]);

        if (!empty($res['success'])) {
            audit_log('CREATE_ORGANIZATION', 'organizations', $res['id'], "Registered new company {$name}");
            $credentialsNote = ($adminEmail !== '' && $adminPass !== '') 
                ? " [Admin Account: {$adminEmail} | Password: {$adminPass}]"
                : '';
            $msg = flash_success("Company '{$name}' (ID: {$res['id']}) created successfully with isolated catalog and main lab branch.{$credentialsNote}");
        } else {
            $msg = flash_error($res['error'] ?? 'Could not create company.');
        }
    }
}

// Handle adding admin credentials to existing company
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_org_admin') {
    $targetOrgId = trim((string)($_POST['target_org_id'] ?? ''));
    $adminName = trim((string)($_POST['admin_name'] ?? ''));
    $adminEmail = trim(strtolower((string)($_POST['admin_email'] ?? '')));
    $adminPass = (string)($_POST['admin_password'] ?? '');

    if ($targetOrgId === '' || $adminEmail === '' || $adminPass === '') {
        $msg = flash_error('Organization, Admin Email, and Admin Password are all required.');
    } else {
        // Find main lab branch for this organization or null
        $branches = db()->fetchAll("SELECT id FROM branches WHERE organization_id = :oid LIMIT 1", ['oid' => $targetOrgId]);
        $branchId = !empty($branches[0]['id']) ? $branches[0]['id'] : null;

        $userRes = user_repo()->createUser([
            'organization_id' => $targetOrgId,
            'branch_id' => $branchId,
            'email' => $adminEmail,
            'password' => $adminPass,
            'name' => $adminName !== '' ? $adminName : 'Company Admin',
            'portal' => 'admin',
            'role' => 'Company Admin',
            'permissions' => [
                'booking_create' => 1,
                'booking_cancel' => 1,
                'report_edit' => 1,
                'report_verify' => 1,
                'report_publish' => 1,
                'rates_edit' => 1,
                'users_manage' => 1,
                'header_footer_edit' => 1,
                'view_results' => 1,
                'view_financials' => 1,
                'view_audit' => 1,
            ],
        ]);

        if (!empty($userRes['success'])) {
            audit_log('CREATE_COMPANY_ADMIN', 'users', $adminEmail, "Generated company admin for {$targetOrgId}");
            $msg = flash_success("New Company Admin '{$adminEmail}' created successfully for organization {$targetOrgId}.");
        } else {
            $msg = flash_error($userRes['error'] ?? 'Failed to generate admin account.');
        }
    }
}

// Handle status toggle (active / suspended)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_org_status') {
    $targetOrgId = trim((string)($_POST['org_id'] ?? ''));
    $newStatus = (int)($_POST['status'] ?? 1);
    if ($targetOrgId !== '') {
        org_repo()->updateOrganization($targetOrgId, ['is_active' => $newStatus]);
        audit_log('TOGGLE_ORG_STATUS', 'organizations', $targetOrgId, "Set active status to {$newStatus}");
        $statusLabel = $newStatus === 1 ? 'activated' : 'suspended';
        $msg = flash_success("Company '{$targetOrgId}' has been {$statusLabel}.");
    }
}

// Handle edit company
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_org') {
    $targetOrgId = trim((string)($_POST['org_id'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));

    if ($targetOrgId === '' || $name === '') {
        $msg = flash_error('Company ID and Name are required.');
    } else {
        $ok = org_repo()->updateOrganization($targetOrgId, [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
        ]);
        if ($ok) {
            audit_log('EDIT_ORGANIZATION', 'organizations', $targetOrgId, "Updated company details for {$name}");
            $msg = flash_success("Company '{$name}' ({$targetOrgId}) updated successfully.");
        } else {
            $msg = flash_error('Failed to update company.');
        }
    }
}

// Handle delete company
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_org') {
    $targetOrgId = trim((string)($_POST['org_id'] ?? ''));
    if ($targetOrgId === 'ORG-001') {
        $msg = flash_error('Cannot delete the primary default organization.');
    } else {
        $ok = org_repo()->deleteOrganization($targetOrgId);
        if ($ok) {
            audit_log('DELETE_ORGANIZATION', 'organizations', $targetOrgId, "Deleted organization {$targetOrgId}");
            $msg = flash_success("Company '{$targetOrgId}' has been deleted successfully.");
        } else {
            $msg = flash_error('Failed to delete company.');
        }
    }
}

$organizations = org_repo()->getAll();

// Metrics
$totalOrgs = count($organizations);
$activeOrgs = 0;
$totalBranches = 0;
$totalUsers = 0;
foreach ($organizations as $org) {
    if (!empty($org['is_active'])) $activeOrgs++;
    $totalBranches += (int)($org['branch_count'] ?? 0);
    $totalUsers += (int)($org['user_count'] ?? 0);
}

// Build table rows
$rows = [];
foreach ($organizations as $org) {
    $oid = (string)$org['id'];
    $isActive = (int)($org['is_active'] ?? 1) === 1;

    $idBadge = '<span class="font-mono font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded text-xs border border-indigo-200">' . e($oid) . '</span>';
    
    $nameHtml = '<div>' .
        '<div class="font-bold text-slate-900 text-sm">' . e($org['name']) . '</div>' .
        '<div class="text-xs text-slate-500 mt-0.5"><i class="fa-solid fa-location-dot text-slate-400 mr-1"></i>' . e($org['address'] ?: 'No address specified') . '</div>' .
        '</div>';

    $contactHtml = '<div class="text-xs space-y-0.5">' .
        ($org['phone'] ? '<div><i class="fa-solid fa-phone text-slate-400 mr-1"></i>' . e($org['phone']) . '</div>' : '') .
        ($org['email'] ? '<div><i class="fa-solid fa-envelope text-slate-400 mr-1"></i>' . e($org['email']) . '</div>' : '') .
        '</div>';

    $statsHtml = '<div class="flex items-center gap-2 text-xs">' .
        '<span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-2 py-0.5 rounded font-medium" title="Branches"><i class="fa-solid fa-sitemap text-slate-400"></i> ' . (int)$org['branch_count'] . '</span>' .
        '<span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-2 py-0.5 rounded font-medium" title="Staff Accounts"><i class="fa-solid fa-users text-slate-400"></i> ' . (int)$org['user_count'] . '</span>' .
        '<span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-2 py-0.5 rounded font-medium" title="Patients"><i class="fa-solid fa-hospital-user text-slate-400"></i> ' . (int)$org['patient_count'] . '</span>' .
        '</div>';

    $statusHtml = $isActive
        ? '<span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span> Active</span>'
        : '<span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-800"><span class="w-1.5 h-1.5 rounded-full bg-rose-600"></span> Suspended</span>';

    $orgDataJson = htmlspecialchars(json_encode([
        'id' => $oid,
        'name' => $org['name'] ?? '',
        'phone' => $org['phone'] ?? '',
        'email' => $org['email'] ?? '',
        'address' => $org['address'] ?? ''
    ]), ENT_QUOTES, 'UTF-8');

    $actionsHtml = '<div class="flex items-center gap-1.5 justify-end flex-wrap">';
    
    // Quick Add Admin Trigger
    $actionsHtml .= '<button type="button" onclick="openAdminModal(\'' . e($oid) . '\', \'' . e(addslashes($org['name'])) . '\')" class="px-2 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded text-xs font-medium transition" title="Generate Admin Account"><i class="fa-solid fa-user-plus mr-1"></i> Add Admin</button>';

    // Edit Company
    $actionsHtml .= '<button type="button" onclick=\'openEditOrgModal(' . $orgDataJson . ')\' class="px-2 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded text-xs font-medium transition" title="Edit Company Details"><i class="fa-solid fa-pen-to-square mr-1"></i> Edit</button>';

    // View Users
    $actionsHtml .= '<a href="/portals/admin/users.php?org_id=' . urlencode($oid) . '" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 rounded text-xs font-medium transition" title="View Users & Passwords"><i class="fa-solid fa-users mr-1"></i> Users</a>';

    // Header & Footer Letterhead
    $actionsHtml .= '<a href="/portals/branding.php?org=' . urlencode($oid) . '" class="px-2 py-1 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 rounded text-xs font-medium transition" title="Configure Header & Footer Letterhead"><i class="fa-solid fa-heading mr-1"></i> Header/Footer</a>';

    // Toggle Status Form
    $actionsHtml .= '<form method="post" class="inline m-0" onsubmit="return confirm(\'Are you sure you want to ' . ($isActive ? 'suspend' : 'activate') . ' ' . e(addslashes($org['name'])) . '?\');">';
    $actionsHtml .= '<input type="hidden" name="action" value="toggle_org_status">';
    $actionsHtml .= '<input type="hidden" name="org_id" value="' . e($oid) . '">';
    $actionsHtml .= '<input type="hidden" name="status" value="' . ($isActive ? 0 : 1) . '">';
    if ($isActive) {
        $actionsHtml .= '<button type="submit" class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 rounded text-xs font-medium transition" title="Suspend Organization"><i class="fa-solid fa-ban mr-1"></i> Suspend</button>';
    } else {
        $actionsHtml .= '<button type="submit" class="px-2 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded text-xs font-medium transition" title="Activate Organization"><i class="fa-solid fa-check mr-1"></i> Activate</button>';
    }
    $actionsHtml .= '</form>';

    // Delete Company (except default ORG-001)
    if ($oid !== 'ORG-001') {
        $actionsHtml .= '<form method="post" class="inline m-0" onsubmit="return confirm(\'Are you sure you want to delete ' . e(addslashes($org['name'])) . ' (' . e($oid) . ')? All branches and users will be deleted.\');">';
        $actionsHtml .= '<input type="hidden" name="action" value="delete_org">';
        $actionsHtml .= '<input type="hidden" name="org_id" value="' . e($oid) . '">';
        $actionsHtml .= '<button type="submit" class="px-2 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded text-xs font-medium transition" title="Delete Organization"><i class="fa-solid fa-trash mr-1"></i> Delete</button>';
        $actionsHtml .= '</form>';
    }

    $actionsHtml .= '</div>';

    $rows[] = [$idBadge, $nameHtml, $contactHtml, $statsHtml, $statusHtml, $actionsHtml];
}

$pageHeader = page_header('Platform Client Companies', 'Multi-tenant diagnostic center management and administrator credential generation.');

$statsCardsHtml = '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">' .
    '<div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex items-center justify-between">' .
        '<div><p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Companies</p><p class="text-2xl font-bold text-slate-800 mt-1">' . $totalOrgs . '</p></div>' .
        '<div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-lg"><i class="fa-solid fa-building-columns"></i></div>' .
    '</div>' .
    '<div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex items-center justify-between">' .
        '<div><p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Active Clients</p><p class="text-2xl font-bold text-emerald-600 mt-1">' . $activeOrgs . '</p></div>' .
        '<div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg"><i class="fa-solid fa-circle-check"></i></div>' .
    '</div>' .
    '<div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex items-center justify-between">' .
        '<div><p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Branches</p><p class="text-2xl font-bold text-indigo-600 mt-1">' . $totalBranches . '</p></div>' .
        '<div class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg"><i class="fa-solid fa-sitemap"></i></div>' .
    '</div>' .
    '<div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex items-center justify-between">' .
        '<div><p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Staff Accounts</p><p class="text-2xl font-bold text-amber-600 mt-1">' . $totalUsers . '</p></div>' .
        '<div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-lg"><i class="fa-solid fa-users"></i></div>' .
    '</div>' .
'</div>';

// Form card to register new diagnostic center company
$registerCard = '<div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm mb-6">' .
    '<div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-3">' .
        '<div class="flex items-center gap-2">' .
            '<div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fa-solid fa-plus text-sm"></i></div>' .
            '<div><h3 class="font-bold text-slate-800 text-sm">Register New Diagnostic Company</h3><p class="text-xs text-slate-500">Creates isolated database records, branding configuration, and initial administrator credentials.</p></div>' .
        '</div>' .
    '</div>' .
    '<form method="post" class="space-y-4">' .
        '<input type="hidden" name="action" value="create_org">' .
        '<div class="grid grid-cols-1 md:grid-cols-3 gap-3">' .
            '<div>' .
                '<label class="block text-xs font-semibold text-slate-700 mb-1">Company / Lab Name <span class="text-rose-500">*</span></label>' .
                '<input type="text" name="name" required placeholder="e.g. Al-Razi Diagnostic Complex" class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">' .
            '</div>' .
            '<div>' .
                '<label class="block text-xs font-semibold text-slate-700 mb-1">Official Phone</label>' .
                '<input type="text" name="phone" placeholder="e.g. +92 41 1234567" class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">' .
            '</div>' .
            '<div>' .
                '<label class="block text-xs font-semibold text-slate-700 mb-1">Official Email</label>' .
                '<input type="email" name="email" placeholder="e.g. contact@alrazi.pk" class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">' .
            '</div>' .
        '</div>' .
        '<div>' .
            '<label class="block text-xs font-semibold text-slate-700 mb-1">Address / Location</label>' .
            '<input type="text" name="address" placeholder="e.g. 14 Medical Enclave, Jail Road, Faisalabad" class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">' .
        '</div>' .
        '<div class="bg-slate-50 p-3 rounded-lg border border-slate-200/80">' .
            '<p class="text-xs font-bold text-slate-800 mb-2.5 flex items-center gap-1.5"><i class="fa-solid fa-user-shield text-indigo-600"></i> Initial Company Admin Account (Credentials)</p>' .
            '<div class="grid grid-cols-1 md:grid-cols-3 gap-3">' .
                '<div>' .
                    '<label class="block text-[11px] font-semibold text-slate-600 mb-1">Admin Full Name</label>' .
                    '<input type="text" name="admin_name" placeholder="e.g. Dr. Salman Qureshi" class="w-full text-xs px-2.5 py-1.5 bg-white border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">' .
                '</div>' .
                '<div>' .
                    '<label class="block text-[11px] font-semibold text-slate-600 mb-1">Admin Login Email</label>' .
                    '<input type="email" name="admin_email" placeholder="e.g. admin@alrazi.pk" class="w-full text-xs px-2.5 py-1.5 bg-white border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">' .
                '</div>' .
                '<div>' .
                    '<label class="block text-[11px] font-semibold text-slate-600 mb-1">Admin Password</label>' .
                    '<div class="flex items-center gap-1">' .
                        '<input type="text" name="admin_password" id="newAdminPass" placeholder="e.g. Pass123!" class="w-full text-xs px-2.5 py-1.5 bg-white border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">' .
                        '<button type="button" onclick="generateRandomPass(\'newAdminPass\')" class="px-2 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs rounded border border-slate-300 font-medium" title="Generate Password"><i class="fa-solid fa-wand-magic-sparkles"></i></button>' .
                    '</div>' .
                '</div>' .
            '</div>' .
        '</div>' .
        '<div class="flex justify-end">' .
            '<button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-sm transition flex items-center gap-1.5"><i class="fa-solid fa-plus"></i> Create Client Company & Admin</button>' .
        '</div>' .
    '</form>' .
'</div>';

// Modal for adding admin to existing org
$modalHtml = '<div id="adminModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center hidden">' .
    '<div class="bg-white rounded-xl border border-slate-200 p-5 shadow-2xl max-w-md w-full mx-4">' .
        '<div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">' .
            '<h3 class="font-bold text-slate-800 text-sm flex items-center gap-2"><i class="fa-solid fa-user-plus text-indigo-600"></i> Generate Company Admin</h3>' .
            '<button type="button" onclick="closeAdminModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>' .
        '</div>' .
        '<form method="post" class="space-y-3">' .
            '<input type="hidden" name="action" value="create_org_admin">' .
            '<input type="hidden" name="target_org_id" id="modalTargetOrgId" value="">' .
            '<div>' .
                '<p class="text-xs text-slate-500 mb-2">Company: <strong id="modalTargetOrgName" class="text-slate-800"></strong></p>' .
            '</div>' .
            '<div>' .
                '<label class="block text-xs font-semibold text-slate-700 mb-1">Admin Full Name</label>' .
                '<input type="text" name="admin_name" placeholder="e.g. Laboratory Director" class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">' .
            '</div>' .
            '<div>' .
                '<label class="block text-xs font-semibold text-slate-700 mb-1">Admin Email <span class="text-rose-500">*</span></label>' .
                '<input type="email" name="admin_email" required placeholder="e.g. director@company.pk" class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">' .
            '</div>' .
            '<div>' .
                '<label class="block text-xs font-semibold text-slate-700 mb-1">Password <span class="text-rose-500">*</span></label>' .
                '<div class="flex items-center gap-1">' .
                    '<input type="text" name="admin_password" id="modalAdminPass" required placeholder="Choose a password" class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">' .
                    '<button type="button" onclick="generateRandomPass(\'modalAdminPass\')" class="px-2.5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs rounded-lg border border-slate-300 font-medium" title="Generate Password"><i class="fa-solid fa-wand-magic-sparkles"></i></button>' .
                '</div>' .
            '</div>' .
            '<div class="flex justify-end gap-2 pt-3 border-t border-slate-100">' .
                '<button type="button" onclick="closeAdminModal()" class="px-3 py-1.5 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-medium">Cancel</button>' .
                '<button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-sm">Save & Generate Credentials</button>' .
            '</div>' .
        '</form>' .
    '</div>' .
'</div>';

// Modal for editing company
$editOrgModalHtml = '<div id="editOrgModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center hidden">' .
    '<div class="bg-white rounded-xl border border-slate-200 p-5 shadow-2xl max-w-md w-full mx-4">' .
        '<div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">' .
            '<h3 class="font-bold text-slate-800 text-sm flex items-center gap-2"><i class="fa-solid fa-pen-to-square text-indigo-600"></i> Edit Company Details</h3>' .
            '<button type="button" onclick="closeEditOrgModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>' .
        '</div>' .
        '<form method="post" class="space-y-3">' .
            '<input type="hidden" name="action" value="edit_org">' .
            '<input type="hidden" name="org_id" id="editOrgModalId" value="">' .
            '<div>' .
                '<label class="block text-xs font-semibold text-slate-700 mb-1">Company Name <span class="text-rose-500">*</span></label>' .
                '<input type="text" name="name" id="editOrgModalName" required class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg">' .
            '</div>' .
            '<div>' .
                '<label class="block text-xs font-semibold text-slate-700 mb-1">Official Phone</label>' .
                '<input type="text" name="phone" id="editOrgModalPhone" class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg">' .
            '</div>' .
            '<div>' .
                '<label class="block text-xs font-semibold text-slate-700 mb-1">Official Email</label>' .
                '<input type="email" name="email" id="editOrgModalEmail" class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg">' .
            '</div>' .
            '<div>' .
                '<label class="block text-xs font-semibold text-slate-700 mb-1">Address / Location</label>' .
                '<input type="text" name="address" id="editOrgModalAddress" class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg">' .
            '</div>' .
            '<div class="flex justify-end gap-2 pt-3 border-t border-slate-100">' .
                '<button type="button" onclick="closeEditOrgModal()" class="px-3 py-1.5 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-medium">Cancel</button>' .
                '<button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-sm">Save Changes</button>' .
            '</div>' .
        '</form>' .
    '</div>' .
'</div>';

$scriptHtml = '<script>
function openAdminModal(orgId, orgName) {
    document.getElementById("modalTargetOrgId").value = orgId;
    document.getElementById("modalTargetOrgName").innerText = orgName;
    document.getElementById("adminModal").classList.remove("hidden");
}
function closeAdminModal() {
    document.getElementById("adminModal").classList.add("hidden");
}
function openEditOrgModal(org) {
    document.getElementById("editOrgModalId").value = org.id || "";
    document.getElementById("editOrgModalName").value = org.name || "";
    document.getElementById("editOrgModalPhone").value = org.phone || "";
    document.getElementById("editOrgModalEmail").value = org.email || "";
    document.getElementById("editOrgModalAddress").value = org.address || "";
    document.getElementById("editOrgModal").classList.remove("hidden");
}
function closeEditOrgModal() {
    document.getElementById("editOrgModal").classList.add("hidden");
}
function generateRandomPass(elementId) {
    var chars = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%";
    var pass = "";
    for (var i = 0; i < 10; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById(elementId).value = pass;
}
</script>';

$tableCard = card(data_table(['ID', 'Company Name & Location', 'Contact Details', 'Statistics', 'Status', 'Actions'], $rows), 'overflow-hidden');

$content = $msg . $pageHeader . $statsCardsHtml . $registerCard . $tableCard . $modalHtml . $editOrgModalHtml . $scriptHtml;

render_page('Client Companies', 'admin', 'organizations', $content);
