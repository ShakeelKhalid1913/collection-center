<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';
require_once __DIR__ . '/../../../includes/documents.php';

$orgId = current_organization_id();
$settings = branding_settings($orgId);

$labName = !empty($settings['name']) ? $settings['name'] : 'DIAGNOSTIC LABORATORY & RESEARCH CENTER';
$tagline = !empty($settings['header']) ? $settings['header'] : 'ISO Certified Diagnostic & Imaging Services';
$address = !empty($settings['address']) ? $settings['address'] : 'Main Healthcare Boulevard, Pakistan';
$phone = !empty($settings['phone']) ? $settings['phone'] : '+92 300 0000000';
$email = !empty($settings['email']) ? $settings['email'] : 'info@diagnosticlab.pk';
$logoUrl = !empty($settings['header_image_url']) ? $settings['header_image_url'] : '';

// Fetch all active tests
$allTests = test_repo()->getTests($orgId);

// Group tests by department / category
$grouped = [];
foreach ($allTests as $t) {
    $dept = trim((string)($t['category'] ?? 'General Pathology'));
    if ($dept === '') $dept = 'General Pathology';
    $grouped[$dept][] = $t;
}
ksort($grouped);

// Fetch test packages
$packages = test_repo()->getPackages($orgId);
$totalTestsCount = count($allTests);
$totalPackagesCount = count($packages);
$printDate = date('d F Y');

// Header Actions
$headerActions = <<<HTML
<div class="flex flex-wrap items-center gap-2">
    <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs sm:text-sm shadow-md transition-all">
        <i class="fa-solid fa-print text-[#c2f13c]"></i> Print / Save as PDF
    </button>
    <a href="/portals/tests/prices.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-2xl bg-white hover:bg-slate-50 text-slate-800 font-bold text-xs sm:text-sm border border-slate-300 shadow-2xs transition-all">
        <i class="fa-solid fa-tags text-slate-500"></i> Edit Prices
    </a>
    <a href="/portals/tests/packages.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-2xl bg-indigo-50 hover:bg-indigo-100 text-indigo-800 font-bold text-xs sm:text-sm border border-indigo-200 transition-all">
        <i class="fa-solid fa-boxes-packing text-indigo-600"></i> Test Packages
    </a>
</div>
HTML;

$content = page_header('Official Rate List', "Complete tariff list for {$totalTestsCount} laboratory investigations and bundled health packages.", $headerActions);

// Search & Filter Toolbar (no-print)
$deptOptionsHtml = '<option value="">All Departments (' . count($grouped) . ')</option>';
foreach (array_keys($grouped) as $d) {
    $deptOptionsHtml .= '<option value="' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '</option>';
}

$content .= <<<HTML
<style>
@media print {
    .app-sidebar,
    .app-topbar,
    .app-footer,
    .no-print,
    .app-sidebar-backdrop {
        display: none !important;
    }
    .main-content-wrapper,
    .app-main {
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    .rate-list-document {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    .rate-table {
        font-size: 11px !important;
    }
    .rate-table th {
        background-color: #f1f5f9 !important;
        color: #000000 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .dept-header-row {
        background-color: #f8fafc !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        page-break-after: avoid;
    }
    tr {
        page-break-inside: avoid;
    }
}
</style>

<div class="no-print mb-5 p-4 bg-white border border-slate-200/90 rounded-3xl shadow-xs flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-3">
        <!-- Live Search -->
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs pointer-events-none"></i>
            <input type="search" id="filter-search" oninput="filterRateList()" placeholder="Filter test name or code…" class="pl-8 pr-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-slate-900/10 w-52 sm:w-64">
        </div>

        <!-- Department Filter Dropdown -->
        <select id="filter-dept" onchange="filterRateList()" class="py-1.5 px-3 rounded-xl border border-slate-200 text-xs font-semibold bg-white text-slate-700 focus:outline-none">
            {$deptOptionsHtml}
        </select>
    </div>

    <!-- Wholesale B2B Rate Column Toggle -->
    <label class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-3 py-1.5 rounded-xl cursor-pointer">
        <input type="checkbox" id="toggle-b2b" onchange="toggleB2BColumn()" class="w-3.5 h-3.5 rounded text-indigo-600 focus:ring-indigo-500">
        <span>Show Wholesale (B2B) Rates</span>
    </label>
</div>

<!-- Printable Document Sheet -->
<div class="rate-list-document bg-white border border-slate-200/90 rounded-3xl shadow-card p-6 sm:p-10 max-w-5xl mx-auto">
    
    <!-- Letterhead Branding Header -->
    <header class="border-b-2 border-slate-900 pb-5 mb-6">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-4 text-center sm:text-left">
HTML;

if ($logoUrl !== '') {
    $content .= '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Logo" class="w-16 h-16 object-contain shrink-0">';
} else {
    $content .= '<div class="w-14 h-14 rounded-2xl bg-slate-900 text-white flex items-center justify-center font-extrabold text-2xl shrink-0 shadow-sm">'
        . '<i class="fa-solid fa-flask-vial text-[#c2f13c]"></i>'
        . '</div>';
}

$labNameEsc = htmlspecialchars($labName, ENT_QUOTES, 'UTF-8');
$taglineEsc = htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8');
$addressEsc = htmlspecialchars($address, ENT_QUOTES, 'UTF-8');
$phoneEsc = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$emailEsc = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

$content .= <<<HTML
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight leading-none">{$labNameEsc}</h1>
                    <p class="text-xs font-semibold text-slate-500 mt-1">{$taglineEsc}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">{$addressEsc} · Tel: {$phoneEsc} · {$emailEsc}</p>
                </div>
            </div>

            <div class="text-center sm:text-right shrink-0 bg-slate-50 border border-slate-200 px-4 py-2.5 rounded-2xl">
                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block">Official Tariff</span>
                <span class="text-sm font-extrabold text-slate-900 block mt-0.5">Rate List Catalog</span>
                <span class="text-[11px] text-slate-500 font-semibold block mt-0.5"><i class="fa-regular fa-calendar-check mr-1 text-emerald-600"></i> Effective {$printDate}</span>
            </div>
        </div>
    </header>
HTML;

// Section 1: Bundled Health Packages
if (!empty($packages)) {
    $pkgCount = count($packages);
    $content .= <<<HTML
    <section class="mb-8" id="packages-section">
        <div class="flex items-center justify-between mb-3 border-b border-indigo-200 pb-2">
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 rounded-lg bg-indigo-600 text-white inline-flex items-center justify-center text-xs font-bold">
                    <i class="fa-solid fa-boxes-packing"></i>
                </span>
                <h2 class="text-sm font-bold text-indigo-950 uppercase tracking-wider">Health Packages &amp; Bundled Profiles</h2>
            </div>
            <span class="text-xs font-bold text-indigo-600">{$pkgCount} Packages Configured</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
HTML;

    foreach ($packages as $pkg) {
        $pkgPrice = (float)($pkg['price'] ?? 0);
        $pkgReg = (float)($pkg['regular_price'] ?? $pkgPrice);
        $pkgSaving = max(0, $pkgReg - $pkgPrice);
        $pCode = htmlspecialchars((string)$pkg['code'], ENT_QUOTES, 'UTF-8');
        $pName = htmlspecialchars((string)$pkg['name'], ENT_QUOTES, 'UTF-8');
        $pTests = htmlspecialchars((string)($pkg['tests_included'] ?? $pkg['tests'] ?? ''), ENT_QUOTES, 'UTF-8');
        $pPriceFmt = number_format($pkgPrice);
        $pRegFmt = number_format($pkgReg);

        $savingBadge = $pkgSaving > 0 ? '<span class="text-[10px] font-bold text-emerald-700 bg-emerald-100/80 px-1.5 py-0.5 rounded">Save Rs. ' . number_format($pkgSaving) . '</span>' : '';

        $content .= <<<HTML
            <div class="p-3.5 rounded-2xl border border-indigo-100 bg-indigo-50/40 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono text-[10px] font-bold text-indigo-700 bg-white px-2 py-0.5 rounded border border-indigo-200">{$pCode}</span>
                        {$savingBadge}
                    </div>
                    <h3 class="text-xs font-bold text-slate-900 mt-1.5">{$pName}</h3>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">{$pTests}</p>
                </div>
                <div class="mt-3 pt-2 border-t border-indigo-100/80 flex items-baseline justify-between">
                    <span class="text-xs text-slate-400 line-through">Rs. {$pRegFmt}</span>
                    <span class="text-sm font-extrabold text-emerald-700">Rs. {$pPriceFmt}</span>
                </div>
            </div>
HTML;
    }

    $content .= '</div></section>';
}

// Section 2: Department-Wise Diagnostic Investigation Tariffs
$content .= '<section class="space-y-6" id="investigations-section">';
$overallSr = 1;

foreach ($grouped as $deptName => $testsInDept) {
    $deptNameEsc = htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8');
    $deptCount = count($testsInDept);

    $content .= <<<HTML
    <div class="dept-block" data-dept="{$deptNameEsc}">
        <!-- Department Header -->
        <div class="dept-header-row bg-slate-100 border-l-4 border-slate-900 px-3.5 py-2 mb-2 rounded-r-xl flex items-center justify-between">
            <span class="text-xs font-extrabold uppercase tracking-wider text-slate-900">{$deptNameEsc}</span>
            <span class="text-[11px] font-bold text-slate-500">{$deptCount} Tests</span>
        </div>

        <!-- Tests Table -->
        <table class="rate-table w-full text-left text-xs mb-4">
            <thead>
                <tr class="border-b border-slate-200 text-slate-500 uppercase text-[10px] tracking-wider">
                    <th class="py-2 px-2.5 w-12 text-center">Sr.</th>
                    <th class="py-2 px-2.5 w-24">Code</th>
                    <th class="py-2 px-2.5">Investigation / Test Name</th>
                    <th class="py-2 px-2.5 w-32">Sample Type</th>
                    <th class="py-2 px-2.5 w-24 text-center">TAT</th>
                    <th class="py-2 px-2.5 w-28 text-right font-bold text-slate-800">Rate (PKR)</th>
                    <th class="py-2 px-2.5 w-28 text-right font-bold text-indigo-700 b2b-column hidden">B2B Rate</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
HTML;

    foreach ($testsInDept as $t) {
        $code = htmlspecialchars((string)($t['code'] ?? '—'), ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars((string)($t['name'] ?? '—'), ENT_QUOTES, 'UTF-8');
        $price = (float)($t['price'] ?? 0);
        $b2bPrice = (float)($t['b2b_price'] ?? round($price * 0.75));
        $sampleType = htmlspecialchars((string)($t['sample_type'] ?? $t['specimen'] ?? 'Serum / Blood'), ENT_QUOTES, 'UTF-8');
        $tat = htmlspecialchars((string)($t['tat'] ?? 'Same Day'), ENT_QUOTES, 'UTF-8');
        $searchData = htmlspecialchars(mb_strtolower($name . ' ' . $code . ' ' . $deptName), ENT_QUOTES, 'UTF-8');
        $priceFmt = number_format($price);
        $b2bPriceFmt = number_format($b2bPrice);
        $srNum = $overallSr++;

        $content .= <<<HTML
            <tr class="test-row hover:bg-slate-50/70 transition-all" data-search="{$searchData}">
                <td class="py-2 px-2.5 text-center text-slate-400 font-mono">{$srNum}</td>
                <td class="py-2 px-2.5 font-mono font-bold text-indigo-700">{$code}</td>
                <td class="py-2 px-2.5 font-semibold text-slate-900">{$name}</td>
                <td class="py-2 px-2.5 text-slate-600">{$sampleType}</td>
                <td class="py-2 px-2.5 text-center text-slate-500 font-medium">{$tat}</td>
                <td class="py-2 px-2.5 text-right font-extrabold text-emerald-800">Rs. {$priceFmt}</td>
                <td class="py-2 px-2.5 text-right font-bold text-indigo-700 b2b-column hidden">Rs. {$b2bPriceFmt}</td>
            </tr>
HTML;
    }

    $content .= '</tbody></table></div>';
}

$content .= '</section>';

// Document Notes Footer
$content .= <<<HTML
    <footer class="mt-8 pt-4 border-t border-slate-200 text-[10px] text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2">
        <div>
            <p class="font-semibold text-slate-700">Important Patient &amp; Client Notes:</p>
            <p>1. 10–12 hours fasting required for lipid profiles, fasting blood glucose, and ultrasound whole abdomen.</p>
            <p>2. Emergency &amp; STAT investigations available 24/7 with expedited turnaround time.</p>
            <p>3. Rates are inclusive of all sampling supplies and standard clinical verification.</p>
        </div>
        <div class="text-center sm:text-right shrink-0">
            <span class="font-bold text-slate-800">{$labNameEsc}</span>
            <span class="block text-slate-400">Computer Generated Document · Page 1 of 1</span>
        </div>
    </footer>
</div>

<!-- Interactive Client-Side Filter Script -->
<script>
function filterRateList() {
    const query = document.getElementById('filter-search').value.toLowerCase().trim();
    const selectedDept = document.getElementById('filter-dept').value;

    const deptBlocks = document.querySelectorAll('.dept-block');
    const pkgSection = document.getElementById('packages-section');

    if (pkgSection) {
        pkgSection.style.display = (query === '' && selectedDept === '') ? 'block' : 'none';
    }

    deptBlocks.forEach(block => {
        const blockDept = block.getAttribute('data-dept');
        const matchesDept = (!selectedDept || blockDept === selectedDept);

        if (!matchesDept) {
            block.style.display = 'none';
            return;
        }

        let visibleInDept = 0;
        const rows = block.querySelectorAll('.test-row');
        rows.forEach(row => {
            const searchData = row.getAttribute('data-search') || '';
            const matchesSearch = (!query || searchData.includes(query));

            if (matchesSearch) {
                row.style.display = '';
                visibleInDept++;
            } else {
                row.style.display = 'none';
            }
        });

        block.style.display = (visibleInDept > 0) ? '' : 'none';
    });
}

function toggleB2BColumn() {
    const isChecked = document.getElementById('toggle-b2b').checked;
    document.querySelectorAll('.b2b-column').forEach(el => {
        if (isChecked) {
            el.classList.remove('hidden');
        } else {
            el.classList.add('hidden');
        }
    });
}
</script>
HTML;

$portal = current_portal();
render_page('Official Rate List', $portal, 'rate-list', $content, true);
