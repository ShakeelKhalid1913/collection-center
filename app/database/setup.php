<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

require_once __DIR__ . '/Database.php';

function runSetup(): array  
{
    $config = require __DIR__ . '/../config/database.php';
    $logs = [];

    try {
        // Step 1: Connect to server without DB selected
        $logs[] = "Connecting to MariaDB server at {$config['host']}:{$config['port']}...";
        $serverPdo = Database::connectServer($config);

        // Step 2: Create Database if not exists
        $dbName = $config['dbname'];
        $logs[] = "Creating database `{$dbName}` if not exists...";
        $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Step 3: Connect to newly created DB & Run Schema
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $dbName, $config['charset']);
        $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);

        $logs[] = "Executing database schema (schema.sql)...";
        $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
        $pdo->exec($schemaSql);
        $logs[] = "Database schema initialized successfully.";

        // Step 4: Seed Data
        $logs[] = "Seeding default data...";

        // Seed Organization & Branch
        $pdo->exec("INSERT IGNORE INTO organizations (id, name) VALUES ('ORG-001', 'Health LMS Pro Diagnostics')");
        $pdo->exec("INSERT IGNORE INTO branches (id, organization_id, code, name, branch_type, address, phone) VALUES 
            ('BR-GULBERG', 'ORG-001', 'CC-01', 'Gulberg Collection Point', 'collection_center', '12-A Main Boulevard, Faisalabad', '+92 42 111 222 333'),
            ('BR-MAIN-LAB', 'ORG-001', 'LAB-01', 'Main Pathology Laboratory', 'main_lab', 'Central Lab Tower, Faisalabad', '+92 42 111 222 444'),
            ('BR-IMAGING', 'ORG-001', 'IMG-01', 'Diagnostic Imaging Center', 'imaging', 'Imaging Block, Faisalabad', '+92 42 111 222 555')
        ");

        // Seed Default Users (Password: 1913)
        $defaultPassword = password_hash('1913', PASSWORD_BCRYPT);
        $stmtUser = $pdo->prepare("INSERT IGNORE INTO users (id, organization_id, branch_id, email, password_hash, name, role, portal, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        
        $defaultUsers = [
            ['USR-01', 'ORG-001', 'BR-GULBERG', 'staff@citylab.pk', $defaultPassword, 'Collection Staff', 'Collection Center Staff', 'collection_center'],
            ['USR-02', 'ORG-001', 'BR-MAIN-LAB', 'lab@citylab.pk', $defaultPassword, 'Dr. Main Lab', 'Laboratory Staff', 'main_lab'],
            ['USR-03', 'ORG-001', 'BR-IMAGING', 'imaging@citylab.pk', $defaultPassword, 'Radiology Dept', 'Diagnostic Center Staff', 'imaging'],
            ['USR-04', 'ORG-001', 'BR-MAIN-LAB', 'admin@citylab.pk', $defaultPassword, 'System Administrator', 'Admin', 'admin'],
        ];
        foreach ($defaultUsers as $userRow) {
            $stmtUser->execute($userRow);
        }
        $logs[] = "Default staff users seeded with password '1913': staff@citylab.pk, lab@citylab.pk, imaging@citylab.pk, admin@citylab.pk.";

        // Seed Patients
        $stmtPatient = $pdo->prepare("INSERT IGNORE INTO patients (id, organization_id, patient_no, title, full_name, relation, phone, age, gender, cnic, blood_group, email, address, internal_notes, branch) VALUES (?, 'ORG-001', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $patients = [
            ['P-10482', 'P-10482', 'Mrs', 'Ayesha Khan', 'Self', '0300-1122334', 34, 'Female', '35202-1234567-1', 'B+', 'ayesha.k@email.com', 'House 12, Block C, Gulberg III, Faisalabad', 'Prefers morning slots', 'CC-01'],
            ['P-10481', 'P-10481', 'Mr', 'Muhammad Ali', 'Self', '0321-9988776', 45, 'Male', '35201-7654321-9', 'O+', '', '45 Model Town Link Road, Faisalabad', '', 'CC-01'],
            ['P-10480', 'P-10480', 'Ms', 'Sana Ahmed', 'Daughter of', '0333-4455667', 28, 'Female', '35203-9876543-2', 'A+', 'sana.ahmed@email.com', 'Flat 4B, Johar Town, Faisalabad', 'Corporate account: Apex Foods', 'CC-02'],
            ['P-10479', 'P-10479', 'Mr', 'Hassan Raza', 'Self', '0345-2233445', 52, 'Male', '35204-1122334-4', 'AB+', 'hassan.raza@email.com', '19 DHA Phase 5, Faisalabad', 'Diabetic — fasting tests only', 'CC-01'],
            ['P-10478', 'P-10478', 'Baby', 'Ahmed Malik', 'Son of', '0301-5566778', 2, 'Male', '', 'Unknown', '', 'Canal Road, Faisalabad', 'Pediatric draw — experienced phlebotomist', 'CC-01'],
        ];
        foreach ($patients as $pRow) {
            $stmtPatient->execute($pRow);
        }

        // Seed Tests
        $stmtTest = $pdo->prepare("INSERT IGNORE INTO tests (id, organization_id, code, name, category, price, sample_type, unit, normal_range) VALUES (?, 'ORG-001', ?, ?, ?, ?, ?, ?, ?)");
        $tests = [
            ['TST-01', 'CBC', 'Complete Blood Count', 'Hematology', 1200.00, 'Blood', '—', '—'],
            ['TST-02', 'HB', 'Hemoglobin', 'Hematology', 350.00, 'Blood', 'g/dL', '13–17'],
            ['TST-03', 'ESR', 'ESR', 'Hematology', 400.00, 'Blood', 'mm/hr', '0–20'],
            ['TST-04', 'FBS', 'Fasting Blood Sugar', 'Biochemistry', 450.00, 'Blood', 'mg/dL', '70–100'],
            ['TST-05', 'RBS', 'Random Blood Sugar', 'Biochemistry', 400.00, 'Blood', 'mg/dL', '70–140'],
            ['TST-06', 'LFT', 'Liver Function Test', 'Biochemistry', 2800.00, 'Blood', '—', '—'],
            ['TST-07', 'RFT', 'Renal Function Test', 'Biochemistry', 2500.00, 'Blood', '—', '—'],
            ['TST-08', 'LIPID', 'Lipid Profile', 'Biochemistry', 2200.00, 'Blood', '—', '—'],
            ['TST-09', 'TFT', 'Thyroid Profile (T3/T4/TSH)', 'Special Chemistry', 3200.00, 'Blood', '—', '—'],
            ['TST-10', 'UDR', 'Urine Complete (DR)', 'Chemistry', 500.00, 'Urine', '—', '—'],
            ['TST-11', 'CXR', 'Chest X-Ray PA', 'Radiology', 1800.00, '—', '—', '—'],
            ['TST-12', 'UA', 'Uric Acid', 'Biochemistry', 500.00, 'Blood', 'mg/dL', '3.5–7.2'],
            ['TST-13', 'CREAT', 'Creatinine', 'Biochemistry', 550.00, 'Blood', 'mg/dL', '0.6–1.3'],
            ['TST-14', 'UREA', 'Blood Urea', 'Biochemistry', 500.00, 'Blood', 'mg/dL', '15–40'],
            ['TST-15', 'HBA1C', 'HbA1c', 'Special Chemistry', 1800.00, 'Blood', '%', '4.0–5.6'],
            ['TST-16', 'CRP', 'C-Reactive Protein', 'Special Chemistry', 1200.00, 'Blood', 'mg/L', '<5'],
            ['TST-17', 'VITD', 'Vitamin D (25-OH)', 'Special Chemistry', 3500.00, 'Blood', 'ng/mL', '30–100'],
            ['TST-18', 'B12', 'Vitamin B12', 'Special Chemistry', 2800.00, 'Blood', 'pg/mL', '200–900'],
            ['TST-19', 'SGPT', 'SGPT (ALT)', 'Biochemistry', 450.00, 'Blood', 'U/L', '7–56'],
            ['TST-20', 'SGOT', 'SGOT (AST)', 'Biochemistry', 450.00, 'Blood', 'U/L', '10–40'],
            ['TST-21', 'BIL', 'Bilirubin Total', 'Biochemistry', 500.00, 'Blood', 'mg/dL', '0.1–1.2'],
            ['TST-22', 'CHOL', 'Cholesterol Total', 'Chemistry', 600.00, 'Blood', 'mg/dL', '<200'],
            ['TST-23', 'TG', 'Triglycerides', 'Chemistry', 600.00, 'Blood', 'mg/dL', '<150'],
            ['TST-24', 'WBC', 'White Blood Cell Count', 'Hematology', 400.00, 'Blood', '10³/µL', '4–11'],
            ['TST-25', 'PLT', 'Platelet Count', 'Hematology', 400.00, 'Blood', '10³/µL', '150–450'],
            ['TST-26', 'PAP', 'Pap Smear', 'Histopathology', 2500.00, 'Slide', '—', '—'],
            ['TST-27', 'BIOPSY', 'Tissue Biopsy (routine)', 'Histopathology', 4500.00, 'Tissue', '—', '—'],
            ['TST-28', 'C/S', 'Culture & Sensitivity', 'Microbiology', 2200.00, 'Swab/Fluid', '—', '—'],
            ['TST-29', 'BLOOD-C', 'Blood Culture', 'Microbiology', 3500.00, 'Blood', '—', '—'],
            ['TST-30', 'AFB', 'AFB Smear', 'Microbiology', 800.00, 'Sputum', '—', '—'],
        ];
        foreach ($tests as $tRow) {
            $stmtTest->execute($tRow);
        }

        // Seed Packages
        $stmtPkg = $pdo->prepare("INSERT IGNORE INTO packages (id, organization_id, code, name, tests_included, price, regular_price) VALUES (?, 'ORG-001', ?, ?, ?, ?, ?)");
        $packages = [
            ['PKG-01', 'PKG-01', 'Basic Health Panel', 'CBC, FBS, Urine DR', 2200.00, 2550.00],
            ['PKG-02', 'PKG-02', 'Executive Checkup', 'CBC, LFT, Lipid Profile, RFT', 6500.00, 8700.00],
            ['PKG-03', 'PKG-03', 'Diabetes Screen', 'FBS, HbA1c, Urine DR', 2800.00, 3200.00],
            ['PKG-04', 'PKG-04', 'Thyroid + CBC', 'TFT, CBC', 3800.00, 4400.00],
        ];
        foreach ($packages as $pkgRow) {
            $stmtPkg->execute($pkgRow);
        }

        // Seed Lab Entries
        $stmtLab = $pdo->prepare("INSERT IGNORE INTO lab_entries (id, organization_id, branch_id, lab_no, patient_id, patient_name, tests, doctor, route, priority, status, sample_status, amount, paid, discount, branch) VALUES (?, 'ORG-001', 'BR-GULBERG', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $labEntries = [
            ['LAB-01', 'L-2026-0891', 'P-10482', 'Ayesha Khan', 'CBC, FBS', 'Dr. Fatima Noor', 'Laboratory — Pathology', 'Normal', 'pending', 'pending', 1650.00, 1650.00, 0.00, 'CC-01'],
            ['LAB-02', 'L-2026-0890', 'P-10481', 'Muhammad Ali', 'LFT', 'Dr. Usman Malik', 'Laboratory — Biochemistry', 'Urgent', 'collected', 'collected', 2800.00, 1500.00, 200.00, 'CC-01'],
            ['LAB-03', 'L-2026-0889', 'P-10480', 'Sana Ahmed', 'Basic Health Panel', 'Walk-in / Self', 'Laboratory — Pathology', 'Normal', 'completed', 'received', 2200.00, 2200.00, 0.00, 'CC-02'],
            ['LAB-04', 'L-2026-0888', 'P-10479', 'Hassan Raza', 'FBS, HB', 'Dr. Nadia Hussain', 'Laboratory — Biochemistry', 'STAT', 'critical', 'completed', 800.00, 800.00, 0.00, 'CC-01'],
            ['LAB-05', 'L-2026-0887', 'P-10478', 'Ahmed Malik', 'CBC', 'Dr. Imran Sheikh', 'Laboratory — Hematology', 'Normal', 'pending', 'pending', 1200.00, 0.00, 0.00, 'CC-01'],
        ];
        foreach ($labEntries as $lRow) {
            $stmtLab->execute($lRow);
        }

        // Seed Samples
        $stmtSample = $pdo->prepare("INSERT IGNORE INTO samples (id, lab_no, patient, sample, status, received_at) VALUES (?, ?, ?, ?, ?, ?)");
        $samples = [
            ['S-441', 'L-2026-0891', 'Ayesha Khan', 'Blood', 'received', '2026-09-16 09:12'],
            ['S-440', 'L-2026-0890', 'Muhammad Ali', 'Blood', 'processing', '2026-09-16 08:45'],
            ['S-439', 'L-2026-0889', 'Sana Ahmed', 'Blood', 'completed', '2026-09-15 16:20'],
        ];
        foreach ($samples as $sRow) {
            $stmtSample->execute($sRow);
        }

        // Seed Results Pending
        $stmtResult = $pdo->prepare("INSERT IGNORE INTO results (id, lab_no, patient, test, due) VALUES (?, ?, ?, ?, ?)");
        $results = [
            ['R-101', 'L-2026-0891', 'Ayesha Khan', 'CBC', 'Today'],
            ['R-102', 'L-2026-0890', 'Muhammad Ali', 'LFT', 'Today'],
        ];
        foreach ($results as $rRow) {
            $stmtResult->execute($rRow);
        }

        // Seed Imaging Scans
        $stmtImg = $pdo->prepare("INSERT IGNORE INTO imaging_scans (id, scan_no, patient, modality, study, status, scan_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $imagingScans = [
            ['IMG-01', 'XR-2026-120', 'Hassan Raza', 'X-Ray', 'Chest PA', 'pending', '2026-09-16'],
            ['IMG-02', 'CT-2026-045', 'Ayesha Khan', 'CT Scan', 'Brain Plain', 'completed', '2026-09-15'],
            ['IMG-03', 'US-2026-088', 'Sana Ahmed', 'Ultrasound', 'Abdomen', 'pending', '2026-09-16'],
            ['IMG-04', 'ECG-2026-031', 'Muhammad Ali', 'ECG', '12-Lead', 'pending', '2026-09-16'],
        ];
        foreach ($imagingScans as $imgRow) {
            $stmtImg->execute($imgRow);
        }

        // Seed Settings
        $pdo->exec("INSERT IGNORE INTO lab_settings (organization_id, lab_name, address, phone, email, header_text, footer_text, logo_text) VALUES 
            ('ORG-001', 'Health LMS Pro Diagnostics', '12-A Main Boulevard, Faisalabad', '+92 42 111 222 333', 'reports@healthlmspro.pk', 'Health LMS Pro — Diagnostic & Laboratory Services', 'This report is electronically verified. For queries call reception.', 'HLP')
        ");

        // Seed Collection Centers
        $pdo->exec("INSERT IGNORE INTO collection_centers (id, name, code, patients_today) VALUES
            ('CC-01', 'Gulberg Collection Point', 'CC-01', 18),
            ('CC-02', 'Model Town Collection Point', 'CC-02', 14)
        ");

        // Ensure imaging columns exist on older DBs
        foreach ([
            "ALTER TABLE imaging_scans ADD COLUMN patient_id VARCHAR(64) NULL",
            "ALTER TABLE imaging_scans ADD COLUMN radiologist VARCHAR(255) NULL",
            "ALTER TABLE imaging_scans ADD COLUMN clinical_notes TEXT NULL",
            "ALTER TABLE imaging_scans ADD COLUMN findings TEXT NULL",
            "ALTER TABLE imaging_scans ADD COLUMN impression TEXT NULL",
            "ALTER TABLE lab_settings ADD COLUMN bill_header_text TEXT NULL",
            "ALTER TABLE lab_settings ADD COLUMN bill_footer_text TEXT NULL",
            "ALTER TABLE lab_settings ADD COLUMN header_image LONGBLOB NULL",
            "ALTER TABLE lab_settings ADD COLUMN header_image_mime VARCHAR(64) NULL",
            "ALTER TABLE lab_settings ADD COLUMN header_image_ver INT NOT NULL DEFAULT 0",
            "ALTER TABLE tests ADD COLUMN normal_value VARCHAR(128) NULL",
            "ALTER TABLE tests ADD COLUMN reference_value VARCHAR(128) NULL",
            "ALTER TABLE tests ADD COLUMN methodology TEXT NULL",
        ] as $alter) {
            try {
                $pdo->exec($alter);
            } catch (PDOException $ignored) {
                // Column already exists
            }
        }

        // Ensure new tables exist on older DBs
        $pdo->exec("CREATE TABLE IF NOT EXISTS test_parameters (
            id              VARCHAR(64) PRIMARY KEY,
            test_id         VARCHAR(64) NOT NULL,
            name            VARCHAR(255) NOT NULL,
            unit            VARCHAR(32),
            normal_value    VARCHAR(128),
            reference_range VARCHAR(128),
            sort_order      INT DEFAULT 0,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_test_params_test (test_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS report_signatories (
            id              VARCHAR(64) PRIMARY KEY,
            organization_id VARCHAR(64) NOT NULL,
            slot_number     INT NOT NULL DEFAULT 1,
            name            VARCHAR(255) NOT NULL,
            qualifications  TEXT,
            designation     VARCHAR(255),
            is_active       TINYINT(1) DEFAULT 1,
            sort_order      INT DEFAULT 0,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_signatories_org (organization_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Re-seed expanded tests (INSERT IGNORE) so existing DBs get Uric Acid etc.
        $stmtTestExtra = $pdo->prepare("INSERT IGNORE INTO tests (id, organization_id, code, name, category, price, sample_type, unit, normal_range) VALUES (?, 'ORG-001', ?, ?, ?, ?, ?, ?, ?)");
        foreach ($tests as $tRow) {
            $stmtTestExtra->execute($tRow);
        }

        // Align old category names to client departments
        $pdo->exec("UPDATE tests SET category = 'Special Chemistry' WHERE category IN ('Hormones','Immunology')");
        $pdo->exec("UPDATE tests SET category = 'Chemistry' WHERE category IN ('Clinical Pathology')");
        $logs[] = "Catalog synced (departments: Hematology, Chemistry, Biochemistry, Special Chemistry, Histopathology, Microbiology).";

        $logs[] = "All tables & seed data inserted successfully!";
        return ['success' => true, 'logs' => $logs];

    } catch (PDOException $e) {
        $logs[] = "SETUP ERROR: " . $e->getMessage();
        return ['success' => false, 'logs' => $logs];
    }
}

// Invoked via CLI, direct PHP, or front controller (/database/setup.php)
$isSetupEntry = (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'setup.php')
    || str_ends_with(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/setup.php')
    || (PHP_SAPI === 'cli' && realpath($_SERVER['argv'][0] ?? '') === realpath(__FILE__));

if ($isSetupEntry) {
    $res = runSetup();
    if (PHP_SAPI === 'cli') {
        foreach ($res['logs'] as $msg) {
            echo $msg . PHP_EOL;
        }
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><title>Database Setup</title><style>body{font-family:sans-serif;padding:2rem;background:#f8fafc;color:#1e293b}.card{background:#fff;padding:2rem;border-radius:8px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1)}pre{background:#f1f5f9;padding:1rem;border-radius:6px;overflow-x:auto}.success{color:#16a34a;font-weight:bold}.error{color:#dc2626;font-weight:bold}</style></head><body><div class="card">';
        echo '<h1>MariaDB Setup — Health LMS Pro</h1>';
        if ($res['success']) {
            echo '<p class="success">Database setup completed successfully!</p>';
        } else {
            echo '<p class="error">Setup encountered an error.</p>';
        }
        echo '<pre>' . htmlspecialchars(implode("\n", $res['logs'])) . '</pre>';
        echo '<p><a href="/login.php">Go to Login</a> | <a href="/signup.php">Go to Signup</a></p>';
        echo '</div></body></html>';
    }
}
