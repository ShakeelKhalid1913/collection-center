<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';
$q = trim((string)($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $labNo = trim((string)($_POST['lab_no'] ?? ''));
    if ($labNo === '') {
        $message = flash_error('Lab number is required.');
    } else {
        $ok = lab_repo()->deleteByLabNo($labNo, $orgId);
        $message = $ok
            ? flash_success('Entry ' . $labNo . ' deleted (including results/samples).')
            : flash_error('Could not delete entry. Check lab number and try again.');
    }
}

$entries = lab_repo()->searchEntries($orgId, $q, null, null, 150);
$rows = [];
foreach ($entries as $e) {
    $labNo = (string)$e['lab_no'];
    $rows[] = [
        '<strong class="font-mono text-teal-800">' . e($labNo) . '</strong>',
        e((string)($e['patient_name'] ?? '—')),
        e((string)($e['tests'] ?? '—')),
        e((string)($e['doctor'] ?? '—')),
        status_badge((string)($e['status'] ?? 'pending')),
        e(format_money((float)($e['amount'] ?? 0))),
        e(format_date((string)($e['created_at'] ?? ''))),
        '<form method="post" onsubmit="return confirm(\'Permanently delete entry ' . e($labNo) . '?\');">'
            . '<input type="hidden" name="action" value="delete">'
            . '<input type="hidden" name="lab_no" value="' . e($labNo) . '">'
            . '<button type="submit" class="btn btn-secondary text-xs text-rose-700"><i class="fa-solid fa-trash mr-1"></i> Delete</button>'
            . '</form>',
    ];
}

$content = page_header(
    'Delete Entry',
    'Find and permanently remove entered patient lab records.'
);
$content .= $message;
$content .= card(
    '<form method="get" class="flex flex-wrap items-end gap-3 p-4">' .
    '<div class="min-w-[220px] flex-1">' .
    '<label class="field-label" for="q">Search lab no / patient / doctor</label>' .
    '<input type="search" id="q" name="q" class="field" value="' . e($q) . '" placeholder="e.g. L-2026-0891 or Ayesha">' .
    '</div>' .
    '<button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Search</button>' .
    '</form>'
);

$content .= '<div class="mt-4">' . card(
    panel_head('Patient entries (' . count($entries) . ')') .
    (count($rows) > 0
        ? data_table(['Lab No', 'Patient', 'Tests', 'Doctor', 'Status', 'Amount', 'Date', ''], $rows)
        : '<p class="p-4 text-sm text-slate-500">No entries found.</p>'),
    'overflow-hidden'
) . '</div>';

render_page('Delete Entry', 'main-lab', 'delete-entry', $content);
