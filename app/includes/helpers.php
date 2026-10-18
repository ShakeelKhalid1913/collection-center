<?php

declare(strict_types=1);

const FIELD_CLASS = 'field';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function brand_logo(string $class = 'brand-logo', string $alt = 'Lab Dash Pro'): string
{
    return '<img src="/assets/logo.png" alt="' . e($alt) . '" class="' . e($class) . '" width="128" height="128" decoding="async">';
}

/**
 * Vendor credit — Shakeel only (NOT the client lab WhatsApp).
 * One WhatsApp only: 0328-3070070
 */
function software_credit_footer(bool $compact = false): string
{
    $wa = 'https://wa.me/923283070070';
    $line = 'Software created by <strong style="display:inline!important;">Shakeel Khalid</strong> &middot; <a href="' . e($wa) . '" target="_blank" rel="noopener" style="display:inline!important;">WhatsApp 0328-3070070</a>';

    if ($compact) {
        return '<p class="software-credit software-credit--print" style="white-space:nowrap!important;text-align:center!important;margin:0.2rem auto 0!important;display:block!important;width:100%!important;font-size:0.68rem!important;line-height:1.2!important;">' . $line . '</p>';
    }

    return '<footer class="software-credit-bar">'
        . '<p class="software-credit" style="white-space:nowrap!important;text-align:center!important;display:block!important;width:100%!important;">' . $line . '</p>'
        . '</footer>';
}


function nav_item(string $href, string $label, string $activeKey, string $key, ?string $badge = null, string $icon = 'fa-solid fa-circle'): string
{
    $active = $activeKey === $key ? ' is-active' : '';
    $badgeHtml = $badge
        ? '<span class="ml-auto min-w-[1.25rem] rounded-full bg-teal-100 px-1.5 py-0.5 text-center text-[10px] font-bold text-teal-800">' . e($badge) . '</span>'
        : '';
    $iconHtml = '<i class="' . e($icon) . ' nav-link__icon" aria-hidden="true"></i>';
    return sprintf(
        '<a href="%s" class="nav-link%s">%s<span class="nav-link__text">%s</span>%s</a>',
        e($href),
        $active,
        $iconHtml,
        e($label),
        $badgeHtml
    );
}

function nav_group(string $title, array $items): string
{
    $html = $title !== '' ? '<p class="nav-section">' . e($title) . '</p>' : '';
    foreach ($items as $item) {
        $html .= $item;
    }
    return $html;
}

function status_badge(string $status): string
{
    $map = [
        'pending' => 'badge-pending',
        'collected' => 'badge-collected',
        'received' => 'badge-received',
        'processing' => 'badge-processing',
        'completed' => 'badge-completed',
        'verified' => 'badge-verified',
        'critical' => 'badge-critical',
        'paid' => 'badge-paid',
        'partial' => 'badge-partial',
        'active' => 'badge-active',
        'normal' => 'badge-default',
        'urgent' => 'badge-pending',
        'stat' => 'badge-critical',
    ];
    $class = $map[strtolower($status)] ?? 'badge-default';
    return '<span class="badge ' . $class . '">' . e(ucfirst($status)) . '</span>';
}

function stat_card(string $label, string $value, string $hint = '', string $tone = 'teal', string $icon = 'fa-solid fa-chart-simple'): string
{
    $tones = ['teal', 'blue', 'amber', 'violet', 'rose'];
    if (!in_array($tone, $tones, true)) {
        $tone = 'teal';
    }
    $label = e($label);
    $value = e($value);
    $hint = e($hint);
    $icon = e($icon);
    return <<<HTML
    <div class="stat-card stat-card--{$tone}">
        <div class="stat-card__icon" aria-hidden="true"><i class="{$icon}"></i></div>
        <div>
            <div class="stat-card__label">{$label}</div>
            <div class="stat-card__value">{$value}</div>
            <div class="stat-card__hint">{$hint}</div>
        </div>
    </div>
    HTML;
}

function page_header(string $title, string $subtitle = '', ?string $actionHtml = null, string $icon = 'fa-solid fa-layer-group'): string
{
    $title = e($title);
    $subtitle = e($subtitle);
    $action = $actionHtml ? '<div class="flex shrink-0 flex-wrap items-center gap-2.5">' . $actionHtml . '</div>' : '';
    $subHtml = $subtitle !== '' ? '<p class="text-xs sm:text-sm text-slate-500 font-medium">' . $subtitle . '</p>' : '';
    return <<<HTML
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between bg-white rounded-3xl p-5 sm:p-6 border border-slate-200/90 shadow-card">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-slate-900 to-slate-800 text-[#c2f13c] flex items-center justify-center text-xl font-black shadow-md shrink-0">
                <i class="{$icon}"></i>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">{$title}</h1>
                {$subHtml}
            </div>
        </div>
        {$action}
    </div>
    HTML;
}

function btn_primary(string $href, string $label, string $icon = 'fa-solid fa-arrow-right'): string
{
    return '<a href="' . e($href) . '" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs sm:text-sm transition-all shadow-md border border-slate-700/60"><i class="' . e($icon) . ' text-[#c2f13c]" aria-hidden="true"></i> ' . e($label) . '</a>';
}

function btn_secondary(string $href, string $label, string $icon = 'fa-solid fa-arrow-up-right-from-square'): string
{
    return '<a href="' . e($href) . '" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-white hover:bg-slate-50 text-slate-800 font-bold text-xs sm:text-sm transition-all border border-slate-300 shadow-sm"><i class="' . e($icon) . ' text-slate-500" aria-hidden="true"></i> ' . e($label) . '</a>';
}

function btn_submit(string $label, string $extraClass = '', string $icon = 'fa-solid fa-floppy-disk'): string
{
    return '<button type="submit" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs sm:text-sm transition-all shadow-md border border-slate-700/60 ' . e($extraClass) . '"><i class="' . e($icon) . ' text-[#c2f13c]" aria-hidden="true"></i> ' . e($label) . '</button>';
}

function link_action(string $label, string $icon = 'fa-solid fa-pen-to-square'): string
{
    return '<button type="button" class="link-action"><i class="' . e($icon) . '" aria-hidden="true"></i> ' . e($label) . '</button>';
}

function user_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $initials .= strtoupper(substr($p, 0, 1));
    }
    return $initials !== '' ? $initials : 'U';
}

/**
 * Auto high/low flag from a numeric result and reference range.
 * Supports "low - high", "< N", and "> N".
 */
function compute_result_flag(string $value, string $range): string
{
    $value = trim($value);
    $range = trim($range);
    if ($value === '' || $range === '' || $range === '—' || !is_numeric($value)) {
        return '';
    }
    $num = (float)$value;

    if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*[\-–]\s*([0-9]+(?:\.[0-9]+)?)$/', $range, $m)) {
        $low = (float)$m[1];
        $high = (float)$m[2];
        if ($num < $low) {
            return 'L';
        }
        if ($num > $high) {
            return 'H';
        }
        return '';
    }

    if (preg_match('/^<\s*([0-9]+(?:\.[0-9]+)?)$/', $range, $m)) {
        return $num >= (float)$m[1] ? 'H' : '';
    }

    if (preg_match('/^>\s*([0-9]+(?:\.[0-9]+)?)$/', $range, $m)) {
        return $num <= (float)$m[1] ? 'L' : '';
    }

    return '';
}

/**
 * Parse dropdown options from a range/options string.
 * Accepts "Options: A|B", "Positive / Negative", "S / I / R", or comma lists.
 *
 * @return list<string>|null
 */
function parse_result_options(?string $raw): ?array
{
    $raw = trim((string)$raw);
    if ($raw === '' || $raw === '—') {
        return null;
    }

    if (preg_match('/^Options:\s*(.+)$/i', $raw, $m)) {
        $raw = trim($m[1]);
    }

    if (preg_match('/^S\s*[\/|,]\s*I\s*[\/|,]\s*R$/i', $raw)) {
        return ['S', 'I', 'R'];
    }

    $parts = preg_split('/\s*[|\/,]\s*/', $raw) ?: [];
    $parts = array_values(array_filter(array_map('trim', $parts), static fn($p) => $p !== ''));
    if (count($parts) < 2) {
        return null;
    }

    foreach ($parts as $p) {
        // Numeric ranges are not option lists
        if (is_numeric($p) || preg_match('/^[<>]=?\s*[0-9]/', $p) || preg_match('/[0-9]+\s*[\-–]\s*[0-9]+/', $p)) {
            return null;
        }
        if (strlen($p) > 40) {
            return null;
        }
    }

    return $parts;
}

/**
 * Diagnostic Center & Hospital Department Metadata Provider.
 * Categorizes any test into one of the 6 core clinical departments:
 * - Laboratory (Pathology, Hematology, Biochemistry, etc.)
 * - Ultrasound (USG / Sonology / Doppler)
 * - X-Ray (Digital Radiography)
 * - ECG (Cardiology / 12-Lead Electrocardiography)
 * - CT Scan (Computed Tomography)
 * - MRI (Magnetic Resonance Imaging)
 */
function get_department_meta(string $testName, ?string $category = null): array
{
    $name = trim($testName);
    $cat = $category !== null ? trim($category) : '';
    $testUpper = strtoupper($name . ' ' . $cat);

    // 1. Ultrasound / USG / Doppler / Sonology
    if (strcasecmp($cat, 'Ultrasound') === 0 
        || preg_match('/(ultrasound|usg|doppler|sonograph|pelvis\s*scan|abdomen\s*scan|kub\s*scan|follicular\s*track|anomaly\s*scan|transvaginal|tvs\b)/i', $testUpper)
    ) {
        return [
            'id' => 'ultrasound',
            'name' => 'Ultrasound',
            'short' => 'USG',
            'icon' => 'fa-solid fa-wave-square',
            'icon_color' => 'text-teal-600',
            'badge_class' => 'bg-teal-50 text-teal-800 border-teal-200',
            'station_url' => '/portals/imaging/ultrasound.php',
            'report_url' => '/portals/imaging/reports.php',
            'is_imaging' => true,
        ];
    }

    // 2. X-Ray / Radiography
    if (strcasecmp($cat, 'X-Ray') === 0
        || preg_match('/(x-ray|xray|radiograph|chest\s*pa|chest\s*ap|spine\s*x|bone\s*x|joint\s*x)/i', $testUpper)
    ) {
        return [
            'id' => 'xray',
            'name' => 'X-Ray',
            'short' => 'X-Ray',
            'icon' => 'fa-solid fa-x-ray',
            'icon_color' => 'text-blue-600',
            'badge_class' => 'bg-blue-50 text-blue-800 border-blue-200',
            'station_url' => '/portals/imaging/xray.php',
            'report_url' => '/portals/imaging/reports.php',
            'is_imaging' => true,
        ];
    }

    // 3. ECG / Cardiology
    if (strcasecmp($cat, 'ECG') === 0 || strcasecmp($cat, 'Cardiology') === 0
        || preg_match('/\b(ecg|ekg|electrocardiogra|rhythm\s*strip)\b/i', $testUpper)
    ) {
        return [
            'id' => 'ecg',
            'name' => 'ECG',
            'short' => 'ECG',
            'icon' => 'fa-solid fa-heart-pulse',
            'icon_color' => 'text-rose-600',
            'badge_class' => 'bg-rose-50 text-rose-800 border-rose-200',
            'station_url' => '/portals/imaging/ecg.php',
            'report_url' => '/portals/imaging/reports.php',
            'is_imaging' => true,
        ];
    }

    // 4. CT Scan / Computed Tomography (avoid clotting time / bleeding time BT CT)
    if (strcasecmp($cat, 'CT Scan') === 0 || strcasecmp($cat, 'CT') === 0
        || (!preg_match('/(bleeding|clotting|bt\s*ct|bt\s*\/\s*ct)/i', $testUpper)
            && preg_match('/\b(ct\s*scan|hrct|computed\s*tomography|brain\s*ct|chest\s*ct|abdomen\s*ct|ct\s+(brain|chest|abdomen|pelvis|head|spine|neck|pns|kub|angio|plain|contrast|cervical|lumbar|dorsal|extremit|temporal|orbit|paranasal|virtual|whole))\b/i', $testUpper))
    ) {
        return [
            'id' => 'ct',
            'name' => 'CT Scan',
            'short' => 'CT',
            'icon' => 'fa-solid fa-laptop-medical',
            'icon_color' => 'text-purple-600',
            'badge_class' => 'bg-purple-50 text-purple-800 border-purple-200',
            'station_url' => '/portals/imaging/ct.php',
            'report_url' => '/portals/imaging/reports.php',
            'is_imaging' => true,
        ];
    }

    // 5. MRI / Magnetic Resonance Imaging
    if (strcasecmp($cat, 'MRI') === 0
        || preg_match('/\b(mri|magnetic\s*resonance|mrcp)\b/i', $testUpper)
    ) {
        return [
            'id' => 'mri',
            'name' => 'MRI',
            'short' => 'MRI',
            'icon' => 'fa-solid fa-magnet',
            'icon_color' => 'text-indigo-600',
            'badge_class' => 'bg-indigo-50 text-indigo-800 border-indigo-200',
            'station_url' => '/portals/imaging/mri.php',
            'report_url' => '/portals/imaging/reports.php',
            'is_imaging' => true,
        ];
    }

    // 6. Default: Laboratory (Pathology, Hematology, Biochemistry, etc.)
    $labCat = $cat !== '' ? $cat : 'Pathology';
    return [
        'id' => 'lab',
        'name' => 'Laboratory',
        'short' => 'Lab',
        'category' => $labCat,
        'icon' => 'fa-solid fa-flask-vial',
        'icon_color' => 'text-emerald-600',
        'badge_class' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'station_url' => '/portals/main-lab/results/entry.php',
        'report_url' => '/portals/main-lab/reports/preview.php',
        'is_imaging' => false,
    ];
}

/**
 * Classify a comma-separated tests string into department groupings.
 * @return array<string, array{meta: array, tests: list<string>}>
 */
function classify_order_departments(string $testsString): array
{
    $parts = array_filter(array_map('trim', explode(',', $testsString)));
    $grouped = [];
    foreach ($parts as $p) {
        $meta = get_department_meta($p);
        $dId = $meta['id'];
        if (!isset($grouped[$dId])) {
            $grouped[$dId] = [
                'meta' => $meta,
                'tests' => [],
            ];
        }
        $grouped[$dId]['tests'][] = $p;
    }
    return $grouped;
}

/**
 * Render cleanly separated department badges with distinct icons for any order or test.
 */
function render_department_badges_html(string $testsString, ?string $labNo = null, bool $withLinks = true): string
{
    $grouped = classify_order_departments($testsString);
    if (empty($grouped)) {
        return '<span class="text-xs text-slate-400">—</span>';
    }

    $html = '<div class="inline-flex flex-wrap items-center gap-1.5">';
    foreach ($grouped as $deptId => $item) {
        $m = $item['meta'];
        $testsCount = count($item['tests']);
        $testsList = implode(', ', $item['tests']);
        $label = $m['short'] . ($testsCount > 1 ? " ({$testsCount})" : ": {$item['tests'][0]}");
        
        $url = '';
        if ($withLinks && $labNo !== null && $labNo !== '') {
            $q = urlencode($labNo);
            if ($deptId === 'lab') {
                $url = "/portals/main-lab/reports/preview.php?lab_no={$q}";
            } else {
                $url = "/portals/imaging/reports.php?scan_no={$q}";
            }
        }

        $tag = $url !== '' ? 'a' : 'span';
        $hrefAttr = $url !== '' ? ' href="' . e($url) . '"' : '';

        $html .= "<{$tag}{$hrefAttr} class=\"inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-bold border {$m['badge_class']} shadow-2xs hover:opacity-90 transition-opacity\" title=\"" . e($testsList) . "\">"
            . "<i class=\"{$m['icon']} {$m['icon_color']}\"></i>"
            . "<span>" . e($label) . "</span>"
            . "</{$tag}>";
    }
    $html .= '</div>';
    return $html;
}

/**
 * Standard Normal Diagnostic & Radiology Report Templates.
 * Shared across Ultrasound, X-Ray, ECG, CT Scan, and MRI departments.
 * Pre-configured normal clinical texts ready for one-click insertion and consultant editing.
 */
function get_all_imaging_templates(): array
{
    return [
        // --- 1. ULTRASOUND TEMPLATES ---
        'us_whole_abd' => [
            'id' => 'us_whole_abd',
            'dept' => 'ultrasound',
            'title' => 'Ultrasound Whole Abdomen & Pelvis (Normal)',
            'findings' => "LIVER: Normal in size, smooth contour and uniform parenchymal echotexture. No focal solid or cystic mass lesion seen. Intrahepatic biliary tree and portal vein are normal in caliber.\n\nGALLBLADDER: Normal in size and shape. Wall thickness is normal (< 3 mm). Acoustic lumen is completely clear; no calculus, sludge or mass lesion seen. Common bile duct (CBD) is normal.\n\nPANCREAS: Visualized head, body and tail appear normal in size and echogenicity. Main pancreatic duct is not dilated.\n\nSPLEEN: Normal in size (bipolar diameter < 12 cm) and parenchymal echotexture. No splenomegaly or focal lesion noted.\n\nKIDNEYS: Both kidneys are normal in size, shape and cortical thickness. Corticomedullary differentiation is well preserved. No calculus, hydronephrosis or perinephric collection seen bilaterally.\nRight Kidney: 10.4 x 4.5 cm (Cortical thickness: 14 mm)\nLeft Kidney: 10.8 x 4.8 cm (Cortical thickness: 15 mm)\n\nURINARY BLADDER: Well distended with thin, smooth walls and clear lumen. No intraluminal calculus or mass.\n\nPELVIS / PROSTATE: Visualized pelvic structures are unremarkable. No free fluid or collection in abdomen or pouch of Douglas.",
            'impression' => "NORMAL ULTRASOUND EXAMINATION OF WHOLE ABDOMEN & PELVIS.\nNo evidence of hepatosplenomegaly, cholelithiasis, nephrolithiasis, hydronephrosis, or free fluid.",
        ],
        'us_upper_abd' => [
            'id' => 'us_upper_abd',
            'dept' => 'ultrasound',
            'title' => 'Ultrasound Upper Abdomen / Hepatobiliary (Normal)',
            'findings' => "LIVER: Normal in size (span 14.2 cm), smooth borders and homogeneous parenchymal echogenicity. No focal lesion, cyst or mass detected. Intrahepatic biliary radicals and portal vein (10 mm) are normal.\n\nGALLBLADDER: Well distended, thin-walled (< 3 mm) without intraluminal calculus, mass or pericholecystic fluid. Common bile duct is normal in caliber (4 mm).\n\nPANCREAS: Visualized head, body and tail appear normal in size and echotexture. No focal mass or ductal dilatation.\n\nSPLEEN: Normal in size and echogenicity. Splenic vein is patent and normal.\n\nBoth kidneys show normal position, size and cortical thickness without hydronephrosis.\nNo ascites or retroperitoneal lymphadenopathy identified.",
            'impression' => "NORMAL HEPATOBILIARY & UPPER ABDOMINAL ULTRASOUND STUDY.",
        ],
        'us_kub' => [
            'id' => 'us_kub',
            'dept' => 'ultrasound',
            'title' => 'Ultrasound KUB & Prostate (Normal)',
            'findings' => "RIGHT KIDNEY: Normal in position, size (10.2 x 4.4 cm) and shape. Cortical thickness is 14 mm with clear corticomedullary junction. No calculus, hydronephrosis, cyst or mass seen.\n\nLEFT KIDNEY: Normal in position, size (10.6 x 4.6 cm) and shape. Cortical thickness is 15 mm. No calculus, hydronephrosis or focal lesion identified.\n\nURETERS: Both upper and lower ureters are not dilated.\n\nURINARY BLADDER: Adequately distended with thin, regular walls (< 3 mm). Lumen is clear without calculus, polyp or mass lesion. Post-void residual volume is insignificant (< 15 ml).\n\nPROSTATE (MALE): Normal size and homogeneous parenchymal echotexture. Capsule is intact. Estimated weight: approx. 18 grams.",
            'impression' => "NORMAL ULTRASOUND STUDY OF KIDNEYS, URETERS, BLADDER & PROSTATE (KUB).\nNo evidence of nephrolithiasis, obstructive uropathy, or significant post-void residue.",
        ],
        'us_pelvis' => [
            'id' => 'us_pelvis',
            'dept' => 'ultrasound',
            'title' => 'Ultrasound Pelvis - Female (Normal)',
            'findings' => "URINARY BLADDER: Well distended with thin, smooth walls and clear anechoic lumen.\n\nUTERUS: Anteverted, normal in size (7.6 x 4.2 x 3.8 cm), shape and myometrial echotexture. Myometrium is homogeneous without focal leiomyoma or adenomyosis. Endometrial stripe is central, regular with normal thickness (7 mm).\n\nRIGHT OVARY: Normal in size (2.8 x 1.8 cm) and location. Shows physiological small follicles. No cystic or solid mass lesion.\n\nLEFT OVARY: Normal in size (2.7 x 1.7 cm) with physiological follicular development. No adnexal cyst or mass.\n\nPOUCH OF DOUGLAS: Completely clear. No free fluid or adnexal mass noted.",
            'impression' => "NORMAL PELVIC ULTRASOUND EXAMINATION.\nNormal anteverted uterus with regular endometrium and normal bilateral ovaries.",
        ],
        'us_obstetric' => [
            'id' => 'us_obstetric',
            'dept' => 'ultrasound',
            'title' => 'Ultrasound Obstetric / Pregnancy (Routine Normal)',
            'findings' => "REAL-TIME SCAN: Demonstrates a single live intrauterine fetus in cephalic / longitudinal presentation.\n\nFETAL CARDIAC ACTIVITY: Present and regular (Heart Rate: 144 beats/min). Active fetal movements observed.\n\nBIOMETRY:\n- Biparietal Diameter (BPD): Normal for gestational age\n- Head Circumference (HC): Normal for gestational age\n- Abdominal Circumference (AC): Normal for gestational age\n- Femur Length (FL): Normal for gestational age\n\nCOMPOSITE GESTATIONAL AGE: Concordant with dates.\nESTIMATED FETAL WEIGHT (EFW): Appropriate for gestational age.\n\nLIQUOR AMNII: Adequate in volume (Amniotic Fluid Index within normal range: 13.5 cm).\n\nPLACENTA: Fundal / posterior in location, Grade I maturity, well away from internal os. No retroplacental clot.\n\nCERVIX: Closed internal os, cervical length is adequate (> 35 mm).",
            'impression' => "SINGLE LIVE INTRAUTERINE PREGNANCY.\nFetal growth, biometry, cardiac activity, and liquor volume are normal and concordant with gestational age.",
        ],
        'us_anomaly' => [
            'id' => 'us_anomaly',
            'dept' => 'ultrasound',
            'title' => 'Ultrasound 2nd Trimester Fetal Anomaly Scan (Normal)',
            'findings' => "FETAL ANATOMICAL SURVEY (Targeted 2nd Trimester):\n- CRANIUM & BRAIN: Intact skull vault, normal midline falx, symmetrical lateral ventricles (< 8 mm), cavum septum pellucidum visualized, normal posterior fossa & cerebellum. Cisterna magna is normal (5 mm).\n- FACE: Intact upper lip and primary palate. Bilateral orbits and nasal bone present.\n- THORAX & HEART: Symmetrical lung fields. Normal four-chamber view of heart with intact ventricular septum and normal outflow tracts crossing. Regular rhythm (142 bpm).\n- ABDOMEN: Intact anterior abdominal wall with normal umbilical cord insertion. Stomach bubble present on left. Intact bowel.\n- KIDNEYS & BLADDER: Both renal fossae occupied with normal kidneys, no pelvic dilatation. Urinary bladder visualized.\n- SPINE: Complete intact neural tube from cervical to sacral levels without splaying or meningocele.\n- EXTREMITIES: Bilateral humeri, forearms, hands, femurs, lower legs and feet identified with normal mobility and digits.\n- PLACENTA & LIQUOR: Posterior fundal placenta, grade I maturity. Amniotic fluid index is normal (14 cm).",
            'impression' => "NORMAL TARGETED 2ND TRIMESTER FETAL ANOMALY SCAN.\nNo gross structural or morphological fetal anomaly identified at the time of examination.",
        ],
        'us_scrotal' => [
            'id' => 'us_scrotal',
            'dept' => 'ultrasound',
            'title' => 'Ultrasound Scrotum & Testicular Doppler (Normal)',
            'findings' => "RIGHT TESTIS: Normal in scrotal sac, normal size (4.0 x 2.4 x 2.1 cm) and homogeneous parenchymal echogenicity. Epididymal head, body and tail are normal. Intratesticular vascularity on Color and Spectral Doppler is normal with low-resistance arterial waveforms.\n\nLEFT TESTIS: Normal in scrotal sac, normal size (4.1 x 2.3 x 2.0 cm) and homogeneous echotexture. Epididymis is unremarkable. Color Doppler reveals normal symmetric parenchymal vascularity.\n\nPAMPINIFORM PLEXUS: Caliber of pampiniform venous plexus is normal bilaterally (< 2 mm at rest). No venous dilatation or retrograde reflux on Valsalva maneuver.\n\nSCROTAL SAC: Normal wall thickness. No hydrocele, hematocele or pyocele bilaterally.",
            'impression' => "NORMAL TESTICULAR & SCROTAL COLOR DOPPLER STUDY.\nNo evidence of testicular torsion, epididymo-orchitis, varicocele, or mass lesion.",
        ],
        'us_thyroid' => [
            'id' => 'us_thyroid',
            'dept' => 'ultrasound',
            'title' => 'Ultrasound Thyroid & Neck (Normal)',
            'findings' => "RIGHT LOBE: Measures 4.2 x 1.4 x 1.3 cm. Shows uniform, homogeneous parenchymal echotexture with smooth margins. No focal solid, cystic or calcified nodule seen.\n\nLEFT LOBE: Measures 4.0 x 1.3 x 1.2 cm. Normal size and homogeneous echogenicity without nodule.\n\nISTHMUS: Normal thickness (2.5 mm) with smooth contours.\n\nCOLOR DOPPLER: Parenchymal vascularity is symmetrical and normal throughout both lobes. No thyroid inferno pattern.\n\nCERVICAL LYMPH NODES: Bilateral carotid sheaths and cervical chains show no pathologically enlarged or necrotic lymph nodes.",
            'impression' => "NORMAL ULTRASOUND EXAMINATION OF THYROID GLAND & NECK.\nNo goiter, solitary thyroid nodule, or cervical lymphadenopathy.",
        ],
        'us_breast' => [
            'id' => 'us_breast',
            'dept' => 'ultrasound',
            'title' => 'Ultrasound Breast - Bilateral (Normal / BI-RADS 1)',
            'findings' => "BILATERAL BREASTS: Evaluated in radial and antiradial planes.\nDemonstrate normal fibroglandular parenchyma and subcutaneous fat with uniform acoustic architecture. No discrete solid mass, architectural distortion, abnormal acoustic shadowing, or suspicious clustered microcalcifications.\nRetroareolar regions and Cooper's ligaments appear intact bilaterally.\n\nAXILLAE: Bilateral axillary regions show preserved fatty hilum lymph nodes of benign reactive morphology. No pathological axillary lymphadenopathy.",
            'impression' => "NORMAL BILATERAL BREAST ULTRASOUND STUDY (BI-RADS CATEGORY 1: NEGATIVE).\nNo sonographic evidence of solid mass lesion or malignancy.",
        ],

        // --- 2. DIGITAL X-RAY TEMPLATES ---
        'xr_chest_pa' => [
            'id' => 'xr_chest_pa',
            'dept' => 'xray',
            'title' => 'Chest X-Ray PA View (Normal)',
            'findings' => "LUNG FIELDS: Both lung fields are clear and well expanded without focal consolidation, cavitation, infiltrate, mass or reticulonodular opacity. Bronchovascular markings are within normal limits.\n\nPLEURA: Both costophrenic and cardiophrenic angles are sharp and clear. No pleural effusion or pneumothorax.\n\nCARDIAC SILHOUETTE: Transverse cardiac diameter is within normal limits. Cardiothoracic ratio (CTR) is normal (< 0.50). Aortic knuckle and pulmonary conus are unremarkable.\n\nMEDIASTINUM: Mediastinal contours, hilar shadows and trachea are central and within normal limits.\n\nBONY THORAX & SOFT TISSUES: Ribs, clavicles and visualized thoracic cage bones show normal alignment and intact cortices. Diaphragmatic domes are smooth and regular. Soft tissue shadows unremarkable.",
            'impression' => "NORMAL CHEST RADIOGRAPH (PA VIEW).\nNo active parenchymal lesion, cardiomegaly, or pleural collection.",
        ],
        'xr_chest_ap' => [
            'id' => 'xr_chest_ap',
            'dept' => 'xray',
            'title' => 'Chest X-Ray AP View (Normal)',
            'findings' => "LUNG FIELDS: Bilateral lung zones are clear without consolidation, active infiltrate or collapse. Pulmonary vasculature is normal.\n\nPLEURAL SPACES: Bilateral CP angles are clear without effusion or pneumothorax.\n\nHEART & MEDIASTINUM: Cardiac size is within normal limits for AP projection. Trachea is central in position. Mediastinum unremarkable.\n\nBONES & SOFT TISSUES: Thoracic cage skeleton shows normal mineralization and intact architecture.",
            'impression' => "NORMAL CHEST AP RADIOGRAPH.\nNo acute cardiopulmonary abnormality detected.",
        ],
        'xr_cervical' => [
            'id' => 'xr_cervical',
            'dept' => 'xray',
            'title' => 'Cervical Spine X-Ray AP/LAT (Normal)',
            'findings' => "ALIGNMENT: Normal physiological cervical lordosis is maintained. Anterior and posterior spinal lines and spinolaminar line are smooth and uninterrupted.\n\nVERTEBRAE: Cervical vertebral body heights, cortical margins and pedicles are intact. No compression fracture, lytic or sclerotic bone lesion.\n\nDISC SPACES: Intervertebral disc spaces from C2 through C7 are well preserved without narrowing or osteophyte formation.\n\nPREVERTEBRAL TISSUES: Prevertebral soft tissue shadow is within normal physiological limits. Atlanto-dens interval (ADI) is normal (< 3 mm).",
            'impression' => "NORMAL CERVICAL SPINE RADIOGRAPH (AP & LATERAL VIEWS).\nNo fracture, listhesis, or degenerative disc disease.",
        ],
        'xr_ls_spine' => [
            'id' => 'xr_ls_spine',
            'dept' => 'xray',
            'title' => 'L/S Spine X-Ray AP/LAT (Normal)',
            'findings' => "ALIGNMENT: Normal physiological lumbar lordosis is preserved. No evidence of spondylolisthesis or scoliosis.\n\nVERTEBRAL BODIES: Lumbar vertebral bodies (L1 through L5) show normal height, trabecular pattern and intact cortical borders. Posterior elements, pedicles and transverse processes are intact.\n\nINTERVERTEBRAL DISCS: Disc spaces from L1-L2 down to L5-S1 are well maintained with no significant narrowing or endplate sclerosis. No marginal osteophytes.\n\nSACROILIAC JOINTS: Visualized SI joints and psoas margins are symmetrical and intact.",
            'impression' => "NORMAL LUMBO-SACRAL (L/S) SPINE RADIOGRAPH.\nNo fracture, spondylolisthesis, or significant spondylotic change.",
        ],
        'xr_knee' => [
            'id' => 'xr_knee',
            'dept' => 'xray',
            'title' => 'Knee Joint X-Ray AP/LAT (Normal)',
            'findings' => "BONY STRUCTURES: Visualized distal femur, proximal tibia, fibular head and patella demonstrate normal cortical margins, alignment and trabecular pattern. No fracture or focal bone lesion.\n\nJOINT SPACES: Medial and lateral tibiofemoral joint compartments show normal and symmetrical height without joint space narrowing. Patellofemoral joint space is preserved.\n\nARTICULAR SURFACES: Smooth and regular without subchondral sclerosis, subchondral cysts, or osteophytes.\n\nSOFT TISSUES: No joint effusion, suprapatellar pouch distension or abnormal soft tissue calcification.",
            'impression' => "NORMAL KNEE JOINT RADIOGRAPH (AP & LATERAL VIEWS).\nNo fracture, dislocation, joint effusion, or osteoarthritis.",
        ],
        'xr_abd_plain' => [
            'id' => 'xr_abd_plain',
            'dept' => 'xray',
            'title' => 'Abdomen Plain X-Ray Erect/Supine (Normal)',
            'findings' => "BOWEL GAS PATTERN: Normal non-dilated bowel gas distribution throughout small bowel and colon. No abnormally dilated bowel loops or multiple air-fluid levels.\n\nFREE AIR: No pneumoperitoneum or subdiaphragmatic crescent of free gas on erect projection.\n\nCALCIFICATIONS: No radiopaque renal, ureteric, vesical or gallstone calculus seen. Psoas muscle outlines and properitoneal fat lines are preserved.\n\nBONES: Visualized pelvic and lower thoracic bones appear intact.",
            'impression' => "NORMAL PLAIN ABDOMINAL RADIOGRAPH.\nNo signs of bowel obstruction, perforation, or radiopaque urinary calculus.",
        ],

        // --- 3. 12-LEAD ECG TEMPLATES ---
        'ecg_12lead' => [
            'id' => 'ecg_12lead',
            'dept' => 'ecg',
            'title' => '12-Lead Electrocardiogram (Normal Sinus Rhythm)',
            'findings' => "RHYTHM: Regular Sinus Rhythm.\nHEART RATE: 74 beats per minute.\nP WAVE: Normal morphology, axis, and duration (< 0.12 sec) in lead II.\nPR INTERVAL: 0.16 seconds (Normal 0.12 - 0.20 sec). No AV block.\nQRS COMPLEX: Duration 0.08 seconds (Normal < 0.10 sec). Normal R-wave progression across precordial leads V1 through V6. No pathological Q waves.\nAXIS: Normal electrical frontal plane axis (+45 degrees).\nST SEGMENT: Isoelectric; no significant ST elevation or depression in limb or chest leads.\nT WAVES: Normal upright morphology in leads I, II, aVF, and V2-V6. No T-wave inversion or peaking.\nQT / QTc INTERVAL: QT: 380 ms, QTc (Bazett): 420 ms (Normal < 440 ms in males, < 460 ms in females).",
            'impression' => "NORMAL 12-LEAD ELECTROCARDIOGRAM (ECG).\nNormal sinus rhythm with normal intervals and no evidence of acute ischemia or arrhythmia.",
        ],
        'ecg_resting_strip' => [
            'id' => 'ecg_resting_strip',
            'dept' => 'ecg',
            'title' => 'Resting 12-Lead ECG with Rhythm Strip (Normal)',
            'findings' => "Rate: 72 bpm | Rhythm: Regular Sinus | Axis: Normal (0 to +90)\nPR Interval: 154 ms | QRS Duration: 84 ms | QTc: 418 ms\nContinuous Lead II rhythm strip shows consistent P waves preceding every QRS with constant PR interval. No ectopics, pause or premature atrial/ventricular complexes.",
            'impression' => "NORMAL RESTING 12-LEAD ECG. No cardiac arrhythmia or repolarization abnormality.",
        ],

        // --- 4. CT SCAN TEMPLATES ---
        'ct_brain_plain' => [
            'id' => 'ct_brain_plain',
            'dept' => 'ct',
            'title' => 'Brain Plain CT Scan (Normal)',
            'findings' => "TECHNIQUE: Non-contrast axial CT images of brain obtained from base of skull through vertex with bone and soft tissue reconstructions.\n\nCEREBRAL HEMISPHERES: Symmetrical cerebral and cerebellar hemispheres with normal grey-white matter differentiation. No focal area of abnormal hypoattenuation (ischemia/infarction) or hyperattenuation (acute intra-axial or extra-axial hemorrhage).\n\nBASAL GANGLIA & BRAINSTEM: Basal ganglia, internal capsules and thalami are unremarkable. Brainstem and posterior fossa structures appear normal.\n\nVENTRICLES & CISTERNS: Lateral, third and fourth ventricles are normal in size, shape and symmetry. Basal cisterns and cortical sulci are age-appropriate without effacement.\n\nMIDLINE STRUCTURES: Midline structures are central; no midline shift or herniation.\n\nBONY VAULT: Calvarium, skull base and visualized paranasal sinuses are intact. No fracture or lytic bone lesion.",
            'impression' => "NORMAL NON-CONTRAST BRAIN CT SCAN.\nNo acute intracranial hemorrhage, territorial infarct, or mass effect.",
        ],
        'ct_hrct_chest' => [
            'id' => 'ct_hrct_chest',
            'dept' => 'ct',
            'title' => 'HRCT Chest (High Resolution CT - Normal)',
            'findings' => "TECHNIQUE: High-resolution volumetric axial CT scan of the thorax acquired in full inspiration.\n\nLUNG PARENCHYMA: Bilateral lungs show normal attenuation and aeration throughout. No ground-glass opacities, consolidation, reticulation, interlobular septal thickening, bronchiectasis, or honeycombing. No solitary or multiple pulmonary nodules.\n\nAIRWAYS: Trachea, carina and bilateral major bronchi are patent with normal branching and smooth caliber.\n\nMEDIASTINUM & HILA: Heart is within normal limits. Great mediastinal vessels and central pulmonary arteries are normal in caliber. No mediastinal or hilar lymphadenopathy.\n\nPLEURA: No pleural effusion, pleural thickening or pneumothorax bilaterally.\n\nCHEST WALL: Visualized ribs, clavicles, scapulae and soft tissues are intact.",
            'impression' => "NORMAL HIGH-RESOLUTION CHEST CT SCAN (HRCT).\nNo evidence of interstitial lung disease (ILD), active infection, or mediastinal lymphadenopathy.",
        ],
        'ct_abd_pelvis' => [
            'id' => 'ct_abd_pelvis',
            'dept' => 'ct',
            'title' => 'CT Abdomen & Pelvis with Contrast (Normal)',
            'findings' => "LIVER: Normal in size and contour with uniform parenchymal enhancement. No focal hepatic lesion or biliary dilatation.\nGALLBLADDER: Normal wall thickness, lumen clear. CBD is normal in caliber.\nPANCREAS & SPLEEN: Normal in size, parenchymal enhancement and morphology.\nADRENAL GLANDS: Normal bilateral limb morphology.\nKIDNEYS: Normal symmetric nephrogram and excretion without hydronephrosis or renal calculus.\nBOWEL LOOPS: Normal wall thickness and luminal caliber throughout stomach, small bowel and colon.\nMESENTERY & RETROPERITONEUM: No pathological lymphadenopathy, ascites, or free fluid collection.\nURINARY BLADDER & PELVIC VISCERA: Normal in appearance with smooth margins.",
            'impression' => "NORMAL CONTRAST-ENHANCED CT SCAN OF ABDOMEN & PELVIS.\nNo acute intra-abdominal pathology, mass lesion, or lymphadenopathy.",
        ],
        'ct_pns' => [
            'id' => 'ct_pns',
            'dept' => 'ct',
            'title' => 'CT PNS - Paranasal Sinuses (Normal)',
            'findings' => "Bilateral maxillary, frontal, ethmoid and sphenoid sinuses are well pneumatized and clear without mucosal thickening, polypoid mass or air-fluid levels.\nBilateral osteomeatal complexes are patent.\nNasal septum is midline without significant deviation or spur.\nNasal turbinates are normal in configuration. Bony sinus walls, cribriform plates and lamina papyracea are intact.",
            'impression' => "NORMAL CT EXAMINATION OF PARANASAL SINUSES (PNS).\nPatent osteomeatal complexes with clear sinus cavities.",
        ],

        // --- 5. MRI TEMPLATES ---
        'mri_brain_plain' => [
            'id' => 'mri_brain_plain',
            'dept' => 'mri',
            'title' => 'Brain Plain MRI (Normal)',
            'findings' => "TECHNIQUE: Multiplanar multi-echo MR imaging of brain performed using T1W, T2W, FLAIR, DWI and ADC sequences.\n\nCEREBRAL PARENCHYMA: Normal signal intensity of cerebral cortex and deep white matter. No abnormal T2/FLAIR hyperintensity or focal signal alteration. DWI demonstrates no restricted diffusion (acute infarction or abscess).\n\nBASAL GANGLIA & VENTRICLES: Symmetrical basal ganglia, corpus callosum and internal capsules. Ventricular system, basal cisterns and sulci are normal in size and symmetry.\n\nPOSTERIOR FOSSA: Brainstem and cerebellum show normal signal intensity and morphology. Fourth ventricle is central.\n\nVASCULAR FLOW: Major intracranial vascular flow voids (Circle of Willis) are well maintained.\n\nBONES & ORBITS: Visualized calvarium, orbits and craniocervical junction appear unremarkable.",
            'impression' => "NORMAL NON-CONTRAST BRAIN MRI STUDY.\nNo acute infarction, intracranial hemorrhage, demyelinating plaque, or space-occupying lesion (SOL).",
        ],
        'mri_lumbar' => [
            'id' => 'mri_lumbar',
            'dept' => 'mri',
            'title' => 'Lumbar Spine MRI (Normal)',
            'findings' => "TECHNIQUE: Sagittal T1W, T2W, and axial T2W images of the lumbar spine.\n\nALIGNMENT: Normal physiological lumbar lordosis is maintained with normal vertebral alignment. No spondylolisthesis.\n\nVERTEBRAE: Vertebral body heights and marrow signal intensities from L1 to L5 are normal. No compression fracture or bone marrow infiltration.\n\nINTERVERTEBRAL DISCS: Disc hydration signal on T2W is preserved across all lumbar levels (L1-L2 to L5-S1). No disc bulge, protrusion, extrusion, or herniation.\n\nSPINAL CANAL & ROOTS: The spinal canal, thecal sac and exit neural foramina show normal caliber throughout. Conus medullaris terminates normally at L1-L2 level with normal cauda equina nerve rootlets.",
            'impression' => "NORMAL MRI EXAMINATION OF LUMBAR SPINE.\nNo disc herniation, spinal canal stenosis, or neural impingement.",
        ],
        'mri_cervical' => [
            'id' => 'mri_cervical',
            'dept' => 'mri',
            'title' => 'Cervical Spine MRI (Normal)',
            'findings' => "ALIGNMENT: Normal physiological cervical lordosis is preserved.\nVERTEBRAL BODIES: Normal heights, alignment and bone marrow signal from C1 through C7.\nINTERVERTEBRAL DISCS: Cervical discs show preserved T2 signal and height without posterior disc displacement.\nSPINAL CORD: Cervical spinal cord is central, showing normal caliber and uniform intramedullary signal without cord edema, syrinx, or compression. Craniocervical junction is normal.",
            'impression' => "NORMAL CERVICAL SPINE MRI STUDY. Normal spinal cord and neural exits.",
        ],
        'mri_knee' => [
            'id' => 'mri_knee',
            'dept' => 'mri',
            'title' => 'Knee Joint MRI (Normal)',
            'findings' => "LIGAMENTS: Anterior Cruciate Ligament (ACL) and Posterior Cruciate Ligament (PCL) are intact with normal low-signal continuity. Medial and Lateral Collateral Ligament complexes are intact.\n\nMENISCI: Medial and Lateral menisci demonstrate normal morphology and low signal on all sequences without intra-substance tear or extrusion.\n\nEXTENSOR MECHANISM: Quadriceps and patellar tendons are intact. Patella is normally seated in trochlear groove.\n\nARTICULAR CARTILAGE: Cartilage over patellofemoral and tibiofemoral surfaces is intact with normal thickness. No subchondral marrow edema or osteochondral defect.\n\nSYNOVIUM & FLUID: No significant joint effusion, synovitis, or popliteal (Baker's) cyst.",
            'impression' => "NORMAL KNEE JOINT MRI EXAMINATION.\nIntact cruciate ligaments, normal menisci, and preserved articular cartilage.",
        ],
        'mri_mrcp' => [
            'id' => 'mri_mrcp',
            'dept' => 'mri',
            'title' => 'MRCP - Magnetic Resonance Cholangiopancreatography (Normal)',
            'findings' => "TECHNIQUE: Heavily T2-weighted 2D and 3D MRCP reconstructions of the biliary and pancreatic ductal systems.\n\nBILIARY TREE: Right and left hepatic ducts, common hepatic duct, and common bile duct (CBD) show smooth caliber (CBD measures 4.5 mm) without dilatation, stricture or filling defects (choledocholithiasis).\nGALLBLADDER: Well distended with normal signal fluid; cystic duct is patent.\nPANCREATIC DUCT: Main pancreatic duct (Wirsung) is normal in caliber (< 2 mm) with smooth course and no ectasia or intraductal calculi.\nSOLID VISCERA: Liver, pancreas and spleen appear unremarkable on scout sequences.",
            'impression' => "NORMAL MRCP EXAMINATION.\nNo biliary ductal dilatation, choledocholithiasis, or pancreatic duct pathology.",
        ],
    ];
}

/**
 * Resolve standard normal template for any study name and department.
 */
function get_default_imaging_template(string $studyName, ?string $deptId = null): array
{
    $all = get_all_imaging_templates();
    $nameUpper = strtoupper($studyName);

    // 1. Ultrasound matching
    if ($deptId === 'ultrasound' || str_contains($nameUpper, 'ULTRASOUND') || str_contains($nameUpper, 'USG')) {
        if (str_contains($nameUpper, 'ANOMALY')) return $all['us_anomaly'];
        if (str_contains($nameUpper, 'OBSTETRIC') || str_contains($nameUpper, 'PREGNANCY')) return $all['us_obstetric'];
        if (str_contains($nameUpper, 'PELVIS') && (str_contains($nameUpper, 'FEMALE') || str_contains($nameUpper, 'GYNAE'))) return $all['us_pelvis'];
        if (str_contains($nameUpper, 'KUB') || str_contains($nameUpper, 'PROSTATE')) return $all['us_kub'];
        if (str_contains($nameUpper, 'UPPER ABDOMEN') || str_contains($nameUpper, 'HEPATOBILIARY')) return $all['us_upper_abd'];
        if (str_contains($nameUpper, 'SCROT') || str_contains($nameUpper, 'TESTIS')) return $all['us_scrotal'];
        if (str_contains($nameUpper, 'THYROID') || str_contains($nameUpper, 'NECK')) return $all['us_thyroid'];
        if (str_contains($nameUpper, 'BREAST')) return $all['us_breast'];
        return $all['us_whole_abd'];
    }

    // 2. X-Ray matching
    if ($deptId === 'xray' || str_contains($nameUpper, 'X-RAY') || str_contains($nameUpper, 'XRAY')) {
        if (str_contains($nameUpper, 'CERVICAL') || str_contains($nameUpper, 'NECK')) return $all['xr_cervical'];
        if (str_contains($nameUpper, 'LUMBAR') || str_contains($nameUpper, 'L/S') || str_contains($nameUpper, 'SPINE')) return $all['xr_ls_spine'];
        if (str_contains($nameUpper, 'KNEE')) return $all['xr_knee'];
        if (str_contains($nameUpper, 'ABDOMEN')) return $all['xr_abd_plain'];
        if (str_contains($nameUpper, 'AP VIEW')) return $all['xr_chest_ap'];
        return $all['xr_chest_pa'];
    }

    // 3. ECG matching
    if ($deptId === 'ecg' || str_contains($nameUpper, 'ECG') || str_contains($nameUpper, 'EKG')) {
        if (str_contains($nameUpper, 'STRIP')) return $all['ecg_resting_strip'];
        return $all['ecg_12lead'];
    }

    // 4. CT Scan matching
    if ($deptId === 'ct' || str_contains($nameUpper, 'CT')) {
        if (str_contains($nameUpper, 'HRCT') || (str_contains($nameUpper, 'CHEST') && str_contains($nameUpper, 'CT'))) return $all['ct_hrct_chest'];
        if (str_contains($nameUpper, 'ABDOMEN') || str_contains($nameUpper, 'PELVIS')) return $all['ct_abd_pelvis'];
        if (str_contains($nameUpper, 'PNS') || str_contains($nameUpper, 'SINUS')) return $all['ct_pns'];
        return $all['ct_brain_plain'];
    }

    // 5. MRI matching
    if ($deptId === 'mri' || str_contains($nameUpper, 'MRI')) {
        if (str_contains($nameUpper, 'LUMBAR') || str_contains($nameUpper, 'SPINE')) return $all['mri_lumbar'];
        if (str_contains($nameUpper, 'CERVICAL')) return $all['mri_cervical'];
        if (str_contains($nameUpper, 'KNEE')) return $all['mri_knee'];
        if (str_contains($nameUpper, 'MRCP') || str_contains($nameUpper, 'BILIARY')) return $all['mri_mrcp'];
        return $all['mri_brain_plain'];
    }

    // Default fallback
    return [
        'id' => 'generic_normal',
        'dept' => $deptId ?? 'radiology',
        'title' => 'Standard Normal Examination',
        'findings' => "Visualized anatomical structures demonstrate normal caliber, signal/density characteristics, and morphology without evidence of focal acute lesion, mass effect, or structural disruption.",
        'impression' => "No significant abnormality detected on this examination.",
    ];
}


