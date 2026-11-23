<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = current_user();
$orgId = $user['organization_id'] ?? 'ORG-001';

$name = trim((string)($_POST['name'] ?? $_GET['name'] ?? ''));

if ($name !== '') {
    record_doctor_name($name, $orgId);
    echo json_encode(['success' => true, 'doctor' => $name]);
    exit;
}

// Return list of saved doctors
$savedDoctors = [];
try {
    $shares = db()->fetchAll(
        "SELECT doctor_name FROM doctor_shares WHERE organization_id = :org AND is_active = 1 ORDER BY updated_at DESC, created_at DESC",
        ['org' => $orgId]
    );
    foreach ($shares as $s) {
        $d = trim((string)$s['doctor_name']);
        if ($d !== '') {
            $savedDoctors[] = $d;
        }
    }
} catch (\Throwable) {}

echo json_encode(['success' => true, 'doctors' => $savedDoctors]);
