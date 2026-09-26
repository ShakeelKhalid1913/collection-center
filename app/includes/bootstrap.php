<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';

// Database & Repositories
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/PatientRepository.php';
require_once __DIR__ . '/../repositories/TestRepository.php';
require_once __DIR__ . '/../repositories/LabEntryRepository.php';
require_once __DIR__ . '/../repositories/ResultRepository.php';
require_once __DIR__ . '/../repositories/SettingRepository.php';

use App\Database\Database;
use App\Repositories\UserRepository;
use App\Repositories\PatientRepository;
use App\Repositories\TestRepository;
use App\Repositories\LabEntryRepository;
use App\Repositories\ResultRepository;
use App\Repositories\SettingRepository;

const APP_NAME = 'Health LMS Pro';

/**
 * Three main staff user types (client):
 * Diagnostic Center · Laboratory · Collection Center
 * Admin remains for system management only.
 * Keys use hyphens in URLs/session; DB ENUM uses underscores.
 */
const PORTALS = [
    'collection-center' => [
        'label' => 'Collection Center',
        'path' => '/portals/collection-center/dashboard.php',
        'icon' => 'building',
        'staff' => true,
    ],
    'main-lab' => [
        'label' => 'Laboratory',
        'path' => '/portals/main-lab/dashboard.php',
        'icon' => 'flask',
        'staff' => true,
    ],
    'imaging' => [
        'label' => 'Diagnostic Center',
        'path' => '/portals/imaging/dashboard.php',
        'icon' => 'x-ray',
        'staff' => true,
        'modules' => ['X-Ray', 'CT Scan', 'Ultrasound', 'ECG'],
    ],
    'admin' => [
        'label' => 'Admin',
        'path' => '/portals/admin/dashboard.php',
        'icon' => 'shield',
        'staff' => false,
    ],
];

function db(): Database
{
    return Database::getInstance();
}

/** Convert UI portal key (hyphen) ↔ DB ENUM (underscore). */
function portal_to_db(string $portal): string
{
    return str_replace('-', '_', $portal);
}

function portal_from_db(string $portal): string
{
    return str_replace('_', '-', $portal);
}

function is_setup_request(): bool
{
    $script = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    return $script === 'setup.php'
        || str_ends_with($uri, '/database/setup.php')
        || str_ends_with($uri, '/setup.php');
}

/**
 * Enforce Online-Only MariaDB Database Connection.
 * Renders high-contrast "Database Connection Offline" error page if DB cannot be reached.
 */
function check_database_connection(): void
{
    if (is_setup_request()) {
        return;
    }

    if (!db()->isConnected()) {
        http_response_code(503);
        render_offline_page();
        exit;
    }
}

function render_offline_page(): void
{
    $config = require __DIR__ . '/../config/database.php';
    $host = e(($config['host'] ?? '127.0.0.1') . ':' . ($config['port'] ?? 3306));
    $dbname = e($config['dbname'] ?? 'collection_center_db');

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Offline · Health LMS Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 1.5rem; }
        .offline-card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 2.5rem; max-width: 500px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); text-align: center; }
        .offline-icon { width: 4.5rem; height: 4.5rem; background: rgba(225, 29, 72, 0.15); color: #f43f5e; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.5rem; }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; color: #ffffff; }
        p { color: #94a3b8; font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.5rem; }
        .steps { background: #0f172a; border-radius: 8px; padding: 1rem 1.25rem; text-align: left; font-size: 0.875rem; color: #cbd5e1; margin-bottom: 1.75rem; }
        .steps ol { margin: 0; padding-left: 1.25rem; }
        .steps li { margin-bottom: 0.5rem; }
        .steps li:last-child { margin-bottom: 0; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; background: #0284c7; color: #ffffff; text-decoration: none; font-weight: 600; padding: 0.75rem 1.5rem; border-radius: 8px; transition: all 0.2s; border: none; cursor: pointer; }
        .btn:hover { background: #0369a1; }
        code { background: #334155; padding: 0.1rem 0.35rem; border-radius: 4px; font-size: 0.85em; }
    </style>
</head>
<body>
    <div class="offline-card">
        <div class="offline-icon"><i class="fa-solid fa-database"></i></div>
        <h1>Database Offline / Connection Error</h1>
        <p>Health LMS Pro is running in <strong>online-only database mode</strong>. Could not connect to MariaDB at <code>' . $host . '</code> / <code>' . $dbname . '</code>.</p>
        <div class="steps">
            <strong>Troubleshooting Checklist:</strong>
            <ol>
                <li>Ensure MariaDB / MySQL server is running.</li>
                <li>Set <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASS</code> env vars.</li>
                <li>Run setup to create database and seed tables.</li>
            </ol>
        </div>
        <a href="/database/setup.php" class="btn"><i class="fa-solid fa-wrench"></i> Run Database Setup</a>
    </div>
</body>
</html>';
}

// Auto-check DB connection
check_database_connection();

function user_repo(): UserRepository
{
    static $repo = null;
    return $repo ??= new UserRepository();
}

function patient_repo(): PatientRepository
{
    static $repo = null;
    return $repo ??= new PatientRepository();
}

function test_repo(): TestRepository
{
    static $repo = null;
    return $repo ??= new TestRepository();
}

function lab_repo(): LabEntryRepository
{
    static $repo = null;
    return $repo ??= new LabEntryRepository();
}

function result_repo(): ResultRepository
{
    static $repo = null;
    return $repo ??= new ResultRepository();
}

function setting_repo(): SettingRepository
{
    static $repo = null;
    return $repo ??= new SettingRepository();
}

/**
 * Direct MariaDB query provider (legacy name mock() kept for portal pages).
 */
function mock(string $key): array
{
    switch ($key) {
        case 'mock_patients':
            return array_map(fn($p) => [
                'id' => $p['id'] ?? $p['patient_no'],
                'title' => $p['title'] ?? '',
                'name' => $p['full_name'] ?? $p['name'] ?? '',
                'relation' => $p['relation'] ?? 'Self',
                'phone' => $p['phone'] ?? '',
                'age' => (int)($p['age'] ?? 0),
                'gender' => $p['gender'] ?? 'Male',
                'cnic' => $p['cnic'] ?? '',
                'blood_group' => $p['blood_group'] ?? '',
                'email' => $p['email'] ?? '',
                'address' => $p['address'] ?? '',
                'notes' => $p['internal_notes'] ?? '',
                'registered' => date('Y-m-d', strtotime($p['created_at'] ?? 'now')),
                'branch' => $p['branch'] ?? 'CC-01',
            ], patient_repo()->getAll());

        case 'mock_lab_entries':
            return array_map(fn($e) => [
                'lab_no' => $e['lab_no'],
                'patient' => $e['patient_name'] ?? $e['patient'] ?? '',
                'patient_id' => $e['patient_id'] ?? '',
                'tests' => $e['tests'] ?? '',
                'status' => $e['status'] ?? 'pending',
                'sample_status' => $e['sample_status'] ?? 'pending',
                'amount' => (float)($e['amount'] ?? 0),
                'paid' => (float)($e['paid'] ?? 0),
                'discount' => (float)($e['discount'] ?? 0),
                'doctor' => $e['doctor'] ?? 'Walk-in / Self',
                'route' => $e['route'] ?? 'Laboratory — Pathology',
                'priority' => $e['priority'] ?? 'Normal',
                'date' => date('Y-m-d', strtotime($e['created_at'] ?? 'now')),
                'branch' => $e['branch'] ?? 'CC-01',
            ], lab_repo()->getAll());

        case 'mock_tests':
            return array_map(fn($t) => [
                'code' => $t['code'],
                'name' => $t['name'],
                'category' => $t['category'] ?? 'General',
                'price' => (float)($t['price'] ?? 0),
                'sample' => $t['sample_type'] ?? 'Blood',
                'unit' => $t['unit'] ?? '—',
                'range' => $t['normal_range'] ?? '—',
            ], test_repo()->getTests());

        case 'mock_packages':
            return array_map(fn($pkg) => [
                'code' => $pkg['code'],
                'name' => $pkg['name'],
                'tests' => $pkg['tests_included'] ?? '',
                'price' => (float)($pkg['price'] ?? 0),
                'regular' => (float)($pkg['regular_price'] ?? 0),
            ], test_repo()->getPackages());

        case 'mock_samples':
            return result_repo()->getSamples();

        case 'mock_results_pending':
            return result_repo()->getPendingResults();

        case 'mock_imaging':
            return array_map(fn($s) => [
                'id' => $s['id'] ?? '',
                'scan_no' => $s['scan_no'],
                'patient' => $s['patient'],
                'patient_id' => $s['patient_id'] ?? '',
                'modality' => $s['modality'],
                'study' => $s['study'],
                'status' => $s['status'],
                'date' => $s['scan_date'] ?? date('Y-m-d'),
                'radiologist' => $s['radiologist'] ?? '',
                'findings' => $s['findings'] ?? '',
                'impression' => $s['impression'] ?? '',
            ], result_repo()->getImagingScans());

        case 'mock_lab_settings':
            $set = setting_repo()->getSettings();
            return [
                'name' => $set['lab_name'] ?? 'Health LMS Pro Diagnostics',
                'address' => $set['address'] ?? '',
                'phone' => $set['phone'] ?? '',
                'email' => $set['email'] ?? '',
                'header' => $set['header_text'] ?? '',
                'footer' => $set['footer_text'] ?? '',
                'logo_text' => $set['logo_text'] ?? 'HLP',
            ];

        case 'mock_collection_centers':
            return result_repo()->getCollectionCenters();

        case 'mock_doctors':
            return [
                ['id' => 'D-01', 'name' => 'Dr. Imran Sheikh', 'specialty' => 'General Physician'],
                ['id' => 'D-02', 'name' => 'Dr. Fatima Noor', 'specialty' => 'Gynecologist'],
                ['id' => 'D-03', 'name' => 'Dr. Usman Malik', 'specialty' => 'Cardiologist'],
                ['id' => 'D-04', 'name' => 'Dr. Nadia Hussain', 'specialty' => 'Endocrinologist'],
                ['id' => 'D-05', 'name' => 'Walk-in / Self', 'specialty' => '—'],
            ];

        case 'mock_routes':
            return [
                ['id' => 'RT-MAIN', 'label' => 'Laboratory — Pathology (HQ)'],
                ['id' => 'RT-BIO', 'label' => 'Laboratory — Biochemistry Bench'],
                ['id' => 'RT-HEMA', 'label' => 'Laboratory — Hematology Bench'],
                ['id' => 'RT-IMG', 'label' => 'Diagnostic Center'],
                ['id' => 'RT-OUT', 'label' => 'External referral lab'],
            ];
    }

    return [];
}

function resolve_doctor_name(?string $idOrName): string
{
    if ($idOrName === null || $idOrName === '') {
        return 'Walk-in / Self';
    }
    foreach (mock('mock_doctors') as $d) {
        if ($d['id'] === $idOrName) {
            return $d['name'];
        }
    }
    return $idOrName;
}

function resolve_route_label(?string $idOrLabel): string
{
    if ($idOrLabel === null || $idOrLabel === '') {
        return 'Laboratory — Pathology (HQ)';
    }
    foreach (mock('mock_routes') as $r) {
        if ($r['id'] === $idOrLabel) {
            return $r['label'];
        }
    }
    return $idOrLabel;
}

/**
 * Resolve selected catalog checkbox values (names) + amount from POST.
 * @return array{tests: string, amount: float}
 */
function resolve_catalog_from_post(array $post): array
{
    $selected = $post['tests'] ?? [];
    if (!is_array($selected)) {
        $selected = $selected !== '' ? [$selected] : [];
    }
    $selected = array_values(array_filter(array_map('trim', $selected)));

    $amount = (float)($post['amount'] ?? 0);
    if ($amount <= 0 && $selected !== []) {
        $priceMap = [];
        foreach (mock('mock_tests') as $t) {
            $priceMap[$t['name']] = (float)$t['price'];
            $priceMap[$t['code']] = (float)$t['price'];
        }
        foreach (mock('mock_packages') as $p) {
            $priceMap[$p['name']] = (float)$p['price'];
            $priceMap[$p['code']] = (float)$p['price'];
        }
        foreach ($selected as $name) {
            $amount += $priceMap[$name] ?? 0;
        }
    }

    $discount = (float)($post['discount'] ?? 0);
    $net = max(0, $amount - $discount);

    if ($selected === []) {
        // Sensible default when nothing checked (CBC + FBS seed prices)
        return [
            'tests' => 'CBC, FBS',
            'amount' => $net > 0 ? $net : 1650.0,
            'gross' => $amount > 0 ? $amount : 1650.0,
            'discount' => $discount,
            'paid' => (float)($post['paid'] ?? 0),
        ];
    }

    return [
        'tests' => implode(', ', $selected),
        'amount' => $net > 0 ? $net : ($amount > 0 ? $amount : 0),
        'gross' => $amount,
        'discount' => $discount,
        'paid' => (float)($post['paid'] ?? 0),
    ];
}

function staff_portals(): array
{
    return array_filter(PORTALS, static fn (array $p): bool => !empty($p['staff']));
}

function start_user_session(array $user): void
{
    $portal = portal_from_db((string)($user['portal'] ?? 'collection_center'));
    if (!isset(PORTALS[$portal])) {
        $portal = 'collection-center';
    }
    $_SESSION['portal'] = $portal;
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'portal' => $portal,
        'organization_id' => $user['organization_id'] ?? 'ORG-001',
        'branch_id' => $user['branch_id'] ?? null,
    ];
}

function require_auth(?string $portal = null): void
{
    if (empty($_SESSION['user'])) {
        header('Location: /login.php');
        exit;
    }

    $sessionPortal = portal_from_db((string)($_SESSION['portal'] ?? $_SESSION['user']['portal'] ?? ''));
    $_SESSION['portal'] = $sessionPortal;

    if ($portal !== null && $sessionPortal !== $portal) {
        http_response_code(403);
        $allowedLabel = e(PORTALS[$sessionPortal]['label'] ?? $sessionPortal);
        $requestedLabel = e(PORTALS[$portal]['label'] ?? $portal);
        $home = e(PORTALS[$sessionPortal]['path'] ?? '/login.php');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Access denied</title>
        <style>body{font-family:system-ui;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:1.5rem}
        .card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:2rem;max-width:420px;text-align:center}
        a{color:#38bdf8;font-weight:600}</style></head><body><div class="card">
        <h1 style="margin:0 0 .5rem;font-size:1.25rem">Access denied</h1>
        <p style="color:#94a3b8;line-height:1.5">Your account is locked to <strong>' . $allowedLabel . '</strong> and cannot open <strong>' . $requestedLabel . '</strong> pages.</p>
        <p><a href="' . $home . '">Go to your dashboard</a> · <a href="/logout.php">Sign out</a></p>
        </div></body></html>';
        exit;
    }
}

function current_user(): array
{
    return $_SESSION['user'] ?? [];
}

function current_portal(): string
{
    return portal_from_db((string)($_SESSION['portal'] ?? ''));
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

function flash_success(string $message): string
{
    return '<div class="alert-success" style="margin-bottom:1rem"><i class="fa-solid fa-circle-check"></i> ' . e($message) . '</div>';
}

function flash_error(string $message): string
{
    return '<div class="alert-error" style="margin-bottom:1rem"><i class="fa-solid fa-circle-exclamation"></i> ' . e($message) . '</div>';
}

function nav_badge_counts(): array
{
    return [
        'cc_pending' => (string)lab_repo()->countByStatuses(['pending', 'collected']),
        'samples_pending' => (string)result_repo()->countPendingSamples(),
        'results_verify' => (string)result_repo()->countPendingVerification(),
        'critical' => (string)result_repo()->countCritical(),
        'imaging_pending' => (string)result_repo()->countImagingPending(),
    ];
}
