<?php

declare(strict_types=1);

/**
 * Shared helpers for printable receipts & lab reports (header/footer branding).
 * Report layout follows clinical Infinity-style: brand → IDs → patient/doctor grid → title → results.
 */

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
            ];
        }
    }

    $lines = [];
    $deptHint = '';
    if ($entry) {
        $results = result_repo()->getResultsByLabNo((string)$entry['lab_no']);
        foreach ($results as $r) {
            if (($r['value'] ?? '') === '' && ($r['parameter'] ?? '') === '') {
                continue;
            }
            $lines[] = [
                'test' => $r['parameter'] ?: ($r['test'] ?? 'Result'),
                'result' => $r['value'] ?? 'Pending',
                'unit' => $r['unit'] ?? '—',
                'range' => '—',
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
                    'test' => $tName,
                    'result' => 'Pending',
                    'unit' => $meta['unit'] ?? '—',
                    'range' => $meta['range'] ?? '—',
                    'flag' => '',
                ];
            }
        }
    }

    $reportTitle = $deptHint !== ''
        ? strtoupper($deptHint) . ' REPORT'
        : 'LABORATORY REPORT';

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
        return date('d/m/Y h:i A');
    }
    $ts = strtotime($iso);
    return $ts ? date('d/m/Y h:i A', $ts) : $iso;
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
    $address = e($settings['address'] ?? '');
    $phone = e($settings['phone'] ?? '');
    $email = e($settings['email'] ?? '');

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

    $mrNo = e($patient['id'] ?? '—');
    $pname = e($patient['name'] ?? '—');
    $fhName = e(($patient['relation_of'] ?? '') !== '' ? $patient['relation_of'] : '—');
    $age = e((string)($patient['age'] ?? '—'));
    $gender = e($patient['gender'] ?? '—');
    $ageGender = trim($age . ($age !== '—' && $gender !== '—' ? ' years / ' : ' / ') . $gender, ' /');
    $phoneP = e(($patient['phone'] ?? '') !== '' ? $patient['phone'] : '—');

    $labNo = (string)($entry['lab_no'] ?? '—');
    $labNoE = e($labNo);
    $barcodeSafe = preg_replace('/[^A-Za-z0-9\-]/', '', $labNo) ?: 'LAB';
    $registeredAt = e($entry['branch'] ?? $settings['name'] ?? 'Main Lab');
    $registeredOn = e(format_datetime_report($entry['created_at'] ?? null));
    $receivedOn = e(format_datetime_report($entry['created_at'] ?? null));
    $reportedOn = e(date('d/m/Y h:i A'));
    $reference = e(($entry['doctor'] ?? '') !== '' ? $entry['doctor'] : 'Walk-in / Self');
    $route = e($entry['route'] ?? '—');
    $priority = e($entry['priority'] ?? 'Normal');

    $cnicRow = '';
    if (!empty($patient['cnic'])) {
        $cnicRow = '<div class="lab-report__row"><span class="lab-report__lbl">CNIC</span><span class="lab-report__val">' . e($patient['cnic']) . '</span></div>';
    }
    $bloodRow = '';
    if (!empty($patient['blood_group'])) {
        $bloodRow = '<div class="lab-report__row"><span class="lab-report__lbl">Blood group</span><span class="lab-report__val">' . e($patient['blood_group']) . '</span></div>';
    }
    $emailRow = '';
    if (!empty($patient['email'])) {
        $emailRow = '<div class="lab-report__row"><span class="lab-report__lbl">Email</span><span class="lab-report__val">' . e($patient['email']) . '</span></div>';
    }

    $title = e(strtoupper($reportTitle));

    $rows = '';
    foreach ($resultLines as $line) {
        $flagRaw = strtolower((string)($line['flag'] ?? ''));
        $flag = in_array($flagRaw, ['critical', 'h', 'l'], true)
            ? '<span class="lab-report__flag">' . e(strtoupper($flagRaw === 'critical' ? 'CRITICAL' : $flagRaw)) . '</span>'
            : e(($line['flag'] ?? '') !== '' ? (string)$line['flag'] : '—');
        $rows .= '<tr>
            <td>' . e($line['test'] ?? '') . '</td>
            <td class="lab-report__result">' . e($line['result'] ?? '') . '</td>
            <td>' . e($line['unit'] ?? '') . '</td>
            <td>' . e($line['range'] ?? '') . '</td>
            <td>' . $flag . '</td>
        </tr>';
    }
    if ($rows === '') {
        $rows = '<tr><td colspan="5" class="lab-report__empty">No tests on this entry yet.</td></tr>';
    }

    $footerNote = e($settings['footer'] ?? '');
    $footerBlock = $footerNote !== '' ? '<p class="lab-report__lab-note">' . $footerNote . '</p>' : '';
    $credit = software_credit_footer(true);
    $printStamp = e(date('h:i:s A'));
    $addrLine = trim($address . ($phone !== '' ? '  ·  ' . $phone : '') . ($email !== '' ? '  ·  ' . $email : ''));
    
    $signatoriesHtml = render_report_signatories($signatories);

    return <<<HTML
    <div class="print-area lab-report">
        <header class="lab-report__header">
            {$brandBlock}

            <div class="lab-report__id-bar">
                <div class="lab-report__id-block">
                    <span class="lab-report__id-label">Patient No</span>
                    <span class="lab-report__barcode" aria-hidden="true">*{$barcodeSafe}*</span>
                    <span class="lab-report__id-code">{$mrNo}</span>
                </div>
                <div class="lab-report__id-block lab-report__id-block--right">
                    <span class="lab-report__id-label">Case No</span>
                    <span class="lab-report__barcode" aria-hidden="true">*{$barcodeSafe}*</span>
                    <span class="lab-report__id-code">{$labNoE}</span>
                </div>
            </div>

            <div class="lab-report__meta">
                <div class="lab-report__meta-col">
                    <div class="lab-report__row"><span class="lab-report__lbl">MR No</span><span class="lab-report__val">{$mrNo}</span></div>
                    <div class="lab-report__row"><span class="lab-report__lbl">Name</span><span class="lab-report__val lab-report__val--strong">{$pname}</span></div>
                    <div class="lab-report__row"><span class="lab-report__lbl">F/H Name</span><span class="lab-report__val">{$fhName}</span></div>
                    <div class="lab-report__row"><span class="lab-report__lbl">Age / Gender</span><span class="lab-report__val">{$ageGender}</span></div>
                    {$cnicRow}
                    <div class="lab-report__row"><span class="lab-report__lbl">Phone</span><span class="lab-report__val">{$phoneP}</span></div>
                    {$bloodRow}
                    {$emailRow}
                </div>
                <div class="lab-report__meta-col">
                    <div class="lab-report__row"><span class="lab-report__lbl">Registered At</span><span class="lab-report__val">{$registeredAt}</span></div>
                    <div class="lab-report__row"><span class="lab-report__lbl">Registered On</span><span class="lab-report__val">{$registeredOn}</span></div>
                    <div class="lab-report__row"><span class="lab-report__lbl">Received On</span><span class="lab-report__val">{$receivedOn}</span></div>
                    <div class="lab-report__row"><span class="lab-report__lbl">Reported On</span><span class="lab-report__val">{$reportedOn}</span></div>
                    <div class="lab-report__row"><span class="lab-report__lbl">Reference</span><span class="lab-report__val">{$reference}</span></div>
                    <div class="lab-report__row"><span class="lab-report__lbl">Route</span><span class="lab-report__val">{$route}</span></div>
                    <div class="lab-report__row"><span class="lab-report__lbl">Priority</span><span class="lab-report__val">{$priority}</span></div>
                </div>
            </div>

            <div class="lab-report__title-wrap">
                <h2 class="lab-report__title">{$title}</h2>
            </div>
        </header>

        <section class="lab-report__body">
            <table class="lab-report__table">
                <thead>
                    <tr>
                        <th>Test</th>
                        <th>Result</th>
                        <th>Unit</th>
                        <th>Reference</th>
                        <th>Flag</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
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
