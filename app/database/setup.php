<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/import_tests_csv.php';

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

        // Seed Tests from project-root tests.csv (replaces hardcoded catalog)
        $csvPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tests.csv';
        $import = import_tests_from_csv($pdo, $csvPath, 'ORG-001');
        foreach ($import['logs'] as $msg) {
            $logs[] = $msg;
        }
        $attached = seed_panel_parameters_for_imported_catalog($pdo, 'ORG-001');
        if ($attached !== []) {
            foreach ($attached as $panel => $info) {
                $logs[] = "{$panel} multi-parameters attached to {$info}.";
            }
        } else {
            $logs[] = 'No panel tests found for parameter seeding.';
        }

        // Seed Packages
        $stmtPkg = $pdo->prepare("INSERT IGNORE INTO packages (id, organization_id, code, name, tests_included, price, regular_price) VALUES (?, 'ORG-001', ?, ?, ?, ?, ?)");
        $packages = [
            ['PKG-01', 'PKG-01', 'Basic Health Panel', 'CBC (Complete Blood Count), FBS (Fasting Blood Sugar), UCE (Urine Complete Examination)', 2200.00, 2550.00],
            ['PKG-02', 'PKG-02', 'Executive Checkup', 'CBC (Complete Blood Count), LFTs (Liver Function Tests), LIPID PROFILE', 6500.00, 8700.00],
            ['PKG-03', 'PKG-03', 'Diabetes Screen', 'FBS (Fasting Blood Sugar), HBA1C (Glycated Hemoglobin), UCE (Urine Complete Examination)', 2800.00, 3200.00],
            ['PKG-04', 'PKG-04', 'Thyroid + CBC', 'TFTs (Thyroid Function Tests) HC, CBC (Complete Blood Count)', 3800.00, 4400.00],
        ];
        foreach ($packages as $pkgRow) {
            $stmtPkg->execute($pkgRow);
        }

        // Seed Lab Entries
        $stmtLab = $pdo->prepare("INSERT IGNORE INTO lab_entries (id, organization_id, branch_id, lab_no, patient_id, patient_name, tests, doctor, route, priority, status, sample_status, amount, paid, discount, branch) VALUES (?, 'ORG-001', 'BR-GULBERG', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $labEntries = [
            ['LAB-01', 'L-2026-0891', 'P-10482', 'Ayesha Khan', 'CBC (Complete Blood Count), FBS (Fasting Blood Sugar)', 'Dr. Fatima Noor', 'Laboratory — Pathology', 'Normal', 'pending', 'pending', 1650.00, 1650.00, 0.00, 'CC-01'],
            ['LAB-02', 'L-2026-0890', 'P-10481', 'Muhammad Ali', 'LFTs (Liver Function Tests)', 'Dr. Usman Malik', 'Laboratory — Biochemistry', 'Urgent', 'collected', 'collected', 2800.00, 1500.00, 200.00, 'CC-01'],
            ['LAB-03', 'L-2026-0889', 'P-10480', 'Sana Ahmed', 'Basic Health Panel', 'Walk-in / Self', 'Laboratory — Pathology', 'Normal', 'completed', 'received', 2200.00, 2200.00, 0.00, 'CC-02'],
            ['LAB-04', 'L-2026-0888', 'P-10479', 'Hassan Raza', 'FBS (Fasting Blood Sugar), HB (Hemoglobin)', 'Dr. Nadia Hussain', 'Laboratory — Biochemistry', 'STAT', 'critical', 'completed', 800.00, 800.00, 0.00, 'CC-01'],
            ['LAB-05', 'L-2026-0887', 'P-10478', 'Ahmed Malik', 'CBC (Complete Blood Count)', 'Dr. Imran Sheikh', 'Laboratory — Hematology', 'Normal', 'pending', 'pending', 1200.00, 0.00, 0.00, 'CC-01'],
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
            ('ORG-001', 'Health LMS Pro Diagnostics', '12-A Main Boulevard, Faisalabad', '+92 42 111 222 333', 'reports@healthlmspro.pk', 'Health LMS Pro — Diagnostic & Laboratory Services', 'Get well soon. Thank you.', 'HLP')
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
            "ALTER TABLE lab_settings ADD COLUMN header_image_position VARCHAR(32) NOT NULL DEFAULT 'left'",
            "ALTER TABLE tests ADD COLUMN normal_value VARCHAR(128) NULL",
            "ALTER TABLE tests ADD COLUMN reference_value VARCHAR(128) NULL",
            "ALTER TABLE tests ADD COLUMN methodology TEXT NULL",
            "ALTER TABLE test_parameters ADD COLUMN section VARCHAR(128) NULL",
            "ALTER TABLE test_parameters ADD COLUMN sub_table TEXT NULL",
            "ALTER TABLE results ADD COLUMN section VARCHAR(128) NULL",
            "ALTER TABLE results ADD COLUMN reference_range VARCHAR(128) NULL",
            "ALTER TABLE results ADD COLUMN sub_table TEXT NULL",
            "ALTER TABLE results ADD COLUMN sort_order INT DEFAULT 0",
            "ALTER TABLE results ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1",
            "ALTER TABLE results ADD COLUMN print_page INT NOT NULL DEFAULT 1",
            "ALTER TABLE tests ADD COLUMN result_type VARCHAR(32) NULL",
            "ALTER TABLE tests ADD COLUMN result_options VARCHAR(255) NULL",
            "ALTER TABLE tests ADD COLUMN report_template VARCHAR(64) NULL",
            "ALTER TABLE patients ADD COLUMN referring_doctor VARCHAR(255) NULL",
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
            section         VARCHAR(128) NULL,
            name            VARCHAR(255) NOT NULL,
            unit            VARCHAR(32),
            normal_value    VARCHAR(128),
            reference_range VARCHAR(128),
            sub_table       TEXT NULL,
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS waste_records (
            id              VARCHAR(64) PRIMARY KEY,
            organization_id VARCHAR(64) NOT NULL,
            title           VARCHAR(255) NOT NULL,
            notes           TEXT,
            record_date     DATE NOT NULL,
            mou_image       LONGBLOB NULL,
            mou_image_mime  VARCHAR(64) NULL,
            slip_image      LONGBLOB NULL,
            slip_image_mime VARCHAR(64) NULL,
            created_by      VARCHAR(128) NULL,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_waste_org_date (organization_id, record_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS doctor_shares (
            id                  VARCHAR(64) PRIMARY KEY,
            organization_id     VARCHAR(64) NOT NULL,
            doctor_name         VARCHAR(255) NOT NULL,
            commission_percent  DECIMAL(5,2) NOT NULL DEFAULT 0,
            notes               TEXT,
            is_active           TINYINT(1) DEFAULT 1,
            created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_doctor_shares_org (organization_id),
            UNIQUE KEY uq_doctor_share_org_name (organization_id, doctor_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed Default Doctor Signatories
        $signatoriesSeed = [
            ['SIG-01', 'ORG-001', 1, 'Dr Alina', "M.B.B.S, M Phill Hematology", 'Consultant Pathologist', 1, 0],
            ['SIG-02', 'ORG-001', 2, 'Dr M Mujeeb Ur Rehman', "M.B.B.S (Pak) R.M.P (PMC)\nPMC Reg # 712493-01-M", 'Consultant Physician', 1, 1],
            ['SIG-03', 'ORG-001', 3, 'IMRAN AFZAL (MLT)', "Medical Lab Technology", 'Head Lab Technologist', 1, 2],
        ];
        $stmtSig = $pdo->prepare("INSERT INTO report_signatories (id, organization_id, slot_number, name, qualifications, designation, is_active, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE name=VALUES(name), qualifications=VALUES(qualifications), designation=VALUES(designation)");
        foreach ($signatoriesSeed as $sRow) {
            $stmtSig->execute($sRow);
        }

        // Seed Full CBC Results for Lab Entry L-2026-0891
        $cbcResultsSeed = [
            ['R-CBC-01', 'L-2026-0891', 'Ayesha Khan', 'CBC (Complete Blood Count)', 'ERYTHROCYTES', 'Hemoglobin (HB)', '14.9', 'g/dl', '12.0 - 16.5', "New born (HB): 15.2 - 23.5\nBaby (HB): 10.1 - 12.8\nInfant (HB): 10.8 - 12.9\nChildren (HB): 11.1 - 14.3", 'normal', 1],
            ['R-CBC-02', 'L-2026-0891', 'Ayesha Khan', 'CBC (Complete Blood Count)', 'ERYTHROCYTES', 'Total RBCs', '6.03', 'X10^12/L', '4.50 - 6.50', null, 'normal', 2],
            ['R-CBC-03', 'L-2026-0891', 'Ayesha Khan', 'CBC (Complete Blood Count)', 'ABSOLUTE VALUES', 'HCT (Hematocrit)', '45.9', '%', '38.0 - 52.0', null, 'normal', 3],
            ['R-CBC-04', 'L-2026-0891', 'Ayesha Khan', 'CBC (Complete Blood Count)', 'ABSOLUTE VALUES', 'MCV', '76.2', 'Fl', '75.0 - 95.0', null, 'normal', 4],
            ['R-CBC-05', 'L-2026-0891', 'Ayesha Khan', 'CBC (Complete Blood Count)', 'ABSOLUTE VALUES', 'MCH', '26.3', 'Pg', '27.0 - 32.0', null, 'L', 5],
            ['R-CBC-06', 'L-2026-0891', 'Ayesha Khan', 'CBC (Complete Blood Count)', 'ABSOLUTE VALUES', 'MCHC', '34.5', 'g/dl', '30.0 - 35.0', null, 'normal', 6],
            ['R-CBC-07', 'L-2026-0891', 'Ayesha Khan', 'CBC (Complete Blood Count)', 'THROMBOCYTE', 'Platelet Count', '267', 'x10^3/µL', '150 - 400', null, 'normal', 7],
            ['R-CBC-08', 'L-2026-0891', 'Ayesha Khan', 'CBC (Complete Blood Count)', 'Differential Leukocytes Count', 'WBC (TLC)', '9.70', 'K/uL', '4.0 - 11.0', null, 'normal', 8],
            ['R-CBC-09', 'L-2026-0891', 'Ayesha Khan', 'CBC (Complete Blood Count)', 'Differential Leukocytes Count', 'Neutrophils', '55', '%', '40 - 75', null, 'normal', 9],
            ['R-CBC-10', 'L-2026-0891', 'Ayesha Khan', 'CBC (Complete Blood Count)', 'Differential Leukocytes Count', 'Lymphocytes', '35', '%', '20 - 50', null, 'normal', 10],
            ['R-CBC-11', 'L-2026-0891', 'Ayesha Khan', 'CBC (Complete Blood Count)', 'Differential Leukocytes Count', 'Monocytes', '06', '%', '02 - 10', null, 'normal', 11],
            ['R-CBC-12', 'L-2026-0891', 'Ayesha Khan', 'CBC (Complete Blood Count)', 'Differential Leukocytes Count', 'Eosinophils', '04', '%', '01 - 06', null, 'normal', 12],
            ['R-CBC-13', 'L-2026-0891', 'Ayesha Khan', 'CBC (Complete Blood Count)', 'Differential Leukocytes Count', 'ESR', '14', 'mm/1stHr', '0 - 18', null, 'normal', 13],
        ];
        $stmtCbcRes = $pdo->prepare("INSERT INTO results (id, lab_no, patient, test, section, parameter, value, unit, reference_range, sub_table, flag, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE section=VALUES(section), parameter=VALUES(parameter), value=VALUES(value), unit=VALUES(unit), reference_range=VALUES(reference_range), sub_table=VALUES(sub_table), flag=VALUES(flag), sort_order=VALUES(sort_order)");
        foreach ($cbcResultsSeed as $rRow) {
            $stmtCbcRes->execute($rRow);
        }

        $logs[] = "Test catalog loaded from tests.csv (source of truth).";
        $logs[] = "mock_tests in bootstrap.php loads from DB via test_repo.";
        $logs[] = "Default doctor signatories seeded for report footer.";

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
