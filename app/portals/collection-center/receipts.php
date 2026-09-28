<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$labNo = trim((string)($_GET['lab_no'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

$ctx = load_document_context($labNo !== '' ? $labNo : null);
$settings = $ctx['settings'];
$patient = $ctx['patient'];
$entry = $ctx['entry'];
$currentLab = (string)($entry['lab_no'] ?? '');

$allEntries = lab_repo()->getAll($orgId, 120);
$dropdownOpts = '';
foreach ($allEntries as $ae) {
    $sel = (($ae['lab_no'] ?? '') === $currentLab) ? ' selected' : '';
    $dropdownOpts .= '<option value="' . e((string)$ae['lab_no']) . '"' . $sel . '>'
        . e((string)$ae['lab_no']) . ' — ' . e((string)($ae['patient_name'] ?? ''))
        . ' (' . e(normalize_tests_list((string)($ae['tests'] ?? ''))) . ')</option>';
}

$visitRows = '';
if ($q !== '' && $labNo === '') {
    $seen = [];
    foreach (patient_repo()->search($q, $orgId) as $p) {
        $pid = (string)($p['patient_no'] ?? $p['id'] ?? '');
        $visits = lab_repo()->getByPatientId((string)($p['id'] ?? ''));
        if ($visits === [] && $pid !== '') {
            $visits = lab_repo()->getByPatientId($pid);
        }
        foreach ($visits as $ae) {
            $ln = (string)($ae['lab_no'] ?? '');
            if ($ln === '' || isset($seen[$ln])) {
                continue;
            }
            $seen[$ln] = true;
            $visitRows .= '<tr class="border-b border-slate-100">'
                . '<td class="px-3 py-2 font-mono text-teal-800 font-semibold">' . e($ln) . '</td>'
                . '<td class="px-3 py-2 font-semibold">' . e((string)($ae['patient_name'] ?? ($p['full_name'] ?? ''))) . '</td>'
                . '<td class="px-3 py-2 text-sm">' . e(normalize_tests_list((string)($ae['tests'] ?? ''))) . '</td>'
                . '<td class="px-3 py-2"><a class="btn btn-primary text-xs" href="/portals/collection-center/receipts.php?lab_no=' . urlencode($ln) . '&q=' . urlencode($q) . '">Open bill</a></td>'
                . '</tr>';
        }
    }
    foreach (lab_repo()->getAll($orgId, 200) as $ae) {
        $blob = strtolower(($ae['lab_no'] ?? '') . ' ' . ($ae['patient_name'] ?? '') . ' ' . ($ae['patient_id'] ?? ''));
        if (!str_contains($blob, strtolower($q))) {
            continue;
        }
        $ln = (string)($ae['lab_no'] ?? '');
        if ($ln === '' || isset($seen[$ln])) {
            continue;
        }
        $seen[$ln] = true;
        $visitRows .= '<tr class="border-b border-slate-100">'
            . '<td class="px-3 py-2 font-mono text-teal-800 font-semibold">' . e($ln) . '</td>'
            . '<td class="px-3 py-2 font-semibold">' . e((string)($ae['patient_name'] ?? '')) . '</td>'
            . '<td class="px-3 py-2 text-sm">' . e(normalize_tests_list((string)($ae['tests'] ?? ''))) . '</td>'
            . '<td class="px-3 py-2"><a class="btn btn-primary text-xs" href="/portals/collection-center/receipts.php?lab_no=' . urlencode($ln) . '&q=' . urlencode($q) . '">Open bill</a></td>'
            . '</tr>';
    }
}

$reportUrl = '/portals/collection-center/reports/preview.php?lab_no=' . urlencode($currentLab);

$content = page_header(
    'Patient Bill / Receipt',
    'Search patient → open visit bill with test prices.',
    $currentLab !== '' ? btn_secondary($reportUrl, 'Open lab report') : ''
);

$qVal = e($q);
$content .= <<<HTML
<div class="mb-4 no-print space-y-3 p-4 bg-white border border-slate-200 rounded-lg">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[16rem]">
            <label class="field-label" for="bill-q">Search patient</label>
            <input type="search" id="bill-q" name="q" class="field" value="{$qVal}" placeholder="Name, phone, MR No, or Lab No…" autofocus>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass mr-1"></i> Search</button>
        <a href="/portals/collection-center/receipts.php" class="btn btn-secondary">Clear</a>
    </form>
    <div class="flex flex-wrap gap-3 items-end border-t border-slate-100 pt-3">
        <div class="flex-1 min-w-[16rem]">
            <label class="field-label" for="bill-visit">Or open visit from list</label>
            <select id="bill-visit" class="field text-sm" onchange="if(this.value) location.href='/portals/collection-center/receipts.php?lab_no='+encodeURIComponent(this.value)">
                <option value="">— Select lab number / patient —</option>
                {$dropdownOpts}
            </select>
        </div>
    </div>
</div>
HTML;

if ($q !== '' && $labNo === '') {
    if ($visitRows === '') {
        $content .= card('<p class="p-6 text-slate-600">No visits found for <strong>' . e($q) . '</strong>.</p>');
    } else {
        $content .= card(
            panel_head('Matching visits') .
            '<div class="overflow-x-auto"><table class="min-w-full text-sm">'
            . '<thead class="bg-slate-800 text-white text-xs uppercase"><tr>'
            . '<th class="px-3 py-2 text-left">Lab No</th><th class="px-3 py-2 text-left">Patient</th><th class="px-3 py-2 text-left">Tests</th><th class="px-3 py-2 text-left">Action</th>'
            . '</tr></thead><tbody>' . $visitRows . '</tbody></table></div>',
            'overflow-hidden'
        );
    }
}

if ($currentLab !== '' && $entry) {
    $content .= report_actions($patient['phone'] ?? '', $reportUrl, 'bill-' . $currentLab);
    $content .= '<div class="mt-4">' . render_receipt_document($settings, $entry, $patient) . '</div>';
} elseif ($q === '') {
    $content .= card('<p class="p-6 text-slate-600">Search by patient name, phone, MR No, or Lab No to open their bill.</p>');
}

render_page('Patient Bill', 'collection-center', 'receipts', $content, true);
