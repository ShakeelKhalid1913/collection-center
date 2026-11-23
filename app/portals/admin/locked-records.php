<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

require_auth('admin');

$orgId = current_organization_id();
$orgName = current_organization_name();
$isSuper = is_super_admin();
$message = '';

// Handle Admin Unlock / Lock actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['lab_no'])) {
    $postLabNo = trim((string)$_POST['lab_no']);
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'unlock_bill') {
        lab_repo()->unlockBill($postLabNo);
        audit_log('UNLOCK_BILL', 'lab_entries', $postLabNo, 'Bill unlocked via Admin Console');
        $message = flash_success("Bill for {$postLabNo} has been unlocked successfully. Staff can now edit this bill.");
    } elseif ($action === 'unlock_report') {
        lab_repo()->unlockReport($postLabNo);
        audit_log('UNLOCK_REPORT', 'lab_entries', $postLabNo, 'Report unlocked via Admin Console');
        $message = flash_success("Diagnostic report for {$postLabNo} has been unlocked successfully. Results can now be edited.");
    } elseif ($action === 'lock_bill') {
        lab_repo()->lockBill($postLabNo, current_user()['name'] ?? 'Admin');
        audit_log('LOCK_BILL', 'lab_entries', $postLabNo, 'Bill locked via Admin Console');
        $message = flash_success("Bill for {$postLabNo} is now locked. Employees cannot modify it.");
    } elseif ($action === 'lock_report') {
        lab_repo()->lockReport($postLabNo, current_user()['name'] ?? 'Admin');
        audit_log('LOCK_REPORT', 'lab_entries', $postLabNo, 'Report locked via Admin Console');
        $message = flash_success("Diagnostic report for {$postLabNo} is now locked. Employees cannot modify results.");
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$filter = trim((string)($_GET['filter'] ?? 'all'));

// Counts
$lockedBillsCount = 0;
$lockedReportsCount = 0;
try {
    $rowB = db()->fetchOne("SELECT COUNT(*) as c FROM lab_entries WHERE organization_id = :org AND is_bill_locked = 1", ['org' => $orgId]);
    $lockedBillsCount = (int)($rowB['c'] ?? 0);
    $rowR = db()->fetchOne("SELECT COUNT(*) as c FROM lab_entries WHERE organization_id = :org AND is_report_locked = 1", ['org' => $orgId]);
    $lockedReportsCount = (int)($rowR['c'] ?? 0);
} catch (\Throwable) {}

// Query locked entries
$whereClauses = ["organization_id = :org"];
$params = ['org' => $orgId];

if ($filter === 'bill') {
    $whereClauses[] = "is_bill_locked = 1";
} elseif ($filter === 'report') {
    $whereClauses[] = "is_report_locked = 1";
} else {
    $whereClauses[] = "(is_bill_locked = 1 OR is_report_locked = 1)";
}

if ($q !== '') {
    $whereClauses[] = "(lab_no LIKE :q OR patient_name LIKE :q2 OR patient_id LIKE :q3 OR doctor LIKE :q4)";
    $params['q'] = '%' . $q . '%';
    $params['q2'] = '%' . $q . '%';
    $params['q3'] = '%' . $q . '%';
    $params['q4'] = '%' . $q . '%';
}

$sql = "SELECT * FROM lab_entries WHERE " . implode(' AND ', $whereClauses) . " ORDER BY created_at DESC LIMIT 150";
$entries = [];
try {
    $entries = db()->fetchAll($sql, $params);
} catch (\Throwable) {}

ob_start();
?>
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-lock text-rose-600"></i> Locked Bills &amp; Reports Console
                </h1>
                <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-slate-900 text-[#c2f13c] text-[11px] font-bold uppercase tracking-wider">
                    <i class="fa-solid fa-shield-halved text-[10px]"></i> Admin Security
                </span>
            </div>
            <p class="text-xs text-slate-500 font-medium">
                Protected financial invoices and finalized diagnostic reports. As requested by client, once locked by staff, <strong>only Admin ID can unlock or modify them</strong>.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="/portals/dashboard.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white hover:bg-slate-100 text-slate-700 font-bold text-xs border border-slate-300 shadow-2xs transition-colors">
                <i class="fa-solid fa-arrow-left text-xs text-slate-500"></i>
                <span>Admin Dashboard</span>
            </a>
            <a href="/portals/audit-trail.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition-colors">
                <i class="fa-solid fa-clock-rotate-left text-[#c2f13c] text-xs"></i>
                <span>Security Logs</span>
            </a>
        </div>
    </div>

    <?= $message ?>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl p-4 border border-rose-200 shadow-2xs flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Locked Bills</p>
                    <p class="text-2xl font-black text-rose-950 leading-tight"><?= $lockedBillsCount ?></p>
                </div>
            </div>
            <span class="text-xs font-bold text-rose-700 bg-rose-50 px-2.5 py-1 rounded-lg border border-rose-200">Protected</span>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-indigo-200 shadow-2xs flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-file-medical"></i>
                </div>
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Locked Reports</p>
                    <p class="text-2xl font-black text-indigo-950 leading-tight"><?= $lockedReportsCount ?></p>
                </div>
            </div>
            <span class="text-xs font-bold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-200">Protected</span>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-emerald-200 shadow-2xs flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Admin Authority</p>
                    <p class="text-sm font-black text-slate-900 leading-tight">Master ID Override</p>
                </div>
            </div>
            <span class="text-[10px] font-black uppercase text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-full">Active</span>
        </div>
    </div>

    <!-- Quick Lock/Unlock Action Card (By Lab No) -->
    <div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white p-5 rounded-3xl shadow-card">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-black uppercase tracking-wider text-[#c2f13c] flex items-center gap-2">
                    <i class="fa-solid fa-key"></i> Quick Admin Unlock by Lab No
                </h3>
                <p class="text-xs text-slate-300 mt-0.5">
                    Type any patient Lab Number below to unlock or lock its bill or diagnostic report immediately.
                </p>
            </div>
            <form method="post" class="flex flex-wrap items-center gap-2">
                <input 
                    type="text" 
                    name="lab_no" 
                    placeholder="e.g. L-2026-0177" 
                    required 
                    class="px-3.5 py-2 text-xs font-mono font-bold text-slate-900 bg-white rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#c2f13c]"
                >
                <button type="submit" name="action" value="unlock_bill" class="px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs transition-colors cursor-pointer shadow-xs">
                    <i class="fa-solid fa-lock-open mr-1"></i> Unlock Bill
                </button>
                <button type="submit" name="action" value="unlock_report" class="px-3.5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-bold text-xs transition-colors cursor-pointer shadow-xs">
                    <i class="fa-solid fa-lock-open mr-1"></i> Unlock Report
                </button>
            </form>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-2xs flex flex-wrap items-center justify-between gap-3">
        <form method="get" class="flex flex-wrap items-center gap-2 flex-1">
            <div class="relative flex-1 min-w-[220px]">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input 
                    type="text" 
                    name="q" 
                    value="<?= e($q) ?>" 
                    placeholder="Search by Lab No, Patient Name, or MR No…" 
                    class="field pl-9 text-xs w-full font-semibold"
                >
            </div>
            <select name="filter" class="field text-xs font-semibold w-auto" onchange="this.form.submit()">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Locked Records</option>
                <option value="bill" <?= $filter === 'bill' ? 'selected' : '' ?>>Locked Bills Only</option>
                <option value="report" <?= $filter === 'report' ? 'selected' : '' ?>>Locked Reports Only</option>
            </select>
            <button type="submit" class="btn btn-secondary text-xs font-bold"><i class="fa-solid fa-filter mr-1"></i> Filter</button>
            <?php if ($q !== '' || $filter !== 'all'): ?>
                <a href="/portals/locked-records.php" class="btn btn-secondary text-xs">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Locked Records Table -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200/80 flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Currently Locked Records (<?= count($entries) ?>)</span>
            <span class="text-[11px] text-slate-500 font-medium">Click "Unlock" to grant employee editing access</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-100/80 text-[10px] uppercase font-bold text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Lab No</th>
                        <th class="px-4 py-3">Patient</th>
                        <th class="px-4 py-3">Tests Booked</th>
                        <th class="px-4 py-3">Bill Amount</th>
                        <th class="px-4 py-3">Bill Lock Status</th>
                        <th class="px-4 py-3">Report Lock Status</th>
                        <th class="px-4 py-3 text-right">Admin Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500">
                                <i class="fa-solid fa-shield-check text-emerald-500 text-3xl mb-2 block"></i>
                                <div class="font-bold text-sm text-slate-800">No locked entries matching your criteria.</div>
                                <p class="text-xs text-slate-400 mt-1">When staff or admin locks a bill or report, it will appear here for centralized control.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($entries as $e): 
                            $eLab = e((string)$e['lab_no']);
                            $ePatient = e((string)($e['patient_name'] ?? '—'));
                            $eMr = e((string)($e['patient_id'] ?? '—'));
                            $eTests = e(normalize_tests_list((string)($e['tests'] ?? '')));
                            $eAmount = number_format((float)($e['amount'] ?? 0));
                            $ePaid = number_format((float)($e['paid'] ?? 0));
                            $isBLocked = !empty($e['is_bill_locked']);
                            $isRLocked = !empty($e['is_report_locked']);
                        ?>
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-4 py-3 font-mono font-bold text-teal-800 text-sm">
                                <?= $eLab ?>
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-900">
                                <?= $ePatient ?>
                                <div class="text-[11px] font-normal text-slate-400 font-mono">MR: <?= $eMr ?></div>
                            </td>
                            <td class="px-4 py-3 text-slate-700 max-w-xs truncate" title="<?= $eTests ?>">
                                <?= $eTests ?>
                            </td>
                            <td class="px-4 py-3 font-mono">
                                <div class="font-bold text-slate-900">Rs. <?= $eAmount ?></div>
                                <div class="text-[10px] text-emerald-700">Paid: Rs. <?= $ePaid ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($isBLocked): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-rose-100 text-rose-900 font-bold text-[11px] border border-rose-200">
                                        <i class="fa-solid fa-lock text-rose-600"></i> Locked
                                    </span>
                                    <div class="text-[10px] text-slate-400 mt-0.5">by <?= e((string)($e['bill_locked_by'] ?? 'Staff')) ?></div>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 font-medium text-[11px]">
                                        <i class="fa-solid fa-lock-open text-slate-400"></i> Open
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($isRLocked): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-rose-100 text-rose-900 font-bold text-[11px] border border-rose-200">
                                        <i class="fa-solid fa-lock text-rose-600"></i> Locked
                                    </span>
                                    <div class="text-[10px] text-slate-400 mt-0.5">by <?= e((string)($e['report_locked_by'] ?? 'Staff')) ?></div>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 font-medium text-[11px]">
                                        <i class="fa-solid fa-lock-open text-slate-400"></i> Open
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1.5 flex-wrap justify-end">
                                    <?php if ($isBLocked): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="lab_no" value="<?= $eLab ?>">
                                            <input type="hidden" name="action" value="unlock_bill">
                                            <button type="submit" class="btn btn-secondary text-xs py-1 px-2.5 font-bold text-rose-800 bg-white border-rose-300 hover:bg-rose-50 shadow-2xs" title="Unlock Bill for staff">
                                                <i class="fa-solid fa-lock-open mr-1 text-rose-600"></i> Unlock Bill
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($isRLocked): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="lab_no" value="<?= $eLab ?>">
                                            <input type="hidden" name="action" value="unlock_report">
                                            <button type="submit" class="btn btn-secondary text-xs py-1 px-2.5 font-bold text-indigo-800 bg-white border-indigo-300 hover:bg-indigo-50 shadow-2xs" title="Unlock Report for staff">
                                                <i class="fa-solid fa-lock-open mr-1 text-indigo-600"></i> Unlock Report
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <a href="/portals/receipts.php?lab_no=<?= urlencode((string)$e['lab_no']) ?>" target="_blank" class="btn btn-secondary text-xs py-1 px-2" title="View / Edit Bill Slip">
                                        <i class="fa-solid fa-receipt text-slate-600"></i>
                                    </a>
                                    <a href="/portals/reports/preview.php?lab_no=<?= urlencode((string)$e['lab_no']) ?>" target="_blank" class="btn btn-secondary text-xs py-1 px-2" title="View Final Report">
                                        <i class="fa-solid fa-eye text-teal-600"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<?php
$content = ob_get_clean();
render_page('Locked Bills & Reports', 'admin', 'locked-records', $content);
