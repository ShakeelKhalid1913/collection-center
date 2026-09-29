<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_auth('main-lab');

$id = trim((string)($_GET['id'] ?? ''));
$type = strtolower(trim((string)($_GET['type'] ?? 'mou')));
if ($id === '' || !in_array($type, ['mou', 'slip'], true)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$orgId = current_user()['organization_id'] ?? 'ORG-001';
$row = waste_repo()->find($id);
if (!$row || (string)($row['organization_id'] ?? '') !== $orgId) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$blob = $type === 'slip' ? ($row['slip_image'] ?? null) : ($row['mou_image'] ?? null);
$mime = $type === 'slip' ? ($row['slip_image_mime'] ?? null) : ($row['mou_image_mime'] ?? null);

if ($blob === null || $blob === '') {
    http_response_code(404);
    echo 'Image not found';
    exit;
}

header('Content-Type: ' . ($mime ?: 'image/jpeg'));
header('Cache-Control: private, max-age=3600');
echo $blob;
exit;
