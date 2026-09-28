<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $by = current_user()['name'] ?? 'Pathologist';

    if (!empty($_POST['lab_no'])) {
        result_repo()->verifyAllByLabNo(trim((string)$_POST['lab_no']), $by);
        header('Location: /portals/main-lab/results/verification.php?verified=1&lab_no=' . urlencode(trim((string)$_POST['lab_no'])));
        exit;
    }

    if (!empty($_POST['result_id'])) {
        result_repo()->verifyResult((string)$_POST['result_id'], $by);
        header('Location: /portals/main-lab/results/verification.php?verified=1');
        exit;
    }
}

$rows = [];
foreach (result_repo()->getUnverifiedResults() as $r) {
    $labNo = (string)($r['lab_no'] ?? '');
    $filled = (int)($r['filled_count'] ?? 0);
    $total = (int)($r['param_count'] ?? 0);

    $actions = '<div class="flex flex-wrap gap-2">'
        . '<a href="/portals/main-lab/results/entry.php?lab_no=' . urlencode($labNo) . '" class="btn btn-secondary text-xs"><i class="fa-solid fa-keyboard mr-1"></i> Review</a>'
        . '<a href="/portals/main-lab/reports/preview.php?lab_no=' . urlencode($labNo) . '" class="btn btn-secondary text-xs"><i class="fa-solid fa-eye mr-1"></i> Report</a>'
        . '<form method="post" class="inline">'
        . '<input type="hidden" name="lab_no" value="' . e($labNo) . '">'
        . '<button type="submit" class="btn btn-primary text-xs"><i class="fa-solid fa-circle-check mr-1"></i> Verify &amp; release</button>'
        . '</form>'
        . '</div>';

    $rows[] = [
        '<strong class="font-mono text-teal-800">' . e($labNo) . '</strong>',
        e((string)($r['patient'] ?? '—')),
        e((string)($r['test'] ?? '—')),
        e($filled . ' / ' . $total . ' values entered'),
        $actions,
    ];
}

$content = page_header(
    'Verification',
    'Sign off visits that already have results entered. After verify, the report is ready to print.'
);

if (isset($_GET['verified'])) {
    $labHint = trim((string)($_GET['lab_no'] ?? ''));
    $msg = $labHint !== ''
        ? 'Lab No ' . $labHint . ' verified and released.'
        : 'Result verified and released.';
    $content .= flash_success($msg);
}

if ($rows === []) {
    $content .= card(
        '<p class="p-6 text-slate-600">Nothing waiting for verification. Enter results first, then come back here to sign off.</p>' .
        '<div class="px-6 pb-6"><a class="btn btn-primary" href="/portals/main-lab/results/entry.php">Enter Results</a></div>'
    );
} else {
    $content .= card(
        data_table(['Lab No', 'Patient', 'Tests', 'Progress', 'Action'], $rows),
        'overflow-hidden'
    );
}

render_page('Verification', 'main-lab', 'verification', $content);
