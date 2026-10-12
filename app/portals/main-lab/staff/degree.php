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

$degree = employee_repo()->getDegree($id, $orgId);
if (!$degree || empty($degree['degree_blob'])) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body><h3>Degree document not uploaded for this employee.</h3></body></html>';
    exit;
}

$mime = !empty($degree['degree_mime']) ? $degree['degree_mime'] : 'image/jpeg';
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
echo $degree['degree_blob'];
exit;
