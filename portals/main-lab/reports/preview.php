<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$settings = mock('mock_lab_settings');
$patient = mock('mock_patients')[0];
$lines = [
    ['test' => 'Hemoglobin', 'result' => '14.2', 'unit' => 'g/dL', 'range' => '13–17', 'flag' => ''],
    ['test' => 'Fasting Blood Sugar', 'result' => '92', 'unit' => 'mg/dL', 'range' => '70–100', 'flag' => ''],
    ['test' => 'WBC', 'result' => '7.1', 'unit' => '10³/µL', 'range' => '4–11', 'flag' => ''],
];

$content = page_header('Report Preview', 'High-contrast layout for print and PDF export.');
$content .= report_actions($patient['phone']);
$content .= '<div class="mt-6">' . render_report_document($settings, $patient, $lines) . '</div>';

render_page('Report Preview', 'main-lab', 'reports-generate', $content);
