<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$rows = [];
foreach (mock('mock_imaging') as $scan) {
    if (!in_array($scan['status'], ['pending', 'in_progress'], true)) {
        continue;
    }
    $rows[] = [
        '<span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md text-xs">' . e($scan['scan_no']) . '</span>',
        '<span class="font-bold text-slate-800">' . e($scan['patient']) . '</span>',
        '<span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">' . e($scan['modality']) . '</span>',
        '<span class="text-xs text-slate-600 font-medium">' . e($scan['study']) . '</span>',
        status_badge($scan['status']),
        '<a href="/portals/imaging/results.php?scan_no=' . urlencode($scan['scan_no']) . '" class="btn btn-primary text-xs px-3 py-1.5 font-semibold"><i class="fa-solid fa-pen-nib mr-1"></i> Findings</a>',
    ];
}

$content = page_header('Pending Scans', 'Track scan status through reporting.');
if (isset($_GET['created'])) {
    $content .= flash_success('Study created.');
}
$content .= card(data_table(['Scan No', 'Patient', 'Modality', 'Study', 'Status', 'Action'], $rows), 'overflow-hidden');

render_page('Pending Scans', 'imaging', 'pending-scans', $content);
