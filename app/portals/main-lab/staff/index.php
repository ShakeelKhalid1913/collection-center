<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

require_auth();

$user = current_user();
$orgId = $user['organization_id'] ?? 'ORG-001';
$portal = current_portal();
$message = '';
$tab = trim((string)($_GET['tab'] ?? 'staff'));

if ($tab === 'attendance') {
    header('Location: /portals/main-lab/staff/attendance.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_employee') {
        $photo = $_FILES['photo'] ?? null;
        $degree = $_FILES['degree'] ?? null;
        $res = employee_repo()->saveEmployee($_POST, $photo, $degree, $orgId);
        $message = $res['ok'] ? flash_success($res['message']) : flash_error($res['error'] ?? 'Could not save employee.');
        $tab = 'staff';
    } elseif ($action === 'delete_employee') {
        $id = trim((string)($_POST['employee_id'] ?? ''));
        if ($id !== '') {
            $ok = employee_repo()->deleteEmployee($id, $orgId);
            $message = $ok ? flash_success('Employee removed successfully.') : flash_error('Could not remove employee.');
        }
        $tab = 'staff';
    } elseif ($action === 'save_license') {
        $file = $_FILES['license_file'] ?? null;
        $ok = employee_repo()->saveLicense($_POST, $file, $orgId);
        $message = $ok ? flash_success('Lab Registration Certificate & License updated.') : flash_error('Could not save license.');
        $tab = 'license';
    } elseif ($action === 'save_attendance') {
        $attDate = trim((string)($_POST['attendance_date'] ?? date('Y-m-d')));
        $records = $_POST['att'] ?? [];
        if (is_array($records)) {
            $ok = employee_repo()->saveAttendanceBatch($records, $orgId, $attDate);
            $message = $ok ? flash_success("Attendance saved for {$attDate}.") : flash_error('Could not save attendance.');
        }
        $tab = 'attendance';
    }
}

// Data for License Frame
$license = employee_repo()->getLicenseDetails($orgId);
$licenseTitle = e($license['license_title'] ?? 'Healthcare Commission Registration License');
$licenseNumber = e($license['license_number'] ?? 'PHC-LAB-2024-8891');
$licenseAuth = e($license['license_authority'] ?? 'Punjab Healthcare Commission (PHC)');
$hasLicenseImg = !empty($license['has_license']);
$licenseImgUrl = $hasLicenseImg ? '/portals/main-lab/staff/license-image.php?org=' . urlencode($orgId) . '&v=' . ($license['license_image_ver'] ?? 1) : '';

// Data for Staff Table
$employees = employee_repo()->getAll($orgId);
$empRows = [];
foreach ($employees as $e) {
    $eid = (string)$e['id'];
    $empNo = (string)($e['emp_no'] ?? $e['emp_code'] ?? 'W-001');
    $name = (string)$e['full_name'];
    $desig = (string)$e['designation'];
    $dept = (string)$e['department'];
    $phone = (string)($e['phone'] ?: '—');
    $degreeTitle = (string)($e['degree_title'] ?: '—');
    $hasPhoto = !empty($e['has_photo']);
    $hasDegree = !empty($e['has_degree']);

    $avatarHtml = $hasPhoto
        ? '<img src="/portals/main-lab/staff/photo.php?id=' . urlencode($eid) . '" alt="' . e($name) . '" class="w-10 h-10 rounded-full object-cover border-2 border-teal-500 shadow-xs">'
        : '<div class="w-10 h-10 rounded-full bg-slate-800 text-[#c2f13c] font-black flex items-center justify-center text-xs shadow-xs">' . e(user_initials($name)) . '</div>';

    $degreeBadge = $hasDegree
        ? '<a href="/portals/main-lab/staff/degree.php?id=' . urlencode($eid) . '" target="_blank" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100" title="View Uploaded Degree / Diploma"><i class="fa-solid fa-graduation-cap"></i> ' . e($degreeTitle) . '</a>'
        : '<span class="text-xs text-slate-400">' . e($degreeTitle) . '</span>';

    $cardBtn = '<a href="/portals/main-lab/staff/card.php?id=' . urlencode($eid) . '" target="_blank" class="btn btn-secondary text-xs py-1 px-2" title="Print Worker ID Badge"><i class="fa-solid fa-id-card text-blue-600 mr-1"></i> ID Card</a>';

    $editBtn = '<button type="button" onclick=\'openEditEmpModal(' . htmlspecialchars(json_encode([
        'id' => $eid,
        'emp_no' => $empNo,
        'full_name' => $name,
        'designation' => $desig,
        'department' => $dept,
        'phone' => (string)($e['phone'] ?? ''),
        'cnic' => (string)($e['cnic'] ?? ''),
        'blood_group' => (string)($e['blood_group'] ?? ''),
        'joining_date' => (string)($e['joining_date'] ?? ''),
        'degree_title' => $degreeTitle !== '—' ? $degreeTitle : '',
        'has_photo' => $hasPhoto,
        'has_degree' => $hasDegree,
    ]), ENT_QUOTES, 'UTF-8') . ')\' class="btn btn-secondary text-xs py-1 px-2.5" title="Edit Doctor / Worker & Photo"><i class="fa-solid fa-pen text-teal-600 mr-1"></i> Edit</button>';

    $delBtn = '<form method="post" onsubmit="return confirm(\'Delete employee?\');" class="inline">' .
        '<input type="hidden" name="action" value="delete_employee">' .
        '<input type="hidden" name="employee_id" value="' . e($eid) . '">' .
        '<button type="submit" class="btn btn-danger text-xs py-1 px-2" title="Delete"><i class="fa-solid fa-trash"></i></button>' .
        '</form>';

    $actionsHtml = '<div class="flex items-center gap-1.5 whitespace-nowrap">' . $editBtn . $cardBtn . $delBtn . '</div>';

    $empRows[] = [
        '<div class="flex items-center gap-3">' . $avatarHtml . '<div><strong class="text-slate-900 text-sm">' . e($name) . '</strong><div class="text-[11px] text-slate-500">' . e($desig) . '</div></div></div>',
        '<span class="font-mono font-bold text-teal-800 bg-teal-50 border border-teal-200 px-2 py-0.5 rounded text-xs">' . e($empNo) . '</span>',
        '<span class="badge badge-teal text-[11px] font-semibold">' . e($dept) . '</span>',
        '<span class="text-xs text-slate-600 font-mono">' . e($phone) . '</span>',
        $degreeBadge,
        $actionsHtml,
    ];
}

// Attendance data
$attDate = trim((string)($_GET['att_date'] ?? date('Y-m-d')));
$attRecords = employee_repo()->getAttendanceForDate($orgId, $attDate);

$headerAction = '<button type="button" onclick="resetAndOpenEmpModal()" class="btn btn-primary text-sm py-2 px-4 shadow-sm"><i class="fa-solid fa-user-plus mr-1.5"></i> Add Employee / Worker</button>';
$content = page_header('Staff Management & Documents', 'Employee profiles, photo and degree uploads, printable worker ID cards, worker attendance, and lab registration license.', $headerAction);
$content .= $message;

// Tabs
$staffActive = $tab === 'staff' ? 'border-b-2 border-teal-600 text-teal-700 font-bold' : 'text-slate-500 hover:text-slate-800 font-medium';
$attendanceActive = $tab === 'attendance' ? 'border-b-2 border-teal-600 text-teal-700 font-bold' : 'text-slate-500 hover:text-slate-800 font-medium';
$licenseActive = $tab === 'license' ? 'border-b-2 border-teal-600 text-teal-700 font-bold' : 'text-slate-500 hover:text-slate-800 font-medium';
$empCount = count($employees);
$content .= <<<HTML
<div class="flex items-center gap-6 border-b border-slate-200 mb-6 text-sm">
    <a href="?tab=staff" class="pb-3 px-1 transition-all {$staffActive}">
        <i class="fa-solid fa-users mr-1.5"></i> Employees &amp; ID Cards ({$empCount})
    </a>
    <a href="/portals/main-lab/staff/attendance.php" class="pb-3 px-1 transition-all text-slate-500 hover:text-slate-800 font-medium">
        <i class="fa-solid fa-camera mr-1.5 text-teal-600"></i> Worker Photo Attendance Terminal
    </a>
    <a href="?tab=license" class="pb-3 px-1 transition-all {$licenseActive}">
        <i class="fa-solid fa-award mr-1.5"></i> Lab Registration Certificate in Frame
    </a>
</div>
HTML;

if ($tab === 'license') {
    // Certificate in Frame View
    $certificateGraphicHtml = $hasLicenseImg
        ? '<img src="' . $licenseImgUrl . '" alt="Registration Certificate" class="w-full h-auto max-h-80 object-contain">'
        : '<div class="p-8 text-center text-slate-400 text-xs"><i class="fa-solid fa-file-shield text-4xl mb-2 text-slate-300"></i><p>No scanned certificate uploaded yet.<br>Upload your registration certificate or license below.</p></div>';

    $content .= <<<HTML
    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Framed Certificate Preview -->
        <div class="lg:col-span-2">
            <div class="relative bg-gradient-to-b from-amber-50 to-orange-50 border-8 border-amber-700/80 rounded-3xl p-6 sm:p-10 shadow-2xl text-center">
                <!-- Inner Gold Border Frame -->
                <div class="border-2 border-dashed border-amber-600/60 rounded-2xl p-6 sm:p-8 bg-white/95">
                    <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-700 mx-auto flex items-center justify-center text-2xl mb-4 border border-amber-300">
                        <i class="fa-solid fa-certificate"></i>
                    </div>
                    <span class="text-[11px] uppercase tracking-widest font-black text-amber-800 block mb-1">Official Healthcare Licensing</span>
                    <h2 class="text-xl sm:text-2xl font-black text-slate-900 uppercase tracking-tight mb-2">{$licenseTitle}</h2>
                    <div class="text-sm font-semibold text-slate-600 mb-4">Authority: <strong class="text-slate-800">{$licenseAuth}</strong></div>
                    
                    <div class="inline-block px-4 py-1.5 rounded-full bg-amber-50 border border-amber-300 text-amber-950 font-mono font-bold text-sm mb-6">
                        License No: {$licenseNumber}
                    </div>

                    <!-- Certificate Image / Graphic -->
                    <div class="max-w-md mx-auto my-4 rounded-xl overflow-hidden border border-slate-200 bg-slate-50 min-h-[220px] flex items-center justify-center">
                        {$certificateGraphicHtml}
                    </div>

                    <div class="flex items-center justify-between text-xs text-slate-500 pt-6 border-t border-amber-200/80">
                        <div>
                            <div class="font-bold text-slate-800">Healthcare Commission Verified</div>
                            <div class="text-[10px]">Quality Standard ISO 15189</div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold text-slate-800">Active Laboratory License</div>
                            <div class="text-[10px]">Valid Across Punjab / Pakistan</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- License Details & Upload Form -->
        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-card h-fit">
            <h3 class="text-sm font-bold text-slate-800 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-teal-600"></i> Update Certificate Details &amp; Image
            </h3>
            <form method="post" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="save_license">
                <div>
                    <label class="field-label text-xs">Certificate / License Title</label>
                    <input type="text" name="license_title" value="{$licenseTitle}" class="field text-sm" placeholder="e.g. Punjab Healthcare Commission License" required>
                </div>
                <div>
                    <label class="field-label text-xs">License / Registration Number</label>
                    <input type="text" name="license_number" value="{$licenseNumber}" class="field text-sm font-mono" placeholder="e.g. PHC-LAB-2024-8891">
                </div>
                <div>
                    <label class="field-label text-xs">Issuing Authority</label>
                    <input type="text" name="license_authority" value="{$licenseAuth}" class="field text-sm" placeholder="e.g. Punjab Healthcare Commission (PHC)">
                </div>
                <div>
                    <label class="field-label text-xs">Upload Certificate Document / Image <span class="text-slate-400">(PNG, JPG, WebP)</span></label>
                    <input type="file" name="license_file" accept="image/*,.pdf" class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-teal-700 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-teal-800">
                </div>
                <button type="submit" class="btn btn-primary text-sm py-2 px-4 w-full">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i> Save &amp; Frame Certificate
                </button>
            </form>
        </div>
    </div>
    HTML;
} elseif ($tab === 'attendance') {
    // Attendance View
    $attRows = '';
    $aIdx = 0;
    foreach ($attRecords as $rec) {
        $aIdx++;
        $eId = e((string)$rec['employee_id']);
        $eName = e((string)$rec['full_name']);
        $eDesig = e((string)$rec['designation']);
        $eDept = e((string)$rec['department']);
        $curStatus = (string)($rec['status'] ?? 'present');
        $cIn = e((string)($rec['check_in'] ?? '09:00'));
        $cOut = e((string)($rec['check_out'] ?? '17:00'));
        $notes = e((string)($rec['notes'] ?? ''));

        $statusRadios = '<div class="inline-flex items-center gap-3 text-xs">'
            . '<label class="inline-flex items-center gap-1 cursor-pointer"><input type="radio" name="att[' . $eId . '][status]" value="present" ' . ($curStatus === 'present' ? 'checked' : '') . ' class="text-emerald-600"> <span class="text-emerald-700 font-bold">Present</span></label>'
            . '<label class="inline-flex items-center gap-1 cursor-pointer"><input type="radio" name="att[' . $eId . '][status]" value="late" ' . ($curStatus === 'late' ? 'checked' : '') . ' class="text-amber-600"> <span class="text-amber-700 font-bold">Late</span></label>'
            . '<label class="inline-flex items-center gap-1 cursor-pointer"><input type="radio" name="att[' . $eId . '][status]" value="absent" ' . ($curStatus === 'absent' ? 'checked' : '') . ' class="text-rose-600"> <span class="text-rose-700 font-bold">Absent</span></label>'
            . '<label class="inline-flex items-center gap-1 cursor-pointer"><input type="radio" name="att[' . $eId . '][status]" value="leave" ' . ($curStatus === 'leave' ? 'checked' : '') . ' class="text-blue-600"> <span class="text-blue-700 font-bold">Leave</span></label>'
            . '</div>';

        $attRows .= '<tr>'
            . '<td class="py-2.5 px-3 text-xs font-mono">' . $aIdx . '</td>'
            . '<td class="py-2.5 px-3"><strong>' . $eName . '</strong><div class="text-[11px] text-slate-500">' . $eDesig . ' · ' . $eDept . '</div></td>'
            . '<td class="py-2.5 px-3">' . $statusRadios . '</td>'
            . '<td class="py-2.5 px-3"><input type="time" name="att[' . $eId . '][check_in]" value="' . $cIn . '" class="field !w-24 text-xs font-mono py-1 px-1.5"></td>'
            . '<td class="py-2.5 px-3"><input type="time" name="att[' . $eId . '][check_out]" value="' . $cOut . '" class="field !w-24 text-xs font-mono py-1 px-1.5"></td>'
            . '<td class="py-2.5 px-3"><input type="text" name="att[' . $eId . '][notes]" value="' . $notes . '" class="field text-xs py-1 px-2" placeholder="Remarks…"></td>'
            . '</tr>';
    }

    $content .= <<<HTML
    <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-card mb-6">
        <form method="get" class="flex flex-wrap items-center justify-between gap-4 mb-4 pb-4 border-b border-slate-100">
            <input type="hidden" name="tab" value="attendance">
            <div class="flex items-center gap-3">
                <span class="text-xs font-bold text-slate-700">Attendance Date:</span>
                <input type="date" name="att_date" value="{$attDate}" class="field text-xs font-semibold py-1.5" onchange="this.form.submit()">
            </div>
            <div class="text-xs text-slate-500">
                Marking daily staff presence, late check-in, and leave logs.
            </div>
        </form>

        <form method="post" class="space-y-4">
            <input type="hidden" name="action" value="save_attendance">
            <input type="hidden" name="attendance_date" value="{$attDate}">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-y border-slate-200 bg-slate-50 text-[11px] font-bold uppercase text-slate-500">
                            <th class="py-2 px-3">#</th>
                            <th class="py-2 px-3">Employee Name</th>
                            <th class="py-2 px-3">Status</th>
                            <th class="py-2 px-3">Check-in</th>
                            <th class="py-2 px-3">Check-out</th>
                            <th class="py-2 px-3">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        {$attRows}
                    </tbody>
                </table>
            </div>
            <div class="pt-3 border-t border-slate-100 flex items-center justify-end">
                <button type="submit" class="btn btn-primary text-sm py-2 px-5">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i> Save Worker Attendance
                </button>
            </div>
        </form>
    </div>
    HTML;
} else {
    // Employees & ID Cards View
    $content .= <<<HTML
    <!-- Modal: Add / Edit Employee -->
    <div id="add-emp-modal" class="hidden mb-6 bg-white border border-slate-200 rounded-3xl p-6 shadow-card transition-all">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
            <div class="flex items-center gap-2.5">
                <span id="emp-modal-icon" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-teal-100 text-teal-700 font-bold text-sm">
                    <i class="fa-solid fa-user-plus"></i>
                </span>
                <div>
                    <h3 id="emp-modal-title" class="text-base font-bold text-slate-800">Add New Laboratory Worker / Employee</h3>
                    <p id="emp-modal-sub" class="text-xs text-slate-500">Upload employee photo, degrees, worker details, and generate printable ID card.</p>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('add-emp-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg p-1">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="emp-form" method="post" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="save_employee">
            <input type="hidden" name="id" id="emp-id" value="">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="field-label text-xs">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="full_name" id="emp-full-name" class="field text-sm" placeholder="e.g. Dr. Tariq Mahmood" required>
                </div>
                <div>
                    <label class="field-label text-xs">Worker / Employee ID <span class="text-slate-400">(optional)</span></label>
                    <input type="text" name="emp_no" id="emp-emp-no" class="field text-sm font-mono uppercase" placeholder="e.g. W-005">
                </div>
                <div>
                    <label class="field-label text-xs">Designation <span class="text-red-500">*</span></label>
                    <input type="text" name="designation" id="emp-designation" class="field text-sm" placeholder="e.g. Senior Pathologist / Phlebotomist" required>
                </div>
                <div>
                    <label class="field-label text-xs">Department</label>
                    <select name="department" id="emp-department" class="field text-sm">
                        <option value="Laboratory">Laboratory (Pathology)</option>
                        <option value="Phlebotomy">Phlebotomy / Sample Collection</option>
                        <option value="Ultrasound">Ultrasound</option>
                        <option value="X-Ray">X-Ray (Radiography)</option>
                        <option value="ECG">ECG</option>
                        <option value="CT Scan">CT Scan</option>
                        <option value="Reception / Billing">Reception / Billing</option>
                        <option value="Administration">Administration</option>
                    </select>
                </div>
                <div>
                    <label class="field-label text-xs">Phone Number</label>
                    <input type="text" name="phone" id="emp-phone" class="field text-sm" placeholder="0300 1234567">
                </div>
                <div>
                    <label class="field-label text-xs">CNIC Number</label>
                    <input type="text" name="cnic" id="emp-cnic" class="field text-sm font-mono" placeholder="35201-1234567-1">
                </div>
                <div>
                    <label class="field-label text-xs">Blood Group</label>
                    <select name="blood_group" id="emp-blood-group" class="field text-sm font-semibold">
                        <option value="">Select Blood Group</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                    </select>
                </div>
                <div>
                    <label class="field-label text-xs">Joining Date</label>
                    <input type="date" name="joining_date" id="emp-joining-date" class="field text-sm" value="{$today}">
                </div>
                <div>
                    <label class="field-label text-xs">Degree / Qualification Title</label>
                    <input type="text" name="degree_title" id="emp-degree-title" class="field text-sm" placeholder="e.g. M.B.B.S, F.C.P.S, B.S MLT">
                </div>
                <div>
                    <label class="field-label text-xs font-bold text-slate-800">Doctor / Worker Photo <span class="text-slate-400 font-normal">(for ID Card)</span></label>
                    <div id="emp-photo-status" class="hidden mb-2 text-xs text-teal-700 bg-teal-50 border border-teal-200 rounded-lg p-2 flex items-center gap-2">
                        <i class="fa-solid fa-image text-teal-600"></i>
                        <span>Current photo exists. Upload below to replace it:</span>
                    </div>
                    <input type="file" name="photo" accept="image/*" class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-teal-700 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-teal-800">
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label text-xs font-bold text-slate-800">Degree / Certificate Document</label>
                    <div id="emp-degree-status" class="hidden mb-2 text-xs text-purple-700 bg-purple-50 border border-purple-200 rounded-lg p-2 flex items-center gap-2">
                        <i class="fa-solid fa-file-pdf text-purple-600"></i>
                        <span>Current degree uploaded. Upload below to replace it:</span>
                    </div>
                    <input type="file" name="degree" accept="image/*,.pdf" class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-purple-700 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-purple-800">
                </div>
            </div>
            <div class="flex items-center gap-3 pt-3 border-t border-slate-100">
                <button type="submit" class="btn btn-primary text-sm py-2 px-5">
                    <i class="fa-solid fa-check mr-1.5"></i> <span id="emp-submit-text">Save Worker &amp; Documents</span>
                </button>
                <button type="button" onclick="document.getElementById('add-emp-modal').classList.add('hidden')" class="btn btn-secondary text-sm py-2 px-4">
                    Cancel
                </button>
            </div>
        </form>
    </div>

    <script>
    function resetAndOpenEmpModal() {
        const modal = document.getElementById('add-emp-modal');
        const form = document.getElementById('emp-form');
        if (!modal || !form) return;
        form.reset();
        document.getElementById('emp-id').value = '';
        document.getElementById('emp-modal-title').textContent = 'Add New Laboratory Worker / Employee';
        document.getElementById('emp-modal-sub').textContent = 'Upload employee photo, degrees, worker details, and generate printable ID card.';
        document.getElementById('emp-submit-text').textContent = 'Save Worker & Documents';
        document.getElementById('emp-photo-status').classList.add('hidden');
        document.getElementById('emp-degree-status').classList.add('hidden');
        modal.classList.remove('hidden');
        modal.scrollIntoView({ behavior: 'smooth' });
    }

    function openEditEmpModal(emp) {
        const modal = document.getElementById('add-emp-modal');
        if (!modal) return;
        document.getElementById('emp-id').value = emp.id || '';
        document.getElementById('emp-full-name').value = emp.full_name || '';
        document.getElementById('emp-emp-no').value = emp.emp_no || '';
        document.getElementById('emp-designation').value = emp.designation || '';
        document.getElementById('emp-department').value = emp.department || 'Laboratory';
        document.getElementById('emp-phone').value = emp.phone || '';
        document.getElementById('emp-cnic').value = emp.cnic || '';
        document.getElementById('emp-blood-group').value = emp.blood_group || '';
        document.getElementById('emp-joining-date').value = emp.joining_date || '';
        document.getElementById('emp-degree-title').value = emp.degree_title || '';

        const photoBox = document.getElementById('emp-photo-status');
        if (emp.has_photo) {
            photoBox.classList.remove('hidden');
        } else {
            photoBox.classList.add('hidden');
        }

        const degreeBox = document.getElementById('emp-degree-status');
        if (emp.has_degree) {
            degreeBox.classList.remove('hidden');
        } else {
            degreeBox.classList.add('hidden');
        }

        document.getElementById('emp-modal-title').textContent = 'Edit Doctor / Worker & Update Photo';
        document.getElementById('emp-modal-sub').textContent = 'Update details, upload or change photo for ID card badge, or update degree document.';
        document.getElementById('emp-submit-text').textContent = 'Update Doctor / Worker Details';
        modal.classList.remove('hidden');
        modal.scrollIntoView({ behavior: 'smooth' });
        document.getElementById('emp-full-name').focus();
    }
    </script>
    HTML;

    $content .= card(data_table(['Worker Profile', 'Worker ID', 'Department', 'Phone', 'Degree Document', 'Actions'], $empRows), 'overflow-hidden');
}

$pageTitle = $tab === 'attendance' ? 'Employee Attendance' : ($tab === 'license' ? 'Registration Certificate' : 'Staff & Workers');
$activeKey = $tab === 'attendance' ? 'staff-attendance' : 'staff-docs';

render_page($pageTitle, $portal, $activeKey, $content);
