<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;

class EmployeeRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        static $migrated = false;
        if ($migrated) {
            return;
        }
        $migrated = true;

        try {
            $this->db->execute("
                CREATE TABLE IF NOT EXISTS employees (
                    id                  VARCHAR(64) PRIMARY KEY,
                    organization_id     VARCHAR(64) NOT NULL,
                    branch_id           VARCHAR(64) NULL,
                    emp_no              VARCHAR(32) NOT NULL,
                    full_name           VARCHAR(255) NOT NULL,
                    designation         VARCHAR(128) NOT NULL,
                    department          VARCHAR(128) NOT NULL DEFAULT 'Laboratory',
                    phone               VARCHAR(64) NULL,
                    email               VARCHAR(255) NULL,
                    cnic                VARCHAR(32) NULL,
                    gender              VARCHAR(16) NULL,
                    blood_group         VARCHAR(16) NULL,
                    joining_date        DATE NULL,
                    photo_mime          VARCHAR(64) NULL,
                    photo_blob          LONGBLOB NULL,
                    degree_mime         VARCHAR(64) NULL,
                    degree_blob         LONGBLOB NULL,
                    degree_title        VARCHAR(255) NULL,
                    is_active           TINYINT(1) DEFAULT 1,
                    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_emp_org (organization_id),
                    INDEX idx_emp_no (emp_no)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $this->db->execute("
                CREATE TABLE IF NOT EXISTS employee_attendance (
                    id                  VARCHAR(64) PRIMARY KEY,
                    organization_id     VARCHAR(64) NOT NULL,
                    employee_id         VARCHAR(64) NOT NULL,
                    attendance_date     DATE NOT NULL,
                    check_in            TIME NULL,
                    check_out           TIME NULL,
                    status              ENUM('present','late','absent','leave') NOT NULL DEFAULT 'present',
                    notes               VARCHAR(255) NULL,
                    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_att_org (organization_id),
                    INDEX idx_att_date (attendance_date),
                    UNIQUE KEY uq_emp_date (employee_id, attendance_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (\Throwable $e) {}

        try {
            // Check existing columns
            $cols = $this->db->fetchAll("SHOW COLUMNS FROM employees");
            $colNames = array_column($cols, 'Field');

            if (!in_array('emp_no', $colNames, true) && !in_array('emp_code', $colNames, true)) {
                $this->db->execute("ALTER TABLE employees ADD COLUMN emp_no VARCHAR(32) NOT NULL DEFAULT 'W-001' AFTER branch_id");
            } elseif (in_array('emp_code', $colNames, true) && !in_array('emp_no', $colNames, true)) {
                $this->db->execute("ALTER TABLE employees ADD COLUMN emp_no VARCHAR(32) NULL AFTER emp_code");
                $this->db->execute("UPDATE employees SET emp_no = emp_code WHERE emp_no IS NULL OR emp_no = ''");
            }

            if (!in_array('email', $colNames, true)) {
                $this->db->execute("ALTER TABLE employees ADD COLUMN email VARCHAR(255) NULL AFTER phone");
            }
            if (!in_array('gender', $colNames, true)) {
                $this->db->execute("ALTER TABLE employees ADD COLUMN gender VARCHAR(16) NULL AFTER cnic");
            }
            if (!in_array('is_active', $colNames, true)) {
                $this->db->execute("ALTER TABLE employees ADD COLUMN is_active TINYINT(1) DEFAULT 1");
                if (in_array('status', $colNames, true)) {
                    $this->db->execute("UPDATE employees SET is_active = CASE WHEN status = 'active' THEN 1 ELSE 0 END");
                }
            }
            // Seed default employees if empty
            $cnt = $this->db->fetchOne("SELECT COUNT(*) AS c FROM employees WHERE organization_id = 'ORG-001'");
            if (empty($cnt['c'])) {
                $defaultEmployees = [
                    ['EMP-101', 'ORG-001', 'BR-MAIN-LAB', 'W-001', 'W-001', 'Dr. Muhammad Tariq', 'Consultant Pathologist', 'Laboratory', '0300-1234567', 'tariq@citylab.pk', '35202-1234567-1', 'Male', 'B+', '2022-01-15', 'M.B.B.S, F.C.P.S (Pathology)'],
                    ['EMP-102', 'ORG-001', 'BR-MAIN-LAB', 'W-002', 'W-002', 'Zubair Akram', 'Senior Lab Technologist', 'Laboratory', '0321-7654321', 'zubair@citylab.pk', '35201-9876543-3', 'Male', 'O+', '2023-03-01', 'B.S Medical Laboratory Technology (MLT)'],
                    ['EMP-103', 'ORG-001', 'BR-GULBERG', 'W-003', 'W-003', 'Imran Sheikh', 'Collection Point Supervisor', 'Collection Center', '0333-5566778', 'imran@citylab.pk', '35203-4567890-5', 'Male', 'A+', '2023-06-10', 'Diploma in Clinical Pathology & Phlebotomy'],
                    ['EMP-104', 'ORG-001', 'BR-IMAGING', 'W-004', 'W-004', 'Dr. Saima Farooq', 'Consultant Radiologist', 'Diagnostic Center (Imaging)', '0345-8899001', 'saima@citylab.pk', '35204-6789012-4', 'Female', 'AB+', '2022-08-20', 'M.B.B.S, M.C.P.S (Radiology)'],
                ];
                $stmt = $this->db->prepare("INSERT INTO employees (id, organization_id, branch_id, emp_no, emp_code, full_name, designation, department, phone, email, cnic, gender, blood_group, joining_date, degree_title, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                foreach ($defaultEmployees as $emp) {
                    $stmt->execute($emp);
                }
            }
        } catch (\Throwable $e) {}

        // Add license columns to lab_settings if missing
        try {
            $cols = $this->db->fetchAll("SHOW COLUMNS FROM lab_settings LIKE 'license_image'");
            if (empty($cols)) {
                $this->db->execute("ALTER TABLE lab_settings ADD COLUMN license_image LONGBLOB NULL");
                $this->db->execute("ALTER TABLE lab_settings ADD COLUMN license_image_mime VARCHAR(64) NULL");
                $this->db->execute("ALTER TABLE lab_settings ADD COLUMN license_image_ver INT DEFAULT 0");
                $this->db->execute("ALTER TABLE lab_settings ADD COLUMN license_title VARCHAR(255) NULL");
                $this->db->execute("ALTER TABLE lab_settings ADD COLUMN license_number VARCHAR(128) NULL");
                $this->db->execute("ALTER TABLE lab_settings ADD COLUMN license_authority VARCHAR(255) NULL");
            }
        } catch (\Throwable $e) {}

        // Add photo columns to employee_attendance if missing
        try {
            $attCols = $this->db->fetchAll("SHOW COLUMNS FROM employee_attendance");
            $attColNames = array_column($attCols, 'Field');
            if (!in_array('in_photo_blob', $attColNames, true)) {
                $this->db->execute("ALTER TABLE employee_attendance ADD COLUMN in_photo_blob LONGBLOB NULL");
                $this->db->execute("ALTER TABLE employee_attendance ADD COLUMN in_photo_mime VARCHAR(64) NULL");
            }
            if (!in_array('out_photo_blob', $attColNames, true)) {
                $this->db->execute("ALTER TABLE employee_attendance ADD COLUMN out_photo_blob LONGBLOB NULL");
                $this->db->execute("ALTER TABLE employee_attendance ADD COLUMN out_photo_mime VARCHAR(64) NULL");
            }
            if (!in_array('total_hours', $attColNames, true)) {
                $this->db->execute("ALTER TABLE employee_attendance ADD COLUMN total_hours VARCHAR(32) NULL");
            }
        } catch (\Throwable $e) {}
    }

    public function getAll(?string $orgId = null, ?string $branchId = null): array
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        $sql = "SELECT id, organization_id, branch_id, emp_no, full_name, designation, department, phone, email, cnic, gender, blood_group, joining_date, degree_title, is_active, created_at,
                       CASE WHEN photo_blob IS NOT NULL AND LENGTH(photo_blob) > 0 THEN 1 ELSE 0 END AS has_photo,
                       CASE WHEN degree_blob IS NOT NULL AND LENGTH(degree_blob) > 0 THEN 1 ELSE 0 END AS has_degree
                FROM employees 
                WHERE organization_id = :org";
        $params = ['org' => $orgId];

        if ($branchId !== null && $branchId !== '') {
            $sql .= " AND branch_id = :branch";
            $params['branch'] = $branchId;
        }

        $sql .= " ORDER BY is_active DESC, full_name ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function findById(string $id, ?string $orgId = null): ?array
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        return $this->db->fetchOne(
            "SELECT id, organization_id, branch_id, emp_no, full_name, designation, department, phone, email, cnic, gender, blood_group, joining_date, degree_title, is_active, created_at,
                    CASE WHEN photo_blob IS NOT NULL AND LENGTH(photo_blob) > 0 THEN 1 ELSE 0 END AS has_photo,
                    CASE WHEN degree_blob IS NOT NULL AND LENGTH(degree_blob) > 0 THEN 1 ELSE 0 END AS has_degree
             FROM employees 
             WHERE id = :id AND organization_id = :org",
            ['id' => $id, 'org' => $orgId]
        );
    }

    public function getPhoto(string $id, string $orgId = 'ORG-001'): ?array
    {
        return $this->db->fetchOne(
            "SELECT photo_mime, photo_blob FROM employees WHERE id = :id AND organization_id = :org LIMIT 1",
            ['id' => $id, 'org' => $orgId]
        );
    }

    public function getDegree(string $id, string $orgId = 'ORG-001'): ?array
    {
        return $this->db->fetchOne(
            "SELECT degree_mime, degree_blob, degree_title FROM employees WHERE id = :id AND organization_id = :org LIMIT 1",
            ['id' => $id, 'org' => $orgId]
        );
    }

    public function saveEmployee(array $data, ?array $photoFile = null, ?array $degreeFile = null, string $orgId = 'ORG-001'): array
    {
        $id = !empty($data['id']) ? (string)$data['id'] : ('EMP-' . bin2hex(random_bytes(4)));
        $empNo = trim((string)($data['emp_no'] ?? ''));
        if ($empNo === '') {
            $empNo = 'W-' . sprintf('%03d', rand(10, 999));
        }
        $name = trim((string)($data['full_name'] ?? ''));
        $designation = trim((string)($data['designation'] ?? 'Staff'));
        $dept = trim((string)($data['department'] ?? 'Laboratory'));
        $phone = trim((string)($data['phone'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $cnic = trim((string)($data['cnic'] ?? ''));
        $gender = trim((string)($data['gender'] ?? 'Male'));
        $blood = trim((string)($data['blood_group'] ?? ''));
        $joiningDate = !empty($data['joining_date']) ? date('Y-m-d', strtotime((string)$data['joining_date'])) : date('Y-m-d');
        $degreeTitle = trim((string)($data['degree_title'] ?? ''));
        $branchId = !empty($data['branch_id']) ? (string)$data['branch_id'] : 'BR-MAIN-LAB';
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        if ($name === '') {
            return ['ok' => false, 'error' => 'Employee name is required.'];
        }

        $existing = $this->findById($id, $orgId);
        if ($existing) {
            $this->db->execute(
                "UPDATE employees 
                 SET emp_no = :emp_no, full_name = :name, designation = :desig, department = :dept,
                     branch_id = :branch, phone = :phone, email = :email, cnic = :cnic, gender = :gender,
                     blood_group = :blood, joining_date = :joining, degree_title = :deg_title, is_active = :active
                 WHERE id = :id AND organization_id = :org",
                [
                    'id' => $id,
                    'org' => $orgId,
                    'emp_no' => $empNo,
                    'name' => $name,
                    'desig' => $designation,
                    'dept' => $dept,
                    'branch' => $branchId,
                    'phone' => $phone,
                    'email' => $email,
                    'cnic' => $cnic,
                    'gender' => $gender,
                    'blood' => $blood,
                    'joining' => $joiningDate,
                    'deg_title' => $degreeTitle,
                    'active' => $isActive,
                ]
            );
        } else {
            $this->db->execute(
                "INSERT INTO employees 
                 (id, organization_id, branch_id, emp_no, full_name, designation, department, phone, email, cnic, gender, blood_group, joining_date, degree_title, is_active)
                 VALUES 
                 (:id, :org, :branch, :emp_no, :name, :desig, :dept, :phone, :email, :cnic, :gender, :blood, :joining, :deg_title, :active)",
                [
                    'id' => $id,
                    'org' => $orgId,
                    'branch' => $branchId,
                    'emp_no' => $empNo,
                    'name' => $name,
                    'desig' => $designation,
                    'dept' => $dept,
                    'phone' => $phone,
                    'email' => $email,
                    'cnic' => $cnic,
                    'gender' => $gender,
                    'blood' => $blood,
                    'joining' => $joiningDate,
                    'deg_title' => $degreeTitle,
                    'active' => $isActive,
                ]
            );
        }

        // Process photo upload
        if ($photoFile && !empty($photoFile['tmp_name']) && is_uploaded_file($photoFile['tmp_name'])) {
            $mime = $this->detectUploadMime($photoFile);
            if (str_starts_with($mime, 'image/') || $mime === 'application/octet-stream') {
                $blob = file_get_contents($photoFile['tmp_name']);
                if ($blob !== false) {
                    $this->db->execute(
                        "UPDATE employees SET photo_mime = :mime, photo_blob = :blob WHERE id = :id AND organization_id = :org",
                        ['mime' => $mime, 'blob' => $blob, 'id' => $id, 'org' => $orgId]
                    );
                }
            }
        }

        // Process degree upload
        if ($degreeFile && !empty($degreeFile['tmp_name']) && is_uploaded_file($degreeFile['tmp_name'])) {
            $mime = $this->detectUploadMime($degreeFile);
            $blob = file_get_contents($degreeFile['tmp_name']);
            if ($blob !== false) {
                $this->db->execute(
                    "UPDATE employees SET degree_mime = :mime, degree_blob = :blob WHERE id = :id AND organization_id = :org",
                    ['mime' => $mime, 'blob' => $blob, 'id' => $id, 'org' => $orgId]
                );
            }
        }

        return ['ok' => true, 'id' => $id, 'message' => 'Employee saved successfully.'];
    }

    private function detectUploadMime(array $file): string
    {
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp !== '' && is_file($tmp)) {
            // 1. Try getimagesize() for image uploads (does not require php_fileinfo extension)
            $imgInfo = @getimagesize($tmp);
            if (!empty($imgInfo['mime'])) {
                return (string)$imgInfo['mime'];
            }

            // 2. Try global mime_content_type if extension is enabled
            if (function_exists('\\mime_content_type')) {
                try {
                    $m = @\mime_content_type($tmp);
                    if ($m && is_string($m)) {
                        return $m;
                    }
                } catch (\Throwable) {}
            }

            // 3. Try finfo if available
            if (class_exists('\\finfo')) {
                try {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $m = $finfo->file($tmp);
                    if ($m && is_string($m)) {
                        return $m;
                    }
                } catch (\Throwable) {}
            }
        }

        // 4. Try browser-provided type
        $clientType = strtolower(trim((string)($file['type'] ?? '')));
        if ($clientType !== '' && preg_match('#^[a-z0-9.+_-]+/[a-z0-9.+_-]+$#i', $clientType)) {
            return $clientType;
        }

        // 5. Fallback based on file extension
        $name = strtolower((string)($file['name'] ?? ''));
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    public function deleteEmployee(string $id, string $orgId = 'ORG-001'): bool
    {
        $this->db->execute("DELETE FROM employee_attendance WHERE employee_id = :id AND organization_id = :org", ['id' => $id, 'org' => $orgId]);
        return $this->db->execute("DELETE FROM employees WHERE id = :id AND organization_id = :org", ['id' => $id, 'org' => $orgId]);
    }

    // Attendance
    public function getAttendanceForDate(string $orgId = 'ORG-001', string $date = ''): array
    {
        if ($date === '') {
            $date = date('Y-m-d');
        }
        $sql = "SELECT e.id AS employee_id, e.emp_no, e.full_name, e.designation, e.department,
                       a.id AS attendance_id, a.attendance_date, a.check_in, a.check_out, 
                       COALESCE(a.status, 'present') AS status, a.notes
                FROM employees e
                LEFT JOIN employee_attendance a ON (a.employee_id = e.id AND a.attendance_date = :att_date)
                WHERE e.organization_id = :org AND e.is_active = 1
                ORDER BY e.full_name ASC";
        return $this->db->fetchAll($sql, ['org' => $orgId, 'att_date' => $date]);
    }

    public function saveAttendanceBatch(array $records, string $orgId = 'ORG-001', string $date = ''): bool
    {
        if ($date === '') {
            $date = date('Y-m-d');
        }

        foreach ($records as $empId => $att) {
            $status = in_array($att['status'] ?? '', ['present', 'late', 'absent', 'leave'], true) ? $att['status'] : 'present';
            $checkIn = !empty($att['check_in']) ? (string)$att['check_in'] : null;
            $checkOut = !empty($att['check_out']) ? (string)$att['check_out'] : null;
            $notes = trim((string)($att['notes'] ?? ''));

            $id = 'ATT-' . bin2hex(random_bytes(4));
            $this->db->execute(
                "INSERT INTO employee_attendance (id, organization_id, employee_id, attendance_date, check_in, check_out, status, notes)
                 VALUES (:id, :org, :emp, :date, :cin, :cout, :status, :notes)
                 ON DUPLICATE KEY UPDATE check_in = VALUES(check_in), check_out = VALUES(check_out), status = VALUES(status), notes = VALUES(notes)",
                [
                    'id' => $id,
                    'org' => $orgId,
                    'emp' => $empId,
                    'date' => $date,
                    'cin' => $checkIn,
                    'cout' => $checkOut,
                    'status' => $status,
                    'notes' => $notes,
                ]
            );
        }

        return true;
    }

    public function recordClockPunch(
        string $empId,
        string $type,
        ?string $time = null,
        ?string $photoBase64 = null,
        ?array $photoFile = null,
        string $orgId = 'ORG-001',
        string $date = ''
    ): array {
        if ($date === '') {
            $date = date('Y-m-d');
        }
        if ($time === null || $time === '') {
            $time = date('H:i:s');
        }

        $emp = $this->findById($empId, $orgId);
        if (!$emp) {
            return ['ok' => false, 'error' => 'Employee not found.'];
        }

        $blob = null;
        $mime = null;

        if ($photoBase64 !== null && $photoBase64 !== '') {
            if (preg_match('/^data:([^;]+);base64,(.+)$/', $photoBase64, $m)) {
                $mime = $m[1];
                $blob = base64_decode($m[2]);
            }
        } elseif ($photoFile && !empty($photoFile['tmp_name']) && is_uploaded_file($photoFile['tmp_name'])) {
            $mime = $this->detectUploadMime($photoFile);
            $blob = file_get_contents($photoFile['tmp_name']);
        }

        $existing = $this->db->fetchOne(
            "SELECT * FROM employee_attendance WHERE employee_id = :emp AND attendance_date = :date AND organization_id = :org LIMIT 1",
            ['emp' => $empId, 'date' => $date, 'org' => $orgId]
        );

        $nowTimeStr = date('H:i:s', strtotime($time));

        if ($type === 'in') {
            if ($existing) {
                $updateSql = "UPDATE employee_attendance SET check_in = :cin, status = 'present'";
                $params = ['cin' => $nowTimeStr, 'id' => $existing['id']];
                if ($blob !== null && $blob !== false) {
                    $updateSql .= ", in_photo_blob = :blob, in_photo_mime = :mime";
                    $params['blob'] = $blob;
                    $params['mime'] = $mime;
                }
                $updateSql .= " WHERE id = :id";
                $this->db->execute($updateSql, $params);
            } else {
                $id = 'ATT-' . bin2hex(random_bytes(4));
                $this->db->execute(
                    "INSERT INTO employee_attendance 
                     (id, organization_id, employee_id, attendance_date, check_in, in_photo_blob, in_photo_mime, status)
                     VALUES (:id, :org, :emp, :date, :cin, :blob, :mime, 'present')",
                    [
                        'id' => $id,
                        'org' => $orgId,
                        'emp' => $empId,
                        'date' => $date,
                        'cin' => $nowTimeStr,
                        'blob' => $blob,
                        'mime' => $mime,
                    ]
                );
            }
            return [
                'ok' => true,
                'message' => "Arrival clock-in recorded for {$emp['full_name']} at " . date('g:i A', strtotime($nowTimeStr)) . '.',
                'time' => $nowTimeStr,
            ];
        }

        // type === 'out'
        if (!$existing) {
            $id = 'ATT-' . bin2hex(random_bytes(4));
            $this->db->execute(
                "INSERT INTO employee_attendance 
                 (id, organization_id, employee_id, attendance_date, check_out, out_photo_blob, out_photo_mime, status)
                 VALUES (:id, :org, :emp, :date, :cout, :blob, :mime, 'present')",
                [
                    'id' => $id,
                    'org' => $orgId,
                    'emp' => $empId,
                    'date' => $date,
                    'cout' => $nowTimeStr,
                    'blob' => $blob,
                    'mime' => $mime,
                ]
            );
        } else {
            $totalHours = null;
            if (!empty($existing['check_in'])) {
                $inTs = strtotime($date . ' ' . $existing['check_in']);
                $outTs = strtotime($date . ' ' . $nowTimeStr);
                if ($outTs > $inTs) {
                    $diffMins = (int)round(($outTs - $inTs) / 60);
                    $hrs = intdiv($diffMins, 60);
                    $remMins = $diffMins % 60;
                    $totalHours = ($hrs > 0 ? "{$hrs}h " : '') . "{$remMins}m";
                }
            }

            $updateSql = "UPDATE employee_attendance SET check_out = :cout";
            $params = ['cout' => $nowTimeStr, 'id' => $existing['id']];
            if ($totalHours !== null) {
                $updateSql .= ", total_hours = :th";
                $params['th'] = $totalHours;
            }
            if ($blob !== null && $blob !== false) {
                $updateSql .= ", out_photo_blob = :blob, out_photo_mime = :mime";
                $params['blob'] = $blob;
                $params['mime'] = $mime;
            }
            $updateSql .= " WHERE id = :id";
            $this->db->execute($updateSql, $params);
        }

        return [
            'ok' => true,
            'message' => "Departure clock-out recorded for {$emp['full_name']} at " . date('g:i A', strtotime($nowTimeStr)) . '.',
            'time' => $nowTimeStr,
        ];
    }

    public function getMonthlyAttendance(string $orgId, string $yearMonth, ?string $empId = null): array
    {
        $sql = "SELECT a.id, a.attendance_date, a.check_in, a.check_out, a.status, a.notes, a.total_hours,
                       e.id AS employee_id, e.emp_no, e.full_name, e.designation, e.department,
                       CASE WHEN a.in_photo_blob IS NOT NULL AND LENGTH(a.in_photo_blob) > 0 THEN 1 ELSE 0 END AS has_in_photo,
                       CASE WHEN a.out_photo_blob IS NOT NULL AND LENGTH(a.out_photo_blob) > 0 THEN 1 ELSE 0 END AS has_out_photo,
                       CASE WHEN e.photo_blob IS NOT NULL AND LENGTH(e.photo_blob) > 0 THEN 1 ELSE 0 END AS has_profile_photo
                FROM employee_attendance a
                INNER JOIN employees e ON e.id = a.employee_id
                WHERE a.organization_id = :org AND a.attendance_date LIKE :ym";
        $params = ['org' => $orgId, 'ym' => $yearMonth . '%'];

        if ($empId !== null && $empId !== '') {
            $sql .= " AND a.employee_id = :emp";
            $params['emp'] = $empId;
        }

        $sql .= " ORDER BY a.attendance_date DESC, a.check_in DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getAttendancePhoto(string $attId, string $type = 'in', string $orgId = 'ORG-001'): ?array
    {
        $blobCol = $type === 'out' ? 'out_photo_blob' : 'in_photo_blob';
        $mimeCol = $type === 'out' ? 'out_photo_mime' : 'in_photo_mime';
        return $this->db->fetchOne(
            "SELECT {$mimeCol} AS mime, {$blobCol} AS blob_data FROM employee_attendance WHERE id = :id AND organization_id = :org LIMIT 1",
            ['id' => $attId, 'org' => $orgId]
        );
    }
    public function getLicenseDetails(string $orgId = 'ORG-001'): array
    {
        $row = $this->db->fetchOne(
            "SELECT license_title, license_number, license_authority, license_image_mime, license_image_ver,
                    CASE WHEN license_image IS NOT NULL AND LENGTH(license_image) > 0 THEN 1 ELSE 0 END AS has_license
             FROM lab_settings WHERE organization_id = :org LIMIT 1",
            ['org' => $orgId]
        );
        return $row ?: [];
    }

    public function getLicenseBlob(string $orgId = 'ORG-001'): ?array
    {
        return $this->db->fetchOne(
            "SELECT license_image_mime, license_image FROM lab_settings WHERE organization_id = :org LIMIT 1",
            ['org' => $orgId]
        );
    }

    public function saveLicense(array $data, ?array $file = null, string $orgId = 'ORG-001'): bool
    {
        $title = trim((string)($data['license_title'] ?? 'Punjab Healthcare Commission / PMDC License'));
        $number = trim((string)($data['license_number'] ?? ''));
        $authority = trim((string)($data['license_authority'] ?? 'Punjab Healthcare Commission (PHC)'));

        $this->db->execute(
            "UPDATE lab_settings 
             SET license_title = :title, license_number = :number, license_authority = :auth
             WHERE organization_id = :org",
            [
                'title' => $title,
                'number' => $number,
                'auth' => $authority,
                'org' => $orgId,
            ]
        );

        if ($file && !empty($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
            $mime = $this->detectUploadMime($file);
            $blob = file_get_contents($file['tmp_name']);
            if ($blob !== false) {
                $this->db->execute(
                    "UPDATE lab_settings 
                     SET license_image = :blob, license_image_mime = :mime, license_image_ver = license_image_ver + 1
                     WHERE organization_id = :org",
                    ['blob' => $blob, 'mime' => $mime, 'org' => $orgId]
                );
            }
        }

        return true;
    }
}
