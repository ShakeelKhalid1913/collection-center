<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $message = flash_error('Full name is required.');
    } else {
        $res = patient_repo()->create([
            'full_name' => $name,
            'phone' => $_POST['phone'] ?? '',
            'age' => (int)($_POST['age'] ?? 0),
            'gender' => $_POST['gender'] ?? 'Female',
            'branch' => 'LAB-01',
            'created_by' => current_user()['id'] ?? null,
            'organization_id' => current_user()['organization_id'] ?? 'ORG-001',
        ]);
        if ($res['success']) {
            header('Location: /portals/main-lab/patients/records.php?registered=1');
            exit;
        }
        $message = flash_error('Could not save patient.');
    }
}

$content = page_header('Register Patient', 'Shared patient database across all portals.');
$content .= $message;
$content .= card(
    '<form method="post" class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">' .
    form_field('Full name', 'name') .
    form_field('Mobile', 'phone') .
    form_field('Age', 'age', 'number') .
    select_field('Gender', 'gender', ['Female' => 'Female', 'Male' => 'Male']) .
    '<div class="sm:col-span-2">' . btn_submit('Save') . '</div></form>'
);

render_page('Register Patient', 'main-lab', 'register', $content);
