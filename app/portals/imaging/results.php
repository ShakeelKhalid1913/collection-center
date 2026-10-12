<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
$scanNo = trim((string)($_GET['scan_no'] ?? $_POST['scan_no'] ?? ''));
$scan = $scanNo !== '' ? result_repo()->findImagingScan($scanNo) : null;

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$allScans = result_repo()->getImagingScans($orgId);

if (!$scan) {
    $pending = array_values(array_filter(
        $allScans,
        static fn($s) => in_array($s['status'] ?? '', ['pending', 'in_progress'], true)
    ));
    $scan = $pending[0] ?? ($allScans[0] ?? null);
}

// Dynamic doctors list
$doctorOpts = [];
foreach (mock('mock_doctors') as $d) {
    $spec = !empty($d['specialty']) ? ' (' . $d['specialty'] . ')' : '';
    $doctorOpts[$d['name']] = $d['name'] . $spec;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['scan_no'])) {
    $radCustom = trim((string)($_POST['rad_custom'] ?? ''));
    $radSelected = trim((string)($_POST['rad'] ?? ''));
    $finalRad = $radCustom !== '' ? $radCustom : ($radSelected !== '' ? resolve_doctor_name($radSelected) : 'Dr. Imran Sheikh');

    $ok = result_repo()->saveImagingFindings($_POST['scan_no'], [
        'radiologist' => $finalRad,
        'findings' => $_POST['findings'] ?? '',
        'impression' => $_POST['impression'] ?? '',
        'status' => 'reported',
    ]);
    if ($ok) {
        header('Location: /portals/imaging/reports.php?scan_no=' . urlencode($_POST['scan_no']));
        exit;
    }
    $message = flash_error('Could not save findings.');
    $scan = result_repo()->findImagingScan($_POST['scan_no']);
}

$titleHint = $scan
    ? (($scan['scan_no'] ?? '') . ' · ' . ($scan['patient'] ?? '') . ' · ' . ($scan['study'] ?? ''))
    : 'No studies yet';

$content = page_header('Results / Findings', $titleHint);
$content .= $message;

if (!$scan) {
    $content .= card(
        '<p class="p-6 text-sm text-slate-600">No imaging studies found. Book a study from New Study first.</p>' .
        '<div class="p-6 pt-0">' . btn_primary('/portals/imaging/new-scan.php', 'New study') . '</div>'
    );
} else {
    $currentRad = $scan['radiologist'] ?? ($scan['doctor'] ?? 'Dr. Imran Sheikh');

    // Scan picker dropdown for switching between scans
    $scanPickerOpts = '';
    foreach ($allScans as $sc) {
        $sel = ($sc['scan_no'] === $scan['scan_no']) ? ' selected' : '';
        $scanPickerOpts .= '<option value="' . e($sc['scan_no']) . '"' . $sel . '>'
            . e($sc['scan_no']) . ' — ' . e($sc['patient']) . ' (' . e($sc['study']) . ') [' . e(ucfirst($sc['status'])) . ']</option>';
    }

    $radSelectHtml = '<option value="">— Select Doctor —</option>';
    foreach ($doctorOpts as $docName => $docLabel) {
        $sel = (strcasecmp($docName, $currentRad) === 0) ? ' selected' : '';
        $radSelectHtml .= '<option value="' . e($docName) . '"' . $sel . '>' . e($docLabel) . '</option>';
    }

    $content .= <<<HTML
<div class="mb-5 no-print p-4 bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-3">
    <div class="flex-1 min-w-[16rem]">
        <label class="field-label text-xs">Switch Study / Patient</label>
        <select class="field text-sm font-semibold" onchange="if(this.value) location.href='/portals/imaging/results.php?scan_no='+encodeURIComponent(this.value)">
            {$scanPickerOpts}
        </select>
    </div>
    <div class="flex items-center gap-2">
        <a href="/portals/imaging/receipt.php?scan_no={$scan['scan_no']}" class="btn btn-secondary text-xs">
            <i class="fa-solid fa-receipt mr-1"></i> Print Slip
        </a>
        <a href="/portals/imaging/reports.php?scan_no={$scan['scan_no']}" class="btn btn-secondary text-xs">
            <i class="fa-solid fa-file-waveform mr-1"></i> View Report
        </a>
    </div>
</div>
HTML;

    $content .= card(
        '<form method="post" class="space-y-4 p-4 sm:p-6">' .
        '<input type="hidden" name="scan_no" value="' . e($scan['scan_no']) . '">' .
        '<div class="grid gap-4 sm:grid-cols-2">' .
        '<div><label class="field-label">Reporting Doctor / Radiologist</label><select name="rad" class="field text-sm">' . $radSelectHtml . '</select></div>' .
        form_field('Or Custom Doctor Name (Switch Doctor)', 'rad_custom', 'text', null, 'Type any doctor name to switch', true) .
        '</div>' .
        '<div><label class="field-label">Clinical Indication / History</label><p class="text-xs text-slate-600 bg-slate-50 p-2.5 rounded-lg border border-slate-200 m-0">' . e($scan['clinical_notes'] ?: 'None provided.') . '</p></div>' .
        '<div><label class="field-label">Radiological Findings</label><textarea name="findings" rows="7" class="field text-sm" placeholder="Describe findings (e.g. Bone alignment, soft tissues, lung fields, organ dimensions...)">' . e($scan['findings'] ?? '') . '</textarea></div>' .
        '<div><label class="field-label">Impression / Conclusion</label><textarea name="impression" rows="3" class="field text-sm font-semibold" placeholder="Diagnostic impression (e.g. Normal study, no focal lesion seen.)">' . e($scan['impression'] ?? '') . '</textarea></div>' .
        '<div class="pt-2 flex flex-wrap gap-2">' .
        btn_submit('Save findings & generate report') .
        '<a href="/portals/imaging/pending-scans.php" class="btn btn-secondary">Back to pending</a>' .
        '</div>' .
        '</form>'
    );
}

render_page('Results', 'imaging', 'results', $content);
