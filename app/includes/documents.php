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

    $patientDoctor = trim((string)($patient['referring_doctor'] ?? ''));
    if ($patientDoctor === '' || preg_match('/^walk[- ]*in|^self$/i', $patientDoctor)) {
        if (!empty($patient['emergency_name']) && !preg_match('/^walk[- ]*in|^self$/i', (string)$patient['emergency_name'])) {
            $patientDoctor = trim((string)$patient['emergency_name']);
        }
    }

    $entryDoctor = trim((string)($entry['doctor'] ?? ''));

    if ($entryDoctor !== '' && !preg_match('/^walk[- ]*in|^self$/i', $entryDoctor)) {
        $doctorRaw = $entryDoctor;
    } elseif ($patientDoctor !== '' && !preg_match('/^walk[- ]*in|^self$/i', $patientDoctor)) {
        $doctorRaw = $patientDoctor;
    } else {
        $doctorRaw = $entryDoctor !== '' ? $entryDoctor : ($patientDoctor !== '' ? $patientDoctor : 'Walk-in / Self');
    }

    // Client order: Age up (was under MR), MR where Phone was, Phone under Age.
    // Route / Priority removed from printed report.
    $metaCells = report_meta_cell('Patient Name', $pname, true)
        . report_meta_cell('Age / Gender', $ageGender)
        . report_meta_cell('Father / Husband Name', $fhName !== '' ? $fhName : '—')
        . report_meta_cell('MR No', $mrNo, true)
        . report_meta_cell('Doctor Name', $doctorRaw, true)
        . report_meta_cell('Phone', $phoneP)
        . report_meta_cell('Address', $patientAddress)
        . report_meta_cell('Case No', $labNoDisplay)
        . report_meta_cell('Registered at', $registeredAt !== '' ? $registeredAt : '—')
        . report_meta_cell('Received on', $receivedOn)
        . report_meta_cell('Registered On', $registeredOn)
        . report_meta_cell('Reported On', $reportedOn);

    if ($cnic !== '') {
        $metaCells .= report_meta_cell('CNIC', $cnic, false, true);
    }
    if (!empty($patient['blood_group'])) {
        $metaCells .= report_meta_cell('Blood group', (string)$patient['blood_group'], false, true);
    }
    if (!empty($patient['email'])) {
        $metaCells .= report_meta_cell('Email', (string)$patient['email'], false, true);
    }

    return '<div class="lab-report__meta">' . $metaCells . '</div>';
}

function supported_document_fonts(): array
{
    return [
        'times_bold_italic' => [
            'name' => 'Times New Roman (Bold Italic Clinical)',
            'family' => "'Times New Roman', Times, Georgia, serif",
            'weight' => '700',
            'style' => 'italic',
        ],
        'times' => [
            'name' => 'Times New Roman (Regular)',
            'family' => "'Times New Roman', Times, Georgia, serif",
            'weight' => '400',
            'style' => 'normal',
        ],
        'times_bold' => [
            'name' => 'Times New Roman (Bold)',
            'family' => "'Times New Roman', Times, Georgia, serif",
            'weight' => '700',
            'style' => 'normal',
        ],
        'arial' => [
            'name' => 'Arial / Helvetica (Modern Clean)',
            'family' => "Arial, Helvetica, 'Segoe UI', sans-serif",
            'weight' => '500',
            'style' => 'normal',
        ],
        'arial_bold' => [
            'name' => 'Arial (Bold)',
            'family' => "Arial, Helvetica, 'Segoe UI', sans-serif",
            'weight' => '700',
            'style' => 'normal',
        ],
        'calibri' => [
            'name' => 'Calibri (Clean Office)',
            'family' => "Calibri, Carlito, 'Segoe UI', sans-serif",
            'weight' => '600',
            'style' => 'normal',
        ],
        'inter' => [
            'name' => 'Inter / Roboto (Digital Sans-Serif)',
            'family' => "Inter, Roboto, -apple-system, sans-serif",
            'weight' => '600',
            'style' => 'normal',
        ],
        'georgia' => [
            'name' => 'Georgia (Elegant Serif)',
            'family' => "Georgia, 'Times New Roman', serif",
            'weight' => '600',
            'style' => 'normal',
        ],
        'segoe' => [
            'name' => 'Segoe UI (Standard Windows)',
            'family' => "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif",
            'weight' => '600',
            'style' => 'normal',
        ],
        'monospace' => [
            'name' => 'Consolas / Monospace (Laboratory Analyzer)',
            'family' => "Consolas, 'Liberation Mono', Courier, monospace",
            'weight' => '600',
            'style' => 'normal',
        ],
        'poppins' => [
            'name' => 'Poppins (Modern Clean Geometric)',
            'family' => "'Poppins', 'Segoe UI', -apple-system, sans-serif",
            'weight' => '600',
            'style' => 'normal',
        ],
        'open_sans' => [
            'name' => 'Open Sans (Neutral Highly Readable)',
            'family' => "'Open Sans', Arial, Helvetica, sans-serif",
            'weight' => '600',
            'style' => 'normal',
        ],
        'merriweather' => [
            'name' => 'Merriweather (Distinguished Editorial Serif)',
            'family' => "'Merriweather', Georgia, serif",
            'weight' => '700',
            'style' => 'normal',
        ],
        'courier' => [
            'name' => 'Courier Prime (Typewriter Clinical)',
            'family' => "'Courier Prime', 'Courier New', Courier, monospace",
            'weight' => '700',
            'style' => 'normal',
        ],
        'trebuchet' => [
            'name' => 'Trebuchet MS (Clear Diagnostic Sans)',
            'family' => "'Trebuchet MS', 'Lucida Sans Unicode', sans-serif",
            'weight' => '600',
            'style' => 'normal',
        ],
        'century_gothic' => [
            'name' => 'Century Gothic (Spacious Geometric)',
            'family' => "'Century Gothic', 'Apple Gothic', sans-serif",
            'weight' => '600',
            'style' => 'normal',
        ],
        'tahoma' => [
            'name' => 'Tahoma (Compact Technical)',
            'family' => "Tahoma, Verdana, sans-serif",
            'weight' => '600',
            'style' => 'normal',
        ],
    ];
}

function supported_document_font_sizes(): array
{
    return [
        'compact' => [
            'name' => 'Compact (11px / High Density)',
            'size' => '11px',
            'line_height' => '1.3',
        ],
        'normal' => [
            'name' => 'Normal (12px / Standard Default)',
            'size' => '12px',
            'line_height' => '1.38',
        ],
        'standard' => [
            'name' => 'Standard (13px / Crisp Readable)',
            'size' => '13px',
            'line_height' => '1.42',
        ],
        'medium' => [
            'name' => 'Medium (14px / Enhanced Visibility)',
            'size' => '14px',
            'line_height' => '1.48',
        ],
        'large' => [
            'name' => 'Large (15px / Large Report)',
            'size' => '15px',
            'line_height' => '1.52',
        ],
        'xlarge' => [
            'name' => 'Extra Large (16px / High Contrast)',
            'size' => '16px',
            'line_height' => '1.58',
        ],
    ];
}

function branding_settings(?string $orgId = null): array
{
    $orgId = $orgId ?? (current_user()['organization_id'] ?? 'ORG-001');
    $set = setting_repo()->getSettings($orgId);
    $hasImage = !empty($set['has_header_image']) || !empty($set['header_image_mime']);

    $footer = trim((string)($set['footer_text'] ?? ''));
    if (preg_match('/electronically verified|queries call reception/i', $footer)) {
        $footer = 'Get well soon.';
    }

    $billFooter = trim((string)($set['bill_footer_text'] ?? ''));
    if ($billFooter === '' || preg_match('/electronically verified|queries call reception/i', $billFooter)) {
        $billFooter = 'Get well soon.';
    }

    return [
        'organization_id' => $orgId,
        'name' => $set['lab_name'] ?? 'Lab Dash Pro Diagnostics',
        'address' => $set['address'] ?? '',
        'phone' => $set['phone'] ?? '',
        'email' => $set['email'] ?? '',
        'header' => $set['header_text'] ?? '',
        'footer' => $footer,
        'logo_text' => $set['logo_text'] ?? 'LDP',
        'bill_header' => $set['bill_header_text'] ?? $set['header_text'] ?? '',
        'bill_footer' => $billFooter,
        'has_header_image' => $hasImage,
        'header_image_url' => $hasImage
            ? '/branding-header.php?org=' . rawurlencode($orgId) . '&v=' . (int)($set['header_image_ver'] ?? 1)
            : '',
        'header_image_position' => \App\Repositories\SettingRepository::normalizeHeaderImagePosition(
            (string)($set['header_image_position'] ?? 'left')
        ),
        'header_layout_json' => (string)($set['header_layout_json'] ?? ''),
        'header_layout' => $set['header_layout'] ?? (!empty($set['header_layout_json']) ? json_decode((string)$set['header_layout_json'], true) : null),
        'has_footer_image' => !empty($set['has_footer_image']) || !empty($set['footer_image_mime']),
        'footer_image_url' => (!empty($set['has_footer_image']) || !empty($set['footer_image_mime']))
            ? '/branding-footer.php?org=' . rawurlencode($orgId) . '&v=' . (int)($set['footer_image_ver'] ?? 1)
            : '',
        'footer_layout_json' => (string)($set['footer_layout_json'] ?? ''),
        'footer_layout' => $set['footer_layout'] ?? (!empty($set['footer_layout_json']) ? json_decode((string)$set['footer_layout_json'], true) : null),
        'report_font' => (string)($set['report_font'] ?? 'times_bold_italic'),
        'bill_font' => (string)($set['bill_font'] ?? 'times_bold_italic'),
        'report_font_size' => (string)($set['report_font_size'] ?? 'normal'),
        'bill_font_size' => (string)($set['bill_font_size'] ?? 'normal'),
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
 * Process uploaded letterhead footer PNG/JPEG/WebP from $_FILES['footer_image'].
 * @return array{ok: bool, error?: string, saved?: bool, cleared?: bool}
 */
function process_footer_image_upload(string $orgId): array
{
    if (!empty($_POST['remove_footer_image'])) {
        setting_repo()->clearFooterImage($orgId);
        return ['ok' => true, 'cleared' => true];
    }

    if (empty($_FILES['footer_image']) || !is_array($_FILES['footer_image'])) {
        return ['ok' => true, 'saved' => false];
    }

    $file = $_FILES['footer_image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'saved' => false];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Footer image upload failed. Try again.'];
    }

    $maxBytes = 2 * 1024 * 1024; // 2 MB
    if (($file['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'error' => 'Footer image must be 2 MB or smaller.'];
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
        return ['ok' => false, 'error' => 'Use PNG, JPG, or WebP for the footer image.'];
    }

    $binary = file_get_contents($tmp);
    if ($binary === false || $binary === '') {
        return ['ok' => false, 'error' => 'Could not read uploaded footer image.'];
    }

    $existing = setting_repo()->getSettings($orgId);
    if ($existing === []) {
        setting_repo()->save(['name' => 'Lab'], $orgId);
    }

    if (!setting_repo()->saveFooterImage($orgId, $binary, $mime)) {
        return ['ok' => false, 'error' => 'Could not save footer image.'];
    }

    return ['ok' => true, 'saved' => true];
}

/**
 * Shared save for Admin / CC / Lab branding forms (text + optional PNG header & footer).
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
        'header_image_position' => $_POST['header_image_position'] ?? 'left',
        'header_layout_json' => isset($_POST['header_layout_json']) ? (string)$_POST['header_layout_json'] : null,
        'footer_layout_json' => isset($_POST['footer_layout_json']) ? (string)$_POST['footer_layout_json'] : null,
        'report_font' => $_POST['report_font'] ?? 'times_bold_italic',
        'bill_font' => $_POST['bill_font'] ?? 'times_bold_italic',
        'report_font_size' => $_POST['report_font_size'] ?? 'normal',
        'bill_font_size' => $_POST['bill_font_size'] ?? 'normal',
    ], $orgId);

    if (!$ok) {
        return ['ok' => false, 'message' => 'Could not save branding. If columns are missing, re-run /database/setup.php once.'];
    }

    $img = process_header_image_upload($orgId);
    if (!$img['ok']) {
        return ['ok' => false, 'message' => $img['error'] ?? 'Header image could not be saved.'];
    }

    $fImg = process_footer_image_upload($orgId);
    if (!$fImg['ok']) {
        return ['ok' => false, 'message' => $fImg['error'] ?? 'Footer image could not be saved.'];
    }

    $notes = [];
    if (!empty($img['cleared'])) $notes[] = 'header image removed';
    if (!empty($img['saved'])) $notes[] = 'header image updated';
    if (!empty($fImg['cleared'])) $notes[] = 'footer image removed';
    if (!empty($fImg['saved'])) $notes[] = 'footer image updated';

    $msg = 'Branding saved — used on bills and lab reports.';
    if (!empty($notes)) {
        $msg = 'Branding saved (' . implode(', ', $notes) . ').';
    }
    return ['ok' => true, 'message' => $msg];
}

function branding_header_image_field(array $settings): string
{
    $pos = \App\Repositories\SettingRepository::normalizeHeaderImagePosition(
        (string)($settings['header_image_position'] ?? 'left')
    );

    $layout = $settings['header_layout'] ?? null;
    if (is_string($layout) && $layout !== '') {
        $layout = json_decode($layout, true);
    }

    $canvasH = 150;
    if (isset($layout['canvas_h'])) {
        $canvasH = max(150, min(350, (int)$layout['canvas_h']));
    }

    $logoX = 15;
    $logoY = 10;
    $logoW = 240;
    $logoH = 70;
    if (!empty($layout['logo']) && is_array($layout['logo'])) {
        $logoX = max(0, min(700, (int)($layout['logo']['x'] ?? 15)));
        $logoY = max(0, min($canvasH - 20, (int)($layout['logo']['y'] ?? 10)));
        $logoW = max(60, min(760, (int)($layout['logo']['w'] ?? 240)));
        $logoH = max(30, min($canvasH, (int)($layout['logo']['h'] ?? 70)));
    } elseif ($pos === 'center') {
        $logoX = 260;
    } elseif ($pos === 'right') {
        $logoX = 500;
    }

    $qrX = 680;
    $qrY = 10;
    $qrSize = 60;
    if (!empty($layout['qr']) && is_array($layout['qr'])) {
        $qrX = max(0, min(700, (int)($layout['qr']['x'] ?? 680)));
        $qrY = max(0, min($canvasH - 20, (int)($layout['qr']['y'] ?? 10)));
        $qrSize = max(35, min(140, (int)($layout['qr']['size'] ?? 60)));
    } elseif ($pos === 'right') {
        $qrX = 20;
    }

    $initialData = [
        'canvas_h' => $canvasH,
        'logo' => [
            'x' => $logoX,
            'y' => $logoY,
            'w' => $logoW,
            'h' => $logoH,
            'x_pct' => round(($logoX / 760) * 100, 2),
            'w_pct' => round(($logoW / 760) * 100, 2),
        ],
        'qr' => [
            'x' => $qrX,
            'y' => $qrY,
            'size' => $qrSize,
            'x_pct' => round(($qrX / 760) * 100, 2),
        ],
    ];
    $layoutJson = htmlspecialchars((string)json_encode($initialData), ENT_QUOTES, 'UTF-8');

    $preview = '';
    $logoInnerHtml = '';
    if (!empty($settings['has_header_image']) && !empty($settings['header_image_url'])) {
        $url = e($settings['header_image_url']);
        $preview = <<<HTML
        <div class="branding-header-preview mt-3 pt-3 border-t border-slate-200">
            <p class="text-xs font-medium text-slate-600 mb-2">Current header image</p>
            <img src="{$url}" alt="Lab header" class="branding-header-preview__img">
            <label class="mt-2 flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="remove_header_image" value="1" class="rounded border-slate-300">
                Remove header image (use text letterhead only)
            </label>
        </div>
        HTML;

        $logoInnerHtml = '<img id="builder-logo-preview-img" src="' . $url . '" alt="Header Logo" draggable="false" style="max-height:100%;max-width:100%;object-fit:contain;pointer-events:none;">';
    } else {
        $logoText = e($settings['name'] ?? 'LAB LOGO / LETTERHEAD');
        $logoInnerHtml = '<div id="builder-logo-preview-text" class="flex flex-col items-center justify-center w-full h-full pointer-events-none text-teal-700 text-center select-none p-1">'
            . '<span class="font-bold text-xs uppercase tracking-wider">' . $logoText . '</span>'
            . '<span class="text-[10px] text-slate-400 mt-0.5">(Upload letterhead image below)</span>'
            . '</div>';
    }

    $qrInnerHtml = '<div class="flex items-center justify-center w-full h-full pointer-events-none select-none p-1">'
        . '<svg viewBox="0 0 24 24" width="100%" height="100%" fill="none" stroke="#0284c7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
        . '<rect x="3" y="3" width="7" height="7"></rect>'
        . '<rect x="14" y="3" width="7" height="7"></rect>'
        . '<rect x="3" y="14" width="7" height="7"></rect>'
        . '<rect x="7" y="7" width="1" height="1"></rect>'
        . '<rect x="17" y="7" width="1" height="1"></rect>'
        . '<rect x="7" y="17" width="1" height="1"></rect>'
        . '<line x1="14" y1="14" x2="14" y2="14.01"></line>'
        . '<line x1="17" y1="14" x2="17" y2="17"></line>'
        . '<line x1="14" y1="17" x2="17" y2="17"></line>'
        . '<line x1="20" y1="14" x2="20" y2="20"></line>'
        . '</svg>'
        . '</div>';

    return <<<HTML
    <div class="rounded-lg border border-slate-300 bg-slate-50 p-4 space-y-3 header-builder-wrap">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-sm font-semibold text-slate-800 flex items-center gap-1.5">
                    <i class="fa-solid fa-up-down-left-right text-teal-600"></i> Header Logo &amp; QR Code Builder (Drag &amp; Drop)
                </p>
                <p class="text-xs text-slate-500 mt-0.5">
                    Drag the <strong>Logo</strong> or <strong>QR Code</strong> box anywhere on the canvas. Drag the bottom-right circle handle (<span class="font-bold text-slate-700">⤡</span>) to resize.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <label for="builder-h-slider" class="text-xs font-medium text-slate-600 select-none">Height: <span id="builder-h-val" class="font-bold text-teal-700">{$canvasH}px</span></label>
                <input type="range" id="builder-h-slider" min="60" max="220" step="5" value="{$canvasH}" class="w-24 accent-teal-600 cursor-pointer" title="Adjust canvas height">
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-1.5 pt-1 text-xs">
            <span class="text-slate-400 font-medium mr-1 uppercase text-[10px] tracking-wider">Presets:</span>
            <button type="button" class="btn btn-secondary text-xs py-1 px-2.5" data-builder-preset="left">Logo Left · QR Right</button>
            <button type="button" class="btn btn-secondary text-xs py-1 px-2.5" data-builder-preset="center">Logo Center · QR Right</button>
            <button type="button" class="btn btn-secondary text-xs py-1 px-2.5" data-builder-preset="right">Logo Right · QR Left</button>
            <button type="button" class="btn btn-secondary text-xs py-1 px-2.5" data-builder-preset="default">Default</button>
        </div>

        <input type="hidden" name="header_layout_json" id="header_layout_json" value='{$layoutJson}'>
        <input type="hidden" name="header_image_position" id="header_image_position" value="{$pos}">

        <!-- Real Drag & Drop Interactive Canvas -->
        <div id="header-builder-canvas" class="header-builder-canvas" style="height: {$canvasH}px;">
            <div id="drag-item-logo" class="builder-item builder-item--logo" style="left: {$logoX}px; top: {$logoY}px; width: {$logoW}px; height: {$logoH}px;" title="Drag to move header logo">
                <span class="builder-badge">Header Logo / Letterhead</span>
                {$logoInnerHtml}
                <div class="builder-resize-handle" data-handle="se" title="Drag to resize">⤡</div>
            </div>

            <div id="drag-item-qr" class="builder-item builder-item--qr" style="left: {$qrX}px; top: {$qrY}px; width: {$qrSize}px; height: {$qrSize}px;" title="Drag to move QR code">
                <span class="builder-badge builder-badge--qr">QR Code</span>
                {$qrInnerHtml}
                <div class="builder-resize-handle" data-handle="se" title="Drag to resize">⤡</div>
            </div>
        </div>

        <div class="text-[11px] text-slate-500 flex items-center justify-between pt-1">
            <span>💡 Position and size will be rendered proportionally on preview, A4 prints, and downloaded PDFs.</span>
            <span id="builder-coords-status" class="text-slate-400 font-mono text-[10px]"></span>
        </div>

        {$preview}

        <div class="mt-3 pt-3 border-t border-slate-200">
            <label class="field-label" for="header_image">Upload header image <span class="text-slate-400 font-normal">(optional, PNG / JPG / WebP under 2 MB)</span></label>
            <input type="file" id="header_image" name="header_image" accept="image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-teal-700 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-teal-800">
        </div>
    </div>
    HTML;
}

function branding_footer_image_field(array $settings): string
{
    $layout = $settings['footer_layout'] ?? null;
    if (is_string($layout) && $layout !== '') {
        $layout = json_decode($layout, true);
    }

    $canvasH = 80;
    if (isset($layout['canvas_h'])) {
        $canvasH = max(40, min(220, (int)$layout['canvas_h']));
    }

    $imgX = 10;
    $imgY = 10;
    $imgW = 740;
    $imgH = 60;
    if (!empty($layout['image']) && is_array($layout['image'])) {
        $imgX = max(0, min(700, (int)($layout['image']['x'] ?? 10)));
        $imgY = max(0, min($canvasH - 20, (int)($layout['image']['y'] ?? 10)));
        $imgW = max(60, min(760, (int)($layout['image']['w'] ?? 740)));
        $imgH = max(20, min($canvasH, (int)($layout['image']['h'] ?? 60)));
    }

    $initialData = [
        'canvas_h' => $canvasH,
        'image' => [
            'x' => $imgX,
            'y' => $imgY,
            'w' => $imgW,
            'h' => $imgH,
            'x_pct' => round(($imgX / 760) * 100, 2),
            'w_pct' => round(($imgW / 760) * 100, 2),
        ],
    ];
    $layoutJson = htmlspecialchars((string)json_encode($initialData), ENT_QUOTES, 'UTF-8');

    $preview = '';
    $imgInnerHtml = '';
    if (!empty($settings['has_footer_image']) && !empty($settings['footer_image_url'])) {
        $url = e($settings['footer_image_url']);
        $preview = <<<HTML
        <div class="branding-footer-preview mt-3 pt-3 border-t border-slate-200">
            <p class="text-xs font-semibold text-slate-600 mb-2">Current footer image</p>
            <img src="{$url}" alt="Lab footer" class="max-h-24 max-w-full rounded border border-slate-200 object-contain p-1 bg-white">
            <label class="mt-2 flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="remove_footer_image" value="1" class="rounded border-slate-300">
                Remove footer image
            </label>
        </div>
        HTML;

        $imgInnerHtml = '<img id="builder-footer-preview-img" src="' . $url . '" alt="Footer Banner" draggable="false" style="max-height:100%;max-width:100%;object-fit:contain;pointer-events:none;">';
    } else {
        $footerText = e($settings['footer'] ?: 'LAB FOOTER / LETTERHEAD BANNER');
        $imgInnerHtml = '<div id="builder-footer-preview-text" class="flex flex-col items-center justify-center w-full h-full pointer-events-none text-sky-700 text-center select-none p-1">'
            . '<span class="font-bold text-xs uppercase tracking-wider">' . $footerText . '</span>'
            . '<span class="text-[10px] text-slate-400 mt-0.5">(Upload letterhead footer image below)</span>'
            . '</div>';
    }

    return <<<HTML
    <div class="rounded-xl border border-slate-300 bg-slate-50 p-4 space-y-3 footer-builder-wrap">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-sm font-semibold text-slate-800 flex items-center gap-1.5">
                    <i class="fa-solid fa-shoe-prints text-sky-600"></i> Footer Letterhead Builder (Drag &amp; Drop)
                </p>
                <p class="text-xs text-slate-500 mt-0.5">
                    Position and resize your <strong>Footer Image</strong> on the canvas. Drag the bottom-right circle handle (<span class="font-bold text-slate-700">⤡</span>) to resize.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <label for="footer-builder-h-slider" class="text-xs font-medium text-slate-600 select-none">Height: <span id="footer-builder-h-val" class="font-bold text-sky-700">{$canvasH}px</span></label>
                <input type="range" id="footer-builder-h-slider" min="40" max="200" step="5" value="{$canvasH}" class="w-24 accent-sky-600 cursor-pointer" title="Adjust footer canvas height">
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-1.5 pt-1 text-xs">
            <span class="text-slate-400 font-medium mr-1 uppercase text-[10px] tracking-wider">Presets:</span>
            <button type="button" class="btn btn-secondary text-xs py-1 px-2.5" data-footer-preset="full">Full Width</button>
            <button type="button" class="btn btn-secondary text-xs py-1 px-2.5" data-footer-preset="center">Center Banner</button>
            <button type="button" class="btn btn-secondary text-xs py-1 px-2.5" data-footer-preset="left">Left Banner</button>
            <button type="button" class="btn btn-secondary text-xs py-1 px-2.5" data-footer-preset="default">Default</button>
        </div>

        <input type="hidden" name="footer_layout_json" id="footer_layout_json" value='{$layoutJson}'>

        <!-- Footer Drag & Drop Canvas -->
        <div id="footer-builder-canvas" class="footer-builder-canvas" style="height: {$canvasH}px;">
            <div id="drag-item-footer-img" class="builder-item builder-item--footer-img" style="left: {$imgX}px; top: {$imgY}px; width: {$imgW}px; height: {$imgH}px;" title="Drag to move footer image">
                <span class="builder-badge builder-badge--footer">Footer Banner / Image</span>
                {$imgInnerHtml}
                <div class="builder-resize-handle" data-handle="se" title="Drag to resize">⤡</div>
            </div>
        </div>

        <div class="text-[11px] text-slate-500 flex items-center justify-between pt-1">
            <span>💡 Position and size will be rendered proportionally on preview, A4 prints, and downloaded PDFs.</span>
            <span id="footer-builder-coords-status" class="text-slate-400 font-mono text-[10px]"></span>
        </div>

        {$preview}

        <div class="mt-3 pt-3 border-t border-slate-200">
            <label class="field-label" for="footer_image">Upload footer image <span class="text-slate-400 font-normal">(optional, PNG / JPG / WebP under 2 MB)</span></label>
            <input type="file" id="footer_image" name="footer_image" accept="image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-sky-700 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-sky-800">
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
        if (!$p && !empty($entry['patient_name'])) {
            $matches = patient_repo()->search((string)$entry['patient_name'], $settings['organization_id'] ?? 'ORG-001');
            if (!empty($matches)) {
                $p = $matches[0];
            }
        }
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
        $results = result_repo()->ensureResultsInitialized(
            $labNo,
            (string)($entry['tests'] ?? ''),
            (string)($patient['name'] ?? ''),
            $settings['organization_id'] ?? 'ORG-001'
        );

        $testTitles = array_unique(array_filter(array_column($results, 'test')));
        $testMethodologies = [];
        $testCatalogMeta = [];
        foreach ($testTitles as $tt) {
            $tObj = test_repo()->findByCode($tt, $settings['organization_id'] ?? 'ORG-001');
            $testCatalogMeta[$tt] = $tObj;
            if ($tObj && !empty($tObj['methodology'])) {
                $testMethodologies[$tt] = trim((string)$tObj['methodology']);
            }
        }

        foreach ($results as $r) {
            if (($r['value'] ?? '') === '' && ($r['parameter'] ?? '') === '') {
                continue;
            }
            // Skip parameters the lab unchecked (Show = off)
            if (isset($r['is_visible']) && (int)$r['is_visible'] === 0) {
                continue;
            }
            $tTitle = $r['test'] ?? '';
            $tObj = $testCatalogMeta[$tTitle] ?? null;
            $pCount = $tObj ? count(test_repo()->getParameters((string)$tObj['id'])) : -1;
            $paramName = $r['parameter'] ?: ($tTitle !== '' ? $tTitle : 'Result');
            if ($pCount === 0 && $tObj) {
                $paramName = $tObj['name'] ?: $tTitle;
            }

            $deptInfo = get_test_department_info($tTitle, $tObj['category'] ?? null);
            $isImaging = $deptInfo['is_imaging'];
            $isMicro = $deptInfo['is_microbiology'];
            $hasRef = $deptInfo['has_reference_ranges'];

            $lines[] = [
                'test_title' => $tTitle,
                'department_code' => $deptInfo['department_code'],
                'department_name' => $deptInfo['department_name'],
                'department_title' => $deptInfo['department_title'],
                'has_ref_range' => $hasRef,
                'section' => ($pCount === 0) ? '' : ($r['section'] ?? ''),
                'test' => $paramName,
                'result' => ($r['value'] !== null && $r['value'] !== '') ? $r['value'] : 'Pending',
                'unit' => ($hasRef && ($r['unit'] !== '' && $r['unit'] !== '—')) ? $r['unit'] : ($hasRef ? ($tObj['unit'] ?? '—') : ''),
                'range' => $hasRef ? (($r['reference_range'] ?: ($r['normal_value'] ?? '')) ?: ($tObj['reference_value'] ?? $tObj['normal_value'] ?? $tObj['normal_range'] ?? '—')) : '',
                'sub_table' => ($pCount === 0) ? '' : ($r['sub_table'] ?? ''),
                'result_note' => ($pCount === 0) ? '' : ($r['result_note'] ?? ''),
                'methodology' => $testMethodologies[$tTitle] ?? '',
                'flag' => $r['flag'] ?? '',
                'print_page' => max(1, (int)($r['print_page'] ?? 1)),
                'is_imaging' => $isImaging,
                'is_microbiology' => $isMicro,
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
                $deptInfo = get_test_department_info($tName, $meta['category'] ?? null);
                $isImaging = $deptInfo['is_imaging'];
                $isMicro = $deptInfo['is_microbiology'];
                $hasRef = $deptInfo['has_reference_ranges'];

                $lines[] = [
                    'test_title' => $tName,
                    'department_code' => $deptInfo['department_code'],
                    'department_name' => $deptInfo['department_name'],
                    'department_title' => $deptInfo['department_title'],
                    'has_ref_range' => $hasRef,
                    'section' => '',
                    'test' => $tName,
                    'result' => 'Pending',
                    'unit' => $hasRef ? ($meta['unit'] ?? '—') : '',
                    'range' => $hasRef ? ($meta['range'] ?? '—') : '',
                    'sub_table' => '',
                    'flag' => '',
                    'print_page' => 1,
                    'is_imaging' => $isImaging,
                    'is_microbiology' => $isMicro,
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
    $imgHtml = '';
    if (!empty($settings['has_footer_image']) && !empty($settings['footer_image_url'])) {
        $imgHtml = '<div class="lab-report__footer-custom mb-2 text-center"><img src="' . e($settings['footer_image_url']) . '" alt="Footer Letterhead" style="max-height:60px;max-width:100%;object-fit:contain;"></div>';
    }

    $rawFooter = trim((string)($forBill ? (!empty($settings['bill_footer']) ? $settings['bill_footer'] : ($settings['footer'] ?? '')) : ($settings['footer'] ?? '')));
    if ($forBill) {
        if ($rawFooter === '' || preg_match('/electronically verified|queries call reception/i', $rawFooter)) {
            $rawFooter = 'Get well soon.';
        }
    } elseif ($rawFooter !== '' && preg_match('/electronically verified|queries call reception/i', $rawFooter)) {
        $rawFooter = 'Get well soon.';
    }
    $footer = e($rawFooter);
    $labLine = $footer !== ''
        ? '<p class="lab-report__lab-note">' . $footer . '</p>'
        : '';
    return $imgHtml . $labLine . software_credit_footer(true);
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
 * Supports page grouping: lines with print_page produce independent sheets.
 *
 * @param array{hide_header?:bool,hide_qr?:bool,hide_all?:bool} $options
 */
function render_report_document(
    array $settings,
    array $patient,
    array $resultLines,
    ?array $entry = null,
    string $reportTitle = 'DEPARTMENT OF LABORATORY MEDICINE',
    array $signatories = [],
    string $specimen = 'Serum',
    array $options = []
): string {
    $rawPages = [];
    foreach ($resultLines as $line) {
        $pageNo = max(1, (int)($line['print_page'] ?? 1));
        $rawPages[$pageNo][] = $line;
    }
    if ($rawPages === []) {
        $rawPages[1] = [];
    }
    ksort($rawPages, SORT_NUMERIC);

    // Department-wise separation: Radiology imaging studies (Ultrasound, X-Ray, ECG) must NEVER be mixed on the same sheet as Pathology lab tables
    $pages = [];
    $sheetNum = 1;
    foreach ($rawPages as $pNo => $pLines) {
        $labLines = [];
        $imagingLines = [];
        foreach ($pLines as $l) {
            if (!empty($l['is_imaging'])) {
                $imagingLines[] = $l;
            } else {
                $labLines[] = $l;
            }
        }
        if (!empty($labLines) && !empty($imagingLines)) {
            $pages[$sheetNum++] = $labLines;
            foreach ($imagingLines as $imgL) {
                $pages[$sheetNum++] = [$imgL];
            }
        } else {
            $pages[$sheetNum++] = $pLines;
        }
    }
    $totalPages = count($pages);

    $sheets = '';
    $index = 0;
    foreach ($pages as $pageNo => $pageLines) {
        $index++;
        $sheets .= render_report_sheet(
            $settings,
            $patient,
            $pageLines,
            $entry,
            $reportTitle,
            $signatories,
            $specimen,
            $index,
            $totalPages,
            $index < $totalPages
        );
    }

    return '<div class="print-stack" data-print-stack>' . $sheets . '</div>';
}

/**
 * Department classification and reference value requirements:
 * - Radiology (Ultrasound, X-Ray, ECG, CT scan): Reference range/normal values DO NOT apply.
 * - Microbiology: Reference range/normal values DO NOT apply.
 * - Histopathology: Reference range/normal values DO NOT apply.
 * - Chemistry, Hematology, Special Chemistry, etc.: Reference ranges/normal values DO apply.
 */
function get_test_department_info(string $testTitle, ?string $category = null): array
{
    $title = trim($testTitle);
    $cat = $category !== null ? trim($category) : '';

    // 1. Radiology / Imaging / Ultrasound / ECG / X-Ray / CT
    if (in_array(strtolower($cat), ['radiology', 'imaging', 'cardiology'], true)
        || preg_match('/(ultrasound|x-ray|xray|ecg|ekg|ct scan|computed tomography|doppler|mri|radiology|sonograph|echocardiogra|mammograph)/i', $title)
    ) {
        $deptTitle = 'DEPARTMENT OF RADIOLOGY & DIAGNOSTIC IMAGING';
        if (preg_match('/(ultrasound|sonograph|doppler)/i', $title)) {
            $deptTitle = 'DEPARTMENT OF RADIOLOGY & ULTRASONOGRAPHY';
        } elseif (preg_match('/(ecg|ekg|echocardiogra)/i', $title)) {
            $deptTitle = 'DEPARTMENT OF CARDIOLOGY & ECG';
        } elseif (preg_match('/(mri|magnetic resonance)/i', $title)) {
            $deptTitle = 'DEPARTMENT OF MRI & ADVANCED IMAGING';
        } elseif (preg_match('/(ct scan|computed tomography)/i', $title)) {
            $deptTitle = 'DEPARTMENT OF COMPUTED TOMOGRAPHY (CT SCAN)';
        } elseif (preg_match('/(x-ray|xray|radiography)/i', $title)) {
            $deptTitle = 'DEPARTMENT OF RADIOLOGY (X-RAY)';
        }
        return [
            'department_code' => 'radiology',
            'department_name' => 'Radiology & Imaging',
            'department_title' => $deptTitle,
            'has_reference_ranges' => false,
            'is_imaging' => true,
            'is_microbiology' => false,
        ];
    }

    // 2. Microbiology & Parasitology
    if (in_array(strtolower($cat), ['microbiology', 'parasitology'], true)
        || preg_match('/(microbiology|culture|sensitivity|c\/s|c & s|gram stain|afb|zn stain|fungal|swab|urine c\/s|blood c\/s|stool c\/s|pus c\/s|sputum c\/s)/i', $title)
    ) {
        return [
            'department_code' => 'microbiology',
            'department_name' => 'Microbiology',
            'department_title' => 'DEPARTMENT OF MICROBIOLOGY',
            'has_reference_ranges' => false,
            'is_imaging' => false,
            'is_microbiology' => true,
        ];
    }

    // 3. Histopathology & Cytopathology
    if (in_array(strtolower($cat), ['histopathology', 'cytopathology'], true)
        || preg_match('/(histopath|biopsy|cytology|pap smear|fnac)/i', $title)
    ) {
        return [
            'department_code' => 'histopathology',
            'department_name' => 'Histopathology',
            'department_title' => 'DEPARTMENT OF HISTOPATHOLOGY',
            'has_reference_ranges' => false,
            'is_imaging' => false,
            'is_microbiology' => false,
        ];
    }

    // 4. Hematology
    if (in_array(strtolower($cat), ['hematology'], true)
        || preg_match('/(hematology|haematology|cbc|complete blood|haemoglobin|hemoglobin|esr|platelet|pt|inr|aptt|blood group|coagulation|peripheral film)/i', $title)
    ) {
        return [
            'department_code' => 'hematology',
            'department_name' => 'Hematology',
            'department_title' => 'DEPARTMENT OF HAEMATOLOGY',
            'has_reference_ranges' => true,
            'is_imaging' => false,
            'is_microbiology' => false,
        ];
    }

    // 5. Special Chemistry & Endocrinology & Immunology
    if (in_array(strtolower($cat), ['special chemistry', 'endocrinology', 'immunology', 'molecular'], true)
        || preg_match('/(special chemistry|tft|thyroid|tsh|ft3|ft4|hba1c|troponin|vitamin|ferritin|hormone|psa|beta hcg|prolactin|cortisol|insulin)/i', $title)
    ) {
        return [
            'department_code' => 'special_chemistry',
            'department_name' => 'Special Chemistry',
            'department_title' => 'DEPARTMENT OF SPECIAL CHEMISTRY',
            'has_reference_ranges' => true,
            'is_imaging' => false,
            'is_microbiology' => false,
        ];
    }

    // 6. Chemical Pathology / Biochemistry / Chemistry
    if (in_array(strtolower($cat), ['biochemistry', 'chemistry', 'chemical pathology', 'clinical pathology'], true)
        || preg_match('/(chemistry|biochemistry|lft|rft|lipid|glucose|sugar|urea|creatinine|bilirubin|electrolytes|uric acid|alt|ast|alp)/i', $title)
    ) {
        return [
            'department_code' => 'chemistry',
            'department_name' => 'Chemical Pathology',
            'department_title' => 'DEPARTMENT OF CHEMICAL PATHOLOGY',
            'has_reference_ranges' => true,
            'is_imaging' => false,
            'is_microbiology' => false,
        ];
    }

    // 7. Serology
    if (in_array(strtolower($cat), ['serology'], true)
        || preg_match('/(serology|elisa|hcv|hbsag|hiv|vdrl|widal|typhidot|ra factor|crp|aso)/i', $title)
    ) {
        return [
            'department_code' => 'serology',
            'department_name' => 'Serology',
            'department_title' => 'DEPARTMENT OF SEROLOGY & IMMUNOLOGY',
            'has_reference_ranges' => true,
            'is_imaging' => false,
            'is_microbiology' => false,
        ];
    }

    $rawCat = $cat !== '' ? $cat : 'Pathology';
    return [
        'department_code' => 'pathology',
        'department_name' => $rawCat,
        'department_title' => department_report_heading($rawCat),
        'has_reference_ranges' => true,
        'is_imaging' => false,
        'is_microbiology' => false,
    ];
}

/**
 * Determine whether a test belongs to Radiology/Imaging (Ultrasound, X-Ray, ECG, CT Scan)
 * that should be rendered as a full-page narrative report without lab reference columns.
 */
function is_narrative_imaging_test(string $testName, ?string $category = null): bool
{
    $info = get_test_department_info($testName, $category);
    return !empty($info['is_imaging']);
}

/**
 * Determine whether a given test or biomarker is eligible for the vertical ladder graphical report.
 */
function is_graph_eligible_test(string $testTitle, string $paramName): bool
{
    $combined = strtoupper($testTitle . ' ' . $paramName);

    // 1. Lipid Profile
    if (str_contains($combined, 'LIPID')
        || str_contains($combined, 'CHOLESTEROL')
        || str_contains($combined, 'TRIGLYCERIDE')
        || str_contains($combined, 'HDL')
        || str_contains($combined, 'LDL')
        || str_contains($combined, 'VLDL')
    ) {
        return true;
    }

    // 2. RFTS (Renal / Kidney Function Tests)
    if (str_contains($combined, 'RFT')
        || str_contains($combined, 'RENAL')
        || str_contains($combined, 'KIDNEY')
        || str_contains($combined, 'CREATININE')
        || str_contains($combined, 'UREA')
        || str_contains($combined, 'BUN')
        || str_contains($combined, 'URIC ACID')
        || str_contains($combined, 'URATE')
    ) {
        return true;
    }

    // 3. LFTS (Liver Function Tests)
    if (str_contains($combined, 'LFT')
        || str_contains($combined, 'LIVER')
        || str_contains($combined, 'HEPATIC')
        || str_contains($combined, 'BILIRUBIN')
        || str_contains($combined, 'SGPT')
        || str_contains($combined, 'ALT')
        || str_contains($combined, 'SGOT')
        || str_contains($combined, 'AST')
        || str_contains($combined, 'ALKALINE PHOSPHATASE')
        || str_contains($combined, 'ALP')
        || str_contains($combined, 'TOTAL PROTEIN')
        || str_contains($combined, 'ALBUMIN')
        || str_contains($combined, 'GLOBULIN')
        || str_contains($combined, 'A/G RATIO')
    ) {
        return true;
    }

    // 4. HbA1c
    if (str_contains($combined, 'HBA1C')
        || str_contains($combined, 'GLYCATED')
        || str_contains($combined, 'A1C')
        || str_contains($combined, 'AVERAGE GLUCOSE')
        || str_contains($combined, 'EAG')
    ) {
        return true;
    }

    // 5. Thyroid Profile (TFTs)
    if (str_contains($combined, 'THYROID')
        || str_contains($combined, 'TFT')
        || str_contains($combined, 'TSH')
        || str_contains($combined, 'FT3')
        || str_contains($combined, 'FT4')
        || str_contains($combined, 'FREE T3')
        || str_contains($combined, 'FREE T4')
        || str_contains($combined, 'TOTAL T3')
        || str_contains($combined, 'TOTAL T4')
        || preg_match('/\bT3\b|\bT4\b/', $combined)
    ) {
        return true;
    }

    // 6. Electrolytes
    if (str_contains($combined, 'ELECTROLYTE')
        || str_contains($combined, 'SODIUM')
        || str_contains($combined, 'POTASSIUM')
        || str_contains($combined, 'CHLORIDE')
        || str_contains($combined, 'CALCIUM')
        || str_contains($combined, 'BICARBONATE')
        || preg_match('/\bNA\b|\bK\b|\bCL\b|\bCA\b|\bHCO3\b/', $combined)
    ) {
        return true;
    }

    // 7. ELISA (HCV, HBV/HBsAg, HIV, HEV)
    if (str_contains($combined, 'ELISA')
        || str_contains($combined, 'HCV')
        || str_contains($combined, 'HEV')
        || str_contains($combined, 'HBSAG')
        || str_contains($combined, 'HBV')
        || str_contains($combined, 'HIV')
        || str_contains($combined, 'S/CO')
        || str_contains($combined, 'INDEX')
        || str_contains($combined, 'COI')
        || str_contains($combined, 'CUTOFF')
    ) {
        return true;
    }

    return false;
}

/**
 * Render clinical vertical ladder chart report matching visual layout in reference image.
 * Features:
 * - Left column: Parameter title & formatted interpretation ranges
 * - Middle column: Vertical ladder scale with horizontal grid lines, value marker badge, and normal/high legend
 * - Right column: Large prominent numeric readout with unit
 */
function render_parameter_gauge(
    string $paramName,
    float $numVal,
    string $unit,
    string $rangeRaw,
    string $flag,
    string $subText
): string {
    $paramLower = strtolower($paramName);
    $flagLower = strtolower($flag);
    $rangeStr = trim($rangeRaw);

    $minScale = 0.0;
    $maxScale = 100.0;
    $ticks = [];
    $isLow = false;
    $isHigh = false;
    $normalText = '';
    $lowText = '';
    $highText = '';
    $defaultSubText = '';

    // 1. Total Cholesterol
    if (str_contains($paramLower, 'cholesterol') && !str_contains($paramLower, 'hdl') && !str_contains($paramLower, 'ldl') && !str_contains($paramLower, 'ratio')) {
        $maxScale = max(250.0, ceil($numVal / 50.0) * 50.0);
        $step = $maxScale / 5.0;
        $ticks = [$maxScale, $maxScale - $step, $maxScale - ($step * 2), $maxScale - ($step * 3), $maxScale - ($step * 4), 0.0];
        $isHigh = $numVal >= 200.0;
        $normalText = 'Less Than 200.00';
        $highText = '> 200.00';
        $defaultSubText = "Less than : 200 (Desirable)\n200 - 239 : (Borderline High)\nMore than: 240 (High)";
    }
    // 2. Triglycerides
    elseif (str_contains($paramLower, 'triglyceride')) {
        $maxScale = max(350.0, ceil($numVal / 50.0) * 50.0);
        $step = 50.0;
        $ticks = [];
        for ($t = $maxScale; $t >= 0.0; $t -= $step) {
            $ticks[] = $t;
        }
        $isHigh = $numVal >= 200.0;
        $normalText = 'Less Than 200.00';
        $highText = '> 200.00';
        $defaultSubText = "Adults < 65 Years : < 200\nAdults > 65 Years : < 365";
    }
    // 3. HDL (Cholesterol)
    elseif (str_contains($paramLower, 'hdl')) {
        $maxScale = 40.0;
        $ticks = [40.0, 30.0, 20.0, 10.0, 0.0];
        $isLow = $numVal < 45.0;
        $isHigh = $numVal > 65.0;
        $normalText = '45.00 - 65.00';
        $lowText = '< 45.00';
        $highText = '> 65.00';
        $defaultSubText = "Male : 35 - 55\nFemale : 45 - 65\n- Without known Coronary Artery Disease More than\nOr Equal to 55: DESIRABLE\n- With known Coronary Artery Disease Less than\nOr Equal to 35: OPTIMAL";
    }
    // 4. LDL (Cholesterol)
    elseif (str_contains($paramLower, 'ldl') && !str_contains($paramLower, 'vldl')) {
        $maxScale = max(140.0, ceil($numVal / 20.0) * 20.0);
        $step = 20.0;
        $ticks = [];
        for ($t = $maxScale; $t >= 0.0; $t -= $step) {
            $ticks[] = $t;
        }
        $isHigh = $numVal >= 150.0;
        $normalText = 'Less Than 150.00';
        $highText = '> 150.00';
        $defaultSubText = "Desirable : < 130 (< 99 for patients with CHD)\nBorderline : 130-159\nHigh Risk : = 160";
    }
    // 5. VLDL Cholesterol
    elseif (str_contains($paramLower, 'vldl')) {
        $maxScale = max(60.0, ceil($numVal / 10.0) * 10.0);
        $step = 10.0;
        $ticks = [];
        for ($t = $maxScale; $t >= 0.0; $t -= $step) {
            $ticks[] = $t;
        }
        $isHigh = $numVal >= 40.0;
        $normalText = '5.00 - 40.00';
        $highText = '> 40.00';
        $defaultSubText = "Normal Range : 5.00 - 40.00 mg/dL\nHigh Level : > 40.00 mg/dL";
    }
    // 6. Creatinine (RFT)
    elseif (str_contains($paramLower, 'creatinine')) {
        $maxScale = max(3.0, ceil($numVal * 2.0) / 2.0);
        $ticks = [$maxScale, $maxScale - 0.5, $maxScale - 1.0, $maxScale - 1.5, $maxScale - 2.0, $maxScale - 2.5, 0.0];
        $isLow = $numVal < 0.6;
        $isHigh = $numVal > 1.2;
        $normalText = '0.60 - 1.20';
        $lowText = '< 0.60';
        $highText = '> 1.20';
        $defaultSubText = "Normal Male : 0.70 - 1.30 mg/dL\nNormal Female : 0.50 - 1.10 mg/dL\nImpaired Renal Function : > 1.20 mg/dL";
    }
    // 7. Urea / Blood Urea / BUN (RFT)
    elseif (str_contains($paramLower, 'urea') || str_contains($paramLower, 'bun')) {
        $isBun = str_contains($paramLower, 'bun');
        if ($isBun) {
            $maxScale = max(40.0, ceil($numVal / 10.0) * 10.0);
            $ticks = [$maxScale, $maxScale - 10, $maxScale - 20, $maxScale - 30, 0.0];
            $isHigh = $numVal > 20.0;
            $normalText = '7.00 - 20.00';
            $highText = '> 20.00';
            $defaultSubText = "Normal Range : 7.00 - 20.00 mg/dL\nElevated BUN : > 20.00 mg/dL";
        } else {
            $maxScale = max(100.0, ceil($numVal / 20.0) * 20.0);
            $ticks = [$maxScale, $maxScale - 20, $maxScale - 40, $maxScale - 60, $maxScale - 80, 0.0];
            $isHigh = $numVal > 45.0;
            $normalText = '15.00 - 45.00';
            $highText = '> 45.00';
            $defaultSubText = "Normal Range : 15.00 - 45.00 mg/dL\nElevated Urea (Azotemia) : > 45.00 mg/dL";
        }
    }
    // 8. Uric Acid (RFT)
    elseif (str_contains($paramLower, 'uric')) {
        $maxScale = max(12.0, ceil($numVal / 2.0) * 2.0);
        $ticks = [$maxScale, $maxScale - 2, $maxScale - 4, $maxScale - 6, $maxScale - 8, $maxScale - 10, 0.0];
        $isHigh = $numVal > 7.2;
        $normalText = '3.50 - 7.20';
        $highText = '> 7.20';
        $defaultSubText = "Male : 3.50 - 7.20 mg/dL\nFemale : 2.60 - 6.00 mg/dL\nHyperuricemia : > 7.20 mg/dL";
    }
    // 9. SGPT / ALT (LFT)
    elseif (str_contains($paramLower, 'sgpt') || str_contains($paramLower, 'alt')) {
        $maxScale = max(150.0, ceil($numVal / 25.0) * 25.0);
        $step = 25.0;
        $ticks = [];
        for ($t = $maxScale; $t >= 0.0; $t -= $step) {
            $ticks[] = $t;
        }
        $isHigh = $numVal > 45.0;
        $normalText = 'Less Than 45.00';
        $highText = '> 45.00';
        $defaultSubText = "Normal : Up to 45.00 U/L\nBorderline Elevated : 46.00 - 80.00\nMarkedly High : > 80.00 U/L";
    }
    // 10. SGOT / AST (LFT)
    elseif (str_contains($paramLower, 'sgot') || str_contains($paramLower, 'ast')) {
        $maxScale = max(120.0, ceil($numVal / 20.0) * 20.0);
        $step = 20.0;
        $ticks = [];
        for ($t = $maxScale; $t >= 0.0; $t -= $step) {
            $ticks[] = $t;
        }
        $isHigh = $numVal > 40.0;
        $normalText = 'Less Than 40.00';
        $highText = '> 40.00';
        $defaultSubText = "Normal : Up to 40.00 U/L\nElevated Level : > 40.00 U/L";
    }
    // 11. Bilirubin (Total / Direct / Indirect - LFT)
    elseif (str_contains($paramLower, 'bilirubin')) {
        $maxScale = max(3.0, ceil($numVal * 2.0) / 2.0);
        $ticks = [$maxScale, $maxScale - 0.5, $maxScale - 1.0, $maxScale - 1.5, $maxScale - 2.0, $maxScale - 2.5, 0.0];
        $isHigh = $numVal > 1.2;
        $normalText = '0.20 - 1.20';
        $highText = '> 1.20';
        $defaultSubText = "Normal Range : 0.20 - 1.20 mg/dL\nJaundice / Hyperbilirubinemia : > 1.20 mg/dL";
    }
    // 12. Alkaline Phosphatase / ALP (LFT)
    elseif (str_contains($paramLower, 'alkaline') || str_contains($paramLower, 'alp')) {
        $maxScale = max(350.0, ceil($numVal / 50.0) * 50.0);
        $step = 50.0;
        $ticks = [];
        for ($t = $maxScale; $t >= 0.0; $t -= $step) {
            $ticks[] = $t;
        }
        $isHigh = $numVal > 306.0;
        $normalText = '65.00 - 306.00';
        $highText = '> 306.00';
        $defaultSubText = "Normal Adults : 65.00 - 306.00 U/L\nElevated (Biliary / Bone) : > 306.00 U/L";
    }
    // 13. Total Protein / Albumin / Globulin (LFT)
    elseif (str_contains($paramLower, 'albumin') || str_contains($paramLower, 'protein') || str_contains($paramLower, 'globulin')) {
        $maxScale = 10.0;
        $ticks = [10.0, 8.0, 6.0, 4.0, 2.0, 0.0];
        $isLow = $numVal < 3.5;
        $isHigh = $numVal > 8.3;
        $normalText = '3.50 - 5.50';
        $lowText = '< 3.50';
        $highText = '> 5.50';
        $defaultSubText = "Albumin Normal : 3.50 - 5.50 g/dL\nTotal Protein : 6.00 - 8.30 g/dL";
    }
    // 14. HbA1c
    elseif (str_contains($paramLower, 'hba1c') || str_contains($paramLower, 'glycated') || str_contains($paramLower, 'a1c')) {
        $maxScale = max(14.0, ceil($numVal / 2.0) * 2.0);
        $step = 2.0;
        $ticks = [];
        for ($t = $maxScale; $t >= 0.0; $t -= $step) {
            $ticks[] = $t;
        }
        $isHigh = $numVal >= 6.5;
        $isLow = false;
        $normalText = 'Less Than 5.70 %';
        $highText = 'Diabetes >= 6.50 %';
        $defaultSubText = "Less than 5.7 % : Normal (Non-diabetic)\n5.7 - 6.4 % : Prediabetes (Borderline)\n6.5 % or More : Diabetes Mellitus";
    }
    // 15. TSH (Thyroid)
    elseif (str_contains($paramLower, 'tsh')) {
        $maxScale = max(8.0, ceil($numVal / 2.0) * 2.0);
        $step = 2.0;
        $ticks = [];
        for ($t = $maxScale; $t >= 0.0; $t -= $step) {
            $ticks[] = $t;
        }
        $isLow = $numVal < 0.40;
        $isHigh = $numVal > 4.20;
        $normalText = '0.40 - 4.20';
        $lowText = '< 0.40';
        $highText = '> 4.20';
        $defaultSubText = "Euthyroid (Normal) : 0.40 - 4.20 uIU/mL\nHyperthyroidism : < 0.40 uIU/mL\nHypothyroidism : > 4.20 uIU/mL";
    }
    // 16. Free T4 / Free T3 / Total T3 / Total T4 (Thyroid)
    elseif (str_contains($paramLower, 't3') || str_contains($paramLower, 't4')) {
        if (str_contains($paramLower, 'total t3') || (str_contains($paramLower, 't3') && !str_contains($paramLower, 'free') && $numVal > 20)) {
            $maxScale = max(250.0, ceil($numVal / 50.0) * 50.0);
            $ticks = [$maxScale, $maxScale - 50, $maxScale - 100, $maxScale - 150, $maxScale - 200, 0.0];
            $isLow = $numVal < 80.0;
            $isHigh = $numVal > 200.0;
            $normalText = '80.00 - 200.00';
            $lowText = '< 80.00';
            $highText = '> 200.00';
            $defaultSubText = "Normal Total T3 : 80.00 - 200.00 ng/dL";
        } elseif (str_contains($paramLower, 'total t4') || (str_contains($paramLower, 't4') && !str_contains($paramLower, 'free') && $numVal > 3.0)) {
            $maxScale = max(16.0, ceil($numVal / 4.0) * 4.0);
            $ticks = [$maxScale, $maxScale - 4, $maxScale - 8, $maxScale - 12, 0.0];
            $isLow = $numVal < 4.5;
            $isHigh = $numVal > 12.0;
            $normalText = '4.50 - 12.00';
            $lowText = '< 4.50';
            $highText = '> 12.00';
            $defaultSubText = "Normal Total T4 : 4.50 - 12.00 ug/dL";
        } else {
            $maxScale = max(3.0, ceil($numVal * 2.0) / 2.0);
            $ticks = [$maxScale, $maxScale - 0.5, $maxScale - 1.0, $maxScale - 1.5, $maxScale - 2.0, $maxScale - 2.5, 0.0];
            $isLow = $numVal < 0.8;
            $isHigh = $numVal > 1.8;
            $normalText = '0.80 - 1.80';
            $lowText = '< 0.80';
            $highText = '> 1.80';
            $defaultSubText = "Normal Euthyroid : 0.80 - 1.80 ng/dL\nLow Level : < 0.80 ng/dL\nHigh Level : > 1.80 ng/dL";
        }
    }
    // 17. Sodium / Na (Electrolytes)
    elseif (str_contains($paramLower, 'sodium') || preg_match('/\bna\b/', $paramLower)) {
        $minScale = 110.0;
        $maxScale = 160.0;
        $ticks = [160.0, 150.0, 140.0, 130.0, 120.0, 110.0];
        $isLow = $numVal < 135.0;
        $isHigh = $numVal > 145.0;
        $normalText = '135.00 - 145.00';
        $lowText = '< 135.00';
        $highText = '> 145.00';
        $defaultSubText = "Normal Range : 135.00 - 145.00 mEq/L\nHyponatremia : < 135.00 mEq/L\nHypernatremia : > 145.00 mEq/L";
    }
    // 18. Potassium / K (Electrolytes)
    elseif (str_contains($paramLower, 'potassium') || preg_match('/\bk\b/', $paramLower)) {
        $minScale = 0.0;
        $maxScale = 7.0;
        $ticks = [7.00, 6.00, 5.00, 4.00, 3.00, 2.00, 0.00];
        $isLow = $numVal < 3.5;
        $isHigh = $numVal > 5.1;
        $normalText = '3.50 - 5.10';
        $lowText = '< 3.50';
        $highText = '> 5.10';
        $defaultSubText = "Normal Range : 3.50 - 5.10 mEq/L\nHypokalemia : < 3.50 mEq/L\nHyperkalemia : > 5.10 mEq/L";
    }
    // 19. Chloride / Cl (Electrolytes)
    elseif (str_contains($paramLower, 'chloride') || preg_match('/\bcl\b/', $paramLower)) {
        $minScale = 70.0;
        $maxScale = 130.0;
        $ticks = [130.0, 115.0, 100.0, 85.0, 70.0];
        $isLow = $numVal < 96.0;
        $isHigh = $numVal > 108.0;
        $normalText = '96.00 - 108.00';
        $lowText = '< 96.00';
        $highText = '> 108.00';
        $defaultSubText = "Normal Range : 96.00 - 108.00 mEq/L";
    }
    // 20. Calcium (Electrolytes)
    elseif (str_contains($paramLower, 'calcium') || preg_match('/\bca\b/', $paramLower)) {
        $maxScale = 15.0;
        $ticks = [15.0, 12.0, 9.0, 6.0, 3.0, 0.0];
        $isLow = $numVal < 8.5;
        $isHigh = $numVal > 10.5;
        $normalText = '8.50 - 10.50';
        $lowText = '< 8.50';
        $highText = '> 10.50';
        $defaultSubText = "Normal Adults : 8.50 - 10.50 mg/dL";
    }
    // 21. ELISA Tests (HCV, HBsAg, HIV, HEV)
    elseif (str_contains($paramLower, 'elisa') || str_contains($paramLower, 'hcv') || str_contains($paramLower, 'hbsag') || str_contains($paramLower, 'hbv') || str_contains($paramLower, 'hiv') || str_contains($paramLower, 'hev') || str_contains($paramLower, 's/co') || str_contains($paramLower, 'coi')) {
        $maxScale = max(3.0, ceil($numVal * 2.0) / 2.0);
        $ticks = [$maxScale, $maxScale - 0.5, $maxScale - 1.0, $maxScale - 1.5, $maxScale - 2.0, $maxScale - 2.5, 0.0];
        $isHigh = $numVal >= 1.0;
        $normalText = 'Non-Reactive < 1.00';
        $highText = 'Reactive > 1.00';
        $defaultSubText = "Non-Reactive (Negative) : < 1.00 S/CO\nGreyzone / Borderline : 0.90 - 1.10\nReactive (Positive) : > 1.00 S/CO";
    }
    // 22. Generic Fallback (Dynamic parse from reference range)
    else {
        $rangeNumbers = [];
        if (preg_match_all('/([0-9]+(?:\.[0-9]+)?)/', $rangeStr, $m)) {
            $rangeNumbers = array_map('floatval', $m[1]);
        }
        if (count($rangeNumbers) >= 2) {
            $refMin = $rangeNumbers[0];
            $refMax = $rangeNumbers[1];
            $maxScale = max($refMax * 1.35, $numVal * 1.25);
            $minScale = 0.0;
            $ticks = [$maxScale, $maxScale * 0.8, $maxScale * 0.6, $maxScale * 0.4, $maxScale * 0.2, 0.0];
            $isLow = $numVal < $refMin;
            $isHigh = $numVal > $refMax;
            $normalText = number_format($refMin, 2) . ' - ' . number_format($refMax, 2);
            $lowText = '< ' . number_format($refMin, 2);
            $highText = '> ' . number_format($refMax, 2);
            $defaultSubText = "Reference Range : " . $rangeStr;
        } else {
            $maxScale = max(100.0, $numVal * 1.35);
            $minScale = 0.0;
            $ticks = [$maxScale, $maxScale * 0.75, $maxScale * 0.5, $maxScale * 0.25, 0.0];
            $isHigh = $flagLower === 'h';
            $isLow = $flagLower === 'l';
            $normalText = 'Ref: ' . $rangeStr;
            $highText = 'Elevated';
            $defaultSubText = "Reference Interval : " . $rangeStr;
        }
    }

    // Flag overrides
    if ($flagLower === 'h' || $flagLower === 'critical') {
        $isHigh = true;
        $isLow = false;
    } elseif ($flagLower === 'l') {
        $isLow = true;
        $isHigh = false;
    }

    if ($isHigh) {
        $themeColor = '#dc2626'; // Red
    } elseif ($isLow) {
        $themeColor = '#d97706'; // Amber / Orange
    } else {
        $themeColor = '#16a34a'; // Green
    }

    $span = max(0.001, $maxScale - $minScale);
    $pct = max(6.0, min(94.0, (($numVal - $minScale) / $span) * 100.0));

    // Criteria html
    $actualSubText = $subText !== '' ? $subText : $defaultSubText;
    $criteriaLines = array_filter(array_map('trim', explode("\n", html_entity_decode($actualSubText, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
    $criteriaHtml = '';
    foreach ($criteriaLines as $cl) {
        $criteriaHtml .= '<div>' . e($cl) . '</div>';
    }

    // Grid lines html
    $gridCount = count($ticks);
    $gridLinesHtml = '';
    foreach ($ticks as $idx => $t) {
        $topPct = $gridCount > 1 ? round(($idx / ($gridCount - 1)) * 100, 2) : 0;
        $tFormatted = number_format((float)$t, 2);
        $gridLinesHtml .= '<div style="position: absolute; left: 0; right: 0; top: ' . $topPct . '%; display: flex; align-items: center; pointer-events: none;">'
            . '<span style="width: 52px; text-align: right; font-size: 10px; color: #64748b; font-family: monospace; font-weight: 600; margin-right: 12px; flex-shrink: 0; transform: translateY(-50%);">' . $tFormatted . '</span>'
            . '<div style="flex: 1; height: 1px; background: #e2e8f0;"></div>'
            . '</div>';
    }

    // Legend html matching visual image
    if ($lowText !== '') {
        $legendHtml = '<span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#1e293b;"><span style="width:11px;height:11px;background:#16a34a;display:inline-block;"></span> Normal <i>' . e($normalText) . '</i></span>'
                    . '<span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#1e293b;"><span style="width:11px;height:11px;background:#d97706;display:inline-block;"></span> Low Level <i>' . e($lowText) . '</i></span>'
                    . '<span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#1e293b;"><span style="width:11px;height:11px;background:#dc2626;display:inline-block;"></span> High Level <i>' . e($highText) . '</i></span>';
    } else {
        $legendHtml = '<span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#1e293b;"><span style="width:11px;height:11px;background:#16a34a;display:inline-block;"></span> Normal <i>' . e($normalText) . '</i></span>'
                    . '<span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#1e293b;"><span style="width:11px;height:11px;background:#dc2626;display:inline-block;"></span> High Level <i>' . e($highText) . '</i></span>';
    }

    $paramNameEsc = e($paramName);
    $unitEsc = e($unit);

    $displayValBadge = (floor($numVal) == $numVal) ? (string)(int)$numVal : number_format($numVal, 1);
    $displayValRight = (floor($numVal) == $numVal && $numVal >= 10 && !str_contains($paramLower, 'ldl'))
        ? (string)(int)$numVal
        : (fmod($numVal, 1.0) == 0.0 && $numVal >= 10 && !str_contains($paramLower, 'ldl') ? (string)(int)$numVal : number_format($numVal, 2));

    return <<<HTML
<tr class="lab-report__gauge-row" style="border-bottom: 2px solid #cbd5e1; page-break-inside: avoid;">
    <td colspan="4" style="padding: 14px 4px 18px 4px !important;">
        <div style="display: grid; grid-template-columns: 28% 44% 28%; align-items: center; gap: 14px;">
            <!-- Column 1: Test Title & Clinical Interpretation -->
            <div>
                <div style="font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
                    {$paramNameEsc}
                </div>
                <div style="font-size: 11px; color: #334155; line-height: 1.45;">
                    {$criteriaHtml}
                </div>
            </div>

            <!-- Column 2: Vertical Ladder Chart & Reference Legend -->
            <div>
                <div style="position: relative; height: 130px; width: 100%; margin: 0 auto;">
                    <!-- Identifier on left (■ 2302) -->
                    <div style="position: absolute; left: 0; top: 50%; transform: translateY(-50%); font-size: 11px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 5px;">
                        <span style="display: inline-block; width: 6px; height: 6px; background: #000;"></span> 2302
                    </div>

                    <!-- Chart Grid (Ticks + Horizontal Gridlines) -->
                    <div style="margin-left: 65px; position: relative; height: 130px;">
                        {$gridLinesHtml}

                        <!-- Value Badge & Green Square Marker -->
                        <div style="position: absolute; left: 45%; bottom: {$pct}%; transform: translate(-50%, 50%); display: flex; flex-direction: column; align-items: center; z-index: 5;">
                            <span style="background: {$themeColor}; color: #ffffff; font-size: 11px; font-weight: 800; padding: 2px 7px; border-radius: 2px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); white-space: nowrap; font-family: sans-serif;">
                                {$displayValBadge}
                            </span>
                            <span style="width: 7px; height: 7px; background: #16a34a; margin-top: 2px; display: block;"></span>
                        </div>
                    </div>
                </div>

                <!-- Bottom Legend matching image -->
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; font-weight: 700; color: #1e293b; margin-top: 10px; padding-top: 4px;">
                    {$legendHtml}
                </div>
            </div>

            <!-- Column 3: Large Prominent Value Display -->
            <div style="text-align: right; padding-right: 12px;">
                <div style="font-size: 40px; font-weight: 800; color: {$themeColor}; line-height: 1; letter-spacing: -0.5px;">
                    {$displayValRight} <span style="font-size: 14px; font-weight: 600; color: {$themeColor}; margin-left: 2px;">{$unitEsc}</span>
                </div>
            </div>
        </div>
    </td>
</tr>
HTML;
}

/**
 * Build result table rows for one report page.
 */
function build_report_result_rows(array $resultLines, bool $isTwoColumn = false): string
{
    $rows = '';
    $currentTestTitle = null;
    $currentSection = null;
    $showSirLegend = false;
    $colspan = $isTwoColumn ? 2 : 4;

    // Cache or collect test-level methodologies
    $testMethodologies = [];
    foreach ($resultLines as $l) {
        $tt = trim((string)($l['test_title'] ?? ''));
        if ($tt !== '' && !empty($l['methodology']) && !isset($testMethodologies[$tt])) {
            $testMethodologies[$tt] = trim((string)$l['methodology']);
        }
    }

    $getMethodology = static function (string $title) use (&$testMethodologies): string {
        if ($title === '') {
            return '';
        }
        if (array_key_exists($title, $testMethodologies)) {
            return $testMethodologies[$title];
        }
        $meta = test_repo()->findByCode($title);
        $m = trim((string)($meta['methodology'] ?? ''));
        $testMethodologies[$title] = $m;
        return $m;
    };

    $renderMethodology = static function (string $text) use ($colspan): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $paras = preg_split('/\n\s*\n/', $text) ?: [$text];
        $methodologyHtml = '';
        foreach ($paras as $para) {
            $para = trim((string)$para);
            if ($para === '') continue;
            if (preg_match('/^([A-Za-z0-9\s\/\-_]+:)(.*)$/s', $para, $pm)) {
                $titlePart = trim($pm[1]);
                $contentPart = trim($pm[2] ?? '');
                $methodologyHtml .= '<div class="lab-report__methodology-heading">' . e($titlePart) . '</div>';
                if ($contentPart !== '') {
                    $methodologyHtml .= '<div class="lab-report__methodology-p">' . nl2br(e($contentPart)) . '</div>';
                }
            } else {
                $methodologyHtml .= '<div class="lab-report__methodology-p">' . nl2br(e($para)) . '</div>';
            }
        }
        if ($methodologyHtml === '') {
            return '';
        }
        return '<tr class="lab-report__methodology-row">
            <td colspan="' . $colspan . '">
                <div class="lab-report__methodology-box">
                    ' . $methodologyHtml . '
                </div>
            </td>
        </tr>';
    };

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
            // Render previous test's methodology before starting next test
            if ($currentTestTitle !== null) {
                $prevM = $getMethodology($currentTestTitle);
                if ($prevM !== '') {
                    $rows .= $renderMethodology($prevM);
                }
            }
            $currentTestTitle = $testTitle;
            $currentSection = null;
            $needsHeading = ($testRowCounts[$testTitle] ?? 0) > 1
                || strcasecmp($testTitle, $paramRaw) !== 0;
            if ($needsHeading) {
                $rows .= '<tr class="lab-report__test-head"><td colspan="' . $colspan . '">'
                    . '<div class="lab-report__panel-banner"><strong>' . e($testTitle) . '</strong></div>'
                    . '</td></tr>';
            }
        }

        if ($section !== '' && $section !== $currentSection) {
            $currentSection = $section;
            $rows .= '<tr class="lab-report__section-head"><td colspan="' . $colspan . '"><span>' . e($section) . '</span></td></tr>';
            if (preg_match('/antibiotic|sensitivity|culture/i', $section)) {
                $showSirLegend = true;
            }
        }

        $flagRaw = strtolower((string)($line['flag'] ?? ''));
        $resClass = 'lab-report__result';
        $arrow = '';
        $resultRaw = trim((string)($line['result'] ?? ''));
        if ($resultRaw === '-' || $resultRaw === '—' || $resultRaw === '') {
            $resVal = '—';
            $isPending = false;
        } else {
            $resVal = e($resultRaw);
            $isPending = strcasecmp($resultRaw, 'Pending') === 0;
        }

        if ($flagRaw === 'l') {
            $resClass .= ' lab-report__result--low';
            $arrow = ' <span class="lab-report__arrow lab-report__arrow--low">&darr;</span>';
        } elseif ($flagRaw === 'h') {
            $resClass .= ' lab-report__result--high';
            $arrow = ' <span class="lab-report__arrow lab-report__arrow--high">&uarr;</span>';
        } elseif ($flagRaw === 'critical') {
            $resClass .= ' lab-report__result--critical';
            $arrow = ' <span class="lab-report__arrow lab-report__arrow--critical">!</span>';
        } elseif (!$isPending && is_numeric($resultRaw)) {
            // In-range numeric result → green marker
            $arrow = ' <span class="lab-report__arrow lab-report__arrow--normal">&#10003;</span>';
        }

        $unitVal = e($line['unit'] ?? '—');
        $rangeVal = e($line['range'] ?? '—');
        $paramName = e($paramRaw);

        $resNoteHtml = '';
        $resNote = trim((string)($line['result_note'] ?? ''));
        if ($resNote !== '') {
            $resNoteHtml = '<div class="lab-report__result-note">' . nl2br(e($resNote)) . '</div>';
        }

        if (!empty($line['is_imaging'])) {
            $studyTitle = $testTitle !== '' ? $testTitle : $paramRaw;
            $deptBanner = !empty($line['department_title']) ? $line['department_title'] : 'DEPARTMENT OF RADIOLOGY & DIAGNOSTIC IMAGING';
            $imagingText = ($resultRaw !== '' && $resultRaw !== '-' && $resultRaw !== '—') ? $resultRaw : 'Clinical imaging study recorded. Interpretation / report pending.';
            $rows .= '<tr class="lab-report__imaging-row">
                <td colspan="' . $colspan . '" style="padding: 16px 8px; border-bottom: none;">
                    <div style="text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 6px; margin-bottom: 16px;">
                        <strong style="font-size: 18px; text-transform: uppercase; letter-spacing: 1.2px; color: #0f172a;">' . e($studyTitle) . '</strong>
                        <div style="font-size: 10.5px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px;">' . e($deptBanner) . '</div>
                    </div>
                    <div style="font-size: 13.5px; line-height: 1.8; white-space: pre-wrap; padding: 6px 4px; font-family: var(--report-body-font, inherit); color: #0f172a; min-height: 280px;">' . e($imagingText) . '</div>
                </td>
            </tr>';
            continue;
        }

        $isGraphEligible = is_graph_eligible_test($testTitle, $paramRaw);
        $hasNumericVal = is_numeric($resultRaw) || (preg_match('/^([0-9]+(?:\.[0-9]+)?)/', $resultRaw, $nm) && (float)$nm[1] > 0);

        if ($isGraphEligible && $hasNumericVal && !$isTwoColumn) {
            $parsedNum = is_numeric($resultRaw) ? (float)$resultRaw : (float)$nm[1];
            $subText = (string)($line['sub_table'] ?? '');
            $rows .= render_parameter_gauge(
                $paramRaw,
                $parsedNum,
                (string)($line['unit'] ?? ''),
                (string)($line['range'] ?? ''),
                $flagRaw,
                $subText
            );
            continue;
        }

        if ($isTwoColumn) {
            // Microbiology / Narrative Pathology without Reference Ranges or Units
            $rows .= '<tr>
                <td class="lab-report__param-cell" style="width: 48%; font-weight: 700;">' . $paramName . '</td>
                <td class="' . $resClass . '" style="width: 52%; text-align: left;">' . $resVal . $arrow . $resNoteHtml . '</td>
            </tr>';
        } else {
            // Multi-column pathology table
            if (empty($line['has_ref_range'])) {
                // If a test without reference ranges (e.g. Microbiology) is in a general lab table, span result over range/unit
                $rows .= '<tr>
                    <td class="lab-report__param-cell">' . $paramName . '</td>
                    <td colspan="3" class="' . $resClass . '" style="text-align: left;">' . $resVal . $arrow . $resNoteHtml . '</td>
                </tr>';
            } else {
                $rows .= '<tr>
                    <td class="lab-report__param-cell">' . $paramName . '</td>
                    <td class="lab-report__range-cell">' . $rangeVal . '</td>
                    <td class="lab-report__unit-cell">' . $unitVal . '</td>
                    <td class="' . $resClass . '">' . $resVal . $arrow . $resNoteHtml . '</td>
                </tr>';
            }
        }

        if (!empty($line['sub_table'])) {
            $subText = trim((string)$line['sub_table']);
            $currentMeth = $getMethodology($testTitle);
            $isDuplicateOfTestMeth = $currentMeth !== '' && (
                $subText === $currentMeth
                || strcasecmp(substr($subText, 0, 50), substr($currentMeth, 0, 50)) === 0
            );

            if (!$isDuplicateOfTestMeth) {
                $isMethodology = preg_match('/methodolog|comment|interpretation|principle|note\s*:/i', $subText)
                    || strlen($subText) > 200
                    || preg_match('/\.\s+[A-Z]/', $subText);

                if ($isMethodology) {
                    $rows .= $renderMethodology($subText);
                } else {
                    $subLines = explode("\n", $subText);
                    $subRowsHtml = '';
                    foreach ($subLines as $sl) {
                        $parts = explode(':', $sl, 2);
                        if (count($parts) === 2) {
                            $subRowsHtml .= '<tr><td>' . e(trim($parts[0])) . '</td><td>' . e(trim($parts[1])) . '</td></tr>';
                        }
                    }
                    if ($subRowsHtml !== '') {
                        $subTitle = $isTwoColumn ? 'Antibiotic Sensitivity / Antimicrobial Susceptibility' : 'Reference Criteria';
                        $subHead1 = $isTwoColumn ? 'Antimicrobial Agent' : 'Criteria / Parameter';
                        $subHead2 = $isTwoColumn ? 'Susceptibility' : 'Reference Range';
                        $rows .= '<tr class="lab-report__subtable-row">
                            <td colspan="' . $colspan . '">
                                <div class="lab-report__subtable-wrap">
                                    <span class="lab-report__subtable-title">' . e($subTitle) . '</span>
                                    <table class="lab-report__subtable">
                                        <thead><tr><th>' . e($subHead1) . '</th><th>' . e($subHead2) . '</th></tr></thead>
                                        <tbody>' . $subRowsHtml . '</tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>';
                    }
                }
            }
        }
    }

    // Render methodology for the last test
    if ($currentTestTitle !== null) {
        $lastM = $getMethodology($currentTestTitle);
        if ($lastM !== '') {
            $rows .= $renderMethodology($lastM);
        }
    }

    if ($showSirLegend) {
        $rows .= '<tr class="lab-report__legend-row"><td colspan="' . $colspan . '">'
            . '<div class="lab-report__sir-legend">S = Sensitive &nbsp;|&nbsp; I = Intermediate &nbsp;|&nbsp; R = Resistant</div>'
            . '</td></tr>';
    }

    if ($rows === '') {
        $rows = '<tr><td colspan="' . $colspan . '" class="lab-report__empty">No tests on this entry yet.</td></tr>';
    }

    return $rows;
}

/**
 * One physical report sheet.
 * Page 1: full letterhead + patient info.
 * Page 2+: slim continuation bar only (no full patient block again).
 */
function render_report_sheet(
    array $settings,
    array $patient,
    array $resultLines,
    ?array $entry,
    string $reportTitle,
    array $signatories,
    string $specimen,
    int $pageIndex,
    int $totalPages,
    bool $forceBreakAfter
): string {
    $labAddress = e($settings['address'] ?? '');
    $labPhone = e($settings['phone'] ?? '');
    $labEmail = e($settings['email'] ?? '');
    $labNoRaw = (string)($entry['lab_no'] ?? '');

    $isAllImaging = !empty($resultLines) && array_reduce($resultLines, static function($carry, $l) {
        return $carry && !empty($l['is_imaging']);
    }, true);

    $isAllMicrobiology = !empty($resultLines) && array_reduce($resultLines, static function($carry, $l) {
        return $carry && !empty($l['is_microbiology']);
    }, true);

    $allNoRefRange = !empty($resultLines) && array_reduce($resultLines, static function($carry, $l) {
        return $carry && empty($l['has_ref_range']);
    }, true);

    $isTwoColumn = !$isAllImaging && ($isAllMicrobiology || $allNoRefRange);

    // Dynamic Sheet Department Title
    $deptTitles = array_unique(array_filter(array_column($resultLines, 'department_title')));
    if (count($deptTitles) === 1) {
        $sheetTitle = reset($deptTitles);
    } elseif ($isAllImaging) {
        $sheetTitle = 'DEPARTMENT OF RADIOLOGY & DIAGNOSTIC IMAGING';
    } elseif ($isAllMicrobiology) {
        $sheetTitle = 'DEPARTMENT OF MICROBIOLOGY';
    } elseif (!empty($deptTitles)) {
        $sheetTitle = 'DEPARTMENT OF LABORATORY MEDICINE';
    } else {
        $sheetTitle = $reportTitle;
    }
    $title = e(strtoupper($sheetTitle));

    $resultDate = e(format_date_report(null));
    $rows = build_report_result_rows($resultLines, $isTwoColumn);
    $pageLabel = 'Page ' . $pageIndex . ' of ' . max(1, $totalPages);
    $breakClass = $forceBreakAfter ? ' lab-report--page-break' : '';
    $isFirstPage = $pageIndex <= 1;
    $isLastPage = $pageIndex >= $totalPages;

    $letterhead = render_letterhead_image($settings);
    $logoImg = brand_logo('brand-logo brand-logo--report');

    $qrPayload = $labNoRaw !== ''
        ? (isset($_SERVER['HTTP_HOST'])
            ? (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
                . '://' . $_SERVER['HTTP_HOST']
                . '/track.php?lab_no=' . rawurlencode($labNoRaw))
            : $labNoRaw)
        : ($settings['name'] ?? 'Lab Report');
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=72x72&margin=0&data=' . rawurlencode($qrPayload);
    $qrHtml = '<div class="lab-report__qr"><img src="' . e($qrUrl) . '" alt="Report QR" width="56" height="56"></div>';

    $imgPos = \App\Repositories\SettingRepository::normalizeHeaderImagePosition(
        (string)($settings['header_image_position'] ?? 'left')
    );

    $layout = $settings['header_layout'] ?? null;
    if (is_string($layout) && $layout !== '') {
        $layout = json_decode($layout, true);
    }

    if (is_array($layout) && (!empty($layout['logo']) || isset($layout['canvas_h']))) {
        $canvasH = max(150, min(350, (int)($layout['canvas_h'] ?? 150)));
        $logoConf = $layout['logo'] ?? [];
        $logoXPct = isset($logoConf['x_pct']) ? (float)$logoConf['x_pct'] : round(((float)($logoConf['x'] ?? 15) / 760) * 100, 2);
        $logoY = max(0, (int)($logoConf['y'] ?? 10));
        $logoW = max(50, min(760, (int)($logoConf['w'] ?? 240)));
        $logoH = max(20, min(300, (int)($logoConf['h'] ?? 70)));

        $qrConf = $layout['qr'] ?? [];
        $qrXPct = isset($qrConf['x_pct']) ? (float)$qrConf['x_pct'] : round(((float)($qrConf['x'] ?? 680) / 760) * 100, 2);
        $qrY = max(0, (int)($qrConf['y'] ?? 10));
        $qrSize = max(35, min(200, (int)($qrConf['size'] ?? 56)));

        $logoInner = $letterhead !== ''
            ? $letterhead
            : '<div class="lab-report__mark lab-report__mark--logo" style="width:100%;height:100%;display:flex;align-items:center;">' . $logoImg . '</div>';

        $brandBlock = '<div class="lab-report__top lab-report__top--custom" style="height:' . $canvasH . 'px;min-height:' . $canvasH . 'px;">'
            . '<div class="lab-report__banner lab-report__banner--custom" style="left:' . $logoXPct . '%;top:' . $logoY . 'px;width:' . $logoW . 'px;height:' . $logoH . 'px;">'
            . $logoInner
            . '</div>'
            . '<div class="lab-report__qr lab-report__qr--custom" style="left:' . $qrXPct . '%;top:' . $qrY . 'px;width:' . $qrSize . 'px;height:' . $qrSize . 'px;">'
            . '<img src="' . e($qrUrl) . '" alt="Report QR" width="' . $qrSize . '" height="' . $qrSize . '">'
            . '</div>'
            . '</div>';
    } else {
        if ($letterhead !== '') {
            // Full-width letterhead banner; QR sits on the right over/beside it
            $brandBlock = '<div class="lab-report__top lab-report__top--banner lab-report__top--img-' . e($imgPos) . '" style="min-height:150px;">'
                . '<div class="lab-report__banner">' . $letterhead . '</div>'
                . $qrHtml
                . '</div>';
        } else {
            $brandBlock = '<div class="lab-report__top lab-report__top--img-' . e($imgPos) . '" style="min-height:150px;">'
                . '<div class="lab-report__letterhead">'
                . '<div class="lab-report__brand lab-report__brand--img-' . e($imgPos) . ' lab-report__brand--logo-only">'
                . '<div class="lab-report__mark lab-report__mark--logo">' . $logoImg . '</div>'
                . '</div>'
                . '</div>'
                . $qrHtml
                . '</div>';
        }
    }
    $metaBlock = build_patient_document_meta($patient, $entry, 'report');
    $headerHtml = <<<HTML
    <header class="lab-report__header">
        {$brandBlock}
        {$metaBlock}
        <div class="lab-report__title-wrap">
            <h2 class="lab-report__title">{$title}</h2>
        </div>
    </header>
    HTML;

    // No "Note: lab values..." line — only interpret disclaimer + Get well soon footer
    $noteHtml = '';

    $footerBlock = '';
    $credit = software_credit_footer(true);
    $addrLine = trim($labAddress . ($labPhone !== '' ? '  ·  ' . $labPhone : '') . ($labEmail !== '' ? '  ·  ' . $labEmail : ''));
    $contactBar = $addrLine !== '' ? '<div class="lab-report__contact-bar">' . $addrLine . '</div>' : '';
    $signatoriesHtml = render_report_signatories($signatories);
    $disclaimer = '<p class="lab-report__disclaimer">All results should be interpreted and correlated by a physician. Electronically varified report - not valid for legal proceedings unless stamped®</p>';
    $customFooterHtml = '';
    if (!empty($settings['has_footer_image']) && !empty($settings['footer_image_url'])) {
        $fLayout = $settings['footer_layout'] ?? null;
        if (is_string($fLayout) && $fLayout !== '') {
            $fLayout = json_decode($fLayout, true);
        }
        $fCanvasH = max(35, min(220, (int)($fLayout['canvas_h'] ?? 70)));
        $fImgConf = $fLayout['image'] ?? [];
        $fXPct = isset($fImgConf['x_pct']) ? (float)$fImgConf['x_pct'] : 0.0;
        $fY = max(0, (int)($fImgConf['y'] ?? 0));
        $fWPct = isset($fImgConf['w_pct']) ? (float)$fImgConf['w_pct'] : 100.0;
        $fH = max(20, (int)($fImgConf['h'] ?? 60));

        $customFooterHtml = '<div class="lab-report__footer-custom" style="position:relative;height:' . $fCanvasH . 'px;min-height:' . $fCanvasH . 'px;">'
            . '<div style="position:absolute;left:' . $fXPct . '%;top:' . $fY . 'px;width:' . $fWPct . '%;height:' . $fH . 'px;display:flex;align-items:center;">'
            . '<img src="' . e($settings['footer_image_url']) . '" alt="Footer Letterhead" style="max-height:100%;max-width:100%;object-fit:contain;">'
            . '</div></div>';
    }

    $reportFontKey = $settings['report_font'] ?? 'times_bold_italic';
    $allFonts = supported_document_fonts();
    $fontConf = $allFonts[$reportFontKey] ?? $allFonts['times_bold_italic'];
    $rFamily = $fontConf['family'];
    $rWeight = $fontConf['weight'];
    $rStyle = $fontConf['style'];

    $reportFontSizeKey = $settings['report_font_size'] ?? 'normal';
    $allFontSizes = supported_document_font_sizes();
    $fontSizeConf = $allFontSizes[$reportFontSizeKey] ?? $allFontSizes['normal'];
    $rSize = $fontSizeConf['size'];
    $rLineHeight = $fontSizeConf['line_height'];

    $styleAttr = htmlspecialchars("--report-body-font: {$rFamily}; --report-body-weight: {$rWeight}; --report-body-style: {$rStyle}; --report-body-size: {$rSize};", ENT_COMPAT, 'UTF-8');

    $reportFontStyle = <<<CSS
<style>
.lab-report .lab-report__body,
.lab-report .lab-report__body *,
.lab-report .lab-report__table,
.lab-report .lab-report__table *,
.lab-report .lab-report__table thead th,
.lab-report .lab-report__table tbody td,
.lab-report .lab-report__param-cell,
.lab-report .lab-report__range-cell,
.lab-report .lab-report__unit-cell,
.lab-report .lab-report__result,
.lab-report .lab-report__result *,
.lab-report .lab-report__test-head td,
.lab-report .lab-report__sir-legend,
.lab-report .lab-report__sir-legend *,
.lab-report .lab-report__methodology-box,
.lab-report .lab-report__methodology-box *,
.lab-report .lab-report__methodology-p,
.lab-report .lab-report__comments,
.lab-report .lab-report__comments * {
    font-family: {$rFamily} !important;
}
.lab-report .lab-report__table tbody td,
.lab-report .lab-report__param-cell,
.lab-report .lab-report__range-cell,
.lab-report .lab-report__unit-cell,
.lab-report .lab-report__result {
    font-weight: {$rWeight} !important;
    font-style: {$rStyle} !important;
    font-size: {$rSize} !important;
    line-height: {$rLineHeight} !important;
}
</style>
CSS;

    $tableHeadHtml = '';
    if (!$isAllImaging) {
        if ($isTwoColumn) {
            $tableHeadHtml = <<<THEAD
                <thead>
                    <tr>
                        <th class="lab-report__th-test" style="width: 48%;">TEST / INVESTIGATION</th>
                        <th class="lab-report__th-result" style="width: 52%; text-align: left;">OBSERVATION / RESULT<br><span class="lab-report__result-date">{$resultDate}</span></th>
                    </tr>
                </thead>
THEAD;
        } else {
            $tableHeadHtml = <<<THEAD
                <thead>
                    <tr>
                        <th class="lab-report__th-test">TEST</th>
                        <th class="lab-report__th-range">REFERENCE RANGE</th>
                        <th class="lab-report__th-unit">UNIT</th>
                        <th class="lab-report__th-result">RESULT<br><span class="lab-report__result-date">{$resultDate}</span></th>
                    </tr>
                </thead>
THEAD;
        }
    }

    return <<<HTML
    <div class="print-area lab-report{$breakClass}" style="{$styleAttr}">
        {$reportFontStyle}
        {$headerHtml}

        <section class="lab-report__body">
            <table class="lab-report__table">
                {$tableHeadHtml}
                <tbody>{$rows}</tbody>
            </table>
            {$noteHtml}
        </section>

        <footer class="lab-report__footer">
            <div class="lab-report__footer-branding">
                {$customFooterHtml}
                {$disclaimer}
                {$footerBlock}
                {$signatoriesHtml}
                {$contactBar}
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
        $deptMeta = get_department_meta($label, (string)($test['category'] ?? ''));
        $vialText = $deptMeta['name'];
        if ($deptMeta['id'] === 'lab') {
            $sampleType = trim((string)($test['sample_type'] ?? ''));
            $vialText = $sampleType !== '' ? $sampleType : 'Lab';
        }

        $subtotal += $price;
        $items[] = [
            'name' => $label,
            'price' => $price,
            'vial' => $vialText,
            'department' => $deptMeta['name'],
            'dept_id' => $deptMeta['id'],
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
    $billFontKey = $settings['bill_font'] ?? 'times_bold_italic';
    $allFonts = supported_document_fonts();
    $bFontConf = $allFonts[$billFontKey] ?? $allFonts['times_bold_italic'];
    $bFamily = $bFontConf['family'];
    $bWeight = $bFontConf['weight'];
    $bStyle = $bFontConf['style'];

    $billFontSizeKey = $settings['bill_font_size'] ?? 'normal';
    $allFontSizes = supported_document_font_sizes();
    $bSizeConf = $allFontSizes[$billFontSizeKey] ?? $allFontSizes['normal'];
    $bSize = $bSizeConf['size'];
    $bLineHeight = $bSizeConf['line_height'];

    $bStyleAttr = htmlspecialchars("--bill-body-font: {$bFamily}; --bill-body-weight: {$bWeight}; --bill-body-style: {$bStyle}; --bill-body-size: {$bSize};", ENT_COMPAT, 'UTF-8');

    $billFontStyle = <<<CSS
<style>
.lab-bill .lab-bill__table,
.lab-bill .lab-bill__table *,
.lab-bill .lab-bill__table th,
.lab-bill .lab-bill__table td,
.lab-bill .lab-bill__table tbody td,
.lab-bill .lab-bill__table thead th,
.lab-bill .lab-bill__totals,
.lab-bill .lab-bill__totals *,
.lab-bill .lab-bill__totals td {
    font-family: {$bFamily} !important;
}
.lab-bill .lab-bill__table td,
.lab-bill .lab-bill__totals td {
    font-weight: {$bWeight} !important;
    font-style: {$bStyle} !important;
    font-size: {$bSize} !important;
    line-height: {$bLineHeight} !important;
}
</style>
CSS;

    return <<<HTML
    <div class="print-area lab-bill" style="{$bStyleAttr}">
        {$billFontStyle}
        {$headerHtml}
        {$metaBlock}

        <table class="lab-bill__table">
            <thead>
                <tr>
                    <th class="lab-bill__c-vial">Dept / Vial</th>
                    <th class="lab-bill__c-test">Test / Investigation</th>
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
