<?php

declare(strict_types=1);

/**
 * Shared helpers for printable receipts & lab reports (header/footer branding).
 * Report layout: brand → compact patient grid → department title → results.
 */

/**
 * Compact stacked label/value cell for the report patient header.
 * When $optional is true, empty / dash values are omitted to save space.
 * Pass raw (unescaped) values — this helper escapes for output.
 */
function report_meta_cell(string $label, string $value, bool $strong = false, bool $optional = false): string
{
    $trimmed = trim($value);
    if ($optional && ($trimmed === '' || $trimmed === '—')) {
        return '';
    }
    $display = $trimmed !== '' ? e($trimmed) : '—';
    $valClass = 'lab-report__val';
    if ($strong) {
        $valClass .= ' lab-report__val--strong';
    }
    if (strcasecmp($label, 'Patient Name') === 0) {
        $valClass .= ' lab-report__val--patient';
    }
    return '<div class="lab-report__cell">'
        . '<span class="lab-report__lbl">' . e($label) . '</span>'
        . '<span class="' . $valClass . '">' . $display . '</span>'
        . '</div>';
}

/**
 * Shared patient header grid — same markup/CSS for lab report and patient bill.
 *
 * @param 'report'|'bill' $context Reserved; header fields stay identical on both documents.
 */
function build_patient_document_meta(array $patient, ?array $entry, string $context = 'report'): string
{
    unset($context);
    $mrNo = (string)($patient['id'] ?? ($entry['patient_id'] ?? '—'));
    $pname = (string)($patient['name'] ?? ($entry['patient_name'] ?? '—'));
    $fhName = trim((string)($patient['relation_of'] ?? ''));
    $age = (string)($patient['age'] ?? '—');
    $gender = (string)($patient['gender'] ?? '—');
    $ageGender = trim(
        ($age !== '—' && $age !== '' ? $age . ' years' : '—')
        . ($gender !== '—' && $gender !== '' ? ' / ' . $gender : ''),
        ' /'
    );
    $phoneP = ($patient['phone'] ?? '') !== '' ? (string)$patient['phone'] : '—';
    $patientAddress = ($patient['address'] ?? '') !== '' ? (string)$patient['address'] : '—';
    $cnic = trim((string)($patient['cnic'] ?? ''));

    $labNo = (string)($entry['lab_no'] ?? '');
    $labNoDisplay = $labNo !== '' ? $labNo : '—';
    $registeredAt = trim((string)($entry['branch'] ?? ''));
    $receivedOn = format_datetime_report($entry['created_at'] ?? null);
    $registeredOn = format_datetime_report($entry['created_at'] ?? null);
    $reportedOn = format_datetime_report(
        $entry['reported_at'] ?? $entry['updated_at'] ?? $entry['created_at'] ?? null
    );

    $doctorRaw = trim((string)($entry['doctor'] ?? ''));
    if ($doctorRaw === '') {
        $doctorRaw = trim((string)($patient['referring_doctor'] ?? ''));
    }
    if ($doctorRaw === '') {
        $doctorRaw = 'Walk-in / Self';
    }

    // Identical field set on test report + billing report (matches sample patient header)
    $metaCells = report_meta_cell('Patient Name', $pname, true)
        . report_meta_cell('MR No', $mrNo, true)
        . report_meta_cell('Father / Husband Name', $fhName !== '' ? $fhName : '—')
        . report_meta_cell('Phone', $phoneP)
        . report_meta_cell('Doctor Name', $doctorRaw, true)
        . report_meta_cell('Age / Gender', $ageGender)
        . report_meta_cell('Address', $patientAddress)
        . report_meta_cell('Case No', $labNoDisplay)
        . report_meta_cell('Registered at', $registeredAt !== '' ? $registeredAt : '—')
        . report_meta_cell('Received on', $receivedOn)
        . report_meta_cell('Registered On', $registeredOn)
        . report_meta_cell('Reported On', $reportedOn)
        . report_meta_cell('Specimen Taken', 'Taken In Lab');

    if ($cnic !== '') {
        $metaCells .= report_meta_cell('CNIC', $cnic, false, true);
    }
    if (!empty($patient['blood_group'])) {
        $metaCells .= report_meta_cell('Blood group', (string)$patient['blood_group'], false, true);
    }
    if (!empty($patient['email'])) {
        $metaCells .= report_meta_cell('Email', (string)$patient['email'], false, true);
    }

    $route = trim((string)($entry['route'] ?? ''));
    $priority = trim((string)($entry['priority'] ?? ''));
    if ($route !== '' && strcasecmp($route, '—') !== 0) {
        $metaCells .= report_meta_cell('Route', $route, false, true);
    }
    if ($priority !== '' && strcasecmp($priority, 'Normal') !== 0) {
        $metaCells .= report_meta_cell('Priority', $priority, false, true);
    }

    return '<div class="lab-report__meta">' . $metaCells . '</div>';
}

function branding_settings(?string $orgId = null): array
{
    $orgId = $orgId ?? (current_user()['organization_id'] ?? 'ORG-001');
    $set = setting_repo()->getSettings($orgId);
    $hasImage = !empty($set['has_header_image']) || !empty($set['header_image_mime']);
    return [
        'organization_id' => $orgId,
        'name' => $set['lab_name'] ?? 'Health LMS Pro Diagnostics',
        'address' => $set['address'] ?? '',
        'phone' => $set['phone'] ?? '',
        'email' => $set['email'] ?? '',
        'header' => $set['header_text'] ?? '',
        'footer' => $set['footer_text'] ?? '',
        'logo_text' => $set['logo_text'] ?? 'HLP',
        'bill_header' => $set['bill_header_text'] ?? $set['header_text'] ?? '',
        'bill_footer' => $set['bill_footer_text'] ?? $set['footer_text'] ?? '',
        'has_header_image' => $hasImage,
        'header_image_url' => $hasImage
            ? '/branding-header.php?org=' . rawurlencode($orgId) . '&v=' . (int)($set['header_image_ver'] ?? 1)
            : '',
    ];
}

/**
 * Process uploaded letterhead PNG/JPEG/WebP from $_FILES['header_image'].
 * @return array{ok: bool, error?: string, saved?: bool, cleared?: bool}
 */
function process_header_image_upload(string $orgId): array
{
    if (!empty($_POST['remove_header_image'])) {
        setting_repo()->clearHeaderImage($orgId);
        return ['ok' => true, 'cleared' => true];
    }

    if (empty($_FILES['header_image']) || !is_array($_FILES['header_image'])) {
        return ['ok' => true, 'saved' => false];
    }

    $file = $_FILES['header_image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'saved' => false];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Header image upload failed. Try again.'];
    }

    $maxBytes = 2 * 1024 * 1024; // 2 MB
    if (($file['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'error' => 'Header image must be 2 MB or smaller.'];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }

    $info = @getimagesize($tmp);
    if ($info === false) {
        return ['ok' => false, 'error' => 'File is not a valid image.'];
    }

    $mime = $info['mime'] ?? '';
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Use PNG, JPG, or WebP for the header image.'];
    }

    $binary = file_get_contents($tmp);
    if ($binary === false || $binary === '') {
        return ['ok' => false, 'error' => 'Could not read uploaded image.'];
    }

    // Ensure a settings row exists before storing the blob
    $existing = setting_repo()->getSettings($orgId);
    if ($existing === []) {
        setting_repo()->save(['name' => 'Lab'], $orgId);
    }

    if (!setting_repo()->saveHeaderImage($orgId, $binary, $mime)) {
        return ['ok' => false, 'error' => 'Could not save header image. Re-run /database/setup.php if columns are missing.'];
    }

    return ['ok' => true, 'saved' => true];
}

/**
 * Shared save for Admin / CC / Lab branding forms (text + optional PNG header).
 * @return array{ok: bool, message: string}
 */
function save_branding_request(string $orgId): array
{
    $ok = setting_repo()->save([
        'name' => $_POST['name'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'email' => $_POST['email'] ?? '',
        'address' => $_POST['address'] ?? '',
        'header' => $_POST['header'] ?? '',
        'footer' => $_POST['footer'] ?? '',
        'logo_text' => $_POST['logo'] ?? 'HLP',
        'bill_header' => $_POST['bill_header'] ?? '',
        'bill_footer' => $_POST['bill_footer'] ?? '',
    ], $orgId);

    if (!$ok) {
        return ['ok' => false, 'message' => 'Could not save branding. If columns are missing, re-run /database/setup.php once.'];
    }

    $img = process_header_image_upload($orgId);
    if (!$img['ok']) {
        return ['ok' => false, 'message' => $img['error'] ?? 'Header image could not be saved.'];
    }

    if (!empty($img['cleared'])) {
        return ['ok' => true, 'message' => 'Branding saved — header image removed.'];
    }
    if (!empty($img['saved'])) {
        return ['ok' => true, 'message' => 'Branding saved — header image updated.'];
    }
    return ['ok' => true, 'message' => 'Branding saved — used on bills and lab reports.'];
}

function branding_header_image_field(array $settings): string
{
    $preview = '';
    if (!empty($settings['has_header_image']) && !empty($settings['header_image_url'])) {
        $url = e($settings['header_image_url']);
        $preview = <<<HTML
        <div class="branding-header-preview">
            <p class="text-xs font-medium text-slate-600 mb-2">Current header image</p>
            <img src="{$url}" alt="Lab header" class="branding-header-preview__img">
            <label class="mt-2 flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="remove_header_image" value="1" class="rounded border-slate-300">
                Remove header image (use text letterhead only)
            </label>
        </div>
        HTML;
    }

    return <<<HTML
    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 space-y-3">
        <div>
            <p class="text-sm font-semibold text-slate-800">Header image (PNG / JPG)</p>
            <p class="text-xs text-slate-500 mt-1">If the lab sends a letterhead as an image, upload it here. It is fitted across the top of bills and reports. Recommended: wide PNG, under 2&nbsp;MB.</p>
        </div>
        {$preview}
        <div>
            <label class="field-label" for="header_image">Upload header image <span class="text-slate-400 font-normal">(optional)</span></label>
            <input type="file" id="header_image" name="header_image" accept="image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-teal-700 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-teal-800">
        </div>
    </div>
    HTML;
}

/**
 * @return array{settings: array, patient: array, entry: ?array, lines: array, report_title: string}
 */
function load_document_context(?string $labNo): array
{
    $settings = branding_settings();
    $entry = ($labNo !== null && $labNo !== '') ? lab_repo()->findByLabNo($labNo) : null;

    if (!$entry) {
        $all = lab_repo()->getAll();
        $entry = $all[0] ?? null;
    }

    $patient = [
        'id' => '',
        'name' => '—',
        'title' => '',
        'age' => '',
        'gender' => '',
        'phone' => '',
        'cnic' => '',
        'blood_group' => '',
        'email' => '',
        'address' => '',
        'relation_of' => '',
        'referring_doctor' => '',
    ];

    if ($entry) {
        $patient['name'] = $entry['patient_name'] ?? '—';
        $patient['id'] = $entry['patient_id'] ?? '';
        $p = patient_repo()->findById((string)($entry['patient_id'] ?? ''));
        if ($p) {
            $patient = [
                'id' => $p['patient_no'] ?? $p['id'] ?? '',
                'name' => trim(($p['title'] ?? '') . ' ' . ($p['full_name'] ?? '')),
                'title' => $p['title'] ?? '',
                'age' => (string)($p['age'] ?? ''),
                'gender' => $p['gender'] ?? '',
                'phone' => $p['phone'] ?? '',
                'cnic' => $p['cnic'] ?? '',
                'blood_group' => $p['blood_group'] ?? '',
                'email' => $p['email'] ?? '',
                'address' => $p['address'] ?? '',
                'relation_of' => $p['relation_of'] ?? '',
                'emergency_name' => $p['emergency_name'] ?? '',
                'referring_doctor' => $p['referring_doctor'] ?? $p['emergency_name'] ?? '',
            ];
        }
    }

    $lines = [];
    $deptHint = '';
    if ($entry) {
        $labNo = (string)$entry['lab_no'];
        $results = result_repo()->getResultsByLabNo($labNo);
        if (empty($results)) {
            $results = result_repo()->ensureResultsInitialized(
                $labNo,
                (string)($entry['tests'] ?? ''),
                (string)($patient['name'] ?? ''),
                $settings['organization_id'] ?? 'ORG-001'
            );
        }

        foreach ($results as $r) {
            if (($r['value'] ?? '') === '' && ($r['parameter'] ?? '') === '') {
                continue;
            }
            // Skip parameters the lab unchecked (Show = off)
            if (isset($r['is_visible']) && (int)$r['is_visible'] === 0) {
                continue;
            }
            $lines[] = [
                'test_title' => $r['test'] ?? '',
                'section' => $r['section'] ?? '',
                'test' => $r['parameter'] ?: ($r['test'] ?? 'Result'),
                'result' => ($r['value'] !== null && $r['value'] !== '') ? $r['value'] : 'Pending',
                'unit' => $r['unit'] ?? '—',
                'range' => $r['reference_range'] ?: ($r['normal_value'] ?? '—'),
                'sub_table' => $r['sub_table'] ?? '',
                'flag' => $r['flag'] ?? '',
            ];
        }

        if ($lines === []) {
            $tests = array_filter(array_map('trim', explode(',', (string)($entry['tests'] ?? ''))));
            foreach ($tests as $tName) {
                $meta = null;
                foreach (mock('mock_tests') as $t) {
                    if (strcasecmp($t['name'], $tName) === 0 || strcasecmp($t['code'], $tName) === 0) {
                        $meta = $t;
                        break;
                    }
                }
                if ($meta && $deptHint === '' && isset(TEST_DEPARTMENTS[$meta['category'] ?? ''])) {
                    $deptHint = $meta['category'];
                }
                $lines[] = [
                    'test_title' => $tName,
                    'section' => '',
                    'test' => $tName,
                    'result' => 'Pending',
                    'unit' => $meta['unit'] ?? '—',
                    'range' => $meta['range'] ?? '—',
                    'sub_table' => '',
                    'flag' => '',
                ];
            }
        }
    }

    // Determine department title + specimen
    $specimen = '';
    if ($deptHint === '' && $entry) {
        $tests = array_filter(array_map('trim', explode(',', (string)($entry['tests'] ?? ''))));
        foreach ($tests as $tName) {
            foreach (mock('mock_tests') as $t) {
                if (strcasecmp($t['name'], $tName) === 0 || strcasecmp($t['code'], $tName) === 0) {
                    $deptHint = $t['category'] ?? '';
                    $specimen = (string)($t['sample'] ?? '');
                    break 2;
                }
            }
        }
    }
    if ($specimen === '' && $entry) {
        $tests = array_filter(array_map('trim', explode(',', (string)($entry['tests'] ?? ''))));
        foreach ($tests as $tName) {
            foreach (mock('mock_tests') as $t) {
                if (strcasecmp($t['name'], $tName) === 0 || strcasecmp($t['code'], $tName) === 0) {
                    $specimen = (string)($t['sample'] ?? '');
                    break 2;
                }
            }
        }
    }

    $reportTitle = department_report_heading($deptHint);

    return [
        'settings' => $settings,
        'patient' => $patient,
        'entry' => $entry,
        'lines' => $lines,
        'report_title' => $reportTitle,
        'specimen' => $specimen !== '' && $specimen !== '—' ? $specimen : 'Serum',
        'signatories' => setting_repo()->getSignatories($settings['organization_id'] ?? 'ORG-001'),
    ];
}

/**
 * Sample-style department heading for the report title bar.
 */
function department_report_heading(string $category): string
{
    $map = [
        'Biochemistry' => 'DEPARTMENT OF CHEMICAL PATHOLOGY',
        'Chemistry' => 'DEPARTMENT OF CHEMICAL PATHOLOGY',
        'Chemical Pathology' => 'DEPARTMENT OF CHEMICAL PATHOLOGY',
        'Special Chemistry' => 'DEPARTMENT OF SPECIAL CHEMISTRY',
        'Hematology' => 'DEPARTMENT OF HAEMATOLOGY',
        'Microbiology' => 'DEPARTMENT OF MICROBIOLOGY',
        'Histopathology' => 'DEPARTMENT OF HISTOPATHOLOGY',
        'Pathology' => 'DEPARTMENT OF PATHOLOGY',
        'Serology' => 'DEPARTMENT OF SEROLOGY',
        'Immunology' => 'DEPARTMENT OF IMMUNOLOGY',
        'Molecular' => 'DEPARTMENT OF MOLECULAR PATHOLOGY',
        'Endocrinology' => 'DEPARTMENT OF ENDOCRINOLOGY',
        'Clinical Pathology' => 'DEPARTMENT OF CLINICAL PATHOLOGY',
        'Report Profile' => 'DEPARTMENT OF LABORATORY MEDICINE',
        'Radiology' => 'DEPARTMENT OF RADIOLOGY',
    ];
    if ($category !== '' && isset($map[$category])) {
        return $map[$category];
    }
    if ($category !== '') {
        return 'DEPARTMENT OF ' . strtoupper($category);
    }
    return 'DEPARTMENT OF LABORATORY MEDICINE';
}

function format_datetime_report(?string $iso): string
{
    if (!$iso) {
        return date('d-M-Y g:i A');
    }
    $ts = strtotime($iso);
    return $ts ? date('d-M-Y g:i A', $ts) : $iso;
}

function format_date_report(?string $iso = null): string
{
    if (!$iso) {
        return date('d-M-Y');
    }
    $ts = strtotime($iso);
    return $ts ? date('d-M-Y', $ts) : date('d-M-Y');
}

function render_letterhead_image(array $settings): string
{
    if (empty($settings['has_header_image']) || empty($settings['header_image_url'])) {
        return '';
    }
    $url = e($settings['header_image_url']);
    $alt = e(($settings['name'] ?? 'Lab') . ' header');
    return '<div class="lab-letterhead-image"><img src="' . $url . '" alt="' . $alt . '" class="lab-letterhead-image__img"></div>';
}

function render_branded_header(array $settings, bool $forBill = false): string
{
    $imageHtml = render_letterhead_image($settings);
    $name = e($settings['name'] ?? '');
    $header = e($forBill ? ($settings['bill_header'] ?: ($settings['header'] ?? '')) : ($settings['header'] ?? ''));
    $address = e($settings['address'] ?? '');
    $phone = e($settings['phone'] ?? '');

    $tag = $header !== '' ? $header : 'Quality diagnostics you can trust';
    $contact = $address;
    if ($phone !== '') {
        $contact .= ($contact !== '' ? ' · ' : '') . $phone;
    }

    // PNG letterhead on top; keep contact line under it. Text logo only when no image.
    if ($imageHtml !== '') {
        return <<<HTML
    <div class="lab-doc-brand lab-doc-brand--image">
        {$imageHtml}
        <div class="lab-doc-brand__text lab-doc-brand__text--under-image">
            <p class="lab-doc-brand__contact">{$contact}</p>
        </div>
    </div>
    HTML;
    }

    $logoImg = brand_logo('brand-logo brand-logo--report');
    return <<<HTML
    <div class="lab-doc-brand">
        <div class="lab-doc-brand__logo">{$logoImg}</div>
        <div class="lab-doc-brand__text">
            <p class="lab-doc-brand__name">{$name}</p>
            <p class="lab-doc-brand__tag">{$tag}</p>
            <p class="lab-doc-brand__contact">{$contact}</p>
        </div>
    </div>
    HTML;
}

function render_branded_footer(array $settings, bool $forBill = false): string
{
    $footer = e($forBill ? ($settings['bill_footer'] ?: ($settings['footer'] ?? '')) : ($settings['footer'] ?? ''));
    $labLine = $footer !== ''
        ? '<p class="lab-report__lab-note">' . $footer . '</p>'
        : '';
    return $labLine . software_credit_footer(true);
}

function render_report_signatories(array $signatories): string
{
    if (empty($signatories)) {
        return '';
    }
    $html = '<div class="lab-report__signatories">';
    foreach ($signatories as $sig) {
        $name = e(trim((string)($sig['name'] ?? '')));
        $qualsRaw = trim((string)($sig['qualifications'] ?? ''));
        $desigRaw = trim((string)($sig['designation'] ?? ''));

        // Max 2 detail lines under the name (box stays ~3 lines tall)
        $details = [];
        foreach (preg_split('/\R/', $qualsRaw) ?: [] as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }
            $details[] = $line;
            if (count($details) >= 2) {
                break;
            }
        }
        if ($desigRaw !== '' && count($details) < 2) {
            $flat = strtolower(implode(' ', $details));
            if ($flat === '' || !str_contains($flat, strtolower($desigRaw))) {
                $details[] = $desigRaw;
            }
        }
        // Prefer designation as 2nd line when quals were long/noisy
        if ($desigRaw !== '' && count($details) >= 2) {
            $last = $details[count($details) - 1];
            if (strcasecmp($last, $desigRaw) !== 0 && str_contains(strtolower($desigRaw), strtolower($last))) {
                $details[count($details) - 1] = $desigRaw;
            }
        }
        $details = array_slice($details, 0, 2);

        $html .= '<div class="lab-report__signatory">';
        $html .= '<div class="lab-report__signatory-name">' . $name . '</div>';
        foreach ($details as $d) {
            $html .= '<div class="lab-report__signatory-line">' . e($d) . '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Compact clinical lab report (Infinity-style header / clean B&W table).
 */
function render_report_document(
    array $settings,
    array $patient,
    array $resultLines,
    ?array $entry = null,
    string $reportTitle = 'DEPARTMENT OF LABORATORY MEDICINE',
    array $signatories = [],
    string $specimen = 'Serum'
): string {
    $letterhead = render_letterhead_image($settings);
    $logoImg = brand_logo('brand-logo brand-logo--report');
    $labName = e($settings['name'] ?? '');
    $tagline = e(($settings['header'] ?? '') !== '' ? $settings['header'] : 'Quality is our Promise');
    $labAddress = e($settings['address'] ?? '');
    $labPhone = e($settings['phone'] ?? '');
    $labEmail = e($settings['email'] ?? '');

    $labNoRaw = (string)($entry['lab_no'] ?? '');
    $qrPayload = $labNoRaw !== ''
        ? (isset($_SERVER['HTTP_HOST'])
            ? (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
                . '://' . $_SERVER['HTTP_HOST']
                . '/portals/main-lab/reports/preview.php?lab_no=' . rawurlencode($labNoRaw))
            : $labNoRaw)
        : ($settings['name'] ?? 'Lab Report');
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=96x96&margin=0&data=' . rawurlencode($qrPayload);
    $qrHtml = '<div class="lab-report__qr"><img src="' . e($qrUrl) . '" alt="Report QR" width="72" height="72"></div>';

    $brandInner = $letterhead !== ''
        ? $letterhead
        : <<<HTML
            <div class="lab-report__brand">
                <div class="lab-report__logo">{$logoImg}</div>
                <div>
                    <p class="lab-report__lab-name">{$labName}</p>
                    <p class="lab-report__tagline">{$tagline}</p>
                </div>
            </div>
            HTML;

    $brandBlock = '<div class="lab-report__top">' . $brandInner . $qrHtml . '</div>';

    $metaBlock = build_patient_document_meta($patient, $entry, 'report');

    $title = e(strtoupper($reportTitle));
    $specimenE = e($specimen !== '' ? $specimen : 'Serum');
    $resultDate = e(format_date_report(null));

    $rows = '';
    $currentTestTitle = null;
    $currentSection = null;

    // Count rows per test so single-parameter tests (FBS, HB) don't print the name twice
    $testRowCounts = [];
    foreach ($resultLines as $line) {
        $tt = trim((string)($line['test_title'] ?? ''));
        if ($tt === '') {
            continue;
        }
        $testRowCounts[$tt] = ($testRowCounts[$tt] ?? 0) + 1;
    }

    foreach ($resultLines as $line) {
        $testTitle = trim((string)($line['test_title'] ?? ''));
        $section = trim((string)($line['section'] ?? ''));
        $paramRaw = trim((string)($line['test'] ?? ''));

        if ($testTitle !== '' && $testTitle !== $currentTestTitle) {
            $currentTestTitle = $testTitle;
            $currentSection = null;
            // Only show a panel heading for multi-parameter tests (e.g. CBC), or when
            // the parameter label differs from the test name.
            $needsHeading = ($testRowCounts[$testTitle] ?? 0) > 1
                || strcasecmp($testTitle, $paramRaw) !== 0;
            if ($needsHeading) {
                $rows .= '<tr class="lab-report__test-head"><td colspan="4"><strong>' . e($testTitle) . '</strong></td></tr>';
            }
        }

        if ($section !== '' && $section !== $currentSection) {
            $currentSection = $section;
            $rows .= '<tr class="lab-report__section-head"><td colspan="4"><span>' . e($section) . '</span></td></tr>';
        }

        $flagRaw = strtolower((string)($line['flag'] ?? ''));
        $resClass = 'lab-report__result';
        $arrow = '';
        if ($flagRaw === 'l') {
            $resClass .= ' lab-report__result--low';
            $arrow = ' <span class="lab-report__arrow">&darr;</span>';
        } elseif ($flagRaw === 'h') {
            $resClass .= ' lab-report__result--high';
            $arrow = ' <span class="lab-report__arrow">&uarr;</span>';
        } elseif ($flagRaw === 'critical') {
            $resClass .= ' lab-report__result--critical';
            $arrow = ' <span class="lab-report__flag">!</span>';
        }

        $resVal = e($line['result'] ?? 'Pending');
        $unitVal = e($line['unit'] ?? '—');
        $rangeVal = e($line['range'] ?? '—');
        $paramName = e($paramRaw);

        $rows .= '<tr>
            <td class="lab-report__param-cell">' . $paramName . '</td>
            <td class="lab-report__range-cell">' . $rangeVal . '</td>
            <td class="lab-report__unit-cell">' . $unitVal . '</td>
            <td class="' . $resClass . '">' . $resVal . $arrow . '</td>
        </tr>';

        if (!empty($line['sub_table'])) {
            $subLines = explode("\n", trim((string)$line['sub_table']));
            $subRowsHtml = '';
            foreach ($subLines as $sl) {
                $parts = explode(':', $sl, 2);
                if (count($parts) === 2) {
                    $subRowsHtml .= '<tr><td>' . e(trim($parts[0])) . '</td><td>' . e(trim($parts[1])) . '</td></tr>';
                }
            }
            if ($subRowsHtml !== '') {
                $rows .= '<tr class="lab-report__subtable-row">
                    <td colspan="4">
                        <div class="lab-report__subtable-wrap">
                            <span class="lab-report__subtable-title">Reference Table</span>
                            <table class="lab-report__subtable">
                                <thead><tr><th>AGE</th><th>VALUE</th></tr></thead>
                                <tbody>' . $subRowsHtml . '</tbody>
                            </table>
                        </div>
                    </td>
                </tr>';
            }
        }
    }

    if ($rows === '') {
        $rows = '<tr><td colspan="4" class="lab-report__empty">No tests on this entry yet.</td></tr>';
    }

    $footerNote = e($settings['footer'] ?? '');
    $footerBlock = $footerNote !== '' ? '<p class="lab-report__lab-note">' . $footerNote . '</p>' : '';
    $credit = software_credit_footer(true);
    $printStamp = e(date('d-M-Y h:i:s A'));
    $addrLine = trim($labAddress . ($labPhone !== '' ? '  ·  ' . $labPhone : '') . ($labEmail !== '' ? '  ·  ' . $labEmail : ''));

    $signatoriesHtml = render_report_signatories($signatories);

    return <<<HTML
    <div class="print-area lab-report">
        <header class="lab-report__header">
            {$brandBlock}

            {$metaBlock}

            <div class="lab-report__title-wrap">
                <h2 class="lab-report__title">{$title}</h2>
                <span class="lab-report__specimen"><strong>Specimen:</strong> {$specimenE}</span>
            </div>
        </header>

        <section class="lab-report__body">
            <table class="lab-report__table">
                <thead>
                    <tr>
                        <th class="lab-report__th-test">TEST</th>
                        <th class="lab-report__th-range">REFERENCE RANGE</th>
                        <th class="lab-report__th-unit">UNIT</th>
                        <th class="lab-report__th-result">RESULT<br><span class="lab-report__result-date">{$resultDate}</span></th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
            <div class="lab-report__clinical-note">Note: Lab values should always be correlated with clinical picture. Normal Range(s) and Unit(s) shown are most recent results</div>
        </section>

        <aside class="lab-report__side-note" aria-hidden="true">
            All Results Should Be Interpreted And Correlated By Physician. Electronically Verified Report. No Signature(s) required. Not Valid for Legal Proceeding.
        </aside>

        <footer class="lab-report__footer">
            <p class="lab-report__disclaimer">All results should be interpreted and correlated by a physician. Electronically verified report — not valid for legal proceedings unless stamped.</p>
            {$footerBlock}
            {$signatoriesHtml}
            <div class="lab-report__contact-bar">{$addrLine}</div>
            <div class="lab-report__bottom-line">
                <span>Page 1 of 1</span>
                <span>{$printStamp}</span>
            </div>
            {$credit}
        </footer>
    </div>
    HTML;
}


function receipt_line_items(array $entry, string $orgId = 'ORG-001'): array
{
    $raw = array_filter(array_map('trim', explode(',', (string)($entry['tests'] ?? ''))));
    $items = [];
    $subtotal = 0.0;

    foreach ($raw as $name) {
        $label = normalize_test_label($name, $orgId);
        $price = 0.0;

        $test = test_repo()->findByCode($label, $orgId) ?: test_repo()->findByCode($name, $orgId);
        if ($test) {
            $price = (float)($test['price'] ?? 0);
            $label = (string)($test['name'] ?? $label);
        } else {
            foreach (test_repo()->getPackages($orgId) as $pkg) {
                if (strcasecmp((string)($pkg['name'] ?? ''), $name) === 0
                    || strcasecmp((string)($pkg['name'] ?? ''), $label) === 0
                    || strcasecmp((string)($pkg['code'] ?? ''), $name) === 0) {
                    $price = (float)($pkg['price'] ?? 0);
                    $label = (string)($pkg['name'] ?? $label);
                    break;
                }
            }
        }

        $subtotal += $price;
        $items[] = [
            'name' => $label,
            'price' => $price,
            'vial' => '—',
            'expected' => '—',
        ];
    }

    return ['items' => $items, 'subtotal' => $subtotal];
}

function render_receipt_document(array $settings, array $entry, array $patient): string
{
    $headerHtml = render_branded_header($settings, true);
    $footerHtml = render_branded_footer($settings, true);
    $orgId = (string)($settings['organization_id'] ?? current_user()['organization_id'] ?? 'ORG-001');

    $lines = receipt_line_items($entry, $orgId);
    $catalogSub = (float)$lines['subtotal'];
    $discount = (float)($entry['discount'] ?? 0);
    $storedAmount = (float)($entry['amount'] ?? 0);
    // Prefer catalog line sum; fall back to stored amount if catalog prices missing
    $subtotal = $catalogSub > 0 ? $catalogSub : ($storedAmount + $discount);
    $afterDiscount = max(0, $subtotal - $discount);
    // If stored net differs and catalog was empty, trust entry
    if ($catalogSub <= 0 && $storedAmount > 0) {
        $afterDiscount = $storedAmount;
        $subtotal = $storedAmount + $discount;
    }
    $paid = (float)($entry['paid'] ?? 0);
    $due = max(0, $afterDiscount - $paid);
    $isPaid = $due <= 0.009 && $paid > 0;

    $metaBlock = build_patient_document_meta($patient, $entry, 'bill');

    $rowsHtml = '';
    foreach ($lines['items'] as $i => $item) {
        $rowsHtml .= '<tr>'
            . '<td class="lab-bill__c-vial">' . e((string)$item['vial']) . '</td>'
            . '<td class="lab-bill__c-test">' . e((string)$item['name']) . '</td>'
            . '<td class="lab-bill__c-exp">' . e((string)$item['expected']) . '</td>'
            . '<td class="lab-bill__c-price">' . number_format((float)$item['price'], 0) . '</td>'
            . '</tr>';
    }
    if ($rowsHtml === '') {
        $rowsHtml = '<tr><td colspan="4" class="lab-bill__empty">No tests on this visit.</td></tr>';
    }

    $subFmt = 'Rs ' . number_format($subtotal, 0);
    $discFmt = 'Rs ' . number_format($discount, 0);
    $afterFmt = 'Rs ' . number_format($afterDiscount, 0);
    $paidFmt = 'Rs ' . number_format($paid, 0);
    $dueFmt = 'Rs ' . number_format($due, 0);
    $stamp = $isPaid ? '<div class="lab-bill__stamp" aria-hidden="true">PAID</div>' : '';

    return <<<HTML
    <div class="print-area lab-bill">
        {$headerHtml}
        {$metaBlock}

        <table class="lab-bill__table">
            <thead>
                <tr>
                    <th class="lab-bill__c-vial">Vial No.</th>
                    <th class="lab-bill__c-test">Test Name</th>
                    <th class="lab-bill__c-exp">Expected Report</th>
                    <th class="lab-bill__c-price">Price (Rs)</th>
                </tr>
            </thead>
            <tbody>{$rowsHtml}</tbody>
        </table>

        <div class="lab-bill__footer-row">
            <div class="lab-bill__totals-wrap">
                {$stamp}
                <table class="lab-bill__totals">
                    <tr><td>Subtotal</td><td>{$subFmt}</td></tr>
                    <tr><td>Total Discount</td><td>{$discFmt}</td></tr>
                    <tr><td>After Discount</td><td>{$afterFmt}</td></tr>
                    <tr><td>Paid</td><td>{$paidFmt}</td></tr>
                    <tr class="lab-bill__due"><td>Due</td><td>{$dueFmt}</td></tr>
                </table>
            </div>
        </div>

        <div class="lab-bill__brand-footer">{$footerHtml}</div>
    </div>
    HTML;
}
