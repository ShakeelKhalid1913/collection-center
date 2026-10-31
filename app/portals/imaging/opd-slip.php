<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';
require_once __DIR__ . '/../../includes/documents.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$message = '';

$settings = branding_settings($orgId);
$doctors = mock('mock_doctors');
$patients = patient_repo()->getAll($orgId, 50);

$slipNo = 'OPD-' . date('ymd') . '-' . rand(100, 999);
$dateStr = date('d-M-Y h:i A');

// Handle form submission / generation
$printedSlip = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_opd') {
    $patientName = trim((string)($_POST['patient_name'] ?? 'Walk-in Patient'));
    $patientAge = trim((string)($_POST['age'] ?? ''));
    $patientGender = trim((string)($_POST['gender'] ?? 'Male'));
    $patientPhone = trim((string)($_POST['phone'] ?? ''));
    $patientMr = trim((string)($_POST['mr_no'] ?? ('MR-' . rand(10000, 99999))));

    $docSelect = trim((string)($_POST['doctor_id'] ?? ''));
    $customDoc = trim((string)($_POST['custom_doctor'] ?? ''));
    $doctorName = $customDoc !== '' ? $customDoc : resolve_doctor_name($docSelect);

    $dept = trim((string)($_POST['department'] ?? 'General OPD'));
    $fee = (float)($_POST['fee'] ?? 1000);
    $discount = (float)($_POST['discount'] ?? 0);
    $netFee = max(0, $fee - $discount);
    $payMode = trim((string)($_POST['pay_mode'] ?? 'Cash'));

    $bp = trim((string)($_POST['vital_bp'] ?? ''));
    $pulse = trim((string)($_POST['vital_pulse'] ?? ''));
    $temp = trim((string)($_POST['vital_temp'] ?? ''));
    $weight = trim((string)($_POST['vital_weight'] ?? ''));
    $complaints = trim((string)($_POST['complaints'] ?? ''));

    $printedSlip = [
        'slip_no' => $slipNo,
        'date' => $dateStr,
        'patient_name' => $patientName,
        'mr_no' => $patientMr,
        'age' => $patientAge,
        'gender' => $patientGender,
        'phone' => $patientPhone,
        'doctor' => $doctorName,
        'dept' => $dept,
        'fee' => $fee,
        'discount' => $discount,
        'net' => $netFee,
        'pay_mode' => $payMode,
        'bp' => $bp,
        'pulse' => $pulse,
        'temp' => $temp,
        'weight' => $weight,
        'complaints' => $complaints,
    ];

    audit_log('GENERATE_OPD_SLIP', 'opd', $slipNo, "Generated OPD Slip for {$patientName} with {$doctorName} (Rs. {$netFee})");
}

// Doctor dropdown options
$docOptsHtml = '';
foreach ($doctors as $d) {
    $spec = !empty($d['specialty']) ? ' — ' . $d['specialty'] : '';
    $docOptsHtml .= '<option value="' . e($d['name']) . '">' . e($d['name'] . $spec) . '</option>';
}

$printBoxHtml = '';
if ($printedSlip !== null) {
    $printHeader = render_branded_header($settings, true);
    $printFooter = render_branded_footer($settings, true);

    $printBoxHtml = <<<HTML
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center justify-between no-print">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-lg"><i class="fa-solid fa-check"></i></span>
            <div>
                <strong class="text-emerald-900 block text-sm">OPD Consultation Slip Generated (#{$printedSlip['slip_no']})</strong>
                <span class="text-xs text-emerald-700">Patient: {$printedSlip['patient_name']} &middot; Doctor: {$printedSlip['doctor']} &middot; Fee: Rs. {$printedSlip['net']}</span>
            </div>
        </div>
        <button onclick="window.print()" class="btn btn-primary px-4 py-2 text-xs font-bold shadow-md flex items-center gap-2">
            <i class="fa-solid fa-print"></i> Print OPD Slip (A4 / Thermal)
        </button>
    </div>

    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-6 sm:p-8 max-w-4xl mx-auto mb-8 print-container font-sans text-slate-800">
        <!-- Letterhead Header -->
        {$printHeader}

        <!-- Slip Header Ribbon -->
        <div class="border-y-2 border-slate-900 py-2.5 my-3 flex items-center justify-between">
            <div>
                <span class="text-xs font-black uppercase tracking-wider bg-slate-900 text-white px-2.5 py-0.5 rounded">OPD CONSULTATION TOKEN</span>
                <span class="text-xs font-bold text-slate-600 ml-2">Slip No: <strong class="font-mono text-slate-900">{$printedSlip['slip_no']}</strong></span>
            </div>
            <div class="text-xs font-semibold text-slate-600">
                Date &amp; Time: <span class="font-mono text-slate-900">{$printedSlip['date']}</span>
            </div>
        </div>

        <!-- Patient & Consultant Meta Grid -->
        <div class="grid grid-cols-2 gap-4 bg-slate-50 p-3.5 rounded-xl border border-slate-200 text-xs">
            <div>
                <p><span class="text-slate-400 font-bold uppercase text-[10px]">Patient Name:</span> <strong class="text-slate-900 text-sm">{$printedSlip['patient_name']}</strong></p>
                <p class="mt-1"><span class="text-slate-400 font-bold uppercase text-[10px]">MR Number:</span> <span class="font-mono font-bold text-slate-800">{$printedSlip['mr_no']}</span> &middot; <span class="text-slate-400 font-bold uppercase text-[10px]">Age / Sex:</span> <span class="font-bold">{$printedSlip['age']} / {$printedSlip['gender']}</span></p>
                <p class="mt-1"><span class="text-slate-400 font-bold uppercase text-[10px]">Contact:</span> <span class="font-mono">{$printedSlip['phone']}</span></p>
            </div>
            <div class="text-right">
                <p><span class="text-slate-400 font-bold uppercase text-[10px]">Consultant Doctor:</span> <strong class="text-teal-900 text-sm block">{$printedSlip['doctor']}</strong></p>
                <p class="mt-1"><span class="text-slate-400 font-bold uppercase text-[10px]">Specialty / Dept:</span> <span class="font-bold text-slate-700">{$printedSlip['dept']}</span></p>
                <p class="mt-1 font-bold text-emerald-800">Fee: Rs. {$printedSlip['net']} <span class="text-[10px] text-slate-500 font-normal">({$printedSlip['pay_mode']})</span></p>
            </div>
        </div>

        <!-- Patient Vitals Bar -->
        <div class="grid grid-cols-4 gap-2 my-3 p-2.5 bg-blue-50/50 rounded-lg border border-blue-100 text-center text-xs">
            <div><span class="text-[10px] text-blue-600 font-bold uppercase block">Blood Pressure (BP)</span> <strong class="text-slate-800">{$printedSlip['bp']}</strong></div>
            <div><span class="text-[10px] text-blue-600 font-bold uppercase block">Pulse Rate</span> <strong class="text-slate-800">{$printedSlip['pulse']}</strong></div>
            <div><span class="text-[10px] text-blue-600 font-bold uppercase block">Temperature</span> <strong class="text-slate-800">{$printedSlip['temp']}</strong></div>
            <div><span class="text-[10px] text-blue-600 font-bold uppercase block">Weight (kg)</span> <strong class="text-slate-800">{$printedSlip['weight']}</strong></div>
        </div>

        <!-- Doctor Clinical Notes & Rx Pad -->
        <div class="border-t border-slate-300 pt-3 mt-4 min-h-[380px] flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-xs font-bold text-slate-400 mb-2 border-b border-dashed border-slate-200 pb-1">
                    <span><i class="fa-solid fa-stethoscope text-teal-600 mr-1"></i> CLINICAL ASSESSMENT / DIAGNOSIS</span>
                    <span class="text-2xl font-serif font-black text-slate-900">℞</span>
                </div>
                <div class="text-xs text-slate-700 italic">
                    {$printedSlip['complaints']}
                </div>
            </div>

            <!-- Doctor Signature Block -->
            <div class="flex justify-between items-end pt-12 text-xs">
                <div class="text-[10px] text-slate-400">
                    <p>Valid for follow-up within 7 days with this slip.</p>
                    <p>Computer-generated consultation token &middot; Lab Dash Pro</p>
                </div>
                <div class="text-center w-56 border-t border-slate-400 pt-1">
                    <strong class="block text-slate-800">{$printedSlip['doctor']}</strong>
                    <span class="text-[10px] text-slate-500">Consultant Physician Signature</span>
                </div>
            </div>
        </div>

        <!-- Letterhead Footer -->
        {$printFooter}
    </div>
HTML;
}

$content = <<<HTML
<div class="space-y-6">
    {$printBoxHtml}

    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-6 no-print">
        <div class="flex items-center gap-3 border-b border-slate-100 pb-4 mb-6">
            <span class="w-10 h-10 rounded-2xl bg-teal-100 text-teal-700 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-user-doctor"></i>
            </span>
            <div>
                <h2 class="text-lg font-black text-slate-900 tracking-tight">Generate Doctor OPD Consultation Slip</h2>
                <p class="text-xs text-slate-500">Create official patient checkup slip, doctor selection, vitals, and prescription token</p>
            </div>
        </div>

        <form method="post" class="space-y-6">
            <input type="hidden" name="action" value="generate_opd">

            <!-- Section 1: Patient Details -->
            <div>
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-3"><i class="fa-solid fa-user mr-1 text-teal-600"></i> Patient Demographics</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label class="field-label" for="patient_name">Patient Full Name *</label>
                        <input type="text" id="patient_name" name="patient_name" required class="field text-xs font-bold" placeholder="e.g. Muhammad Bilal">
                    </div>
                    <div>
                        <label class="field-label" for="mr_no">MR / Card No</label>
                        <input type="text" id="mr_no" name="mr_no" class="field text-xs font-mono" placeholder="MR-29381">
                    </div>
                    <div>
                        <label class="field-label" for="age">Age</label>
                        <input type="number" id="age" name="age" class="field text-xs" placeholder="35">
                    </div>
                    <div>
                        <label class="field-label" for="gender">Gender</label>
                        <select id="gender" name="gender" class="field text-xs font-semibold">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="field-label" for="phone">Contact Phone / WhatsApp</label>
                        <input type="text" id="phone" name="phone" class="field text-xs" placeholder="+92 300 1234567">
                    </div>
                    <div>
                        <label class="field-label" for="complaints">Chief Complaints / Presenting Symptoms</label>
                        <input type="text" id="complaints" name="complaints" class="field text-xs" placeholder="e.g. Chest discomfort, palpitations, fever for 3 days">
                    </div>
                </div>
            </div>

            <!-- Section 2: Consultant Doctor & Department -->
            <div class="border-t border-slate-100 pt-5">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-3"><i class="fa-solid fa-user-doctor mr-1 text-indigo-600"></i> Consultant Doctor &amp; Specialty</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="field-label" for="doctor_id">Select Doctor *</label>
                        <select id="doctor_id" name="doctor_id" class="field text-xs font-bold">
                            {$docOptsHtml}
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="custom_doctor">Or Type New / Visiting Doctor</label>
                        <input type="text" id="custom_doctor" name="custom_doctor" class="field text-xs" placeholder="Dr. First Last, Specialist">
                    </div>
                    <div>
                        <label class="field-label" for="department">Clinic Room / Specialty</label>
                        <select id="department" name="department" class="field text-xs">
                            <option value="General OPD">General OPD / Physician</option>
                            <option value="Cardiology Clinic">Cardiology Clinic (ECG/Echo)</option>
                            <option value="Gynecology &amp; Obstetrics">Gynecology &amp; Obstetrics</option>
                            <option value="Radiology &amp; Ultrasound">Radiology &amp; Ultrasound</option>
                            <option value="Pediatrics Clinic">Pediatrics Clinic</option>
                            <option value="Orthopedic Clinic">Orthopedic Clinic</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 3: Vitals & Fees -->
            <div class="border-t border-slate-100 pt-5">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-3"><i class="fa-solid fa-heart-pulse mr-1 text-rose-600"></i> Patient Clinical Vitals &amp; Billing</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                    <div>
                        <label class="field-label" for="vital_bp">Blood Pressure (BP)</label>
                        <input type="text" id="vital_bp" name="vital_bp" class="field text-xs font-mono" placeholder="120/80 mmHg">
                    </div>
                    <div>
                        <label class="field-label" for="vital_pulse">Pulse Rate (bpm)</label>
                        <input type="text" id="vital_pulse" name="vital_pulse" class="field text-xs font-mono" placeholder="78 bpm">
                    </div>
                    <div>
                        <label class="field-label" for="vital_temp">Temperature</label>
                        <input type="text" id="vital_temp" name="vital_temp" class="field text-xs font-mono" placeholder="98.6 °F">
                    </div>
                    <div>
                        <label class="field-label" for="vital_weight">Weight (kg)</label>
                        <input type="text" id="vital_weight" name="vital_weight" class="field text-xs font-mono" placeholder="68 kg">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="field-label" for="fee">Consultation Fee (PKR)</label>
                        <input type="number" id="fee" name="fee" value="1000" class="field text-xs font-bold font-mono">
                    </div>
                    <div>
                        <label class="field-label" for="discount">Discount (PKR)</label>
                        <input type="number" id="discount" name="discount" value="0" class="field text-xs font-mono">
                    </div>
                    <div>
                        <label class="field-label" for="pay_mode">Payment Mode</label>
                        <select id="pay_mode" name="pay_mode" class="field text-xs font-semibold">
                            <option value="Cash">Cash</option>
                            <option value="JazzCash / EasyPaisa">JazzCash / EasyPaisa</option>
                            <option value="Bank / Card">Bank / Credit Card</option>
                            <option value="Complimentary / Free">Complimentary / Free</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <button type="submit" class="btn btn-primary px-6 py-2.5 text-xs font-extrabold shadow-md flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice"></i> Generate &amp; Print OPD Slip
                </button>
            </div>
        </form>
    </div>
</div>
HTML;

render_page('Doctor OPD Slip', 'imaging', 'opd-slip', $content);
