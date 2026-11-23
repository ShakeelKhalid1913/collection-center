<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = trim((string)($_POST['patient_id'] ?? ''));
    if ($patientId === '') {
        $patientId = trim((string)($_POST['patient_scan'] ?? ''));
    }
    $patientName = '';

    if ($patientId !== '') {
        $p = patient_repo()->findById($patientId, current_user()['organization_id'] ?? 'ORG-001');
        if ($p) {
            $patientName = trim(($p['title'] ?? '') . ' ' . ($p['full_name'] ?? $p['name'] ?? ''));
            $patientId = (string)($p['id'] ?? $patientId);
        }
    }

    if ($patientName === '') {
        $patientName = trim((string)($_POST['patient_name'] ?? ''));
    }
    if ($patientName === '') {
        $patientName = 'Walk-in Patient';
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
    } else {
        record_doctor_name($doctor, current_user()['organization_id'] ?? 'ORG-001');
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

    $portal = current_portal();
    if ($res['success']) {
        $labNo = $res['lab_no'];
        $testCount = is_array($catalog['tests']) ? count($catalog['tests']) : (is_string($catalog['tests']) && $catalog['tests'] !== '' ? count(explode(',', $catalog['tests'])) : 1);
        audit_log('CREATE_ENTRY', 'lab_entries', $labNo, 'Booked ' . $testCount . ' tests for ' . $patientName);
        $action = $_POST['submit_action'] ?? 'enter_results';
        $qLab = urlencode((string)$labNo);
        if ($action === 'enter_results') {
            header('Location: /portals/results/entry.php?lab_no=' . $qLab . '&created=1');
            exit;
        } elseif ($action === 'barcode_label') {
            header('Location: /portals/barcode-label.php?lab_no=' . $qLab);
            exit;
        } else {
            header('Location: /portals/receipts.php?lab_no=' . $qLab . '&created=1');
            exit;
        }
    }
    $message = flash_error('Could not save lab entry.');
}

$portal = current_portal();
$activeKey = ($portal === 'imaging') ? 'new-scan' : 'new-entry';
$pageTitle = ($portal === 'imaging') ? 'New Booking (Unified Slip)' : 'Book Tests';

$patientOpts = ['' => '— Select patient —'];
$allPatients = patient_repo()->getAll(current_user()['organization_id'] ?? 'ORG-001');
$patientMetaMap = [];

$selectedPatientId = trim((string)($_GET['patient_id'] ?? $_GET['mr'] ?? ''));
$selectedPatient = null;
if ($selectedPatientId !== '') {
    $selectedPatient = patient_repo()->findById($selectedPatientId, current_user()['organization_id'] ?? 'ORG-001');
    if ($selectedPatient) {
        $selectedPatientId = (string)$selectedPatient['id'];
    }
}

foreach ($allPatients as $p) {
    $pid = (string)$p['id'];
    $mr = (string)($p['patient_no'] ?? $p['id']);
    $pFull = trim(($p['title'] ?? '') . ' ' . ($p['full_name'] ?? $p['name'] ?? ''));
    $phone = (string)($p['phone'] ?? '');
    $patientOpts[$pid] = $mr . ' · ' . $pFull . ($phone !== '' ? ' · ' . $phone : '');
    $patientMetaMap[$pid] = [
        'mr' => $mr,
        'name' => $pFull,
        'doctor' => (string)($p['referring_doctor'] ?? $p['emergency_name'] ?? ''),
    ];
}

if ($selectedPatient && !isset($patientOpts[(string)$selectedPatient['id']])) {
    $spId = (string)$selectedPatient['id'];
    $spMr = (string)($selectedPatient['patient_no'] ?? $spId);
    $spFull = trim(($selectedPatient['title'] ?? '') . ' ' . ($selectedPatient['full_name'] ?? $selectedPatient['name'] ?? ''));
    $spPhone = (string)($selectedPatient['phone'] ?? '');
    $patientOpts[$spId] = $spMr . ' · ' . $spFull . ($spPhone !== '' ? ' · ' . $spPhone : '');
    $patientMetaMap[$spId] = [
        'mr' => $spMr,
        'name' => $spFull,
        'doctor' => (string)($selectedPatient['referring_doctor'] ?? $selectedPatient['emergency_name'] ?? ''),
    ];
}

$doctorOpts = ['' => '— Referring doctor —'];
foreach (mock('mock_doctors') as $d) {
    $doctorOpts[$d['id']] = $d['name'] . ' (' . $d['specialty'] . ')';
}

$routeOpts = [];
foreach (mock('mock_routes') as $r) {
    $routeOpts[$r['id']] = $r['label'];
}

if (isset($_GET['from_register'])) {
    $message = flash_success('Patient registered successfully! Select tests below to book.');
}

$registerUrl = ($portal === 'imaging') ? '/portals/patients.php' : '/portals/patients/register.php';

$content = workflow_stepper('booking', null, $selectedPatientId);
$content .= page_header(
    $pageTitle,
    '',
    btn_primary($registerUrl, '+ Register Patient')
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
<div class="sm:col-span-2 lg:col-span-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <span class="text-xs font-bold text-slate-700 uppercase tracking-wide">Pricing Mode</span>
        <div class="flex items-center gap-4 text-xs font-semibold">
            <label class="inline-flex items-center gap-1.5 cursor-pointer text-slate-800">
                <input type="radio" name="is_b2b" value="0" checked class="text-slate-900 focus:ring-slate-900">
                <span>Standard Walk-in Rate</span>
            </label>
            <label class="inline-flex items-center gap-1.5 cursor-pointer text-indigo-700">
                <input type="radio" name="is_b2b" value="1" class="text-indigo-600 focus:ring-indigo-500">
                <span><i class="fa-solid fa-handshake mr-1"></i> B2B Partner Rate</span>
            </label>
        </div>
    </div>
    <div data-b2b-client-box style="display:none;" class="mt-3 pt-3 border-t border-slate-200 grid gap-3 sm:grid-cols-2">
        <div>
            <label class="field-label text-xs font-bold text-indigo-900">Partner Lab</label>
            <select name="b2b_client_id" data-b2b-client-select class="field text-sm font-semibold">
                {$b2bClientOptions}
            </select>
        </div>
        <div>
            <label class="field-label text-xs">Reference Note</label>
            <input type="text" name="b2b_client_name" class="field text-sm" placeholder="e.g. Batch # or Specimen Barcode">
        </div>
    </div>
</div>
HTML;

$nextLabMr = patient_repo()->getNextMrNumber(current_user()['organization_id'] ?? 'ORG-001');
$prefilledMr = $nextLabMr;
$prefilledName = '';
$prefilledDoctor = '';
if ($selectedPatient) {
    $prefilledMr = (string)($selectedPatient['patient_no'] ?? $nextLabMr);
    $prefilledName = trim(($selectedPatient['title'] ?? '') . ' ' . ($selectedPatient['full_name'] ?? $selectedPatient['name'] ?? ''));
    $prefilledDoctor = (string)($selectedPatient['referring_doctor'] ?? $selectedPatient['emergency_name'] ?? '');
}

$mrModeField = <<<HTML
<div class="sm:col-span-1">
    <div class="flex items-center justify-between mb-1">
        <label class="field-label mb-0" for="patient_scan">MR Number</label>
        <div class="flex items-center gap-2 text-xs">
            <label class="inline-flex items-center gap-1 cursor-pointer font-bold text-slate-700 px-2 py-0.5 rounded-md hover:bg-slate-100">
                <input type="radio" name="mr_mode" value="auto" checked onchange="toggleLabMrMode('auto')" class="text-slate-900 focus:ring-slate-900">
                <span>Auto</span>
            </label>
            <label class="inline-flex items-center gap-1 cursor-pointer font-bold text-slate-600 px-2 py-0.5 rounded-md hover:bg-slate-100">
                <input type="radio" name="mr_mode" value="manual" onchange="toggleLabMrMode('manual')" class="text-slate-900 focus:ring-slate-900">
                <span>Manual</span>
            </label>
        </div>
    </div>
    <input type="text" id="patient_scan" name="patient_scan" value="{$prefilledMr}" class="field text-sm font-mono bg-slate-50 border-slate-300 text-slate-900 font-bold cursor-not-allowed" placeholder="Auto-generated" readonly>
</div>
HTML;

$patientNameHidden = '<input type="hidden" id="selected_patient_name" name="patient_name" value="' . e($prefilledName) . '">';

$content .= card(
    '<div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3 sm:p-5">' .
    select_field('Patient', 'patient_id', $patientOpts, $selectedPatientId) .
    $patientNameHidden .
    $mrModeField .
    doctor_input_field('Referring doctor', 'doctor', $prefilledDoctor !== '' ? $prefilledDoctor : 'Walk-in / Self') .
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
        '<div class="sticky-actions border-t border-slate-100 p-4 space-y-2">' .
        '<button type="submit" name="submit_action" value="enter_results" class="w-full flex items-center justify-center gap-2 px-5 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm shadow-md transition-all">' .
        '<i class="fa-solid fa-square-poll-horizontal"></i> Save &amp; Enter Results &rarr;' .
        '</button>' .
        '<div class="flex items-center gap-2">' .
        '<button type="submit" name="submit_action" value="print_bill" class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-all shadow-xs">' .
        '<i class="fa-solid fa-receipt text-[#c2f13c]"></i> Save &amp; Bill' .
        '</button>' .
        '<button type="submit" name="submit_action" value="barcode_label" class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs border border-slate-300 transition-all shadow-xs">' .
        '<i class="fa-solid fa-barcode text-slate-500"></i> Barcode' .
        '</button>' .
        '</div>' .
        '</div>'
    ) .
    '</div>';

$preTestsRaw = trim((string)($_GET['pre_tests'] ?? ''));
$preTestsArray = $preTestsRaw !== '' ? array_filter(array_map('trim', explode(',', $preTestsRaw))) : [];
$preTestsJson = json_encode(array_values($preTestsArray));
$preDiscount = (float)($_GET['pre_discount'] ?? 0);

if (!empty($preTestsArray) || $preDiscount > 0) {
    $content .= <<<SCRIPT
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const preTests = {$preTestsJson};
        const preDiscount = {$preDiscount};
        if (preTests && preTests.length > 0) {
            preTests.forEach(function(tName) {
                const trimmed = tName.trim().toLowerCase();
                const inputs = document.querySelectorAll('input[type="checkbox"][name*="tests"]');
                inputs.forEach(function(inp) {
                    if (inp.value.trim().toLowerCase() === trimmed) {
                        inp.checked = true;
                        inp.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            });
        }
        if (preDiscount > 0) {
            const discInput = document.querySelector('input[name*="discount"]');
            if (discInput) {
                discInput.value = preDiscount;
                discInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
    });
    </script>
    SCRIPT;
}

$patientMetaJson = json_encode($patientMetaMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

$content .= <<<SCRIPT
<script>
const defaultLabMr = '{$nextLabMr}';
const patientMeta = {$patientMetaJson};
function toggleLabMrMode(mode) {
    const mrInput = document.getElementById('patient_scan');
    const mrHint = document.getElementById('lab-mr-hint');
    if (!mrInput) return;
    if (mode === 'manual') {
        mrInput.readOnly = false;
        mrInput.classList.remove('bg-emerald-50/70', 'border-emerald-300', 'text-emerald-950', 'cursor-not-allowed');
        mrInput.classList.add('bg-white', 'text-slate-900', 'border-teal-500');
        mrInput.placeholder = 'e.g. MR-10492';
        mrInput.focus();
        if (mrHint) mrHint.innerHTML = '<span class="text-blue-600 font-semibold"><i class="fa-solid fa-pen-to-square mr-1"></i> Manual Mode: enter custom MR</span>';
    } else {
        mrInput.readOnly = true;
        mrInput.value = defaultLabMr;
        mrInput.classList.remove('bg-white', 'text-slate-900', 'border-teal-500');
        mrInput.classList.add('bg-emerald-50/70', 'border-emerald-300', 'text-emerald-950', 'cursor-not-allowed');
        if (mrHint) mrHint.innerHTML = '<i class="fa-solid fa-circle-check mr-1 text-emerald-600"></i> Auto-incremented sequential MR: <strong>' + defaultLabMr + '</strong>';
    }
}
document.addEventListener('DOMContentLoaded', function() {
    const pSel = document.querySelector('select[name="patient_id"]');
    const mrInput = document.getElementById('patient_scan');
    const nameInput = document.getElementById('selected_patient_name');
    const docInput = document.getElementById('doctor');
    if (pSel) {
        pSel.addEventListener('change', function() {
            const pid = this.value;
            if (pid && patientMeta[pid]) {
                if (mrInput) mrInput.value = patientMeta[pid].mr;
                if (nameInput) nameInput.value = patientMeta[pid].name;
                if (docInput && (!docInput.value || docInput.value.startsWith('Walk-in')) && patientMeta[pid].doctor) {
                    docInput.value = patientMeta[pid].doctor;
                }
            } else {
                if (mrInput) mrInput.value = defaultLabMr;
                if (nameInput) nameInput.value = '';
            }
        });
    }
});
</script>
SCRIPT;

$content .= '</div></form>';

render_page($pageTitle, $portal, $activeKey, $content);
