<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/components.php';

$content = page_header('Test Packages', 'Manage bundled offerings.');
$content .= card('<p class="p-6 text-sm text-slate-600">Package editor UI — same as Main Lab packages view, wired to admin permissions later.</p>');

render_page('Packages', 'admin', 'packages', $content);
