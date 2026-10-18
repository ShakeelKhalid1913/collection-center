<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$message = '';

// Preselected modality from query parameter (e.g. ?modality=us or ?modality=xray)
$preselectModality = trim((string)($_GET['modality'] ?? ''));

// Handle form submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $patientId = trim((string)($_POST['patient_id'] ?? ''));
    $walkinName = trim((string)($_POST['walkin_name'] ?? ''));
    $walkinPhone = trim((string)($_POST['walkin_phone'] ?? ''));
    $walkinAge = trim((string)($_POST['walkin_age'] ?? ''));
    $walkinGender = trim((string)($_POST['walkin_gender'] ?? ''));

    $patientName = 'Walk-in Patient';

    if ($patientId !== '') {
        $p = patient_repo()->findById($patientId);
        if ($p) {
            $patientName = trim(($p['title'] ?? '') . ' ' . ($p['full_name'] ?? $p['name'] ?? $patientId));
            $patientId = $p['patient_no'] ?? $p['id'] ?? $patientId;
        }
    } elseif ($walkinName !== '') {
        // Register walk-in as new patient record
        $patientName = $walkinName;
        $newPid = 'P-' . date('ym') . '-' . sprintf('%03d', rand(100, 999));
        try {
            $newP = patient_repo()->create([
                'patient_no' => $newPid,
                'full_name' => $walkinName,
                'phone' => $walkinPhone,
                'age' => $walkinAge ?: '30',
                'gender' => $walkinGender ?: 'Male',
                'organization_id' => $orgId,
            ]);
            $patientId = $newP['patient_no'] ?? $newPid;
        } catch (\Throwable $e) {
            $patientId = $newPid;
        }
    }

    // Resolve selected tests from checkboxes or custom list
    $selectedTests = $_POST['tests'] ?? [];
    if (!is_array($selectedTests)) {
        $selectedTests = array_filter(array_map('trim', explode(',', (string)$selectedTests)));
    }
    
    // Custom test name if entered
    $customTest = trim((string)($_POST['custom_test'] ?? ''));
    if ($customTest !== '') {
        $selectedTests[] = $customTest;
    }

    if (empty($selectedTests)) {
        $message = flash_error('Please select at least one test or study to book.');
    } else {
        $testsString = implode(', ', array_unique($selectedTests));

        $docCustom = trim((string)($_POST['doctor_custom'] ?? ''));
        $docSelect = trim((string)($_POST['doctor'] ?? ''));
        $doctor = $docCustom !== '' ? $docCustom : ($docSelect !== '' ? resolve_doctor_name($docSelect) : 'Walk-in / Self');

        $gross = (float)($_POST['gross_amount'] ?? 0);
        $discount = (float)($_POST['discount'] ?? 0);
        $paid = (float)($_POST['paid'] ?? 0);
        $net = max(0, $gross - $discount);
        if ($gross <= 0) {
            // Auto calculate from tests catalog
            foreach ($selectedTests as $tName) {
                $tMeta = test_repo()->findByCode($tName);
                $gross += $tMeta ? (float)($tMeta['price'] ?? 1000) : 1000.0;
            }
            $net = max(0, $gross - $discount);
        }
        if ($paid <= 0 && isset($_POST['paid_full'])) {
            $paid = $net;
        }

        // Create the unified lab_entries invoice (auto-syncs to imaging_scans and test_results)
        $res = lab_repo()->create([
            'patient_id' => $patientId ?: 'WALK-IN',
            'patient_name' => $patientName,
            'tests' => $testsString,
            'doctor' => $doctor,
            'route' => 'Diagnostic Center',
            'priority' => $_POST['priority'] ?? 'Normal',
            'status' => 'pending',
            'sample_status' => 'received',
            'amount' => $gross,
            'paid' => $paid,
            'discount' => $discount,
            'clinical_notes' => $_POST['clinical'] ?? '',
            'created_at' => !empty($_POST['entry_time']) ? $_POST['entry_time'] : date('Y-m-d H:i:s'),
            'organization_id' => $orgId,
            'branch' => 'DC-MAIN',
            'branch_id' => current_user()['branch_id'] ?? 'BR-IMAGING',
        ]);

        if ($res['success']) {
            $createdLabNo = $res['lab_no'];
            // Look up any created imaging scan number
            $firstScan = db()->fetchOne("SELECT scan_no FROM imaging_scans WHERE lab_no = :ln LIMIT 1", ['ln' => $createdLabNo]);
            $scanParam = !empty($firstScan['scan_no']) ? ('&scan_no=' . urlencode($firstScan['scan_no'])) : '';

            header('Location: /portals/imaging/receipt.php?lab_no=' . urlencode($createdLabNo) . $scanParam . '&created=1');
            exit;
        }

        $message = flash_error('Could not save booking order. Please check inputs.');
    }
}

// Dynamic Patients
$patientOpts = ['' => '— Search / Select Registered Patient —'];
$allPatients = patient_repo()->getAll($orgId);
foreach ($allPatients as $p) {
    $pid = $p['patient_no'] ?? $p['id'];
    $pname = trim(($p['title'] ?? '') . ' ' . ($p['full_name'] ?? $p['name'] ?? ''));
    $patientOpts[$pid] = $pid . ' · ' . $pname . ' · ' . ($p['phone'] ?? 'No Phone');
}

// Selected patient from GET parameter
$selectedPatientId = trim((string)($_GET['patient_id'] ?? ''));

// Dynamic Doctors
$doctorOpts = ['' => '— Select Referring Doctor / Consultant —'];
foreach (mock('mock_doctors') as $d) {
    $spec = !empty($d['specialty']) ? ' (' . $d['specialty'] . ')' : '';
    $doctorOpts[$d['name']] = $d['name'] . $spec;
}

// Dynamic Test Catalog across All 6 Departments
$allCatalogTests = test_repo()->getTests($orgId);

// Categorize tests by Department
$deptCatalog = [
    'lab' => [
        'name' => 'Pathology / Laboratory',
        'icon' => 'fa-solid fa-flask',
        'badge' => 'bg-emerald-100 text-emerald-800',
        'tests' => [],
    ],
    'ultrasound' => [
        'name' => 'Ultrasound',
        'icon' => 'fa-solid fa-wave-square',
        'badge' => 'bg-teal-100 text-teal-800',
        'tests' => [],
    ],
    'xray' => [
        'name' => 'Digital X-Ray',
        'icon' => 'fa-solid fa-x-ray',
        'badge' => 'bg-blue-100 text-blue-800',
        'tests' => [],
    ],
    'ecg' => [
        'name' => '12-Lead ECG',
        'icon' => 'fa-solid fa-heart-pulse',
        'badge' => 'bg-rose-100 text-rose-800',
        'tests' => [],
    ],
    'ct' => [
        'name' => 'CT Scan',
        'icon' => 'fa-solid fa-laptop-medical',
        'badge' => 'bg-purple-100 text-purple-800',
        'tests' => [],
    ],
    'mri' => [
        'name' => 'MRI Scan',
        'icon' => 'fa-solid fa-magnet',
        'badge' => 'bg-indigo-100 text-indigo-800',
        'tests' => [],
    ],
];

// Fallback standard tests for any empty departments
$defaultDeptTests = [
    'lab' => [
        ['name' => 'Complete Blood Picture (CBC)', 'price' => 750, 'code' => 'CBC'],
        ['name' => 'Blood Sugar Fasting (BSF)', 'price' => 300, 'code' => 'BSF'],
        ['name' => 'Blood Sugar Random (BSR)', 'price' => 300, 'code' => 'BSR'],
        ['name' => 'Liver Function Tests (LFT)', 'price' => 1500, 'code' => 'LFT'],
        ['name' => 'Renal Function Tests (RFT / Urea / Creatinine)', 'price' => 1200, 'code' => 'RFT'],
        ['name' => 'Lipid Profile', 'price' => 1800, 'code' => 'LIPID'],
        ['name' => 'Urine Complete Examination (Urine RE)', 'price' => 400, 'code' => 'URE'],
        ['name' => 'Serum Electrolytes (Na/K/Cl)', 'price' => 1200, 'code' => 'SE'],
        ['name' => 'HbA1c (Glycated Hemoglobin)', 'price' => 1500, 'code' => 'HBA1C'],
        ['name' => 'Thyroid Profile (T3, T4, TSH)', 'price' => 2500, 'code' => 'TFT'],
        ['name' => 'Serum Uric Acid', 'price' => 450, 'code' => 'URIC'],
        ['name' => 'HbsAg & Anti-HCV Screening', 'price' => 1200, 'code' => 'HEPB_C'],
    ],
    'ultrasound' => [
        ['name' => 'Ultrasound Whole Abdomen & Pelvis', 'price' => 2000, 'code' => 'US_ABD_PEL'],
        ['name' => 'Ultrasound KUB & Prostate', 'price' => 1800, 'code' => 'US_KUB'],
        ['name' => 'Ultrasound Pelvis (Female)', 'price' => 1800, 'code' => 'US_PELVIS'],
        ['name' => 'Ultrasound Obstetric / Pregnancy', 'price' => 2000, 'code' => 'US_OBS'],
        ['name' => 'Ultrasound 2nd Trimester Anomaly Scan', 'price' => 3000, 'code' => 'US_ANOMALY'],
        ['name' => 'Ultrasound Upper Abdomen', 'price' => 1800, 'code' => 'US_UPPER_ABD'],
        ['name' => 'Ultrasound Scrotum & Doppler', 'price' => 2500, 'code' => 'US_SCROTUM'],
        ['name' => 'Ultrasound Thyroid & Neck', 'price' => 2000, 'code' => 'US_THYROID'],
        ['name' => 'Ultrasound Breast (Bilateral)', 'price' => 2500, 'code' => 'US_BREAST'],
        ['name' => 'Ultrasound TVS (Transvaginal)', 'price' => 2500, 'code' => 'US_TVS'],
    ],
    'xray' => [
        ['name' => 'Chest X-Ray PA View', 'price' => 1000, 'code' => 'XR_CHEST_PA'],
        ['name' => 'Chest X-Ray AP/LAT', 'price' => 1500, 'code' => 'XR_CHEST_LAT'],
        ['name' => 'Cervical Spine X-Ray AP/LAT', 'price' => 1500, 'code' => 'XR_CSPINE'],
        ['name' => 'Lumbar Spine X-Ray AP/LAT', 'price' => 1500, 'code' => 'XR_LSPINE'],
        ['name' => 'Knee Joint X-Ray AP/LAT', 'price' => 1400, 'code' => 'XR_KNEE'],
        ['name' => 'Pelvis with Both Hips AP', 'price' => 1400, 'code' => 'XR_PELVIS'],
        ['name' => 'Abdomen Plain X-Ray (Erect & Supine)', 'price' => 1500, 'code' => 'XR_ABD'],
        ['name' => 'PNS (Paranasal Sinuses) Water\'s View', 'price' => 1200, 'code' => 'XR_PNS'],
    ],
    'ecg' => [
        ['name' => '12-Lead Electrocardiogram (ECG)', 'price' => 800, 'code' => 'ECG_12LEAD'],
        ['name' => 'ECG with Rhythm Strip', 'price' => 1000, 'code' => 'ECG_RHYTHM'],
    ],
    'ct' => [
        ['name' => 'Brain Plain CT Scan', 'price' => 6500, 'code' => 'CT_BRAIN'],
        ['name' => 'Brain CT Scan with IV Contrast', 'price' => 9500, 'code' => 'CT_BRAIN_CONTRAST'],
        ['name' => 'HRCT Chest (High Resolution)', 'price' => 8500, 'code' => 'CT_HRCT_CHEST'],
        ['name' => 'Whole Abdomen & Pelvis CT Scan', 'price' => 11000, 'code' => 'CT_ABD_PEL'],
        ['name' => 'PNS CT Scan (Coronal & Axial)', 'price' => 7000, 'code' => 'CT_PNS'],
        ['name' => 'Cervical / Lumbar Spine CT Scan', 'price' => 7500, 'code' => 'CT_SPINE'],
    ],
    'mri' => [
        ['name' => 'MRI Brain Plain', 'price' => 12000, 'code' => 'MRI_BRAIN'],
        ['name' => 'MRI Brain Plain & Contrast', 'price' => 16000, 'code' => 'MRI_BRAIN_CONTRAST'],
        ['name' => 'MRI Lumbar Spine (L-Spine)', 'price' => 12000, 'code' => 'MRI_LSPINE'],
        ['name' => 'MRI Cervical Spine (C-Spine)', 'price' => 12000, 'code' => 'MRI_CSPINE'],
        ['name' => 'MRI Knee Joint (Right / Left)', 'price' => 12000, 'code' => 'MRI_KNEE'],
        ['name' => 'MRCP (Biliary Tree & Pancreas)', 'price' => 15000, 'code' => 'MRI_MRCP'],
    ],
];

// Populate tests from database
$seenTestNames = [];
foreach ($allCatalogTests as $t) {
    $tName = trim((string)($t['name'] ?? ''));
    if ($tName === '' || isset($seenTestNames[strtolower($tName)])) {
        continue;
    }
    $seenTestNames[strtolower($tName)] = true;
    $deptMeta = get_department_meta($tName, (string)($t['category'] ?? ''));
    $deptId = $deptMeta['id'] ?? 'lab';
    if (!isset($deptCatalog[$deptId])) {
        $deptId = 'lab';
    }
    $deptCatalog[$deptId]['tests'][] = [
        'name' => $tName,
        'price' => (float)($t['price'] ?? 1000),
        'code' => (string)($t['code'] ?? $tName),
    ];
}

// Add standard default tests if missing
foreach ($defaultDeptTests as $dKey => $tList) {
    foreach ($tList as $dTest) {
        if (!isset($seenTestNames[strtolower($dTest['name'])])) {
            $seenTestNames[strtolower($dTest['name'])] = true;
            $deptCatalog[$dKey]['tests'][] = $dTest;
        }
    }
}

// Check which tab should be active initially
$activeTabKey = match (strtolower($preselectModality)) {
    'us', 'ultrasound' => 'ultrasound',
    'xray', 'x-ray' => 'xray',
    'ecg', 'ekg' => 'ecg',
    'ct' => 'ct',
    'mri' => 'mri',
    'lab', 'pathology' => 'lab',
    default => 'all',
};

// Build Department Tabs HTML
$tabButtonsHtml = '<button type="button" onclick="filterCatalogDept(\'all\', this)" class="dept-tab-btn px-3.5 py-1.5 rounded-xl font-bold text-xs transition-all ' . ($activeTabKey === 'all' ? 'bg-slate-900 text-white shadow-sm' : 'bg-slate-100 hover:bg-slate-200 text-slate-700') . '">All Modalities</button>';
foreach ($deptCatalog as $k => $d) {
    $isActive = ($activeTabKey === $k);
    $activeCls = $isActive ? 'bg-slate-900 text-white shadow-sm' : 'bg-slate-100 hover:bg-slate-200 text-slate-700';
    $tabButtonsHtml .= '<button type="button" onclick="filterCatalogDept(\'' . $k . '\', this)" class="dept-tab-btn px-3.5 py-1.5 rounded-xl font-bold text-xs transition-all ' . $activeCls . ' flex items-center gap-1.5">'
        . '<i class="' . $d['icon'] . '"></i> ' . e($d['name'])
        . '<span class="ml-1 text-[10px] px-1.5 py-0.2 rounded-full ' . ($isActive ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700') . '">' . count($d['tests']) . '</span>'
        . '</button>';
}

// Build Test Checkboxes HTML
$catalogCardsHtml = '';
foreach ($deptCatalog as $k => $d) {
    foreach ($d['tests'] as $testItem) {
        $tName = $testItem['name'];
        $tPrice = (float)$testItem['price'];
        $tCode = $testItem['code'];
        $tPriceFmt = number_format($tPrice, 0);

        $catalogCardsHtml .= <<<CARD
        <label class="test-item-card flex items-start gap-2.5 p-3 rounded-2xl border border-slate-200/90 hover:border-blue-400 hover:bg-blue-50/30 transition-all cursor-pointer select-none bg-white shadow-2xs" 
               data-dept="{$k}" 
               data-name="{$tName}" 
               data-price="{$tPrice}">
            <input type="checkbox" name="tests[]" value="{$tName}" 
                   class="test-checkbox mt-1 w-4 h-4 rounded text-blue-600 border-slate-300 focus:ring-blue-500 cursor-pointer"
                   data-name="{$tName}" 
                   data-price="{$tPrice}"
                   data-dept-name="{$d['name']}"
                   onchange="handleTestToggle(this)">
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-1 mb-0.5">
                    <span class="inline-block px-1.5 py-0.2 rounded text-[10px] font-extrabold uppercase tracking-wider {$d['badge']}">
                        <i class="{$d['icon']} text-[9px] mr-0.5"></i> {$d['name']}
                    </span>
                    <span class="font-mono font-bold text-xs text-slate-900 shrink-0">Rs. {$tPriceFmt}</span>
                </div>
                <div class="font-bold text-slate-800 text-xs truncate leading-snug" title="{$tName}">{$tName}</div>
            </div>
        </label>
CARD;
    }
}

// Build Patient Options HTML
$patientSelectOpts = '';
foreach ($patientOpts as $k => $v) {
    $kStr = (string)$k;
    $vStr = (string)$v;
    $sel = ($selectedPatientId !== '' && $selectedPatientId === $kStr) ? ' selected' : '';
    $patientSelectOpts .= '<option value="' . e($kStr) . '"' . $sel . '>' . e($vStr) . '</option>';
}

// Build Doctor Options HTML
$docSelectOpts = '';
foreach ($doctorOpts as $k => $v) {
    $kStr = (string)$k;
    $vStr = (string)$v;
    $docSelectOpts .= '<option value="' . e($kStr) . '">' . e($vStr) . '</option>';
}

$headerActions = <<<HTML
<div class="flex items-center gap-2">
    <a href="/portals/imaging/patients.php" class="btn btn-secondary text-xs font-semibold py-2 px-3">
        <i class="fa-solid fa-users mr-1"></i> Patient Directory
    </a>
    <a href="/portals/imaging/dashboard.php" class="btn btn-secondary text-xs font-semibold py-2 px-3">
        <i class="fa-solid fa-gauge-high mr-1"></i> Center Dashboard
    </a>
</div>
HTML;

$content = page_header('New Booking (Unified Slip)', 'Single entry point for all departments: Laboratory, Ultrasound, X-Ray, ECG, CT Scan, and MRI.', $headerActions, 'fa-solid fa-file-circle-plus');
$content .= $message;

$content .= <<<HTML
<form method="post" id="unified-booking-form" class="space-y-6">

    <!-- Top Grid: Patient & Referral Information -->
    <div class="bg-white rounded-3xl p-5 sm:p-6 border border-slate-200/90 shadow-card">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm">
                    <i class="fa-solid fa-user-plus"></i>
                </span>
                <div>
                    <h3 class="text-sm sm:text-base font-extrabold text-slate-900 leading-tight">Patient &amp; Referral Details</h3>
                    <span class="text-xs text-slate-400">Search patient from records or quickly enter walk-in</span>
                </div>
            </div>
            <a href="/portals/imaging/patients.php" class="text-xs font-bold text-blue-600 hover:underline inline-flex items-center gap-1">
                <i class="fa-solid fa-address-book"></i> Browse Patients
            </a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Registered Patient Select -->
            <div class="sm:col-span-2 lg:col-span-1">
                <label class="field-label" for="booking-patient-select">Registered Patient</label>
                <select id="booking-patient-select" name="patient_id" class="field text-sm" onchange="handlePatientSelect(this)">
                    {$patientSelectOpts}
                </select>
                <span class="text-[11px] text-slate-400 mt-1 block">Pick from shared patient master record</span>
            </div>

            <!-- Walk-in Name -->
            <div>
                <label class="field-label" for="walkin-name">Or Walk-in Patient Name</label>
                <input type="text" id="walkin-name" name="walkin_name" class="field text-sm" placeholder="e.g. Muhammad Ali">
                <span class="text-[11px] text-slate-400 mt-1 block">Used if not already registered</span>
            </div>

            <!-- Walk-in Phone -->
            <div>
                <label class="field-label" for="walkin-phone">Patient Phone / WhatsApp</label>
                <input type="text" id="walkin-phone" name="walkin_phone" class="field text-sm" placeholder="0300-1234567">
            </div>

            <!-- Age & Gender -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="field-label" for="walkin-age">Age (Yrs)</label>
                    <input type="number" id="walkin-age" name="walkin_age" class="field text-sm" placeholder="32" min="1" max="120">
                </div>
                <div>
                    <label class="field-label" for="walkin-gender">Gender</label>
                    <select id="walkin-gender" name="walkin_gender" class="field text-sm">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <!-- Referring Doctor -->
            <div>
                <label class="field-label" for="doctor-select">Referring Doctor / Consultant</label>
                <select id="doctor-select" name="doctor" class="field text-sm">
                    {$docSelectOpts}
                </select>
            </div>

            <!-- Custom Doctor Field -->
            <div>
                <label class="field-label" for="doctor-custom">Or Type Doctor Name</label>
                <input type="text" id="doctor-custom" name="doctor_custom" class="field text-sm" placeholder="e.g. Dr. Ayesha Khan">
            </div>

            <!-- Clinical Indication / Notes -->
            <div class="sm:col-span-2">
                <label class="field-label" for="clinical-notes">Clinical Indication / Symptoms / Indication</label>
                <input type="text" id="clinical-notes" name="clinical" class="field text-sm" placeholder="e.g. Persistent lower abdominal pain & fever for 4 days">
            </div>

            <!-- Priority -->
            <div>
                <label class="field-label" for="booking-priority">Order Priority</label>
                <select id="booking-priority" name="priority" class="field text-sm font-semibold">
                    <option value="Normal">Normal</option>
                    <option value="Urgent">Urgent</option>
                    <option value="STAT">STAT / Emergency</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Main Section: Two-Column Layout (Catalog on Left, Order Manifest on Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- Left Column: Unified Multi-Department Catalog Picker (8 Cols) -->
        <div class="lg:col-span-7 xl:col-span-8 bg-white rounded-3xl p-5 sm:p-6 border border-slate-200/90 shadow-card space-y-4">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center font-bold text-sm">
                        <i class="fa-solid fa-list-check"></i>
                    </span>
                    <div>
                        <h3 class="text-sm sm:text-base font-extrabold text-slate-900 leading-tight">Diagnostic Test Catalog</h3>
                        <span class="text-xs text-slate-400">Multi-select tests across any modality on one slip</span>
                    </div>
                </div>

                <!-- Instant Search Bar -->
                <div class="w-full sm:w-64">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                        <input type="text" id="catalog-search" 
                               placeholder="Search CBC, X-Ray, Pelvis..." 
                               class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl border border-slate-300 bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                               onkeyup="handleCatalogSearch(this.value)">
                    </div>
                </div>
            </div>

            <!-- Modality Filter Buttons -->
            <div class="flex flex-wrap items-center gap-1.5 p-1.5 bg-slate-50 rounded-2xl border border-slate-200/80">
                {$tabButtonsHtml}
            </div>

            <!-- Catalog Checkbox Grid -->
            <div id="catalog-cards-container" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-[520px] overflow-y-auto pr-1">
                {$catalogCardsHtml}
            </div>

            <!-- Custom Investigation Add Row -->
            <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center gap-2">
                <span class="text-xs font-bold text-slate-500 whitespace-nowrap"><i class="fa-solid fa-plus-circle text-blue-600 mr-1"></i> Custom Test:</span>
                <input type="text" name="custom_test" placeholder="e.g. Right Shoulder AP/LAT View" class="field text-xs flex-1 min-w-[12rem] py-1.5">
                <span class="text-[11px] text-slate-400">Can be entered if not listed above</span>
            </div>
        </div>

        <!-- Right Column: Order Manifest & Real-Time Billing (4 Cols Sticky) -->
        <div class="lg:col-span-5 xl:col-span-4 space-y-4 lg:sticky lg:top-20">
            
            <div class="bg-gradient-to-br from-[#0c1322] via-[#0f172a] to-[#1e293b] text-white rounded-3xl p-5 sm:p-6 shadow-xl border border-slate-800">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-xl bg-[#c2f13c]/20 text-[#c2f13c] flex items-center justify-center font-bold text-sm">
                            <i class="fa-solid fa-receipt"></i>
                        </span>
                        <div>
                            <h3 class="text-sm font-extrabold tracking-tight text-white">Order Manifest</h3>
                            <span class="text-[11px] text-slate-400">Unified slip summary</span>
                        </div>
                    </div>
                    <span id="manifest-count-badge" class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#c2f13c] text-slate-950">
                        0 Tests
                    </span>
                </div>

                <!-- Selected Items List -->
                <div id="manifest-items-list" class="my-4 divide-y divide-slate-800/80 max-h-48 overflow-y-auto pr-1">
                    <div id="empty-manifest-hint" class="py-6 text-center text-slate-400 italic text-xs">
                        <i class="fa-solid fa-cart-flatbed text-2xl mb-1 text-slate-600 block"></i>
                        No tests selected yet.<br>Click tests from the catalog on the left.
                    </div>
                </div>

                <!-- Real-Time Billing Calculations -->
                <div class="pt-3 border-t border-slate-800/80 space-y-2 text-xs">
                    <div class="flex items-center justify-between text-slate-300">
                        <span>Catalog Subtotal:</span>
                        <span class="font-mono font-bold text-white text-sm" id="calc-gross-disp">Rs. 0</span>
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-1">
                        <label class="text-slate-300 font-medium" for="billing-discount">Discount (Rs.):</label>
                        <input type="number" id="billing-discount" name="discount" value="0" min="0" step="10"
                               class="w-28 px-2.5 py-1 text-right text-xs rounded-xl bg-slate-800/90 border border-slate-700 text-white font-mono font-bold focus:outline-none focus:ring-1 focus:ring-[#c2f13c]"
                               oninput="calculateBillingTotals()">
                    </div>

                    <div class="flex items-center justify-between text-[#c2f13c] font-black text-sm pt-2 border-t border-slate-800">
                        <span>Net Payable:</span>
                        <span class="font-mono text-base" id="calc-net-disp">Rs. 0</span>
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-1">
                        <label class="text-emerald-400 font-bold" for="billing-paid">Amount Paid (Rs.):</label>
                        <input type="number" id="billing-paid" name="paid" value="0" min="0" step="10"
                               class="w-28 px-2.5 py-1 text-right text-xs rounded-xl bg-slate-800/90 border border-emerald-500/50 text-emerald-400 font-mono font-bold focus:outline-none focus:ring-1 focus:ring-emerald-400"
                               oninput="calculateBillingTotals()">
                    </div>

                    <div class="flex items-center justify-between text-rose-400 font-bold pt-1">
                        <span>Balance Due:</span>
                        <span class="font-mono" id="calc-due-disp">Rs. 0</span>
                    </div>
                </div>

                <!-- Hidden inputs for calculated amounts -->
                <input type="hidden" name="gross_amount" id="hidden-gross" value="0">

                <!-- Submit Button -->
                <div class="mt-5 pt-3 border-t border-slate-800 flex flex-col gap-2">
                    <button type="submit" class="w-full py-3 px-4 rounded-2xl bg-[#c2f13c] hover:bg-[#b2e62a] text-slate-950 font-black text-xs sm:text-sm transition-all shadow-md flex items-center justify-center gap-2">
                        <i class="fa-solid fa-print"></i>
                        <span>Generate Unified Token Slip</span>
                    </button>
                    <a href="/portals/imaging/dashboard.php" class="text-center text-xs text-slate-400 hover:text-white py-1">
                        Cancel &amp; Return to Dashboard
                    </a>
                </div>
            </div>

            <!-- Department Routing Info Box -->
            <div class="bg-white rounded-2xl p-4 border border-slate-200/90 text-xs text-slate-600 shadow-2xs space-y-1.5">
                <div class="font-bold text-slate-800 flex items-center gap-1.5">
                    <i class="fa-solid fa-sitemap text-blue-600"></i>
                    <span>Automatic Multi-Department Dispatch</span>
                </div>
                <p class="text-[11px] leading-relaxed text-slate-500">
                    Booking this order creates a single unified token invoice. Pathology tests will automatically show on the <strong>Laboratory Station</strong>, and imaging studies will automatically show on <strong>Ultrasound, X-Ray, ECG, CT Scan, and MRI</strong> stations.
                </p>
            </div>

        </div>

    </div>

</form>

<script>
let currentSelectedTests = [];

function handlePatientSelect(selectEl) {
    const val = selectEl.value;
    const walkinNameInput = document.getElementById('walkin-name');
    const walkinPhoneInput = document.getElementById('walkin-phone');
    if (val !== '') {
        // Clear walk-in inputs
        if (walkinNameInput) walkinNameInput.value = '';
        if (walkinPhoneInput) walkinPhoneInput.value = '';
    }
}

function filterCatalogDept(deptKey, btnEl) {
    // Update active tab buttons
    document.querySelectorAll('.dept-tab-btn').forEach(btn => {
        btn.classList.remove('bg-slate-900', 'text-white', 'shadow-sm');
        btn.classList.add('bg-slate-100', 'text-slate-700');
    });
    if (btnEl) {
        btnEl.classList.remove('bg-slate-100', 'text-slate-700');
        btnEl.classList.add('bg-slate-900', 'text-white', 'shadow-sm');
    }

    // Filter card visibility
    const cards = document.querySelectorAll('.test-item-card');
    cards.forEach(card => {
        const d = card.getAttribute('data-dept');
        if (deptKey === 'all' || d === deptKey) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });

    // Clear search bar
    const searchInput = document.getElementById('catalog-search');
    if (searchInput) searchInput.value = '';
}

function handleCatalogSearch(q) {
    q = (q || '').toLowerCase().trim();
    const cards = document.querySelectorAll('.test-item-card');
    cards.forEach(card => {
        const name = (card.getAttribute('data-name') || '').toLowerCase();
        const dept = (card.getAttribute('data-dept') || '').toLowerCase();
        if (name.indexOf(q) !== -1 || dept.indexOf(q) !== -1) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

function handleTestToggle(checkboxEl) {
    const tName = checkboxEl.getAttribute('data-name');
    const tPrice = parseFloat(checkboxEl.getAttribute('data-price') || '0');
    const tDept = checkboxEl.getAttribute('data-dept-name') || '';

    if (checkboxEl.checked) {
        if (!currentSelectedTests.some(t => t.name === tName)) {
            currentSelectedTests.push({ name: tName, price: tPrice, dept: tDept });
        }
    } else {
        currentSelectedTests = currentSelectedTests.filter(t => t.name !== tName);
    }

    renderManifestItems();
    calculateBillingTotals();
}

function removeTestFromManifest(tName) {
    currentSelectedTests = currentSelectedTests.filter(t => t.name !== tName);
    // Uncheck in catalog
    const cb = document.querySelector('.test-checkbox[data-name="' + CSS.escape(tName) + '"]');
    if (cb) cb.checked = false;

    renderManifestItems();
    calculateBillingTotals();
}

function renderManifestItems() {
    const listEl = document.getElementById('manifest-items-list');
    const badgeEl = document.getElementById('manifest-count-badge');
    if (!listEl) return;

    if (badgeEl) {
        badgeEl.textContent = currentSelectedTests.length + (currentSelectedTests.length === 1 ? ' Test' : ' Tests');
    }

    if (currentSelectedTests.length === 0) {
        listEl.innerHTML = `
            <div id="empty-manifest-hint" class="py-6 text-center text-slate-400 italic text-xs">
                <i class="fa-solid fa-cart-flatbed text-2xl mb-1 text-slate-600 block"></i>
                No tests selected yet.<br>Click tests from the catalog on the left.
            </div>`;
        return;
    }

    let html = '';
    currentSelectedTests.forEach(t => {
        html += `
        <div class="py-2 flex items-center justify-between gap-2 text-xs">
            <div class="min-w-0">
                <div class="font-bold text-white truncate text-xs">\${t.name}</div>
                <span class="text-[10px] text-slate-400">\${t.dept} &middot; Rs. \${Math.round(t.price).toLocaleString()}</span>
            </div>
            <button type="button" onclick="removeTestFromManifest('\${t.name}')" class="text-slate-500 hover:text-rose-400 p-1 text-xs shrink-0" title="Remove">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>`;
    });

    listEl.innerHTML = html;
}

function calculateBillingTotals() {
    let gross = 0;
    currentSelectedTests.forEach(t => {
        gross += t.price;
    });

    const discInput = document.getElementById('billing-discount');
    const paidInput = document.getElementById('billing-paid');
    const discount = discInput ? Math.max(0, parseFloat(discInput.value) || 0) : 0;
    const net = Math.max(0, gross - discount);

    // If paid input hasn't been manually set or matches previous gross, default to net
    if (paidInput && (paidInput.dataset.manual !== 'true' || parseFloat(paidInput.value) === 0)) {
        paidInput.value = net;
    }

    const paid = paidInput ? Math.max(0, parseFloat(paidInput.value) || 0) : 0;
    const due = Math.max(0, net - paid);

    const grossEl = document.getElementById('calc-gross-disp');
    const netEl = document.getElementById('calc-net-disp');
    const dueEl = document.getElementById('calc-due-disp');
    const hiddenGross = document.getElementById('hidden-gross');

    if (grossEl) grossEl.textContent = 'Rs. ' + Math.round(gross).toLocaleString();
    if (netEl) netEl.textContent = 'Rs. ' + Math.round(net).toLocaleString();
    if (dueEl) dueEl.textContent = 'Rs. ' + Math.round(due).toLocaleString();
    if (hiddenGross) hiddenGross.value = gross;
}

// Track manual editing of paid input
document.addEventListener('DOMContentLoaded', function() {
    const paidInput = document.getElementById('billing-paid');
    if (paidInput) {
        paidInput.addEventListener('input', function() {
            this.dataset.manual = 'true';
            calculateBillingTotals();
        });
    }
});
</script>
HTML;

render_page('New Booking (Unified Slip)', 'imaging', 'new-scan', $content);
