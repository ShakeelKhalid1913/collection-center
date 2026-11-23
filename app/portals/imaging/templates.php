<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

require_auth();

$orgId = current_organization_id();
$userId = current_user()['id'] ?? 'admin';
$message = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $delId = trim((string)$_GET['delete']);
    if (template_repo()->delete($delId, $orgId)) {
        header('Location: /portals/imaging/templates.php?deleted=1');
        exit;
    }
    $message = flash_error('Could not delete template.');
}

if (isset($_GET['deleted'])) {
    $message = flash_success('Radiology report template deleted successfully.');
}

// Handle Add / Edit Template
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $department = trim($_POST['department'] ?? 'Ultrasound');
    $tplId = trim($_POST['template_id'] ?? '');

    if ($title === '' || $content === '') {
        $message = flash_error('Template title and report findings content cannot be empty.');
    } else {
        if ($tplId !== '') {
            $ok = template_repo()->update($tplId, [
                'title' => $title,
                'department' => $department,
                'content' => $content,
                'is_private' => !empty($_POST['is_private']) ? 1 : 0,
            ], $orgId);
            $message = $ok ? flash_success("Template '{$title}' updated successfully!") : flash_error('Failed to update template.');
        } else {
            $ok = template_repo()->create([
                'organization_id' => $orgId,
                'title' => $title,
                'department' => $department,
                'content' => $content,
                'is_private' => !empty($_POST['is_private']) ? 1 : 0,
                'created_by' => $userId,
            ]);
            $message = $ok ? flash_success("Radiology report template '{$title}' created successfully!") : flash_error('Failed to save template.');
        }
    }
}

$deptFilter = trim((string)($_GET['dept'] ?? ''));
$templates = template_repo()->getAll($orgId, $userId, $deptFilter ?: null);

// If no templates exist for this org yet, seed standard radiology templates
if (empty($templates)) {
    template_repo()->seedStandardUltrasoundTemplates($orgId);
    $templates = template_repo()->getAll($orgId, $userId, $deptFilter ?: null);
}

$departments = [
    '' => 'All Modalities',
    'Ultrasound' => 'Ultrasound (USG)',
    'Radiology' => 'X-Ray & Digital Radiography',
    'Cardiology' => 'ECG & Cardiology',
    'Echocardiography' => 'Echocardiography (Echo)',
    'CT Scan' => 'CT Scan (Computed Tomography)',
    'MRI' => 'MRI (Magnetic Resonance Imaging)',
    'General' => 'General Radiology',
];

$addForm = card(
    panel_head('Create / Add Radiology Report Template', 'Save reusable macro findings for Ultrasound, X-Ray, ECG, Echo, CT Scan, and MRI') .
    '<form method="post" class="p-4 sm:p-6 space-y-4">' .
    '<div class="grid gap-4 sm:grid-cols-2">' .
    form_field('Template Title', 'title', 'text', null, 'e.g. Ultrasound Abdomen & Pelvis (Normal Findings)') .
    select_field('Modality / Department', 'department', array_slice($departments, 1), 'Ultrasound') .
    '</div>' .
    '<div>' .
    '<label class="field-label font-bold text-slate-800">Template Findings & Impression Text</label>' .
    '<textarea name="content" rows="10" class="field text-sm font-mono leading-relaxed" placeholder="Organ dimensions, parenchymal echotexture, lung fields, cardiac chambers, impression...&#10;&#10;LIVER: Normal in size and shape. Normal parenchymal echotexture.&#10;GALLBLADDER: Well distended, thin-walled, no calculus seen.&#10;IMPRESSION: Normal radiological study."></textarea>' .
    '</div>' .
    '<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-3 border-t border-slate-100">' .
    '<label class="inline-flex items-center gap-2 text-xs sm:text-sm font-semibold text-slate-700 cursor-pointer">' .
    '<input type="checkbox" name="is_private" value="1" class="w-4 h-4 rounded text-slate-900 focus:ring-slate-900 border-slate-300"> ' .
    '<span><i class="fa-solid fa-lock text-amber-500 mr-1"></i> Make Private Template (Only visible to my user account)</span>' .
    '</label>' .
    btn_submit('Save Template to Radiology Library', '', 'fa-solid fa-bookmark') .
    '</div>' .
    '</form>'
);

// Modality filter tabs
$tabHtml = '<div class="flex flex-wrap items-center gap-2 mb-6">';
foreach ($departments as $k => $label) {
    $isActive = ($deptFilter === $k);
    $activeClasses = $isActive
        ? 'bg-slate-900 text-white shadow-sm'
        : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200';
    $url = '/portals/templates.php' . ($k !== '' ? ('?dept=' . urlencode($k)) : '');
    $tabHtml .= '<a href="' . $url . '" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all ' . $activeClasses . '">' . e($label) . '</a>';
}
$tabHtml .= '</div>';

$cardsHtml = '';
foreach ($templates as $tpl) {
    $isPriv = (int)($tpl['is_private'] ?? 0) === 1;
    $lockBadge = $isPriv
        ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-50 text-amber-700 border border-amber-200"><i class="fa-solid fa-lock"></i> Private</span>'
        : '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-teal-50 text-teal-700 border border-teal-200"><i class="fa-solid fa-globe"></i> Shared</span>';

    $deptBadge = '<span class="px-2.5 py-0.5 rounded-lg text-[11px] font-extrabold bg-slate-100 text-slate-800">' . e($tpl['department']) . '</span>';
    $rawContent = htmlspecialchars($tpl['content'], ENT_QUOTES, 'UTF-8');
    $delUrl = '/portals/imaging/templates.php?delete=' . urlencode($tpl['id']);

    $cardsHtml .= <<<HTML
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 flex flex-col justify-between hover:border-slate-300 hover:shadow-sm transition-all">
        <div>
            <div class="flex items-center justify-between gap-2 mb-2">
                <div class="flex items-center gap-1.5 flex-wrap">
                    {$deptBadge}
                    {$lockBadge}
                </div>
                <a href="{$delUrl}" onclick="return confirm('Delete this template from Radiology library?')" class="text-slate-400 hover:text-rose-600 text-xs p-1" title="Delete">
                    <i class="fa-solid fa-trash-can"></i>
                </a>
            </div>
            <h3 class="text-sm font-extrabold text-slate-900 tracking-tight mb-2">{$tpl['title']}</h3>
            <div class="bg-slate-50 rounded-xl p-3 text-xs font-mono text-slate-700 whitespace-pre-wrap max-h-48 overflow-y-auto border border-slate-200/80 leading-relaxed">{$tpl['content']}</div>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
            <span class="text-[10px] text-slate-400 font-medium">Updated: {$tpl['updated_at']}</span>
            <div class="flex items-center gap-2">
                <a href="/portals/results.php" class="inline-flex items-center gap-1 text-[11px] font-bold text-teal-700 hover:text-teal-900">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Use in Findings
                </a>
            </div>
        </div>
    </div>
HTML;
}

if ($cardsHtml === '') {
    $cardsHtml = '<div class="col-span-full p-8 text-center text-slate-500 font-medium bg-white rounded-2xl border border-slate-200">No report templates found for the selected modality. Add one using the form above.</div>';
}

$content = page_header('Radiology Report Templates', 'Centralized template library for Ultrasound, X-Ray, ECG, Echocardiography, CT Scan, and MRI findings.');
$content .= $message;
$content .= '<div class="mb-6">' . $addForm . '</div>';
$content .= $tabHtml;
$content .= '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">' . $cardsHtml . '</div>';

render_page('Report Templates', 'imaging', 'templates', $content);
