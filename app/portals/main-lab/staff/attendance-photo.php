<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

require_auth();

$attId = trim((string)($_GET['id'] ?? ''));
$type = strtolower(trim((string)($_GET['type'] ?? 'in')));
if (!in_array($type, ['in', 'out'], true)) {
    $type = 'in';
}
$orgId = current_user()['organization_id'] ?? 'ORG-001';

if ($attId === '') {
    http_response_code(404);
    exit('Attendance ID missing');
}

$photo = employee_repo()->getAttendancePhoto($attId, $type, $orgId);

if (!$photo || empty($photo['blob_data'])) {
    http_response_code(404);
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/></svg>';
    exit;
}

$mime = !empty($photo['mime']) ? $photo['mime'] : 'image/jpeg';
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=86400');
echo $photo['blob_data'];
