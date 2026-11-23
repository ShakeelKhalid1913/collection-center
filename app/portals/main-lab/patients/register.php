<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $message = flash_error('Full name is required.');
    } else {
        $regDoctor = trim($_POST['doctor'] ?? '');
        if ($regDoctor !== '') {
            record_doctor_name($regDoctor, current_user()['organization_id'] ?? 'ORG-001');
        }
        $res = patient_repo()->create([
            'patient_no' => trim($_POST['patient_no'] ?? ''),
            'title' => trim((string)($_POST['title'] ?? '')),
            'full_name' => $name,
            'phone' => $_POST['phone'] ?? '',
            'age' => (int)($_POST['age'] ?? 0),
            'gender' => $_POST['gender'] ?? 'Female',
            'blood_group' => trim((string)($_POST['blood_group'] ?? '')),
            'relation_of' => trim($_POST['relation_of'] ?? ''),
            'doctor' => $regDoctor,
            'referring_doctor' => $regDoctor,
            'address' => trim($_POST['address'] ?? ''),
            'branch' => 'LAB-01',
            'created_by' => current_user()['id'] ?? null,
            'created_at' => !empty($_POST['entry_time']) ? $_POST['entry_time'] : date('Y-m-d H:i:s'),
            'organization_id' => current_user()['organization_id'] ?? 'ORG-001',
        ]);
        if ($res['success']) {
            $pid = (string)($res['id'] ?? '');
            $pNo = (string)($res['patient_no'] ?? '');
            $action = $_POST['submit_action'] ?? 'book_tests';
            if ($action === 'book_tests') {
                header('Location: /portals/lab-entries/new.php?patient_id=' . urlencode($pid) . '&mr=' . urlencode($pNo) . '&from_register=1');
                exit;
            } elseif ($action === 'register_next') {
                header('Location: /portals/patients/register.php?registered=1&last_mr=' . urlencode($pNo ?: $pid));
                exit;
            } else {
                header('Location: /portals/patients/records.php?registered=1');
                exit;
            }
        }
        $message = flash_error('Could not save patient.');
    }
}

$blood = [
    '' => '— Optional —',
    'A+' => 'A+', 'A-' => 'A-',
    'B+' => 'B+', 'B-' => 'B-',
    'AB+' => 'AB+', 'AB-' => 'AB-',
    'O+' => 'O+', 'O-' => 'O-',
    'Unknown' => 'Unknown',
];

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$nextMr = patient_repo()->getNextMrNumber($orgId);

$mrModeField = <<<HTML
<div class="sm:col-span-1">
    <div class="flex items-center justify-between mb-1">
        <label class="field-label mb-0" for="patient_no">MR Number</label>
        <div class="flex items-center gap-2 text-xs">
            <label class="inline-flex items-center gap-1 cursor-pointer font-bold text-slate-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                <input type="radio" name="mr_mode" value="auto" checked onchange="togglePatientMrMode('auto')" class="text-emerald-600 focus:ring-emerald-500">
                <span>Auto (Next)</span>
            </label>
            <label class="inline-flex items-center gap-1 cursor-pointer font-bold text-slate-600 px-2 py-0.5 rounded-md hover:bg-slate-100">
                <input type="radio" name="mr_mode" value="manual" onchange="togglePatientMrMode('manual')" class="text-teal-600 focus:ring-teal-500">
                <span>Manual</span>
            </label>
        </div>
    </div>
    <input type="text" id="patient_no" name="patient_no" value="{$nextMr}" class="field text-sm font-mono bg-emerald-50/70 border-emerald-300 text-emerald-950 font-bold cursor-not-allowed" placeholder="Auto-generated on save" readonly>
    <span id="mr-hint" class="text-[11px] text-emerald-700 font-semibold mt-1 block">
        <i class="fa-solid fa-circle-check mr-1"></i> Auto-incremented sequential MR number
    </span>
</div>
HTML;

if (isset($_GET['registered'])) {
    $lastMr = htmlspecialchars((string)($_GET['last_mr'] ?? ''), ENT_QUOTES, 'UTF-8');
    $msgText = 'Patient saved successfully.' . ($lastMr !== '' ? ' (MR: <strong>' . $lastMr . '</strong>)' : '');
    $message = flash_success($msgText);
}

$flowButtons = <<<HTML
<div class="sm:col-span-2 pt-3 border-t border-slate-100 flex flex-wrap items-center gap-2.5">
    <button type="submit" name="submit_action" value="book_tests" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm shadow-md transition-all">
        <i class="fa-solid fa-flask-vial"></i> Save &amp; Book Tests &rarr;
    </button>
    <button type="submit" name="submit_action" value="register_next" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs sm:text-sm shadow-sm transition-all">
        <i class="fa-solid fa-user-plus text-[#c2f13c]"></i> Save &amp; Add Another
    </button>
    <button type="submit" name="submit_action" value="save_only" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs sm:text-sm border border-slate-300 shadow-xs transition-all">
        <i class="fa-solid fa-floppy-disk text-slate-400"></i> Save Patient Only
    </button>
    <a href="/portals/patients/records.php" class="text-xs font-semibold text-slate-500 hover:text-slate-800 ml-auto">View All Patients &rarr;</a>
</div>
HTML;

$content = workflow_stepper('patient');
$content .= page_header('Register Patient', 'Step 1 of 5: Register patient profile, then advance directly to test booking.');
$content .= $message;
$content .= card(
    '<form method="post" class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">' .
    $mrModeField .
    title_input_field('Title', 'title', $_POST['title'] ?? '') .
    form_field('Full name', 'name') .
    form_field('Father / Husband Name', 'relation_of', 'text', null, 'F/H name', true) .
    form_field('Mobile', 'phone') .
    form_field('Age', 'age', 'number') .
    select_field('Gender', 'gender', ['Female' => 'Female', 'Male' => 'Male', 'Other' => 'Other']) .
    doctor_input_field('Referring Doctor', 'doctor', 'Walk-in / Self') .
    form_field('Registration Date & Time', 'entry_time', 'datetime-local', date('Y-m-d\TH:i'), '', true) .
    '<div class="sm:col-span-2">' .
    form_field('Address', 'address', 'text', null, 'House, street, area, city', true) .
    '</div>' .
    $flowButtons .
    '</form>'
) . <<<HTML
<script>
function togglePatientMrMode(mode) {
    const input = document.getElementById('patient_no');
    const hint = document.getElementById('mr-hint');
    if (mode === 'auto') {
        input.value = "{$nextMr}";
        input.readOnly = true;
        input.className = "field text-sm font-mono bg-emerald-50/70 border-emerald-300 text-emerald-950 font-bold cursor-not-allowed";
        if (hint) hint.innerHTML = '<i class="fa-solid fa-circle-check mr-1"></i> Auto-incremented sequential MR number';
    } else {
        input.readOnly = false;
        input.value = "";
        input.focus();
        input.className = "field text-sm font-mono bg-white border-slate-300 text-slate-900 font-bold";
        if (hint) hint.innerHTML = '<i class="fa-solid fa-pen-to-square mr-1"></i> Enter custom manual MR number';
    }
}
</script>
HTML;

render_page('Register Patient', 'main-lab', 'register', $content);
