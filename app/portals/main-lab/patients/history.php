<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$patientId = trim((string)($_GET['id'] ?? $_GET['patient_no'] ?? $_GET['patient_id'] ?? ''));

// Get all real patients for quick selection
$allPatients = patient_repo()->getAll($orgId);

$selectedPatient = null;
if ($patientId !== '') {
    $selectedPatient = patient_repo()->findById($patientId);
}

// Fallback to first patient if none specified
if (!$selectedPatient && !empty($allPatients[0])) {
    $selectedPatient = $allPatients[0];
    $patientId = $selectedPatient['patient_no'] ?? $selectedPatient['id'];
}

$patientOpts = '';
foreach ($allPatients as $p) {
    $pId = $p['patient_no'] ?? $p['id'];
    $sel = ($selectedPatient && ($selectedPatient['id'] === $p['id'] || ($selectedPatient['patient_no'] ?? '') === $pId)) ? ' selected' : '';
    $patientOpts .= '<option value="' . e($pId) . '"' . $sel . '>' . e($pId) . ' — ' . e($p['full_name'] ?? $p['name']) . ' (' . e($p['phone'] ?? '') . ')</option>';
}

$rows = [];
$patientName = $selectedPatient['full_name'] ?? $selectedPatient['name'] ?? '—';
$mrNo = $selectedPatient['patient_no'] ?? $selectedPatient['id'] ?? '—';
$phone = $selectedPatient['phone'] ?? '—';
$ageGender = trim(($selectedPatient['age'] ?? '—') . ' yrs / ' . ($selectedPatient['gender'] ?? '—'), ' /');

if ($selectedPatient) {
    // Fetch real lab entries for this patient
    $entries = lab_repo()->getByPatientId((string)$selectedPatient['id']);
    if (empty($entries) && !empty($selectedPatient['patient_no'])) {
        $entries = lab_repo()->getByPatientId((string)$selectedPatient['patient_no']);
    }

    foreach ($entries as $e) {
        $labQ = urlencode($e['lab_no']);
        $actions = '<div class="flex flex-wrap gap-2">' .
            '<a href="/portals/main-lab/reports/preview.php?lab_no=' . $labQ . '" class="btn btn-secondary text-xs"><i class="fa-solid fa-eye mr-1"></i> Report</a>' .
            '<a href="/portals/main-lab/results/entry.php?lab_no=' . $labQ . '" class="btn btn-primary text-xs"><i class="fa-solid fa-keyboard mr-1"></i> Edit Results</a>' .
            '<a href="/portals/collection-center/lab-entries/edit.php?lab_no=' . $labQ . '" class="btn btn-secondary text-xs"><i class="fa-solid fa-pen-to-square mr-1"></i> Edit Tests</a>' .
            '</div>';

        $rows[] = [
            '<strong class="text-teal-800 font-mono">' . e($e['lab_no']) . '</strong>',
            '<span class="font-semibold">' . e($e['tests']) . '</span>',
            e($e['doctor'] ?? 'Walk-in / Self'),
            status_badge($e['status']),
            e(format_money((float)$e['amount'])),
            e(format_date($e['created_at'] ?? date('Y-m-d'))),
            $actions,
        ];
    }
}

$content = page_header('Patient Test History', "{$patientName} · MR No: {$mrNo} · {$ageGender}");

// Patient Switcher
$content .= <<<HTML
<div class="mb-4 flex flex-wrap items-center justify-between gap-3 p-4 bg-white rounded-lg border border-slate-200 shadow-sm">
    <div class="flex items-center gap-2">
        <label class="text-sm font-semibold text-slate-700 whitespace-nowrap"><i class="fa-solid fa-user text-teal-600 mr-1"></i> Select Patient:</label>
        <select class="field text-sm max-w-md" onchange="if(this.value) window.location.href='/portals/main-lab/patients/history.php?id=' + encodeURIComponent(this.value);">
            {$patientOpts}
        </select>
    </div>
    <div class="flex items-center gap-2">
        <a href="/portals/collection-center/lab-entries/new.php?patient_id={$mrNo}" class="btn btn-primary text-xs">
            <i class="fa-solid fa-plus mr-1"></i> Book New Test for this Patient
        </a>
    </div>
</div>
HTML;

$docDisplay = e($selectedPatient['referring_doctor'] ?? $selectedPatient['doctor'] ?? $selectedPatient['emergency_name'] ?? '—');
$addrDisplay = e($selectedPatient['address'] ?? '—');

// Patient Summary Card
$content .= <<<HTML
<div class="mb-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6 p-4 bg-slate-50 border border-slate-200 rounded-lg text-sm">
    <div><span class="text-slate-500 block text-xs font-semibold uppercase">Patient Name</span><strong class="text-slate-900 text-base">{$patientName}</strong></div>
    <div><span class="text-slate-500 block text-xs font-semibold uppercase">MR Number</span><strong class="text-teal-800 font-mono text-base">{$mrNo}</strong></div>
    <div><span class="text-slate-500 block text-xs font-semibold uppercase">Phone</span><span class="text-slate-800 font-mono">{$phone}</span></div>
    <div><span class="text-slate-500 block text-xs font-semibold uppercase">Age / Gender</span><span class="text-slate-800">{$ageGender}</span></div>
    <div><span class="text-slate-500 block text-xs font-semibold uppercase">Doctor</span><span class="text-slate-800 font-medium">{$docDisplay}</span></div>
    <div><span class="text-slate-500 block text-xs font-semibold uppercase">Address</span><span class="text-slate-800 truncate block" title="{$addrDisplay}">{$addrDisplay}</span></div>
</div>
HTML;

if (empty($rows)) {
    $content .= card('<p class="p-6 text-slate-600 text-center">No lab test history found for this patient. Click "Book New Test for this Patient" above to assign tests.</p>');
} else {
    $content .= card(
        panel_head("Lab Entries for {$patientName} (" . count($rows) . ")") .
        data_table(['Lab No', 'Assigned Tests', 'Doctor', 'Status', 'Amount', 'Date', 'Actions'], $rows),
        'overflow-hidden'
    );
}

render_page('Patient History', 'main-lab', 'history', $content);
