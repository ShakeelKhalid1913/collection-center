<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = trim($_POST['patient_id'] ?? $_POST['patient_scan'] ?? '');
    $patientName = 'Walk-in Patient';

    if ($patientId !== '') {
        $p = patient_repo()->findById($patientId);
        if ($p) {
            $patientName = $p['full_name'] ?? $p['name'] ?? $patientId;
            $patientId = $p['id'] ?? $patientId;
        }
    }

    $catalog = resolve_catalog_from_post($_POST);
    $isB2b = !empty($_POST['is_b2b']) ? 1 : 0;
    $b2bClientId = trim((string)($_POST['b2b_client_id'] ?? ''));
    $b2bClientName = trim((string)($_POST['b2b_client_name'] ?? ''));
    if ($isB2b && $b2bClientId !== '') {
        $client = b2b_repo()->findById($b2bClientId, current_user()['organization_id'] ?? 'ORG-001');
        if ($client) {
            $b2bClientName = $client['name'];
        }
    }

    $typedDoc = trim((string)($_POST['doctor'] ?? ''));
    $selectedDoc = resolve_doctor_name($_POST['doctor_id'] ?? '');
    $doctor = $typedDoc !== '' ? $typedDoc : $selectedDoc;
    if ($doctor === '') {
        $doctor = 'Walk-in / Self';
    }

    $typedRoute = trim((string)($_POST['route'] ?? ''));
    $selectedRoute = resolve_route_label($_POST['route_id'] ?? '');
    $route = $typedRoute !== '' ? $typedRoute : $selectedRoute;
    if ($route === '') {
        $route = 'Laboratory — Pathology (HQ)';
    }

    $res = lab_repo()->create([
        'patient_id' => $patientId ?: 'WALK-IN',
        'patient_name' => $patientName,
        'tests' => $catalog['tests'],
        'doctor' => $doctor,
        'route' => $route,
        'priority' => $_POST['priority'] ?? 'Normal',
        'status' => 'pending',
        'sample_status' => $_POST['sample_status'] ?? 'pending',
        'amount' => $catalog['amount'] > 0 ? $catalog['amount'] : $catalog['gross'],
        'paid' => $catalog['paid'],
        'discount' => $catalog['discount'],
        'is_b2b' => $isB2b,
        'b2b_client_id' => $b2bClientId ?: null,
        'b2b_client_name' => $b2bClientName ?: null,
        'clinical_notes' => $_POST['clinical'] ?? '',
        'created_at' => !empty($_POST['entry_time']) ? $_POST['entry_time'] : date('Y-m-d H:i:s'),
        'branch' => 'MAIN-LAB',
        'branch_id' => current_user()['branch_id'] ?? 'BR-MAIN',
        'organization_id' => current_user()['organization_id'] ?? 'ORG-001',
    ]);

    if ($res['success']) {
        header('Location: /portals/main-lab/results/entry.php?lab_no=' . urlencode($res['lab_no']));
        exit;
    }
    $message = flash_error('Could not save lab entry.');
}

$patientOpts = ['' => '— Select patient —'];
foreach (patient_repo()->getAll(current_user()['organization_id'] ?? 'ORG-001') as $p) {
    $pid = $p['patient_no'] ?? $p['id'];
    $patientOpts[$pid] = $pid . ' · ' . trim(($p['title'] ?? '') . ' ' . ($p['full_name'] ?? $p['name'] ?? '')) . ' · ' . ($p['phone'] ?? '');
}

$doctorOpts = ['' => '— Referring doctor —'];
foreach (mock('mock_doctors') as $d) {
    $doctorOpts[$d['id']] = $d['name'] . ' (' . $d['specialty'] . ')';
}

$routeOpts = [];
foreach (mock('mock_routes') as $r) {
    $routeOpts[$r['id']] = $r['label'];
}

$selectedPatientId = trim((string)($_GET['patient_id'] ?? ''));

$content = page_header(
    'New Lab Entry',
    'Patient → pick test type → choose tests → discount & save.',
    btn_primary('/portals/main-lab/patients/register.php', 'Register new patient')
);

$content .= $message;
$content .= '<form method="post" data-billing class="space-y-4">';

$b2bClients = b2b_repo()->getActiveClients(current_user()['organization_id'] ?? 'ORG-001');
$b2bClientOptions = '<option value="">— Select Partner Lab —</option>';
foreach ($b2bClients as $cl) {
    $b2bClientOptions .= '<option value="' . e($cl['id']) . '" data-discount="' . (float)$cl['discount_percent'] . '">'
        . e($cl['name']) . ' (' . (float)$cl['discount_percent'] . '% Wholesale Contract)</option>';
}

$b2bBlock = <<<HTML
<div class="sm:col-span-2 lg:col-span-3 p-3.5 bg-slate-50/90 rounded-xl border border-slate-200">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Pricing Mode / Order Type</span>
            <p class="text-xs text-slate-500">Regular walk-in patient or partner lab discounted wholesale contract.</p>
        </div>
        <div class="flex items-center gap-4 text-sm font-semibold">
            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                <input type="radio" name="is_b2b" value="0" checked class="text-teal-600 focus:ring-teal-500">
                <span>Walk-in Patient (Standard Rate)</span>
            </label>
            <label class="inline-flex items-center gap-1.5 cursor-pointer text-indigo-700">
                <input type="radio" name="is_b2b" value="1" class="text-indigo-600 focus:ring-indigo-500">
                <span><i class="fa-solid fa-handshake mr-1"></i> B2B Partner Lab (Wholesale Rate)</span>
            </label>
        </div>
    </div>
    <div data-b2b-client-box style="display:none;" class="mt-3 pt-3 border-t border-slate-200 grid gap-3 sm:grid-cols-2">
        <div>
            <label class="field-label text-xs font-bold text-indigo-900">Select Partner Referring Lab</label>
            <select name="b2b_client_id" data-b2b-client-select class="field text-sm font-semibold">
                {$b2bClientOptions}
            </select>
        </div>
        <div>
            <label class="field-label text-xs">Partner Lab Reference / Note (optional)</label>
            <input type="text" name="b2b_client_name" class="field text-sm" placeholder="e.g. Batch # or Specimen Barcode">
        </div>
    </div>
</div>
HTML;

$content .= card(
    '<div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3 sm:p-5">' .
    select_field('Patient', 'patient_id', $patientOpts, $selectedPatientId) .
    form_field('Or MR / Patient ID', 'patient_scan', 'text', $selectedPatientId, 'P-xxxxx', true) .
    doctor_input_field('Referring doctor', 'doctor', 'Walk-in / Self') .
    route_input_field('Route to', 'route', 'Laboratory — Pathology (HQ)') .
    form_field('Entry Date & Time', 'entry_time', 'datetime-local', date('Y-m-d\TH:i'), '', true) .
    select_field('Priority', 'priority', [
        'Normal' => 'Normal',
        'Urgent' => 'Urgent',
        'STAT' => 'STAT',
    ], 'Normal', true) .
    '<input type="hidden" name="sample_status" value="pending">' .
    $b2bBlock .
    '</div>'
);

$content .= '<div class="book-layout">';
$content .= card(
    panel_head('Tests') .
    '<div class="p-3 sm:p-4">' . catalog_picker('pathology') . '</div>',
    'overflow-hidden'
);

$content .= '<div class="book-layout__side space-y-3">' .
    card(
        panel_head('Discount & payment') .
        '<div class="p-4">' . billing_panel() . '</div>' .
        '<div class="px-4 pb-4">' .
        form_field('Notes (optional)', 'clinical', 'text', null, '', true) .
        '</div>' .
        '<div class="sticky-actions border-t border-slate-100">' .
        btn_submit('Save & enter results') .
        '</div>'
    ) .
    '</div>';

$content .= '</div></form>';

render_page('New Lab Entry', 'main-lab', 'new-entry', $content);
