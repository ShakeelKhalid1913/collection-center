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
    $valClass = 'lab-report__val' . ($strong ? ' lab-report__val--strong' : '');
    return '<div class="lab-report__cell">'
        . '<span class="lab-report__lbl">' . e($label) . '</span>'
        . '<span class="' . $valClass . '">' . $display . '</span>'
        . '</div>';
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

    // Determine department title
    if ($deptHint === '' && $entry) {
        $tests = array_filter(array_map('trim', explode(',', (string)($entry['tests'] ?? ''))));
        foreach ($tests as $tName) {
            foreach (mock('mock_tests') as $t) {
                if (strcasecmp($t['name'], $tName) === 0 || strcasecmp($t['code'], $tName) === 0) {
                    $deptHint = $t['category'] ?? '';
                    break 2;
                }
            }
        }
    }

    $reportTitle = $deptHint !== ''
        ? strtoupper($deptHint) . ' REPORT:'
        : 'LABORATORY REPORT:';

    return [
        'settings' => $settings,
        'patient' => $patient,
        'entry' => $entry,
        'lines' => $lines,
        'report_title' => $reportTitle,
        'signatories' => setting_repo()->getSignatories($settings['organization_id'] ?? 'ORG-001'),
    ];
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
        $name = e($sig['name']);
        $quals = e($sig['qualifications'] ?? '');
        $desig = e($sig['designation'] ?? '');
        
        $html .= '<div class="lab-report__signatory">';
        $html .= '<div class="lab-report__signatory-name">' . $name . '</div>';
        if ($quals !== '') {
            $html .= '<div class="lab-report__signatory-quals">' . nl2br($quals) . '</div>';
        }
        if ($desig !== '') {
            $html .= '<div class="lab-report__signatory-quals">' . $desig . '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Infinity-style clinical lab report.
 */
function render_report_document(array $settings, array $patient, array $resultLines, ?array $entry = null, string $reportTitle = 'LABORATORY REPORT', array $signatories = []): string
{
    $letterhead = render_letterhead_image($settings);
    $logoImg = brand_logo('brand-logo brand-logo--report');
    $labName = e($settings['name'] ?? '');
    $tagline = e(($settings['header'] ?? '') !== '' ? $settings['header'] : 'Quality is our Promise');
    $labAddress = e($settings['address'] ?? '');
    $labPhone = e($settings['phone'] ?? '');
    $labEmail = e($settings['email'] ?? '');

    $brandBlock = $letterhead !== ''
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

    $mrNo = (string)($patient['id'] ?? '—');
    $pname = (string)($patient['name'] ?? '—');
    $fhNameRaw = trim((string)($patient['relation_of'] ?? ''));
    $age = (string)($patient['age'] ?? '—');
    $gender = (string)($patient['gender'] ?? '—');
    $ageGender = trim($age . ($age !== '—' && $gender !== '—' ? ' years / ' : ' / ') . $gender, ' /');
    $phoneP = ($patient['phone'] ?? '') !== '' ? (string)$patient['phone'] : '—';
    $patientAddress = ($patient['address'] ?? '') !== '' ? (string)$patient['address'] : '—';

    $labNo = (string)($entry['lab_no'] ?? '');
    $labNoDisplay = $labNo !== '' ? $labNo : '—';
    $registeredAt = trim((string)($entry['branch'] ?? ''));
    $receivedOn = format_datetime_report($entry['created_at'] ?? null);
    $reportedOn = format_datetime_report(null);
    $resultDate = e(format_date_report(null));

    $doctorRaw = trim((string)($entry['doctor'] ?? ''));
    if ($doctorRaw === '') {
        $doctorRaw = trim((string)($patient['referring_doctor'] ?? ''));
    }
    if ($doctorRaw === '') {
        $doctorRaw = 'Walk-in / Self';
    }

    // Mandatory cells — always printed
    $metaCells = report_meta_cell('Patient Name', $pname, true)
        . report_meta_cell('MR No', $mrNo, true)
        . report_meta_cell('Phone', $phoneP)
        . report_meta_cell('Doctor Name', $doctorRaw, true)
        . report_meta_cell('Age / Gender', $ageGender)
        . report_meta_cell('Address', $patientAddress);

    // Optional cells — only when filled (saves vertical space)
    $metaCells .= report_meta_cell('Case No', $labNoDisplay, false, true)
        . report_meta_cell('F/H Name', $fhNameRaw, false, true)
        . report_meta_cell('Registered at', $registeredAt, false, true)
        . report_meta_cell('Received on', $receivedOn, false, true)
        . report_meta_cell('Reported On', $reportedOn, false, true);

    if (!empty($patient['cnic'])) {
        $metaCells .= report_meta_cell('CNIC', (string)$patient['cnic'], false, true);
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

    $title = e(strtoupper($reportTitle));

    $rows = '';
    $currentTestTitle = null;
    $currentSection = null;

    foreach ($resultLines as $line) {
        $testTitle = trim((string)($line['test_title'] ?? ''));
        $section = trim((string)($line['section'] ?? ''));

        // Print main test heading if it's a test with sections or multi-parameters
        if ($testTitle !== '' && $testTitle !== $currentTestTitle) {
            $currentTestTitle = $testTitle;
            $currentSection = null;
            $rows .= '<tr class="lab-report__test-head"><td colspan="4"><strong>' . e($testTitle) . '</strong></td></tr>';
        }

        // Print Section Banner (e.g. ERYTHROCYTES, ABSOLUTE VALUES, etc.)
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

        $rows .= '<tr>
            <td class="lab-report__param-cell">' . e($line['test'] ?? '') . '</td>
            <td class="font-mono text-slate-700">' . $rangeVal . '</td>
            <td>' . $unitVal . '</td>
            <td class="' . $resClass . '" style="text-align: right;">' . $resVal . $arrow . '</td>
        </tr>';

        // Render sub-table if present (e.g. for Hemoglobin age-wise values)
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
    $printStamp = e(date('h:i:s A'));
    $addrLine = trim($labAddress . ($labPhone !== '' ? '  ·  ' . $labPhone : '') . ($labEmail !== '' ? '  ·  ' . $labEmail : ''));

    $signatoriesHtml = render_report_signatories($signatories);

    return <<<HTML
    <div class="print-area lab-report">
        <header class="lab-report__header">
            {$brandBlock}

            <div class="lab-report__meta">
                {$metaCells}
            </div>

            <div class="lab-report__title-wrap">
                <h2 class="lab-report__title">{$title}</h2>
            </div>
        </header>

        <section class="lab-report__body">
            <table class="lab-report__table">
                <thead>
                    <tr>
                        <th style="width: 38%;">TEST</th>
                        <th style="width: 26%;">REFERENCE RANGE</th>
                        <th style="width: 14%;">UNIT</th>
                        <th style="width: 22%; text-align: right;">RESULT [{$resultDate}]</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
            <div class="lab-report__clinical-note">Note: Lab values should always be correlated with clinical picture. Normal Range(s) and Unit(s) shown are most recent results</div>
        </section>

        <aside class="lab-report__side-note" aria-hidden="true">
            All Results Should Be Interpreted And Correlated By Physician. Electronically Verified Report. No Signature(s) required.
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

function render_receipt_document(array $settings, array $entry, array $patient): string
{
    $headerHtml = render_branded_header($settings, true);
    $footerHtml = render_branded_footer($settings, true);
    $labNo = e($entry['lab_no'] ?? '—');
    $pname = e($patient['name'] ?? ($entry['patient_name'] ?? '—'));
    $tests = e($entry['tests'] ?? '—');
    $date = e(isset($entry['created_at']) ? format_date($entry['created_at']) : date('d M Y'));
    $amount = e(format_money((float)($entry['amount'] ?? 0)));
    $paid = e(format_money((float)($entry['paid'] ?? 0)));
    $discount = e(format_money((float)($entry['discount'] ?? 0)));
    $due = e(format_money(max(0, (float)($entry['amount'] ?? 0) - (float)($entry['paid'] ?? 0))));
    $receiptNo = 'R-' . preg_replace('/\D/', '', (string)($entry['lab_no'] ?? '0'));

    return <<<HTML
    <div class="print-area lab-receipt">
        {$headerHtml}
        <p class="lab-receipt__heading">Cash Receipt</p>
        <div class="lab-receipt__body">
            <p><span>Receipt #</span><strong>{$receiptNo}</strong></p>
            <p><span>Lab No</span><strong>{$labNo}</strong></p>
            <p><span>Patient</span><strong>{$pname}</strong></p>
            <p><span>Tests</span><strong>{$tests}</strong></p>
            <p><span>Date</span><strong>{$date}</strong></p>
        </div>
        <div class="lab-receipt__totals">
            <p><span>Total</span><strong>{$amount}</strong></p>
            <p><span>Discount</span><strong>{$discount}</strong></p>
            <p><span>Paid</span><strong>{$paid}</strong></p>
            <p><span>Due</span><strong>{$due}</strong></p>
        </div>
        <div class="lab-receipt__footer">{$footerHtml}</div>
    </div>
    HTML;
}
