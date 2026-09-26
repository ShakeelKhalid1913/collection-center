<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
$resultId = $_GET['id'] ?? $_POST['result_id'] ?? '';
$result = $resultId !== '' ? result_repo()->findResult($resultId) : null;

if (!$result) {
    $pending = result_repo()->getPendingResults();
    $result = $pending[0] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['result_id'])) {
    $value = trim($_POST['value'] ?? '');
    $flag = $_POST['flag'] ?? '';
    $ok = result_repo()->saveResultEntry($_POST['result_id'], [
        'parameter' => $_POST['parameter'] ?? 'Result',
        'value' => $value,
        'unit' => $_POST['unit'] ?? '',
        'flag' => $flag !== '' ? $flag : null,
    ]);
    if ($ok && !empty($_POST['send_verify'])) {
        header('Location: /portals/main-lab/results/verification.php');
        exit;
    }
    $message = $ok ? flash_success('Result saved.') : flash_error('Could not save result.');
    $result = result_repo()->findResult($_POST['result_id']);
}

$labNo = $result['lab_no'] ?? '—';
$patient = $result['patient'] ?? '—';
$test = $result['test'] ?? '—';
$rid = $result['id'] ?? '';

$content = page_header('Results Entry', "Lab No {$labNo} · {$patient} · {$test}");
$content .= $message;

if (!$result) {
    $content .= card('<p class="p-4 text-sm text-slate-600">No pending results. Create a lab entry from Collection Center first.</p>');
} else {
    $content .= card(
        '<form method="post">' .
        '<input type="hidden" name="result_id" value="' . e($rid) . '">' .
        '<div class="overflow-x-auto">' .
        '<table class="min-w-full text-sm"><thead class="bg-slate-50"><tr>' .
        '<th class="px-4 py-3 text-left">Parameter</th><th class="px-4 py-3 text-left">Result</th><th class="px-4 py-3 text-left">Unit</th><th class="px-4 py-3 text-left">Flag</th>' .
        '</tr></thead><tbody>' .
        '<tr class="border-t">' .
        '<td class="px-4 py-3"><input class="field" name="parameter" value="' . e($result['parameter'] ?? $test) . '"></td>' .
        '<td class="px-4 py-3"><input class="field w-28" name="value" value="' . e($result['value'] ?? '') . '" required></td>' .
        '<td class="px-4 py-3"><input class="field w-28" name="unit" value="' . e($result['unit'] ?? '') . '"></td>' .
        '<td class="px-4 py-3"><select class="field" name="flag">' .
        '<option value="">Normal</option>' .
        '<option value="H"' . (($result['flag'] ?? '') === 'H' ? ' selected' : '') . '>High</option>' .
        '<option value="L"' . (($result['flag'] ?? '') === 'L' ? ' selected' : '') . '>Low</option>' .
        '<option value="critical"' . (($result['flag'] ?? '') === 'critical' ? ' selected' : '') . '>Critical</option>' .
        '</select></td>' .
        '</tr>' .
        '</tbody></table></div>' .
        '<div class="border-t border-gray-300 p-4 flex flex-wrap gap-2">' .
        btn_submit('Save draft') .
        '<button type="submit" name="send_verify" value="1" class="btn btn-secondary"><i class="fa-solid fa-user-check"></i> Save &amp; send to verification</button>' .
        '</div></form>'
    );
}

render_page('Results Entry', 'main-lab', 'results-entry', $content);
