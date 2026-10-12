<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

$user = current_user();
$orgId = trim((string)($_GET['org'] ?? ($user['organization_id'] ?? 'ORG-001')));

$lic = employee_repo()->getLicenseBlob($orgId);
if (!$lic || empty($lic['license_image'])) {
    http_response_code(404);
    exit;
}

$mime = !empty($lic['license_image_mime']) ? $lic['license_image_mime'] : 'image/jpeg';
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
echo $lic['license_image'];
exit;
