<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
$scanNo = $_GET['scan_no'] ?? $_POST['scan_no'] ?? '';
$scan = $scanNo !== '' ? result_repo()->findImagingScan($scanNo) : null;

if (!$scan) {
    $pending = array_values(array_filter(
        result_repo()->getImagingScans(),
        static fn($s) => in_array($s['status'] ?? '', ['pending', 'in_progress'], true)
    ));
    $scan = $pending[0] ?? (result_repo()->getImagingScans()[0] ?? null);
}

$doctorOpts = [];
foreach (mock('mock_doctors') as $d) {
    $doctorOpts[$d['id']] = $d['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['scan_no'])) {
    $ok = result_repo()->saveImagingFindings($_POST['scan_no'], [
        'radiologist' => resolve_doctor_name($_POST['rad'] ?? ''),
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
        '<p class="p-4 text-sm text-slate-600">No imaging studies. Create one from New Study first.</p>' .
        '<div class="p-4 pt-0">' . btn_primary('/portals/imaging/new-scan.php', 'New study') . '</div>'
    );
} else {
    $selectedRad = 'D-01';
    foreach (mock('mock_doctors') as $d) {
        if (($scan['radiologist'] ?? '') === $d['name']) {
            $selectedRad = $d['id'];
            break;
        }
    }
    $content .= card(
        '<form method="post" class="space-y-4 p-4 sm:p-6">' .
        '<input type="hidden" name="scan_no" value="' . e($scan['scan_no']) . '">' .
        select_field('Radiologist', 'rad', $doctorOpts, $selectedRad) .
        '<div><label class="field-label">Findings</label><textarea name="findings" rows="6" class="field">' . e($scan['findings'] ?? '') . '</textarea></div>' .
        '<div><label class="field-label">Impression</label><textarea name="impression" rows="3" class="field">' . e($scan['impression'] ?? '') . '</textarea></div>' .
        btn_submit('Save findings & generate report') .
        '</form>'
    );
}

render_page('Results', 'imaging', 'results', $content);
