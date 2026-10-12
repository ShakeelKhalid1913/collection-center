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

$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$modality = trim((string)($_GET['modality'] ?? 'all'));
$branchFilter = trim((string)($_GET['branch'] ?? ($isCollectionCenter ? ($user['branch_id'] ?? '') : '')));

// Fetch lab entries
$sqlLab = "SELECT 'Laboratory' AS dept, le.lab_no AS ref_no, le.patient_id, le.patient_name,
                  le.tests AS study_title, le.amount, le.paid, le.discount, le.status, le.created_at,
                  le.created_by, le.doctor, le.branch, le.branch_id,
                  COALESCE(p.phone, '—') AS patient_phone
           FROM lab_entries le
           LEFT JOIN patients p ON (p.id = le.patient_id OR p.patient_no = le.patient_id)
           WHERE le.organization_id = :org AND DATE(le.created_at) = :dt";
$paramsLab = ['org' => $orgId, 'dt' => $date];

if ($branchFilter !== '') {
    $sqlLab .= " AND (le.branch_id = :branch OR le.branch = :branch)";
    $paramsLab['branch'] = $branchFilter;
}

$labEntries = db()->fetchAll($sqlLab, $paramsLab);

// Fetch imaging scans (X-Ray, Ultrasound, CT Scan, ECG)
$sqlImg = "SELECT s.modality AS dept, s.scan_no AS ref_no, s.patient_id, s.patient AS patient_name,
                  s.study AS study_title, COALESCE(s.amount, 0) AS amount, COALESCE(s.paid, 0) AS paid,
                  COALESCE(s.discount, 0) AS discount, s.status, s.created_at,
                  s.radiologist AS created_by, s.radiologist AS doctor, 'IMG-01' AS branch, s.branch_id,
                  COALESCE(p.phone, '—') AS patient_phone
           FROM imaging_scans s
           LEFT JOIN patients p ON (p.id = s.patient_id OR p.patient_no = s.patient_id)
           WHERE (s.organization_id = :org OR s.organization_id = 'ORG-001') AND DATE(s.scan_date) = :dt";
$paramsImg = ['org' => $orgId, 'dt' => $date];

if ($branchFilter !== '') {
    $sqlImg .= " AND s.branch_id = :branch";
    $paramsImg['branch'] = $branchFilter;
}

$imagingEntries = db()->fetchAll($sqlImg, $paramsImg);

// Combine and filter by modality if specified
$combined = array_merge($labEntries, $imagingEntries);

if ($modality !== 'all') {
    $combined = array_filter($combined, static function ($item) use ($modality) {
        $d = strtolower((string)$item['dept']);
        $m = strtolower($modality);
        if ($m === 'laboratory' || $m === 'lab') {
            return $d === 'laboratory';
        }
        if ($m === 'ultrasound' || $m === 'us') {
            return str_contains($d, 'ultrasound') || str_contains($d, 'us');
        }
        if ($m === 'x-ray' || $m === 'xray') {
            return str_contains($d, 'x-ray') || str_contains($d, 'xray');
        }
        if ($m === 'ct' || $m === 'ct scan') {
            return str_contains($d, 'ct');
        }
        if ($m === 'ecg') {
            return str_contains($d, 'ecg');
        }
        return str_contains($d, $m);
    });
}

// Sort by created_at ASC
usort($combined, static fn($a, $b) => strcmp((string)$a['created_at'], (string)$b['created_at']));

// Calculate totals
$totalPatients = count(array_unique(array_filter(array_column($combined, 'patient_id') ?: array_column($combined, 'patient_name'))));
$totalOrders = count($combined);
$totalGross = 0.0;
$totalDiscount = 0.0;
$totalPaid = 0.0;
$totalDue = 0.0;

// Breakdown by modality
$deptStats = [];

foreach ($combined as $c) {
    $g = (float)$c['amount'];
    $p = (float)$c['paid'];
    $d = (float)$c['discount'];
    $due = max(0, $g - $d - $p);

    $totalGross += $g;
    $totalDiscount += $d;
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

$netRevenue = max(0, $totalGross - $totalDiscount);

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
    $g = (float)$item['amount'];
    $d = (float)$item['discount'];
    $p = (float)$item['paid'];
    $due = max(0, $g - $d - $p);

    $slipRows .= '<tr>'
        . '<td class="py-2 px-3 text-xs text-slate-500">' . $idx . '</td>'
        . '<td class="py-2 px-3 text-xs font-mono">' . $time . '</td>'
        . '<td class="py-2 px-3 text-xs font-mono font-bold text-slate-900">' . $ref . '</td>'
        . '<td class="py-2 px-3 text-xs font-bold text-slate-800">' . $pName . '<br><span class="text-[10px] text-slate-400 font-normal">' . $pPhone . '</span></td>'
        . '<td class="py-2 px-3 text-xs">' . $deptBadge . '</td>'
        . '<td class="py-2 px-3 text-xs text-slate-600 max-w-xs truncate" title="' . $studies . '">' . $studies . '</td>'
        . '<td class="py-2 px-3 text-xs text-right font-medium">Rs ' . number_format($g, 0) . '</td>'
        . '<td class="py-2 px-3 text-xs text-right text-rose-600">Rs ' . number_format($d, 0) . '</td>'
        . '<td class="py-2 px-3 text-xs text-right font-bold text-emerald-700">Rs ' . number_format($p, 0) . '</td>'
        . '<td class="py-2 px-3 text-xs text-right font-bold ' . ($due > 0 ? 'text-amber-700' : 'text-slate-400') . '">Rs ' . number_format($due, 0) . '</td>'
        . '</tr>';
}

if ($slipRows === '') {
    $slipRows = '<tr><td colspan="10" class="text-center py-8 text-slate-400 text-xs">No patient entries recorded for this date and department.</td></tr>';
}

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
$breakdownsHtml = '';
foreach ($deptStats as $dName => $s) {
    $breakdownsHtml .= '<div class="p-3 bg-white border border-slate-200 rounded-xl">'
        . '<div class="text-[11px] uppercase font-bold text-slate-400 tracking-wider">' . e($dName) . '</div>'
        . '<div class="flex items-center justify-between mt-1">'
        . '<span class="text-xs text-slate-600">' . $s['count'] . ' studies</span>'
        . '<strong class="text-sm font-black text-slate-900">Rs ' . number_format($s['paid'], 0) . '</strong>'
        . '</div>'
        . '</div>';
}

$headerAction = '<button type="button" onclick="window.print()" class="btn btn-primary text-sm py-2 px-4 shadow-sm"><i class="fa-solid fa-print mr-1.5"></i> Print Closing Slip</button>';
$content = '<div class="no-print">' . page_header('Daily Financial Closing & Reconciliation', 'Shift reconciliation slip summarizing daily activity, patient counts, and revenue collected.', $headerAction) . '</div>';

$selAll = ($modality === 'all') ? 'selected' : '';
$selLab = ($modality === 'Laboratory') ? 'selected' : '';
$selUS  = ($modality === 'Ultrasound') ? 'selected' : '';
$selXR  = ($modality === 'X-Ray') ? 'selected' : '';
$selECG = ($modality === 'ECG') ? 'selected' : '';
$selCT  = ($modality === 'CT Scan') ? 'selected' : '';
$branchScopeHtml = ($branchOpts !== '') ? '<div><label class="field-label text-[11px] mb-1">Branch Scope</label><select name="branch" class="field text-xs font-semibold py-1.5" onchange="this.form.submit()">' . $branchOpts . '</select></div>' : '';
$modalityMiniCardsHtml = ($breakdownsHtml !== '' && $modality === 'all') ? '<div class="grid gap-3 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5">' . $breakdownsHtml . '</div>' : '';
$generatedTime = date('d-M-Y h:i A');
$operatorName = e($user['name'] ?? 'Counter');
$totalPaidFmt = number_format($totalPaid, 0);
$totalDueFmt = number_format($totalDue, 0);
$totalGrossFmt = number_format($totalGross, 0);
$totalDiscountFmt = number_format($totalDiscount, 0);

$content .= <<<HTML
<div class="no-print space-y-5">
    <!-- Filter Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-card">
        <form method="get" class="flex flex-wrap items-center gap-3">
            <div>
                <label class="field-label text-[11px] mb-1">Closing Date</label>
                <input type="date" name="date" value="{$date}" class="field text-xs font-semibold py-1.5" onchange="this.form.submit()">
            </div>
            <div>
                <label class="field-label text-[11px] mb-1">Department / Modality</label>
                <select name="modality" class="field text-xs font-semibold py-1.5" onchange="this.form.submit()">
                    <option value="all" {$selAll}>All Modalities (Consolidated)</option>
                    <option value="Laboratory" {$selLab}>Laboratory (Pathology)</option>
                    <option value="Ultrasound" {$selUS}>Ultrasound</option>
                    <option value="X-Ray" {$selXR}>X-Ray</option>
                    <option value="ECG" {$selECG}>ECG</option>
                    <option value="CT Scan" {$selCT}>CT Scan</option>
                </select>
            </div>
            {$branchScopeHtml}
            <div class="flex items-end self-end">
                <button type="submit" class="btn btn-secondary text-xs py-2 px-3"><i class="fa-solid fa-filter mr-1"></i> Refresh Slip</button>
            </div>
        </form>
    </div>

    <!-- Metrics Cards -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="stat-card stat-card--teal">
            <div class="stat-card__icon"><i class="fa-solid fa-users"></i></div>
            <div>
                <div class="stat-card__label">Total Patients</div>
                <div class="stat-card__value">{$totalPatients}</div>
                <div class="stat-card__hint">{$totalOrders} tests / studies</div>
            </div>
        </div>
        <div class="stat-card stat-card--blue">
            <div class="stat-card__icon"><i class="fa-solid fa-file-invoice"></i></div>
            <div>
                <div class="stat-card__label">Gross Billed</div>
                <div class="stat-card__value">Rs {$totalGross}</div>
                <div class="stat-card__hint">Before discounts</div>
            </div>
        </div>
        <div class="stat-card stat-card--rose">
            <div class="stat-card__icon"><i class="fa-solid fa-tag"></i></div>
            <div>
                <div class="stat-card__label">Discounts Given</div>
                <div class="stat-card__value">Rs {$totalDiscount}</div>
                <div class="stat-card__hint">Concessions / B2B</div>
            </div>
        </div>
        <div class="stat-card stat-card--violet">
            <div class="stat-card__icon"><i class="fa-solid fa-cash-register"></i></div>
            <div>
                <div class="stat-card__label">Total Cash Collected</div>
                <div class="stat-card__value">Rs {$totalPaid}</div>
                <div class="stat-card__hint">Net cash in drawer</div>
            </div>
        </div>
        <div class="stat-card stat-card--amber">
            <div class="stat-card__icon"><i class="fa-solid fa-clock"></i></div>
            <div>
                <div class="stat-card__label">Pending Dues</div>
                <div class="stat-card__value">Rs {$totalDue}</div>
                <div class="stat-card__hint">Receivables remaining</div>
            </div>
        </div>
    </div>

    <!-- Modality-Wise Mini Cards if Consolidated -->
    {$modalityMiniCardsHtml}
</div>

<!-- Printable Slip Container -->
<div class="mt-6 bg-white border border-slate-200/90 rounded-3xl p-6 shadow-card print:border-none print:shadow-none print:p-0 print:m-0">
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
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 border border-slate-200 rounded-xl p-3 mb-4 text-xs">
        <div>Total Patients: <strong class="font-mono text-sm text-slate-900">{$totalPatients}</strong></div>
        <div>Total Tests / Studies: <strong class="font-mono text-sm text-slate-900">{$totalOrders}</strong></div>
        <div>Total Cash Collected: <strong class="font-mono text-sm text-emerald-800">Rs {$totalPaidFmt}</strong></div>
        <div>Pending Receivables: <strong class="font-mono text-sm text-amber-800">Rs {$totalDueFmt}</strong></div>
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
                    <th class="py-2 px-3 text-right">Gross</th>
                    <th class="py-2 px-3 text-right">Disc</th>
                    <th class="py-2 px-3 text-right">Paid</th>
                    <th class="py-2 px-3 text-right">Due</th>
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
