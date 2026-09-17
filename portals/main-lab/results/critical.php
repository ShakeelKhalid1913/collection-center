<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$rows = [
    [e('L-2026-0888'), e('Hassan Raza'), e('FBS'), e('186 mg/dL'), status_badge('critical'), link_action('Log callback')],
    [e('L-2026-0877'), e('Nadia Bibi'), e('HB'), e('6.8 g/dL'), status_badge('critical'), link_action('Log callback')],
];

$content = page_header('Critical Results', 'Out-of-range values requiring follow-up.');
$content .= card(data_table(['Lab No', 'Patient', 'Test', 'Result', 'Flag', 'Action'], $rows), 'overflow-hidden');

render_page('Critical Results', 'main-lab', 'critical', $content);
