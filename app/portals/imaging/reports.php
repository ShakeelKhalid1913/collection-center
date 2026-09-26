<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$settings = mock('mock_lab_settings');
$patient = mock('mock_patients')[3];

$doc = <<<HTML
<div class="print-area mx-auto max-w-3xl border-2 border-black bg-white p-6 text-black">
    <p class="text-lg font-bold">{$settings['name']} — Radiology</p>
    <p class="text-sm">{$settings['address']} · {$settings['phone']}</p>
    <hr class="my-4 border-black">
    <p class="text-sm"><strong>Patient:</strong> {$patient['name']} · <strong>Study:</strong> Chest X-Ray PA</p>
    <p class="mt-4 text-sm"><strong>Findings:</strong> Heart size normal. Lungs clear.</p>
    <p class="mt-2 text-sm"><strong>Impression:</strong> Normal study.</p>
    <p class="mt-8 text-sm font-semibold">Dr. Imran Sheikh — Radiologist</p>
    ' . software_credit_footer(true) . '
</div>
HTML;

$content = page_header('Radiology Report', 'Print · PDF · WhatsApp');
$content .= report_actions($patient['phone'], '/portals/imaging/reports.php');
$content .= '<div class="mt-4">' . $doc . '</div>';

render_page('Reports', 'imaging', 'reports', $content, true);
