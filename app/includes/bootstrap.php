<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Karachi');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/documents.php';
require_once __DIR__ . '/barcode.php';

// Database & Repositories
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/PatientRepository.php';
require_once __DIR__ . '/../repositories/TestRepository.php';
require_once __DIR__ . '/../repositories/LabEntryRepository.php';
require_once __DIR__ . '/../repositories/ResultRepository.php';
require_once __DIR__ . '/../repositories/SettingRepository.php';
require_once __DIR__ . '/../repositories/WasteRecordRepository.php';
require_once __DIR__ . '/../repositories/DoctorShareRepository.php';
require_once __DIR__ . '/../repositories/ExpenseRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/TemplateRepository.php';
require_once __DIR__ . '/../repositories/PatientDuesRepository.php';
require_once __DIR__ . '/../repositories/B2BRepository.php';
require_once __DIR__ . '/../repositories/EmployeeRepository.php';
require_once __DIR__ . '/../repositories/OrganizationRepository.php';

use App\Database\Database;
use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;
use App\Repositories\PatientRepository;
use App\Repositories\TestRepository;
use App\Repositories\LabEntryRepository;
use App\Repositories\ResultRepository;
use App\Repositories\SettingRepository;
use App\Repositories\WasteRecordRepository;
use App\Repositories\DoctorShareRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\StockRepository;
use App\Repositories\TemplateRepository;
use App\Repositories\PatientDuesRepository;
use App\Repositories\B2BRepository;
use App\Repositories\EmployeeRepository;

const APP_NAME = 'Lab Dash Pro';

/**
 * Primary lab departments (test categories) — client-defined.
 * Optional when adding a test; used to group / filter the catalog.
 */
const TEST_DEPARTMENTS = [
    'Hematology' => 'Hematology',
    'Biochemistry' => 'Biochemistry',
    'Chemical Pathology' => 'Chemical Pathology',
    'Special Chemistry' => 'Special Chemistry',
    'Serology' => 'Serology',
    'Microbiology' => 'Microbiology',
    'Histopathology' => 'Histopathology',
    'Pathology' => 'Pathology',
    'Radiology' => 'Radiology',
    'Molecular' => 'Molecular',
    'Report Profile' => 'Report Profile',
    'Clinical Pathology' => 'Clinical Pathology',
    'Immunology' => 'Immunology',
    'Endocrinology' => 'Endocrinology',
    'Chemistry' => 'Chemistry',
];

function test_departments(): array
{
    return TEST_DEPARTMENTS;
}

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
    <title>Database Offline · Lab Dash Pro</title>
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
        <p>Lab Dash Pro is running in <strong>online-only database mode</strong>. Could not connect to MariaDB at <code>' . $host . '</code> / <code>' . $dbname . '</code>.</p>
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

function waste_repo(): WasteRecordRepository
{
    static $repo = null;
    return $repo ??= new WasteRecordRepository();
}

function doctor_share_repo(): DoctorShareRepository
{
    static $repo = null;
    return $repo ??= new DoctorShareRepository();
}

function expense_repo(): ExpenseRepository
{
    static $repo = null;
    return $repo ??= new ExpenseRepository();
}

function stock_repo(): StockRepository
{
    static $repo = null;
    return $repo ??= new StockRepository();
}

function template_repo(): TemplateRepository
{
    static $repo = null;
    return $repo ??= new TemplateRepository();
}

function dues_repo(): PatientDuesRepository
{
    static $repo = null;
    return $repo ??= new PatientDuesRepository();
}

function b2b_repo(): B2BRepository
{
    static $repo = null;
    return $repo ??= new B2BRepository();
}

function employee_repo(): EmployeeRepository
{
    static $repo = null;
    return $repo ??= new EmployeeRepository();
}

function org_repo(): OrganizationRepository
{
    static $repo = null;
    return $repo ??= new OrganizationRepository();
}

function audit_log(string $action, string $entityType, ?string $entityId = null, ?string $details = null): bool
{
    try {
        $user = current_user();
        $orgId = $user['organization_id'] ?? 'ORG-001';
        $userId = $user['id'] ?? null;
        $userName = $user['name'] ?? 'System';
        $portal = current_portal();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $id = 'AUD-' . bin2hex(random_bytes(6));

        return db()->execute(
            "INSERT INTO audit_logs (id, organization_id, user_id, user_name, portal, action, entity_type, entity_id, details, ip_address)
             VALUES (:id, :org, :uid, :uname, :portal, :action, :etype, :eid, :details, :ip)",
            [
                'id' => $id,
                'org' => $orgId,
                'uid' => $userId,
                'uname' => $userName,
                'portal' => $portal,
                'action' => $action,
                'etype' => $entityType,
                'eid' => $entityId,
                'details' => $details,
                'ip' => $ip,
            ]
        );
    } catch (\Throwable) {
        return false;
    }
}

/**
 * Direct MariaDB query provider (legacy name mock() kept for portal pages).
 */
function mock(string $key): array
{
    switch ($key) {
        case 'mock_patients':
            $curUser = current_user();
            $orgId = $curUser['organization_id'] ?? 'ORG-001';
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
            ], patient_repo()->getAll($orgId));

        case 'mock_lab_entries':
            $curUser = current_user();
            $orgId = $curUser['organization_id'] ?? 'ORG-001';
            $branchId = ($curUser['portal'] ?? '') === 'collection-center' ? ($curUser['branch_id'] ?? null) : null;
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
                'is_b2b' => !empty($e['is_b2b']),
                'b2b_client_name' => $e['b2b_client_name'] ?? '',
                'doctor' => $e['doctor'] ?? 'Walk-in / Self',
                'route' => $e['route'] ?? 'Laboratory — Pathology',
                'priority' => $e['priority'] ?? 'Normal',
                'date' => date('Y-m-d', strtotime($e['created_at'] ?? 'now')),
                'branch' => $e['branch'] ?? 'CC-01',
                'branch_id' => $e['branch_id'] ?? '',
            ], lab_repo()->getAll($orgId, $branchId));

        case 'mock_tests':
            // Catalog source of truth: tests table (TestRepository), not a static list.
            $orgId = current_user()['organization_id'] ?? 'ORG-001';
            return array_map(fn($t) => [
                'code' => $t['code'],
                'name' => $t['name'],
                'category' => $t['category'] ?? 'General',
                'price' => (float)($t['price'] ?? 0),
                'b2b_price' => (float)($t['b2b_price'] ?? round(((float)($t['price'] ?? 0)) * 0.75, 0)),
                'sample' => $t['sample_type'] ?? 'Blood',
                'unit' => $t['unit'] ?? '—',
                'range' => $t['normal_range'] ?? '—',
            ], test_repo()->getTests($orgId));

        case 'mock_packages':
            return array_map(fn($pkg) => [
                'code' => $pkg['code'],
                'name' => $pkg['name'],
                'tests' => $pkg['tests_included'] ?? '',
                'price' => (float)($pkg['price'] ?? 0),
                'regular' => (float)($pkg['regular_price'] ?? 0),
            ], test_repo()->getPackages());

        case 'mock_samples':
            $orgId = current_user()['organization_id'] ?? 'ORG-001';
            return result_repo()->getSamples($orgId);

        case 'mock_results_pending':
            $orgId = current_user()['organization_id'] ?? 'ORG-001';
            return result_repo()->getPendingResults($orgId);

        case 'mock_imaging':
            $orgId = current_user()['organization_id'] ?? 'ORG-001';
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
            ], result_repo()->getImagingScans($orgId));

        case 'mock_lab_settings':
            return branding_settings();

        case 'mock_collection_centers':
            return result_repo()->getCollectionCenters();

        case 'mock_doctors':
            $orgId = current_user()['organization_id'] ?? 'ORG-001';
            return get_all_system_doctors($orgId);

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

/**
 * Fetch all available doctors dynamically from signatories, doctor shares, and employee staff.
 */
function get_all_system_doctors(string $orgId = 'ORG-001'): array
{
    $doctors = [];
    $seen = [];

    // 1. Report Signatories (Consultant Pathologists, Radiologists, Physicians)
    try {
        $sigs = db()->fetchAll(
            "SELECT id, name, designation, qualifications FROM report_signatories WHERE organization_id = :org AND is_active = 1 ORDER BY sort_order ASC, name ASC",
            ['org' => $orgId]
        );
        foreach ($sigs as $s) {
            $name = trim((string)($s['name'] ?? ''));
            if ($name !== '' && !isset($seen[strtolower($name)])) {
                $seen[strtolower($name)] = true;
                $doctors[] = [
                    'id' => $s['id'],
                    'name' => $name,
                    'specialty' => $s['designation'] ?: 'Consultant',
                    'qualifications' => $s['qualifications'] ?? '',
                ];
            }
        }
    } catch (\Throwable $e) {}

    // 2. Doctor Shares (Commission / Referring Doctors)
    try {
        $shares = db()->fetchAll(
            "SELECT id, doctor_name FROM doctor_shares WHERE organization_id = :org AND is_active = 1 ORDER BY doctor_name ASC",
            ['org' => $orgId]
        );
        foreach ($shares as $sh) {
            $name = trim((string)($sh['doctor_name'] ?? ''));
            if ($name !== '' && !isset($seen[strtolower($name)])) {
                $seen[strtolower($name)] = true;
                $doctors[] = [
                    'id' => $sh['id'],
                    'name' => $name,
                    'specialty' => 'Referring Consultant',
                    'qualifications' => '',
                ];
            }
        }
    } catch (\Throwable $e) {}

    // 3. Employees (Staff with Doctor / Radiologist / Pathologist roles)
    try {
        $emps = db()->fetchAll(
            "SELECT id, full_name, designation, department FROM employees WHERE organization_id = :org AND is_active = 1 ORDER BY full_name ASC",
            ['org' => $orgId]
        );
        foreach ($emps as $em) {
            $name = trim((string)($em['full_name'] ?? ''));
            $desig = trim((string)($em['designation'] ?? ''));
            $dept = trim((string)($em['department'] ?? ''));
            if ($name !== '' && !isset($seen[strtolower($name)])) {
                if (stripos($name, 'Dr') !== false || stripos($desig, 'Doctor') !== false || stripos($desig, 'Consultant') !== false || stripos($desig, 'Pathologist') !== false || stripos($desig, 'Radiologist') !== false || stripos($desig, 'Sonologist') !== false) {
                    $seen[strtolower($name)] = true;
                    $doctors[] = [
                        'id' => $em['id'],
                        'name' => $name,
                        'specialty' => $desig ?: ($dept ?: 'Specialist'),
                        'qualifications' => '',
                    ];
                }
            }
        }
    } catch (\Throwable $e) {}

    // 4. Default Consultants / Doctors
    $defaults = [
        ['id' => 'D-01', 'name' => 'Dr. Imran Sheikh', 'specialty' => 'Consultant Radiologist', 'qualifications' => 'M.B.B.S, F.C.P.S (Radiology)'],
        ['id' => 'D-02', 'name' => 'Dr. Fatima Noor', 'specialty' => 'Consultant Sonologist / Gynecologist', 'qualifications' => 'M.B.B.S, MCPS'],
        ['id' => 'D-03', 'name' => 'Dr. Usman Malik', 'specialty' => 'Cardiologist (ECG / Echo)', 'qualifications' => 'M.B.B.S, Dip Card'],
        ['id' => 'D-04', 'name' => 'Dr. Nadia Hussain', 'specialty' => 'General Physician', 'qualifications' => 'M.B.B.S'],
        ['id' => 'D-05', 'name' => 'Walk-in / Self', 'specialty' => '—', 'qualifications' => ''],
    ];
    foreach ($defaults as $df) {
        if (!isset($seen[strtolower($df['name'])])) {
            $seen[strtolower($df['name'])] = true;
            $doctors[] = $df;
        }
    }

    return $doctors;
}

function resolve_doctor_name(?string $idOrName): string
{
    if ($idOrName === null || trim($idOrName) === '') {
        return 'Walk-in / Self';
    }
    $trimmed = trim($idOrName);
    foreach (mock('mock_doctors') as $d) {
        if ($d['id'] === $trimmed || strcasecmp($d['name'], $trimmed) === 0) {
            return $d['name'];
        }
    }
    return $trimmed;
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
 * Canonical catalog display name for a booked test / package (e.g. CBC → CBC (Complete Blood Count)).
 */
function normalize_test_label(string $name, string $orgId = 'ORG-001'): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }

    $pkg = null;
    try {
        $pkg = test_repo()->getPackages($orgId);
    } catch (Throwable) {
        $pkg = mock('mock_packages');
    }
    foreach ($pkg as $p) {
        $pName = (string)($p['name'] ?? '');
        $pCode = (string)($p['code'] ?? '');
        if (strcasecmp($pName, $name) === 0 || strcasecmp($pCode, $name) === 0) {
            return $pName !== '' ? $pName : $name;
        }
    }

    $test = test_repo()->findByCode($name, $orgId);
    if ($test && !empty($test['name'])) {
        return (string)$test['name'];
    }

    return $name;
}

/**
 * Normalize a comma-separated (or array) tests list to catalog names.
 */
function normalize_tests_list(string|array $tests, string $orgId = 'ORG-001'): string
{
    if (is_string($tests)) {
        $parts = array_filter(array_map('trim', explode(',', $tests)));
    } else {
        $parts = array_values(array_filter(array_map(static fn ($t) => trim((string)$t), $tests)));
    }

    $normalized = [];
    $seen = [];
    foreach ($parts as $part) {
        $label = normalize_test_label($part, $orgId);
        if ($label === '') {
            continue;
        }
        $key = strtolower($label);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $normalized[] = $label;
    }

    return implode(', ', $normalized);
}

/**
 * Resolve selected catalog checkbox values (names) + amount from POST.
 * @return array{tests: string, amount: float, gross: float, discount: float, paid: float}
 */
function resolve_catalog_from_post(array $post): array
{
    $selected = $post['tests'] ?? [];
    if (!is_array($selected)) {
        $selected = $selected !== '' ? [$selected] : [];
    }
    $selected = array_values(array_filter(array_map('trim', $selected)));
    $orgId = current_user()['organization_id'] ?? 'ORG-001';

    // Always store official catalog names (never bare "CBC" / "LFT")
    $selected = array_values(array_filter(array_map(
        static fn (string $n): string => normalize_test_label($n, $orgId),
        $selected
    )));
    $selected = array_values(array_unique($selected));

    $isB2b = !empty($post['is_b2b']) && $post['is_b2b'] !== '0';
    $b2bClientId = trim((string)($post['b2b_client_id'] ?? ''));
    $b2bClientName = trim((string)($post['b2b_client_name'] ?? ''));

    $amount = (float)($post['amount'] ?? 0);
    if ($amount <= 0 && $selected !== []) {
        $priceMap = [];
        foreach (mock('mock_tests') as $t) {
            $p = $isB2b ? (float)($t['b2b_price'] ?? round(((float)$t['price']) * 0.75)) : (float)$t['price'];
            $priceMap[$t['name']] = $p;
            $priceMap[$t['code']] = $p;
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
        $fallback = normalize_tests_list('CBC, FBS', $orgId);
        return [
            'tests' => $fallback,
            'amount' => $net > 0 ? $net : 1650.0,
            'gross' => $amount > 0 ? $amount : 1650.0,
            'discount' => $discount,
            'paid' => (float)($post['paid'] ?? 0),
            'is_b2b' => $isB2b ? 1 : 0,
            'b2b_client_id' => $b2bClientId,
            'b2b_client_name' => $b2bClientName,
        ];
    }

    return [
        'tests' => implode(', ', $selected),
        'amount' => $net > 0 ? $net : ($amount > 0 ? $amount : 0),
        'gross' => $amount,
        'discount' => $discount,
        'paid' => (float)($post['paid'] ?? 0),
        'is_b2b' => $isB2b ? 1 : 0,
        'b2b_client_id' => $b2bClientId,
        'b2b_client_name' => $b2bClientName,
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
    $permissions = [];
    if (!empty($user['permissions'])) {
        $permissions = is_array($user['permissions']) ? $user['permissions'] : (json_decode((string)$user['permissions'], true) ?: []);
    }
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'portal' => $portal,
        'organization_id' => $user['organization_id'] ?? 'ORG-001',
        'branch_id' => $user['branch_id'] ?? null,
        'permissions' => $permissions,
        'is_super_admin' => !empty($user['is_super_admin']) || ($user['email'] ?? '') === 'admin@ldp.com',
    ];
}

function is_super_admin(): bool
{
    $u = current_user();
    return !empty($u['is_super_admin']) || ($u['email'] ?? '') === 'admin@ldp.com';
}

function user_has_permission(string $permission): bool
{
    $user = current_user();
    if (empty($user)) {
        return false;
    }
    // Super Admin or Admin always has all permissions
    if (!empty($user['is_super_admin']) || ($user['portal'] ?? '') === 'admin') {
        return true;
    }
    $perms = $user['permissions'] ?? [];
    if (!is_array($perms) || empty($perms)) {
        // If not explicitly set, grant defaults by portal
        return true;
    }
    return !empty($perms[$permission]);
}

function require_permission(string $permission, string $redirect = ''): void
{
    if (!user_has_permission($permission)) {
        http_response_code(403);
        $home = $redirect !== '' ? $redirect : (PORTALS[current_portal()]['path'] ?? '/');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Permission Denied</title>
        <style>body{font-family:system-ui;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:1.5rem}
        .card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:2rem;max-width:440px;text-align:center}
        a{color:#c2f13c;font-weight:600}</style></head><body><div class="card">
        <h1 style="margin:0 0 .5rem;font-size:1.25rem;color:#f43f5e"><i class="fa-solid fa-lock"></i> Restricted Action</h1>
        <p style="color:#94a3b8;line-height:1.5">You do not have the required permission (<code>' . htmlspecialchars($permission, ENT_QUOTES, 'UTF-8') . '</code>) configured by administrator.</p>
        <p><a href="' . htmlspecialchars($home, ENT_QUOTES, 'UTF-8') . '">Return to dashboard</a> · <a href="/logout.php">Sign out</a></p>
        </div></body></html>';
        exit;
    }
}

function require_auth(?string $portal = null): void
{
    if (empty($_SESSION['user'])) {
        header('Location: /login.php');
        exit;
    }

    $userPortal = portal_from_db((string)($_SESSION['user']['portal'] ?? ''));
    if (!empty($userPortal) && isset(PORTALS[$userPortal])) {
        $_SESSION['portal'] = $userPortal;
    }
}

function current_user(): array
{
    return $_SESSION['user'] ?? [];
}

function current_org_id(): string
{
    return current_user()['organization_id'] ?? 'ORG-001';
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
    $orgId = current_org_id();
    return [
        'cc_pending' => (string)lab_repo()->countByStatuses(['pending', 'collected'], $orgId),
        'samples_pending' => (string)result_repo()->countPendingSamples($orgId),
        'results_verify' => (string)result_repo()->countPendingVerification($orgId),
        'critical' => (string)result_repo()->countCritical($orgId),
        'imaging_pending' => (string)result_repo()->countImagingPending($orgId),
    ];
}
