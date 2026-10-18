<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';
require_once __DIR__ . '/../../includes/documents.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$scanNo = trim((string)($_GET['scan_no'] ?? $_POST['scan_no'] ?? ''));
$labNo = trim((string)($_GET['lab_no'] ?? $_POST['lab_no'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

// Search by query if provided
if ($q !== '' && $scanNo === '' && $labNo === '') {
    $foundScan = db()->fetchOne(
        "SELECT scan_no, lab_no FROM imaging_scans 
         WHERE (scan_no LIKE :q OR lab_no LIKE :q OR patient LIKE :q) 
         ORDER BY id DESC LIMIT 1",
        ['q' => '%' . $q . '%']
    );
    if ($foundScan) {
        $scanNo = (string)($foundScan['scan_no'] ?? '');
        $labNo = (string)($foundScan['lab_no'] ?? '');
    } else {
        $foundLab = db()->fetchOne(
            "SELECT lab_no FROM lab_entries 
             WHERE (lab_no LIKE :q OR patient_name LIKE :q OR patient_id LIKE :q) 
             ORDER BY id DESC LIMIT 1",
            ['q' => '%' . $q . '%']
        );
        if ($foundLab) {
            $labNo = (string)($foundLab['lab_no'] ?? '');
        }
    }
}

$scan = null;
if ($scanNo !== '') {
    $scan = result_repo()->findImagingScan($scanNo);
}

if (!$scan && $labNo !== '') {
    $scan = db()->fetchOne("SELECT * FROM imaging_scans WHERE lab_no = :lab_no ORDER BY id DESC LIMIT 1", ['lab_no' => $labNo]);
}

$labEntry = null;
if ($labNo !== '') {
    $labEntry = lab_repo()->findByLabNo($labNo);
} elseif ($scan && !empty($scan['lab_no'])) {
    $labNo = (string)$scan['lab_no'];
    $labEntry = lab_repo()->findByLabNo($labNo);
}

if (!$scan && !$labEntry) {
    // Fallback to latest scan or lab entry
    $scans = result_repo()->getImagingScans($orgId);
    $scan = $scans[0] ?? null;
    if ($scan && !empty($scan['lab_no'])) {
        $labNo = (string)$scan['lab_no'];
        $labEntry = lab_repo()->findByLabNo($labNo);
    }
}

if ($scan) {
    $scanNo = $scan['scan_no'];
    if ($labNo === '' && !empty($scan['lab_no'])) {
        $labNo = (string)$scan['lab_no'];
    }
}

$settings = branding_settings();

// Load patient details if available
$patient = null;
$patientId = $scan['patient_id'] ?? ($labEntry['patient_id'] ?? '');
if ($patientId !== '') {
    $patient = patient_repo()->findById($patientId);
}
if (!$patient && !empty($scan['patient'])) {
    $pFound = db()->fetchOne("SELECT * FROM patients WHERE full_name = :p LIMIT 1", ['p' => $scan['patient']]);
    if ($pFound) {
        $patient = $pFound;
    }
}
if (!$patient && !empty($labEntry['patient_name'])) {
    $pFound = db()->fetchOne("SELECT * FROM patients WHERE full_name = :p LIMIT 1", ['p' => $labEntry['patient_name']]);
    if ($pFound) {
        $patient = $pFound;
    }
}

$patientName = $scan['patient'] ?? ($labEntry['patient_name'] ?? ($patient['full_name'] ?? 'Walk-in Patient'));
$patientAge = !empty($patient['age']) ? $patient['age'] . ' Y' : '—';
$patientGender = $patient['gender'] ?? '—';
$patientPhone = $patient['phone'] ?? '—';
$patientMrNo = $patient['patient_no'] ?? ($patientId ?: '—');
$doctorName = $scan['radiologist'] ?? ($scan['doctor'] ?? ($labEntry['doctor'] ?? 'Walk-in / Self'));
$clinicalNotes = $scan['clinical_notes'] ?? ($labEntry['clinical_notes'] ?? '');
$createdAt = !empty($scan['created_at']) 
    ? date('d M Y, h:i A', strtotime($scan['created_at'])) 
    : (!empty($labEntry['created_at']) ? date('d M Y, h:i A', strtotime($labEntry['created_at'])) : date('d M Y, h:i A'));

// Gather all line items (Tests / Studies)
$lineItems = [];
$grossAmount = 0.0;

if ($labEntry && !empty($labEntry['tests'])) {
    $testArr = array_filter(array_map('trim', explode(',', (string)$labEntry['tests'])));
    foreach ($testArr as $tName) {
        $meta = get_department_meta($tName);
        $tRecord = test_repo()->findByCode($tName);
        $tPrice = $tRecord ? (float)($tRecord['price'] ?? 1000) : 1000.0;
        $lineItems[] = [
            'name' => $tName,
            'dept' => $meta['name'],
            'icon' => $meta['icon'],
            'price' => $tPrice,
        ];
        $grossAmount += $tPrice;
    }
} elseif ($scan) {
    $modality = $scan['modality'] ?? 'Diagnostic Scan';
    $studyName = $scan['study'] ?? 'Study / Exam';
    $sPrice = (float)($scan['amount'] ?? 1500);
    $meta = get_department_meta($studyName, $modality);
    $lineItems[] = [
        'name' => $studyName,
        'dept' => $meta['name'],
        'icon' => $meta['icon'],
        'price' => $sPrice,
    ];
    $grossAmount = $sPrice;
}

$amount = $labEntry ? (float)($labEntry['amount'] ?? $grossAmount) : ($scan ? (float)($scan['amount'] ?? $grossAmount) : $grossAmount);
if ($amount <= 0 && $grossAmount > 0) {
    $amount = $grossAmount;
}
$discount = $labEntry ? (float)($labEntry['discount'] ?? 0) : ($scan ? (float)($scan['discount'] ?? 0) : 0);
$paid = $labEntry ? (float)($labEntry['paid'] ?? 0) : ($scan ? (float)($scan['paid'] ?? 0) : 0);
$net = max(0, $amount - $discount);
$due = max(0, $net - $paid);

$tokenLabHint = $labNo !== '' ? ('<span class="text-xs font-mono font-semibold text-slate-500">Token Ref: ' . e($labNo) . '</span>') : '';
$amountFmt = number_format($amount, 0);
$discountFmt = number_format($discount, 0);
$netFmt = number_format($net, 0);
$paidFmt = number_format($paid, 0);
$dueFmt = number_format($due, 0);
$notesBlock = $clinicalNotes !== '' ? ('<div class="p-2 bg-amber-50 rounded-lg border border-amber-200 text-amber-900 mb-2"><strong>Indication:</strong> ' . e($clinicalNotes) . '</div>') : '';

$isCreated = isset($_GET['created']);
$alert = $isCreated ? flash_success('Booking order created successfully! Official patient bill / slip is ready to print.') : '';

// Generate barcode HTML
$barcodeValue = $scanNo ?: ($labNo ?: 'SCAN-001');
$barcodeHtml = function_exists('render_barcode_html')
    ? render_barcode_html($barcodeValue, 1.8, 38)
    : ('<div class="font-mono text-xs font-bold tracking-widest">' . e($barcodeValue) . '</div>');

// Dynamic Workstation Redirection Buttons
$workstationButtons = '';
$hasLab = false;
$hasUS = false;
$hasXR = false;
$hasECG = false;
$hasCT = false;
$hasMRI = false;

foreach ($lineItems as $item) {
    $dLow = strtolower($item['dept']);
    if (str_contains($dLow, 'path') || str_contains($dLow, 'lab')) $hasLab = true;
    if (str_contains($dLow, 'ultra')) $hasUS = true;
    if (str_contains($dLow, 'x-ray') || str_contains($dLow, 'xray')) $hasXR = true;
    if (str_contains($dLow, 'ecg')) $hasECG = true;
    if (str_contains($dLow, 'ct')) $hasCT = true;
    if (str_contains($dLow, 'mri')) $hasMRI = true;
}

if ($hasLab && $labNo !== '') {
    $workstationButtons .= '<a href="/portals/imaging/laboratory.php" class="btn btn-secondary text-xs font-bold text-emerald-800 hover:bg-emerald-50"><i class="fa-solid fa-flask mr-1 text-emerald-600"></i> Laboratory Station</a>';
}
if ($hasUS) {
    $workstationButtons .= '<a href="/portals/imaging/ultrasound.php" class="btn btn-secondary text-xs font-bold text-teal-800 hover:bg-teal-50"><i class="fa-solid fa-wave-square mr-1 text-teal-600"></i> Ultrasound Station</a>';
}
if ($hasXR) {
    $workstationButtons .= '<a href="/portals/imaging/xray.php" class="btn btn-secondary text-xs font-bold text-blue-800 hover:bg-blue-50"><i class="fa-solid fa-x-ray mr-1 text-blue-600"></i> X-Ray Station</a>';
}
if ($hasECG) {
    $workstationButtons .= '<a href="/portals/imaging/ecg.php" class="btn btn-secondary text-xs font-bold text-rose-800 hover:bg-rose-50"><i class="fa-solid fa-heart-pulse mr-1 text-rose-600"></i> ECG Station</a>';
}
if ($hasCT) {
    $workstationButtons .= '<a href="/portals/imaging/ct.php" class="btn btn-secondary text-xs font-bold text-purple-800 hover:bg-purple-50"><i class="fa-solid fa-laptop-medical mr-1 text-purple-600"></i> CT Station</a>';
}
if ($hasMRI) {
    $workstationButtons .= '<a href="/portals/imaging/mri.php" class="btn btn-secondary text-xs font-bold text-indigo-800 hover:bg-indigo-50"><i class="fa-solid fa-magnet mr-1 text-indigo-600"></i> MRI Station</a>';
}

$actionsHtml = <<<HTML
<div class="no-print mb-5 flex flex-wrap items-center justify-between gap-3 p-4 bg-white rounded-2xl border border-slate-200/90 shadow-sm">
    <div class="flex flex-wrap items-center gap-2">
        <a href="/portals/imaging/new-scan.php" class="btn btn-primary text-xs font-bold bg-[#c2f13c] text-slate-950 hover:bg-[#b2e62a]"><i class="fa-solid fa-plus mr-1"></i> New Booking</a>
        <a href="/portals/imaging/dashboard.php" class="btn btn-secondary text-xs"><i class="fa-solid fa-gauge-high mr-1"></i> Dashboard</a>
        {$workstationButtons}
    </div>
    <div class="flex items-center gap-2">
        <button type="button" onclick="window.print()" class="btn btn-primary text-xs font-bold px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white shadow-md">
            <i class="fa-solid fa-print mr-1.5"></i> Print Patient Slip
        </button>
        <a href="/portals/imaging/results.php?scan_no={$scanNo}" class="btn btn-secondary text-xs font-semibold">
            <i class="fa-solid fa-pen-to-square mr-1 text-teal-600"></i> Enter Findings
        </a>
    </div>
</div>
HTML;

// Quick Lookup Bar
$lookupHtml = <<<HTML
<div class="no-print mb-5 p-3.5 bg-white rounded-2xl border border-slate-200/90 shadow-2xs flex flex-wrap items-center justify-between gap-3 text-xs">
    <form method="get" class="flex flex-wrap items-center gap-2 flex-1 max-w-md">
        <label for="lookup-q" class="font-bold text-slate-700 whitespace-nowrap"><i class="fa-solid fa-magnifying-glass mr-1 text-slate-400"></i> Lookup Slip:</label>
        <input type="search" id="lookup-q" name="q" value="{$q}" placeholder="Token Ref, Lab No, MR No, or Patient..." class="field text-xs py-1.5 px-3 flex-1 min-w-[14rem]">
        <button type="submit" class="btn btn-primary text-xs py-1.5 px-3">Open</button>
    </form>
    <div class="text-slate-400 text-[11px]">
        Showing active bill for <strong>{$patientName}</strong>
    </div>
</div>
HTML;

$headerHtml = render_branded_header($settings, true);
$footerHtml = render_branded_footer($settings, true);

// Table of Line Items
$itemsTableRows = '';
foreach ($lineItems as $idx => $item) {
    $itemNo = $idx + 1;
    $iName = e($item['name']);
    $iDept = e($item['dept']);
    $iPriceFmt = number_format($item['price'], 0);
    $itemsTableRows .= <<<TR
    <tr>
        <td class="p-3 font-mono font-bold text-slate-500">{$itemNo}</td>
        <td class="p-3 font-bold text-slate-900 text-sm">{$iName}</td>
        <td class="p-3">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-slate-100 text-slate-700 border border-slate-200">
                <i class="{$item['icon']} text-[9px]"></i> {$iDept}
            </span>
        </td>
        <td class="p-3 text-right font-mono font-bold text-slate-900">Rs. {$iPriceFmt}</td>
    </tr>
TR;
}

$slipHtml = <<<HTML
<div class="print-area mx-auto max-w-2xl bg-white p-6 sm:p-8 rounded-3xl border border-slate-300 shadow-xl text-slate-900" style="font-family: inherit;">
    {$headerHtml}

    <div class="mt-4 pt-3 border-t-2 border-slate-900 flex flex-wrap items-center justify-between gap-2">
        <div>
            <span class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Diagnostic Center &bull; Patient Bill / Slip</span>
            <div class="text-2xl font-black text-slate-900 font-mono">{$barcodeValue}</div>
            {$tokenLabHint}
        </div>
        <div class="text-right">
            {$barcodeHtml}
            <div class="text-[11px] font-mono font-bold text-slate-600 mt-1">{$barcodeValue}</div>
        </div>
    </div>

    <!-- Patient & Study Meta Grid -->
    <div class="mt-4 bg-slate-50 rounded-2xl p-4 border border-slate-200/90 grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
        <div>
            <span class="text-slate-400 font-semibold block uppercase text-[10px]">Patient Name</span>
            <span class="font-bold text-slate-900 text-sm">{$patientName}</span>
        </div>
        <div>
            <span class="text-slate-400 font-semibold block uppercase text-[10px]">MR / Patient ID</span>
            <span class="font-mono font-bold text-slate-800">{$patientMrNo}</span>
        </div>
        <div>
            <span class="text-slate-400 font-semibold block uppercase text-[10px]">Age / Gender</span>
            <span class="font-semibold text-slate-800">{$patientAge} / {$patientGender}</span>
        </div>
        <div>
            <span class="text-slate-400 font-semibold block uppercase text-[10px]">Phone</span>
            <span class="font-semibold text-slate-800">{$patientPhone}</span>
        </div>
        <div>
            <span class="text-slate-400 font-semibold block uppercase text-[10px]">Doctor / Consultant</span>
            <span class="font-bold text-slate-800">{$doctorName}</span>
        </div>
        <div>
            <span class="text-slate-400 font-semibold block uppercase text-[10px]">Booking Date</span>
            <span class="font-semibold text-slate-800">{$createdAt}</span>
        </div>
    </div>

    <!-- Multi-Item Investigation Table -->
    <div class="mt-4 border border-slate-200 rounded-2xl overflow-hidden">
        <table class="w-full text-left text-xs border-collapse">
            <thead class="bg-slate-100 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                <tr>
                    <th class="p-3 w-8">#</th>
                    <th class="p-3">Investigation / Study</th>
                    <th class="p-3">Department</th>
                    <th class="p-3 text-right">Charges (Rs.)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
                {$itemsTableRows}
            </tbody>
        </table>
    </div>

    <!-- Financial Summary Box -->
    <div class="mt-4 flex flex-wrap items-end justify-between gap-4 pt-2">
        <div class="text-xs text-slate-500 max-w-xs">
            {$notesBlock}
            <p class="m-0 mt-2 text-[11px] leading-relaxed">
                Please bring this token slip when receiving your reports and films. For inquiries or online reporting, contact reception.
            </p>
        </div>
        <div class="w-56 bg-slate-50 border border-slate-200 rounded-2xl p-3 text-xs space-y-1.5 font-semibold">
            <div class="flex justify-between text-slate-600">
                <span>Subtotal:</span>
                <span class="font-mono">Rs. {$amountFmt}</span>
            </div>
            <div class="flex justify-between text-slate-600">
                <span>Discount:</span>
                <span class="font-mono">Rs. {$discountFmt}</span>
            </div>
            <div class="flex justify-between text-slate-900 font-bold border-t border-slate-200 pt-1">
                <span>Net Total:</span>
                <span class="font-mono">Rs. {$netFmt}</span>
            </div>
            <div class="flex justify-between text-emerald-700">
                <span>Paid:</span>
                <span class="font-mono font-bold">Rs. {$paidFmt}</span>
            </div>
            <div class="flex justify-between text-rose-700 font-extrabold border-t border-slate-200 pt-1">
                <span>Balance Due:</span>
                <span class="font-mono">Rs. {$dueFmt}</span>
            </div>
        </div>
    </div>

    <div class="mt-6 pt-4 border-t border-slate-200">
        {$footerHtml}
    </div>
</div>
HTML;

$content = page_header('Patient Bill / Slip', 'Diagnostic Center &bull; Official Token Receipt for Pathology, Ultrasound, X-Ray, ECG, CT & MRI.');
$content .= $alert;
$content .= $actionsHtml;
$content .= $lookupHtml;
$content .= $slipHtml;

$activeKey = isset($_GET['created']) ? 'new-scan' : 'receipt';
render_page('Patient Bill / Slip', 'imaging', $activeKey, $content, true);
