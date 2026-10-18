<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$message = '';

// Handle quick patient registration
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['register_patient'])) {
    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $age = trim((string)($_POST['age'] ?? ''));
    $gender = trim((string)($_POST['gender'] ?? 'Male'));
    $address = trim((string)($_POST['address'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));

    if ($name === '') {
        $message = flash_error('Please enter the patient full name.');
    } else {
        $pId = 'P-' . date('ym') . '-' . sprintf('%03d', rand(100, 999));
        $res = patient_repo()->create([
            'patient_no' => $pId,
            'title' => $_POST['title'] ?? ($gender === 'Female' ? 'Ms.' : 'Mr.'),
            'full_name' => $name,
            'phone' => $phone,
            'age' => $age ?: '30',
            'gender' => $gender,
            'address' => $address,
            'city' => $city,
            'organization_id' => $orgId,
        ]);
        if ($res) {
            $createdPid = $res['patient_no'] ?? $pId;
            header('Location: /portals/imaging/new-scan.php?patient_id=' . urlencode($createdPid));
            exit;
        }
        $message = flash_error('Could not register patient.');
    }
}

$allPatients = patient_repo()->getAll($orgId);

$rows = [];
foreach ($allPatients as $p) {
    $pId = (string)($p['patient_no'] ?? $p['id']);
    $pName = trim(($p['title'] ?? '') . ' ' . ($p['full_name'] ?? $p['name'] ?? ''));
    $phone = (string)($p['phone'] ?? '—');
    $ageGender = trim(($p['age'] ?? '—') . ' yrs / ' . ($p['gender'] ?? '—'), ' /');
    $city = (string)($p['city'] ?? '—');

    $actions = '<div class="inline-flex items-center gap-1.5 whitespace-nowrap">' .
        '<a href="/portals/imaging/new-scan.php?patient_id=' . urlencode($pId) . '" class="btn btn-primary text-xs px-2.5 py-1 font-bold bg-[#c2f13c] text-slate-950 hover:bg-[#b2e62a]"><i class="fa-solid fa-plus mr-1"></i> Book Order</a>' .
        '<a href="/portals/imaging/report-history.php?q=' . urlencode($pName) . '" class="btn btn-secondary text-xs px-2.5 py-1 font-semibold"><i class="fa-solid fa-clock-rotate-left mr-1"></i> History</a>' .
        '<a href="/portals/main-lab/patients/card.php?id=' . urlencode($pId) . '" class="btn btn-secondary text-xs px-2 py-1 font-semibold" title="Print ID Card"><i class="fa-solid fa-id-card"></i></a>' .
        '</div>';

    $rows[] = [
        '<span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md text-xs">' . e($pId) . '</span>',
        '<span class="font-bold text-slate-900">' . e($pName) . '</span>',
        '<span class="text-xs font-mono text-slate-600">' . e($phone) . '</span>',
        '<span class="text-xs text-slate-500 font-medium">' . e($ageGender) . '</span>',
        '<span class="text-xs text-slate-500">' . e($city) . '</span>',
        $actions,
    ];
}

$headerActions = <<<HTML
<div class="flex items-center gap-2">
    <button type="button" onclick="toggleRegisterModal(true)" class="btn btn-primary text-xs font-bold py-2 px-3 bg-blue-600 hover:bg-blue-700 text-white shadow-sm">
        <i class="fa-solid fa-user-plus mr-1"></i> Register New Patient
    </button>
    <a href="/portals/imaging/new-scan.php" class="btn btn-primary text-xs font-bold py-2 px-3 bg-[#c2f13c] text-slate-950 hover:bg-[#b2e62a] shadow-sm">
        <i class="fa-solid fa-file-circle-plus mr-1"></i> New Booking Slip
    </a>
</div>
HTML;

$content = page_header('Patient Directory', 'Centralized patient records shared across Laboratory, Ultrasound, X-Ray, ECG, CT Scan & MRI.', $headerActions, 'fa-solid fa-users');
$content .= $message;

$content .= <<<HTML
<!-- Quick Register Modal / Slide-down -->
<div id="register-modal" class="hidden mb-6 bg-white rounded-3xl p-5 sm:p-6 border-2 border-blue-200 shadow-xl">
    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
        <div class="flex items-center gap-2">
            <span class="w-8 h-8 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm">
                <i class="fa-solid fa-user-plus"></i>
            </span>
            <div>
                <h3 class="text-sm font-extrabold text-slate-900 leading-tight">Register New Diagnostic Patient</h3>
                <span class="text-xs text-slate-400">Save record then proceed directly to booking</span>
            </div>
        </div>
        <button type="button" onclick="toggleRegisterModal(false)" class="text-slate-400 hover:text-slate-600 p-1">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>
    </div>

    <form method="post" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 items-end">
        <input type="hidden" name="register_patient" value="1">
        
        <div>
            <label class="field-label" for="pt-name">Full Name *</label>
            <input type="text" id="pt-name" name="name" class="field text-xs" placeholder="e.g. Tariq Mehmood" required>
        </div>

        <div>
            <label class="field-label" for="pt-phone">Phone Number *</label>
            <input type="text" id="pt-phone" name="phone" class="field text-xs" placeholder="0300-1234567" required>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="field-label" for="pt-age">Age (Yrs)</label>
                <input type="number" id="pt-age" name="age" class="field text-xs" placeholder="35" min="1" max="120">
            </div>
            <div>
                <label class="field-label" for="pt-gender">Gender</label>
                <select id="pt-gender" name="gender" class="field text-xs">
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>

        <div>
            <label class="field-label" for="pt-city">City / Area</label>
            <input type="text" id="pt-city" name="city" class="field text-xs" placeholder="Lahore">
        </div>

        <div class="sm:col-span-2 lg:col-span-4 flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
            <button type="button" onclick="toggleRegisterModal(false)" class="btn btn-secondary text-xs">Cancel</button>
            <button type="submit" class="btn btn-primary text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white px-5">
                <i class="fa-solid fa-check mr-1"></i> Save &amp; Book Order
            </button>
        </div>
    </form>
</div>

<script>
function toggleRegisterModal(show) {
    const el = document.getElementById('register-modal');
    if (el) {
        if (show) {
            el.classList.remove('hidden');
            const inp = document.getElementById('pt-name');
            if (inp) inp.focus();
        } else {
            el.classList.add('hidden');
        }
    }
}
</script>
HTML;

$content .= card(
    panel_head('All Registered Patients (' . count($allPatients) . ')') .
    data_table(['MR Number', 'Full Name', 'Phone', 'Age / Gender', 'City', 'Actions'], $rows),
    'overflow-hidden'
);

render_page('Patients', 'imaging', 'patients', $content);
