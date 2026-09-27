<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
$orgId = current_user()['organization_id'] ?? 'ORG-001';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_signatories') {
        $sigs = [];
        for ($i = 0; $i < 5; $i++) {
            $sigs[] = [
                'name' => $_POST['sig_name'][$i] ?? '',
                'qualifications' => $_POST['sig_quals'][$i] ?? '',
                'designation' => $_POST['sig_desig'][$i] ?? '',
            ];
        }
        $ok = setting_repo()->saveSignatories($orgId, $sigs);
        $message = $ok ? flash_success('Signatories saved successfully.') : flash_error('Could not save signatories.');
    } else {
        $result = save_branding_request($orgId);
        $message = $result['ok'] ? flash_success($result['message']) : flash_error($result['message']);
    }
}

$s = branding_settings($orgId);
$signatories = setting_repo()->getSignatories($orgId);

$content = page_header(
    'Header / Footer',
    'Lab letterhead for bills and reports (same settings as Admin → Client Branding).'
);
$content .= $message;
$content .= '<div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">'
    . '<strong>Same branding for everyone.</strong> Not a separate staff letterhead — edits here update the shared lab header/footer used on all printouts.'
    . '</div>';
$content .= card(
    '<form method="post" enctype="multipart/form-data" class="space-y-4 p-4 sm:p-6">' .
    '<input type="hidden" name="action" value="save_branding">' .
    form_field('Lab name', 'name', 'text', $s['name']) .
    form_field('Address', 'address', 'text', $s['address']) .
    form_field('Phone', 'phone', 'text', $s['phone']) .
    form_field('Email', 'email', 'email', $s['email']) .
    branding_header_image_field($s) .
    form_field('Report header', 'header', 'text', $s['header']) .
    form_field('Report footer', 'footer', 'text', $s['footer']) .
    form_field('Bill header (optional)', 'bill_header', 'text', $s['bill_header'], '', true) .
    form_field('Bill footer (optional)', 'bill_footer', 'text', $s['bill_footer'], '', true) .
    form_field('Logo text', 'logo', 'text', $s['logo_text']) .
    '<div class="flex flex-wrap gap-2">' .
    btn_submit('Save branding') .
    btn_secondary('/portals/main-lab/reports/preview.php', 'Preview sample report') .
    '</div></form>'
);

$sigFormFields = '';
for ($i = 0; $i < 5; $i++) {
    $sig = $signatories[$i] ?? ['name' => '', 'qualifications' => '', 'designation' => ''];
    $sigFormFields .= '<div class="p-4 border border-slate-200 rounded-lg bg-slate-50 space-y-3">';
    $sigFormFields .= '<h3 class="text-sm font-semibold text-slate-700">Slot ' . ($i + 1) . '</h3>';
    $sigFormFields .= form_field('Doctor Name', 'sig_name[]', 'text', $sig['name'], 'e.g. Dr. John Doe', true);
    
    // textarea replacement since textarea_field might not exist or work flawlessly
    $sigFormFields .= '<div><label class="field-label">Qualifications <span class="text-slate-400 font-normal">(optional)</span></label>';
    $sigFormFields .= '<textarea name="sig_quals[]" rows="2" class="field" placeholder="e.g. M.B.B.S, M.Phil">' . e($sig['qualifications']) . '</textarea></div>';
    
    $sigFormFields .= form_field('Designation', 'sig_desig[]', 'text', $sig['designation'], 'e.g. Consultant Pathologist', true);
    $sigFormFields .= '</div>';
}

$content .= '<div class="mt-6"></div>';
$content .= card(
    panel_head('Report Footer — Doctor Signatories', 'Editable sign-off boxes that appear on every printed report') .
    '<form method="post" class="space-y-4 p-4 sm:p-6">' .
    '<input type="hidden" name="action" value="save_signatories">' .
    '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">' .
    $sigFormFields .
    '</div>' .
    '<div class="mt-4">' . btn_submit('Save signatories') . '</div>' .
    '</form>',
    'overflow-hidden'
);

render_page('Report Template', 'main-lab', 'settings-report', $content);
