<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$content = page_header('Register Patient', 'Shared patient database across all portals.');
$content .= card(
    '<form class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">' .
    form_field('Full name', 'name') .
    form_field('Mobile', 'phone') .
    form_field('Age', 'age', 'number') .
    select_field('Gender', 'gender', ['Female' => 'Female', 'Male' => 'Male']) .
    '<div class="sm:col-span-2">' . btn_submit('Save') . '</div></form>'
);

render_page('Register Patient', 'main-lab', 'register', $content);
