<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';
require_once __DIR__ . '/../../includes/barcode.php';

require_auth('main-lab');

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$msg = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_transit') {
    $labNo = trim((string)($_POST['lab_no'] ?? ''));
    $newStatus = trim((string)($_POST['transit_status'] ?? ''));
    $valid = ['collected', 'in_transit', 'testing', 'result_entered', 'verified'];
    if (in_array($newStatus, $valid, true) && $labNo !== '') {
        lab_repo()->updateTransitStatus($labNo, $newStatus);
        audit_log('UPDATE_TRANSIT_STATUS', 'lab_entries', $labNo, "Transit lifecycle status changed to {$newStatus}");
        $msg = flash_success("Specimen [{$labNo}] updated to: " . strtoupper(str_replace('_', ' ', $newStatus)));
    }
}

$entries = lab_repo()->getAll($orgId, 100);

// Chain milestones definition
$chainSteps = [
    'collected' => ['label' => 'Collection Center', 'icon' => 'fa-hospital-user', 'color' => 'amber'],
    'in_transit' => ['label' => 'Main Lab Transit', 'icon' => 'fa-truck-fast', 'color' => 'blue'],
    'testing' => ['label' => 'Testing / Processing', 'icon' => 'fa-vial-virus', 'color' => 'purple'],
    'result_entered' => ['label' => 'Result Entry', 'icon' => 'fa-keyboard', 'color' => 'indigo'],
    'verified' => ['label' => 'Verified Report Generated', 'icon' => 'fa-file-circle-check', 'color' => 'emerald'],
];

$rows = [];
foreach ($entries as $e) {
    $labNo = (string)($e['lab_no'] ?? '');
    $curStatus = (string)($e['transit_status'] ?? 'collected');
    if (!isset($chainSteps[$curStatus])) {
        $curStatus = 'collected';
    }
    $pName = (string)($e['patient_name'] ?? '—');
    $tests = (string)($e['tests'] ?? '—');

    // Build visual pipeline steps
    $pipelineHtml = '<div class="flex items-center gap-1">';
    $keys = array_keys($chainSteps);
    $curIdx = array_search($curStatus, $keys, true);

    foreach ($keys as $idx => $k) {
        $step = $chainSteps[$k];
        $isDone = $idx <= $curIdx;
        $isCurrent = $idx === $curIdx;
        $bg = $isCurrent
            ? 'bg-blue-600 text-white shadow-sm ring-2 ring-blue-300'
            : ($isDone ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-500');

        $pipelineHtml .= '<span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-[10px] ' . $bg . '" title="' . e($step['label']) . '">'
            . '<i class="fa-solid ' . $step['icon'] . '"></i>'
            . '</span>';
        if ($idx < count($keys) - 1) {
            $pipeColor = $idx < $curIdx ? 'bg-emerald-400' : 'bg-slate-200';
            $pipelineHtml .= '<span class="w-2.5 h-0.5 ' . $pipeColor . '"></span>';
        }
    }
    $pipelineHtml .= '</div>';

    // Status dropdown form
    $opts = '';
    foreach ($chainSteps as $val => $meta) {
        $sel = $val === $curStatus ? ' selected' : '';
        $opts .= '<option value="' . $val . '"' . $sel . '>' . e($meta['label']) . '</option>';
    }

    $actionForm = <<<HTML
    <form method="post" class="flex items-center gap-2 m-0">
        <input type="hidden" name="action" value="update_transit">
        <input type="hidden" name="lab_no" value="{$labNo}">
        <select name="transit_status" class="field text-xs py-1 px-2 w-44" onchange="this.form.submit()">
            {$opts}
        </select>
        <a href="/portals/collection-center/barcode-label.php?lab_no={$labNo}" target="_blank" class="btn btn-secondary text-xs py-1 px-2" title="Print Barcode Sticker"><i class="fa-solid fa-barcode"></i></a>
        <a href="/portals/main-lab/results/entry.php?lab_no={$labNo}" class="btn btn-primary text-xs py-1 px-2" title="Enter Results"><i class="fa-solid fa-keyboard"></i></a>
    </form>
    HTML;

    $rows[] = [
        '<span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded">' . e($labNo) . '</span>',
        '<span class="font-semibold text-slate-800">' . e($pName) . '</span>',
        '<span class="text-xs text-slate-600">' . e(normalize_tests_list($tests)) . '</span>',
        $pipelineHtml,
        '<span class="text-xs font-bold uppercase tracking-wider text-slate-700">' . e($chainSteps[$curStatus]['label']) . '</span>',
        $actionForm,
    ];
}

$header = page_header(
    'Sample Transit & Barcode Chain',
    'Full lifecycle specimen audit from collection center transit to main lab diagnostic report.'
);

$content = $msg . $header;
$content .= card(
    '<div class="p-4 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 text-white rounded-2xl mb-4">'
    . '<div class="text-xs uppercase font-extrabold text-[#c2f13c] tracking-wider mb-1">5-Stage Specimen Lifecycle Chain</div>'
    . '<div class="flex flex-wrap items-center gap-3 text-xs">'
    . '<span><i class="fa-solid fa-hospital-user text-amber-400"></i> 1. Collection Center</span> &rarr; '
    . '<span><i class="fa-solid fa-truck-fast text-blue-400"></i> 2. Main Lab Transit</span> &rarr; '
    . '<span><i class="fa-solid fa-vial-virus text-purple-400"></i> 3. Testing / Processing</span> &rarr; '
    . '<span><i class="fa-solid fa-keyboard text-indigo-400"></i> 4. Result Entry</span> &rarr; '
    . '<span><i class="fa-solid fa-file-circle-check text-emerald-400"></i> 5. Verified Report Generated</span>'
    . '</div>'
    . '</div>'
    . data_table(['Lab No', 'Patient', 'Tests', 'Lifecycle Progress', 'Current Stage', 'Dispatch / Actions'], $rows),
    'overflow-hidden'
);

render_page('Sample Tracking', 'main-lab', 'sample-tracking', $content);
