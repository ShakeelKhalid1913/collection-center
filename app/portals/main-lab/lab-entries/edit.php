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
            result_repo()->ensureResultsInitialized(
                $postLabNo,
                $newTests,
                $existingEntry['patient_name'],
                $existingEntry['organization_id'] ?? 'ORG-001'
            );

            $message = flash_success("Updated. Tests on this visit: {$newTests}");
            $entry = lab_repo()->findByLabNo($postLabNo);
        } else {
            $message = flash_error('Could not update tests.');
        }
    }
}

if (!$entry) {
    $content = page_header('Change Tests', 'No visit selected.');
    $content .= card('<p class="p-6 text-slate-600">Open a visit from <a href="/portals/main-lab/patients/history.php" class="text-teal-700 underline font-semibold">Patient History</a>.</p>');
    render_page('Change Tests', 'main-lab', 'history', $content);
    exit;
}

$patient = patient_repo()->findById((string)($entry['patient_id'] ?? '')) ?: [];
$mrNo = $patient['patient_no'] ?? ($entry['patient_id'] ?? '—');
$patientName = $entry['patient_name'] ?? '—';
$currentTests = $entry['tests'] ?? '';

$doctorOpts = ['' => '— Referring doctor —'];
foreach (mock('mock_doctors') as $d) {
    $doctorOpts[$d['id']] = $d['name'] . ' (' . $d['specialty'] . ')';
}

$currentDoctorId = '';
foreach (mock('mock_doctors') as $d) {
    if ($d['name'] === ($entry['doctor'] ?? '')) {
        $currentDoctorId = $d['id'];
        break;
    }
}

$historyUrl = '/portals/main-lab/patients/history.php?id=' . urlencode((string)$mrNo);
$labNoSafe = e((string)$entry['lab_no']);
$patientSafe = e((string)$patientName);
$mrSafe = e((string)$mrNo);
$testsSafe = e((string)$currentTests);

$content = page_header(
    'Change Tests',
    "Lab No {$entry['lab_no']} · {$patientName} · MR {$mrNo}"
);
$content .= $message;

$content .= '<form method="post" data-billing class="space-y-4">' .
    '<input type="hidden" name="lab_no" value="' . $labNoSafe . '">' .
    '<input type="hidden" name="route_id" value="RT-MAIN">';

$content .= card(
    '<div class="grid gap-3 p-4 sm:grid-cols-3 sm:p-5 text-sm">' .
    '<div><span class="block text-[10px] uppercase font-bold text-slate-400">Patient</span><strong>' . $patientSafe . '</strong></div>' .
    '<div><span class="block text-[10px] uppercase font-bold text-slate-400">MR No</span><strong class="font-mono">' . $mrSafe . '</strong></div>' .
    '<div><span class="block text-[10px] uppercase font-bold text-slate-400">Currently booked</span><span class="font-semibold text-teal-800">' . $testsSafe . '</span></div>' .
    '<div class="sm:col-span-2">' . select_field('Referring doctor', 'doctor_id', $doctorOpts, $currentDoctorId, true) . '</div>' .
    '<div>' . select_field('Priority', 'priority', ['Normal' => 'Normal', 'Urgent' => 'Urgent', 'STAT' => 'STAT'], $entry['priority'] ?? 'Normal') . '</div>' .
    '</div>'
);

$content .= '<div class="book-layout">';
$content .= card(
    panel_head('Tests') .
    '<div class="p-3 sm:p-4">' . catalog_picker('pathology', array_filter(array_map('trim', explode(',', (string)$currentTests)))) . '</div>',
    'overflow-hidden'
);

$content .= '<div class="book-layout__side space-y-3">' .
    card(
        panel_head('Discount & payment') .
        '<div class="p-4">' . billing_panel() . '</div>' .
        '<div class="px-4 pb-4">' .
        textarea_field('Notes (optional)', 'clinical', $entry['clinical_notes'] ?? '', 'Notes…', 3, true) .
        '</div>' .
        '<div class="sticky-actions border-t border-slate-100 flex flex-wrap gap-2">' .
        '<button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk mr-1"></i> Save tests</button>' .
        '<a href="' . e($historyUrl) . '" class="btn btn-secondary">Cancel</a>' .
        '</div>'
    ) .
    '</div>';

$content .= '</div></form>';

render_page('Change Tests', 'main-lab', 'history', $content);
