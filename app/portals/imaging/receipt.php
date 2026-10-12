<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';
require_once __DIR__ . '/../../includes/documents.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$scanNo = trim((string)($_GET['scan_no'] ?? $_POST['scan_no'] ?? ''));
$labNo = trim((string)($_GET['lab_no'] ?? $_POST['lab_no'] ?? ''));

$scan = null;
if ($scanNo !== '') {
    $scan = result_repo()->findImagingScan($scanNo);
}

if (!$scan && $labNo !== '') {
    $scan = db()->fetchOne("SELECT * FROM imaging_scans WHERE lab_no = :lab_no LIMIT 1", ['lab_no' => $labNo]);
}

if (!$scan) {
    // Fallback to latest scan
    $scans = result_repo()->getImagingScans($orgId);
    $scan = $scans[0] ?? null;
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
if (!empty($scan['patient_id'])) {
    $patient = patient_repo()->findById($scan['patient_id']);
}
if (!$patient && !empty($scan['patient'])) {
    $pFound = db()->fetchOne("SELECT * FROM patients WHERE full_name = :p LIMIT 1", ['p' => $scan['patient']]);
    if ($pFound) {
        $patient = $pFound;
    }
}

$patientName = $scan['patient'] ?? ($patient['full_name'] ?? 'Walk-in Patient');
$patientAge = !empty($patient['age']) ? $patient['age'] . ' Y' : '—';
$patientGender = $patient['gender'] ?? '—';
$patientPhone = $patient['phone'] ?? '—';
$patientMrNo = $patient['patient_no'] ?? ($scan['patient_id'] ?? '—');
$doctorName = $scan['radiologist'] ?? ($scan['doctor'] ?? 'Walk-in / Self');
$modality = $scan['modality'] ?? 'Diagnostic Scan';
$studyName = $scan['study'] ?? 'Study / Exam';
$clinicalNotes = $scan['clinical_notes'] ?? '';
$scanDate = !empty($scan['scan_date']) ? date('d M Y', strtotime($scan['scan_date'])) : date('d M Y');
$createdAt = !empty($scan['created_at']) ? date('d M Y, h:i A', strtotime($scan['created_at'])) : date('d M Y, h:i A');

$amount = (float)($scan['amount'] ?? 0);
$discount = (float)($scan['discount'] ?? 0);
$paid = (float)($scan['paid'] ?? 0);
if ($amount <= 0 && $labNo !== '') {
    $labEntry = lab_repo()->findByLabNo($labNo);
    if ($labEntry) {
        $amount = (float)($labEntry['amount'] ?? 0);
        $discount = (float)($labEntry['discount'] ?? 0);
        $paid = (float)($labEntry['paid'] ?? 0);
    }
}
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
$alert = $isCreated ? flash_success('Study booked successfully! Token slip is ready to print.') : '';

// Generate barcode HTML
$barcodeValue = $scanNo ?: ($labNo ?: 'SCAN-001');
$barcodeHtml = function_exists('render_barcode_html')
    ? render_barcode_html($barcodeValue, 1.8, 38)
    : ('<div class="font-mono text-xs font-bold tracking-widest">' . e($barcodeValue) . '</div>');

$actionsHtml = <<<HTML
<div class="no-print mb-5 flex flex-wrap items-center justify-between gap-3 p-4 bg-white rounded-2xl border border-slate-200/90 shadow-sm">
    <div class="flex items-center gap-2">
        <a href="/portals/imaging/pending-scans.php" class="btn btn-secondary text-xs"><i class="fa-solid fa-arrow-left mr-1"></i> Pending Studies</a>
        <a href="/portals/imaging/new-scan.php" class="btn btn-secondary text-xs"><i class="fa-solid fa-plus mr-1"></i> New Study</a>
    </div>
    <div class="flex items-center gap-2">
        <button type="button" onclick="window.print()" class="btn btn-primary text-xs font-bold px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white shadow-md">
            <i class="fa-solid fa-print mr-1.5"></i> Print Study Slip
        </button>
        <a href="/portals/imaging/results.php?scan_no={$scanNo}" class="btn btn-secondary text-xs font-semibold">
            <i class="fa-solid fa-pen-to-square mr-1"></i> Enter Findings
        </a>
    </div>
</div>
HTML;

$headerHtml = render_branded_header($settings, true);
$footerHtml = render_branded_footer($settings, true);

$slipHtml = <<<HTML
<div class="print-area mx-auto max-w-2xl bg-white p-6 sm:p-8 rounded-3xl border border-slate-300 shadow-xl text-slate-900" style="font-family: inherit;">
    {$headerHtml}

    <div class="mt-4 pt-3 border-t-2 border-slate-900 flex flex-wrap items-center justify-between gap-2">
        <div>
            <span class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Diagnostic Center Token Slip</span>
            <div class="text-2xl font-black text-slate-900 font-mono">{$scanNo}</div>
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
            <span class="text-slate-400 font-semibold block uppercase text-[10px]">Modality</span>
            <span class="font-bold text-teal-700 bg-teal-50 px-2 py-0.5 rounded border border-teal-200 inline-block">{$modality}</span>
        </div>
        <div>
            <span class="text-slate-400 font-semibold block uppercase text-[10px]">Doctor / Radiologist</span>
            <span class="font-bold text-slate-800">{$doctorName}</span>
        </div>
        <div>
            <span class="text-slate-400 font-semibold block uppercase text-[10px]">Booking Date</span>
            <span class="font-semibold text-slate-800">{$createdAt}</span>
        </div>
    </div>

    <!-- Study Details Table -->
    <div class="mt-4 border border-slate-200 rounded-2xl overflow-hidden">
        <table class="w-full text-left text-xs border-collapse">
            <thead class="bg-slate-100 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                <tr>
                    <th class="p-3">#</th>
                    <th class="p-3">Investigation / Study</th>
                    <th class="p-3">Department</th>
                    <th class="p-3 text-right">Charges (Rs.)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
                <tr>
                    <td class="p-3 font-mono font-bold">1</td>
                    <td class="p-3 font-bold text-slate-900 text-sm">{$studyName}</td>
                    <td class="p-3 text-slate-600">{$modality}</td>
                    <td class="p-3 text-right font-mono font-bold text-slate-900">Rs. {$amountFmt}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Financial Summary Box -->
    <div class="mt-4 flex flex-wrap items-end justify-between gap-4 pt-2">
        <div class="text-xs text-slate-500 max-w-xs">
            {$notesBlock}
            <p class="m-0 mt-2 text-[11px] leading-relaxed">Please bring this slip along when collecting your scan report / films. For appointment queries, contact reception.</p>
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

$content = page_header('Study Slip / Token', 'Diagnostic Center — Token Receipt for X-Ray, CT, Ultrasound, ECG.');
$content .= $alert;
$content .= $actionsHtml;
$content .= $slipHtml;

render_page('Study Slip', 'imaging', 'new-scan', $content, true);
