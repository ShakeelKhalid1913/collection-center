<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [
    [e('L-2026-0891'), e('Ayesha Khan'), e('CBC'), e('Technician: Sara'), btn_submit('Verify & release')],
    [e('L-2026-0890'), e('Muhammad Ali'), e('LFT'), e('Technician: Bilal'), btn_submit('Verify & release')],
];

$content = page_header('Verification', 'Pathologist sign-off before report generation.');
$content .= card(data_table(['Lab No', 'Patient', 'Panel', 'Entered by', 'Action'], $rows), 'overflow-hidden');

render_page('Verification', 'main-lab', 'verification', $content);
