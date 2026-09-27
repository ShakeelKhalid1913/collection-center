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
            "ALTER TABLE test_parameters ADD COLUMN section VARCHAR(128) NULL",
            "ALTER TABLE test_parameters ADD COLUMN sub_table TEXT NULL",
            "ALTER TABLE results ADD COLUMN section VARCHAR(128) NULL",
            "ALTER TABLE results ADD COLUMN reference_range VARCHAR(128) NULL",
            "ALTER TABLE results ADD COLUMN sub_table TEXT NULL",
            "ALTER TABLE results ADD COLUMN sort_order INT DEFAULT 0",
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

        // Seed CBC Parameters (Chughtai Lab structure)
        $cbcParams = [
            // ERYTHROCYTES
            ['PRM-CBC-01', 'TST-01', 'ERYTHROCYTES', 'Hemoglobin (HB)', 'g/dl', '12.0 - 16.5', '12.0 - 16.5', "New born (HB): 15.2 - 23.5\nBaby (HB): 10.1 - 12.8\nInfant (HB): 10.8 - 12.9\nChildren (HB): 11.1 - 14.3", 1],
            ['PRM-CBC-02', 'TST-01', 'ERYTHROCYTES', 'Total RBCs', 'X10^12/L', '4.50 - 6.50', '4.50 - 6.50', null, 2],
            // ABSOLUTE VALUES
            ['PRM-CBC-03', 'TST-01', 'ABSOLUTE VALUES', 'HCT (Hematocrit)', '%', '38.0 - 52.0', '38.0 - 52.0', null, 3],
            ['PRM-CBC-04', 'TST-01', 'ABSOLUTE VALUES', 'MCV', 'Fl', '75.0 - 95.0', '75.0 - 95.0', null, 4],
            ['PRM-CBC-05', 'TST-01', 'ABSOLUTE VALUES', 'MCH', 'Pg', '27.0 - 32.0', '27.0 - 32.0', null, 5],
            ['PRM-CBC-06', 'TST-01', 'ABSOLUTE VALUES', 'MCHC', 'g/dl', '30.0 - 35.0', '30.0 - 35.0', null, 6],
            // THROMBOCYTE
            ['PRM-CBC-07', 'TST-01', 'THROMBOCYTE', 'Platelet Count', 'x10^3/µL', '150 - 400', '150 - 400', null, 7],
            // Differential Leukocytes Count
            ['PRM-CBC-08', 'TST-01', 'Differential Leukocytes Count', 'WBC (TLC)', 'K/uL', '4.0 - 11.0', '4.0 - 11.0', null, 8],
            ['PRM-CBC-09', 'TST-01', 'Differential Leukocytes Count', 'Neutrophils', '%', '40 - 75', '40 - 75', null, 9],
            ['PRM-CBC-10', 'TST-01', 'Differential Leukocytes Count', 'Lymphocytes', '%', '20 - 50', '20 - 50', null, 10],
            ['PRM-CBC-11', 'TST-01', 'Differential Leukocytes Count', 'Monocytes', '%', '02 - 10', '02 - 10', null, 11],
            ['PRM-CBC-12', 'TST-01', 'Differential Leukocytes Count', 'Eosinophils', '%', '01 - 06', '01 - 06', null, 12],
            ['PRM-CBC-13', 'TST-01', 'Differential Leukocytes Count', 'ESR', 'mm/1stHr', '0 - 18', '0 - 18', null, 13],
        ];
        $stmtCbcParam = $pdo->prepare("INSERT INTO test_parameters (id, test_id, section, name, unit, normal_value, reference_range, sub_table, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE section=VALUES(section), name=VALUES(name), unit=VALUES(unit), normal_value=VALUES(normal_value), reference_range=VALUES(reference_range), sub_table=VALUES(sub_table), sort_order=VALUES(sort_order)");
        foreach ($cbcParams as $cp) {
            $stmtCbcParam->execute($cp);
        }

        // Seed Default Doctor Signatories
        $signatoriesSeed = [
            ['SIG-01', 'ORG-001', 1, 'Dr Alina', "M.B.B.S, M Phill Hematology\nAssistant Professor\nConsultant Pathologist", 'Assistant Professor / Consultant Pathologist', 1, 0],
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
            ['R-CBC-01', 'L-2026-0891', 'Ayesha Khan', 'CBC', 'ERYTHROCYTES', 'Hemoglobin (HB)', '14.9', 'g/dl', '12.0 - 16.5', "New born (HB): 15.2 - 23.5\nBaby (HB): 10.1 - 12.8\nInfant (HB): 10.8 - 12.9\nChildren (HB): 11.1 - 14.3", 'normal', 1],
            ['R-CBC-02', 'L-2026-0891', 'Ayesha Khan', 'CBC', 'ERYTHROCYTES', 'Total RBCs', '6.03', 'X10^12/L', '4.50 - 6.50', null, 'normal', 2],
            ['R-CBC-03', 'L-2026-0891', 'Ayesha Khan', 'CBC', 'ABSOLUTE VALUES', 'HCT (Hematocrit)', '45.9', '%', '38.0 - 52.0', null, 'normal', 3],
            ['R-CBC-04', 'L-2026-0891', 'Ayesha Khan', 'CBC', 'ABSOLUTE VALUES', 'MCV', '76.2', 'Fl', '75.0 - 95.0', null, 'normal', 4],
            ['R-CBC-05', 'L-2026-0891', 'Ayesha Khan', 'CBC', 'ABSOLUTE VALUES', 'MCH', '26.3', 'Pg', '27.0 - 32.0', null, 'L', 5],
            ['R-CBC-06', 'L-2026-0891', 'Ayesha Khan', 'CBC', 'ABSOLUTE VALUES', 'MCHC', '34.5', 'g/dl', '30.0 - 35.0', null, 'normal', 6],
            ['R-CBC-07', 'L-2026-0891', 'Ayesha Khan', 'CBC', 'THROMBOCYTE', 'Platelet Count', '267', 'x10^3/µL', '150 - 400', null, 'normal', 7],
            ['R-CBC-08', 'L-2026-0891', 'Ayesha Khan', 'CBC', 'Differential Leukocytes Count', 'WBC (TLC)', '9.70', 'K/uL', '4.0 - 11.0', null, 'normal', 8],
            ['R-CBC-09', 'L-2026-0891', 'Ayesha Khan', 'CBC', 'Differential Leukocytes Count', 'Neutrophils', '55', '%', '40 - 75', null, 'normal', 9],
            ['R-CBC-10', 'L-2026-0891', 'Ayesha Khan', 'CBC', 'Differential Leukocytes Count', 'Lymphocytes', '35', '%', '20 - 50', null, 'normal', 10],
            ['R-CBC-11', 'L-2026-0891', 'Ayesha Khan', 'CBC', 'Differential Leukocytes Count', 'Monocytes', '06', '%', '02 - 10', null, 'normal', 11],
            ['R-CBC-12', 'L-2026-0891', 'Ayesha Khan', 'CBC', 'Differential Leukocytes Count', 'Eosinophils', '04', '%', '01 - 06', null, 'normal', 12],
            ['R-CBC-13', 'L-2026-0891', 'Ayesha Khan', 'CBC', 'Differential Leukocytes Count', 'ESR', '14', 'mm/1stHr', '0 - 18', null, 'normal', 13],
        ];
        $stmtCbcRes = $pdo->prepare("INSERT INTO results (id, lab_no, patient, test, section, parameter, value, unit, reference_range, sub_table, flag, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE section=VALUES(section), parameter=VALUES(parameter), value=VALUES(value), unit=VALUES(unit), reference_range=VALUES(reference_range), sub_table=VALUES(sub_table), flag=VALUES(flag), sort_order=VALUES(sort_order)");
        foreach ($cbcResultsSeed as $rRow) {
            $stmtCbcRes->execute($rRow);
        }

        // Re-seed expanded tests (INSERT IGNORE) so existing DBs get Uric Acid etc.
        $stmtTestExtra = $pdo->prepare("INSERT IGNORE INTO tests (id, organization_id, code, name, category, price, sample_type, unit, normal_range) VALUES (?, 'ORG-001', ?, ?, ?, ?, ?, ?, ?)");
        foreach ($tests as $tRow) {
            $stmtTestExtra->execute($tRow);
        }

        // Align old category names to client departments
        $pdo->exec("UPDATE tests SET category = 'Special Chemistry' WHERE category IN ('Hormones','Immunology')");
        $pdo->exec("UPDATE tests SET category = 'Chemistry' WHERE category IN ('Clinical Pathology')");
        $logs[] = "Catalog synced (departments: Hematology, Chemistry, Biochemistry, Special Chemistry, Histopathology, Microbiology).";
        $logs[] = "CBC (Complete Blood Count) pre-configured with 13 parameters and Chughtai Lab sections.";
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
