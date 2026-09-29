<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';
$userName = current_user()['name'] ?? 'Staff';

/**
 * @return array{ok:bool, binary:?string, mime:?string, error:?string}
 */
function read_waste_image(string $field): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
        return ['ok' => true, 'binary' => null, 'mime' => null, 'error' => null];
    }
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'binary' => null, 'mime' => null, 'error' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'binary' => null, 'mime' => null, 'error' => ucfirst($field) . ' upload failed.'];
    }
    if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
        return ['ok' => false, 'binary' => null, 'mime' => null, 'error' => ucfirst($field) . ' must be under 3 MB.'];
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'binary' => null, 'mime' => null, 'error' => 'Invalid ' . $field . ' upload.'];
    }
    $info = @getimagesize($tmp);
    if ($info === false) {
        return ['ok' => false, 'binary' => null, 'mime' => null, 'error' => ucfirst($field) . ' must be a valid image.'];
    }
    $mime = $info['mime'] ?? 'image/jpeg';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return ['ok' => false, 'binary' => null, 'mime' => null, 'error' => ucfirst($field) . ' must be JPG, PNG, or WebP.'];
    }
    $binary = file_get_contents($tmp);
    if ($binary === false) {
        return ['ok' => false, 'binary' => null, 'mime' => null, 'error' => 'Could not read ' . $field . '.'];
    }
    return ['ok' => true, 'binary' => $binary, 'mime' => $mime, 'error' => null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    if ($action === 'delete') {
        $id = trim((string)($_POST['id'] ?? ''));
        $ok = $id !== '' && waste_repo()->delete($id, $orgId);
        $message = $ok ? flash_success('Waste record deleted.') : flash_error('Could not delete waste record.');
    } else {
        $title = trim((string)($_POST['title'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $recordDate = trim((string)($_POST['record_date'] ?? date('Y-m-d')));
        $mou = read_waste_image('mou_image');
        $slip = read_waste_image('slip_image');

        if ($title === '') {
            $message = flash_error('Title is required.');
        } elseif (!$mou['ok']) {
            $message = flash_error((string)$mou['error']);
        } elseif (!$slip['ok']) {
            $message = flash_error((string)$slip['error']);
        } elseif ($mou['binary'] === null && $slip['binary'] === null) {
            $message = flash_error('Upload at least one image (MOU or Slip/Receipt).');
        } else {
            $ok = waste_repo()->create([
                'organization_id' => $orgId,
                'title' => $title,
                'notes' => $notes,
                'record_date' => $recordDate !== '' ? $recordDate : date('Y-m-d'),
                'mou_image' => $mou['binary'],
                'mou_image_mime' => $mou['mime'],
                'slip_image' => $slip['binary'],
                'slip_image_mime' => $slip['mime'],
                'created_by' => $userName,
            ]);
            $message = $ok
                ? flash_success('Waste record saved with uploaded image(s).')
                : flash_error('Could not save waste record.');
        }
    }
}

$records = waste_repo()->getAll($orgId);
$rows = [];
foreach ($records as $r) {
    $id = urlencode((string)$r['id']);
    $mouLink = !empty($r['has_mou'])
        ? '<a href="/portals/main-lab/waste-image.php?id=' . $id . '&type=mou" target="_blank" class="btn btn-secondary text-xs"><i class="fa-solid fa-image mr-1"></i> MOU</a>'
        : '<span class="text-slate-400 text-xs">—</span>';
    $slipLink = !empty($r['has_slip'])
        ? '<a href="/portals/main-lab/waste-image.php?id=' . $id . '&type=slip" target="_blank" class="btn btn-secondary text-xs"><i class="fa-solid fa-receipt mr-1"></i> Slip</a>'
        : '<span class="text-slate-400 text-xs">—</span>';

    $rows[] = [
        e(format_date((string)$r['record_date'])),
        '<strong>' . e((string)$r['title']) . '</strong>',
        e((string)($r['notes'] ?: '—')),
        e((string)($r['created_by'] ?? '—')),
        '<div class="flex flex-wrap gap-2">' . $mouLink . $slipLink . '</div>',
        '<form method="post" onsubmit="return confirm(\'Delete this waste record?\');">'
            . '<input type="hidden" name="action" value="delete">'
            . '<input type="hidden" name="id" value="' . e((string)$r['id']) . '">'
            . '<button type="submit" class="btn btn-secondary text-xs text-rose-700"><i class="fa-solid fa-trash mr-1"></i> Delete</button>'
            . '</form>',
    ];
}

$content = page_header(
    'Waste Record',
    'Log biomedical / waste disposal with MOU and receipt/slip photo uploads.'
);
$content .= $message;
$content .= card(
    panel_head('Add waste record') .
    '<form method="post" enctype="multipart/form-data" class="space-y-4 p-4 sm:p-6">' .
    '<input type="hidden" name="action" value="create">' .
    form_field('Title', 'title', 'text', null, 'e.g. Monthly incineration batch') .
    form_field('Date', 'record_date', 'date', date('Y-m-d')) .
    textarea_field('Notes', 'notes', null, 'Vendor, quantity, reference no.', 3, true) .
    '<div class="grid gap-4 sm:grid-cols-2">' .
    '<div><label class="field-label" for="mou_image">MOU image</label>' .
    '<input type="file" id="mou_image" name="mou_image" accept="image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-teal-700 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-teal-800">' .
    '<p class="mt-1 text-xs text-slate-500">Upload signed MOU photo (JPG/PNG/WebP, max 3 MB).</p></div>' .
    '<div><label class="field-label" for="slip_image">Slip / Receipt image</label>' .
    '<input type="file" id="slip_image" name="slip_image" accept="image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-teal-700 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-teal-800">' .
    '<p class="mt-1 text-xs text-slate-500">Upload disposal slip or receipt photo.</p></div>' .
    '</div>' .
    btn_submit('Save waste record', '', 'fa-solid fa-cloud-arrow-up') .
    '</form>'
);

$content .= '<div class="mt-4">' . card(
    panel_head('Saved records (' . count($records) . ')') .
    (count($rows) > 0
        ? data_table(['Date', 'Title', 'Notes', 'Saved by', 'Images', ''], $rows)
        : '<p class="p-4 text-sm text-slate-500">No waste records yet.</p>'),
    'overflow-hidden'
) . '</div>';

render_page('Waste Record', 'main-lab', 'waste-record', $content);
