<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [];
foreach (result_repo()->getCriticalResults() as $r) {
    $rows[] = [
        e($r['lab_no']),
        e($r['patient']),
        e($r['test']),
        e(($r['value'] ?? '') . ' ' . ($r['unit'] ?? '')),
        status_badge($r['flag'] ?? 'critical'),
        e($r['verified_at'] ? 'Verified' : 'Open'),
    ];
}

$content = page_header('Critical Results', 'Out-of-range values requiring follow-up.');
if ($rows === []) {
    $content .= card('<p class="p-4 text-sm text-slate-600">No critical-flagged results right now.</p>');
} else {
    $content .= card(data_table(['Lab No', 'Patient', 'Test', 'Result', 'Flag', 'Status'], $rows), 'overflow-hidden');
}

render_page('Critical Results', 'main-lab', 'critical', $content);
