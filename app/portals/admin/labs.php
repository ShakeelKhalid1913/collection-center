<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$rows = [
    [e('ORG-001'), e('Health LMS Pro'), e('Laboratory'), e('Faisalabad'), status_badge('active')],
    [e('BR-CC-01'), e('Gulberg Collection Point'), e('Collection Center'), e('Faisalabad'), status_badge('active')],
    [e('BR-IMG'), e('Diagnostic Center'), e('X-Ray / CT / US / ECG'), e('Faisalabad'), status_badge('active')],
];

$content = page_header('Labs & Branches', 'Organization hierarchy.');
$content .= card(data_table(['ID', 'Name', 'Type', 'City', 'Status'], $rows), 'overflow-hidden');

render_page('Labs & Branches', 'admin', 'labs', $content);
