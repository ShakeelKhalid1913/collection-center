<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;

class LabEntryRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        static $ensured = false;
        if (!$ensured) {
            $ensured = true;
            $pdo = $this->db->getPdo();
            try { $pdo->exec("ALTER TABLE lab_entries ADD COLUMN reporting_doctor VARCHAR(255) NULL"); } catch (\Throwable) {}
            try { $pdo->exec("ALTER TABLE lab_entries ADD COLUMN reporting_doctor_quals VARCHAR(255) NULL"); } catch (\Throwable) {}
            try { $pdo->exec("ALTER TABLE lab_entries ADD COLUMN reporting_doctor_desig VARCHAR(255) NULL"); } catch (\Throwable) {}
            try { $pdo->exec("ALTER TABLE lab_entries ADD COLUMN is_bill_locked TINYINT(1) NOT NULL DEFAULT 0"); } catch (\Throwable) {}
            try { $pdo->exec("ALTER TABLE lab_entries ADD COLUMN bill_locked_at DATETIME NULL"); } catch (\Throwable) {}
            try { $pdo->exec("ALTER TABLE lab_entries ADD COLUMN bill_locked_by VARCHAR(100) NULL"); } catch (\Throwable) {}
            try { $pdo->exec("ALTER TABLE lab_entries ADD COLUMN is_report_locked TINYINT(1) NOT NULL DEFAULT 0"); } catch (\Throwable) {}
            try { $pdo->exec("ALTER TABLE lab_entries ADD COLUMN report_locked_at DATETIME NULL"); } catch (\Throwable) {}
            try { $pdo->exec("ALTER TABLE lab_entries ADD COLUMN report_locked_by VARCHAR(100) NULL"); } catch (\Throwable) {}

            // Auto-repair past imaging_scans records where full order total was cloned instead of split
            try {
                $duplicateScans = $this->db->fetchAll(
                    "SELECT s.id, s.lab_no, s.study, le.tests, le.amount, le.paid, le.discount, le.organization_id
                     FROM imaging_scans s
                     INNER JOIN lab_entries le ON le.lab_no = s.lab_no
                     WHERE le.tests LIKE '%,%' OR le.tests LIKE '%+%'"
                );
                if (!empty($duplicateScans)) {
                    $byLab = [];
                    foreach ($duplicateScans as $ds) {
                        $byLab[$ds['lab_no']][] = $ds;
                    }
                    foreach ($byLab as $lNo => $scansList) {
                        $first = $scansList[0];
                        $org = (string)($first['organization_id'] ?? 'ORG-001');
                        $bd = function_exists('allocate_order_breakdown') 
                            ? allocate_order_breakdown([
                                'tests' => $first['tests'],
                                'amount' => $first['amount'],
                                'discount' => $first['discount'],
                                'paid' => $first['paid'],
                            ], $org)
                            : null;
                        if (!$bd) continue;
                        $imMap = [];
                        foreach ($bd['imaging'] as $im) {
                            $imMap[strtolower(trim((string)$im['name']))] = $im;
                        }
                        foreach ($scansList as $scan) {
                            $sKey = strtolower(trim((string)$scan['study']));
                            $alloc = $imMap[$sKey] ?? null;
                            if (!$alloc) {
                                foreach ($imMap as $k => $c) {
                                    if (str_contains($sKey, $k) || str_contains($k, $sKey)) {
                                        $alloc = $c;
                                        break;
                                    }
                                }
                            }
                            if ($alloc) {
                                $this->db->execute(
                                    "UPDATE imaging_scans SET amount = :amt, paid = :paid, discount = :disc WHERE id = :id",
                                    [
                                        'id' => $scan['id'],
                                        'amt' => $alloc['allocated_gross'],
                                        'paid' => $alloc['allocated_paid'],
                                        'disc' => $alloc['allocated_discount'],
                                    ]
                                );
                            }
                        }
                    }
                }
            } catch (\Throwable) {}
        }
    }

    public function getAll(?string $orgId = null, string|int|null $branchOrLimit = null, ?int $limit = null): array
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        if (is_int($branchOrLimit)) {
            $limit = $branchOrLimit;
            $branchId = null;
        } else {
            $branchId = $branchOrLimit;
            $limit = $limit ?? 100;
        }

        $sql = "SELECT * FROM lab_entries WHERE organization_id = :org";
        $params = ['org' => $orgId];
        if ($branchId !== null && $branchId !== '') {
            $sql .= " AND (branch_id = :branch OR branch = :branch2)";
            $params['branch'] = $branchId;
            $params['branch2'] = $branchId;
        }
        $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit;
        return $this->db->fetchAll($sql, $params);
    }

    public function getEntriesWithPatients(?string $orgId = null, string|int|null $branchOrLimit = null, ?int $limit = null): array
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        if (is_int($branchOrLimit)) {
            $limit = $branchOrLimit;
            $branchId = null;
        } else {
            $branchId = $branchOrLimit;
            $limit = $limit ?? 250;
        }

        $sql = "SELECT le.lab_no, le.patient_id, le.patient_name, le.tests, le.doctor, le.status, le.created_at,
                       le.amount, le.paid, le.discount, le.is_b2b, le.b2b_client_name, le.branch_id, le.branch,
                       COALESCE(p.patient_no, le.patient_id) AS mr_no,
                       COALESCE(p.full_name, le.patient_name) AS full_name,
                       p.phone, p.age, p.gender
                FROM lab_entries le
                LEFT JOIN patients p ON (p.id = le.patient_id OR p.patient_no = le.patient_id)
                WHERE le.organization_id = :org";
        $params = ['org' => $orgId];
        if ($branchId !== null && $branchId !== '') {
            $sql .= " AND (le.branch_id = :branch OR le.branch = :branch2)";
            $params['branch'] = $branchId;
            $params['branch2'] = $branchId;
        }
        $sql .= " ORDER BY le.created_at DESC LIMIT " . (int)$limit;
        return $this->db->fetchAll($sql, $params);
    }

    public function findByLabNo(string $labNo, ?string $orgId = null): ?array
    {
        $clean = trim($labNo);
        if ($clean === '') {
            return null;
        }

        if ($orgId === null && function_exists('current_user')) {
            $user = current_user();
            if (!empty($user['organization_id']) && empty($user['is_super_admin'])) {
                $orgId = $user['organization_id'];
            }
        }

        // 1. Direct exact match
        if ($orgId !== null && $orgId !== '') {
            $row = $this->db->fetchOne("SELECT * FROM lab_entries WHERE lab_no = :lab_no AND organization_id = :org LIMIT 1", ['lab_no' => $clean, 'org' => $orgId]);
            if ($row) return $row;
        } else {
            $row = $this->db->fetchOne("SELECT * FROM lab_entries WHERE lab_no = :lab_no LIMIT 1", ['lab_no' => $clean]);
            if ($row) return $row;
        }

        // 2. Slip # / numeric suffix match (e.g. user enters "3235" or "2891" for "L-2026-3235" or "L-2026-2891")
        $digitsOnly = preg_replace('/\D/', '', $clean);
        $cleanSuffix = ltrim($clean, 'L-l- ');
        $needle = $digitsOnly !== '' ? '%' . $digitsOnly : '%' . $cleanSuffix;

        $params = [
            'suffix1' => $needle,
            'suffix2' => $needle,
            'suffix3' => $needle,
        ];
        $sqlOrg = '';
        if ($orgId !== null && $orgId !== '') {
            $sqlOrg = " AND organization_id = :org";
            $params['org'] = $orgId;
        }

        $row = $this->db->fetchOne(
            "SELECT * FROM lab_entries WHERE (lab_no LIKE :suffix1 OR patient_id LIKE :suffix2 OR id LIKE :suffix3)" . $sqlOrg . " ORDER BY created_at DESC LIMIT 1",
            $params
        );
        if ($row) return $row;

        // 3. Fallback across all organizations (e.g. for super admin or cross-lab lookup)
        $row = $this->db->fetchOne(
            "SELECT * FROM lab_entries WHERE (lab_no = :exact OR lab_no LIKE :suffix1 OR patient_id LIKE :suffix2) ORDER BY created_at DESC LIMIT 1",
            ['exact' => $clean, 'suffix1' => $needle, 'suffix2' => $needle]
        );
        return $row ?: null;
    }

    public function generateNextLabNo(string $orgId = 'ORG-001', bool $peekOnly = false): string
    {
        $settings = setting_repo()->getSettings($orgId);
        $prefixPattern = !empty($settings['lab_no_prefix']) ? (string)$settings['lab_no_prefix'] : 'L-{YEAR}-';
        $prefix = str_ireplace(['{year}', '{y}'], date('Y'), $prefixPattern);
        $prefix = str_ireplace(['{month}', '{m}'], date('m'), $prefix);
        if (strcasecmp(trim($prefixPattern), 'none') === 0) {
            $prefix = '';
        }

        $seq = null;
        if (!empty($settings['next_lab_seq']) && (int)$settings['next_lab_seq'] > 0) {
            $seq = (int)$settings['next_lab_seq'];
        } else {
            $recent = $this->db->fetchAll(
                "SELECT lab_no FROM lab_entries WHERE organization_id = :org ORDER BY id DESC LIMIT 100",
                ['org' => $orgId]
            );
            $maxNum = 5054;
            foreach ($recent as $r) {
                if (preg_match('/(\d+)$/', (string)$r['lab_no'], $matches)) {
                    $n = (int)$matches[1];
                    if ($n > $maxNum && $n < 999999) {
                        $maxNum = $n;
                    }
                }
            }
            $seq = $maxNum + 1;
        }

        if ($prefix !== '') {
            $sep = (str_ends_with($prefix, '-') || str_ends_with($prefix, '/')) ? '' : '-';
            $labNo = $prefix . $sep . sprintf('%04d', $seq);
        } else {
            $labNo = (string)$seq;
        }

        if (!$peekOnly) {
            try {
                $this->db->execute(
                    "UPDATE lab_settings SET next_lab_seq = :nextSeq WHERE organization_id = :org",
                    ['nextSeq' => $seq + 1, 'org' => $orgId]
                );
            } catch (\Throwable $e) {}
        }

        return $labNo;
    }

    public function create(array $data): array
    {
        $id = 'LAB-' . bin2hex(random_bytes(6));
        $orgId = (string)($data['organization_id'] ?? 'ORG-001');
        $rawLabNo = trim((string)($data['lab_no'] ?? ''));
        if ($rawLabNo !== '') {
            $labNo = $rawLabNo;
            if (preg_match('/(\d+)$/', $rawLabNo, $m)) {
                $subSeq = (int)$m[1];
                try {
                    $this->db->execute(
                        "UPDATE lab_settings SET next_lab_seq = :nextSeq WHERE organization_id = :org AND (next_lab_seq IS NULL OR next_lab_seq <= :subSeq)",
                        ['nextSeq' => $subSeq + 1, 'subSeq' => $subSeq, 'org' => $orgId]
                    );
                } catch (\Throwable $e) {}
            }
        } else {
            $labNo = $this->generateNextLabNo($orgId, false);
        }
        $createdAt = !empty($data['created_at'])
            ? date('Y-m-d H:i:s', strtotime((string)$data['created_at']))
            : (!empty($data['entry_time'])
                ? date('Y-m-d H:i:s', strtotime((string)$data['entry_time']))
                : date('Y-m-d H:i:s'));

        $sql = "INSERT INTO lab_entries 
                (id, organization_id, branch_id, lab_no, patient_id, patient_name, tests, doctor, route, priority, status, sample_status, amount, paid, discount, branch, clinical_notes, created_at)
                VALUES 
                (:id, :org_id, :branch_id, :lab_no, :patient_id, :patient_name, :tests, :doctor, :route, :priority, :status, :sample_status, :amount, :paid, :discount, :branch, :clinical_notes, :created_at)";

        $ok = $this->db->execute($sql, [
            'id' => $id,
            'org_id' => $data['organization_id'] ?? 'ORG-001',
            'branch_id' => $data['branch_id'] ?? 'BR-GULBERG',
            'lab_no' => $labNo,
            'patient_id' => $data['patient_id'] ?? '',
            'patient_name' => $data['patient_name'] ?? $data['patient'] ?? '',
            'tests' => \normalize_tests_list(
                is_array($data['tests']) ? $data['tests'] : (string)($data['tests'] ?? ''),
                (string)($data['organization_id'] ?? 'ORG-001')
            ),
            'doctor' => $data['doctor'] ?? 'Walk-in / Self',
            'route' => $data['route'] ?? 'Laboratory — Pathology',
            'priority' => $data['priority'] ?? 'Normal',
            'status' => $data['status'] ?? 'pending',
            'sample_status' => $data['sample_status'] ?? 'pending',
            'amount' => (float)($data['amount'] ?? 0),
            'paid' => (float)($data['paid'] ?? 0),
            'discount' => (float)($data['discount'] ?? 0),
            'branch' => $data['branch'] ?? 'CC-01',
            'clinical_notes' => $data['clinical_notes'] ?? '',
            'created_at' => $createdAt,
        ]);

        if ($ok) {
            $tests = \normalize_tests_list(
                is_array($data['tests']) ? $data['tests'] : (string)($data['tests'] ?? ''),
                (string)($data['organization_id'] ?? 'ORG-001')
            );
            // Build proper parameter sheets (CBC → 13 lines, etc.) — no single junk stub
            if ($tests !== '') {
                (new ResultRepository())->ensureResultsInitialized(
                    $labNo,
                    $tests,
                    (string)($data['patient_name'] ?? $data['patient'] ?? ''),
                    (string)($data['organization_id'] ?? 'ORG-001')
                );

                // Auto-route any Imaging / Radiology tests (Ultrasound, X-Ray, ECG, CT, MRI) to imaging_scans
                $this->syncImagingScans($labNo, $tests, $data);

                // If all initialized results have values (e.g. single serology screening tests defaulted to Non Reactive), mark completed
                $initResults = (new ResultRepository())->getResultsByLabNo($labNo);
                if (!empty($initResults)) {
                    $allHaveValues = true;
                    foreach ($initResults as $ir) {
                        if ((int)($ir['is_visible'] ?? 1) === 0) continue;
                        $v = trim((string)($ir['value'] ?? ''));
                        if ($v === '' || $v === '-' || $v === '—' || strcasecmp($v, 'pending') === 0) {
                            $allHaveValues = false;
                            break;
                        }
                    }
                    if ($allHaveValues) {
                        $this->updateStatus($labNo, 'completed');
                    }
                }
            }

            if (($data['sample_status'] ?? '') === 'collected') {
                $this->db->execute(
                    "INSERT INTO samples (id, lab_no, patient, sample, status, received_at) VALUES (:id, :lab_no, :patient, 'Blood', 'collected', :recv)",
                    [
                        'id' => 'S-' . bin2hex(random_bytes(4)),
                        'lab_no' => $labNo,
                        'patient' => $data['patient_name'] ?? '',
                        'recv' => date('Y-m-d H:i'),
                    ]
                );
            }
        }

        return ['success' => $ok, 'lab_no' => $labNo, 'id' => $id];
    }

    public function getByPatientId(string $patientId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM lab_entries WHERE patient_id = :pid ORDER BY created_at DESC",
            ['pid' => $patientId]
        );
    }

    public function syncImagingScans(string $labNo, string $tests, array $data): void
    {
        $testArr = array_filter(array_map('trim', explode(',', $tests)));
        $orgIdVal = (string)($data['organization_id'] ?? 'ORG-001');
        $patientNameVal = (string)($data['patient_name'] ?? $data['patient'] ?? 'Unknown');
        $patientIdVal = (string)($data['patient_id'] ?? '');
        $doctorVal = (string)($data['doctor'] ?? 'Walk-in / Self');
        $notesVal = (string)($data['clinical_notes'] ?? '');

        // Calculate accurate proportional breakdown per study to prevent duplicate/inflated billing
        $breakdown = function_exists('allocate_order_breakdown') 
            ? allocate_order_breakdown(array_merge($data, ['tests' => $tests]), $orgIdVal) 
            : ['imaging' => []];
        $imagingItems = $breakdown['imaging'] ?? [];
        $imagingMap = [];
        foreach ($imagingItems as $im) {
            $n = strtolower(trim((string)$im['name']));
            $imagingMap[$n] = $im;
        }

        foreach ($testArr as $singleTest) {
            $deptMeta = get_department_meta($singleTest);
            if (!empty($deptMeta['is_imaging'])) {
                $modality = $deptMeta['name']; // 'Ultrasound', 'X-Ray', 'ECG', 'CT Scan', 'MRI'

                // Find allocated share for this specific scan
                $targetKey = strtolower(trim($singleTest));
                $matchedAlloc = $imagingMap[$targetKey] ?? null;
                if (!$matchedAlloc) {
                    foreach ($imagingMap as $k => $alloc) {
                        if (str_contains($targetKey, $k) || str_contains($k, $targetKey)) {
                            $matchedAlloc = $alloc;
                            break;
                        }
                    }
                }

                $scanAmount = (float)($matchedAlloc['allocated_gross'] ?? 0);
                $scanDiscount = (float)($matchedAlloc['allocated_discount'] ?? 0);
                $scanPaid = (float)($matchedAlloc['allocated_paid'] ?? 0);

                $existingScan = $this->db->fetchOne(
                    "SELECT id FROM imaging_scans WHERE lab_no = :lab_no AND (study = :st OR study LIKE :st_like) LIMIT 1",
                    ['lab_no' => $labNo, 'st' => $singleTest, 'st_like' => '%' . $singleTest . '%']
                );
                if (!$existingScan) {
                    $scanId = 'IMG-' . bin2hex(random_bytes(5));
                    $prefix = match ($deptMeta['id']) {
                        'ct' => 'CT',
                        'ultrasound' => 'US',
                        'ecg' => 'ECG',
                        'mri' => 'MRI',
                        default => 'XR',
                    };
                    $scanNo = $prefix . '-' . date('Y') . '-' . sprintf('%04d', rand(1000, 9999));
                    $defTemplate = get_default_imaging_template($singleTest, $deptMeta['id']);

                    try {
                        $this->db->execute(
                            "INSERT INTO imaging_scans (id, organization_id, scan_no, lab_no, patient, patient_id, modality, study, status, scan_date, radiologist, doctor, clinical_notes, findings, impression, amount, paid, discount)
                             VALUES (:id, :org, :scan_no, :lab_no, :patient, :patient_id, :modality, :study, 'pending', :scan_date, :radiologist, :doctor, :notes, :findings, :impression, :amount, :paid, :discount)",
                            [
                                'id' => $scanId,
                                'org' => $orgIdVal,
                                'scan_no' => $scanNo,
                                'lab_no' => $labNo,
                                'patient' => $patientNameVal,
                                'patient_id' => $patientIdVal,
                                'modality' => $modality,
                                'study' => $singleTest,
                                'scan_date' => date('Y-m-d'),
                                'radiologist' => $doctorVal,
                                'doctor' => $doctorVal,
                                'notes' => $notesVal,
                                'findings' => $defTemplate['findings'] ?? '',
                                'impression' => $defTemplate['impression'] ?? '',
                                'amount' => $scanAmount,
                                'paid' => $scanPaid,
                                'discount' => $scanDiscount,
                            ]
                        );
                    } catch (\Throwable $e) {}
                } else {
                    // Update existing scan with accurate split amounts if already present
                    try {
                        $this->db->execute(
                            "UPDATE imaging_scans SET amount = :amount, paid = :paid, discount = :discount WHERE id = :id",
                            [
                                'id' => $existingScan['id'],
                                'amount' => $scanAmount,
                                'paid' => $scanPaid,
                                'discount' => $scanDiscount,
                            ]
                        );
                    } catch (\Throwable $e) {}
                }
            }
        }
    }

    public function updateEntry(string $labNo, array $data): bool
    {
        $fields = [];
        $params = ['lab_no' => $labNo];
        $allowed = [
            'patient_name', 'tests', 'doctor', 'reporting_doctor', 'reporting_doctor_quals', 'reporting_doctor_desig', 'route', 'priority', 'status', 'sample_status',
            'amount', 'paid', 'discount', 'clinical_notes', 'created_at',
            'is_bill_locked', 'bill_locked_at', 'bill_locked_by',
            'is_report_locked', 'report_locked_at', 'report_locked_by'
        ];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = :{$f}";
                if ($f === 'tests') {
                    $params[$f] = \normalize_tests_list((string)$data[$f]);
                } elseif ($f === 'created_at') {
                    $params[$f] = date('Y-m-d H:i:s', strtotime((string)$data[$f]));
                } else {
                    $params[$f] = $data[$f];
                }
            }
        }
        if (empty($fields)) return true;
        $ok = $this->db->execute(
            "UPDATE lab_entries SET " . implode(', ', $fields) . " WHERE lab_no = :lab_no",
            $params
        );
        if ($ok && !empty($data['tests'])) {
            $cur = $this->findByLabNo($labNo);
            if ($cur) {
                $this->syncImagingScans($labNo, (string)$data['tests'], array_merge($cur, $data));
            }
        }
        return $ok;
    }

    public function lockBill(string $labNo, string $by): bool
    {
        return $this->updateEntry($labNo, [
            'is_bill_locked' => 1,
            'bill_locked_at' => date('Y-m-d H:i:s'),
            'bill_locked_by' => $by,
        ]);
    }

    public function unlockBill(string $labNo): bool
    {
        return $this->updateEntry($labNo, [
            'is_bill_locked' => 0,
            'bill_locked_at' => null,
            'bill_locked_by' => null,
        ]);
    }

    public function lockReport(string $labNo, string $by): bool
    {
        return $this->updateEntry($labNo, [
            'is_report_locked' => 1,
            'report_locked_at' => date('Y-m-d H:i:s'),
            'report_locked_by' => $by,
        ]);
    }

    public function unlockReport(string $labNo): bool
    {
        return $this->updateEntry($labNo, [
            'is_report_locked' => 0,
            'report_locked_at' => null,
            'report_locked_by' => null,
        ]);
    }

    public function updateStatus(string $labNo, string $status, ?string $sampleStatus = null): bool
    {
        if ($sampleStatus !== null) {
            return $this->db->execute(
                "UPDATE lab_entries SET status = :status, sample_status = :sample_status WHERE lab_no = :lab_no",
                ['status' => $status, 'sample_status' => $sampleStatus, 'lab_no' => $labNo]
            );
        }
        return $this->db->execute(
            "UPDATE lab_entries SET status = :status WHERE lab_no = :lab_no",
            ['status' => $status, 'lab_no' => $labNo]
        );
    }

    /**
     * Track Sample Lifecycle across the 5 chain milestones:
     * 1. collected (Collection Center)
     * 2. in_transit (Main Lab Transit)
     * 3. testing (Testing / Processing)
     * 4. result_entered (Result Entry)
     * 5. verified (Verified Report Generated)
     */
    public function updateTransitStatus(string $labNo, string $transitStatus, ?string $notes = null): bool
    {
        return $this->db->execute(
            "UPDATE lab_entries 
             SET transit_status = :ts, transit_updated_at = NOW() 
             WHERE lab_no = :lab_no",
            ['ts' => $transitStatus, 'lab_no' => $labNo]
        );
    }

    public function countToday(string $orgId = 'ORG-001'): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM lab_entries WHERE organization_id = :org AND DATE(created_at) = CURDATE()",
            ['org' => $orgId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countPatientsToday(string $orgId = 'ORG-001'): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(DISTINCT patient_id) AS cnt FROM lab_entries WHERE organization_id = :org AND DATE(created_at) = CURDATE()",
            ['org' => $orgId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countByStatuses(array $statuses, string $orgId = 'ORG-001'): int
    {
        if ($statuses === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = array_values($statuses);
        $params[] = $orgId;
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM lab_entries WHERE status IN ({$placeholders}) AND organization_id = ?",
            $params
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countPendingSamples(string $orgId = 'ORG-001'): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM lab_entries WHERE organization_id = :org AND sample_status IN ('pending','collected','home') AND status NOT IN ('completed','verified')",
            ['org' => $orgId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function sumPaidToday(string $orgId = 'ORG-001'): float
    {
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(paid),0) AS total FROM lab_entries WHERE organization_id = :org AND DATE(created_at) = CURDATE()",
            ['org' => $orgId]
        );
        return (float)($row['total'] ?? 0);
    }

    public function deleteByLabNo(string $labNo, string $orgId = 'ORG-001'): bool
    {
        $entry = $this->findByLabNo($labNo);
        if (!$entry || (string)($entry['organization_id'] ?? '') !== $orgId) {
            return false;
        }

        $this->db->execute('DELETE FROM results WHERE lab_no = :lab_no', ['lab_no' => $labNo]);
        $this->db->execute('DELETE FROM samples WHERE lab_no = :lab_no', ['lab_no' => $labNo]);
        $this->db->execute('DELETE FROM imaging_scans WHERE lab_no = :lab_no', ['lab_no' => $labNo]);
        $this->db->execute('DELETE FROM lab_entry_tests WHERE lab_entry_id = :id', ['id' => $entry['id']]);

        return $this->db->execute(
            'DELETE FROM lab_entries WHERE lab_no = :lab_no AND organization_id = :org',
            ['lab_no' => $labNo, 'org' => $orgId]
        );
    }

    public function searchEntries(string $orgId = 'ORG-001', string $q = '', ?string $dateFrom = null, ?string $dateTo = null, ?string $branchId = null, int $limit = 200): array
    {
        $sql = 'SELECT * FROM lab_entries WHERE organization_id = :org';
        $params = ['org' => $orgId];

        if ($branchId !== null && $branchId !== '') {
            $sql .= ' AND (branch_id = :branch OR branch = :branch2)';
            $params['branch'] = $branchId;
            $params['branch2'] = $branchId;
        }
        if ($q !== '') {
            $sql .= ' AND (lab_no LIKE :q OR patient_name LIKE :q OR patient_id LIKE :q OR doctor LIKE :q OR tests LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if ($dateFrom !== null && $dateFrom !== '') {
            $sql .= ' AND DATE(created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo !== null && $dateTo !== '') {
            $sql .= ' AND DATE(created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit;
        return $this->db->fetchAll($sql, $params);
    }
}
