<?php

declare(strict_types=1);

/**
 * Vercel / local front controller.
 * App PHP lives in /app (not public static). Assets stay in /assets.
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($uri) ? rawurldecode($uri) : '/';

if (str_contains($uri, '..')) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Bad Request';
    exit;
}

$projectRoot = dirname(__DIR__);

// Local `php -S` — serve CSS/JS/images as real files
if (PHP_SAPI === 'cli-server') {
    $staticPath = $projectRoot . $uri;
    if ($uri !== '/' && is_file($staticPath) && !str_ends_with(strtolower($staticPath), '.php')) {
        return false;
    }
}

$root = $projectRoot . DIRECTORY_SEPARATOR . 'app';

if ($uri === '/' || $uri === '') {
    require $root . '/index.php';
    return true;
}

$path = rtrim($uri, '/');
$candidate = $root . $path;

if (is_file($candidate) && str_ends_with(strtolower($candidate), '.php')) {
    require $candidate;
    return true;
}

if (is_file($candidate . '.php')) {
    require $candidate . '.php';
    return true;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><body><h1>Not Found</h1><p>' . htmlspecialchars($uri) . '</p></body></html>';
return true;
