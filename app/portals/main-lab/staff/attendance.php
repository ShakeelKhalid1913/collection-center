<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';
require_once __DIR__ . '/../../../includes/documents.php';

require_auth();

$user = current_user();
$orgId = $user['organization_id'] ?? 'ORG-001';
$settings = branding_settings($orgId);
$message = '';

// Handle Clock In / Clock Out Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'clock_punch') {
        $empId = trim((string)($_POST['employee_id'] ?? ''));
        $punchType = strtolower(trim((string)($_POST['punch_type'] ?? 'in')));
        $photoBase64 = $_POST['photo_base64'] ?? null;
        $photoFile = $_FILES['photo_file'] ?? null;
        $punchTime = trim((string)($_POST['punch_time'] ?? date('H:i:s')));
        $punchDate = trim((string)($_POST['punch_date'] ?? date('Y-m-d')));

        if ($empId === '') {
            $message = flash_error('Please select an employee before punching attendance.');
        } else {
            $res = employee_repo()->recordClockPunch(
                $empId,
                $punchType,
                $punchTime,
                $photoBase64,
                $photoFile,
                $orgId,
                $punchDate
            );
            $message = $res['ok'] ? flash_success($res['message']) : flash_error($res['error'] ?? 'Could not record attendance punch.');
        }
    }
}

// Filter parameters for monthly history
$selectedMonth = trim((string)($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}
$selectedEmpId = trim((string)($_GET['emp_id'] ?? ''));

// Fetch all active employees
$employees = employee_repo()->getAll($orgId);

// Build employee metadata JSON for client-side selection
$empMap = [];
$empSelectOptions = '<option value="">— Select Employee —</option>';
foreach ($employees as $e) {
    $eId = (string)$e['id'];
    $empNo = (string)($e['emp_no'] ?? $e['emp_code'] ?? 'W-001');
    $name = (string)$e['full_name'];
    $dept = (string)$e['department'];
    $desig = (string)$e['designation'];
    $hasPhoto = !empty($e['has_photo']);
    $photoUrl = $hasPhoto ? '/portals/main-lab/staff/photo.php?id=' . urlencode($eId) : '';

    $empMap[$eId] = [
        'id' => $eId,
        'code' => $empNo,
        'name' => $name,
        'dept' => $dept,
        'designation' => $desig,
        'has_photo' => $hasPhoto,
        'photo_url' => $photoUrl,
    ];

    $selectedAttr = ($eId === $selectedEmpId) ? ' selected' : '';
    $empSelectOptions .= '<option value="' . e($eId) . '"' . $selectedAttr . '>' . e($empNo . ' · ' . $name . ' (' . $dept . ')') . '</option>';
}
$empMapJson = json_encode($empMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

// Fetch monthly attendance records
$historyRecords = employee_repo()->getMonthlyAttendance($orgId, $selectedMonth, $selectedEmpId !== '' ? $selectedEmpId : null);

// Calculate monthly metrics
$totalPunches = count($historyRecords);
$totalCompleteShifts = 0;
foreach ($historyRecords as $hr) {
    if (!empty($hr['check_in']) && !empty($hr['check_out'])) {
        $totalCompleteShifts++;
    }
}

$historyRowsHtml = '';
if (empty($historyRecords)) {
    $historyRowsHtml = '<tr><td colspan="6" class="py-8 text-center text-slate-400 italic">No attendance records found for ' . e(date('F Y', strtotime($selectedMonth . '-01'))) . '.</td></tr>';
} else {
    foreach ($historyRecords as $idx => $r) {
        $attId = e((string)$r['id']);
        $dateFormatted = date('d-M-Y (D)', strtotime((string)$r['attendance_date']));
        $empNo = e((string)($r['emp_no'] ?? '—'));
        $empName = e((string)$r['full_name']);
        $dept = e((string)$r['department']);
        $cIn = !empty($r['check_in']) ? date('g:i A', strtotime((string)$r['check_in'])) : '—';
        $cOut = !empty($r['check_out']) ? date('g:i A', strtotime((string)$r['check_out'])) : '—';
        $hours = !empty($r['total_hours']) ? e((string)$r['total_hours']) : ($cOut !== '—' ? 'Completed' : 'On Duty');

        // Check-in photo thumbnail
        $inPhotoHtml = !empty($r['has_in_photo'])
            ? '<button type="button" onclick="viewPhotoModal(\'/portals/main-lab/staff/attendance-photo.php?id=' . $attId . '&type=in\', \'' . $empName . ' - Clock In Photo\')" class="group relative inline-block">
                <img src="/portals/main-lab/staff/attendance-photo.php?id=' . $attId . '&type=in" alt="In Photo" class="w-9 h-9 rounded-lg object-cover border border-emerald-500 shadow-2xs group-hover:opacity-80 transition-opacity">
                <span class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 bg-black/40 rounded-lg text-white text-[10px]"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
               </button>'
            : '<span class="text-[11px] text-slate-300 italic">No photo</span>';

        // Check-out photo thumbnail
        $outPhotoHtml = !empty($r['has_out_photo'])
            ? '<button type="button" onclick="viewPhotoModal(\'/portals/main-lab/staff/attendance-photo.php?id=' . $attId . '&type=out\', \'' . $empName . ' - Clock Out Photo\')" class="group relative inline-block">
                <img src="/portals/main-lab/staff/attendance-photo.php?id=' . $attId . '&type=out" alt="Out Photo" class="w-9 h-9 rounded-lg object-cover border border-blue-500 shadow-2xs group-hover:opacity-80 transition-opacity">
                <span class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 bg-black/40 rounded-lg text-white text-[10px]"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
               </button>'
            : '<span class="text-[11px] text-slate-300 italic">No photo</span>';

        $statusBadge = '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Present</span>';
        if ($cOut !== '—') {
            $statusBadge = '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200"><i class="fa-solid fa-check-double text-[9px]"></i> Shift Ended</span>';
        }

        $historyRowsHtml .= <<<ROW
        <tr class="hover:bg-slate-50/80 transition-colors">
            <td class="py-2.5 px-3 font-semibold text-slate-700 whitespace-nowrap">{$dateFormatted}</td>
            <td class="py-2.5 px-3">
                <div class="font-bold text-slate-900">{$empName}</div>
                <div class="text-[11px] text-slate-500 font-mono">{$empNo} &middot; {$dept}</div>
            </td>
            <td class="py-2.5 px-3 whitespace-nowrap">
                <div class="flex items-center gap-2">
                    {$inPhotoHtml}
                    <div>
                        <div class="font-mono font-bold text-xs text-slate-800">{$cIn}</div>
                        <div class="text-[10px] text-emerald-600 font-semibold uppercase">Arrival</div>
                    </div>
                </div>
            </td>
            <td class="py-2.5 px-3 whitespace-nowrap">
                <div class="flex items-center gap-2">
                    {$outPhotoHtml}
                    <div>
                        <div class="font-mono font-bold text-xs text-slate-800">{$cOut}</div>
                        <div class="text-[10px] text-blue-600 font-semibold uppercase">Departure</div>
                    </div>
                </div>
            </td>
            <td class="py-2.5 px-3 font-mono font-bold text-xs text-slate-700 whitespace-nowrap">{$hours}</td>
            <td class="py-2.5 px-3 whitespace-nowrap">{$statusBadge}</td>
        </tr>
        ROW;
    }
}

$headerAction = <<<HTML
<div class="flex items-center gap-2">
    <a href="/portals/main-lab/staff/index.php" class="btn btn-secondary text-xs py-2 px-3">
        <i class="fa-solid fa-id-badge mr-1"></i> Staff Profiles &amp; ID Cards
    </a>
    <button type="button" onclick="printMonthlyAttendance()" class="btn btn-secondary text-xs py-2 px-3">
        <i class="fa-solid fa-print mr-1"></i> Print Monthly Summary
    </button>
</div>
HTML;

$content = page_header(
    'Employee Photo Clock-In / Clock-Out Attendance',
    'Live camera verified staff attendance with employee code, department, arrival ("IN") and departure ("OUT") timestamps, and monthly history summaries.',
    $headerAction
);

$content .= $message;

$currentDateHuman = date('l, d F Y');
$monthHuman = date('F Y', strtotime($selectedMonth . '-01'));
$todayYmd = date('Y-m-d');

$content .= <<<HTML
<!-- Top Section: Camera & Live Clock-In / Clock-Out Punch Panel -->
<div class="bg-white border border-slate-200 rounded-3xl p-5 sm:p-6 shadow-card mb-8">
    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 pb-5 mb-5 border-b border-slate-100">
        <div class="flex items-center gap-3">
            <span class="w-12 h-12 rounded-2xl bg-teal-600 text-white flex items-center justify-center text-xl shadow-md shrink-0">
                <i class="fa-solid fa-camera"></i>
            </span>
            <div>
                <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">Clock-In &amp; Clock-Out Duty Terminal</h2>
                <p class="text-xs text-slate-500 font-medium">Verify presence using laptop camera or phone selfie upload &middot; {$currentDateHuman}</p>
            </div>
        </div>

        <!-- Real-Time Digital Clock -->
        <div class="flex items-center gap-3 px-4 py-2 bg-slate-900 text-white rounded-2xl shadow-inner font-mono">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <div>
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Live System Time</div>
                <div id="live-digital-clock" class="text-lg font-black tracking-widest text-[#c2f13c]">--:--:-- --</div>
            </div>
        </div>
    </div>

    <!-- Clock Punch Form -->
    <form id="punch-form" method="post" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <input type="hidden" name="action" value="clock_punch">
        <input type="hidden" name="punch_type" id="punch_type" value="in">
        <input type="hidden" name="punch_time" id="punch_time" value="">
        <input type="hidden" name="punch_date" id="punch_date" value="{$todayYmd}">
        <input type="hidden" name="photo_base64" id="photo_base64" value="">

        <!-- Column 1: Employee Selection & Profile Card -->
        <div class="lg:col-span-5 space-y-4">
            <div>
                <label class="field-label text-xs font-bold text-slate-700">1. Select On-Duty Employee <span class="text-rose-500">*</span></label>
                <select name="employee_id" id="employee_picker" class="field text-sm font-semibold" onchange="onEmployeeSelect(this.value)" required>
                    {$empSelectOptions}
                </select>
                <p class="text-[11px] text-slate-400 mt-1">Select staff name to load department, employee code, and shift status.</p>
            </div>

            <!-- Dynamic Employee Badge -->
            <div id="employee-badge-card" class="p-4 rounded-2xl bg-slate-50 border border-slate-200/90 flex items-center gap-4 transition-all">
                <div id="emp-avatar-box" class="w-16 h-16 rounded-2xl bg-slate-800 text-[#c2f13c] flex items-center justify-center text-xl font-bold shrink-0 shadow-sm overflow-hidden">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div>
                    <div id="emp-display-name" class="font-bold text-slate-900 text-sm">Select an employee above</div>
                    <div class="flex flex-wrap items-center gap-1.5 mt-1">
                        <span id="emp-display-code" class="inline-block px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-200 text-slate-700">CODE: —</span>
                        <span id="emp-display-dept" class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-teal-100 text-teal-800">DEPT: —</span>
                    </div>
                    <div id="emp-display-desig" class="text-xs text-slate-500 mt-1 italic">Designation: —</div>
                </div>
            </div>

            <!-- Status Notice -->
            <div class="p-3 bg-amber-50 rounded-xl border border-amber-200/80 text-xs text-amber-800 flex items-start gap-2.5">
                <i class="fa-solid fa-circle-info text-amber-600 mt-0.5 text-sm shrink-0"></i>
                <div>
                    <strong>Duty Attendance Rule:</strong>
                    <div class="text-[11px] text-amber-700 mt-0.5">Click <strong>Clock In (Arrival)</strong> when entering the center. Click <strong>Clock Out (Departure)</strong> when leaving. The live picture is stored permanently as proof of presence.</div>
                </div>
            </div>
        </div>

        <!-- Column 2: Camera Viewfinder & Snap -->
        <div class="lg:col-span-4 space-y-3">
            <label class="field-label text-xs font-bold text-slate-700">2. Live Verification Photo <span class="text-rose-500">*</span></label>

            <div class="relative w-full aspect-4/3 max-w-sm mx-auto bg-slate-950 rounded-2xl overflow-hidden border-2 border-slate-800 shadow-md flex items-center justify-center">
                <!-- Video stream from camera -->
                <video id="webcam-video" autoplay playsinline class="w-full h-full object-cover"></video>
                <!-- Canvas for capturing snapshot -->
                <canvas id="photo-canvas" class="hidden"></canvas>
                <!-- Still photo preview after capture -->
                <img id="photo-preview-img" src="" alt="Captured Photo" class="hidden w-full h-full object-cover">

                <!-- Camera Offline / Permission Overlay -->
                <div id="camera-placeholder" class="absolute inset-0 flex flex-col items-center justify-center p-4 text-center text-slate-400 bg-slate-900">
                    <i class="fa-solid fa-camera-retro text-3xl text-slate-600 mb-2"></i>
                    <span class="text-xs font-semibold">Webcam Not Started</span>
                    <button type="button" onclick="startCamera()" class="mt-2.5 btn btn-secondary text-xs py-1 px-3">
                        <i class="fa-solid fa-video mr-1"></i> Start Camera
                    </button>
                </div>

                <!-- Live Camera Indicator Badge -->
                <div id="camera-live-badge" class="hidden absolute top-2.5 left-2.5 px-2 py-0.5 rounded-full bg-rose-600/90 text-white text-[10px] font-bold tracking-wider uppercase flex items-center gap-1.5 shadow-xs">
                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span> Live View
                </div>
            </div>

            <!-- Camera Controls & Fallback File Upload -->
            <div class="flex flex-wrap items-center gap-2 justify-center">
                <button type="button" id="snap-btn" onclick="capturePhoto()" class="btn btn-secondary text-xs py-1.5 px-3.5 font-bold" disabled>
                    <i class="fa-solid fa-camera mr-1 text-teal-600"></i> Snap Photo
                </button>
                <button type="button" id="retake-btn" onclick="retakePhoto()" class="hidden btn btn-secondary text-xs py-1.5 px-3">
                    <i class="fa-solid fa-rotate-left mr-1"></i> Retake
                </button>
            </div>

            <div class="text-center pt-1 border-t border-slate-100">
                <label for="photo_file" class="text-[11px] text-teal-700 hover:text-teal-900 font-semibold cursor-pointer underline">
                    Or upload camera photo from mobile / laptop file
                </label>
                <input type="file" id="photo_file" name="photo_file" accept="image/*" capture="user" class="hidden" onchange="onFilePhotoSelected(this)">
            </div>
        </div>

        <!-- Column 3: Punch Action Buttons -->
        <div class="lg:col-span-3 flex flex-col justify-between border-t lg:border-t-0 lg:border-l border-slate-100 pt-4 lg:pt-0 lg:pl-6 space-y-3">
            <div>
                <label class="field-label text-xs font-bold text-slate-700">3. Submit Attendance</label>
                <p class="text-xs text-slate-500 mb-4">Choose action based on whether employee is arriving or leaving duty.</p>

                <!-- Clock In Button -->
                <button type="button" onclick="submitPunch('in')" class="w-full mb-3 py-3.5 px-4 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm flex items-center justify-center gap-2 shadow-sm transition-all transform active:scale-95">
                    <i class="fa-solid fa-door-open text-base"></i>
                    <span>Clock In (Arrival)</span>
                </button>

                <!-- Clock Out Button -->
                <button type="button" onclick="submitPunch('out')" class="w-full py-3.5 px-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-sm flex items-center justify-center gap-2 shadow-sm transition-all transform active:scale-95">
                    <i class="fa-solid fa-door-closed text-base"></i>
                    <span>Clock Out (Departure)</span>
                </button>
            </div>

            <div class="text-center text-[11px] text-slate-400">
                Recorded for {$settings['name']}
            </div>
        </div>
    </form>
</div>

<!-- Bottom Section: Monthly Attendance History & Summary -->
<div class="bg-white border border-slate-200 rounded-3xl p-5 sm:p-6 shadow-card">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 mb-4 border-b border-slate-100">
        <div>
            <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-calendar-days text-teal-600"></i>
                <span>Monthly Attendance Log &amp; Timesheets</span>
            </h3>
            <p class="text-xs text-slate-500">Record of employee arrival, departure, photos, and logged working hours for {$monthHuman}.</p>
        </div>

        <!-- Filter Form -->
        <form method="get" class="flex flex-wrap items-center gap-2.5">
            <div class="flex items-center gap-2">
                <label class="text-xs font-bold text-slate-600">Month:</label>
                <input type="month" name="month" value="{$selectedMonth}" class="field text-xs font-semibold py-1.5 px-2" onchange="this.form.submit()">
            </div>
            <div class="flex items-center gap-2">
                <label class="text-xs font-bold text-slate-600">Employee:</label>
                <select name="emp_id" class="field text-xs font-semibold py-1.5 px-2" onchange="this.form.submit()">
                    <option value="">All Employees</option>
                    {$empSelectOptions}
                </select>
            </div>
        </form>
    </div>

    <!-- Monthly Metric Pills -->
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-5">
        <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200/80">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Punches This Month</div>
            <div class="text-xl font-black text-slate-900 font-mono mt-0.5">{$totalPunches}</div>
        </div>
        <div class="p-3.5 bg-emerald-50 rounded-2xl border border-emerald-200/80">
            <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Completed Full Shifts</div>
            <div class="text-xl font-black text-emerald-800 font-mono mt-0.5">{$totalCompleteShifts}</div>
        </div>
        <div class="p-3.5 bg-sky-50 rounded-2xl border border-sky-200/80 col-span-2 sm:col-span-1">
            <div class="text-[11px] font-bold uppercase tracking-wider text-sky-700">Active Center Staff</div>
            <div class="text-xl font-black text-sky-800 font-mono mt-0.5">
HTML;
$content .= count($employees);
$content .= <<<HTML
            </div>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="overflow-x-auto rounded-2xl border border-slate-200">
        <table class="w-full text-left border-collapse text-xs">
            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                <tr>
                    <th class="py-2.5 px-3">Date</th>
                    <th class="py-2.5 px-3">Employee Name</th>
                    <th class="py-2.5 px-3">In (Arrival)</th>
                    <th class="py-2.5 px-3">Out (Departure)</th>
                    <th class="py-2.5 px-3">Duty Hours</th>
                    <th class="py-2.5 px-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                {$historyRowsHtml}
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: View Full Captured Photo -->
<div id="photo-modal" class="fixed inset-0 z-50 hidden bg-black/80 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl overflow-hidden max-w-md w-full shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between p-4 border-b border-slate-100">
            <h4 id="photo-modal-title" class="font-bold text-sm text-slate-800">Attendance Verification Photo</h4>
            <button type="button" onclick="closePhotoModal()" class="text-slate-400 hover:text-slate-600 text-lg p-1">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="p-4 flex items-center justify-center bg-slate-950">
            <img id="photo-modal-img" src="" alt="Verification Photo" class="max-h-96 w-auto object-contain rounded-xl">
        </div>
        <div class="p-3 text-center bg-slate-50 border-t border-slate-100 text-xs text-slate-500">
            Recorded at duty terminal with timestamp verification.
        </div>
    </div>
</div>

<!-- Hidden Printable Monthly Summary Container -->
<div id="print-attendance-wrap" class="hidden">
    <div style="font-family: Arial, sans-serif; color: #000; padding: 25px; max-width: 800px; margin: 0 auto; line-height: 1.4;">
        <div style="border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="font-size: 20px; font-weight: 800; text-transform: uppercase; margin: 0;">{$settings['name']}</h1>
                <div style="font-size: 11px; color: #475569; margin-top: 2px;">{$settings['address']} &middot; {$settings['phone']}</div>
            </div>
            <div style="text-align: right;">
                <span style="display: inline-block; background: #000; color: #fff; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 3px; text-transform: uppercase;">MONTHLY ATTENDANCE REPORT</span>
                <div style="font-size: 12px; font-weight: 700; margin-top: 4px;">Month: {$monthHuman}</div>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; font-size: 11.5px; margin-bottom: 20px;">
            <thead>
                <tr style="border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; background: #f8fafc;">
                    <th style="padding: 6px 8px; text-align: left;">Date</th>
                    <th style="padding: 6px 8px; text-align: left;">Employee Code</th>
                    <th style="padding: 6px 8px; text-align: left;">Employee Name</th>
                    <th style="padding: 6px 8px; text-align: left;">Department</th>
                    <th style="padding: 6px 8px; text-align: left;">In Time</th>
                    <th style="padding: 6px 8px; text-align: left;">Out Time</th>
                    <th style="padding: 6px 8px; text-align: left;">Total Hours</th>
                </tr>
            </thead>
            <tbody>
HTML;

foreach ($historyRecords as $hr) {
    $dFmt = date('d-M-Y', strtotime((string)$hr['attendance_date']));
    $eNo = (string)($hr['emp_no'] ?? '—');
    $eNm = (string)$hr['full_name'];
    $eDp = (string)$hr['department'];
    $cIn = !empty($hr['check_in']) ? date('g:i A', strtotime((string)$hr['check_in'])) : '—';
    $cOut = !empty($hr['check_out']) ? date('g:i A', strtotime((string)$hr['check_out'])) : '—';
    $hrs = !empty($hr['total_hours']) ? (string)$hr['total_hours'] : ($cOut !== '—' ? 'Completed' : 'On Duty');

    $content .= <<<P_ROW
        <tr style="border-bottom: 1px solid #e2e8f0;">
            <td style="padding: 5px 8px;">{$dFmt}</td>
            <td style="padding: 5px 8px; font-family: monospace;">{$eNo}</td>
            <td style="padding: 5px 8px; font-weight: bold;">{$eNm}</td>
            <td style="padding: 5px 8px;">{$eDp}</td>
            <td style="padding: 5px 8px; font-family: monospace;">{$cIn}</td>
            <td style="padding: 5px 8px; font-family: monospace;">{$cOut}</td>
            <td style="padding: 5px 8px; font-family: monospace;">{$hrs}</td>
        </tr>
    P_ROW;
}

$content .= <<<HTML
            </tbody>
        </table>

        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 40px; padding-top: 15px; border-top: 1px dashed #cbd5e1;">
            <div>
                <div style="font-size: 11px; font-weight: bold;">Generated on: {$currentDateHuman}</div>
                <div style="font-size: 10px; color: #64748b;">Software created by Shakeel Khalid &middot; WhatsApp 0328-3070070</div>
            </div>
            <div style="text-align: center; border-top: 1px solid #000; width: 180px; padding-top: 5px; font-size: 11px; font-weight: bold;">
                Administrator / Incharge Signature
            </div>
        </div>
    </div>
</div>
HTML;

$content .= '<script>';
$content .= 'const EMP_MAP = ' . $empMapJson . ';' . "\n";
$content .= <<<'SCRIPT'
let webcamStream = null;
let capturedDataUri = null;

// Live Digital Clock
function updateLiveClock() {
    const now = new Date();
    const clockEl = document.getElementById('live-digital-clock');
    if (clockEl) {
        clockEl.innerText = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
}
setInterval(updateLiveClock, 1000);
updateLiveClock();

// Auto start webcam on page load
window.addEventListener('DOMContentLoaded', function() {
    startCamera();
});

// Webcam initialization
async function startCamera() {
    const video = document.getElementById('webcam-video');
    const placeholder = document.getElementById('camera-placeholder');
    const liveBadge = document.getElementById('camera-live-badge');
    const snapBtn = document.getElementById('snap-btn');

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        placeholder.innerHTML = '<span class="text-xs text-amber-400 p-2">Camera not supported by browser. Please use the upload photo link below.</span>';
        return;
    }

    try {
        webcamStream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
            audio: false
        });
        video.srcObject = webcamStream;
        video.classList.remove('hidden');
        placeholder.classList.add('hidden');
        liveBadge.classList.remove('hidden');
        snapBtn.disabled = false;
    } catch (err) {
        placeholder.innerHTML = `
            <i class="fa-solid fa-triangle-exclamation text-amber-400 text-2xl mb-1.5"></i>
            <span class="text-xs text-slate-300 font-semibold">Camera Access Blocked</span>
            <span class="text-[11px] text-slate-400 mt-1 max-w-xs">Allow camera permission in browser or click below to upload a photo file.</span>
            <button type="button" onclick="startCamera()" class="mt-2 btn btn-secondary text-xs py-1 px-3">Try Again</button>
        `;
    }
}

// Snap Photo
function capturePhoto() {
    const video = document.getElementById('webcam-video');
    const canvas = document.getElementById('photo-canvas');
    const previewImg = document.getElementById('photo-preview-img');
    const retakeBtn = document.getElementById('retake-btn');
    const snapBtn = document.getElementById('snap-btn');
    const base64Input = document.getElementById('photo_base64');

    if (!video.videoWidth) {
        alert('Camera stream not ready yet.');
        return;
    }

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    capturedDataUri = canvas.toDataURL('image/jpeg', 0.85);
    base64Input.value = capturedDataUri;

    previewImg.src = capturedDataUri;
    previewImg.classList.remove('hidden');
    video.classList.add('hidden');
    retakeBtn.classList.remove('hidden');
    snapBtn.classList.add('hidden');
}

// Retake Photo
function retakePhoto() {
    const video = document.getElementById('webcam-video');
    const previewImg = document.getElementById('photo-preview-img');
    const retakeBtn = document.getElementById('retake-btn');
    const snapBtn = document.getElementById('snap-btn');
    const base64Input = document.getElementById('photo_base64');

    capturedDataUri = null;
    base64Input.value = '';
    previewImg.classList.add('hidden');
    video.classList.remove('hidden');
    retakeBtn.classList.add('hidden');
    snapBtn.classList.remove('hidden');
}

// Handle fallback file upload
function onFilePhotoSelected(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('photo-preview-img');
            const video = document.getElementById('webcam-video');
            const placeholder = document.getElementById('camera-placeholder');
            const retakeBtn = document.getElementById('retake-btn');
            const snapBtn = document.getElementById('snap-btn');
            const base64Input = document.getElementById('photo_base64');

            capturedDataUri = e.target.result;
            base64Input.value = capturedDataUri;

            previewImg.src = capturedDataUri;
            previewImg.classList.remove('hidden');
            video.classList.add('hidden');
            placeholder.classList.add('hidden');
            retakeBtn.classList.remove('hidden');
            snapBtn.classList.add('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// On employee dropdown select
function onEmployeeSelect(empId) {
    const emp = EMP_MAP[empId];
    const nameEl = document.getElementById('emp-display-name');
    const codeEl = document.getElementById('emp-display-code');
    const deptEl = document.getElementById('emp-display-dept');
    const desigEl = document.getElementById('emp-display-desig');
    const avatarBox = document.getElementById('emp-avatar-box');

    if (!emp) {
        nameEl.innerText = 'Select an employee above';
        codeEl.innerText = 'CODE: —';
        deptEl.innerText = 'DEPT: —';
        desigEl.innerText = 'Designation: —';
        avatarBox.innerHTML = '<i class="fa-solid fa-user"></i>';
        return;
    }

    nameEl.innerText = emp.name;
    codeEl.innerText = 'CODE: ' + emp.code;
    deptEl.innerText = 'DEPT: ' + emp.dept;
    desigEl.innerText = 'Designation: ' + emp.designation;

    if (emp.has_photo && emp.photo_url) {
        avatarBox.innerHTML = '<img src="' + emp.photo_url + '" alt="' + emp.name + '" class="w-full h-full object-cover">';
    } else {
        avatarBox.innerHTML = '<span class="font-black text-sm uppercase">' + emp.name.substring(0, 2) + '</span>';
    }
}

// Submit Punch (in or out)
function submitPunch(type) {
    const empId = document.getElementById('employee_picker').value;
    if (!empId) {
        alert('Please select the employee first.');
        document.getElementById('employee_picker').focus();
        return;
    }

    // Auto snap if video is active and not yet captured
    const base64Val = document.getElementById('photo_base64').value;
    const fileInput = document.getElementById('photo_file');
    const hasFile = fileInput.files && fileInput.files.length > 0;

    if (!base64Val && !hasFile) {
        const video = document.getElementById('webcam-video');
        if (video && video.videoWidth > 0 && !video.classList.contains('hidden')) {
            capturePhoto();
        } else {
            alert('Please snap a live photo or upload a photo to verify your attendance.');
            return;
        }
    }

    const now = new Date();
    const timeStr = now.toTimeString().split(' ')[0];
    document.getElementById('punch_type').value = type;
    document.getElementById('punch_time').value = timeStr;

    const actionText = type === 'in' ? 'Clock In (Arrival)' : 'Clock Out (Departure)';
    const emp = EMP_MAP[empId];
    const empName = emp ? emp.name : 'Employee';

    if (confirm(`Confirm ${actionText} for ${empName}?`)) {
        document.getElementById('punch-form').submit();
    }
}

// Modal View Photo
function viewPhotoModal(url, title) {
    document.getElementById('photo-modal-img').src = url;
    document.getElementById('photo-modal-title').innerText = title;
    document.getElementById('photo-modal').classList.remove('hidden');
}
function closePhotoModal() {
    document.getElementById('photo-modal').classList.add('hidden');
    document.getElementById('photo-modal-img').src = '';
}

// Print Monthly Attendance Slip
function printMonthlyAttendance() {
    const printWrap = document.getElementById('print-attendance-wrap');
    if (!printWrap) return;
    const printContent = printWrap.innerHTML;
    const win = window.open('', '_blank', 'width=850,height=750');
    if (!win) {
        alert('Please allow popups to print attendance summary.');
        return;
    }
    win.document.open();
    win.document.write('<!DOCTYPE html><html><head><title>Monthly Attendance Summary</title><style>@media print { body { margin: 0; padding: 15px; font-family: Arial, sans-serif; } @page { size: A4; margin: 12mm; } }</style></head><body onload="window.print(); window.close();">' + printContent + '</body></html>');
    win.document.close();
}
SCRIPT;
$content .= '</script>';

render_page('Employee Attendance', 'main-lab', 'staff-attendance', $content);
