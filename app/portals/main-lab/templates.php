<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$userId = current_user()['id'] ?? 'admin';
$message = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $delId = trim((string)$_GET['delete']);
    if (template_repo()->delete($delId, $orgId)) {
        header('Location: /portals/main-lab/templates.php?deleted=1');
        exit;
    }
    $message = flash_error('Could not delete template.');
}

if (isset($_GET['deleted'])) {
    $message = flash_success('Report template deleted successfully.');
}

// Handle Add / Edit Template
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title === '' || $content === '') {
        $message = flash_error('Template title and report content cannot be empty.');
    } else {
        $ok = template_repo()->create([
            'organization_id' => $orgId,
            'title' => $title,
            'department' => $_POST['department'] ?? 'General',
            'content' => $content,
            'is_private' => !empty($_POST['is_private']) ? 1 : 0,
            'created_by' => $userId,
        ]);

        if ($ok) {
            $message = flash_success("Report template '{$title}' saved successfully!");
        } else {
            $message = flash_error('Failed to save template.');
        }
    }
}

$deptFilter = trim($_GET['dept'] ?? '');
$templates = template_repo()->getAll($orgId, $userId, $deptFilter);

$departments = [
    '' => 'All Departments',
    'Radiology' => 'Radiology (Ultrasound & X-Ray)',
    'Histopathology' => 'Histopathology & Biopsy',
    'Cardiology' => 'Cardiology & ECG',
    'Microbiology' => 'Microbiology & Culture',
    'Hematology' => 'Hematology & Bone Marrow',
    'General' => 'General Clinical Pathology',
];

$addForm = card(
    panel_head('Create New Report Template', 'Save reusable diagnostic finding macros & private narrative templates') .
    '<form method="post" class="p-4 sm:p-6">' .
    '<div class="grid gap-4 sm:grid-cols-2">' .
    form_field('Template Title', 'title', 'text', null, 'e.g. Ultrasound Pelvis Normal Findings') .
    select_field('Department / Modality', 'department', array_slice($departments, 1), 'Radiology') .
    '</div>' .
    '<div class="mt-4">' .
    textarea_field('Template Content / Findings', 'content', null, "Organ findings, microscopic observations, diagnostic impression...\n\nExample:\nLIVER: Normal size and echogenicity.\nKIDNEYS: Normal cortical thickness.", 8) .
    '</div>' .
    '<div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-3 border-t border-slate-100">' .
    '<label class="inline-flex items-center gap-2 text-xs sm:text-sm font-semibold text-slate-700 cursor-pointer">' .
    '<input type="checkbox" name="is_private" value="1" class="w-4 h-4 rounded text-slate-900 focus:ring-slate-900 border-slate-300"> ' .
    '<span><i class="fa-solid fa-lock text-amber-500 mr-1"></i> Make Private Template (Only visible to my user account)</span>' .
    '</label>' .
    btn_submit('Save Template', '', 'fa-solid fa-bookmark') .
    '</div>' .
    '</form>'
);

$cardsHtml = '';
foreach ($templates as $tpl) {
    $isPriv = (int)($tpl['is_private'] ?? 0) === 1;
    $lockBadge = $isPriv
        ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-50 text-amber-700 border border-amber-200"><i class="fa-solid fa-lock"></i> Private</span>'
        : '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-teal-50 text-teal-700 border border-teal-200"><i class="fa-solid fa-globe"></i> Shared</span>';

    $deptBadge = '<span class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700">' . e($tpl['department']) . '</span>';
    $rawContent = htmlspecialchars($tpl['content'], ENT_QUOTES, 'UTF-8');
    $delUrl = '/portals/main-lab/templates.php?delete=' . urlencode($tpl['id']);

    $cardsHtml .= <<<HTML
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between hover:border-slate-300 transition-all">
        <div>
            <div class="flex items-center justify-between gap-2 mb-2">
                <div class="flex items-center gap-1.5 flex-wrap">
                    {$deptBadge}
                    {$lockBadge}
                </div>
                <a href="{$delUrl}" onclick="return confirm('Delete this template?')" class="text-slate-400 hover:text-rose-600 text-xs p-1" title="Delete">
                    <i class="fa-solid fa-trash-can"></i>
                </a>
            </div>
            <h3 class="text-sm font-extrabold text-slate-900 tracking-tight mb-2">{$tpl['title']}</h3>
            <div class="bg-slate-50 rounded-xl p-3 text-xs font-mono text-slate-700 whitespace-pre-wrap max-h-48 overflow-y-auto border border-slate-200/80 leading-relaxed">{$tpl['content']}</div>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
            <span class="text-[10px] text-slate-400 font-medium">Updated: {$tpl['updated_at']}</span>
            <button type="button" onclick="navigator.clipboard.writeText(`{$rawContent}`); alert('Template copied to clipboard!');" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition-colors">
                <i class="fa-solid fa-copy text-teal-600"></i> Copy Text
            </button>
        </div>
    </div>
HTML;
}

if ($cardsHtml === '') {
    $cardsHtml = '<div class="col-span-full p-8 text-center text-slate-500 font-medium bg-white rounded-2xl border border-slate-200">No report templates found for the selected department filter.</div>';
}

$deptSelect = select_field('Filter by Department', 'dept', $departments, $deptFilter, false, 'onchange="window.location.href=\'/portals/main-lab/templates.php?dept=\' + encodeURIComponent(this.value);"');

$content = page_header('Report Templates', 'Private & shared diagnostic templates, narrative macro findings, and impression blocks.');
$content .= $message . '<div class="mb-6">' . $addForm . '</div>' .
    '<div class="mb-4 max-w-sm">' . $deptSelect . '</div>' .
    '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">' . $cardsHtml . '</div>';

render_page('Report Templates', 'main-lab', 'templates', $content);
