<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

require_permission('view_results');

$labNo = trim($_GET['lab_no'] ?? $_POST['lab_no'] ?? '');
$requestedOrg = trim((string)($_GET['org'] ?? ''));
if ($labNo !== '') {
    audit_log('VIEW_REPORT', 'lab_entries', $labNo, 'Viewed diagnostic report preview');
}
$ctx = load_document_context($labNo !== '' ? $labNo : null, $requestedOrg !== '' ? $requestedOrg : null);

if (!empty($ctx['not_found'])) {
    $content = page_header('Report Not Found', 'No diagnostic order matches your search query.');
    $content .= card(
        '<div class="p-8 text-center max-w-lg mx-auto">' .
        '<div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center text-2xl font-bold shadow-inner"><i class="fa-solid fa-file-circle-xmark"></i></div>' .
        '<h2 class="text-base font-extrabold text-slate-800 mb-2">No Lab Order Found for "' . e($labNo) . '"</h2>' .
        '<p class="text-xs text-slate-500 mb-6">Please check the Slip # (e.g. 3235), Lab No (e.g. L-2026-3235), or Patient MR Number and try again.</p>' .
        '<form method="get" action="/portals/reports/preview.php" class="flex flex-col sm:flex-row gap-2 justify-center">' .
        '<input type="text" name="lab_no" class="field text-sm font-mono font-semibold" placeholder="e.g. 3235 or L-2026-3235" autofocus required>' .
        '<button type="submit" class="btn btn-primary text-xs font-bold px-4 py-2 whitespace-nowrap"><i class="fa-solid fa-magnifying-glass mr-1"></i> Search Report</button>' .
        '</form>' .
        '<div class="mt-4"><a href="/portals/reports/generate.php" class="text-xs text-teal-600 hover:underline font-semibold">&larr; Back to Report Search &amp; History</a></div>' .
        '</div>'
    );
    render_page('Report Not Found', 'main-lab', 'reports-preview', $content);
    exit;
}

$previewUrl = '/portals/reports/preview.php' . ($labNo !== '' ? '?lab_no=' . urlencode($labNo) : '');

$entryData = $ctx['entry'] ?? [];
$isBillLocked = !empty($entryData['is_bill_locked']);
$isReportLocked = !empty($entryData['is_report_locked']);
$isAdmin = is_admin_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax_save_page_grouping'])) {
    $targetLabNo = trim((string)($_POST['lab_no'] ?? $labNo));
    if ($targetLabNo === '') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Lab number is missing.']);
        exit;
    }

    $pageMap = [];
    if (!empty($_POST['page_map_test']) && is_array($_POST['page_map_test'])) {
        $pageMap = \App\Repositories\ResultRepository::pageMapFromParallel(
            $_POST['page_map_test'],
            $_POST['page_map_page'] ?? []
        );
    } elseif (!empty($_POST['page_map']) && is_array($_POST['page_map'])) {
        $pageMap = $_POST['page_map'];
    }

    if ($pageMap !== []) {
        result_repo()->assignPrintPages($targetLabNo, $pageMap);
    }

    audit_log('UPDATE_PAGE_GROUPING', 'results', $targetLabNo, 'Updated print page grouping directly from preview');

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $labNo !== '') {
    if (isset($_POST['action_report_lock'])) {
        $act = (string)$_POST['action_report_lock'];
        if ($act === 'lock') {
            lab_repo()->lockReport($labNo, current_user()['name'] ?? 'Staff');
            audit_log('LOCK_REPORT', 'lab_entries', $labNo, "Report locked from preview by " . (current_user()['name'] ?? 'Staff'));
            header('Location: /portals/reports/preview.php?lab_no=' . urlencode($labNo) . '&report_locked=1');
            exit;
        } elseif ($act === 'unlock') {
            if ($isAdmin) {
                lab_repo()->unlockReport($labNo);
                audit_log('UNLOCK_REPORT', 'lab_entries', $labNo, "Report unlocked from preview by Admin");
                header('Location: /portals/reports/preview.php?lab_no=' . urlencode($labNo) . '&report_unlocked=1');
                exit;
            }
        }
    }
    if (isset($_POST['action_bill_lock'])) {
        $act = (string)$_POST['action_bill_lock'];
        if ($act === 'lock') {
            lab_repo()->lockBill($labNo, current_user()['name'] ?? 'Staff');
            audit_log('LOCK_BILL', 'lab_entries', $labNo, "Bill locked from preview by " . (current_user()['name'] ?? 'Staff'));
            header('Location: /portals/reports/preview.php?lab_no=' . urlencode($labNo) . '&bill_locked=1');
            exit;
        } elseif ($act === 'unlock') {
            if ($isAdmin) {
                lab_repo()->unlockBill($labNo);
                audit_log('UNLOCK_BILL', 'lab_entries', $labNo, "Bill unlocked from preview by Admin");
                header('Location: /portals/reports/preview.php?lab_no=' . urlencode($labNo) . '&bill_unlocked=1');
                exit;
            }
        }
    }
}

$lockFlash = '';
if (isset($_GET['report_locked'])) {
    $lockFlash = flash_success('Diagnostic report has been locked. Editing results is disabled for employees.');
} elseif (isset($_GET['report_unlocked'])) {
    $lockFlash = flash_success('Diagnostic report has been unlocked by Admin.');
} elseif (isset($_GET['bill_locked'])) {
    $lockFlash = flash_success('Bill has been locked. Modifying invoice/payments is disabled.');
} elseif (isset($_GET['bill_unlocked'])) {
    $lockFlash = flash_success('Bill has been unlocked by Admin.');
}

$qLab = urlencode($labNo);
$content = workflow_stepper('report', $labNo !== '' ? $labNo : null, $ctx['patient']['id'] ?? null);
$content .= $lockFlash;

$reportLockBtnHtml = '';
if ($isReportLocked) {
    if ($isAdmin) {
        $reportLockBtnHtml = '<form method="post" class="inline"><input type="hidden" name="lab_no" value="' . e($labNo) . '"><button type="submit" name="action_report_lock" value="unlock" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-500 hover:bg-rose-400 text-white font-bold text-xs shadow-xs transition-all"><i class="fa-solid fa-lock-open"></i> Unlock Report (Admin)</button></form>';
    } else {
        $reportLockBtnHtml = '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-950/80 text-rose-300 border border-rose-700/80 font-bold text-xs"><i class="fa-solid fa-lock text-rose-400"></i> Report Locked</span>';
    }
} else {
    $reportLockBtnHtml = '<form method="post" class="inline" onsubmit="return confirm(\'Lock this report? Once locked, test results can only be unlocked or edited from an Admin ID.\');"><input type="hidden" name="lab_no" value="' . e($labNo) . '"><button type="submit" name="action_report_lock" value="lock" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-amber-300 border border-amber-500/40 font-bold text-xs transition-all" title="Lock report once finalized to prevent changes"><i class="fa-solid fa-lock text-amber-400"></i> Lock Report</button></form>';
}

$billLockBtnHtml = '';
if ($isBillLocked) {
    if ($isAdmin) {
        $billLockBtnHtml = '<form method="post" class="inline"><input type="hidden" name="lab_no" value="' . e($labNo) . '"><button type="submit" name="action_bill_lock" value="unlock" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs shadow-xs transition-all"><i class="fa-solid fa-lock-open"></i> Unlock Bill (Admin)</button></form>';
    } else {
        $billLockBtnHtml = '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-950/80 text-emerald-300 border border-emerald-700/80 font-bold text-xs"><i class="fa-solid fa-shield-halved text-emerald-400"></i> Bill Locked</span>';
    }
} else {
    $billLockBtnHtml = '<form method="post" class="inline" onsubmit="return confirm(\'Lock this bill? Once locked, only an Admin ID can unlock or modify it.\');"><input type="hidden" name="lab_no" value="' . e($labNo) . '"><button type="submit" name="action_bill_lock" value="lock" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-sky-300 border border-sky-500/40 font-bold text-xs transition-all" title="Lock bill to prevent payment tampering"><i class="fa-solid fa-lock text-sky-400"></i> Lock Bill</button></form>';
}

$editResultsBtnHtml = '';
if ($isReportLocked && !$isAdmin) {
    $editResultsBtnHtml = '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 text-slate-400 border border-slate-700 text-xs font-bold cursor-not-allowed" title="Report is locked. Only Admin ID can edit results."><i class="fa-solid fa-lock text-rose-400"></i> Results Locked</span>';
} else {
    $editResultsBtnHtml = '<a href="/portals/results/entry.php?lab_no=' . $qLab . '" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 transition-all"><i class="fa-solid fa-keyboard text-xs text-teal-400"></i> Edit Results</a>';
}

$entryOrgId = (string)($entryData['organization_id'] ?? current_organization_id());
$allEntriesForSwitch = lab_repo()->getEntriesWithPatients($entryOrgId, 250);
$reportSelectOpts = '';
foreach ($allEntriesForSwitch as $ae) {
    $aeLab = (string)($ae['lab_no'] ?? '');
    $sel = ($aeLab === $labNo) ? ' selected' : '';
    $pName = (string)($ae['patient_name'] ?? 'Walk-in');
    $pTests = normalize_tests_list((string)($ae['tests'] ?? ''));
    if (strlen($pTests) > 30) {
        $pTests = substr($pTests, 0, 28) . '…';
    }
    $pDate = !empty($ae['created_at']) ? date('d M', strtotime((string)$ae['created_at'])) : '';
    $reportSelectOpts .= '<option value="' . e($aeLab) . '"' . $sel . '>'
        . e($aeLab) . ' — ' . e($pName) . ' (' . e($pTests) . ') [' . $pDate . ']</option>';
}

$content .= <<<HTML
<div class="no-print mb-4 p-3 bg-slate-900 text-white rounded-2xl flex flex-wrap items-center justify-between gap-3 shadow-sm">
    <div class="flex items-center gap-2.5">
        <span class="w-7 h-7 rounded-lg bg-emerald-500 text-slate-950 inline-flex items-center justify-center font-bold text-xs">
            <i class="fa-solid fa-file-circle-check"></i>
        </span>
        <div class="text-xs font-bold text-white">Diagnostic Report Dossier · {$labNo}</div>
    </div>
    <div class="flex flex-wrap items-center gap-2 text-xs font-bold">
        <a href="/portals/patients/register.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition-all">
            <i class="fa-solid fa-user-plus text-xs"></i> + Next Patient &rarr;
        </a>
        <button type="button" onclick="openPageGroupingModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold transition-all shadow-xs cursor-pointer" title="Redo page grouping / split or combine tests">
            <i class="fa-solid fa-table-cells-large text-indigo-200"></i> Page Grouping
        </button>
        {$editResultsBtnHtml}
        {$reportLockBtnHtml}
    </div>
</div>

<!-- Report Switcher & Quick Slip Search Toolbar -->
<div class="no-print mb-4 p-3.5 bg-white rounded-2xl border border-slate-200/90 shadow-2xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 flex-1 min-w-0">
        <label class="text-xs font-bold text-slate-700 whitespace-nowrap flex items-center gap-1.5">
            <i class="fa-solid fa-file-waveform text-teal-600"></i> Select Report:
        </label>
        <select class="field text-xs font-bold py-2 px-3 bg-slate-50 hover:bg-slate-100 text-slate-800 border border-slate-300 rounded-xl cursor-pointer w-full sm:max-w-md shadow-2xs focus:ring-2 focus:ring-teal-500 focus:bg-white" onchange="if(this.value) location.href='/portals/reports/preview.php?lab_no='+encodeURIComponent(this.value)">
            <option value="">— Choose / Switch Patient Report —</option>
            {$reportSelectOpts}
        </select>
    </div>

    <form method="get" action="/portals/reports/preview.php" class="flex items-center gap-2 shrink-0">
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <input type="text" name="lab_no" placeholder="Slip # (e.g. 3235)..." class="field text-xs font-mono font-bold pl-8 pr-3 py-2 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white w-48 sm:w-56 shadow-2xs">
        </div>
        <button type="submit" class="btn btn-primary text-xs font-bold py-2 px-3.5 rounded-xl shadow-2xs whitespace-nowrap">
            Open Report
        </button>
    </form>
</div>
HTML;

$content .= '<div class="no-print">' . page_header(
    'Report Preview — ' . ($labNo !== '' ? $labNo : 'Sample')
) . '</div>';

$entryTests = (string)($ctx['entry']['tests'] ?? '');
if ($entryTests !== '') {
    $deptBadges = render_department_badges_html($entryTests, $labNo);
    $content .= <<<HTML
    <div class="no-print mb-4 p-3 bg-white rounded-2xl border border-slate-200/90 shadow-2xs flex flex-wrap items-center gap-2">
        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
            <i class="fa-solid fa-layer-group text-slate-400"></i> Order Departments:
        </span>
        {$deptBadges}
    </div>
HTML;
}

$format = trim((string)($_GET['format'] ?? $_COOKIE['preferred_report_format'] ?? 'graph'));
if (isset($_GET['format'])) {
    @setcookie('preferred_report_format', $format, time() + 86400 * 30, '/');
}
$useGraph = ($format !== 'simple');

$isPartial = !empty($ctx['is_partial']);
$enteredCount = (int)($ctx['entered_tests_count'] ?? 0);
$totalBooked = (int)($ctx['total_booked_tests'] ?? 0);
$pendingCount = (int)($ctx['pending_tests_count'] ?? 0);

if ($isPartial) {
    $content .= <<<HTML
    <div class="no-print mb-4 p-3 bg-amber-50 rounded-2xl border border-amber-200 text-amber-900 flex flex-wrap items-center justify-between gap-3 text-xs shadow-2xs">
        <div class="flex items-center gap-2.5">
            <span class="w-7 h-7 rounded-lg bg-amber-500 text-slate-950 inline-flex items-center justify-center font-bold">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </span>
            <div>
                <span class="font-bold text-amber-950">Partial Report:</span> Printing <strong>{$enteredCount} of {$totalBooked}</strong> tests.
                <span class="text-amber-800">({$pendingCount} pending test(s) hidden from report until results are entered).</span>
            </div>
        </div>
        <a href="/portals/results/entry.php?lab_no={$qLab}" class="px-3 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold transition-all flex items-center gap-1.5">
            <i class="fa-solid fa-keyboard"></i> Enter Remaining Results &rarr;
        </a>
    </div>
HTML;
} elseif ($totalBooked > 0 && $enteredCount === 0) {
    $content .= <<<HTML
    <div class="no-print mb-4 p-3.5 bg-sky-50 rounded-2xl border border-sky-200 text-sky-900 flex flex-wrap items-center justify-between gap-3 text-xs shadow-2xs">
        <div class="flex items-center gap-2.5">
            <span class="w-7 h-7 rounded-lg bg-sky-500 text-slate-950 inline-flex items-center justify-center font-bold">
                <i class="fa-solid fa-info"></i>
            </span>
            <div>
                <span class="font-bold text-sky-950">No Results Entered Yet:</span>
                <span class="text-sky-800">All {$totalBooked} booked tests are pending result entry. Once entered, they will immediately appear on the printed report.</span>
            </div>
        </div>
        <a href="/portals/results/entry.php?lab_no={$qLab}" class="px-3 py-1.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold transition-all flex items-center gap-1.5">
            <i class="fa-solid fa-keyboard"></i> Enter Results Now &rarr;
        </a>
    </div>
HTML;
}

$content .= '<div class="no-print mb-4">';
$content .= report_actions($ctx['patient']['phone'] ?? '', $previewUrl, 'report-' . ($labNo !== '' ? $labNo : 'preview'), $format);
$content .= '</div>';
$content .= render_report_document(
    $ctx['settings'],
    $ctx['patient'],
    $ctx['lines'],
    $ctx['entry'],
    $ctx['report_title'] ?? 'DIAGNOSTIC REPORT',
    $ctx['signatories'] ?? [],
    $ctx['specimen'] ?? 'Serum',
    ['graph_mode' => $useGraph]
);

if (!empty($_GET['autoprint'])) {
    $content .= '<script>window.addEventListener("DOMContentLoaded", function() { setTimeout(function() { window.print(); }, 400); });</script>';
}

// Page Grouping Modal & Data for in-place grouping right on preview
$entryTests = (string)($ctx['entry']['tests'] ?? '');
$testNames = array_values(array_filter(array_map('trim', explode(',', $entryTests))));

$resultRows = db()->fetchAll("SELECT test, print_page FROM results WHERE lab_no = :lab", ['lab' => $labNo]);
$pageAssignments = [];
$maxPage = 1;
foreach ($resultRows as $rr) {
    $tn = trim((string)($rr['test'] ?? ''));
    $pg = max(1, (int)($rr['print_page'] ?? 1));
    $pageAssignments[$tn] = $pg;
    $maxPage = max($maxPage, $pg);
}
foreach ($testNames as $tn) {
    if (!isset($pageAssignments[$tn])) {
        $pageAssignments[$tn] = 1;
    }
}

$testDetailsForModal = [];
foreach ($testNames as $tn) {
    $dInfo = get_test_department_info($tn);
    $dName = $dInfo['department_name'] ?? 'Laboratory';
    $testDetailsForModal[] = [
        'name' => $tn,
        'page' => (int)($pageAssignments[$tn] ?? 1),
        'department' => $dName,
    ];
}
$testDetailsJson = json_encode($testDetailsForModal, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$testCount = count($testNames);

$modalHtml = <<<HTML
<div id="pageGroupingModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
    <div class="relative w-full max-w-4xl bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-slate-50/80">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-lg shadow-sm">
                    <i class="fa-solid fa-table-cells-large"></i>
                </span>
                <div>
                    <h3 class="text-base font-black text-slate-900 tracking-tight">Free Page Grouping</h3>
                    <p class="text-xs text-slate-500">Configure report page layout directly • Unlimited pages • Quick actions</p>
                </div>
            </div>
            <button type="button" onclick="closePageGroupingModal()" class="w-9 h-9 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 flex items-center justify-center transition-all cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 px-6 py-3 bg-white border-b border-slate-100">
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="pageGroupingAction('one_per_page')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-bold text-xs transition-all cursor-pointer">
                    <i class="fa-solid fa-file-lines text-indigo-600"></i> 1 Test Per Page
                </button>
                <button type="button" onclick="pageGroupingAction('by_dept')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-teal-50 hover:bg-teal-100 text-teal-700 border border-teal-200 font-bold text-xs transition-all cursor-pointer">
                    <i class="fa-solid fa-layer-group text-teal-600"></i> Group by Department
                </button>
                <button type="button" onclick="pageGroupingAction('combine_all')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 font-bold text-xs transition-all cursor-pointer">
                    <i class="fa-solid fa-compress text-slate-500"></i> Combine on 1 Page
                </button>
            </div>
            <button type="button" onclick="pageGroupingAddPage()" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-all shadow-xs cursor-pointer">
                <i class="fa-solid fa-plus text-xs"></i> Add Page
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 bg-slate-50/50 space-y-4" id="modal-pages-container">
            <!-- Dynamically populated by JS -->
        </div>

        <div class="flex items-center justify-between px-6 py-3.5 border-t border-slate-200 bg-white">
            <div class="text-xs font-semibold text-slate-500" id="modal-grouping-summary">
                {$testCount} total tests • {$maxPage} pages
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="closePageGroupingModal()" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 font-bold text-xs transition-all cursor-pointer">
                    Cancel
                </button>
                <button type="button" id="save-grouping-btn" onclick="savePageGroupingFromModal()" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-all flex items-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-floppy-disk"></i> Save Grouping
                </button>
            </div>
        </div>
    </div>
</div>
HTML;

$modalJsInit = 'window.__PAGE_GROUPING_TESTS__ = ' . $testDetailsJson . ';';
$modalJsInit .= 'window.__PAGE_GROUPING_MAX_PAGE__ = ' . (int)$maxPage . ';';
$modalJsInit .= 'window.__PAGE_GROUPING_LAB_NO__ = ' . json_encode($labNo) . ';';

$modalScript = <<<HTML
<script>
{$modalJsInit}
let groupingData = Array.isArray(window.__PAGE_GROUPING_TESTS__) ? window.__PAGE_GROUPING_TESTS__ : [];
let groupingMaxPage = parseInt(window.__PAGE_GROUPING_MAX_PAGE__) || 1;

function openPageGroupingModal() {
    const modal = document.getElementById('pageGroupingModal');
    if (!modal) return;
    renderPageGroupingGrid();
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closePageGroupingModal() {
    const modal = document.getElementById('pageGroupingModal');
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

function renderPageGroupingGrid() {
    const container = document.getElementById('modal-pages-container');
    if (!container) return;

    groupingMaxPage = Math.max(1, groupingData.reduce((max, t) => Math.max(max, parseInt(t.page) || 1), groupingMaxPage));

    const byPage = {};
    for (let p = 1; p <= groupingMaxPage; p++) {
        byPage[p] = [];
    }
    groupingData.forEach((t) => {
        const p = parseInt(t.page) || 1;
        if (!byPage[p]) byPage[p] = [];
        byPage[p].push(t);
    });

    let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
    for (let p = 1; p <= groupingMaxPage; p++) {
        const tests = byPage[p] || [];
        const testCount = tests.length;
        const testCountLabel = testCount === 1 ? '1 test' : `\${testCount} tests`;
        
        let chipsHtml = '';
        if (tests.length === 0) {
            chipsHtml = '<div class="p-4 text-center text-xs text-slate-400 border border-dashed border-slate-300 rounded-xl bg-slate-50/50">Empty page (drag or move tests here)</div>';
        } else {
            tests.forEach((t) => {
                let moveOptions = '';
                for (let op = 1; op <= groupingMaxPage; op++) {
                    const sel = op === p ? ' selected' : '';
                    moveOptions += `<option value="\${op}"\${sel}>Page \${op}</option>`;
                }
                
                chipsHtml += `
                    <div class="modal-test-chip flex items-center justify-between gap-2 p-2.5 bg-white border border-slate-200/90 rounded-xl shadow-2xs hover:border-indigo-300 transition-all cursor-grab" draggable="true" data-test="\${escapeHtmlModal(t.name)}">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-grip-vertical"></i></span>
                            <div class="truncate">
                                <div class="text-xs font-bold text-slate-800 truncate">\${escapeHtmlModal(t.name)}</div>
                                <span class="inline-block text-[10px] font-semibold text-slate-500 bg-slate-100 px-1.5 py-0.2 rounded">\${escapeHtmlModal(t.department || 'Lab')}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <select class="text-[11px] font-semibold border border-slate-300 rounded-lg px-2 py-1 bg-white text-slate-700 focus:ring-1 focus:ring-indigo-500 cursor-pointer" onchange="moveTestToPage('\${escapeJsModal(t.name)}', this.value)">
                                \${moveOptions}
                            </select>
                            <button type="button" class="w-6 h-6 flex items-center justify-center text-slate-400 hover:text-indigo-600 rounded cursor-pointer" onclick="shiftTestOrder('\${escapeJsModal(t.name)}', -1)" title="Move up"><i class="fa-solid fa-chevron-up text-[10px]"></i></button>
                            <button type="button" class="w-6 h-6 flex items-center justify-center text-slate-400 hover:text-indigo-600 rounded cursor-pointer" onclick="shiftTestOrder('\${escapeJsModal(t.name)}', 1)" title="Move down"><i class="fa-solid fa-chevron-down text-[10px]"></i></button>
                        </div>
                    </div>
                `;
            });
        }

        html += `
            <div class="modal-page-card bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex flex-col justify-between" data-modal-page="\${p}">
                <div>
                    <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-700 font-black text-xs flex items-center justify-center">\${p}</span>
                            <span class="text-xs font-extrabold text-slate-900">Page \${p}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full \${testCount > 0 ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-500'}">\${testCountLabel}</span>
                            \${testCount === 0 && groupingMaxPage > 1 ? `<button type="button" onclick="removeEmptyPage(\${p})" class="text-rose-500 hover:text-rose-700 text-xs px-1.5 py-0.5 rounded hover:bg-rose-50 cursor-pointer" title="Remove empty page"><i class="fa-solid fa-trash-can"></i></button>` : ''}
                        </div>
                    </div>
                    <div class="space-y-2 min-h-[60px]" data-modal-page-list="\${p}">
                        \${chipsHtml}
                    </div>
                </div>
            </div>
        `;
    }
    html += '</div>';
    container.innerHTML = html;

    const summaryEl = document.getElementById('modal-grouping-summary');
    if (summaryEl) {
        summaryEl.textContent = `\${groupingData.length} total tests • \${groupingMaxPage} pages`;
    }

    initModalDragDrop();
}

function moveTestToPage(testName, newPage) {
    const t = groupingData.find(item => item.name === testName);
    if (t) {
        t.page = parseInt(newPage);
        renderPageGroupingGrid();
    }
}

function shiftTestOrder(testName, direction) {
    const idx = groupingData.findIndex(item => item.name === testName);
    if (idx < 0) return;
    const targetIdx = idx + direction;
    if (targetIdx >= 0 && targetIdx < groupingData.length) {
        const temp = groupingData[idx];
        groupingData[idx] = groupingData[targetIdx];
        groupingData[targetIdx] = temp;
        renderPageGroupingGrid();
    }
}

function pageGroupingAddPage() {
    groupingMaxPage++;
    renderPageGroupingGrid();
}

function removeEmptyPage(pageNo) {
    groupingData.forEach(t => {
        if (t.page > pageNo) {
            t.page--;
        }
    });
    groupingMaxPage = Math.max(1, groupingMaxPage - 1);
    renderPageGroupingGrid();
}

function pageGroupingAction(action) {
    if (action === 'one_per_page') {
        groupingData.forEach((t, i) => {
            t.page = i + 1;
        });
        groupingMaxPage = groupingData.length;
    } else if (action === 'by_dept') {
        const depts = {};
        let currentP = 1;
        groupingData.forEach((t) => {
            const d = t.department || 'General';
            if (!depts[d]) {
                depts[d] = currentP++;
            }
            t.page = depts[d];
        });
        groupingMaxPage = currentP - 1;
    } else if (action === 'combine_all') {
        groupingData.forEach((t) => {
            t.page = 1;
        });
        groupingMaxPage = 1;
    }
    renderPageGroupingGrid();
}

function initModalDragDrop() {
    const chips = document.querySelectorAll('.modal-test-chip');
    const lists = document.querySelectorAll('[data-modal-page-list]');
    let draggedName = null;

    chips.forEach(chip => {
        chip.addEventListener('dragstart', () => {
            draggedName = chip.getAttribute('data-test');
            chip.classList.add('opacity-50');
        });
        chip.addEventListener('dragend', () => {
            chip.classList.remove('opacity-50');
            draggedName = null;
        });
    });

    lists.forEach(list => {
        const pageCard = list.closest('.modal-page-card');
        const pageNo = parseInt(pageCard.getAttribute('data-modal-page'));

        list.addEventListener('dragover', (e) => {
            e.preventDefault();
            pageCard.classList.add('ring-2', 'ring-indigo-400');
        });
        list.addEventListener('dragleave', () => {
            pageCard.classList.remove('ring-2', 'ring-indigo-400');
        });
        list.addEventListener('drop', (e) => {
            e.preventDefault();
            pageCard.classList.remove('ring-2', 'ring-indigo-400');
            if (!draggedName) return;
            moveTestToPage(draggedName, pageNo);
        });
    });
}

async function savePageGroupingFromModal() {
    const saveBtn = document.getElementById('save-grouping-btn');
    const origHtml = saveBtn ? saveBtn.innerHTML : '';
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving…';
    }

    try {
        const curLabNo = window.__PAGE_GROUPING_LAB_NO__ || '';
        const fd = new FormData();
        fd.append('ajax_save_page_grouping', '1');
        fd.append('lab_no', curLabNo);
        groupingData.forEach((t) => {
            fd.append(`page_map[\${t.name}]`, t.page);
            fd.append('page_map_test[]', t.name);
            fd.append('page_map_page[]', t.page);
        });

        const resp = await fetch(window.location.href, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await resp.json();
        if (data && data.ok) {
            if (saveBtn) {
                saveBtn.className = 'px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold text-xs flex items-center gap-1.5';
                saveBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Grouping Saved!';
            }
            setTimeout(() => {
                closePageGroupingModal();
                window.location.reload();
            }, 300);
        } else {
            throw new Error((data && data.error) ? data.error : 'Save failed');
        }
    } catch (err) {
        console.error('Error saving page grouping:', err);
        alert('Could not save page grouping: ' + (err.message || 'Please check your connection.'));
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = origHtml;
        }
    }
}

function escapeHtmlModal(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function escapeJsModal(str) {
    if (!str) return '';
    return String(str).replace(/\\\\/g, '\\\\\\\\').replace(/'/g, "\\\\'");
}
</script>
HTML;

$content .= $modalHtml . $modalScript;

$portal = current_portal();
$activeKey = ($portal === 'imaging') ? 'report-history' : 'reports-generate';
render_page('Report Preview', $portal, $activeKey, $content, true);
