<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
$labNo = trim((string)($_GET['lab_no'] ?? $_POST['lab_no'] ?? ''));
$entry = $labNo !== '' ? lab_repo()->findByLabNo($labNo) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['lab_no'])) {
    $postLabNo = trim($_POST['lab_no']);
    $existingEntry = lab_repo()->findByLabNo($postLabNo);

    if ($existingEntry) {
        $catalog = resolve_catalog_from_post($_POST);
        $doctor = resolve_doctor_name($_POST['doctor_id'] ?? '');

        $newTests = $catalog['tests'];
        $amount = $catalog['amount'] > 0 ? $catalog['amount'] : (float)($_POST['amount'] ?? $existingEntry['amount']);
        $paid = (float)($_POST['paid'] ?? $existingEntry['paid']);
        $discount = (float)($_POST['discount'] ?? $existingEntry['discount']);

        $ok = lab_repo()->updateEntry($postLabNo, [
            'tests' => $newTests,
            'doctor' => $doctor,
            'route' => resolve_route_label($_POST['route_id'] ?? ''),
            'priority' => $_POST['priority'] ?? 'Normal',
            'amount' => $amount,
            'paid' => $paid,
            'discount' => $discount,
            'clinical_notes' => $_POST['clinical'] ?? '',
        ]);

        if ($ok) {
            // Re-initialize results for any new tests added to this entry
            result_repo()->ensureResultsInitialized(
                $postLabNo,
                $newTests,
                $existingEntry['patient_name'],
                $existingEntry['organization_id'] ?? 'ORG-001'
            );

            $message = flash_success("Lab entry {$postLabNo} updated successfully! Tests assigned: {$newTests}");
            $entry = lab_repo()->findByLabNo($postLabNo);
        } else {
            $message = flash_error('Failed to update lab entry.');
        }
    }
}

if (!$entry) {
    $content = page_header('Edit Lab Entry', 'No entry specified.');
    $content .= card('<p class="p-6 text-slate-600">Please select an entry from <a href="/portals/collection-center/lab-entries/pending.php" class="text-teal-700 underline font-semibold">Pending Entries</a> or <a href="/portals/collection-center/lab-entries/history.php" class="text-teal-700 underline font-semibold">Entries History</a> to edit its assigned tests.</p>');
    render_page('Edit Lab Entry', 'collection-center', 'pending', $content);
    exit;
}

$patient = patient_repo()->findById((string)($entry['patient_id'] ?? '')) ?: [];
$mrNo = $patient['patient_no'] ?? ($entry['patient_id'] ?? '—');
$patientName = $entry['patient_name'] ?? '—';
$currentTests = $entry['tests'] ?? '';

$doctorOpts = ['' => '— Select referring doctor —'];
foreach (mock('mock_doctors') as $d) {
    $doctorOpts[$d['id']] = $d['name'] . ' (' . $d['specialty'] . ')';
}

$routeOpts = [];
foreach (mock('mock_routes') as $r) {
    $routeOpts[$r['id']] = $r['label'];
}

$currentDoctorId = '';
foreach (mock('mock_doctors') as $d) {
    if ($d['name'] === ($entry['doctor'] ?? '')) {
        $currentDoctorId = $d['id'];
        break;
    }
}

$currentRouteId = '';
foreach (mock('mock_routes') as $r) {
    if ($r['label'] === ($entry['route'] ?? '')) {
        $currentRouteId = $r['id'];
        break;
    }
}

$content = page_header(
    'Edit Assigned Tests &amp; Entry',
    "Lab No: {$entry['lab_no']} · Patient: {$patientName} (MR No: {$mrNo})"
);
$content .= $message;

$content .= card(
    '<form method="post" data-billing class="p-4 sm:p-6 space-y-6">' .
    '<input type="hidden" name="lab_no" value="' . e($entry['lab_no']) . '">' .
    
    // Patient Banner
    '<div class="grid gap-3 sm:grid-cols-3 p-4 bg-teal-50 border border-teal-200 rounded-lg text-sm">' .
    '<div><span class="text-teal-700 font-bold block text-xs uppercase">Patient Name</span><strong class="text-slate-900 text-base">' . e($patientName) . '</strong></div>' .
    '<div><span class="text-teal-700 font-bold block text-xs uppercase">MR Number</span><strong class="text-slate-900 font-mono text-base">' . e($mrNo) . '</strong></div>' .
    '<div><span class="text-teal-700 font-bold block text-xs uppercase">Currently Assigned Tests</span><span class="inline-block px-2 py-0.5 rounded bg-teal-200 text-teal-900 font-bold text-xs">' . e($currentTests) . '</span></div>' .
    '</div>' .

    // Doctor & Route
    '<div class="grid gap-4 sm:grid-cols-3 pt-2">' .
    select_field('Referring doctor', 'doctor_id', $doctorOpts, $currentDoctorId, true) .
    select_field('Route / Bench', 'route_id', $routeOpts, $currentRouteId, true) .
    select_field('Priority', 'priority', ['Normal' => 'Normal', 'Urgent' => 'Urgent', 'STAT' => 'STAT'], $entry['priority'] ?? 'Normal') .
    '</div>' .

    // Catalog Picker
    '<div class="border-t border-slate-200 pt-4">' .
    '<h3 class="font-bold text-slate-800 text-base mb-1">Select / Update Tests for this Patient</h3>' .
    '<p class="text-xs text-slate-500 mb-3">Check or uncheck tests below. Searching and filtering is supported. You can select single tests (e.g. CBC, FBS) or packages.</p>' .
    catalog_picker('pathology') .
    '</div>' .

    // Billing details
    '<div class="border-t border-slate-200 pt-4 grid gap-6 sm:grid-cols-2">' .
    '<div>' .
    textarea_field('Clinical notes (optional)', 'clinical', $entry['clinical_notes'] ?? '', 'Notes...', 3, true) .
    '</div>' .
    billing_panel() .
    '</div>' .

    // Actions
    '<div class="border-t border-slate-200 pt-4 flex flex-wrap gap-3 items-center justify-between">' .
    '<div class="flex gap-2">' .
    '<button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk mr-1"></i> Save Updated Tests</button>' .
    '<a href="/portals/collection-center/lab-entries/pending.php" class="btn btn-secondary">Cancel</a>' .
    '<div class="flex gap-2">' .
    '<a href="/portals/collection-center/receipts.php?lab_no=' . urlencode($entry['lab_no']) . '" class="btn btn-secondary text-xs"><i class="fa-solid fa-receipt mr-1"></i> Receipt</a>' .
    '<a href="/portals/collection-center/reports/preview.php?lab_no=' . urlencode($entry['lab_no']) . '" class="btn btn-secondary text-xs"><i class="fa-solid fa-file-medical mr-1"></i> Report</a>' .
    '</div>' .
    '</div>' .

    '</form>',
    'overflow-hidden'
);

render_page('Edit Lab Entry', 'collection-center', 'pending', $content);
