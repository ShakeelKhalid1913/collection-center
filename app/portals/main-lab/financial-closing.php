<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

require_auth();

$user = current_user();
$portal = current_portal();
$orgId = $user['organization_id'] ?? 'ORG-001';
$isCollectionCenter = $portal === 'collection-center';
$userBranch = $isCollectionCenter ? ($user['branch_id'] ?? null) : null;

$period = trim((string)($_GET['period'] ?? 'daily'));
$dateFrom = trim((string)($_GET['from'] ?? ''));
$dateTo = trim((string)($_GET['to'] ?? ''));

if ($period === 'weekly') {
    $dateFrom = date('Y-m-d', strtotime('-6 days'));
    $dateTo = date('Y-m-d');
} elseif ($period === 'monthly') {
    $dateFrom = date('Y-m-01');
    $dateTo = date('Y-m-d');
} elseif ($period === 'custom' && $dateFrom !== '' && $dateTo !== '') {
    // Keep custom from & to
} else {
    $period = 'daily';
    $date = trim((string)($_GET['date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }
    $dateFrom = $date;
    $dateTo = $date;
}

$modality = trim((string)($_GET['modality'] ?? 'all'));
$branchFilter = trim((string)($_GET['branch'] ?? ($isCollectionCenter ? ($user['branch_id'] ?? '') : '')));

// Handle Add Expense action
$expenseFeedback = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_expense'])) {
    $expTitle = trim((string)($_POST['expense_title'] ?? ''));
    $expAmount = (float)($_POST['expense_amount'] ?? 0);
    $expCat = trim((string)($_POST['expense_category'] ?? 'Supplies / Consumables'));
    $expDate = trim((string)($_POST['expense_date'] ?? $dateFrom));
    if ($expTitle !== '' && $expAmount > 0) {
        $expOk = expense_repo()->create([
            'organization_id' => $orgId,
            'branch' => $userBranch ?? 'Main Lab',
            'title' => $expTitle,
            'category' => $expCat,
            'amount' => $expAmount,
            'payment_mode' => 'Cash',
            'receipt_no' => trim((string)($_POST['expense_receipt_no'] ?? '')),
            'notes' => trim((string)($_POST['expense_notes'] ?? '')),
            'expense_date' => $expDate !== '' ? $expDate : date('Y-m-d'),
            'created_by' => $user['id'] ?? null,
        ]);
        if ($expOk) {
            header('Location: ?period=' . urlencode($period) . '&from=' . urlencode($dateFrom) . '&to=' . urlencode($dateTo) . '&modality=' . urlencode($modality) . '&branch=' . urlencode($branchFilter) . '&expense_saved=1');
            exit;
        }
    } else {
        $expenseFeedback = flash_error('Please enter an expense description and a valid amount.');
    }
}

// Handle Delete Expense action
if (isset($_GET['delete_expense'])) {
    $delExpId = trim((string)($_GET['delete_expense'] ?? ''));
    if ($delExpId !== '') {
        expense_repo()->delete($delExpId, $orgId);
        header('Location: ?period=' . urlencode($period) . '&from=' . urlencode($dateFrom) . '&to=' . urlencode($dateTo) . '&modality=' . urlencode($modality) . '&branch=' . urlencode($branchFilter));
        exit;
    }
}
if (isset($_GET['expense_saved'])) {
    $expenseFeedback = flash_success('Expense logged successfully.');
}

// Fetch lab entries
$sqlLab = "SELECT 'Laboratory' AS dept, le.lab_no AS ref_no, le.patient_id, le.patient_name,
                  le.tests AS study_title, le.amount, le.paid, le.discount, le.status, le.created_at,
                  le.created_by, le.doctor, le.branch, le.branch_id,
                  COALESCE(p.phone, '—') AS patient_phone
           FROM lab_entries le
           LEFT JOIN patients p ON (p.id = le.patient_id OR p.patient_no = le.patient_id)
           WHERE le.organization_id = :org AND DATE(le.created_at) BETWEEN :dt_from AND :dt_to";
$paramsLab = ['org' => $orgId, 'dt_from' => $dateFrom, 'dt_to' => $dateTo];

if ($branchFilter !== '') {
    $sqlLab .= " AND (le.branch_id = :branch OR le.branch = :branch2)";
    $paramsLab['branch'] = $branchFilter;
    $paramsLab['branch2'] = $branchFilter;
}

$labEntries = db()->fetchAll($sqlLab, $paramsLab);

// Fetch imaging scans (X-Ray, Ultrasound, CT Scan, ECG, Echocardiography)
$sqlImg = "SELECT s.modality AS dept, s.scan_no AS ref_no, s.patient_id, s.patient AS patient_name,
                  s.study AS study_title, COALESCE(s.amount, 0) AS amount, COALESCE(s.paid, 0) AS paid,
                  COALESCE(s.discount, 0) AS discount, s.status, s.created_at,
                  s.radiologist AS created_by, s.radiologist AS doctor, 'IMG-01' AS branch, s.branch_id,
                  COALESCE(p.phone, '—') AS patient_phone, s.lab_no
           FROM imaging_scans s
           LEFT JOIN patients p ON (p.id = s.patient_id OR p.patient_no = s.patient_id)
           WHERE (s.organization_id = :org OR s.organization_id = 'ORG-001') AND DATE(s.scan_date) BETWEEN :dt_from AND :dt_to";
$paramsImg = ['org' => $orgId, 'dt_from' => $dateFrom, 'dt_to' => $dateTo];

if ($branchFilter !== '') {
    $sqlImg .= " AND s.branch_id = :branch";
    $paramsImg['branch'] = $branchFilter;
}

$imagingEntries = db()->fetchAll($sqlImg, $paramsImg);

// Group standalone imaging scans (not linked to any lab_entry order)
$standaloneImaging = [];
$linkedScanNos = [];
foreach ($imagingEntries as $ie) {
    $ln = trim((string)($ie['lab_no'] ?? ''));
    if ($ln !== '') {
        $linkedScanNos[$ln][] = $ie['ref_no'];
    } else {
        $standaloneImaging[] = $ie;
    }
}

// Build consolidated patient visits (1 clean row per invoice / patient visit in consolidated view)
$consolidatedVisits = [];
$deptStats = [];

foreach ($labEntries as $le) {
    $lNo = (string)$le['ref_no'];
    $bd = allocate_order_breakdown([
        'tests' => $le['study_title'],
        'amount' => $le['amount'],
        'discount' => $le['discount'],
        'paid' => $le['paid'],
    ], $orgId);

    // Track department stats from the investigation breakdown
    foreach ($bd['items'] as $it) {
        $dName = (string)($it['department'] ?? 'Laboratory');
        if (!isset($deptStats[$dName])) {
            $deptStats[$dName] = ['count' => 0, 'gross' => 0.0, 'paid' => 0.0, 'due' => 0.0];
        }
        $deptStats[$dName]['count']++;
        $deptStats[$dName]['gross'] += (float)$it['allocated_gross'];
        $deptStats[$dName]['paid'] += (float)$it['allocated_paid'];
        $deptStats[$dName]['due'] += (float)$it['allocated_due'];
    }

    $deptsInOrder = array_unique(array_filter(array_column($bd['items'], 'department')));
    if (count($deptsInOrder) === 1) {
        $deptLabel = reset($deptsInOrder);
    } elseif (count($deptsInOrder) > 1) {
        $deptLabel = 'Multi-Dept';
    } else {
        $deptLabel = 'Laboratory';
    }

    // Pick best reference number (show first scan number if pure radiology)
    $refNo = $le['ref_no'];
    if (!empty($linkedScanNos[$lNo]) && empty($bd['lab']['has_lab'])) {
        $refNo = $linkedScanNos[$lNo][0];
    }

    $orderGross = !empty($bd['items'])
        ? array_sum(array_column($bd['items'], 'allocated_gross'))
        : ((float)$le['amount'] + (float)$le['discount']);
    $orderDiscount = (float)$le['discount'];
    $orderPaid = (float)$le['paid'];
    $orderNet = max(0.0, $orderGross - $orderDiscount);
    $orderDue = max(0.0, $orderNet - $orderPaid);

    $consolidatedVisits[] = [
        'dept' => $deptLabel,
        'depts_list' => $deptsInOrder,
        'ref_no' => $refNo,
        'patient_id' => $le['patient_id'],
        'patient_name' => $le['patient_name'],
        'study_title' => $le['study_title'],
        'gross' => $orderGross,
        'amount' => $orderGross,
        'discount' => $orderDiscount,
        'net' => $orderNet,
        'paid' => $orderPaid,
        'due' => $orderDue,
        'status' => $le['status'],
        'created_at' => $le['created_at'],
        'created_by' => $le['created_by'],
        'doctor' => $le['doctor'],
        'branch' => $le['branch'],
        'branch_id' => $le['branch_id'],
        'patient_phone' => $le['patient_phone'],
        'breakdown' => $bd,
    ];
}

// Add standalone imaging scans
foreach ($standaloneImaging as $se) {
    $dName = (string)($se['dept'] ?? 'Radiology');
    if (!isset($deptStats[$dName])) {
        $deptStats[$dName] = ['count' => 0, 'gross' => 0.0, 'paid' => 0.0, 'due' => 0.0];
    }
    $g = (float)$se['amount'];
    $p = (float)$se['paid'];
    $d = (float)$se['discount'];
    $net = max(0.0, $g - $d);
    $due = max(0.0, $net - $p);
    $deptStats[$dName]['count']++;
    $deptStats[$dName]['gross'] += $g;
    $deptStats[$dName]['paid'] += $p;
    $deptStats[$dName]['due'] += $due;

    $consolidatedVisits[] = [
        'dept' => $dName,
        'depts_list' => [$dName],
        'ref_no' => $se['ref_no'],
        'patient_id' => $se['patient_id'],
        'patient_name' => $se['patient_name'],
        'study_title' => $se['study_title'],
        'gross' => $g,
        'amount' => $g,
        'discount' => $d,
        'net' => $net,
        'paid' => $p,
        'due' => $due,
        'status' => $se['status'],
        'created_at' => $se['created_at'],
        'created_by' => $se['created_by'],
        'doctor' => $se['doctor'],
        'branch' => $se['branch'],
        'branch_id' => $se['branch_id'],
        'patient_phone' => $se['patient_phone'],
        'breakdown' => null,
    ];
}

// Final combined list based on modality filter
if ($modality === 'all') {
    // Consolidated Master Summary: EXACTLY 1 ROW PER PATIENT VISIT (no duplicate rows)
    $combined = $consolidatedVisits;
} else {
    // Specific Modality Filter: Show only the studies matching this department with their exact allocated share
    $filtered = [];
    $m = strtolower($modality);

    foreach ($consolidatedVisits as $cv) {
        $bd = $cv['breakdown'];
        if ($bd && !empty($bd['items'])) {
            foreach ($bd['items'] as $it) {
                $d = strtolower((string)$it['department']);
                $matches = false;
                if ($m === 'laboratory' || $m === 'lab') {
                    $matches = (($it['dept_id'] ?? '') === 'lab' || str_contains($d, 'lab') || str_contains($d, 'path'));
                } elseif ($m === 'ultrasound' || $m === 'us') {
                    $matches = (str_contains($d, 'ultrasound') || str_contains($d, 'us'));
                } elseif ($m === 'x-ray' || $m === 'xray') {
                    $matches = (str_contains($d, 'x-ray') || str_contains($d, 'xray'));
                } elseif ($m === 'ct' || $m === 'ct scan') {
                    $matches = (str_contains($d, 'ct'));
                } elseif ($m === 'ecg') {
                    $matches = (str_contains($d, 'ecg'));
                } elseif ($m === 'echo' || $m === 'echocardiography') {
                    $matches = (str_contains($d, 'echo'));
                } else {
                    $matches = str_contains($d, $m);
                }

                if ($matches) {
                    $filtered[] = [
                        'dept' => $it['department'],
                        'ref_no' => $cv['ref_no'],
                        'patient_id' => $cv['patient_id'],
                        'patient_name' => $cv['patient_name'],
                        'study_title' => $it['name'],
                        'gross' => (float)$it['allocated_gross'],
                        'amount' => (float)$it['allocated_gross'],
                        'discount' => (float)$it['allocated_discount'],
                        'net' => max(0.0, (float)$it['allocated_gross'] - (float)$it['allocated_discount']),
                        'paid' => (float)$it['allocated_paid'],
                        'due' => (float)($it['allocated_due'] ?? max(0.0, (float)$it['allocated_gross'] - (float)$it['allocated_discount'] - (float)$it['allocated_paid'])),
                        'status' => $cv['status'],
                        'created_at' => $cv['created_at'],
                        'created_by' => $cv['created_by'],
                        'doctor' => $cv['doctor'],
                        'branch' => $cv['branch'],
                        'branch_id' => $cv['branch_id'],
                        'patient_phone' => $cv['patient_phone'],
                    ];
                }
            }
        } else {
            // Standalone scan fallback
            $d = strtolower((string)$cv['dept']);
            if (str_contains($d, $m)) {
                $filtered[] = $cv;
            }
        }
    }
    $combined = $filtered;
}

// Sort by created_at ASC
usort($combined, static fn($a, $b) => strcmp((string)$a['created_at'], (string)$b['created_at']));

// Calculate totals
$totalPatients = count(array_unique(array_filter(array_column($combined, 'patient_id') ?: array_column($combined, 'patient_name'))));
$totalOrders = count($combined);
$totalGross = 0.0;
$totalDiscount = 0.0;
$totalNet = 0.0;
$totalPaid = 0.0;
$totalDue = 0.0;

// Breakdown by modality
$deptStats = [];

foreach ($combined as $c) {
    $g = (float)($c['gross'] ?? $c['amount']);
    $d = (float)$c['discount'];
    $net = isset($c['net']) ? (float)$c['net'] : max(0.0, $g - $d);
    $p = (float)$c['paid'];
    $due = isset($c['due']) ? (float)$c['due'] : max(0.0, $net - $p);

    $totalGross += $g;
    $totalDiscount += $d;
    $totalNet += $net;
    $totalPaid += $p;
    $totalDue += $due;

    $deptName = (string)$c['dept'];
    if (!isset($deptStats[$deptName])) {
        $deptStats[$deptName] = ['count' => 0, 'gross' => 0.0, 'paid' => 0.0, 'due' => 0.0];
    }
    $deptStats[$deptName]['count']++;
    $deptStats[$deptName]['gross'] += $g;
    $deptStats[$deptName]['paid'] += $p;
    $deptStats[$deptName]['due'] += $due;
}

$netRevenue = $totalNet;

// Settings for branding
$settings = branding_settings($orgId);
$labName = e($settings['name'] ?? 'Diagnostic Center & Laboratory');
$labPhone = e($settings['phone'] ?? '');

$modalityLabels = [
    'all' => 'Consolidated Master Summary (All Departments)',
    'Laboratory' => 'Pathology Laboratory Department',
    'Ultrasound' => 'Ultrasound Department',
    'X-Ray' => 'X-Ray / Digital Radiography',
    'ECG' => 'ECG Department',
    'CT Scan' => 'CT Scan Department',
];

$selectedModalityLabel = $modalityLabels[$modality] ?? ($modality . ' Summary');

// Table rows for screen and print
$slipRows = '';
$idx = 0;
foreach ($combined as $item) {
    $idx++;
    $time = date('h:i A', strtotime((string)$item['created_at']));
    $ref = e((string)$item['ref_no']);
    $pName = e((string)$item['patient_name']);
    $pPhone = e((string)$item['patient_phone']);
    $deptBadge = '<span class="badge badge-teal text-[10px]">' . e((string)$item['dept']) . '</span>';
    $studies = e((string)$item['study_title']);
    $g = (float)($item['gross'] ?? $item['amount']);
    $d = (float)$item['discount'];
    $net = isset($item['net']) ? (float)$item['net'] : max(0.0, $g - $d);
    $p = (float)$item['paid'];
    $due = isset($item['due']) ? (float)$item['due'] : max(0.0, $net - $p);

    $slipRows .= '<tr>'
        . '<td class="py-2 px-3 text-xs text-slate-500">' . $idx . '</td>'
        . '<td class="py-2 px-3 text-xs font-mono">' . $time . '</td>'
        . '<td class="py-2 px-3 text-xs font-mono font-bold text-slate-900">' . $ref . '</td>'
        . '<td class="py-2 px-3 text-xs font-bold text-slate-800">' . $pName . '<br><span class="text-[10px] text-slate-400 font-normal">' . $pPhone . '</span></td>'
        . '<td class="py-2 px-3 text-xs">' . $deptBadge . '</td>'
        . '<td class="py-2 px-3 text-xs text-slate-600 max-w-xs truncate" title="' . $studies . '">' . $studies . '</td>'
        . '<td class="py-2 px-3 text-xs text-right font-medium">Rs ' . number_format($g, 0) . '</td>'
        . '<td class="py-2 px-3 text-xs text-right text-rose-600">Rs ' . number_format($d, 0) . '</td>'
        . '<td class="py-2 px-3 text-xs text-right font-medium text-slate-700">Rs ' . number_format($net, 0) . '</td>'
        . '<td class="py-2 px-3 text-xs text-right font-bold text-emerald-700">Rs ' . number_format($p, 0) . '</td>'
        . '<td class="py-2 px-3 text-xs text-right font-bold ' . ($due > 0 ? 'text-amber-700' : 'text-slate-400') . '">Rs ' . number_format($due, 0) . '</td>'
        . '</tr>';
}

if ($slipRows === '') {
    $slipRows = '<tr><td colspan="11" class="text-center py-8 text-slate-400 text-xs">No patient entries recorded for this date and department.</td></tr>';
}

// Fetch expenses for active date range and organization
$sqlExp = "SELECT id, title, category, amount, payment_mode, expense_date, created_at
           FROM expenses
           WHERE organization_id = :org AND expense_date BETWEEN :dt_from AND :dt_to
           ORDER BY expense_date DESC, created_at DESC";
$expensesList = db()->fetchAll($sqlExp, ['org' => $orgId, 'dt_from' => $dateFrom, 'dt_to' => $dateTo]);
$totalExpenses = 0.0;
foreach ($expensesList as $ex) {
    $totalExpenses += (float)$ex['amount'];
}
$netCashInDrawer = max(0.0, $totalPaid - $totalExpenses);

$totalExpensesFmt = number_format($totalExpenses, 0);
$netCashInDrawerFmt = number_format($netCashInDrawer, 0);

// Branch options if Main Lab
$branchOpts = '';
if (!$isCollectionCenter) {
    $branches = db()->fetchAll("SELECT id, code, name FROM branches WHERE organization_id = :org", ['org' => $orgId]);
    $branchOpts = '<option value="">All Branches / Centers</option>';
    foreach ($branches as $b) {
        $sel = $branchFilter === $b['id'] || $branchFilter === $b['code'] ? ' selected' : '';
        $branchOpts .= '<option value="' . e((string)$b['code']) . '"' . $sel . '>' . e((string)$b['name']) . ' (' . e((string)$b['code']) . ')</option>';
    }
}

// Department breakdowns HTML
$deptRowsHtml = '';
$printDeptBadges = [];
foreach ($deptStats as $dName => $s) {
    $sPaid = (float)$s['paid'];
    $pct = $totalPaid > 0 ? round(($sPaid / $totalPaid) * 100) : 0;
    $dIcon = 'fa-solid fa-circle-dot text-teal-600';
    $lowName = strtolower($dName);
    if (str_contains($lowName, 'lab') || str_contains($lowName, 'path')) $dIcon = 'fa-solid fa-flask-vial text-teal-600';
    elseif (str_contains($lowName, 'ultra') || str_contains($lowName, 'us')) $dIcon = 'fa-solid fa-wave-square text-cyan-600';
    elseif (str_contains($lowName, 'x-ray') || str_contains($lowName, 'xray')) $dIcon = 'fa-solid fa-x-ray text-blue-600';
    elseif (str_contains($lowName, 'ecg') || str_contains($lowName, 'echo')) $dIcon = 'fa-solid fa-heart-pulse text-rose-600';
    elseif (str_contains($lowName, 'ct')) $dIcon = 'fa-solid fa-ring text-amber-600';

    $deptRowsHtml .= '<div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100 hover:border-slate-200 transition-all">'
        . '<div class="flex items-center gap-2.5">'
        . '<i class="' . $dIcon . ' text-base"></i>'
        . '<div>'
        . '<div class="text-xs font-bold text-slate-800">' . e($dName) . '</div>'
        . '<div class="text-[10px] text-slate-400 font-medium">' . (int)$s['count'] . ' studies · ' . $pct . '% share</div>'
        . '</div>'
        . '</div>'
        . '<strong class="text-sm font-black text-slate-900 font-mono">Rs ' . number_format($sPaid, 0) . '</strong>'
        . '</div>';

    $printDeptBadges[] = e($dName) . ': <strong>Rs ' . number_format($sPaid, 0) . '</strong>';
}

$printDeptBadgesHtml = implode(' &nbsp;|&nbsp; ', $printDeptBadges);

// Expenses rows HTML
$expenseRowsHtml = '';
foreach ($expensesList as $ex) {
    $exAmt = number_format((float)$ex['amount'], 0);
    $exDate = date('d-M', strtotime((string)$ex['expense_date']));
    $delUrl = '?period=' . urlencode($period) . '&from=' . urlencode($dateFrom) . '&to=' . urlencode($dateTo) . '&modality=' . urlencode($modality) . '&branch=' . urlencode($branchFilter) . '&delete_expense=' . urlencode((string)$ex['id']);
    $expenseRowsHtml .= '<tr class="border-b border-slate-100 text-xs">'
        . '<td class="py-2 px-2.5 font-medium text-slate-800">' . e((string)$ex['title']) . '<br><span class="text-[10px] text-slate-400 font-normal">' . $exDate . ' · ' . e((string)$ex['category']) . '</span></td>'
        . '<td class="py-2 px-2.5 text-right font-bold text-rose-600">Rs ' . $exAmt . '</td>'
        . '<td class="py-2 px-2 text-right no-print"><a href="' . $delUrl . '" onclick="return confirm(\'Delete this expense?\');" class="text-slate-400 hover:text-rose-600 transition-colors" title="Delete"><i class="fa-solid fa-trash-can text-xs"></i></a></td>'
        . '</tr>';
}
if ($expenseRowsHtml === '') {
    $expenseRowsHtml = '<tr><td colspan="3" class="py-4 text-center text-xs text-slate-400">No expenses recorded for this date.</td></tr>';
}

$headerAction = '<button type="button" onclick="window.print()" class="btn btn-primary text-xs py-2 px-4 shadow-sm"><i class="fa-solid fa-print mr-1.5"></i> Print Closing Slip</button>';
$content = '<div class="no-print">' . page_header('Daily Financial Closing', '', $headerAction) . '</div>';

$selAll = ($modality === 'all') ? 'selected' : '';
$selLab = ($modality === 'Laboratory') ? 'selected' : '';
$selUS  = ($modality === 'Ultrasound') ? 'selected' : '';
$selXR  = ($modality === 'X-Ray') ? 'selected' : '';
$selECG = ($modality === 'ECG') ? 'selected' : '';
$selEcho = ($modality === 'echo' || $modality === 'Echocardiography') ? 'selected' : '';
$selCT  = ($modality === 'CT Scan') ? 'selected' : '';
$selPeriodDaily = ($period === 'daily') ? 'selected' : '';
$selPeriodWeekly = ($period === 'weekly') ? 'selected' : '';
$selPeriodMonthly = ($period === 'monthly') ? 'selected' : '';
$selPeriodCustom = ($period === 'custom') ? 'selected' : '';
$displayRange = ($dateFrom === $dateTo) ? date('d M Y', strtotime($dateFrom)) : (date('d M Y', strtotime($dateFrom)) . ' — ' . date('d M Y', strtotime($dateTo)));
$branchScopeHtml = ($branchOpts !== '') ? '<div><label class="field-label text-[11px] mb-1">Branch Scope</label><select name="branch" class="field text-xs font-semibold py-1.5" onchange="this.form.submit()">' . $branchOpts . '</select></div>' : '';
$modalityMiniCardsHtml = ($breakdownsHtml !== '' && $modality === 'all') ? '<div class="grid gap-3 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5">' . $breakdownsHtml . '</div>' : '';
$generatedTime = date('d-M-Y h:i A');
$operatorName = e($user['name'] ?? 'Counter');
$totalPaidFmt = number_format($totalPaid, 0);
$totalDueFmt = number_format($totalDue, 0);
$totalGrossFmt = number_format($totalGross, 0);
$totalDiscountFmt = number_format($totalDiscount, 0);
$totalNetFmt = number_format($totalNet, 0);

$content .= <<<HTML
<style>
@media print {
    body * { visibility: hidden !important; }
    .closing-slip-print, .closing-slip-print * { visibility: visible !important; }
    .closing-slip-print {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
    }
}
</style>
<div class="no-print space-y-5">
    <!-- Filter Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-card">
        <form method="get" class="flex flex-wrap items-center gap-3">
            <div>
                <label class="field-label text-[11px] mb-1">Summary Period</label>
                <select name="period" class="field text-xs font-semibold py-1.5" onchange="this.form.submit()">
                    <option value="daily" {$selPeriodDaily}>Daily Summary (Single Day)</option>
                    <option value="weekly" {$selPeriodWeekly}>Weekly Summary (Last 7 Days)</option>
                    <option value="monthly" {$selPeriodMonthly}>Monthly Summary (This Month)</option>
                    <option value="custom" {$selPeriodCustom}>Custom Date Range</option>
                </select>
            </div>
            <div>
                <label class="field-label text-[11px] mb-1">From Date</label>
                <input type="date" name="from" value="{$dateFrom}" class="field text-xs font-semibold py-1.5">
            </div>
            <div>
                <label class="field-label text-[11px] mb-1">To Date</label>
                <input type="date" name="to" value="{$dateTo}" class="field text-xs font-semibold py-1.5">
            </div>
            <div>
                <label class="field-label text-[11px] mb-1">Department / Modality</label>
                <select name="modality" class="field text-xs font-semibold py-1.5" onchange="this.form.submit()">
                    <option value="all" {$selAll}>All Modalities (Consolidated)</option>
                    <option value="Laboratory" {$selLab}>Laboratory (Pathology)</option>
                    <option value="Ultrasound" {$selUS}>Ultrasound</option>
                    <option value="X-Ray" {$selXR}>X-Ray</option>
                    <option value="ECG" {$selECG}>ECG</option>
                    <option value="echo" {$selEcho}>Echocardiography (Echo)</option>
                    <option value="CT Scan" {$selCT}>CT Scan</option>
                </select>
            </div>
            {$branchScopeHtml}
            <div class="flex items-end self-end">
                <button type="submit" class="btn btn-primary text-xs py-2 px-3 font-bold"><i class="fa-solid fa-filter mr-1"></i> Calculate Summary</button>
            </div>
        </form>
        <div class="mt-2.5 text-xs text-slate-500 font-medium">
            Active Period: <strong class="text-teal-800 font-mono">{$displayRange}</strong>
        </div>
    </div>

    {$expenseFeedback}

    <!-- Top KPI Cards: Clean, Simple & Essential -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat-card stat-card--emerald">
            <div class="stat-card__icon"><i class="fa-solid fa-cash-register"></i></div>
            <div>
                <div class="stat-card__label">Total Payment Generated</div>
                <div class="stat-card__value text-emerald-700">Rs {$totalPaidFmt}</div>
                <div class="stat-card__hint">Total cash collected from patients</div>
            </div>
        </div>
        <div class="stat-card stat-card--rose">
            <div class="stat-card__icon"><i class="fa-solid fa-receipt"></i></div>
            <div>
                <div class="stat-card__label">Lab Expenses Today</div>
                <div class="stat-card__value text-rose-600">Rs {$totalExpensesFmt}</div>
                <div class="stat-card__hint">Logged cash expenses</div>
            </div>
        </div>
        <div class="stat-card stat-card--indigo">
            <div class="stat-card__icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            <div>
                <div class="stat-card__label">Net Drawer Cash (Handover)</div>
                <div class="stat-card__value text-indigo-900">Rs {$netCashInDrawerFmt}</div>
                <div class="stat-card__hint">Cash collected − Daily expenses</div>
            </div>
        </div>
        <div class="stat-card stat-card--teal">
            <div class="stat-card__icon"><i class="fa-solid fa-users"></i></div>
            <div>
                <div class="stat-card__label">Total Patients</div>
                <div class="stat-card__value">{$totalPatients}</div>
                <div class="stat-card__hint">{$totalOrders} studies / investigations</div>
            </div>
        </div>
    </div>

    <!-- Side-by-Side: Payment by Department (Left) vs Lab Expenses (Right) -->
    <div class="grid gap-5 lg:grid-cols-2">
        <!-- Left: Department Payment Breakdown -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-card">
            <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-sitemap text-teal-600"></i> Payment by Department
                    </h3>
                    <p class="text-[11px] text-slate-500">Breakdown of how much payment came from each section</p>
                </div>
                <div class="text-right">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Generated</span>
                    <strong class="text-sm font-black text-emerald-800 font-mono">Rs {$totalPaidFmt}</strong>
                </div>
            </div>
            <div class="space-y-2.5">
                {$deptRowsHtml}
            </div>
        </div>

        <!-- Right: Lab Expenses & Quick Logging -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-card">
            <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-wallet text-rose-600"></i> Daily Lab Expenses
                    </h3>
                    <p class="text-[11px] text-slate-500">Log cash expenses to deduct from drawer total</p>
                </div>
                <div class="text-right">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Expenses</span>
                    <strong class="text-sm font-black text-rose-600 font-mono">Rs {$totalExpensesFmt}</strong>
                </div>
            </div>

            <!-- Quick Add Expense Form -->
            <form method="post" class="bg-slate-50 border border-slate-200 rounded-xl p-3 mb-4 space-y-2.5">
                <input type="hidden" name="action_add_expense" value="1">
                <input type="hidden" name="expense_date" value="{$dateFrom}">
                <div class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                    <i class="fa-solid fa-plus-circle text-teal-600"></i> Quick Log Expense
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <div class="sm:col-span-2">
                        <input type="text" name="expense_title" class="field text-xs py-1.5 w-full" placeholder="Expense description (e.g. Tea, Paper, Courier)" required>
                    </div>
                    <div>
                        <input type="number" step="1" min="1" name="expense_amount" class="field text-xs py-1.5 w-full font-mono font-bold" placeholder="Amount (Rs)" required>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <select name="expense_category" class="field text-xs py-1">
                        <option value="Supplies / Consumables">Supplies &amp; Reagents</option>
                        <option value="Refreshment / Tea">Tea &amp; Staff Refreshment</option>
                        <option value="Maintenance">Repair &amp; Maintenance</option>
                        <option value="Utilities">Utilities &amp; Bills</option>
                        <option value="Courier / Transport">Courier &amp; Transport</option>
                        <option value="Other">Other Expenses</option>
                    </select>
                    <button type="submit" class="btn btn-primary text-xs py-1.5 px-3 font-bold whitespace-nowrap shadow-xs">
                        <i class="fa-solid fa-check mr-1"></i> Save Expense
                    </button>
                </div>
            </form>

            <!-- Expenses List Table -->
            <div class="overflow-x-auto max-h-52 overflow-y-auto">
                <table class="w-full text-left border-collapse">
                    <tbody class="divide-y divide-slate-100">
                        {$expenseRowsHtml}
                    </tbody>
                </table>
            </div>

            <!-- Net Drawer Balance -->
            <div class="mt-3 pt-3 border-t border-slate-200 flex items-center justify-between text-xs">
                <span class="text-slate-600 font-medium">Net Cash in Drawer (Handover):</span>
                <strong class="text-sm font-black text-indigo-900 font-mono">Rs {$netCashInDrawerFmt}</strong>
            </div>
        </div>
    </div>
</div>

<!-- Printable Slip Container -->
<div class="mt-6 bg-white border border-slate-200/90 rounded-3xl p-6 shadow-card print:border-none print:shadow-none print:p-0 print:m-0 print-area closing-slip-print">
    <!-- Header on Print -->
    <div class="border-b-2 border-slate-900 pb-4 mb-4 text-center">
        <h2 class="text-xl font-black uppercase text-slate-900 tracking-wider">{$labName}</h2>
        <div class="text-xs text-slate-600 font-medium">{$labPhone}</div>
        <div class="inline-block mt-2 px-3 py-1 bg-slate-900 text-white rounded text-xs font-bold uppercase tracking-wider">
            {$selectedModalityLabel}
        </div>
        <div class="text-xs text-slate-500 mt-1">
            Reconciliation Date: <strong class="text-slate-900">{$date}</strong> &nbsp;|&nbsp; 
            Generated: <strong class="text-slate-900">{$generatedTime}</strong> &nbsp;|&nbsp; 
            Operator: <strong class="text-slate-900">{$operatorName}</strong>
        </div>
    </div>

    <!-- Summary Box -->
    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 mb-4 space-y-2 text-xs">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-2">
            <div>Total Patients: <strong class="font-mono text-sm text-slate-900">{$totalPatients}</strong></div>
            <div>Total Payment Generated: <strong class="font-mono text-sm text-emerald-800">Rs {$totalPaidFmt}</strong></div>
            <div>Daily Expenses: <strong class="font-mono text-sm text-rose-600">-Rs {$totalExpensesFmt}</strong></div>
            <div>Net Drawer Handover: <strong class="font-mono text-sm text-indigo-900">Rs {$netCashInDrawerFmt}</strong></div>
        </div>
        <div class="text-[11px] text-slate-600">
            <strong>Payment by Department:</strong> {$printDeptBadgesHtml}
        </div>
    </div>

    <!-- Line Items Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-y border-slate-200 bg-slate-50/70 text-[11px] font-bold uppercase text-slate-500 tracking-wider">
                    <th class="py-2 px-3">#</th>
                    <th class="py-2 px-3">Time</th>
                    <th class="py-2 px-3">Lab / MR #</th>
                    <th class="py-2 px-3">Patient</th>
                    <th class="py-2 px-3">Dept</th>
                    <th class="py-2 px-3">Studies / Tests</th>
                    <th class="py-2 px-3 text-right">Total Bill</th>
                    <th class="py-2 px-3 text-right">Discount (Less)</th>
                    <th class="py-2 px-3 text-right">Final Bill</th>
                    <th class="py-2 px-3 text-right">Paid</th>
                    <th class="py-2 px-3 text-right">Remaining Due</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                {$slipRows}
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-slate-900 bg-slate-50 font-bold text-xs">
                    <td colspan="6" class="py-2.5 px-3 uppercase text-right">Grand Total:</td>
                    <td class="py-2.5 px-3 text-right">Rs {$totalGrossFmt}</td>
                    <td class="py-2.5 px-3 text-right text-rose-600">Rs {$totalDiscountFmt}</td>
                    <td class="py-2.5 px-3 text-right text-slate-900">Rs {$totalNetFmt}</td>
                    <td class="py-2.5 px-3 text-right text-emerald-800 text-sm">Rs {$totalPaidFmt}</td>
                    <td class="py-2.5 px-3 text-right text-amber-800">Rs {$totalDueFmt}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Signatures -->
    <div class="hidden print:grid grid-cols-2 gap-8 pt-12 mt-8 border-t border-slate-300 text-xs">
        <div>
            <div class="border-t border-slate-400 w-48 pt-1 text-center font-bold">Desk Cashier / Operator</div>
            <div class="text-[10px] text-slate-400 text-center">Handover Signature</div>
        </div>
        <div class="text-right">
            <div class="border-t border-slate-400 w-48 ml-auto pt-1 text-center font-bold">Pathologist / Manager</div>
            <div class="text-[10px] text-slate-400 text-center">Closing Verified &amp; Stamped</div>
        </div>
    </div>
</div>
HTML;

render_page('Daily Financial Closing', $portal, 'financial-closing', $content);
