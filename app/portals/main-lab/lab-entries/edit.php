<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$message = '';
$labNo = trim((string)($_GET['lab_no'] ?? $_POST['lab_no'] ?? ''));
$searchQuery = trim((string)($_GET['q'] ?? ''));

$entry = $labNo !== '' ? lab_repo()->findByLabNo($labNo) : null;

// Handle saving entry edits (demographics, doctor, tests, bill)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['lab_no'])) {
    $postLabNo = trim($_POST['lab_no']);
    $existingEntry = lab_repo()->findByLabNo($postLabNo);

    if ($existingEntry) {
        $patientName = trim((string)($_POST['patient_name'] ?? $existingEntry['patient_name'] ?? ''));
        $patientAge = trim((string)($_POST['age'] ?? ''));
        $patientGender = trim((string)($_POST['gender'] ?? ''));
        $patientPhone = trim((string)($_POST['phone'] ?? ''));

        $typedDoc = trim((string)($_POST['doctor'] ?? ''));
        $selectedDoc = resolve_doctor_name($_POST['doctor_id'] ?? '');
        $doctor = $typedDoc !== '' ? $typedDoc : $selectedDoc;
        if ($doctor === '') {
            $doctor = $existingEntry['doctor'] ?? 'Walk-in / Self';
        }

        $catalog = resolve_catalog_from_post($_POST);
        $newTests = !empty($catalog['tests']) ? $catalog['tests'] : ($existingEntry['tests'] ?? '');
        $amount = $catalog['amount'] > 0 ? $catalog['amount'] : (float)($_POST['amount'] ?? $existingEntry['amount']);
        $paid = isset($_POST['paid']) ? (float)$_POST['paid'] : (float)$existingEntry['paid'];
        $discount = isset($_POST['discount']) ? (float)$_POST['discount'] : (float)$existingEntry['discount'];
        $priority = trim((string)($_POST['priority'] ?? $existingEntry['priority'] ?? 'Normal'));
        $notes = trim((string)($_POST['clinical'] ?? $existingEntry['clinical_notes'] ?? ''));

        // 1. Update patient demographics in patients table
        $patientId = (string)($existingEntry['patient_id'] ?? '');
        if ($patientId !== '') {
            $p = patient_repo()->findById($patientId, $orgId);
            if ($p && !empty($p['id'])) {
                patient_repo()->update((string)$p['id'], [
                    'full_name' => $patientName,
                    'age' => $patientAge,
                    'gender' => $patientGender,
                    'phone' => $patientPhone,
                    'referring_doctor' => $doctor,
                ], $orgId);
            }
        }

        // 2. Update lab_entries record
        $ok = lab_repo()->updateEntry($postLabNo, [
            'patient_name' => $patientName,
            'tests' => $newTests,
            'doctor' => $doctor,
            'priority' => $priority,
            'amount' => $amount,
            'paid' => $paid,
            'discount' => $discount,
            'clinical_notes' => $notes,
        ]);

        if ($ok) {
            // 3. Update patient name on results table & sync catalog tests
            try {
                $db = db();
                $db->execute("UPDATE results SET patient = :p WHERE lab_no = :lab_no", [
                    'p' => $patientName,
                    'lab_no' => $postLabNo,
                ]);
            } catch (\Throwable $e) {
                // ignore
            }

            result_repo()->ensureResultsInitialized(
                $postLabNo,
                $newTests,
                $patientName,
                $existingEntry['organization_id'] ?? $orgId
            );

            audit_log('UPDATE_ENTRY', 'lab_entries', $postLabNo, "Edited demographics & billing slip for {$patientName}");

            $submitAction = trim((string)($_POST['submit_action'] ?? 'save'));
            if ($submitAction === 'print_slip') {
                header('Location: /portals/main-lab/receipts.php?lab_no=' . urlencode($postLabNo));
                exit;
            }
            if ($submitAction === 'edit_report') {
                header('Location: /portals/main-lab/results/entry.php?lab_no=' . urlencode($postLabNo));
                exit;
            }

            $slipUrl = '/portals/main-lab/receipts.php?lab_no=' . urlencode($postLabNo);
            $reportUrl = '/portals/main-lab/results/entry.php?lab_no=' . urlencode($postLabNo);
            $previewUrl = '/portals/main-lab/reports/preview.php?lab_no=' . urlencode($postLabNo);

            $message = '<div class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex flex-wrap items-center justify-between gap-3 shadow-sm">'
                . '<div class="flex items-center gap-2">'
                . '<i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>'
                . '<span><strong>Changes Saved!</strong> Patient demographics, bill, and test entry have been updated for ' . e($patientName) . '.</span>'
                . '</div>'
                . '<div class="flex flex-wrap items-center gap-2">'
                . '<a href="' . e($slipUrl) . '" target="_blank" class="btn btn-secondary text-xs font-bold bg-white text-emerald-800 border-emerald-300"><i class="fa-solid fa-receipt mr-1"></i> Print Updated Slip</a>'
                . '<a href="' . e($reportUrl) . '" class="btn btn-secondary text-xs font-bold bg-white text-amber-800 border-amber-300"><i class="fa-solid fa-file-pen mr-1"></i> Edit Report</a>'
                . '<a href="' . e($previewUrl) . '" class="btn btn-secondary text-xs font-bold bg-white text-slate-800"><i class="fa-solid fa-eye mr-1"></i> View Preview</a>'
                . '</div>'
                . '</div>';

            $entry = lab_repo()->findByLabNo($postLabNo);
        } else {
            $message = flash_error('Could not update entry.');
        }
    }
}

// If no entry is chosen yet, display lookup search & list of recent entries
if (!$entry) {
    $recentVisits = lab_repo()->getEntriesWithPatients($orgId, 50);
    if ($searchQuery !== '') {
        $qLower = strtolower($searchQuery);
        $recentVisits = array_filter($recentVisits, static function($row) use ($qLower) {
            return str_contains(strtolower((string)($row['lab_no'] ?? '')), $qLower)
                || str_contains(strtolower((string)($row['patient_name'] ?? '')), $qLower)
                || str_contains(strtolower((string)($row['mr_no'] ?? '')), $qLower)
                || str_contains(strtolower((string)($row['phone'] ?? '')), $qLower);
        });
    }

    $rowsHtml = '';
    foreach ($recentVisits as $v) {
        $vLab = e((string)$v['lab_no']);
        $vName = e((string)($v['patient_name'] ?? $v['full_name'] ?? '—'));
        $vMr = e((string)($v['mr_no'] ?? '—'));
        $vAge = e((string)($v['age'] ?? '—'));
        $vGender = e((string)($v['gender'] ?? '—'));
        $vTests = e(normalize_tests_list((string)($v['tests'] ?? '')));
        $vAmount = number_format((float)($v['amount'] ?? 0));
        $vPaid = number_format((float)($v['paid'] ?? 0));
        $vDate = format_date($v['created_at'] ?? null);

        $editUrl = '/portals/main-lab/lab-entries/edit.php?lab_no=' . urlencode((string)$v['lab_no']);
        $slipUrl = '/portals/main-lab/receipts.php?lab_no=' . urlencode((string)$v['lab_no']);
        $reportUrl = '/portals/main-lab/results/entry.php?lab_no=' . urlencode((string)$v['lab_no']);

        $rowsHtml .= <<<HTML
        <tr class="hover:bg-slate-50 transition-colors border-b border-slate-100">
            <td class="px-4 py-3 font-mono font-bold text-teal-800">{$vLab}</td>
            <td class="px-4 py-3 font-semibold text-slate-900">
                {$vName}
                <div class="text-xs font-normal text-slate-500 font-mono">MR: {$vMr} &middot; {$vAge} / {$vGender}</div>
            </td>
            <td class="px-4 py-3 text-xs text-slate-700 max-w-xs truncate" title="{$vTests}">{$vTests}</td>
            <td class="px-4 py-3 text-right text-xs">
                <span class="font-bold text-slate-900">Rs. {$vAmount}</span>
                <div class="text-[11px] text-emerald-700 font-medium">Paid: Rs. {$vPaid}</div>
            </td>
            <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">{$vDate}</td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
                <div class="inline-flex items-center gap-1.5">
                    <a href="{$editUrl}" class="btn btn-primary text-xs py-1 px-2.5 font-bold shadow-2xs" title="Edit Patient Info, Age & Bill"><i class="fa-solid fa-user-pen mr-1"></i> Edit Entry</a>
                    <a href="{$slipUrl}" target="_blank" class="btn btn-secondary text-xs py-1 px-2 font-medium" title="Print Bill Slip"><i class="fa-solid fa-receipt text-slate-600"></i></a>
                    <a href="{$reportUrl}" class="btn btn-secondary text-xs py-1 px-2 font-medium" title="Edit Test Results"><i class="fa-solid fa-file-pen text-amber-600"></i></a>
                </div>
            </td>
        </tr>
        HTML;
    }

    if ($rowsHtml === '') {
        $rowsHtml = '<tr><td colspan="6" class="p-8 text-center text-slate-500">No visits matched your search.</td></tr>';
    }

    $content = page_header(
        'Edit Entry & Bill',
        'Find any patient visit to correct age, name, doctor, tests, or billing slip details.',
        '<a href="/portals/main-lab/lab-entries/new.php" class="btn btn-primary"><i class="fa-solid fa-plus mr-1"></i> New Entry</a>'
    );

    $content .= <<<HTML
    <div class="space-y-4">
        <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
            <form method="get" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[260px] relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="q" value="{$searchQuery}" placeholder="Search Lab No / Token, MR No, or Patient Name..." class="field pl-9 w-full text-sm">
                </div>
                <button type="submit" class="btn btn-secondary font-semibold"><i class="fa-solid fa-filter mr-1"></i> Find Visit</button>
                <a href="/portals/main-lab/lab-entries/edit.php" class="btn btn-secondary font-medium">Clear</a>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200/80 flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Recent Visits</span>
                <span class="text-xs text-slate-500">Select any visit to edit patient demographics &amp; reprint bill</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-100/75 text-[11px] uppercase tracking-wider text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-2.5">Lab No</th>
                            <th class="px-4 py-2.5">Patient Details</th>
                            <th class="px-4 py-2.5">Tests Booked</th>
                            <th class="px-4 py-2.5 text-right">Bill</th>
                            <th class="px-4 py-2.5">Date</th>
                            <th class="px-4 py-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>{$rowsHtml}</tbody>
                </table>
            </div>
        </div>
    </div>
    HTML;

    render_page('Edit Entry & Bill', 'main-lab', 'edit-entry', $content);
    exit;
}

// -------------------------------------------------------------
// Form to Edit Selected Entry
// -------------------------------------------------------------
$patient = patient_repo()->findById((string)($entry['patient_id'] ?? ''), $orgId) ?: [];
$mrNo = $patient['patient_no'] ?? ($entry['patient_id'] ?? '—');
$patientName = (string)($entry['patient_name'] ?? ($patient['full_name'] ?? ''));
$patientAge = (string)($patient['age'] ?? '');
$patientGender = (string)($patient['gender'] ?? 'Male');
$patientPhone = (string)($patient['phone'] ?? '');
$currentTests = (string)($entry['tests'] ?? '');

$doctorOpts = ['' => '— Select Referring Doctor —'];
foreach (mock('mock_doctors') as $d) {
    $doctorOpts[$d['id']] = $d['name'] . ' (' . $d['specialty'] . ')';
}

$currentDoctorId = '';
foreach (mock('mock_doctors') as $d) {
    if (strcasecmp($d['name'], (string)($entry['doctor'] ?? '')) === 0) {
        $currentDoctorId = $d['id'];
        break;
    }
}

$labNoSafe = e((string)$entry['lab_no']);
$patientSafe = e($patientName);
$ageSafe = e($patientAge);
$phoneSafe = e($patientPhone);
$mrSafe = e((string)$mrNo);
$doctorSafe = e((string)($entry['doctor'] ?? ''));

$slipUrl = '/portals/main-lab/receipts.php?lab_no=' . urlencode((string)$entry['lab_no']);
$reportUrl = '/portals/main-lab/results/entry.php?lab_no=' . urlencode((string)$entry['lab_no']);
$previewUrl = '/portals/main-lab/reports/preview.php?lab_no=' . urlencode((string)$entry['lab_no']);

$content = page_header(
    'Edit Entry & Bill Slip',
    "Lab No {$entry['lab_no']} · {$patientName} · MR {$mrNo}",
    '<div class="flex flex-wrap items-center gap-2">'
    . '<a href="/portals/main-lab/lab-entries/edit.php" class="btn btn-secondary text-xs font-semibold"><i class="fa-solid fa-arrow-left mr-1"></i> Change Patient</a>'
    . '<a href="' . e($slipUrl) . '" target="_blank" class="btn btn-secondary text-xs font-semibold"><i class="fa-solid fa-receipt mr-1"></i> Patient Slip</a>'
    . '<a href="' . e($reportUrl) . '" class="btn btn-secondary text-xs font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 border-amber-300"><i class="fa-solid fa-file-pen mr-1 text-amber-600"></i> Edit Report</a>'
    . '<a href="' . e($previewUrl) . '" class="btn btn-secondary text-xs font-semibold"><i class="fa-solid fa-eye mr-1"></i> Preview Report</a>'
    . '</div>'
);

$content .= $message;

$content .= '<form method="post" data-billing class="space-y-4">' .
    '<input type="hidden" name="lab_no" value="' . $labNoSafe . '">';

$docSelectOpts = '<option value="">— Select from list or type below —</option>';
foreach (mock('mock_doctors') as $d) {
    $sel = (strcasecmp($d['name'], $doctorSafe) === 0 || $d['id'] === $currentDoctorId) ? ' selected' : '';
    $docSelectOpts .= '<option value="' . e($d['id']) . '"' . $sel . '>' . e($d['name']) . ' (' . e($d['specialty']) . ')</option>';
}

// Demographics Card
$content .= card(
    panel_head('Patient Demographics & Visit Details', 'Correct age, name, phone, or referring doctor. These update both the bill slip and the diagnostic report.') .
    '<div class="p-4 sm:p-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">' .
    '<div>' .
    '<label class="field-label text-xs font-bold text-slate-800">Patient Full Name <span class="text-rose-500">*</span></label>' .
    '<input type="text" name="patient_name" value="' . $patientSafe . '" required class="field w-full font-semibold">' .
    '</div>' .
    '<div>' .
    '<label class="field-label text-xs font-bold text-slate-800">Age <span class="text-rose-500">*</span></label>' .
    '<input type="text" name="age" value="' . $ageSafe . '" placeholder="e.g. 25, 45 Y" class="field w-full font-semibold">' .
    '<span class="text-[10px] text-slate-400">Printed on receipt slip &amp; report</span>' .
    '</div>' .
    '<div>' .
    '<label class="field-label text-xs font-bold text-slate-800">Gender</label>' .
    '<select name="gender" class="field w-full font-semibold">' .
    '<option value="Male"' . (strcasecmp($patientGender, 'Male') === 0 ? ' selected' : '') . '>Male</option>' .
    '<option value="Female"' . (strcasecmp($patientGender, 'Female') === 0 ? ' selected' : '') . '>Female</option>' .
    '<option value="Other"' . (strcasecmp($patientGender, 'Other') === 0 ? ' selected' : '') . '>Other</option>' .
    '</select>' .
    '</div>' .
    '<div>' .
    '<label class="field-label text-xs font-bold text-slate-800">Phone Number</label>' .
    '<input type="text" name="phone" value="' . $phoneSafe . '" placeholder="0300-1234567" class="field w-full font-mono">' .
    '</div>' .
    '<div class="sm:col-span-2">' .
    '<label class="field-label text-xs font-bold text-slate-800">Referring Doctor</label>' .
    '<select name="doctor_id" class="field w-full">' .
    $docSelectOpts .
    '</select>' .
    '<div class="mt-1.5"><input type="text" name="doctor" value="' . $doctorSafe . '" placeholder="Or type doctor name..." class="field w-full text-xs"></div>' .
    '</div>' .
    '<div>' .
    '<label class="field-label text-xs font-bold text-slate-800">Priority</label>' .
    '<select name="priority" class="field w-full font-semibold">' .
    '<option value="Normal"' . (($entry['priority'] ?? 'Normal') === 'Normal' ? ' selected' : '') . '>Normal</option>' .
    '<option value="Urgent"' . (($entry['priority'] ?? '') === 'Urgent' ? ' selected' : '') . '>Urgent</option>' .
    '<option value="STAT"' . (($entry['priority'] ?? '') === 'STAT' ? ' selected' : '') . '>STAT (Immediate)</option>' .
    '</select>' .
    '</div>' .
    '<div>' .
    '<label class="field-label text-xs font-bold text-slate-400">MR Number (Permanent)</label>' .
    '<input type="text" readonly disabled value="' . $mrSafe . '" class="field w-full bg-slate-100 text-slate-500 font-mono">' .
    '</div>' .
    '</div>'
);

$content .= '<div class="book-layout">';
$content .= card(
    panel_head('Tests on this Visit', 'Tick or untick to add or remove tests on this patient visit.') .
    '<div class="p-3 sm:p-4">' . catalog_picker('pathology', array_filter(array_map('trim', explode(',', (string)$currentTests)))) . '</div>',
    'overflow-hidden'
);

$content .= '<div class="book-layout__side space-y-3">' .
    card(
        panel_head('Discount & Billing Slip') .
        '<div class="p-4">' . billing_panel((float)($entry['discount'] ?? 0), (float)($entry['paid'] ?? 0)) . '</div>' .
        '<div class="px-4 pb-4">' .
        textarea_field('Visit Notes / Remarks', 'clinical', $entry['clinical_notes'] ?? '', 'Notes printed on administrative slip…', 3, true) .
        '</div>' .
        '<div class="sticky-actions border-t border-slate-100 flex flex-col gap-2 p-3 bg-slate-50">' .
        '<button type="submit" name="submit_action" value="save" class="btn btn-primary w-full justify-center"><i class="fa-solid fa-floppy-disk mr-1"></i> Save Changes</button>' .
        '<button type="submit" name="submit_action" value="print_slip" class="btn btn-secondary w-full justify-center text-teal-800 bg-white border-teal-300 hover:bg-teal-50"><i class="fa-solid fa-receipt mr-1 text-teal-600"></i> Save &amp; Print Slip</button>' .
        '<button type="submit" name="submit_action" value="edit_report" class="btn btn-secondary w-full justify-center text-amber-800 bg-white border-amber-300 hover:bg-amber-50"><i class="fa-solid fa-file-pen mr-1 text-amber-600"></i> Save &amp; Edit Report</button>' .
        '</div>'
    ) .
    '</div>';

$content .= '</div></form>';

render_page('Edit Entry & Bill', 'main-lab', 'edit-entry', $content);
