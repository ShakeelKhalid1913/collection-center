<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';
require_once __DIR__ . '/../../includes/documents.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$tab = $_GET['tab'] ?? 'blood_proforma';

$settings = branding_settings($orgId);
$headerHtml = render_branded_header($settings, true);
$footerHtml = render_branded_footer($settings, true);

$patientName = htmlspecialchars($_GET['name'] ?? 'Muhammad Aslam');
$patientMr = htmlspecialchars($_GET['mr'] ?? 'MR-' . date('y') . '-8821');
$patientAge = htmlspecialchars($_GET['age'] ?? '42');
$patientGender = htmlspecialchars($_GET['gender'] ?? 'Male');
$patientBed = htmlspecialchars($_GET['bed'] ?? 'Bed-04 (IPD / Ward)');
$doctor = htmlspecialchars($_GET['doctor'] ?? 'Dr. Imran Sheikh');
$dateToday = date('d-M-Y');

// Tab 1: Blood Transfusion Proforma
$bloodProformaHtml = <<<HTML
<div class="space-y-4">
    <div class="border-y-2 border-slate-900 py-2 flex items-center justify-between">
        <span class="text-xs font-black uppercase tracking-wider bg-red-700 text-white px-2.5 py-0.5 rounded">HOSPITAL FILE: BLOOD TRANSFUSION PROFORMA</span>
        <span class="text-xs font-semibold text-slate-600">Date: <strong class="text-slate-900">{$dateToday}</strong></span>
    </div>

    <!-- Patient Header Box -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs">
        <div><span class="text-slate-400 font-bold uppercase text-[10px] block">Patient Name</span><strong>{$patientName}</strong></div>
        <div><span class="text-slate-400 font-bold uppercase text-[10px] block">MR No / Ward</span><span class="font-mono font-bold">{$patientMr}</span> &middot; {$patientBed}</div>
        <div><span class="text-slate-400 font-bold uppercase text-[10px] block">Age / Gender</span>{$patientAge} Y / {$patientGender}</div>
        <div><span class="text-slate-400 font-bold uppercase text-[10px] block">Consultant Physician</span><strong>{$doctor}</strong></div>
    </div>

    <!-- Blood Compatibility Table -->
    <div class="border border-slate-200 rounded-xl overflow-hidden text-xs">
        <div class="bg-red-50 p-2.5 font-bold text-red-900 border-b border-red-100 flex items-center justify-between">
            <span><i class="fa-solid fa-droplet text-red-600 mr-1.5"></i> Blood Bag &amp; Cross-Match Record</span>
            <span class="text-[11px] font-normal text-red-700">Screening mandatory before release</span>
        </div>
        <table class="w-full text-left border-collapse">
            <tbody class="divide-y divide-slate-100">
                <tr class="bg-white">
                    <td class="p-2.5 font-bold text-slate-600 w-1/4">Recipient Blood Group:</td>
                    <td class="p-2.5 font-mono font-extrabold text-red-700 text-sm">B Positive (B +ve)</td>
                    <td class="p-2.5 font-bold text-slate-600 w-1/4">Blood Component:</td>
                    <td class="p-2.5 font-bold text-slate-800">Packed Red Blood Cells (PRBC)</td>
                </tr>
                <tr class="bg-slate-50/50">
                    <td class="p-2.5 font-bold text-slate-600">Donor Blood Bag No:</td>
                    <td class="p-2.5 font-mono font-bold text-slate-900">BAG-2026-0941</td>
                    <td class="p-2.5 font-bold text-slate-600">Donor Blood Group:</td>
                    <td class="p-2.5 font-mono font-extrabold text-red-700 text-sm">B Positive (B +ve)</td>
                </tr>
                <tr class="bg-white">
                    <td class="p-2.5 font-bold text-slate-600">Cross-Match Method:</td>
                    <td class="p-2.5 font-semibold text-emerald-800">Gel Card AHG (Compatible)</td>
                    <td class="p-2.5 font-bold text-slate-600">Viral Markers (HBs, HCV, HIV):</td>
                    <td class="p-2.5 font-bold text-emerald-700">Non-Reactive (Negative)</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Transfusion Monitoring Log -->
    <div class="border border-slate-200 rounded-xl overflow-hidden text-xs">
        <div class="bg-slate-100 p-2 font-bold text-slate-800 border-b border-slate-200">
            Vital Signs &amp; Adverse Reaction Monitoring During Transfusion
        </div>
        <table class="w-full text-center border-collapse">
            <thead>
                <tr class="bg-slate-50 text-[10px] font-extrabold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                    <th class="p-2">Stage</th>
                    <th class="p-2">Time</th>
                    <th class="p-2">BP (mmHg)</th>
                    <th class="p-2">Pulse (/min)</th>
                    <th class="p-2">Temp (°F)</th>
                    <th class="p-2">Respiration</th>
                    <th class="p-2">Adverse Signs</th>
                    <th class="p-2">Staff Sign</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                <tr><td class="p-2 font-sans font-bold text-left">Pre-Transfusion</td><td>10:00 AM</td><td>120/80</td><td>76</td><td>98.4</td><td>18</td><td class="text-emerald-700 font-sans font-semibold">None</td><td class="font-sans">Sr. Maryam</td></tr>
                <tr><td class="p-2 font-sans font-bold text-left">At 15 Minutes</td><td>10:15 AM</td><td>125/82</td><td>80</td><td>98.6</td><td>18</td><td class="text-emerald-700 font-sans font-semibold">None</td><td class="font-sans">Sr. Maryam</td></tr>
                <tr><td class="p-2 font-sans font-bold text-left">At 1 Hour</td><td>11:00 AM</td><td>120/80</td><td>78</td><td>98.6</td><td>17</td><td class="text-emerald-700 font-sans font-semibold">None</td><td class="font-sans">Sr. Maryam</td></tr>
                <tr><td class="p-2 font-sans font-bold text-left">Post-Transfusion</td><td>01:30 PM</td><td>122/80</td><td>74</td><td>98.4</td><td>16</td><td class="text-emerald-700 font-sans font-semibold">Uneventful</td><td class="font-sans">Sr. Maryam</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Signatures -->
    <div class="grid grid-cols-2 gap-8 pt-8 mt-6 border-t border-slate-200 text-xs">
        <div class="text-center w-56 border-t border-slate-400 pt-1">
            <strong>Medical Officer / Resident Doctor</strong>
            <span class="text-[10px] text-slate-400 block">Checked &amp; Transfusion Started</span>
        </div>
        <div class="text-center w-56 ml-auto border-t border-slate-400 pt-1">
            <strong>Staff Nurse / Phlebotomist</strong>
            <span class="text-[10px] text-slate-400 block">Verified &amp; Infused</span>
        </div>
    </div>
</div>
HTML;

// Tab 2: Medicine Administration Chart
$medicineChartHtml = <<<HTML
<div class="space-y-4">
    <div class="border-y-2 border-slate-900 py-2 flex items-center justify-between">
        <span class="text-xs font-black uppercase tracking-wider bg-teal-800 text-white px-2.5 py-0.5 rounded">HOSPITAL FILE: INPATIENT MEDICATION ADMINISTRATION CHART (MAR)</span>
        <span class="text-xs font-semibold text-slate-600">Admission Date: <strong class="text-slate-900">{$dateToday}</strong></span>
    </div>

    <!-- Patient Header Box -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs">
        <div><span class="text-slate-400 font-bold uppercase text-[10px] block">Patient Name</span><strong>{$patientName}</strong></div>
        <div><span class="text-slate-400 font-bold uppercase text-[10px] block">MR No / Ward</span><span class="font-mono font-bold">{$patientMr}</span> &middot; {$patientBed}</div>
        <div><span class="text-slate-400 font-bold uppercase text-[10px] block">Age / Gender</span>{$patientAge} Y / {$patientGender}</div>
        <div><span class="text-slate-400 font-bold uppercase text-[10px] block">Allergies</span><span class="text-rose-700 font-extrabold">NKDA (No Known Drug Allergies)</span></div>
    </div>

    <!-- Medication Prescription & Dose Log -->
    <div class="border border-slate-200 rounded-xl overflow-hidden text-xs">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-100 text-[10px] font-extrabold text-slate-600 uppercase tracking-wider border-b border-slate-200">
                    <th class="p-2.5 w-1/4">Drug Name &amp; Strength</th>
                    <th class="p-2.5 w-16">Dose</th>
                    <th class="p-2.5 w-16">Route</th>
                    <th class="p-2.5 w-24">Frequency</th>
                    <th class="p-2.5 text-center">Morning (08:00)</th>
                    <th class="p-2.5 text-center">Afternoon (14:00)</th>
                    <th class="p-2.5 text-center">Evening (20:00)</th>
                    <th class="p-2.5 text-center">Night (02:00)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr class="hover:bg-slate-50">
                    <td class="p-2.5 font-bold text-slate-800">Inj. Ceftriaxone 1g</td>
                    <td class="p-2.5 font-mono">1 vial</td>
                    <td class="p-2.5 font-semibold text-blue-700">IV</td>
                    <td class="p-2.5">12 Hourly (BD)</td>
                    <td class="p-2.5 text-center font-bold text-emerald-700">✓ Given</td>
                    <td class="p-2.5 text-center text-slate-300">—</td>
                    <td class="p-2.5 text-center font-bold text-emerald-700">✓ Given</td>
                    <td class="p-2.5 text-center text-slate-300">—</td>
                </tr>
                <tr class="hover:bg-slate-50">
                    <td class="p-2.5 font-bold text-slate-800">Inj. Omeprazole 40mg</td>
                    <td class="p-2.5 font-mono">40 mg</td>
                    <td class="p-2.5 font-semibold text-blue-700">IV</td>
                    <td class="p-2.5">Once Daily (OD)</td>
                    <td class="p-2.5 text-center font-bold text-emerald-700">✓ Given</td>
                    <td class="p-2.5 text-center text-slate-300">—</td>
                    <td class="p-2.5 text-center text-slate-300">—</td>
                    <td class="p-2.5 text-center text-slate-300">—</td>
                </tr>
                <tr class="hover:bg-slate-50">
                    <td class="p-2.5 font-bold text-slate-800">Inf. Paracetamol 1000mg</td>
                    <td class="p-2.5 font-mono">100 ml</td>
                    <td class="p-2.5 font-semibold text-blue-700">IV Drip</td>
                    <td class="p-2.5">8 Hourly (TDS)</td>
                    <td class="p-2.5 text-center font-bold text-emerald-700">✓ Given</td>
                    <td class="p-2.5 text-center font-bold text-emerald-700">✓ Given</td>
                    <td class="p-2.5 text-center font-bold text-emerald-700">✓ Given</td>
                    <td class="p-2.5 text-center text-slate-300">—</td>
                </tr>
                <tr class="hover:bg-slate-50">
                    <td class="p-2.5 font-bold text-slate-800">Tab. Metformin 500mg</td>
                    <td class="p-2.5 font-mono">1 Tab</td>
                    <td class="p-2.5 font-semibold text-purple-700">Oral (PO)</td>
                    <td class="p-2.5">With Meals (BD)</td>
                    <td class="p-2.5 text-center font-bold text-emerald-700">✓ Given</td>
                    <td class="p-2.5 text-center text-slate-300">—</td>
                    <td class="p-2.5 text-center font-bold text-emerald-700">✓ Given</td>
                    <td class="p-2.5 text-center text-slate-300">—</td>
                </tr>
                <tr class="hover:bg-slate-50">
                    <td class="p-2.5 font-bold text-slate-800">Tab. Aspirin (Disprin) 75mg</td>
                    <td class="p-2.5 font-mono">1 Tab</td>
                    <td class="p-2.5 font-semibold text-purple-700">Oral (PO)</td>
                    <td class="p-2.5">Once Daily (OD)</td>
                    <td class="p-2.5 text-center text-slate-300">—</td>
                    <td class="p-2.5 text-center font-bold text-emerald-700">✓ Given</td>
                    <td class="p-2.5 text-center text-slate-300">—</td>
                    <td class="p-2.5 text-center text-slate-300">—</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Signatures -->
    <div class="grid grid-cols-2 gap-8 pt-8 mt-6 border-t border-slate-200 text-xs">
        <div class="text-center w-56 border-t border-slate-400 pt-1">
            <strong>Consultant / Prescribing Doctor</strong>
            <span class="text-[10px] text-slate-400 block">Orders Reviewed &amp; Signed</span>
        </div>
        <div class="text-center w-56 ml-auto border-t border-slate-400 pt-1">
            <strong>Head Ward Nurse</strong>
            <span class="text-[10px] text-slate-400 block">Administration Verified</span>
        </div>
    </div>
</div>
HTML;

// Tab 3: Vital Signs & Viral Chart
$vitalViralChartHtml = <<<HTML
<div class="space-y-4">
    <div class="border-y-2 border-slate-900 py-2 flex items-center justify-between">
        <span class="text-xs font-black uppercase tracking-wider bg-indigo-900 text-white px-2.5 py-0.5 rounded">HOSPITAL FILE: VITALS &amp; VIRAL INFECTION SCREENING CHART</span>
        <span class="text-xs font-semibold text-slate-600">Observation Date: <strong class="text-slate-900">{$dateToday}</strong></span>
    </div>

    <!-- Patient Header Box -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs">
        <div><span class="text-slate-400 font-bold uppercase text-[10px] block">Patient Name</span><strong>{$patientName}</strong></div>
        <div><span class="text-slate-400 font-bold uppercase text-[10px] block">MR No / Ward</span><span class="font-mono font-bold">{$patientMr}</span> &middot; {$patientBed}</div>
        <div><span class="text-slate-400 font-bold uppercase text-[10px] block">Age / Gender</span>{$patientAge} Y / {$patientGender}</div>
        <div><span class="text-slate-400 font-bold uppercase text-[10px] block">Attending Consultant</span><strong>{$doctor}</strong></div>
    </div>

    <!-- Viral Markers Screening Panel -->
    <div class="border border-slate-200 rounded-xl overflow-hidden text-xs">
        <div class="bg-indigo-50 p-2.5 font-bold text-indigo-900 border-b border-indigo-100 flex items-center justify-between">
            <span><i class="fa-solid fa-virus mr-1.5 text-indigo-600"></i> Pre-Admission Viral Infection Screening Markers (ICT / ELISA)</span>
            <span class="text-[10px] text-indigo-700 uppercase font-semibold">Laboratory Diagnostic Record</span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-200 p-3 bg-white text-center">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase block">Hepatitis B (HBsAg)</span>
                <span class="text-sm font-extrabold text-emerald-700">NEGATIVE (Non-Reactive)</span>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase block">Hepatitis C (Anti-HCV)</span>
                <span class="text-sm font-extrabold text-emerald-700">NEGATIVE (Non-Reactive)</span>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase block">HIV I &amp; II Antibodies</span>
                <span class="text-sm font-extrabold text-emerald-700">NEGATIVE (Non-Reactive)</span>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase block">Syphilis (VDRL / TPHA)</span>
                <span class="text-sm font-extrabold text-emerald-700">NON-REACTIVE</span>
            </div>
        </div>
    </div>

    <!-- Multi-Time Vitals Chart -->
    <div class="border border-slate-200 rounded-xl overflow-hidden text-xs">
        <div class="bg-slate-100 p-2 font-bold text-slate-800 border-b border-slate-200">
            24-Hour Patient Vitals &amp; Glucose Monitoring Log
        </div>
        <table class="w-full text-center border-collapse">
            <thead>
                <tr class="bg-slate-50 text-[10px] font-extrabold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                    <th class="p-2 text-left">Time</th>
                    <th class="p-2">BP (mmHg)</th>
                    <th class="p-2">Pulse (/min)</th>
                    <th class="p-2">Temp (°F)</th>
                    <th class="p-2">SpO2 (%)</th>
                    <th class="p-2">Blood Sugar (RBS)</th>
                    <th class="p-2">Urine Output</th>
                    <th class="p-2">Staff Sign</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                <tr><td class="p-2 font-sans font-bold text-left">08:00 AM</td><td>125/80</td><td>76</td><td>98.6</td><td>98%</td><td>138 mg/dL</td><td>250 ml</td><td class="font-sans">Staff Nurse</td></tr>
                <tr><td class="p-2 font-sans font-bold text-left">12:00 PM</td><td>120/78</td><td>74</td><td>98.4</td><td>99%</td><td>—</td><td>300 ml</td><td class="font-sans">Staff Nurse</td></tr>
                <tr><td class="p-2 font-sans font-bold text-left">04:00 PM</td><td>130/82</td><td>82</td><td>99.1</td><td>97%</td><td>155 mg/dL</td><td>200 ml</td><td class="font-sans">Staff Nurse</td></tr>
                <tr><td class="p-2 font-sans font-bold text-left">08:00 PM</td><td>122/80</td><td>78</td><td>98.6</td><td>98%</td><td>—</td><td>350 ml</td><td class="font-sans">Staff Nurse</td></tr>
                <tr><td class="p-2 font-sans font-bold text-left">12:00 AM</td><td>118/75</td><td>72</td><td>98.4</td><td>99%</td><td>122 mg/dL</td><td>280 ml</td><td class="font-sans">Staff Nurse</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Signatures -->
    <div class="grid grid-cols-2 gap-8 pt-8 mt-6 border-t border-slate-200 text-xs">
        <div class="text-center w-56 border-t border-slate-400 pt-1">
            <strong>Resident Medical Officer (RMO)</strong>
            <span class="text-[10px] text-slate-400 block">Vitals Signed &amp; Approved</span>
        </div>
        <div class="text-center w-56 ml-auto border-t border-slate-400 pt-1">
            <strong>Head Ward Sister</strong>
            <span class="text-[10px] text-slate-400 block">Observation Verified</span>
        </div>
    </div>
</div>
HTML;

$activeContent = match ($tab) {
    'medicine_chart' => $medicineChartHtml,
    'viral_chart' => $vitalViralChartHtml,
    default => $bloodProformaHtml,
};

$tabBloodActive = $tab === 'blood_proforma' ? 'bg-slate-900 text-white font-extrabold' : 'text-slate-600 hover:text-slate-900 bg-white border border-slate-200';
$tabMedActive = $tab === 'medicine_chart' ? 'bg-slate-900 text-white font-extrabold' : 'text-slate-600 hover:text-slate-900 bg-white border border-slate-200';
$tabViralActive = $tab === 'viral_chart' ? 'bg-slate-900 text-white font-extrabold' : 'text-slate-600 hover:text-slate-900 bg-white border border-slate-200';

$content = <<<HTML
<div class="space-y-6">
    <!-- Top Action Ribbon (No print) -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-2xs flex flex-wrap items-center justify-between gap-3 no-print">
        <div class="flex flex-wrap items-center gap-2">
            <a href="?tab=blood_proforma" class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all {$tabBloodActive}">
                <i class="fa-solid fa-droplet text-red-500 mr-1"></i> Blood Transfusion Proforma
            </a>
            <a href="?tab=medicine_chart" class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all {$tabMedActive}">
                <i class="fa-solid fa-pills text-teal-500 mr-1"></i> Medication Chart (MAR)
            </a>
            <a href="?tab=viral_chart" class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all {$tabViralActive}">
                <i class="fa-solid fa-heart-pulse text-indigo-500 mr-1"></i> Vitals &amp; Viral Chart
            </a>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="btn btn-primary px-4 py-1.5 text-xs font-extrabold flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-print"></i> Print Hospital File
            </button>
        </div>
    </div>

    <!-- Official Printable Document Container -->
    <div class="bg-white rounded-3xl shadow-xl border border-slate-200 p-6 sm:p-10 max-w-4xl mx-auto print-container font-sans text-slate-800">
        {$headerHtml}
        {$activeContent}
        {$footerHtml}
    </div>
</div>
HTML;

render_page('Clinical Hospital Files', 'imaging', 'hospital-files', $content);
