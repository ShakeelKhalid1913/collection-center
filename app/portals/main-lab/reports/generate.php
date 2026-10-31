<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

require_auth(['main_lab', 'imaging', 'admin']);
$orgId = current_organization_id();
$labRepo = lab_repo();

// If submitted directly
if (!empty($_GET['lab_no'])) {
    redirect('/portals/reports/preview.php?lab_no=' . urlencode((string)$_GET['lab_no']));
}

$verifiedEntries = array_filter(
    $labRepo->getAll($orgId, null, 25),
    static fn($e) => in_array($e['status'] ?? '', ['verified', 'completed', 'in_progress'], true)
);

$content = page_header('Generate & Print Official Lab Report', 'Select any lab order or search by Lab No to preview and print.');

$verifiedRowsHtml = '';
if (!empty($verifiedEntries)) {
    foreach ($verifiedEntries as $e) {
        $labNo = e($e['lab_no']);
        $patient = e($e['patient_name'] ?? 'Walk-in');
        $tests = e($e['tests'] ?? 'CBC');
        $statusBadge = status_badge($e['status'] ?? 'in_progress');
        $date = e(date('d M Y', strtotime($e['created_at'] ?? 'now')));
        $previewUrl = '/portals/reports/preview.php?lab_no=' . urlencode($e['lab_no']);

        $verifiedRowsHtml .= <<<TR
        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
            <td class="p-3 font-mono font-bold text-xs text-slate-800">{$labNo}</td>
            <td class="p-3 text-xs font-semibold text-slate-900">{$patient}</td>
            <td class="p-3 text-xs text-slate-600">{$tests}</td>
            <td class="p-3 text-xs text-slate-500">{$date}</td>
            <td class="p-3">{$statusBadge}</td>
            <td class="p-3 text-right">
                <div class="inline-flex items-center gap-1.5 justify-end">
                    <a href="{$previewUrl}&format=graph" class="btn btn-primary text-xs font-bold py-1 px-2.5 shadow-2xs" title="Official Graphical Report with visual gauges">
                        <i class="fa-solid fa-chart-simple mr-1"></i> Graph
                    </a>
                    <a href="{$previewUrl}&format=simple" class="btn btn-secondary text-xs font-semibold py-1 px-2.5 shadow-2xs" title="Standard Tabular Numbers Report">
                        <i class="fa-solid fa-table-list mr-1"></i> Simple
                    </a>
                </div>
            </td>
        </tr>
TR;
    }
} else {
    $verifiedRowsHtml = '<tr><td colspan="6" class="p-6 text-center text-xs text-slate-400">No verified or in-progress laboratory orders found.</td></tr>';
}

$content .= card(
    '<form method="get" action="/portals/reports/preview.php" class="grid max-w-xl gap-4 p-4 sm:p-6">' .
    '<label class="field-label font-bold text-slate-900">Enter Lab Number (L-YYYY-XXXX)</label>' .
    '<div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">' .
    '<input type="text" name="lab_no" class="field text-sm font-mono font-semibold" placeholder="e.g. L-2026-0891" required>' .
    '<select name="format" class="field text-xs font-bold rounded-xl py-2 px-3 border border-slate-300 bg-slate-50 w-auto">' .
    '<option value="graph"><i class="fa-solid fa-chart-simple"></i> Graph Report</option>' .
    '<option value="simple">Simple Report</option>' .
    '</select>' .
    '<button type="submit" class="btn btn-primary text-xs font-bold px-4 py-2 whitespace-nowrap shadow-md"><i class="fa-solid fa-magnifying-glass mr-1"></i> Generate</button>' .
    '</div>' .
    '</form>'
);

$content .= '<div class="mt-6">' . card(
    '<div class="p-4 border-b border-slate-200 flex items-center justify-between">' .
    '<h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Recent Laboratory Orders Ready For Print</h3>' .
    '<span class="text-xs text-slate-400">Showing up to 25 records</span>' .
    '</div>' .
    '<div class="overflow-x-auto"><table class="w-full text-left text-xs">' .
    '<thead class="bg-slate-50 text-slate-600 uppercase text-[10px] font-bold border-b border-slate-200"><tr>' .
    '<th class="p-3">Lab No</th><th class="p-3">Patient</th><th class="p-3">Tests Ordered</th><th class="p-3">Date</th><th class="p-3">Status</th><th class="p-3 text-right">Action</th>' .
    '</tr></thead><tbody>' . $verifiedRowsHtml . '</tbody></table></div>'
) . '</div>';

render_page('Generate Report', 'main-lab', 'reports-generate', $content);
