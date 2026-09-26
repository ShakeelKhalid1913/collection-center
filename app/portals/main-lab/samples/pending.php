<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['sample_id'])) {
    result_repo()->updateSampleStatus($_POST['sample_id'], $_POST['status'] ?? 'received');
    header('Location: /portals/main-lab/samples/pending.php?updated=1');
    exit;
}

$rows = [];
foreach (mock('mock_samples') as $s) {
    if (($s['status'] ?? '') === 'completed') {
        continue;
    }
    $action = '<form method="post" style="display:inline">'
        . '<input type="hidden" name="sample_id" value="' . e($s['id']) . '">'
        . '<input type="hidden" name="status" value="received">'
        . '<button type="submit" class="btn btn-secondary" style="padding:0.35rem 0.75rem;font-size:0.75rem">Mark received</button>'
        . '</form>';
    $rows[] = [
        e($s['id']),
        e($s['lab_no']),
        e($s['patient']),
        e($s['sample']),
        status_badge($s['status']),
        e($s['received_at'] ?? '—'),
        $action,
    ];
}

$content = page_header('Pending Samples', 'Samples awaiting receipt or processing.');
if (isset($_GET['updated'])) {
    $content .= flash_success('Sample status updated.');
}
$content .= card(data_table(['Sample ID', 'Lab No', 'Patient', 'Type', 'Status', 'Received', 'Action'], $rows), 'overflow-hidden');

render_page('Pending Samples', 'main-lab', 'samples-pending', $content);
