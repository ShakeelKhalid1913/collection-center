<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$rows = [
    [
        '<span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md text-xs">ORG-001</span>',
        '<span class="font-bold text-slate-800">Lab Dash Pro Central</span>',
        '<span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">Laboratory</span>',
        '<span class="text-xs text-slate-500 font-medium">Faisalabad</span>',
        status_badge('active'),
    ],
    [
        '<span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md text-xs">BR-CC-01</span>',
        '<span class="font-bold text-slate-800">Gulberg Collection Point</span>',
        '<span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">Collection Center</span>',
        '<span class="text-xs text-slate-500 font-medium">Faisalabad</span>',
        status_badge('active'),
    ],
    [
        '<span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md text-xs">BR-IMG</span>',
        '<span class="font-bold text-slate-800">Diagnostic Center</span>',
        '<span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">X-Ray / CT / US / ECG</span>',
        '<span class="text-xs text-slate-500 font-medium">Faisalabad</span>',
        status_badge('active'),
    ],
];

$content = page_header('Labs & Branches', 'Organization hierarchy.');
$content .= card(data_table(['ID', 'Name', 'Type', 'City', 'Status'], $rows), 'overflow-hidden');

render_page('Labs & Branches', 'admin', 'labs', $content);
