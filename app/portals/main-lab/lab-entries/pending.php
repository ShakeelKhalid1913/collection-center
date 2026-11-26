<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

require_auth(['main_lab', 'imaging', 'admin']);
$orgId = current_organization_id();

$q = trim((string)($_GET['q'] ?? ''));
$sampleFilter = trim((string)($_GET['sample'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'lab_no_desc'));
if (!in_array($sort, ['lab_no_asc', 'lab_no_desc', 'date_desc', 'date_asc', 'patient_asc', 'patient_desc'], true)) {
    $sort = 'lab_no_desc';
}

$dbEntries = lab_repo()->getAll($orgId, 500, null, $sort);
$sourceEntries = !empty($dbEntries) ? $dbEntries : mock('mock_lab_entries');

$filteredEntries = [];
foreach ($sourceEntries as $e) {
    $status = (string)($e['status'] ?? 'pending');
    $sampleStatus = (string)($e['sample_status'] ?? $status);
    if (!in_array($status, ['pending', 'collected', 'in_progress'], true) && !in_array($sampleStatus, ['pending', 'collected'], true)) {
        continue;
    }
    if ($sampleFilter !== '' && strcasecmp($sampleStatus, $sampleFilter) !== 0) {
        continue;
    }
    if ($q !== '') {
        $qLower = strtolower($q);
        $hay = strtolower(($e['lab_no'] ?? '') . ' ' . ($e['patient_name'] ?? '') . ' ' . ($e['doctor'] ?? '') . ' ' . ($e['tests'] ?? ''));
        if (!str_contains($hay, $qLower)) {
            continue;
        }
    }
    $filteredEntries[] = $e;
}

// Two-tier natural sorting: Year DESC, Number DESC
usort($filteredEntries, static function (array $a, array $b) use ($sort): int {
    [$yrA, $numA] = parse_lab_no_parts((string)($a['lab_no'] ?? ''));
    [$yrB, $numB] = parse_lab_no_parts((string)($b['lab_no'] ?? ''));

    if ($sort === 'lab_no_desc') {
        if ($yrA !== $yrB) {
            return $yrB <=> $yrA;
        }
        return $numA !== $numB ? ($numB <=> $numA) : strnatcasecmp((string)($b['lab_no'] ?? ''), (string)($a['lab_no'] ?? ''));
    } elseif ($sort === 'lab_no_asc') {
        if ($yrA !== $yrB) {
            return $yrA <=> $yrB;
        }
        return $numA !== $numB ? ($numA <=> $numB) : strnatcasecmp((string)($a['lab_no'] ?? ''), (string)($b['lab_no'] ?? ''));
    } elseif ($sort === 'date_desc') {
        $tA = strtotime((string)($a['created_at'] ?? ''));
        $tB = strtotime((string)($b['created_at'] ?? ''));
        if ($tA !== $tB) {
            return $tB <=> $tA;
        }
        if ($yrA !== $yrB) {
            return $yrB <=> $yrA;
        }
        return $numB <=> $numA;
    } elseif ($sort === 'date_asc') {
        $tA = strtotime((string)($a['created_at'] ?? ''));
        $tB = strtotime((string)($b['created_at'] ?? ''));
        if ($tA !== $tB) {
            return $tA <=> $tB;
        }
        if ($yrA !== $yrB) {
            return $yrA <=> $yrB;
        }
        return $numA <=> $numB;
    } elseif ($sort === 'patient_asc') {
        return strcasecmp((string)($a['patient_name'] ?? ''), (string)($b['patient_name'] ?? ''));
    } elseif ($sort === 'patient_desc') {
        return strcasecmp((string)($b['patient_name'] ?? ''), (string)($a['patient_name'] ?? ''));
    } else {
        if ($yrA !== $yrB) {
            return $yrB <=> $yrA;
        }
        return $numA !== $numB ? ($numB <=> $numA) : strnatcasecmp((string)($b['lab_no'] ?? ''), (string)($a['lab_no'] ?? ''));
    }
});

$rows = [];
foreach ($filteredEntries as $e) {
    $status = (string)($e['status'] ?? 'pending');
    $sampleStatus = (string)($e['sample_status'] ?? $status);
    $pName = (string)($e['patient_name'] ?? $e['patient'] ?? '—');
    $labNo = (string)($e['lab_no'] ?? '');
    $labQ = urlencode($labNo);
    $dateVal = $e['created_at'] ?? $e['date'] ?? null;
    $remaining = max(0, (float)($e['amount'] ?? 0) - (float)($e['paid'] ?? 0));

    $rows[] = [
        '<span class="font-mono font-bold text-blue-700 bg-blue-50 px-2.5 py-1 rounded-md border border-blue-200">' . e($labNo) . '</span>',
        '<span class="font-semibold text-slate-900">' . e($pName) . '</span>',
        '<span class="text-xs text-slate-600 font-medium">' . e(normalize_tests_list((string)($e['tests'] ?? ''))) . '</span>',
        e((string)($e['doctor'] ?? 'Walk-in / Self')),
        status_badge($sampleStatus),
        status_badge($status),
        '<span class="font-semibold text-slate-900">' . e(format_money((float)($e['amount'] ?? 0))) . '</span>',
        '<span class="text-xs text-slate-500 whitespace-nowrap">' . e(format_date($dateVal)) . '</span>',
        '<div class="flex items-center gap-1.5 whitespace-nowrap">' .
        '<a href="/portals/results/entry.php?lab_no=' . $labQ . '" class="btn btn-primary text-xs px-2.5 py-1 font-semibold" title="Enter Results"><i class="fa-solid fa-keyboard mr-1"></i> Results</a>' .
        '<a href="/portals/reports/preview.php?lab_no=' . $labQ . '" class="btn btn-secondary text-xs px-2.5 py-1 font-semibold" title="View Report"><i class="fa-solid fa-file-lines"></i></a>' .
        '<a href="/portals/barcode-label.php?lab_no=' . $labQ . '" target="_blank" class="btn btn-secondary text-xs px-2 py-1" title="Barcode Label"><i class="fa-solid fa-barcode text-blue-600"></i></a>' .
        '</div>',
    ];
}

$buildSortUrl = static function (string $targetSort) use ($q, $sampleFilter): string {
    $params = ['sort' => $targetSort];
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($sampleFilter !== '') {
        $params['sample'] = $sampleFilter;
    }
    return '?' . http_build_query($params);
};

$labNoNext = ($sort === 'lab_no_desc') ? 'lab_no_asc' : 'lab_no_desc';
$labNoIcon = match ($sort) {
    'lab_no_asc' => '<i class="fa-solid fa-arrow-up-1-9 text-indigo-600 ml-1"></i>',
    'lab_no_desc' => '<i class="fa-solid fa-arrow-down-9-1 text-indigo-600 ml-1"></i>',
    default => '<i class="fa-solid fa-sort text-slate-400 ml-1"></i>',
};
$labNoHeader = '<a href="' . e($buildSortUrl($labNoNext)) . '" class="inline-flex items-center gap-1 font-extrabold text-slate-800 hover:text-indigo-600 transition-colors" title="Toggle Latest (Year &darr;, No &darr;) / Sequential Lab No">LAB NO ' . $labNoIcon . '</a>';

$dateNext = ($sort === 'date_desc') ? 'date_asc' : 'date_desc';
$dateIcon = match ($sort) {
    'date_desc' => '<i class="fa-solid fa-arrow-down-wide-short text-indigo-600 ml-1"></i>',
    'date_asc' => '<i class="fa-solid fa-arrow-up-wide-short text-indigo-600 ml-1"></i>',
    default => '<i class="fa-solid fa-sort text-slate-400 ml-1"></i>',
};
$dateHeader = '<a href="' . e($buildSortUrl($dateNext)) . '" class="inline-flex items-center gap-1 font-extrabold text-slate-800 hover:text-indigo-600 transition-colors" title="Toggle Newest / Oldest Date">DATE ' . $dateIcon . '</a>';

$patientNext = ($sort === 'patient_asc') ? 'patient_desc' : 'patient_asc';
$patientIcon = match ($sort) {
    'patient_asc' => '<i class="fa-solid fa-arrow-down-a-z text-indigo-600 ml-1"></i>',
    'patient_desc' => '<i class="fa-solid fa-arrow-up-z-a text-indigo-600 ml-1"></i>',
    default => '<i class="fa-solid fa-sort text-slate-400 ml-1"></i>',
};
$patientHeader = '<a href="' . e($buildSortUrl($patientNext)) . '" class="inline-flex items-center gap-1 font-extrabold text-slate-800 hover:text-indigo-600 transition-colors" title="Toggle Patient Name A-Z">PATIENT ' . $patientIcon . '</a>';

$samplePendingSel = ($sampleFilter === 'pending') ? ' selected' : '';
$sampleCollectedSel = ($sampleFilter === 'collected') ? ' selected' : '';
$sortLabDescSel = ($sort === 'lab_no_desc') ? ' selected' : '';
$sortLabAscSel = ($sort === 'lab_no_asc') ? ' selected' : '';
$sortDateDescSel = ($sort === 'date_desc') ? ' selected' : '';
$sortDateAscSel = ($sort === 'date_asc') ? ' selected' : '';
$sortPatAscSel = ($sort === 'patient_asc') ? ' selected' : '';

$filterBarHtml = <<<HTML
<form method="get" class="p-4 bg-slate-50/70 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative w-64 sm:w-72">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
            <input type="search" name="q" value="{$q}" placeholder="Search Lab No, Patient, Doctor…" class="field pl-8 text-xs py-2 bg-white">
        </div>
        <div>
            <select name="sample" onchange="this.form.submit()" class="field text-xs py-2 bg-white font-semibold">
                <option value="">Sample: All</option>
                <option value="pending"{$samplePendingSel}>Pending collection</option>
                <option value="collected"{$sampleCollectedSel}>Collected / In Lab</option>
            </select>
        </div>
        <div>
            <select name="sort" onchange="this.form.submit()" class="field text-xs py-2 bg-white font-bold text-indigo-950">
                <option value="lab_no_desc"{$sortLabDescSel}>Sort: Latest Lab No (Year &darr;, No &darr;)</option>
                <option value="lab_no_asc"{$sortLabAscSel}>Sort: Sequential Lab No (Low &rarr; High)</option>
                <option value="date_desc"{$sortDateDescSel}>Sort: Date (Newest First)</option>
                <option value="date_asc"{$sortDateAscSel}>Sort: Date (Oldest First)</option>
                <option value="patient_asc"{$sortPatAscSel}>Sort: Patient Name (A &rarr; Z)</option>
            </select>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <button type="submit" class="btn btn-primary text-xs py-2 px-3.5"><i class="fa-solid fa-filter mr-1"></i> Filter</button>
        <a href="/portals/lab-entries/pending.php" class="btn btn-secondary text-xs py-2 px-3">Reset</a>
    </div>
</form>
HTML;

$content = page_header(
    'Pending Lab Orders',
    'Laboratory orders awaiting sample collection, testing, or result approval.',
    btn_primary('/portals/lab-entries/new.php', 'Register Patient', 'fa-solid fa-plus')
);

$content .= card(
    $filterBarHtml .
    data_table(
        [$labNoHeader, $patientHeader, 'Tests Ordered', 'Doctor', 'Sample', 'Status', 'Total', $dateHeader, 'Actions'],
        $rows
    ),
    'overflow-hidden'
);

render_page('Pending Lab Orders', 'main-lab', 'pending', $content);
