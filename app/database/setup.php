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
        $pdo->exec("INSERT IGNORE INTO organizations (id, name) VALUES ('ORG-001', 'Lab Dash Pro Diagnostics')");
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
            ('ORG-001', 'Lab Dash Pro Diagnostics', '12-A Main Boulevard, Faisalabad', '+92 42 111 222 333', 'reports@labdashpro.pk', 'Lab Dash Pro — Diagnostic & Laboratory Services', 'Get well soon. Thank you.', 'LDP')
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
            "ALTER TABLE lab_settings ADD COLUMN header_layout_json TEXT NULL",
            "ALTER TABLE lab_settings ADD COLUMN footer_image LONGBLOB NULL",
            "ALTER TABLE lab_settings ADD COLUMN footer_image_mime VARCHAR(64) NULL",
            "ALTER TABLE lab_settings ADD COLUMN footer_image_ver INT NOT NULL DEFAULT 0",
            "ALTER TABLE lab_settings ADD COLUMN footer_layout_json TEXT NULL",
            "ALTER TABLE lab_settings ADD COLUMN report_font VARCHAR(64) NOT NULL DEFAULT 'times_bold_italic'",
            "ALTER TABLE lab_settings ADD COLUMN bill_font VARCHAR(64) NOT NULL DEFAULT 'times_bold_italic'",
            "ALTER TABLE tests ADD COLUMN normal_value VARCHAR(128) NULL",
            "ALTER TABLE tests ADD COLUMN reference_value VARCHAR(128) NULL",
            "ALTER TABLE tests ADD COLUMN methodology TEXT NULL",
            "ALTER TABLE test_parameters ADD COLUMN section VARCHAR(128) NULL",
            "ALTER TABLE test_parameters ADD COLUMN sub_table TEXT NULL",
            "ALTER TABLE test_parameters ADD COLUMN result_note TEXT NULL",
            "ALTER TABLE results ADD COLUMN section VARCHAR(128) NULL",
            "ALTER TABLE results ADD COLUMN reference_range VARCHAR(128) NULL",
            "ALTER TABLE results ADD COLUMN sub_table TEXT NULL",
            "ALTER TABLE results ADD COLUMN result_note TEXT NULL",
            "ALTER TABLE results ADD COLUMN sort_order INT DEFAULT 0",
            "ALTER TABLE results ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1",
            "ALTER TABLE results ADD COLUMN print_page INT NOT NULL DEFAULT 1",
            "ALTER TABLE tests ADD COLUMN result_type VARCHAR(32) NULL",
            "ALTER TABLE tests ADD COLUMN result_options VARCHAR(255) NULL",
            "ALTER TABLE tests ADD COLUMN report_template VARCHAR(64) NULL",
            "ALTER TABLE patients ADD COLUMN referring_doctor VARCHAR(255) NULL",
            "ALTER TABLE users ADD COLUMN permissions TEXT NULL",
            "ALTER TABLE lab_entries ADD COLUMN transit_status VARCHAR(64) DEFAULT 'collected'",
            "ALTER TABLE lab_entries ADD COLUMN transit_updated_at TIMESTAMP NULL",
            "ALTER TABLE lab_entries ADD COLUMN barcode VARCHAR(64) NULL",
        ] as $alter) {
            try {
                $pdo->exec($alter);
            } catch (PDOException $ignored) {
                // Column already exists
            }
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
                id              VARCHAR(64) PRIMARY KEY,
                organization_id VARCHAR(64) NOT NULL,
                user_id         VARCHAR(64) NULL,
                user_name       VARCHAR(255) NULL,
                portal          VARCHAR(64) NULL,
                action          VARCHAR(64) NOT NULL,
                entity_type     VARCHAR(64) NOT NULL,
                entity_id       VARCHAR(64) NULL,
                details         TEXT NULL,
                ip_address      VARCHAR(64) NULL,
                created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_audit_org (organization_id),
                INDEX idx_audit_action (action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Throwable $ignored) {}

        try {
            $pdo->exec("UPDATE lab_settings SET bill_footer_text = 'Get well soon.' WHERE bill_footer_text IS NULL OR bill_footer_text = '' OR bill_footer_text LIKE '%electronically verified%' OR bill_footer_text LIKE '%queries call reception%'");
            $pdo->exec("UPDATE lab_settings SET footer_text = 'Get well soon.' WHERE footer_text LIKE '%electronically verified%' OR footer_text LIKE '%queries call reception%'");
        } catch (\Throwable $ignored) {}

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

        $pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
            id              VARCHAR(64) PRIMARY KEY,
            organization_id VARCHAR(64) NOT NULL DEFAULT 'ORG-001',
            branch          VARCHAR(64) NOT NULL DEFAULT 'CC-01',
            title           VARCHAR(255) NOT NULL,
            category        VARCHAR(100) NOT NULL DEFAULT 'Other',
            amount          DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            payment_mode    VARCHAR(50) NOT NULL DEFAULT 'Cash',
            receipt_no      VARCHAR(100) NULL,
            notes           TEXT NULL,
            expense_date    DATE NOT NULL,
            created_by      VARCHAR(64) NULL,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_expenses_org_date (organization_id, expense_date),
            INDEX idx_expenses_branch (branch)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_items (
            id              VARCHAR(64) PRIMARY KEY,
            organization_id VARCHAR(64) NOT NULL DEFAULT 'ORG-001',
            name            VARCHAR(255) NOT NULL,
            category        VARCHAR(100) NOT NULL DEFAULT 'Reagent',
            unit            VARCHAR(50) NOT NULL DEFAULT 'Tests',
            quantity        INT NOT NULL DEFAULT 0,
            min_level       INT NOT NULL DEFAULT 10,
            unit_price      DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            supplier        VARCHAR(255) NULL,
            expiry_date     DATE NULL,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_inventory_org (organization_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_returns (
            id              VARCHAR(64) PRIMARY KEY,
            organization_id VARCHAR(64) NOT NULL DEFAULT 'ORG-001',
            item_id         VARCHAR(64) NOT NULL,
            item_name       VARCHAR(255) NOT NULL,
            quantity        INT NOT NULL DEFAULT 1,
            reason          VARCHAR(255) NOT NULL,
            return_date     DATE NOT NULL,
            refund_amount   DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            supplier        VARCHAR(255) NULL,
            status          VARCHAR(50) NOT NULL DEFAULT 'Completed',
            created_by      VARCHAR(64) NULL,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_returns_org (organization_id),
            INDEX idx_returns_item (item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS report_templates (
            id              VARCHAR(64) PRIMARY KEY,
            organization_id VARCHAR(64) NOT NULL DEFAULT 'ORG-001',
            title           VARCHAR(255) NOT NULL,
            department      VARCHAR(100) NOT NULL DEFAULT 'General',
            content         MEDIUMTEXT NOT NULL,
            is_private      TINYINT(1) NOT NULL DEFAULT 0,
            created_by      VARCHAR(64) NULL,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_templates_org (organization_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS due_payments (
            id              VARCHAR(64) PRIMARY KEY,
            organization_id VARCHAR(64) NOT NULL DEFAULT 'ORG-001',
            lab_no          VARCHAR(64) NOT NULL,
            patient_id      VARCHAR(64) NOT NULL,
            patient_name    VARCHAR(255) NOT NULL,
            amount_paid     DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            previous_due    DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            remaining_due   DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            payment_mode    VARCHAR(50) NOT NULL DEFAULT 'Cash',
            receipt_no      VARCHAR(100) NULL,
            notes           TEXT NULL,
            collected_by    VARCHAR(64) NULL,
            payment_date    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_due_payments_org (organization_id),
            INDEX idx_due_payments_lab (lab_no),
            INDEX idx_due_payments_patient (patient_id)
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

        // Seed Inventory Items
        $inventorySeed = [
            ['STK-001', 'ORG-001', 'CBC Diluent & Lyse Reagent Pack (5L)', 'Hematology Reagents', 'Packs', 12, 5, 14500.00, 'Sysmex Pakistan', date('Y-m-d', strtotime('+8 months'))],
            ['STK-002', 'ORG-001', 'Blood Glucose Test Strips (Pack of 100)', 'Consumables', 'Boxes', 25, 10, 3200.00, 'Roche Diagnostics', date('Y-m-d', strtotime('+12 months'))],
            ['STK-003', 'ORG-001', 'EDTA Lavender Top Vacuum Tubes (K2) 3ml', 'Vials & Tubes', 'Trays (100)', 4, 8, 2400.00, 'BD Vacutainer', date('Y-m-d', strtotime('+18 months'))],
            ['STK-004', 'ORG-001', 'Serum Clot Activator Red Top Tubes 5ml', 'Vials & Tubes', 'Trays (100)', 15, 6, 2200.00, 'BD Vacutainer', date('Y-m-d', strtotime('+14 months'))],
            ['STK-005', 'ORG-001', 'Lipid Profile Enzymatic Reagent Kit', 'Biochemistry', 'Kits', 3, 4, 18000.00, 'Merck Clinical', date('Y-m-d', strtotime('+4 months'))],
            ['STK-006', 'ORG-001', 'Uric Acid Liquid Stable Reagent 100ml', 'Biochemistry', 'Bottles', 6, 3, 5500.00, 'Randox Laboratories', date('Y-m-d', strtotime('+6 months'))],
            ['STK-007', 'ORG-001', 'Disposable Sterile Blood Lancets (200s)', 'Consumables', 'Boxes', 18, 5, 850.00, 'MediSafe Medical', date('Y-m-d', strtotime('+24 months'))],
            ['STK-008', 'ORG-001', 'Urine 10-Parameter Test Strips', 'Consumables', 'Bottles', 2, 5, 2800.00, 'Siemens Healthineers', date('Y-m-d', strtotime('+2 months'))],
        ];
        $stmtInv = $pdo->prepare("INSERT IGNORE INTO inventory_items (id, organization_id, name, category, unit, quantity, min_level, unit_price, supplier, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($inventorySeed as $inv) {
            $stmtInv->execute($inv);
        }

        // Seed Report Templates
        $templatesSeed = [
            [
                'TPL-US-01', 'ORG-001', 'Ultrasound Whole Abdomen & Pelvis (Normal)', 'Radiology',
                "LIVER: Normal in size, shape and acoustic texture. No focal mass lesion or intrahepatic biliary dilatation seen.\n\nGALLBLADDER: Normal in size, thin-walled, acoustic lumen is clear. No calculus or mass noted.\n\nPANCREAS & SPLEEN: Normal in size and homogenous texture. No mass lesion noted.\n\nKIDNEYS: Both kidneys are normal in size, position and cortical thickness. Normal corticomedullary differentiation preserved. No calculus or hydronephrosis seen.\n\nURINARY BLADDER: Well distended, smooth wall, lumen clear.\n\nIMPRESSION: Normal ultrasound study of whole abdomen and pelvis.",
                0, 'admin'
            ],
            [
                'TPL-XR-01', 'ORG-001', 'Chest X-Ray PA View (Normal Clinical Finding)', 'Radiology',
                "CHEST PA VIEW:\n\n- Lung fields: Clear with normal bronchovascular markings. No focal airspace consolidation, mass, or cavity noted.\n- Costophrenic & cardiophrenic angles: Sharp and clear bilaterally.\n- Cardiac silhouette: Normal in size and contour. Cardiothoracic ratio is within normal limits (< 50%).\n- Hilar structures & mediastinum: Normal appearance.\n- Bony cage & soft tissues: Intact.\n\nIMPRESSION: Normal radiological study of chest.",
                0, 'admin'
            ],
            [
                'TPL-ECG-01', 'ORG-001', '12-Lead Electrocardiogram (Normal Sinus Rhythm)', 'Cardiology',
                "12-LEAD ECG FINDINGS:\n\n- Rhythm: Normal Sinus Rhythm\n- Heart Rate: 72 bpm\n- PR Interval: 0.16 sec (Normal: 0.12 - 0.20s)\n- QRS Duration: 0.08 sec (Normal: < 0.10s)\n- QTc: 410 ms\n- Axis: Normal cardiac axis (+45°)\n- ST-T wave changes: No significant ST elevation/depression or T wave inversion.\n\nIMPRESSION: Normal 12-lead Electrocardiogram (ECG).",
                0, 'admin'
            ],
            [
                'TPL-HISTO-01', 'ORG-001', 'Histopathology Biopsy Routine Narrative', 'Histopathology',
                "GROSS EXAMINATION:\nReceived specimen labeled as biopsy in formalin container consisting of grayish-white soft tissue pieces measuring 1.2 x 0.8 x 0.4 cm. Entire tissue submitted for processing.\n\nMICROSCOPIC EXAMINATION:\nSections show stratified squamous epithelium with underlying fibrovascular stroma. Mild non-specific chronic inflammatory infiltrate composed of mature lymphocytes and plasma cells is noted. No evidence of cellular atypia, dysplasia, or malignancy seen in the examined sections.\n\nDIAGNOSIS: Non-specific chronic inflammation. Negative for malignancy.",
                1, 'admin'
            ],
            [
                'TPL-MICRO-01', 'ORG-001', 'Urine Culture & Sensitivity (No Growth 48 Hrs)', 'Microbiology',
                "SPECIMEN: Clean catch mid-stream urine (MSU)\n\nCULTURE & SENSITIVITY:\n- Sample inoculated on CLED and MacConkey agar plates and incubated aerobically at 37°C for 48 hours.\n\nRESULT:\nNo bacterial pathogen isolated after 48 hours of aerobic incubation at 37°C (< 1,000 CFU/ml).\n\nCOMMENT: Sterile urine culture.",
                0, 'admin'
            ],
        ];
        $stmtTpl = $pdo->prepare("INSERT IGNORE INTO report_templates (id, organization_id, title, department, content, is_private, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($templatesSeed as $tpl) {
            $stmtTpl->execute($tpl);
        }

        // Seed Sample Expenses
        $expensesSeed = [
            ['EXP-001', 'ORG-001', 'CC-01', 'Daily Clinic Sanitization & Disinfectants', 'Sanitization', 1200.00, 'Cash', 'RCP-EXP-101', 'Weekly floor and sample desk disinfection pack', date('Y-m-d'), 'admin'],
            ['EXP-002', 'ORG-001', 'BR-MAIN-LAB', 'Cold Chain Ice Packs & Sample Courier', 'Logistics', 2500.00, 'Online/Bank', 'TCS-99120', 'Dispatch to reference laboratory', date('Y-m-d'), 'admin'],
            ['EXP-003', 'ORG-001', 'CC-01', 'Thermal Receipt Paper Rolls (Pack of 10)', 'Supplies', 1800.00, 'Cash', 'RCP-EXP-102', 'Counter thermal rolls for receipt printing', date('Y-m-d'), 'admin'],
        ];
        $stmtExp = $pdo->prepare("INSERT IGNORE INTO expenses (id, organization_id, branch, title, category, amount, payment_mode, receipt_no, notes, expense_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($expensesSeed as $exp) {
            $stmtExp->execute($exp);
        }

        $logs[] = "Test catalog loaded from tests.csv (source of truth).";
        $logs[] = "mock_tests in bootstrap.php loads from DB via test_repo.";
        $logs[] = "Default doctor signatories seeded for report footer.";
        $logs[] = "Default inventory items seeded for stock management.";
        $logs[] = "Clinical report templates seeded for radiology, cardiology, and pathology.";
        $logs[] = "Sample expense records initialized.";

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
