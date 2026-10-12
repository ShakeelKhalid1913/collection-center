<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

$user = current_user();
$orgId = $user['organization_id'] ?? 'ORG-001';
$id = trim((string)($_GET['id'] ?? ''));

if ($id === '') {
    http_response_code(404);
    exit;
}

$photo = employee_repo()->getPhoto($id, $orgId);
if (!$photo || empty($photo['photo_blob'])) {
    http_response_code(404);
    exit;
}

$mime = !empty($photo['photo_mime']) ? $photo['photo_mime'] : 'image/jpeg';
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
echo $photo['photo_blob'];
exit;
