<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';

// Handle quick doctor switch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_doctor'])) {
    $scanNoToUpdate = trim((string)($_POST['scan_no'] ?? ''));
    $customDoc = trim((string)($_POST['custom_doctor'] ?? ''));
    $selectedDoc = trim((string)($_POST['selected_doctor'] ?? ''));
    $newDoc = $customDoc !== '' ? $customDoc : ($selectedDoc !== '' ? resolve_doctor_name($selectedDoc) : '');

    if ($scanNoToUpdate !== '' && $newDoc !== '') {
        result_repo()->updateImagingDoctor($scanNoToUpdate, $newDoc);
        header('Location: /portals/imaging/pending-scans.php?doc_switched=1');
        exit;
    }
}

$allDoctors = mock('mock_doctors');
$allScans = result_repo()->getImagingScans($orgId);

$rows = [];
foreach ($allScans as $scan) {
    if (!in_array($scan['status'] ?? '', ['pending', 'in_progress'], true)) {
        continue;
    }
    $scanNo = (string)($scan['scan_no'] ?? '');
    $currentDoc = (string)($scan['radiologist'] ?? ($scan['doctor'] ?? 'Dr. Imran Sheikh'));

    // Quick doctor switcher modal trigger or dropdown
    $docOptsHtml = '';
    foreach ($allDoctors as $d) {
        $sel = (strcasecmp($d['name'], $currentDoc) === 0) ? ' selected' : '';
        $docOptsHtml .= '<option value="' . e($d['name']) . '"' . $sel . '>' . e($d['name']) . '</option>';
    }

    $docCell = <<<HTML
<div class="flex items-center gap-1.5">
    <form method="post" class="inline-flex items-center gap-1">
        <input type="hidden" name="scan_no" value="{$scanNo}">
        <input type="hidden" name="switch_doctor" value="1">
        <select name="selected_doctor" onchange="this.form.submit()" class="text-xs font-semibold py-1 px-2 rounded-lg border border-slate-200 bg-white hover:border-slate-300 text-slate-800 cursor-pointer shadow-2xs max-w-[11rem]" title="Switch reporting doctor">
            {$docOptsHtml}
        </select>
    </form>
</div>
HTML;

    $actionButtons = <<<HTML
<div class="flex items-center gap-1.5">
    <a href="/portals/imaging/receipt.php?scan_no={$scanNo}" class="btn btn-secondary text-xs px-2.5 py-1 font-semibold text-slate-700" title="Print Token Slip">
        <i class="fa-solid fa-receipt mr-1 text-slate-500"></i> Slip
    </a>
    <a href="/portals/imaging/results.php?scan_no={$scanNo}" class="btn btn-primary text-xs px-3 py-1 font-semibold" title="Enter Findings">
        <i class="fa-solid fa-pen-nib mr-1"></i> Findings
    </a>
</div>
HTML;

    $rows[] = [
        '<span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md text-xs">' . e($scanNo) . '</span>',
        '<span class="font-bold text-slate-800">' . e($scan['patient'] ?? 'Unknown') . '</span>',
        '<span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">' . e($scan['modality'] ?? '—') . '</span>',
        '<span class="text-xs text-slate-700 font-medium">' . e($scan['study'] ?? '—') . '</span>',
        $docCell,
        status_badge($scan['status'] ?? 'pending'),
        $actionButtons,
    ];
}

$content = page_header(
    'Pending Studies',
    'Track diagnostic scans, switch reporting doctors, print token slips, and enter findings.',
    btn_primary('/portals/imaging/new-scan.php', 'New Study', 'fa-solid fa-plus')
);

if (isset($_GET['created'])) {
    $content .= flash_success('Study booked successfully.');
}
if (isset($_GET['doc_switched'])) {
    $content .= flash_success('Reporting doctor successfully updated / switched.');
}

$content .= card(
    data_table(['Scan No', 'Patient', 'Modality', 'Study', 'Reporting Doctor', 'Status', 'Actions'], $rows),
    'overflow-hidden'
);

render_page('Pending Scans', 'imaging', 'pending-scans', $content);
