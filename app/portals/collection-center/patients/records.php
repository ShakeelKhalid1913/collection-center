<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [];
foreach (mock('mock_patients') as $p) {
    $display = trim(($p['title'] ?? '') . ' ' . $p['name']);
    $rows[] = [
        e($p['id']),
        e($display),
        e($p['relation'] ?? '—'),
        e($p['phone']),
        e($p['cnic'] !== '' ? $p['cnic'] : '—'),
        e($p['blood_group'] ?? '—'),
        e((string) $p['age'] . ' / ' . $p['gender']),
        e($p['branch'] ?? '—'),
        e(format_date($p['registered'] ?? null)),
        '<div class="flex flex-wrap gap-2">' .
        '<a href="/portals/collection-center/lab-entries/new.php?patient_id=' . urlencode($p['id']) . '" class="btn btn-primary text-xs"><i class="fa-solid fa-plus mr-1"></i> Assign Tests</a>' .
        '<a href="/portals/collection-center/patients/history.php?id=' . urlencode($p['id']) . '" class="btn btn-secondary text-xs"><i class="fa-solid fa-clock-rotate-left mr-1"></i> History</a>' .
        '</div>',
    ];
}

$filters = filter_bar([
    form_field('Search', 'q', 'search', null, 'Name, phone, CNIC, patient ID…'),
    select_field('Gender', 'gender', ['' => 'All', 'Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other']),
    select_field('Blood group', 'bg', [
        '' => 'All',
        'A+' => 'A+', 'A-' => 'A-', 'B+' => 'B+', 'B-' => 'B-',
        'AB+' => 'AB+', 'AB-' => 'AB-', 'O+' => 'O+', 'O-' => 'O-',
    ]),
    select_field('Branch', 'branch', ['' => 'All branches', 'CC-01' => 'Gulberg CC-01', 'CC-02' => 'Model Town CC-02']),
    select_field('Registered', 'when', [
        '' => 'Any date',
        'today' => 'Today',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
    ]),
    select_field('Has email', 'email', ['' => 'Any', 'yes' => 'Yes', 'no' => 'No']),
    select_field('Has CNIC', 'cnic', ['' => 'Any', 'yes' => 'On file', 'no' => 'Missing']),
    select_field('Sort by', 'sort', [
        'recent' => 'Newest first',
        'name' => 'Name A–Z',
        'age' => 'Age',
    ]),
]);

$content = page_header(
    'Patient Records',
    'Filter by demographics, branch, and registration date.',
    btn_primary('/portals/collection-center/patients/quick-register.php', 'Quick registration') . ' ' .
    btn_secondary('/portals/collection-center/patients/register.php', 'Full registration')
);

$content .= card(
    $filters .
    data_table(
        ['ID', 'Name', 'Relation', 'Phone', 'CNIC', 'Blood', 'Age/Sex', 'Branch', 'Registered', 'Actions'],
        $rows
    ),
    'overflow-hidden'
);

render_page('Patient Records', 'collection-center', 'records', $content);
