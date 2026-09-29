<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';
$dateFrom = trim((string)($_GET['from'] ?? date('Y-m-01')));
$dateTo = trim((string)($_GET['to'] ?? date('Y-m-d')));
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        $id = trim((string)($_POST['id'] ?? ''));
        $ok = $id !== '' && doctor_share_repo()->delete($id, $orgId);
        $message = $ok ? flash_success('Doctor share removed.') : flash_error('Could not remove doctor share.');
    } else {
        $ok = doctor_share_repo()->save([
            'id' => trim((string)($_POST['id'] ?? '')),
            'organization_id' => $orgId,
            'doctor_name' => trim((string)($_POST['doctor_name'] ?? '')),
            'commission_percent' => (float)($_POST['commission_percent'] ?? 0),
            'notes' => trim((string)($_POST['notes'] ?? '')),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ]);
        $message = $ok
            ? flash_success('Doctor commission saved.')
            : flash_error('Doctor name is required.');
    }
}

if (!empty($_GET['edit'])) {
    $editing = doctor_share_repo()->find((string)$_GET['edit']);
    if ($editing && (string)($editing['organization_id'] ?? '') !== $orgId) {
        $editing = null;
    }
}

$shares = doctor_share_repo()->getAll($orgId);
$summary = doctor_share_repo()->commissionSummary($orgId, $dateFrom, $dateTo);

$shareRows = [];
foreach ($shares as $s) {
    $id = (string)$s['id'];
    $shareRows[] = [
        '<strong>' . e((string)$s['doctor_name']) . '</strong>',
        e(number_format((float)$s['commission_percent'], 1) . '%'),
        e((string)($s['notes'] ?: '—')),
        !empty($s['is_active']) ? status_badge('active') : '<span class="badge-default">Inactive</span>',
        '<div class="flex flex-wrap gap-2">' .
            '<a href="?edit=' . urlencode($id) . '&from=' . urlencode($dateFrom) . '&to=' . urlencode($dateTo) . '" class="btn btn-secondary text-xs"><i class="fa-solid fa-pen mr-1"></i> Edit</a>' .
            '<form method="post" onsubmit="return confirm(\'Remove this doctor share?\');">' .
            '<input type="hidden" name="action" value="delete">' .
            '<input type="hidden" name="id" value="' . e($id) . '">' .
            '<button type="submit" class="btn btn-secondary text-xs text-rose-700"><i class="fa-solid fa-trash mr-1"></i></button>' .
            '</form></div>',
    ];
}

$summaryRows = [];
$totalCommission = 0.0;
foreach ($summary as $row) {
    $totalCommission += (float)$row['commission_amount'];
    $summaryRows[] = [
        e((string)$row['doctor']),
        e((string)$row['visits']),
        e(format_money((float)$row['gross'])),
        e(format_money((float)$row['collected'])),
        $row['configured']
            ? e(number_format((float)$row['commission_percent'], 1) . '%')
            : '<span class="text-amber-700 text-xs">Not set</span>',
        '<strong>' . e(format_money((float)$row['commission_amount'])) . '</strong>',
    ];
}

$valName = (string)($editing['doctor_name'] ?? '');
$valPercent = (string)($editing['commission_percent'] ?? '10');
$valNotes = (string)($editing['notes'] ?? '');
$valActive = $editing === null || !empty($editing['is_active']);

$content = page_header(
    "Doctor's Share",
    'Set referral commission rates and calculate doctor cuts from collected bill amounts.'
);
$content .= $message;

$content .= card(
    panel_head($editing ? 'Edit doctor commission' : 'Add doctor commission') .
    '<form method="post" class="space-y-4 p-4 sm:p-6">' .
    '<input type="hidden" name="action" value="save">' .
    ($editing ? '<input type="hidden" name="id" value="' . e((string)$editing['id']) . '">' : '') .
    form_field('Doctor name', 'doctor_name', 'text', $valName, 'Must match referring doctor on entries') .
    form_field('Commission %', 'commission_percent', 'number', $valPercent, '', false, 'min="0" max="100" step="0.1"') .
    textarea_field('Notes', 'notes', $valNotes, 'Optional agreement notes', 2, true) .
    '<label class="flex items-center gap-2 text-sm text-slate-700">' .
    '<input type="checkbox" name="is_active" value="1" class="rounded border-slate-300"' . ($valActive ? ' checked' : '') . '> Active' .
    '</label>' .
    '<div class="flex flex-wrap gap-2">' .
    btn_submit($editing ? 'Update commission' : 'Save commission') .
    ($editing ? btn_secondary('/portals/main-lab/doctors-share.php', 'Cancel') : '') .
    '</div></form>'
);

$content .= '<div class="mt-4">' . card(
    panel_head('Configured rates (' . count($shares) . ')') .
    (count($shareRows) > 0
        ? data_table(['Doctor', 'Rate', 'Notes', 'Status', ''], $shareRows)
        : '<p class="p-4 text-sm text-slate-500">No doctor rates configured yet.</p>'),
    'overflow-hidden'
) . '</div>';

$content .= '<div class="mt-4">' . card(
    panel_head('Commission summary', 'Based on collected payments') .
    '<form method="get" class="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4">' .
    '<div><label class="field-label" for="from">From</label><input type="date" id="from" name="from" class="field" value="' . e($dateFrom) . '"></div>' .
    '<div><label class="field-label" for="to">To</label><input type="date" id="to" name="to" class="field" value="' . e($dateTo) . '"></div>' .
    ($editing ? '<input type="hidden" name="edit" value="' . e((string)$editing['id']) . '">' : '') .
    '<button type="submit" class="btn btn-primary"><i class="fa-solid fa-calculator"></i> Calculate</button>' .
    '</form>' .
    (count($summaryRows) > 0
        ? data_table(['Doctor', 'Visits', 'Gross', 'Collected', 'Rate', 'Share due'], $summaryRows) .
          '<p class="border-t border-slate-100 px-4 py-3 text-sm font-semibold text-slate-800">Total share due: ' . e(format_money($totalCommission)) . '</p>'
        : '<p class="p-4 text-sm text-slate-500">No lab entries in this date range.</p>'),
    'overflow-hidden'
) . '</div>';

render_page("Doctor's Share", 'main-lab', 'doctors-share', $content);
