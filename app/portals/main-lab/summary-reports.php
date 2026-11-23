<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

require_auth();

$user = current_user();
$portal = current_portal();
$orgId = $user['organization_id'] ?? 'ORG-001';

// 1. Report Type
$reportType = trim((string)($_GET['report_type'] ?? 'dept_business'));
$validReportTypes = [
    'dept_business'    => 'Department Wise Gross Business',
    'registrations'    => 'Patient Registration & Booking Report',
    'user_business'    => 'User / Cashier Wise Business Report',
    'non_business'     => 'Non-Business / Complimentary / Zero Report',
    'dues'             => 'Patient Outstanding Dues & Recovery Report',
    'test_volume'      => 'Test Wise Volume & Revenue Report',
    'doctor_referrals' => 'Doctor Referral & Commission Report',
    'daily_business'   => 'Day Wise Business & Rate List Report',
    'expenses'         => 'Expense & Outflow Report',
    'pnl_summary'      => 'Executive Profit & Loss Summary',
];

if (!isset($validReportTypes[$reportType])) {
    $reportType = 'dept_business';
}

// 2. Duration Logic
$duration = trim((string)($_GET['duration'] ?? 'this_month'));
$today = date('Y-m-d');
$dateFrom = $today;
$dateTo = $today;

switch ($duration) {
    case 'today':
        $dateFrom = $today;
        $dateTo = $today;
        break;
    case 'yesterday':
        $dateFrom = date('Y-m-d', strtotime('-1 day'));
        $dateTo = $dateFrom;
        break;
    case 'this_week':
        $dateFrom = date('Y-m-d', strtotime('monday this week'));
        $dateTo = $today;
        break;
    case 'last_7_days':
        $dateFrom = date('Y-m-d', strtotime('-6 days'));
        $dateTo = $today;
        break;
    case 'this_month':
        $dateFrom = date('Y-m-01');
        $dateTo = $today;
        break;
    case 'last_month':
        $dateFrom = date('Y-m-01', strtotime('first day of last month'));
        $dateTo = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'last_30_days':
        $dateFrom = date('Y-m-d', strtotime('-29 days'));
        $dateTo = $today;
        break;
    case 'this_year':
        $dateFrom = date('Y-01-01');
        $dateTo = $today;
        break;
    case 'custom':
        $custFrom = trim((string)($_GET['date_from'] ?? ''));
        $custTo = trim((string)($_GET['date_to'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $custFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $custTo)) {
            $dateFrom = $custFrom;
            $dateTo = $custTo;
        } else {
            $dateFrom = date('Y-m-01');
            $dateTo = $today;
        }
        break;
    default:
        $duration = 'this_month';
        $dateFrom = date('Y-m-01');
        $dateTo = $today;
        break;
}

// Ensure correct date order
if ($dateFrom > $dateTo) {
    $tmp = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo = $tmp;
}

// Optional Filters
$deptFilter = trim((string)($_GET['dept_filter'] ?? 'all'));
$doctorFilter = trim((string)($_GET['doctor_filter'] ?? ''));
$userFilter = trim((string)($_GET['user_filter'] ?? ''));

// Fetch organization settings for print header
$settings = db()->fetchOne("SELECT * FROM lab_settings WHERE organization_id = :org", [':org' => $orgId]) ?: [];
$labName = $settings['lab_name'] ?? 'Diagnostic Center + Hospital & Laboratory';
$labPhone = $settings['phone'] ?? '+92 306 5193582';
$labAddress = $settings['address'] ?? '';

// ==========================================
// DATA EXTRACTION (MariaDB)
// ==========================================

// 1. Lab Entries
$sqlLab = "SELECT 'Laboratory' AS default_dept, le.id, le.lab_no AS ref_no, le.patient_id, le.patient_name,
                  le.tests AS study_title, le.amount, le.paid, le.discount,
                  le.status, le.created_at, le.created_by, le.doctor, le.branch, le.branch_id,
                  COALESCE(le.modality, 'Laboratory') AS dept,
                  COALESCE(p.phone, '—') AS patient_phone,
                  COALESCE(p.age, '—') AS patient_age,
                  COALESCE(p.gender, '—') AS patient_gender
           FROM lab_entries le
           LEFT JOIN patients p ON (p.id = le.patient_id OR p.patient_no = le.patient_id)
           WHERE le.organization_id = :org AND DATE(le.created_at) BETWEEN :dt_from AND :dt_to";
$paramsLab = [':org' => $orgId, ':dt_from' => $dateFrom, ':dt_to' => $dateTo];

if ($doctorFilter !== '') {
    $sqlLab .= " AND le.doctor = :doc_lab";
    $paramsLab[':doc_lab'] = $doctorFilter;
}
if ($userFilter !== '') {
    $sqlLab .= " AND le.created_by = :user_lab";
    $paramsLab[':user_lab'] = $userFilter;
}
$rawLab = db()->fetchAll($sqlLab, $paramsLab);

// 2. Imaging & Modality Scans (USG, X-Ray, Echo, ECG, CT, MRI)
$sqlImg = "SELECT s.modality AS default_dept, s.id, s.scan_no AS ref_no, s.patient_id, s.patient AS patient_name,
                  s.study AS study_title, COALESCE(s.amount, 0) AS amount,
                  COALESCE(s.paid, 0) AS paid, COALESCE(s.discount, 0) AS discount,
                  s.status, s.created_at, s.created_by, s.doctor,
                  s.modality AS dept,
                  'IMG-01' AS branch, s.branch_id,
                  COALESCE(p.phone, '—') AS patient_phone,
                  COALESCE(p.age, '—') AS patient_age,
                  COALESCE(p.gender, '—') AS patient_gender,
                  s.lab_no
           FROM imaging_scans s
           LEFT JOIN patients p ON (p.id = s.patient_id OR p.patient_no = s.patient_id)
           WHERE s.organization_id = :org AND (
               (s.scan_date IS NOT NULL AND s.scan_date BETWEEN :dt_from AND :dt_to)
               OR (s.scan_date IS NULL AND DATE(s.created_at) BETWEEN :dt_from2 AND :dt_to2)
           )";
$paramsImg = [
    ':org' => $orgId,
    ':dt_from' => $dateFrom,
    ':dt_to' => $dateTo,
    ':dt_from2' => $dateFrom,
    ':dt_to2' => $dateTo,
];

if ($doctorFilter !== '') {
    $sqlImg .= " AND s.doctor = :doc_img";
    $paramsImg[':doc_img'] = $doctorFilter;
}
if ($userFilter !== '') {
    $sqlImg .= " AND s.created_by = :user_img";
    $paramsImg[':user_img'] = $userFilter;
}
$rawImg = db()->fetchAll($sqlImg, $paramsImg);

// Group imaging scans by parent lab order number
$imgByLab = [];
foreach ($rawImg as $rRow) {
    $ln = trim((string)($rRow['lab_no'] ?? ''));
    if ($ln !== '') {
        $imgByLab[$ln][] = $rRow;
    }
}

// Combine all patient transactions with accurate department allocation (no duplicates, no double counting)
$allTransactions = [];
$handledScanNos = [];

foreach ($rawLab as $row) {
    $lNo = (string)$row['ref_no'];
    $bd = allocate_order_breakdown([
        'tests' => $row['study_title'],
        'amount' => $row['amount'],
        'discount' => $row['discount'],
        'paid' => $row['paid'],
    ], $orgId);

    // 1. If this visit has Pathology tests, add 1 Laboratory transaction with exact pathology share
    if (!empty($bd['lab']['has_lab'])) {
        $amount = (float)$bd['lab']['gross'];
        $discount = (float)$bd['lab']['discount'];
        $paid = (float)$bd['lab']['paid'];
        $due = (float)$bd['lab']['due'];

        $allTransactions[] = [
            'source'        => 'Laboratory',
            'dept'          => 'Laboratory',
            'ref_no'        => (string)$row['ref_no'],
            'patient_id'    => (string)($row['patient_id'] ?: '—'),
            'patient_name'  => (string)$row['patient_name'],
            'patient_phone' => (string)$row['patient_phone'],
            'patient_age'   => (string)$row['patient_age'],
            'patient_gender'=> (string)$row['patient_gender'],
            'study_title'   => (string)$bd['lab']['studies'],
            'doctor'        => (string)($row['doctor'] ?: 'Self / Walk-in'),
            'created_by'    => (string)($row['created_by'] ?: 'Reception / Staff'),
            'created_at'    => (string)$row['created_at'],
            'date'          => substr((string)$row['created_at'], 0, 10),
            'status'        => (string)$row['status'],
            'amount'        => $amount,
            'discount'      => $discount,
            'net'           => max(0.0, $amount - $discount),
            'paid'          => $paid,
            'due'           => $due,
        ];
    }

    // 2. Imaging scans for this visit with their exact allocated share
    $linkedScans = $imgByLab[$lNo] ?? [];
    if (!empty($linkedScans)) {
        $imAllocMap = [];
        foreach ($bd['imaging'] as $im) {
            $imAllocMap[strtolower(trim((string)$im['name']))] = $im;
        }

        foreach ($linkedScans as $sRow) {
            $handledScanNos[$sRow['ref_no']] = true;
            $sKey = strtolower(trim((string)$sRow['study_title']));
            $alloc = $imAllocMap[$sKey] ?? null;
            if (!$alloc) {
                foreach ($imAllocMap as $k => $candidate) {
                    if (str_contains($sKey, $k) || str_contains($k, $sKey)) {
                        $alloc = $candidate;
                        break;
                    }
                }
            }

            $amount = $alloc ? (float)$alloc['allocated_gross'] : (float)($sRow['amount'] ?? 0);
            $discount = $alloc ? (float)$alloc['allocated_discount'] : (float)($sRow['discount'] ?? 0);
            $paid = $alloc ? (float)$alloc['allocated_paid'] : (float)($sRow['paid'] ?? 0);
            $due = max(0.0, ($amount - $discount) - $paid);

            $mod = trim((string)$sRow['dept']);
            if ($mod === '') $mod = 'Ultrasound';
            if (strcasecmp($mod, 'Echo') === 0 || strcasecmp($mod, 'Echocardiography') === 0) {
                $mod = 'Echocardiography (Echo)';
            }

            $allTransactions[] = [
                'source'        => 'Diagnostic Imaging',
                'dept'          => $mod,
                'ref_no'        => (string)$sRow['ref_no'],
                'patient_id'    => (string)($sRow['patient_id'] ?: '—'),
                'patient_name'  => (string)$sRow['patient_name'],
                'patient_phone' => (string)$sRow['patient_phone'],
                'patient_age'   => (string)$sRow['patient_age'],
                'patient_gender'=> (string)$sRow['patient_gender'],
                'study_title'   => (string)$sRow['study_title'],
                'doctor'        => (string)($sRow['doctor'] ?: 'Consultant / Self'),
                'created_by'    => (string)($sRow['created_by'] ?: 'Radiology Staff'),
                'created_at'    => (string)$sRow['created_at'],
                'date'          => substr((string)$sRow['created_at'], 0, 10),
                'status'        => (string)$sRow['status'],
                'amount'        => $amount,
                'discount'      => $discount,
                'net'           => max(0.0, $amount - $discount),
                'paid'          => $paid,
                'due'           => $due,
            ];
        }
    } else {
        // Fallback for imaging items not yet in imaging_scans table
        foreach ($bd['imaging'] as $im) {
            $amount = (float)$im['allocated_gross'];
            $discount = (float)$im['allocated_discount'];
            $paid = (float)$im['allocated_paid'];
            $due = (float)$im['allocated_due'];

            $allTransactions[] = [
                'source'        => 'Diagnostic Imaging',
                'dept'          => $im['department'],
                'ref_no'        => (string)$row['ref_no'],
                'patient_id'    => (string)($row['patient_id'] ?: '—'),
                'patient_name'  => (string)$row['patient_name'],
                'patient_phone' => (string)$row['patient_phone'],
                'patient_age'   => (string)$row['patient_age'],
                'patient_gender'=> (string)$row['patient_gender'],
                'study_title'   => (string)$im['name'],
                'doctor'        => (string)($row['doctor'] ?: 'Consultant / Self'),
                'created_by'    => (string)($row['created_by'] ?: 'Staff'),
                'created_at'    => (string)$row['created_at'],
                'date'          => substr((string)$row['created_at'], 0, 10),
                'status'        => (string)$row['status'],
                'amount'        => $amount,
                'discount'      => $discount,
                'net'           => max(0.0, $amount - $discount),
                'paid'          => $paid,
                'due'           => $due,
            ];
        }
    }
}

// 3. Standalone imaging scans not linked to a lab entry
foreach ($rawImg as $row) {
    if (!isset($handledScanNos[$row['ref_no']])) {
        $amount = (float)($row['amount'] ?? 0);
        $discount = (float)($row['discount'] ?? 0);
        $paid = (float)($row['paid'] ?? 0);
        $due = max(0.0, ($amount - $discount) - $paid);

        $mod = trim((string)$row['dept']);
        if ($mod === '') $mod = 'Ultrasound';
        if (strcasecmp($mod, 'Echo') === 0 || strcasecmp($mod, 'Echocardiography') === 0) {
            $mod = 'Echocardiography (Echo)';
        }

        $allTransactions[] = [
            'source'        => 'Diagnostic Imaging',
            'dept'          => $mod,
            'ref_no'        => (string)$row['ref_no'],
            'patient_id'    => (string)($row['patient_id'] ?: '—'),
            'patient_name'  => (string)$row['patient_name'],
            'patient_phone' => (string)$row['patient_phone'],
            'patient_age'   => (string)$row['patient_age'],
            'patient_gender'=> (string)$row['patient_gender'],
            'study_title'   => (string)$row['study_title'],
            'doctor'        => (string)($row['doctor'] ?: 'Consultant / Self'),
            'created_by'    => (string)($row['created_by'] ?: 'Radiology Staff'),
            'created_at'    => (string)$row['created_at'],
            'date'          => substr((string)$row['created_at'], 0, 10),
            'status'        => (string)$row['status'],
            'amount'        => $amount,
            'discount'      => $discount,
            'net'           => max(0.0, $amount - $discount),
            'paid'          => $paid,
            'due'           => $due,
        ];
    }
}

// 3. Expenses
$sqlExp = "SELECT e.id, e.title, e.category, e.amount, e.payment_mode,
                  e.receipt_no, e.notes, e.expense_date, e.created_by, e.created_at
           FROM expenses e
           WHERE e.organization_id = :org AND e.expense_date BETWEEN :dt_from AND :dt_to";
$paramsExp = [':org' => $orgId, ':dt_from' => $dateFrom, ':dt_to' => $dateTo];
if ($userFilter !== '') {
    $sqlExp .= " AND e.created_by = :user_exp";
    $paramsExp[':user_exp'] = $userFilter;
}
$sqlExp .= " ORDER BY e.expense_date DESC, e.created_at DESC";
$rawExpenses = db()->fetchAll($sqlExp, $paramsExp);

// 4. Doctor Shares & Commissions map
$rawShares = db()->fetchAll("SELECT doctor_name, commission_percent FROM doctor_shares WHERE organization_id = :org", [':org' => $orgId]);
$doctorCommissionMap = [];
foreach ($rawShares as $ds) {
    $doctorCommissionMap[trim(mb_strtolower($ds['doctor_name']))] = (float)$ds['commission_percent'];
}

// 5. Test catalog prices map
$rawCatalogTests = db()->fetchAll("SELECT name, category, price FROM tests WHERE organization_id = :org", [':org' => $orgId]);
$testCatalogPriceMap = [];
foreach ($rawCatalogTests as $ct) {
    $testCatalogPriceMap[trim(mb_strtolower($ct['name']))] = [
        'category' => $ct['category'] ?: 'Laboratory',
        'price' => (float)$ct['price'],
    ];
}

// Calculate Global Metrics
$totalOrders = count($allTransactions);
$totalGross = array_sum(array_column($allTransactions, 'amount'));
$totalDiscount = array_sum(array_column($allTransactions, 'discount'));
$totalNet = array_sum(array_column($allTransactions, 'net'));
$totalPaid = array_sum(array_column($allTransactions, 'paid'));
$totalDue = array_sum(array_column($allTransactions, 'due'));
$totalExpenses = array_sum(array_column($rawExpenses, 'amount'));
$netSurplus = $totalPaid - $totalExpenses;

// ==========================================
// REPORT-SPECIFIC COMPUTATION
// ==========================================
$reportColumns = [];
$reportRows = [];
$reportTotals = [];

switch ($reportType) {
    case 'dept_business':
        $reportColumns = ['#', 'Department / Modality', 'Total Orders', 'Gross Bill (Rs.)', 'Discounts (Rs.)', 'Net Revenue (Rs.)', 'Cash Paid (Rs.)', 'Dues (Rs.)', 'Business Share (%)'];
        $byDept = [];
        foreach ($allTransactions as $t) {
            $d = $t['dept'] ?: 'Other';
            if (!isset($byDept[$d])) {
                $byDept[$d] = ['dept' => $d, 'orders' => 0, 'gross' => 0.0, 'discount' => 0.0, 'net' => 0.0, 'paid' => 0.0, 'due' => 0.0];
            }
            $byDept[$d]['orders']++;
            $byDept[$d]['gross'] += $t['amount'];
            $byDept[$d]['discount'] += $t['discount'];
            $byDept[$d]['net'] += $t['net'];
            $byDept[$d]['paid'] += $t['paid'];
            $byDept[$d]['due'] += $t['due'];
        }
        // Sort by gross descending
        uasort($byDept, fn($a, $b) => $b['gross'] <=> $a['gross']);
        $idx = 1;
        foreach ($byDept as $d => $r) {
            $share = $totalGross > 0 ? round(($r['gross'] / $totalGross) * 100, 1) : 0;
            $reportRows[] = [
                $idx++,
                $d,
                number_format($r['orders']),
                number_format($r['gross'], 2),
                number_format($r['discount'], 2),
                number_format($r['net'], 2),
                number_format($r['paid'], 2),
                number_format($r['due'], 2),
                $share . '%',
            ];
        }
        $reportTotals = [
            'Total',
            count($byDept) . ' Departments',
            number_format($totalOrders),
            number_format($totalGross, 2),
            number_format($totalDiscount, 2),
            number_format($totalNet, 2),
            number_format($totalPaid, 2),
            number_format($totalDue, 2),
            '100.0%',
        ];
        break;

    case 'registrations':
        $reportColumns = ['#', 'Date & Time', 'Ref / Lab No.', 'MR No.', 'Patient Name & Contact', 'Department', 'Tests / Investigation', 'Doctor', 'Gross (Rs.)', 'Discount', 'Paid', 'Due', 'Status'];
        $idx = 1;
        // In consolidated view (all departments), group by invoice so each patient visit is exactly 1 row
        $regTransactions = $allTransactions;
        if ($deptFilter === 'all') {
            $byRef = [];
            foreach ($allTransactions as $t) {
                $ref = (string)$t['ref_no'];
                if (!isset($byRef[$ref])) {
                    $byRef[$ref] = $t;
                    $byRef[$ref]['studies_arr'] = [$t['study_title']];
                    $byRef[$ref]['depts_arr'] = [$t['dept']];
                } else {
                    $byRef[$ref]['studies_arr'][] = $t['study_title'];
                    $byRef[$ref]['depts_arr'][] = $t['dept'];
                    $byRef[$ref]['amount'] += $t['amount'];
                    $byRef[$ref]['discount'] += $t['discount'];
                    $byRef[$ref]['paid'] += $t['paid'];
                    $byRef[$ref]['due'] += $t['due'];
                }
            }
            foreach ($byRef as &$br) {
                $uniqDepts = array_unique($br['depts_arr']);
                $br['dept'] = count($uniqDepts) > 1 ? 'Multi-Dept' : reset($uniqDepts);
                $br['study_title'] = implode(', ', array_unique($br['studies_arr']));
            }
            unset($br);
            $regTransactions = array_values($byRef);
        }

        // Sort newest first
        usort($regTransactions, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        foreach ($regTransactions as $t) {
            $reportRows[] = [
                $idx++,
                date('d M Y, h:i A', strtotime($t['created_at'])),
                $t['ref_no'],
                $t['patient_id'],
                $t['patient_name'] . ($t['patient_phone'] !== '—' ? ' (' . $t['patient_phone'] . ')' : ''),
                $t['dept'],
                $t['study_title'],
                $t['doctor'],
                number_format($t['amount'], 2),
                number_format($t['discount'], 2),
                number_format($t['paid'], 2),
                number_format($t['due'], 2),
                ucfirst($t['status']),
            ];
        }
        $reportTotals = [
            'Total',
            count($regTransactions) . ' Registrations',
            '—', '—', '—', '—', '—', '—',
            number_format($totalGross, 2),
            number_format($totalDiscount, 2),
            number_format($totalPaid, 2),
            number_format($totalDue, 2),
            '—',
        ];
        break;

    case 'user_business':
        $reportColumns = ['#', 'Staff / Cashier Name', 'Invoices Handled', 'Gross Billed (Rs.)', 'Discounts Given', 'Net Amount', 'Cash Collected (Rs.)', 'Outstanding Dues', 'Collection Share (%)'];
        $byUser = [];
        foreach ($allTransactions as $t) {
            $u = $t['created_by'] ?: 'Reception Staff';
            if (!isset($byUser[$u])) {
                $byUser[$u] = ['user' => $u, 'orders' => 0, 'gross' => 0.0, 'discount' => 0.0, 'net' => 0.0, 'paid' => 0.0, 'due' => 0.0];
            }
            $byUser[$u]['orders']++;
            $byUser[$u]['gross'] += $t['amount'];
            $byUser[$u]['discount'] += $t['discount'];
            $byUser[$u]['net'] += $t['net'];
            $byUser[$u]['paid'] += $t['paid'];
            $byUser[$u]['due'] += $t['due'];
        }
        uasort($byUser, fn($a, $b) => $b['paid'] <=> $a['paid']);
        $idx = 1;
        foreach ($byUser as $u => $r) {
            $share = $totalPaid > 0 ? round(($r['paid'] / $totalPaid) * 100, 1) : 0;
            $reportRows[] = [
                $idx++,
                $u,
                number_format($r['orders']),
                number_format($r['gross'], 2),
                number_format($r['discount'], 2),
                number_format($r['net'], 2),
                number_format($r['paid'], 2),
                number_format($r['due'], 2),
                $share . '%',
            ];
        }
        $reportTotals = [
            'Total',
            count($byUser) . ' Cashiers / Users',
            number_format($totalOrders),
            number_format($totalGross, 2),
            number_format($totalDiscount, 2),
            number_format($totalNet, 2),
            number_format($totalPaid, 2),
            number_format($totalDue, 2),
            '100.0%',
        ];
        break;

    case 'non_business':
        $reportColumns = ['#', 'Date & Time', 'Ref No.', 'Patient Name & Phone', 'Investigation / Service', 'Department', 'Nominal Rate (Rs.)', '100% Discount Granted', 'Cash Paid', 'Remarks / Classification'];
        $nonBiz = array_filter($allTransactions, fn($t) => ($t['amount'] > 0 && $t['discount'] >= $t['amount']) || ($t['amount'] == 0));
        $idx = 1;
        $nbGross = 0.0;
        $nbDisc = 0.0;
        foreach ($nonBiz as $t) {
            $nbGross += $t['amount'];
            $nbDisc += $t['discount'];
            $reportRows[] = [
                $idx++,
                date('d M Y, h:i A', strtotime($t['created_at'])),
                $t['ref_no'],
                $t['patient_name'] . ($t['patient_phone'] !== '—' ? ' (' . $t['patient_phone'] . ')' : ''),
                $t['study_title'],
                $t['dept'],
                number_format($t['amount'], 2),
                number_format($t['discount'], 2),
                'Rs. 0.00',
                ($t['discount'] >= $t['amount'] ? '100% Courtesy / Free Waiver' : 'Zero Nominal Tariff'),
            ];
        }
        $reportTotals = [
            'Total',
            count($nonBiz) . ' Non-Business Cases',
            '—', '—', '—', '—',
            number_format($nbGross, 2),
            number_format($nbDisc, 2),
            'Rs. 0.00',
            'Welfare & Free Slips',
        ];
        break;

    case 'dues':
        $reportColumns = ['#', 'Booking Date', 'Ref / Lab No.', 'Patient Name', 'Phone / WhatsApp', 'Doctor', 'Investigation', 'Total Bill (Rs.)', 'Paid (Rs.)', 'Balance Due (Rs.)'];
        $duesRows = array_filter($allTransactions, fn($t) => $t['due'] > 0.01);
        usort($duesRows, fn($a, $b) => $b['due'] <=> $a['due']);
        $idx = 1;
        $sumDues = 0.0;
        $sumBill = 0.0;
        $sumPaid = 0.0;
        foreach ($duesRows as $t) {
            $sumBill += $t['net'];
            $sumPaid += $t['paid'];
            $sumDues += $t['due'];
            $reportRows[] = [
                $idx++,
                date('d M Y', strtotime($t['created_at'])),
                $t['ref_no'],
                $t['patient_name'],
                $t['patient_phone'],
                $t['doctor'],
                $t['study_title'],
                number_format($t['net'], 2),
                number_format($t['paid'], 2),
                number_format($t['due'], 2),
            ];
        }
        $reportTotals = [
            'Total',
            count($duesRows) . ' Due Patients',
            '—', '—', '—', '—', '—',
            number_format($sumBill, 2),
            number_format($sumPaid, 2),
            number_format($sumDues, 2),
        ];
        break;

    case 'test_volume':
        $reportColumns = ['#', 'Test / Investigation Title', 'Department / Modality', 'Orders Count', 'Catalog Standard Rate (Rs.)', 'Estimated Gross Volume (Rs.)', 'Volume Share (%)'];
        $testsCountMap = [];
        foreach ($allTransactions as $t) {
            $parts = array_filter(array_map('trim', explode(',', $t['study_title'])));
            $countParts = max(1, count($parts));
            $amtPerPart = $t['amount'] / $countParts;
            foreach ($parts as $p) {
                if ($p === '') continue;
                $key = mb_convert_case($p, MB_CASE_TITLE, "UTF-8");
                if (!isset($testsCountMap[$key])) {
                    $testsCountMap[$key] = [
                        'name' => $key,
                        'dept' => $t['dept'],
                        'count' => 0,
                        'est_revenue' => 0.0,
                    ];
                }
                $testsCountMap[$key]['count']++;
                $testsCountMap[$key]['est_revenue'] += $amtPerPart;
            }
        }
        uasort($testsCountMap, fn($a, $b) => $b['count'] <=> $a['count']);
        $totalTestOrders = array_sum(array_column($testsCountMap, 'count'));
        $idx = 1;
        $sumTestRev = 0.0;
        foreach ($testsCountMap as $k => $r) {
            $lowerKey = trim(mb_strtolower($r['name']));
            $catPrice = $testCatalogPriceMap[$lowerKey]['price'] ?? ($r['est_revenue'] / max(1, $r['count']));
            $share = $totalTestOrders > 0 ? round(($r['count'] / $totalTestOrders) * 100, 1) : 0;
            $sumTestRev += $r['est_revenue'];
            $reportRows[] = [
                $idx++,
                $r['name'],
                $r['dept'],
                number_format($r['count']),
                number_format($catPrice, 2),
                number_format($r['est_revenue'], 2),
                $share . '%',
            ];
        }
        $reportTotals = [
            'Total',
            count($testsCountMap) . ' Unique Tests',
            'All Departments',
            number_format($totalTestOrders),
            '—',
            number_format($sumTestRev, 2),
            '100.0%',
        ];
        break;

    case 'doctor_referrals':
        $reportColumns = ['#', 'Referring Doctor / Consultant', 'Referred Patients', 'Gross Business (Rs.)', 'Discounts (Rs.)', 'Net Business (Rs.)', 'Commission Rate (%)', 'Doctor Share / Commission (Rs.)', 'Hospital / Lab Net (Rs.)'];
        $byDoc = [];
        foreach ($allTransactions as $t) {
            $doc = $t['doctor'] ?: 'Walk-in / Direct';
            if (!isset($byDoc[$doc])) {
                $byDoc[$doc] = ['doctor' => $doc, 'patients' => 0, 'gross' => 0.0, 'discount' => 0.0, 'net' => 0.0];
            }
            $byDoc[$doc]['patients']++;
            $byDoc[$doc]['gross'] += $t['amount'];
            $byDoc[$doc]['discount'] += $t['discount'];
            $byDoc[$doc]['net'] += $t['net'];
        }
        uasort($byDoc, fn($a, $b) => $b['gross'] <=> $a['gross']);
        $idx = 1;
        $totDocShare = 0.0;
        $totHospNet = 0.0;
        foreach ($byDoc as $doc => $r) {
            $lowerDoc = trim(mb_strtolower($doc));
            $commPct = $doctorCommissionMap[$lowerDoc] ?? (str_contains($lowerDoc, 'walk') ? 0.0 : 15.0);
            $docShare = round(($r['net'] * $commPct) / 100, 2);
            $hospNet = max(0.0, $r['net'] - $docShare);
            $totDocShare += $docShare;
            $totHospNet += $hospNet;
            $reportRows[] = [
                $idx++,
                $doc,
                number_format($r['patients']),
                number_format($r['gross'], 2),
                number_format($r['discount'], 2),
                number_format($r['net'], 2),
                $commPct . '%',
                number_format($docShare, 2),
                number_format($hospNet, 2),
            ];
        }
        $reportTotals = [
            'Total',
            count($byDoc) . ' Referring Doctors',
            number_format($totalOrders),
            number_format($totalGross, 2),
            number_format($totalDiscount, 2),
            number_format($totalNet, 2),
            '—',
            number_format($totDocShare, 2),
            number_format($totHospNet, 2),
        ];
        break;

    case 'daily_business':
        $reportColumns = ['#', 'Date', 'Day', 'Patients / Invoices', 'Gross Billed (Rs.)', 'Discounts (Rs.)', 'Net Receivable', 'Cash Collected (Rs.)', 'Daily Dues Added'];
        $byDay = [];
        // Populate all days in range
        $periodStart = new DateTime($dateFrom);
        $periodEnd = new DateTime($dateTo);
        $periodEnd->modify('+1 day');
        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($periodStart, $interval, $periodEnd);

        foreach ($dateRange as $dt) {
            $dStr = $dt->format('Y-m-d');
            $byDay[$dStr] = [
                'date' => $dStr,
                'day' => $dt->format('l'),
                'orders' => 0,
                'gross' => 0.0,
                'discount' => 0.0,
                'net' => 0.0,
                'paid' => 0.0,
                'due' => 0.0,
            ];
        }

        foreach ($allTransactions as $t) {
            $d = $t['date'];
            if (isset($byDay[$d])) {
                $byDay[$d]['orders']++;
                $byDay[$d]['gross'] += $t['amount'];
                $byDay[$d]['discount'] += $t['discount'];
                $byDay[$d]['net'] += $t['net'];
                $byDay[$d]['paid'] += $t['paid'];
                $byDay[$d]['due'] += $t['due'];
            }
        }
        krsort($byDay);
        $idx = 1;
        foreach ($byDay as $dStr => $r) {
            $reportRows[] = [
                $idx++,
                date('d M Y', strtotime($dStr)),
                $r['day'],
                number_format($r['orders']),
                number_format($r['gross'], 2),
                number_format($r['discount'], 2),
                number_format($r['net'], 2),
                number_format($r['paid'], 2),
                number_format($r['due'], 2),
            ];
        }
        $reportTotals = [
            'Total',
            count($byDay) . ' Days Period',
            '—',
            number_format($totalOrders),
            number_format($totalGross, 2),
            number_format($totalDiscount, 2),
            number_format($totalNet, 2),
            number_format($totalPaid, 2),
            number_format($totalDue, 2),
        ];
        break;

    case 'expenses':
        $reportColumns = ['#', 'Expense Date', 'Receipt / Voucher', 'Title & Description', 'Expense Category', 'Payment Mode', 'Logged By', 'Expense Amount (Rs.)'];
        $idx = 1;
        foreach ($rawExpenses as $e) {
            $reportRows[] = [
                $idx++,
                date('d M Y', strtotime($e['expense_date'])),
                $e['receipt_no'] ?: 'EXP-' . str_pad((string)$e['id'], 4, '0', STR_PAD_LEFT),
                $e['title'] . ($e['notes'] ? ' — ' . $e['notes'] : ''),
                $e['category'],
                $e['payment_mode'],
                $e['created_by'] ?: 'Admin / Staff',
                number_format((float)$e['amount'], 2),
            ];
        }
        $reportTotals = [
            'Total',
            count($rawExpenses) . ' Vouchers',
            '—', '—', '—', '—', '—',
            number_format($totalExpenses, 2),
        ];
        break;

    case 'pnl_summary':
        $reportColumns = ['#', 'Accounting Header', 'Description / Source', 'Amount (Rs.)', '% Realization'];
        $reportRows = [
            [1, 'Gross Billing Revenue', 'Sum of all Laboratory, Imaging & Modality bookings', number_format($totalGross, 2), '100.0%'],
            [2, 'Discounts & Waivers', 'Concessions, hospital courtesy and price adjustments', '-' . number_format($totalDiscount, 2), ($totalGross > 0 ? round(($totalDiscount / $totalGross) * 100, 1) : 0) . '%'],
            [3, 'Net Billed Value', 'Actual billable business after client discounts', number_format($totalNet, 2), ($totalGross > 0 ? round(($totalNet / $totalGross) * 100, 1) : 0) . '%'],
            [4, 'Cash Collected In-Hand', 'Actual cash and electronic receipts collected during duration', number_format($totalPaid, 2), ($totalNet > 0 ? round(($totalPaid / $totalNet) * 100, 1) : 0) . '% of Net'],
            [5, 'Unrecovered Patient Dues', 'Receivables remaining with walk-in or admitted patients', number_format($totalDue, 2), ($totalNet > 0 ? round(($totalDue / $totalNet) * 100, 1) : 0) . '% of Net'],
            [6, 'Operational Expenses Outflow', 'Reagents, electricity, rents, consumables, staff tea & logistics', '-' . number_format($totalExpenses, 2), ($totalPaid > 0 ? round(($totalExpenses / $totalPaid) * 100, 1) : 0) . '% of Cash'],
            [7, 'Net Operating Surplus / Profit', 'Cash Received minus Operational Expenses', number_format($netSurplus, 2), ($totalPaid > 0 ? round(($netSurplus / $totalPaid) * 100, 1) : 0) . '% Margin'],
        ];
        $reportTotals = [
            'Summary',
            'Net Financial Bottom Line',
            'Audit Period: ' . date('d M Y', strtotime($dateFrom)) . ' to ' . date('d M Y', strtotime($dateTo)),
            'Rs. ' . number_format($netSurplus, 2),
            ($netSurplus >= 0 ? 'Profitable' : 'Deficit'),
        ];
        break;
}

// ==========================================
// CSV EXPORT HANDLER
// ==========================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'report-' . $reportType . '-' . $dateFrom . '-to-' . $dateTo . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Microsoft Excel compatibility
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header info
    fputcsv($out, [$labName], ',', '"', "\\");
    fputcsv($out, [$validReportTypes[$reportType] . ' (' . date('d M Y', strtotime($dateFrom)) . ' to ' . date('d M Y', strtotime($dateTo)) . ')'], ',', '"', "\\");
    fputcsv($out, ['Generated On: ' . date('d M Y, h:i A') . ' by ' . ($user['name'] ?? 'Staff')], ',', '"', "\\");
    fputcsv($out, [], ',', '"', "\\"); // Blank line

    // Column Headings
    fputcsv($out, $reportColumns, ',', '"', "\\");

    // Data rows
    foreach ($reportRows as $row) {
        fputcsv($out, $row, ',', '"', "\\");
    }

    // Totals row
    if (!empty($reportTotals)) {
        fputcsv($out, [], ',', '"', "\\");
        fputcsv($out, $reportTotals, ',', '"', "\\");
    }

    fclose($out);
    exit;
}

// Generate base query string without export
$qsParams = $_GET;
unset($qsParams['export']);
$currentQs = http_build_query($qsParams);

ob_start();
?>
<style>
@media print {
    @page {
        size: A4 portrait !important;
        margin: 0 !important;
    }
    html, body {
        background: #ffffff !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        height: auto !important;
    }
    /* Hide everything by default, then selectively show the summary report print area */
    body * {
        visibility: hidden !important;
    }
    .summary-report-print,
    .summary-report-print *,
    .print-area,
    .print-area * {
        visibility: visible !important;
    }
    .summary-report-print {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 12mm 14mm !important;
        box-sizing: border-box !important;
        background: #ffffff !important;
    }
    .print-header-banner {
        display: block !important;
        visibility: visible !important;
        margin-bottom: 14px !important;
        padding-bottom: 10px !important;
        border-bottom: 2px solid #0f172a !important;
    }
    .no-print,
    .no-print * {
        display: none !important;
        height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        overflow: hidden !important;
    }
    /* Metrics strip for print */
    .metric-cards-container {
        display: grid !important;
        grid-template-columns: repeat(6, 1fr) !important;
        gap: 6px !important;
        margin-bottom: 14px !important;
    }
    .metric-card {
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
        padding: 5px 6px !important;
        background: #f8fafc !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .metric-card p {
        font-size: 10pt !important;
        margin-top: 2px !important;
    }
    /* Table Styling for crisp portrait print */
    table {
        width: 100% !important;
        border-collapse: collapse !important;
        page-break-inside: auto !important;
        table-layout: auto !important;
    }
    tr {
        page-break-inside: avoid !important;
        page-break-after: auto !important;
    }
    thead {
        display: table-header-group !important;
    }
    tfoot {
        display: table-footer-group !important;
    }
    th, td {
        padding: 4.5px 6px !important;
        font-size: 8pt !important;
        line-height: 1.3 !important;
        color: #0f172a !important;
        border-bottom: 1px solid #e2e8f0 !important;
        white-space: normal !important;
        word-break: break-word !important;
    }
    thead th {
        background-color: #f1f5f9 !important;
        color: #0f172a !important;
        font-weight: 800 !important;
        font-size: 7.5pt !important;
        text-transform: uppercase !important;
        border-bottom: 2px solid #0f172a !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    tfoot tr {
        background-color: #0f172a !important;
        color: #ffffff !important;
        font-weight: 800 !important;
        font-size: 8pt !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    tfoot td {
        color: #ffffff !important;
    }
}
.print-header-banner {
    display: none;
}
</style>

<div class="summary-report-print print-area space-y-6">

    <!-- Top Action Banner & Print Header -->
    <div class="print-header-banner">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-black text-slate-950 uppercase tracking-tight"><?= e($labName) ?></h1>
                <p class="text-xs text-slate-600 mt-1"><?= e($labAddress) ?> &bull; Phone: <?= e($labPhone) ?></p>
            </div>
            <div class="text-right">
                <span class="inline-block px-3 py-1 bg-slate-900 text-white text-xs font-bold rounded uppercase"><?= e($validReportTypes[$reportType]) ?></span>
                <p class="text-xs text-slate-500 mt-1">Duration: <strong><?= date('d M Y', strtotime($dateFrom)) ?></strong> to <strong><?= date('d M Y', strtotime($dateTo)) ?></strong></p>
                <p class="text-[10px] text-slate-400">Printed: <?= date('d M Y, h:i A') ?> by <?= e($user['name'] ?? 'Staff') ?></p>
            </div>
        </div>
    </div>

    <!-- Header bar (No-Print) -->
    <div class="no-print pb-3 border-b border-slate-200 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">Summary Reports Engine</h1>
            <p class="text-xs text-slate-500 font-medium">Department gross volume, cash collections, patient dues, and doctor shares.</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" onclick="window.print()" class="btn btn-secondary text-xs py-2 px-3.5 shadow-xs font-bold">
                <i class="fa-solid fa-print text-slate-500 mr-1.5"></i> Print
            </button>
            <a href="?<?= $currentQs ?>&export=csv" class="btn btn-primary text-xs py-2 px-3.5 shadow-xs font-bold">
                <i class="fa-solid fa-file-excel mr-1.5"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Filter Form Bar (No-Print) -->
    <div class="no-print bg-white rounded-2xl border border-slate-200 p-5 shadow-xs space-y-4">
        <form method="GET" action="/portals/summary-reports.php" id="reportFilterForm" class="space-y-4">
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                <!-- 1. Report Type Dropdown -->
                <div class="space-y-1.5">
                    <label for="report_type" class="block text-xs font-bold text-slate-700 uppercase tracking-wide">
                        <i class="fa-solid fa-chart-pie text-indigo-600 mr-1"></i> Select Report Type
                    </label>
                    <select name="report_type" id="report_type" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-300 bg-slate-50/50 py-2.5 px-3 text-sm font-semibold text-slate-800 focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all">
                        <?php foreach ($validReportTypes as $val => $label): ?>
                            <option value="<?= e($val) ?>" <?= $reportType === $val ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. Duration Dropdown -->
                <div class="space-y-1.5">
                    <label for="duration" class="block text-xs font-bold text-slate-700 uppercase tracking-wide">
                        <i class="fa-regular fa-calendar-days text-teal-600 mr-1"></i> Select Duration
                    </label>
                    <select name="duration" id="duration" onchange="toggleCustomDates(this.value); if(this.value !== 'custom') this.form.submit();" class="w-full rounded-xl border border-slate-300 bg-slate-50/50 py-2.5 px-3 text-sm font-semibold text-slate-800 focus:bg-white focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition-all">
                        <option value="today" <?= $duration === 'today' ? 'selected' : '' ?>>Today (<?= date('d M') ?>)</option>
                        <option value="yesterday" <?= $duration === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                        <option value="this_week" <?= $duration === 'this_week' ? 'selected' : '' ?>>This Week (Monday to Today)</option>
                        <option value="last_7_days" <?= $duration === 'last_7_days' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="this_month" <?= $duration === 'this_month' ? 'selected' : '' ?>>This Month (<?= date('F Y') ?>)</option>
                        <option value="last_month" <?= $duration === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                        <option value="last_30_days" <?= $duration === 'last_30_days' ? 'selected' : '' ?>>Last 30 Days</option>
                        <option value="this_year" <?= $duration === 'this_year' ? 'selected' : '' ?>>This Year (<?= date('Y') ?>)</option>
                        <option value="custom" <?= $duration === 'custom' ? 'selected' : '' ?>>Custom Date Range &rarr;</option>
                    </select>
                </div>

                <!-- 3. Custom Date From & To -->
                <div id="customDateRangeBox" class="<?= $duration === 'custom' ? 'grid grid-cols-2 gap-2' : 'hidden' ?> md:col-span-2">
                    <div class="space-y-1.5">
                        <label for="date_from" class="block text-xs font-bold text-slate-600">From Date</label>
                        <input type="date" name="date_from" id="date_from" value="<?= e($dateFrom) ?>" class="w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm text-slate-800">
                    </div>
                    <div class="space-y-1.5">
                        <label for="date_to" class="block text-xs font-bold text-slate-600">To Date</label>
                        <div class="flex gap-2">
                            <input type="date" name="date_to" id="date_to" value="<?= e($dateTo) ?>" class="w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm text-slate-800">
                            <button type="submit" class="btn btn-primary px-3 text-xs shrink-0" title="Apply Custom Dates">Apply</button>
                        </div>
                    </div>
                </div>

                <!-- 4. Quick Actions & Duration Presets (if not custom) -->
                <?php if ($duration !== 'custom'): ?>
                <div class="lg:col-span-2 flex items-center gap-1.5 flex-wrap">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wide mr-1">Presets:</span>
                    <a href="?report_type=<?= e($reportType) ?>&duration=today" class="px-2.5 py-1 rounded-lg text-xs font-bold border <?= $duration === 'today' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' ?>">Today</a>
                    <a href="?report_type=<?= e($reportType) ?>&duration=last_7_days" class="px-2.5 py-1 rounded-lg text-xs font-bold border <?= $duration === 'last_7_days' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' ?>">7 Days</a>
                    <a href="?report_type=<?= e($reportType) ?>&duration=this_month" class="px-2.5 py-1 rounded-lg text-xs font-bold border <?= $duration === 'this_month' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' ?>">This Month</a>
                    <a href="?report_type=<?= e($reportType) ?>&duration=last_30_days" class="px-2.5 py-1 rounded-lg text-xs font-bold border <?= $duration === 'last_30_days' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' ?>">30 Days</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Active Date Duration Pill -->
            <div class="flex items-center justify-between text-xs text-slate-600 bg-slate-50 rounded-xl px-4 py-2 border border-slate-200/80">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-filter text-indigo-500"></i>
                    <span>Viewing Report: <strong class="text-slate-900"><?= e($validReportTypes[$reportType]) ?></strong></span>
                    <span class="text-slate-300">&bull;</span>
                    <span>Date Range: <strong class="text-slate-900"><?= date('d M Y', strtotime($dateFrom)) ?></strong> to <strong class="text-slate-900"><?= date('d M Y', strtotime($dateTo)) ?></strong></span>
                    <span class="text-slate-400">(<?= (int)((strtotime($dateTo) - strtotime($dateFrom)) / 86400) + 1 ?> days period)</span>
                </div>
                <a href="/portals/summary-reports.php" class="text-indigo-600 hover:text-indigo-800 font-bold hover:underline">Reset Filters</a>
            </div>

        </form>
    </div>

    <!-- Executive Metric Cards -->
    <div class="metric-cards-container grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
        <!-- 1. Orders -->
        <div class="metric-card bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase text-slate-400">Total Bookings</span>
                <span class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xs"><i class="fa-solid fa-clipboard-list"></i></span>
            </div>
            <p class="text-lg sm:text-xl font-extrabold text-slate-900 mt-2"><?= number_format($totalOrders) ?></p>
            <p class="text-[10px] text-slate-500 mt-0.5">Lab + Imaging studies</p>
        </div>

        <!-- 2. Gross Amount -->
        <div class="metric-card bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase text-slate-400">Gross Business</span>
                <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs"><i class="fa-solid fa-receipt"></i></span>
            </div>
            <p class="text-lg sm:text-xl font-extrabold text-indigo-700 mt-2">Rs. <?= number_format($totalGross, 0) ?></p>
            <p class="text-[10px] text-slate-500 mt-0.5">Total tariff nominal</p>
        </div>

        <!-- 3. Discounts -->
        <div class="metric-card bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase text-slate-400">Discounts</span>
                <span class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xs"><i class="fa-solid fa-tag"></i></span>
            </div>
            <p class="text-lg sm:text-xl font-extrabold text-amber-600 mt-2">Rs. <?= number_format($totalDiscount, 0) ?></p>
            <p class="text-[10px] text-slate-500 mt-0.5"><?= $totalGross > 0 ? round(($totalDiscount / $totalGross) * 100, 1) : 0 ?>% discount rate</p>
        </div>

        <!-- 4. Cash Received -->
        <div class="metric-card bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase text-slate-400">Cash Received</span>
                <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs"><i class="fa-solid fa-money-bill-wave"></i></span>
            </div>
            <p class="text-lg sm:text-xl font-extrabold text-emerald-700 mt-2">Rs. <?= number_format($totalPaid, 0) ?></p>
            <p class="text-[10px] text-emerald-600 font-semibold mt-0.5">Actual cash collected</p>
        </div>

        <!-- 5. Outstanding Dues -->
        <div class="metric-card bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase text-slate-400">Pending Dues</span>
                <span class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-xs"><i class="fa-solid fa-hand-holding-dollar"></i></span>
            </div>
            <p class="text-lg sm:text-xl font-extrabold text-rose-600 mt-2">Rs. <?= number_format($totalDue, 0) ?></p>
            <p class="text-[10px] text-rose-500 font-semibold mt-0.5">Patient balances</p>
        </div>

        <!-- 6. Operating Expenses / Surplus -->
        <div class="metric-card bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase text-slate-400">Net Surplus</span>
                <span class="w-7 h-7 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center text-xs"><i class="fa-solid fa-scale-balanced"></i></span>
            </div>
            <p class="text-lg sm:text-xl font-extrabold <?= $netSurplus >= 0 ? 'text-purple-700' : 'text-rose-700' ?> mt-2">Rs. <?= number_format($netSurplus, 0) ?></p>
            <p class="text-[10px] text-slate-500 mt-0.5">Paid minus Rs. <?= number_format($totalExpenses, 0) ?> exp</p>
        </div>
    </div>

    <!-- Main Report Data Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        
        <!-- Table Top Header -->
        <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50">
            <div>
                <h2 class="text-base font-bold text-slate-900"><?= e($validReportTypes[$reportType]) ?></h2>
                <p class="text-xs text-slate-500">Showing audit rows for period <?= date('d M Y', strtotime($dateFrom)) ?> &mdash; <?= date('d M Y', strtotime($dateTo)) ?></p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 border border-slate-200 text-xs font-semibold text-slate-700">
                    <i class="fa-solid fa-list-check text-slate-500"></i> <?= count($reportRows) ?> Records
                </span>
            </div>
        </div>

        <!-- Responsive Table Container -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm border-collapse">
                <thead>
                    <tr class="bg-slate-100/80 text-slate-700 uppercase tracking-wider text-[11px] border-b border-slate-200 font-extrabold">
                        <?php foreach ($reportColumns as $colIdx => $colName): ?>
                            <th class="py-3 px-3.5 <?= $colIdx >= count($reportColumns) - 4 && !in_array($reportType, ['registrations', 'non_business']) ? 'text-right' : '' ?>">
                                <?= e($colName) ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($reportRows)): ?>
                        <tr>
                            <td colspan="<?= count($reportColumns) ?>" class="py-12 text-center text-slate-400">
                                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3 text-lg">
                                    <i class="fa-solid fa-folder-open"></i>
                                </div>
                                <p class="font-bold text-sm text-slate-600">No records found for this period.</p>
                                <p class="text-xs mt-0.5">Try choosing a different duration (e.g. This Month or Last 30 Days) or switch report types.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reportRows as $rIdx => $row): ?>
                            <tr class="hover:bg-slate-50/80 transition-colors <?= $rIdx % 2 === 0 ? 'bg-white' : 'bg-slate-50/30' ?>">
                                <?php foreach ($row as $cIdx => $cell): ?>
                                    <td class="py-3 px-3.5 whitespace-nowrap <?= $cIdx >= count($row) - 4 && !in_array($reportType, ['registrations', 'non_business']) ? 'text-right font-semibold' : '' ?> <?= $cIdx === 0 ? 'text-slate-400 font-bold' : 'text-slate-700' ?>">
                                        <?php if ($cIdx === 1 && $reportType === 'dept_business'): ?>
                                            <span class="font-bold text-slate-900"><?= e($cell) ?></span>
                                        <?php elseif ($reportType === 'dues' && $cIdx === 4 && $cell !== '—'): ?>
                                            <div class="inline-flex items-center gap-1.5">
                                                <span><?= e($cell) ?></span>
                                                <a href="https://wa.me/<?= preg_replace('/\D/', '', $cell) ?>?text=<?= rawurlencode('Assalam-o-Alaikum, reminder regarding pending lab/scan dues at ' . $labName) ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 text-xs" title="Send WhatsApp Due Reminder">
                                                    <i class="fa-brands fa-whatsapp"></i>
                                                </a>
                                            </div>
                                        <?php elseif ($cIdx === count($row) - 1 && $reportType === 'registrations'): ?>
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold <?= strtolower($cell) === 'completed' || strtolower($cell) === 'verified' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>">
                                                <?= e($cell) ?>
                                            </span>
                                        <?php else: ?>
                                            <?= e((string)$cell) ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>

                <!-- Totals Summary Row -->
                <?php if (!empty($reportTotals) && !empty($reportRows)): ?>
                    <tfoot>
                        <tr class="bg-slate-900 text-white font-extrabold text-xs sm:text-sm border-t-2 border-slate-950">
                            <?php foreach ($reportTotals as $tIdx => $tCell): ?>
                                <td class="py-3.5 px-3.5 whitespace-nowrap <?= $tIdx >= count($reportTotals) - 4 && !in_array($reportType, ['registrations', 'non_business']) ? 'text-right' : '' ?>">
                                    <?= e((string)$tCell) ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <!-- Table Footer Pagination / Quick Hint -->
        <div class="no-print p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-2 bg-slate-50/30">
            <span>Data calculated in real-time from MariaDB transaction audit logs.</span>
            <div class="flex items-center gap-3">
                <button type="button" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" class="hover:text-slate-800 flex items-center gap-1 font-bold">
                    <i class="fa-solid fa-arrow-up"></i> Back to Top
                </button>
            </div>
        </div>

    </div>

</div>

<script>
function toggleCustomDates(val) {
    const box = document.getElementById('customDateRangeBox');
    if (!box) return;
    if (val === 'custom') {
        box.classList.remove('hidden');
        box.classList.add('grid', 'grid-cols-2', 'gap-2');
    } else {
        box.classList.add('hidden');
        box.classList.remove('grid', 'grid-cols-2', 'gap-2');
    }
}
</script>
<?php
$content = ob_get_clean();
render_layout('Summary Reports & Business Audit', $portal, 'summary-reports', $content);
