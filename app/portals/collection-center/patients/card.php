<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$patientId = trim((string)($_GET['id'] ?? $_GET['patient_no'] ?? ''));

$patient = null;
if ($patientId !== '') {
    $patient = patient_repo()->findById($patientId);
}

// Fallback to first available patient if none selected
if (!$patient) {
    $all = patient_repo()->getAll($orgId, 1);
    if (!empty($all[0])) {
        $patient = $all[0];
    }
}

if (!$patient) {
    $content = page_header('Patient Card', 'No patient record available.');
    $content .= card('<div class="p-6 text-slate-600"><p class="mb-4">Please select or register a patient first.</p>' . btn_secondary('/portals/collection-center/patients/records.php', 'Patient Records') . '</div>');
    render_page('Patient Card', 'collection-center', 'records', $content);
    exit;
}

$settings = branding_settings($orgId);
$pName = trim(($patient['title'] ?? 'Mr') . ' ' . ($patient['full_name'] ?? $patient['name'] ?? 'Patient'));
$mrNo = (string)($patient['patient_no'] ?? $patient['id'] ?? 'MR-10001');
$phone = (string)($patient['phone'] ?? '—');
$bloodGroup = !empty($patient['blood_group']) ? (string)$patient['blood_group'] : '—';
$gender = (string)($patient['gender'] ?? 'Male');
$age = (string)($patient['age'] ?? '—');
$city = (string)($patient['city'] ?? 'Faisalabad');
$issueDate = date('d-M-Y', strtotime($patient['created_at'] ?? 'now'));
$labName = $settings['name'] ?? 'Lab Dash Pro Diagnostics';
$labPhone = $settings['phone'] ?? '0328-3070070';
$labAddress = $settings['address'] ?? 'Main Boulevard, Gulberg III';

// QR Code for card
$qrPayload = isset($_SERVER['HTTP_HOST'])
    ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/portals/collection-center/patients/history.php?id=' . rawurlencode($mrNo))
    : $mrNo;
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=110x110&margin=0&data=' . rawurlencode($qrPayload);

// All patients for dropdown switcher
$allPatients = patient_repo()->getAll($orgId, 60);
$patientOpts = '';
foreach ($allPatients as $p) {
    $curId = $p['patient_no'] ?? $p['id'];
    $sel = ($curId === $mrNo || $p['id'] === ($patient['id'] ?? '')) ? ' selected' : '';
    $patientOpts .= '<option value="' . e($curId) . '"' . $sel . '>' . e($curId) . ' — ' . e($p['full_name'] ?? $p['name']) . '</option>';
}

$content = <<<HTML
<div class="no-print">
    <div class="mb-6 flex flex-col sm:flex-row items-center justify-between gap-4 bg-white p-5 rounded-3xl border border-slate-200/90 shadow-card">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-slate-900 text-[#c2f13c] flex items-center justify-center text-xl font-black">
                <i class="fa-solid fa-id-card"></i>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Patient Identification Card</h1>
                <p class="text-xs sm:text-sm text-slate-500 font-medium">Standard CR-80 PVC / Paper card layout with scannable QR and barcode.</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs sm:text-sm transition-all shadow-md">
                <i class="fa-solid fa-print text-[#c2f13c]"></i> Print Patient Card
            </button>
            <a href="/portals/collection-center/patients/records.php" class="btn btn-secondary text-xs sm:text-sm">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>

    <div class="mb-6 p-4 bg-white rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3 max-w-lg">
        <label class="text-xs font-bold text-slate-700 uppercase whitespace-nowrap"><i class="fa-solid fa-user mr-1 text-teal-600"></i> Select Patient:</label>
        <select class="field text-sm" onchange="if(this.value) window.location.href='/portals/collection-center/patients/card.php?id=' + encodeURIComponent(this.value);">
            {$patientOpts}
        </select>
    </div>
</div>

<style>
/* CR-80 Standard Card Dimensions: 85.6mm x 53.98mm (~3.37in x 2.125in) */
.patient-card-sheet {
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
    justify-content: center;
    margin: 2rem auto;
}

.patient-id-card {
    width: 85.6mm;
    height: 54mm;
    border-radius: 4.5mm;
    box-sizing: border-box;
    position: relative;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15), 0 8px 10px -6px rgba(15, 23, 42, 0.1);
    border: 1px solid #cbd5e1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    page-break-inside: avoid;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
}

.patient-id-card--front {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
}

.patient-id-card--front::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4mm;
    background: linear-gradient(90deg, #0f172a 0%, #1e293b 70%, #0d9488 100%);
}

.patient-id-card--back {
    background: #ffffff;
    padding: 3mm 4mm;
}

@media print {
    body {
        background: #ffffff !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .no-print, .app-sidebar, .app-topbar, #sidebar, header {
        display: none !important;
    }
    .lg\\:pl-\\[var\\(--sidebar-width\\)\\] {
        padding-left: 0 !important;
    }
    main.app-main {
        padding: 0 !important;
        background: #ffffff !important;
        min-height: auto !important;
    }
    .patient-card-sheet {
        margin: 5mm auto !important;
        gap: 8mm !important;
    }
    .patient-id-card {
        box-shadow: none !important;
        border: 1px solid #94a3b8 !important;
        page-break-inside: avoid !important;
    }
}
</style>

<div class="patient-card-sheet">
    <!-- FRONT SIDE -->
    <div class="patient-id-card patient-id-card--front p-3">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-200/80 pb-1.5 mt-1">
            <div class="flex items-center gap-1.5 min-w-0">
                <div class="w-6 h-6 rounded-md bg-slate-900 text-[#c2f13c] flex items-center justify-center text-xs font-black shrink-0">
                    <i class="fa-solid fa-flask"></i>
                </div>
                <div class="truncate">
                    <p class="text-[10px] font-black uppercase text-slate-900 tracking-tight leading-none truncate">{$labName}</p>
                    <p class="text-[8px] font-bold text-teal-700 tracking-wide uppercase mt-0.5">Patient Membership Card</p>
                </div>
            </div>
            <span class="px-1.5 py-0.5 rounded bg-rose-50 border border-rose-200 text-rose-700 text-[9px] font-black shrink-0">
                {$bloodGroup}
            </span>
        </div>

        <!-- Body details -->
        <div class="flex items-center justify-between gap-2 my-auto">
            <div class="min-w-0 flex-1">
                <p class="text-[8px] uppercase tracking-wider text-slate-400 font-bold mb-0.5">Patient Name</p>
                <h3 class="text-xs font-extrabold text-slate-900 truncate tracking-tight">{$pName}</h3>
                
                <div class="grid grid-cols-2 gap-x-2 gap-y-0.5 mt-1 text-[9px]">
                    <div>
                        <span class="text-slate-400 font-medium">Age/Sex:</span> 
                        <strong class="text-slate-800">{$age}y / {$gender}</strong>
                    </div>
                    <div>
                        <span class="text-slate-400 font-medium">Phone:</span> 
                        <strong class="text-slate-800 font-mono">{$phone}</strong>
                    </div>
                    <div>
                        <span class="text-slate-400 font-medium">City:</span> 
                        <strong class="text-slate-800">{$city}</strong>
                    </div>
                    <div>
                        <span class="text-slate-400 font-medium">Issued:</span> 
                        <span class="text-slate-600">{$issueDate}</span>
                    </div>
                </div>
            </div>

            <!-- QR code -->
            <div class="text-center shrink-0">
                <div class="p-1 bg-white border border-slate-200 rounded-lg shadow-sm inline-block">
                    <img src="{$qrUrl}" alt="QR" class="w-12 h-12 block">
                </div>
                <span class="block text-[7px] font-bold text-slate-400 uppercase mt-0.5">Scan Profile</span>
            </div>
        </div>

        <!-- Card Bottom Bar with MR -->
        <div class="bg-slate-900 -mx-3 -mb-3 px-3 py-1.5 flex items-center justify-between text-white">
            <div class="flex items-center gap-1.5">
                <span class="text-[8px] uppercase font-bold text-slate-400">MR NO:</span>
                <span class="text-xs font-mono font-black tracking-wider text-[#c2f13c]">{$mrNo}</span>
            </div>
            <span class="text-[8px] font-semibold text-slate-300">Fast-Track Registration</span>
        </div>
    </div>

    <!-- BACK SIDE -->
    <div class="patient-id-card patient-id-card--back">
        <div>
            <!-- Magnetic / Barcode stripe representation -->
            <div class="h-5 bg-slate-900 -mx-4 -mt-3 mb-2 flex items-center justify-center">
                <span class="text-[8px] font-mono text-slate-400 uppercase tracking-widest">DIGITAL HEALTH RECORD PASS</span>
            </div>

            <div class="text-[8px] text-slate-600 leading-tight space-y-1">
                <p class="font-bold text-slate-800"><i class="fa-solid fa-circle-info text-teal-600 mr-1"></i> Card Instructions & Benefits:</p>
                <p>1. Present this card at any collection branch for instant priority registration.</p>
                <p>2. Scan the front QR code with any smartphone camera to view verified test reports online.</p>
                <p>3. This card is valid for laboratory diagnostic services across all regional branches.</p>
            </div>
        </div>

        <!-- Helpline & Vendor credit -->
        <div class="border-t border-slate-200 pt-1 text-center">
            <p class="text-[8px] font-bold text-slate-800"><i class="fa-solid fa-phone text-teal-600 mr-0.5"></i> Helpline: {$labPhone} &middot; {$labAddress}</p>
            <p class="text-[7px] text-slate-400 mt-0.5">Software created by Shakeel Khalid &middot; WhatsApp 0328-3070070</p>
        </div>
    </div>
</div>
HTML;

render_page('Patient Card', 'collection-center', 'records', $content);
