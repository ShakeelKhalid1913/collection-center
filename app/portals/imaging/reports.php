<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';
require_once __DIR__ . '/../../includes/documents.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$scanNo = trim((string)($_GET['scan_no'] ?? ''));

$scan = null;
if ($scanNo !== '') {
    $scan = result_repo()->findImagingScan($scanNo);
}

if (!$scan) {
    // Find latest reported or pending scan
    $scans = result_repo()->getImagingScans($orgId);
    $reported = array_values(array_filter($scans, fn($s) => ($s['status'] ?? '') === 'reported'));
    $scan = $reported[0] ?? ($scans[0] ?? null);
}

$settings = branding_settings();

if (!$scan) {
    $content = page_header('Radiology Report', 'Diagnostic Center');
    $content .= card('<p class="p-6 text-slate-600">No imaging scans recorded yet. <a href="/portals/imaging/new-scan.php" class="text-teal-700 font-bold">Book a new study</a>.</p>');
    render_page('Reports', 'imaging', 'reports', $content);
    exit;
}

$scanNo = (string)$scan['scan_no'];
$patientName = (string)($scan['patient'] ?? 'Walk-in Patient');
$patientId = (string)($scan['patient_id'] ?? '');

$patient = null;
if ($patientId !== '') {
    $patient = patient_repo()->findById($patientId);
}
if (!$patient && $patientName !== '') {
    $patient = db()->fetchOne("SELECT * FROM patients WHERE full_name = :p LIMIT 1", ['p' => $patientName]);
}

$age = !empty($patient['age']) ? $patient['age'] . ' Y' : '—';
$gender = $patient['gender'] ?? '—';
$phone = $patient['phone'] ?? '—';
$mrNo = $patient['patient_no'] ?? ($patientId ?: '—');
$doctorName = $scan['radiologist'] ?? ($scan['doctor'] ?? 'Dr. Imran Sheikh');
$modality = $scan['modality'] ?? 'Radiology';
$studyName = $scan['study'] ?? 'Scan Study';
$findings = trim((string)($scan['findings'] ?? ''));
$impression = trim((string)($scan['impression'] ?? ''));
$clinicalNotes = trim((string)($scan['clinical_notes'] ?? ''));
$scanDate = !empty($scan['scan_date']) ? date('d M Y', strtotime($scan['scan_date'])) : date('d M Y');

$clinicalIndicationHtml = $clinicalNotes !== ''
    ? ('<div class="mt-3 text-xs text-slate-600 bg-amber-50/70 p-2.5 rounded-xl border border-amber-200/70"><strong>Clinical Indication:</strong> ' . e($clinicalNotes) . '</div>')
    : '';

$headerHtml = render_branded_header($settings, true);
$footerHtml = render_branded_footer($settings, true);
$credit = software_credit_footer(true);

$barcodeValue = $scanNo;
$barcodeHtml = function_exists('render_barcode_html')
    ? render_barcode_html($barcodeValue, 1.8, 36)
    : ('<div class="font-mono text-xs font-bold">' . e($barcodeValue) . '</div>');

$actionsHtml = <<<HTML
<div class="no-print mb-5 flex flex-wrap items-center justify-between gap-3 p-4 bg-white rounded-2xl border border-slate-200/90 shadow-sm">
    <div class="flex items-center gap-2">
        <a href="/portals/imaging/pending-scans.php" class="btn btn-secondary text-xs"><i class="fa-solid fa-arrow-left mr-1"></i> Pending Scans</a>
        <a href="/portals/imaging/receipt.php?scan_no={$scanNo}" class="btn btn-secondary text-xs"><i class="fa-solid fa-receipt mr-1"></i> Token Slip</a>
        <a href="/portals/imaging/results.php?scan_no={$scanNo}" class="btn btn-secondary text-xs"><i class="fa-solid fa-pen-nib mr-1"></i> Edit Findings</a>
    </div>
    <div class="flex items-center gap-2">
        <button type="button" onclick="window.print()" class="btn btn-primary text-xs font-bold px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white shadow-md">
            <i class="fa-solid fa-print mr-1.5"></i> Print Report
        </button>
    </div>
</div>
HTML;

$findingsContent = $findings !== ''
    ? nl2br(e($findings))
    : '<span class="text-slate-400 italic">Findings pending radiologist review.</span>';

$impressionContent = $impression !== ''
    ? nl2br(e($impression))
    : '<span class="text-slate-400 italic">Impression pending.</span>';

$doc = <<<HTML
<div class="print-area mx-auto max-w-3xl bg-white p-8 sm:p-10 rounded-3xl border border-slate-300 shadow-xl text-slate-900" style="min-height: 900px; display: flex; flex-direction: column; justify-content: space-between;">
    <div>
        {$headerHtml}

        <!-- Report Title Banner -->
        <div class="mt-4 pt-3 border-t-2 border-slate-900 flex flex-wrap items-center justify-between gap-2">
            <div>
                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Department of Diagnostic Radiology &amp; Imaging</span>
                <div class="text-xl font-black text-slate-900">{$studyName}</div>
            </div>
            <div class="text-right">
                {$barcodeHtml}
                <div class="text-[11px] font-mono font-bold text-slate-600 mt-1">{$scanNo}</div>
            </div>
        </div>

        <!-- Patient Demographics Table -->
        <div class="mt-4 bg-slate-50 rounded-2xl p-4 border border-slate-200 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div>
                <span class="text-slate-400 font-semibold block uppercase text-[10px]">Patient Name</span>
                <span class="font-bold text-slate-900 text-sm">{$patientName}</span>
            </div>
            <div>
                <span class="text-slate-400 font-semibold block uppercase text-[10px]">MR / Patient ID</span>
                <span class="font-mono font-bold text-slate-800">{$mrNo}</span>
            </div>
            <div>
                <span class="text-slate-400 font-semibold block uppercase text-[10px]">Age / Gender</span>
                <span class="font-semibold text-slate-800">{$age} / {$gender}</span>
            </div>
            <div>
                <span class="text-slate-400 font-semibold block uppercase text-[10px]">Scan Date</span>
                <span class="font-semibold text-slate-800">{$scanDate}</span>
            </div>
            <div class="sm:col-span-2">
                <span class="text-slate-400 font-semibold block uppercase text-[10px]">Reporting Consultant</span>
                <span class="font-bold text-slate-800">{$doctorName}</span>
            </div>
            <div class="sm:col-span-2">
                <span class="text-slate-400 font-semibold block uppercase text-[10px]">Modality</span>
                <span class="font-bold text-teal-800">{$modality}</span>
            </div>
        </div>

        <!-- Clinical Indication if present -->
        {$clinicalIndicationHtml}

        <!-- Radiological Findings -->
        <div class="mt-6">
            <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-500 border-b border-slate-200 pb-1 mb-2">Findings</h3>
            <div class="text-sm text-slate-800 leading-relaxed pl-1" style="font-family: inherit;">
                {$findingsContent}
            </div>
        </div>

        <!-- Diagnostic Impression -->
        <div class="mt-6 bg-slate-50 rounded-2xl p-4 border border-slate-200">
            <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-700 mb-1">Impression</h3>
            <div class="text-sm font-bold text-slate-900 leading-relaxed">
                {$impressionContent}
            </div>
        </div>
    </div>

    <!-- Signatory & Footer Block -->
    <div class="mt-12 pt-6 border-t border-slate-200">
        <div class="flex justify-between items-end mb-6">
            <div class="text-[11px] text-slate-400 max-w-xs">
                This is a computer-verified radiological diagnostic report. Clinical correlation is recommended.
            </div>
            <div class="text-right">
                <div class="h-10 border-b border-slate-300 w-48 ml-auto mb-1"></div>
                <div class="text-sm font-bold text-slate-900">{$doctorName}</div>
                <div class="text-xs text-slate-500">Consultant Radiologist / Sonologist</div>
            </div>
        </div>
        {$footerHtml}
        {$credit}
    </div>
</div>
HTML;

$content = page_header('Radiology Report', "Report preview · {$scanNo} · {$patientName}");
$content .= $actionsHtml;
$content .= '<div class="mt-4">' . $doc . '</div>';

render_page('Reports', 'imaging', 'reports', $content, true);
