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

// 1. Direct file match for non-portal assets and scripts (e.g., /login.php, /signup.php)
if (!str_starts_with($path, '/portals/')) {
    $candidate = $root . $path;
    if (is_file($candidate) && str_ends_with(strtolower($candidate), '.php')) {
        require $candidate;
        return true;
    }
    if (is_file($candidate . '.php')) {
        require $candidate . '.php';
        return true;
    }
    if (is_dir($candidate)) {
        if (is_file($candidate . '/index.php')) {
            require $candidate . '/index.php';
            return true;
        }
    }
}

// 2. Normalize legacy portal folders (/portals/main-lab/, /portals/imaging/, etc.) -> clean direct /portals/...
if (preg_match('#^/portals/(imaging|main-lab|collection-center|admin)(?:/(.*))?$#i', $path, $matches)) {
    $sub = isset($matches[2]) && $matches[2] !== '' && $matches[2] !== '/' 
        ? ltrim($matches[2], '/') 
        : 'dashboard.php';
    
    // For GET and HEAD requests, redirect 302 directly to clean unified URL
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['GET', 'HEAD'], true)) {
        $qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
        header('Location: /portals/' . $sub . $qs, true, 302);
        exit;
    }

    // For POST requests, rewrite internally to preserve request body
    $path = '/portals/' . $sub;
}

// 3. Unified Direct Portal Dispatcher (/portals/<subpath>)
if (str_starts_with($path, '/portals/')) {
    $sub = substr($path, strlen('/portals/'));
    if ($sub === '' || $sub === '/') {
        $sub = 'dashboard.php';
    }

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $rawPortal = (string)($_SESSION['user']['portal'] ?? $_SESSION['portal'] ?? '');
    $activePortal = str_replace('_', '-', $rawPortal);
    if (!in_array($activePortal, ['collection-center', 'main-lab', 'imaging', 'admin'], true)) {
        $activePortal = 'imaging';
    }

    // Search active portal folder first, then fall back across other portal modules
    $searchOrder = array_unique([$activePortal, 'imaging', 'main-lab', 'collection-center', 'admin']);

    foreach ($searchOrder as $p) {
        $target = $root . '/portals/' . $p . '/' . $sub;
        if (is_file($target) && str_ends_with(strtolower($target), '.php')) {
            require $target;
            return true;
        }
        if (is_file($target . '.php')) {
            require $target . '.php';
            return true;
        }
        if (is_dir($target)) {
            if (is_file($target . '/dashboard.php')) {
                require $target . '/dashboard.php';
                return true;
            }
            if (is_file($target . '/index.php')) {
                require $target . '/index.php';
                return true;
            }
        }
    }

    // Direct root app fallback if needed
    $rootTarget = $root . '/' . $sub;
    if (is_file($rootTarget) && str_ends_with(strtolower($rootTarget), '.php')) {
        require $rootTarget;
        return true;
    }
    if (is_file($rootTarget . '.php')) {
        require $rootTarget . '.php';
        return true;
    }
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><body><h1>Not Found</h1><p>' . htmlspecialchars($uri) . '</p></body></html>';
return true;
