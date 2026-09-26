<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['result_id'])) {
    $by = current_user()['name'] ?? 'Pathologist';
    result_repo()->verifyResult($_POST['result_id'], $by);
    header('Location: /portals/main-lab/results/verification.php?verified=1');
    exit;
}

$rows = [];
foreach (result_repo()->getUnverifiedResults() as $r) {
    $action = '<form method="post" style="display:inline">'
        . '<input type="hidden" name="result_id" value="' . e($r['id']) . '">'
        . '<button type="submit" class="btn btn-primary" style="padding:0.35rem 0.75rem;font-size:0.75rem">Verify &amp; release</button>'
        . '</form>';
    $rows[] = [
        e($r['lab_no']),
        e($r['patient']),
        e($r['test']),
        e(($r['parameter'] ?? '') . ' = ' . ($r['value'] ?? '') . ' ' . ($r['unit'] ?? '')),
        $action,
    ];
}

$content = page_header('Verification', 'Pathologist sign-off before report generation.');
if (isset($_GET['verified'])) {
    $content .= flash_success('Result verified and released.');
}
$content .= card(data_table(['Lab No', 'Patient', 'Panel', 'Entered values', 'Action'], $rows), 'overflow-hidden');

render_page('Verification', 'main-lab', 'verification', $content);
