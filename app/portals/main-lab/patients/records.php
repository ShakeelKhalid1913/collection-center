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

    $actions = '<div class="flex flex-wrap gap-2">' .
        '<a href="/portals/main-lab/patients/history.php?id=' . urlencode($pId) . '" class="btn btn-secondary text-xs"><i class="fa-solid fa-clock-rotate-left mr-1"></i> History</a>' .
        '<a href="/portals/main-lab/lab-entries/new.php?patient_id=' . urlencode($pId) . '" class="btn btn-primary text-xs"><i class="fa-solid fa-plus mr-1"></i> Book / Assign Tests</a>' .
        '</div>';

    $rows[] = [
        '<strong class="text-teal-800 font-mono">' . e($pId) . '</strong>',
        '<span class="font-semibold text-slate-800">' . e($name) . '</span>',
        e($phone),
        e($ageGender),
        e($p['city'] ?? '—'),
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
