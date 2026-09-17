<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../data/mock.php';
require_once __DIR__ . '/helpers.php';

const APP_NAME = 'LabFlow LMS';

const PORTALS = [
    'collection-center' => [
        'label' => 'Collection Center',
        'path' => '/portals/collection-center/dashboard.php',
        'icon' => 'building-storefront',
    ],
    'main-lab' => [
        'label' => 'Main Lab',
        'path' => '/portals/main-lab/dashboard.php',
        'icon' => 'beaker',
    ],
    'imaging' => [
        'label' => 'X-Ray / CT Scan',
        'path' => '/portals/imaging/dashboard.php',
        'icon' => 'scan',
    ],
    'admin' => [
        'label' => 'Admin',
        'path' => '/portals/admin/dashboard.php',
        'icon' => 'shield-check',
    ],
];

function require_auth(?string $portal = null): void
{
    if (empty($_SESSION['user'])) {
        header('Location: /login.php');
        exit;
    }
    if ($portal !== null && ($_SESSION['portal'] ?? '') !== $portal) {
        http_response_code(403);
        echo 'Access denied for this portal.';
        exit;
    }
}

function current_user(): array
{
    return $_SESSION['user'] ?? [];
}

function current_portal(): string
{
    return $_SESSION['portal'] ?? '';
}

function format_money(float $amount): string
{
    return 'Rs. ' . number_format($amount, 0, '.', ',');
}

function format_date(?string $iso): string
{
    if (!$iso) {
        return '—';
    }
    return date('d M Y', strtotime($iso));
}
