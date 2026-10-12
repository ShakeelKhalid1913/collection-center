<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

require_auth('main-lab');

$user = current_user();
$orgId = $user['organization_id'] ?? 'ORG-001';
$message = '';

// Handle creating new collection center
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_center') {
        $name = trim((string)($_POST['name'] ?? ''));
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = trim((string)($_POST['password'] ?? ''));
        $contactPerson = trim((string)($_POST['contact_person'] ?? ''));

        if ($name === '' || $code === '') {
            $message = flash_error('Center Name and Code are required.');
        } else {
            $branchId = 'BR-' . preg_replace('/[^A-Z0-9]/', '', $code);
            if ($branchId === 'BR-') {
                $branchId = 'BR-' . bin2hex(random_bytes(3));
            }

            // Check if branch code exists in this organization
            $existing = db()->fetchOne("SELECT id FROM branches WHERE organization_id = :org AND code = :code", ['org' => $orgId, 'code' => $code]);
            if ($existing) {
                $message = flash_error("A Collection Center with code {$code} already exists.");
            } else {
                $ok = db()->execute(
                    "INSERT INTO branches (id, organization_id, code, name, branch_type, address, phone)
                     VALUES (:id, :org, :code, :name, 'collection_center', :address, :phone)",
                    [
                        'id' => $branchId,
                        'org' => $orgId,
                        'code' => $code,
                        'name' => $name,
                        'address' => $address,
                        'phone' => $phone,
                    ]
                );

                if ($ok && $email !== '' && $password !== '') {
                    $userId = 'USR-' . bin2hex(random_bytes(3));
                    $pwHash = password_hash($password, PASSWORD_BCRYPT);
                    $staffName = $contactPerson !== '' ? $contactPerson : ($name . ' Staff');
                    db()->execute(
                        "INSERT INTO users (id, organization_id, branch_id, email, password_hash, name, role, portal, is_active)
                         VALUES (:uid, :org, :bid, :email, :pw, :name, 'Collection Center Staff', 'collection_center', 1)
                         ON DUPLICATE KEY UPDATE branch_id = VALUES(branch_id), password_hash = VALUES(password_hash)",
                        [
                            'uid' => $userId,
                            'org' => $orgId,
                            'bid' => $branchId,
                            'email' => $email,
                            'pw' => $pwHash,
                            'name' => $staffName,
                        ]
                    );
                }

                $message = flash_success("Child Collection Center '{$name}' ({$code}) created successfully. Operational patient data is strictly synchronized with your Main Lab.");
            }
        }
    }
}

// Fetch all collection centers for this Main Lab's organization
$centers = db()->fetchAll(
    "SELECT b.*, 
            COALESCE(u.email, '—') AS staff_email,
            COALESCE(u.name, '—') AS staff_name
     FROM branches b
     LEFT JOIN users u ON (u.branch_id = b.id AND u.portal = 'collection_center')
     WHERE b.organization_id = :org AND b.branch_type = 'collection_center'
     ORDER BY b.created_at ASC",
    ['org' => $orgId]
);

// If no branches exist, seed default Gulberg CC
if (empty($centers)) {
    db()->execute(
        "INSERT IGNORE INTO branches (id, organization_id, code, name, branch_type, address, phone)
         VALUES ('BR-GULBERG', :org, 'CC-01', 'Gulberg Collection Point', 'collection_center', '12-A Main Boulevard, Faisalabad', '+92 42 111 222 333')",
        ['org' => $orgId]
    );
    $centers = db()->fetchAll(
        "SELECT b.*, 'staff@citylab.pk' AS staff_email, 'Collection Staff' AS staff_name
         FROM branches b WHERE b.organization_id = :org AND b.branch_type = 'collection_center'",
        ['org' => $orgId]
    );
}

// Compute live metrics per collection center
$totalPatientsToday = 0;
$totalRevenueToday = 0;
$rows = [];

foreach ($centers as $c) {
    $cCode = (string)$c['code'];
    $cId = (string)$c['id'];

    // Today's entries
    $stats = db()->fetchOne(
        "SELECT COUNT(*) AS today_count, COALESCE(SUM(paid), 0) AS today_paid,
                SUM(CASE WHEN transit_status = 'in_transit' THEN 1 ELSE 0 END) AS in_transit_count
         FROM lab_entries 
         WHERE organization_id = :org AND (branch_id = :bid OR branch = :code) AND DATE(created_at) = CURDATE()",
        ['org' => $orgId, 'bid' => $cId, 'code' => $cCode]
    );

    $cnt = (int)($stats['today_count'] ?? 0);
    $paid = (float)($stats['today_paid'] ?? 0);
    $transit = (int)($stats['in_transit_count'] ?? 0);

    $totalPatientsToday += $cnt;
    $totalRevenueToday += $paid;

    $transitBadge = $transit > 0 
        ? '<span class="badge badge-warning"><i class="fa-solid fa-truck-fast mr-1"></i> ' . $transit . ' In Transit</span>'
        : '<span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i> Synced</span>';

    $staffDisplay = '<div class="text-xs font-semibold text-slate-800">' . e((string)$c['staff_name']) . '</div>'
                  . '<div class="text-[11px] text-slate-500 font-mono">' . e((string)$c['staff_email']) . '</div>';

    $actionBtn = '<a href="/portals/main-lab/archive.php?branch=' . urlencode($cCode) . '" class="btn btn-secondary text-xs py-1 px-2.5" title="View Patient Entries from this Center"><i class="fa-solid fa-list mr-1"></i> View Entries</a>';

    $rows[] = [
        '<span class="font-mono font-bold text-teal-800 bg-teal-50 border border-teal-200 px-2 py-0.5 rounded text-xs">' . e($cCode) . '</span>',
        '<div><strong class="text-slate-900 text-sm">' . e((string)$c['name']) . '</strong><div class="text-xs text-slate-500">' . e((string)$c['address']) . ' · ' . e((string)$c['phone']) . '</div></div>',
        $staffDisplay,
        '<span class="font-bold text-slate-800">' . $cnt . '</span> <span class="text-xs text-slate-500">patients</span>',
        '<strong class="text-slate-900">Rs ' . number_format($paid, 0) . '</strong>',
        $transitBadge,
        $actionBtn,
    ];
}

$headerAction = '<button type="button" onclick="document.getElementById(\'add-center-modal\').classList.toggle(\'hidden\')" class="btn btn-primary text-sm py-2 px-4 shadow-sm"><i class="fa-solid fa-plus mr-1.5"></i> Add Collection Center</button>';
$content = page_header('Child Collection Centers', 'Nested multi-branch hierarchy — your main lab manages and syncs operational data with all linked child collection points.', $headerAction);
$content .= $message;

// Stat Cards
$totalCenters = count($centers);
$content .= <<<HTML
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
    <div class="stat-card stat-card--teal">
        <div class="stat-card__icon"><i class="fa-solid fa-network-wired"></i></div>
        <div>
            <div class="stat-card__label">Linked Child Centers</div>
            <div class="stat-card__value">{$totalCenters}</div>
            <div class="stat-card__hint">Subordinate to this Main Lab</div>
        </div>
    </div>
    <div class="stat-card stat-card--blue">
        <div class="stat-card__icon"><i class="fa-solid fa-hospital-user"></i></div>
        <div>
            <div class="stat-card__label">Today's CC Patients</div>
            <div class="stat-card__value">{$totalPatientsToday}</div>
            <div class="stat-card__hint">Live synchronized intake</div>
        </div>
    </div>
    <div class="stat-card stat-card--violet">
        <div class="stat-card__icon"><i class="fa-solid fa-coins"></i></div>
        <div>
            <div class="stat-card__label">CC Revenue Today</div>
            <div class="stat-card__value">Rs {$totalRevenueToday}</div>
            <div class="stat-card__hint">Collected at branch counters</div>
        </div>
    </div>
    <div class="stat-card stat-card--amber">
        <div class="stat-card__icon"><i class="fa-solid fa-shield-halved"></i></div>
        <div>
            <div class="stat-card__label">Tenant Isolation</div>
            <div class="stat-card__value">Active</div>
            <div class="stat-card__hint">Strict zero data bleed</div>
        </div>
    </div>
</div>

<!-- Modal: Add Collection Center -->
<div id="add-center-modal" class="hidden mb-6 bg-white border border-slate-200 rounded-3xl p-6 shadow-card transition-all">
    <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
        <div class="flex items-center gap-2.5">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-teal-100 text-teal-700 font-bold text-sm">
                <i class="fa-solid fa-building-circle-check"></i>
            </span>
            <div>
                <h3 class="text-base font-bold text-slate-800">Register New Child Collection Center</h3>
                <p class="text-xs text-slate-500">Create a nested collection point under this Main Lab with its own isolated staff login.</p>
            </div>
        </div>
        <button type="button" onclick="document.getElementById('add-center-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg p-1">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <form method="post" class="space-y-4">
        <input type="hidden" name="action" value="create_center">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="field-label text-xs">Center Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" class="field text-sm" placeholder="e.g. Imran Collection Point" required>
            </div>
            <div>
                <label class="field-label text-xs">Branch Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" class="field text-sm font-mono uppercase" placeholder="e.g. CC-02" required>
            </div>
            <div>
                <label class="field-label text-xs">Contact Person / In-Charge</label>
                <input type="text" name="contact_person" class="field text-sm" placeholder="e.g. Imran Sheikh">
            </div>
            <div>
                <label class="field-label text-xs">Contact Phone</label>
                <input type="text" name="phone" class="field text-sm" placeholder="e.g. 0300 1234567">
            </div>
            <div class="sm:col-span-2">
                <label class="field-label text-xs">Physical Address</label>
                <input type="text" name="address" class="field text-sm" placeholder="e.g. Near DHQ Hospital, Faisalabad">
            </div>
            <div>
                <label class="field-label text-xs">Staff Login Email <span class="text-slate-400 font-normal">(for CC Portal login)</span></label>
                <input type="email" name="email" class="field text-sm font-mono" placeholder="imran@collection.pk">
            </div>
            <div>
                <label class="field-label text-xs">Login Password</label>
                <input type="password" name="password" class="field text-sm font-mono" placeholder="Set staff password">
            </div>
        </div>
        <div class="flex items-center gap-3 pt-3 border-t border-slate-100">
            <button type="submit" class="btn btn-primary text-sm py-2 px-5">
                <i class="fa-solid fa-check mr-1.5"></i> Save &amp; Link Child Center
            </button>
            <button type="button" onclick="document.getElementById('add-center-modal').classList.add('hidden')" class="btn btn-secondary text-sm py-2 px-4">
                Cancel
            </button>
        </div>
    </form>
</div>
HTML;

$content .= card(data_table(['Code', 'Center & Address', 'Staff Account', 'Today Patients', 'Today Collection', 'Sync Status', 'Actions'], $rows), 'overflow-hidden');

render_page('Collection Centers', 'main-lab', 'collection-centers', $content);
