<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$patients = patient_repo()->getAll($orgId);

$rows = [];
foreach ($patients as $p) {
    $pId = $p['patient_no'] ?? $p['id'];
    $name = trim(($p['title'] ?? '') . ' ' . ($p['full_name'] ?? $p['name'] ?? ''));
    $phone = $p['phone'] ?? '—';
    $ageGender = trim(($p['age'] ?? '—') . ' yrs / ' . ($p['gender'] ?? '—'), ' /');

    $actions = '<div class="flex items-center gap-1.5 whitespace-nowrap">' .
        '<a href="/portals/main-lab/patients/history.php?id=' . urlencode($pId) . '" class="btn btn-secondary text-xs px-2.5 py-1 font-semibold"><i class="fa-solid fa-clock-rotate-left mr-1"></i> History</a>' .
        '<a href="/portals/main-lab/lab-entries/new.php?patient_id=' . urlencode($pId) . '" class="btn btn-primary text-xs px-2.5 py-1 font-semibold"><i class="fa-solid fa-plus mr-1"></i> Book Tests</a>' .
        '</div>';

    $rows[] = [
        '<span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md text-xs">' . e($pId) . '</span>',
        '<span class="font-bold text-slate-800">' . e($name) . '</span>',
        '<span class="text-xs font-mono text-slate-600">' . e($phone) . '</span>',
        '<span class="text-xs text-slate-500 font-medium">' . e($ageGender) . '</span>',
        '<span class="text-xs text-slate-500">' . e((string)($p['city'] ?? '—')) . '</span>',
        $actions,
    ];
}

$content = page_header(
    'Patient Records',
    'Shared patient directory. Click History to view all tests or Book / Assign Tests to order new tests.',
    btn_primary('/portals/main-lab/patients/register.php', 'Register New Patient')
);

$content .= card(
    panel_head('All Registered Patients (' . count($patients) . ')') .
    data_table(['MR Number', 'Full Name', 'Phone', 'Age / Gender', 'City', 'Actions'], $rows),
    'overflow-hidden'
);

render_page('Patient Records', 'main-lab', 'records', $content);
