<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

require_auth();

$user = current_user();
$orgId = $user['organization_id'] ?? 'ORG-001';
$id = trim((string)($_GET['id'] ?? ''));

$emp = employee_repo()->findById($id, $orgId);
if (!$emp) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem;text-align:center;"><h2>Employee not found</h2><p><a href="/portals/main-lab/staff/index.php">Back to staff list</a></p></body></html>';
    exit;
}

$settings = branding_settings($orgId);
$labName = e($settings['name'] ?? 'Lab Dash Pro Diagnostics');
$labPhone = e($settings['phone'] ?? '+92 42 111 222 333');
$labAddress = e($settings['address'] ?? 'Main Boulevard, Faisalabad');

$name = e((string)$emp['full_name']);
$desig = e((string)$emp['designation']);
$empNo = e((string)($emp['emp_no'] ?? $emp['emp_code'] ?? 'W-001'));
$dept = e((string)$emp['department']);
$blood = e((string)($emp['blood_group'] ?: '—'));
$cnic = e((string)($emp['cnic'] ?: '—'));
$phone = e((string)($emp['phone'] ?: '—'));
$joining = !empty($emp['joining_date']) ? date('M Y', strtotime((string)$emp['joining_date'])) : 'Jan 2024';

$hasPhoto = !empty($emp['has_photo']);
$photoUrl = $hasPhoto
    ? '/portals/main-lab/staff/photo.php?id=' . urlencode((string)$emp['id'])
    : '';

$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=90x90&margin=0&data=' . rawurlencode('EMP:' . $empNo . '|' . $name . '|' . $labName);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker ID Card · <?= $name ?> (<?= $empNo ?>)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; padding: 0 !important; }
            .card-sheet { box-shadow: none !important; margin: 0 !important; }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen p-4 sm:p-8 flex flex-col items-center justify-center font-sans">

    <!-- Action Toolbar -->
    <div class="no-print max-w-xl w-full mb-6 flex items-center justify-between bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
        <a href="/portals/main-lab/staff/index.php" class="text-xs font-bold text-slate-600 hover:text-slate-900 flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-left"></i> Back to Staff Directory
        </a>
        <button type="button" onclick="window.print()" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold shadow-md hover:bg-slate-800 transition-all flex items-center gap-2">
            <i class="fa-solid fa-print text-[#c2f13c]"></i> Print ID Badge
        </button>
    </div>

    <!-- Dual-Sided ID Badge (Front and Back side-by-side) -->
    <div class="card-sheet flex flex-wrap items-center justify-center gap-6">

        <!-- FRONT SIDE -->
        <div class="w-80 h-[470px] bg-white rounded-3xl overflow-hidden shadow-2xl border border-slate-200 flex flex-col relative">
            <!-- Top Branding Wave -->
            <div class="bg-gradient-to-r from-slate-950 via-teal-950 to-slate-900 text-white p-5 text-center relative">
                <div class="w-10 h-10 mx-auto mb-1.5 rounded-xl bg-teal-500/20 border border-teal-400/40 flex items-center justify-center text-teal-300 text-lg font-black">
                    <i class="fa-solid fa-microscope"></i>
                </div>
                <h1 class="text-xs font-black uppercase tracking-wider text-teal-300"><?= $labName ?></h1>
                <div class="text-[9px] uppercase tracking-widest text-slate-400 font-semibold">Official Staff Identification</div>
            </div>

            <!-- Photo & Identity -->
            <div class="flex-1 flex flex-col items-center justify-center px-4 pt-2 text-center">
                <!-- Avatar circle -->
                <div class="w-24 h-24 rounded-full border-4 border-teal-500 shadow-md overflow-hidden bg-slate-100 mb-3 flex items-center justify-center">
                    <?php if ($hasPhoto): ?>
                        <img src="<?= $photoUrl ?>" alt="<?= $name ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full bg-slate-900 text-[#c2f13c] flex items-center justify-center text-2xl font-black">
                            <?= user_initials($emp['full_name']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <h2 class="text-base font-black text-slate-900 leading-tight"><?= $name ?></h2>
                <div class="text-xs font-bold text-teal-700 mt-0.5"><?= $desig ?></div>
                <div class="inline-block mt-2 px-3 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[11px] font-bold">
                    <?= $dept ?>
                </div>

                <!-- Worker ID & QR -->
                <div class="mt-4 pt-3 border-t border-slate-100 w-full flex items-center justify-between px-3">
                    <div class="text-left">
                        <span class="text-[9px] uppercase font-bold text-slate-400 tracking-wider block">Worker ID</span>
                        <span class="font-mono text-base font-black text-slate-900"><?= $empNo ?></span>
                    </div>
                    <div>
                        <img src="<?= $qrUrl ?>" alt="Badge QR" class="w-12 h-12 object-contain rounded border border-slate-200">
                    </div>
                </div>
            </div>

            <!-- Bottom Color Accent -->
            <div class="h-3 bg-gradient-to-r from-teal-600 via-teal-500 to-[#c2f13c]"></div>
        </div>

        <!-- BACK SIDE -->
        <div class="w-80 h-[470px] bg-white rounded-3xl overflow-hidden shadow-2xl border border-slate-200 flex flex-col relative text-left">
            <!-- Top Bar -->
            <div class="bg-slate-900 text-white p-4 text-center">
                <div class="text-[10px] uppercase font-black tracking-widest text-teal-400">Security &amp; Emergency Info</div>
            </div>

            <!-- Details List -->
            <div class="flex-1 p-5 space-y-3 text-xs">
                <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                    <span class="text-slate-500 font-semibold">Blood Group</span>
                    <strong class="font-mono text-rose-700 text-sm font-black"><?= $blood ?></strong>
                </div>
                <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                    <span class="text-slate-500 font-semibold">CNIC No</span>
                    <strong class="font-mono text-slate-800"><?= $cnic ?></strong>
                </div>
                <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                    <span class="text-slate-500 font-semibold">Emergency Phone</span>
                    <strong class="font-mono text-slate-800"><?= $phone ?></strong>
                </div>
                <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                    <span class="text-slate-500 font-semibold">Member Since</span>
                    <strong class="text-slate-800"><?= $joining ?></strong>
                </div>

                <!-- Terms -->
                <div class="pt-2">
                    <p class="text-[10px] text-slate-400 leading-relaxed">
                        This card is property of <strong><?= $labName ?></strong> and must be worn during working hours. If found, please return to:
                    </p>
                    <p class="text-[10px] font-bold text-slate-700 mt-1">
                        <?= $labAddress ?> · <?= $labPhone ?>
                    </p>
                </div>

                <!-- Signature Placeholder -->
                <div class="pt-4 flex items-center justify-between">
                    <div>
                        <div class="border-t border-slate-400 w-24 pt-0.5 text-[9px] text-slate-500 text-center font-bold">Holder Sign</div>
                    </div>
                    <div class="text-right">
                        <div class="border-t border-slate-400 w-28 ml-auto pt-0.5 text-[9px] text-slate-500 text-center font-bold">Authorized Signatory</div>
                    </div>
                </div>
            </div>

            <!-- Bottom Color Accent -->
            <div class="h-3 bg-gradient-to-r from-slate-900 via-teal-900 to-slate-950"></div>
        </div>

    </div>

</body>
</html>
