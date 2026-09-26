<?php

declare(strict_types=1);

/**
 * Streams lab letterhead image from DB (PNG/JPG/WebP).
 * Used on bills, reports, and branding preview.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$orgId = trim((string)($_GET['org'] ?? ''));
if ($orgId === '') {
    $orgId = current_user()['organization_id'] ?? 'ORG-001';
}

// Soft auth: logged-in users can load any org they know; guests get 404
if (!current_user()) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unauthorized';
    exit;
}

$img = setting_repo()->getHeaderImage($orgId);
if ($img === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No header image';
    exit;
}

$mime = preg_replace('/[^a-z0-9.+\-\/]/i', '', $img['mime']) ?: 'image/png';
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
header('Content-Length: ' . strlen($img['data']));
echo $img['data'];
exit;
