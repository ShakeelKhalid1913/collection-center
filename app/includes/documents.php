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

            $lines[] = [
                'test_title' => $tTitle,
                'section' => ($pCount === 0) ? '' : ($r['section'] ?? ''),
                'test' => $paramName,
                'result' => ($r['value'] !== null && $r['value'] !== '') ? $r['value'] : 'Pending',
                'unit' => ($r['unit'] !== '' && $r['unit'] !== '—') ? $r['unit'] : ($tObj['unit'] ?? '—'),
                'range' => ($r['reference_range'] ?: ($r['normal_value'] ?? '')) ?: ($tObj['reference_value'] ?? $tObj['normal_value'] ?? $tObj['normal_range'] ?? '—'),
                'sub_table' => ($pCount === 0) ? '' : ($r['sub_table'] ?? ''),
                'result_note' => ($pCount === 0) ? '' : ($r['result_note'] ?? ''),
                'methodology' => $testMethodologies[$tTitle] ?? '',
                'flag' => $r['flag'] ?? '',
                'print_page' => max(1, (int)($r['print_page'] ?? 1)),
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
                    'print_page' => 1,
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
    $pages = [];
    foreach ($resultLines as $line) {
        $pageNo = max(1, (int)($line['print_page'] ?? 1));
        $pages[$pageNo][] = $line;
    }
    if ($pages === []) {
        $pages[1] = [];
    }
    ksort($pages, SORT_NUMERIC);
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
 * Build result table rows for one report page.
 */
function build_report_result_rows(array $resultLines): string
{
    $rows = '';
    $currentTestTitle = null;
    $currentSection = null;
    $showSirLegend = false;

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

    $renderMethodology = static function (string $text): string {
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
            <td colspan="4">
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
                $rows .= '<tr class="lab-report__test-head"><td colspan="4">'
                    . '<div class="lab-report__panel-banner"><strong>' . e($testTitle) . '</strong></div>'
                    . '</td></tr>';
            }
        }

        if ($section !== '' && $section !== $currentSection) {
            $currentSection = $section;
            $rows .= '<tr class="lab-report__section-head"><td colspan="4"><span>' . e($section) . '</span></td></tr>';
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

        $rows .= '<tr>
            <td class="lab-report__param-cell">' . $paramName . '</td>
            <td class="lab-report__range-cell">' . $rangeVal . '</td>
            <td class="lab-report__unit-cell">' . $unitVal . '</td>
            <td class="' . $resClass . '">' . $resVal . $arrow . $resNoteHtml . '</td>
        </tr>';

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
                        $rows .= '<tr class="lab-report__subtable-row">
                            <td colspan="4">
                                <div class="lab-report__subtable-wrap">
                                    <span class="lab-report__subtable-title">Reference Criteria</span>
                                    <table class="lab-report__subtable">
                                        <thead><tr><th>Criteria / Parameter</th><th>Reference Range</th></tr></thead>
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
        $rows .= '<tr class="lab-report__legend-row"><td colspan="4">'
            . '<div class="lab-report__sir-legend">S = Sensitive &nbsp;|&nbsp; I = Intermediate &nbsp;|&nbsp; R = Resistant</div>'
            . '</td></tr>';
    }

    if ($rows === '') {
        $rows = '<tr><td colspan="4" class="lab-report__empty">No tests on this entry yet.</td></tr>';
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
    $title = e(strtoupper($reportTitle));
    $resultDate = e(format_date_report(null));
    $rows = build_report_result_rows($resultLines);
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

    return <<<HTML
    <div class="print-area lab-report{$breakClass}">
        {$headerHtml}

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
