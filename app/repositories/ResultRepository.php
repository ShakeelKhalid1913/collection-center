<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;

class ResultRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $this->db->execute('ALTER TABLE results ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1');
        } catch (\Throwable $ignored) {
            // Column already exists
        }
    }

    public function getSamples(): array
    {
        return $this->db->fetchAll("SELECT * FROM samples ORDER BY created_at DESC");
    }

    public function getPendingResults(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM results WHERE verified_at IS NULL AND (value IS NULL OR value = '') ORDER BY created_at DESC"
        );
    }

    public function getResultsByLabNo(string $labNo): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM results WHERE lab_no = :lab_no ORDER BY sort_order ASC, id ASC",
            ['lab_no' => $labNo]
        );
    }

    public function ensureResultsInitialized(string $labNo, string $testsString, string $patientName, string $orgId = 'ORG-001'): array
    {
        $testRepo = new TestRepository();
        $existing = $this->getResultsByLabNo($labNo);

        // Expand packages → individual test names, then resolve each to catalog
        $rawNames = array_filter(array_map('trim', explode(',', $testsString)));
        $resolvedTests = []; // list of ['label' => display name, 'obj' => test row|null]

        foreach ($rawNames as $tName) {
            // Package? expand its tests list
            $pkg = $this->db->fetchOne(
                "SELECT * FROM packages WHERE organization_id = :org AND (name = :n OR code = :c) LIMIT 1",
                ['org' => $orgId, 'n' => $tName, 'c' => $tName]
            );
            if ($pkg && !empty($pkg['tests_included'])) {
                // Drop any old stub row named after the package itself
                $this->db->execute(
                    "DELETE FROM results WHERE lab_no = :lab_no AND LOWER(test) = LOWER(:pkg)",
                    ['lab_no' => $labNo, 'pkg' => $tName]
                );
                foreach (array_filter(array_map('trim', explode(',', (string)$pkg['tests_included']))) as $piece) {
                    $obj = $testRepo->findByCode($piece, $orgId);
                    $resolvedTests[] = [
                        'label' => $obj['name'] ?? $piece,
                        'obj' => $obj,
                        'booked_as' => $piece,
                    ];
                }
                continue;
            }

            $obj = $testRepo->findByCode($tName, $orgId);
            $resolvedTests[] = [
                'label' => $obj['name'] ?? $tName,
                'obj' => $obj,
                'booked_as' => $tName,
            ];
        }

        if ($resolvedTests === []) {
            return $existing;
        }

        // Index existing rows by normalized test title
        $byTest = [];
        foreach ($existing as $r) {
            $key = strtolower(trim((string)($r['test'] ?? '')));
            $byTest[$key][] = $r;
        }

        $sortOrder = 1;
        foreach ($existing as $r) {
            $sortOrder = max($sortOrder, ((int)($r['sort_order'] ?? 0)) + 1);
        }

        foreach ($resolvedTests as $rt) {
            $label = $rt['label'];
            $obj = $rt['obj'];
            $bookedAs = (string)($rt['booked_as'] ?? $label);
            $key = strtolower($label);
            $alsoKeys = [
                strtolower($bookedAs),
                strtolower((string)($obj['code'] ?? '')),
            ];
            if ($obj) {
                $alsoKeys[] = strtolower((string)$obj['name']);
                if (preg_match('/^([A-Za-z0-9]+)\s*\(/', (string)$obj['name'], $m)) {
                    $alsoKeys[] = strtolower($m[1]);
                    $alsoKeys[] = rtrim(strtolower($m[1]), 's');
                }
            }
            $alsoKeys[] = rtrim(strtolower($bookedAs), 's');

            $existingForTest = $byTest[$key] ?? [];
            foreach (array_unique(array_filter($alsoKeys)) as $ak) {
                if (!empty($byTest[$ak])) {
                    $existingForTest = array_merge($existingForTest, $byTest[$ak]);
                }
            }

            $seenIds = [];
            $deduped = [];
            foreach ($existingForTest as $row) {
                $id = $row['id'] ?? '';
                if ($id === '' || isset($seenIds[$id])) {
                    continue;
                }
                $seenIds[$id] = true;
                $deduped[] = $row;
            }
            $existingForTest = $deduped;

            $params = $obj ? $testRepo->getParameters((string)$obj['id']) : [];

            $stubs = [];
            $detailed = [];
            foreach ($existingForTest as $row) {
                $p = trim((string)($row['parameter'] ?? ''));
                $t = trim((string)($row['test'] ?? ''));
                if ($p === '' || strcasecmp($p, $t) === 0 || strcasecmp($p, $label) === 0 || strcasecmp($p, $bookedAs) === 0) {
                    $stubs[] = $row;
                } else {
                    $detailed[] = $row;
                }
            }

            if (!empty($params)) {
                foreach ($stubs as $old) {
                    $this->db->execute('DELETE FROM results WHERE id = :id', ['id' => $old['id']]);
                }

                if ($detailed === [] || count($detailed) < count($params)) {
                    foreach ($detailed as $old) {
                        $this->db->execute('DELETE FROM results WHERE id = :id', ['id' => $old['id']]);
                    }
                    foreach ($params as $p) {
                        $resId = 'RES-' . bin2hex(random_bytes(5));
                        $this->db->execute(
                            "INSERT INTO results (id, lab_no, patient, test, section, parameter, value, unit, reference_range, sub_table, flag, sort_order, is_visible)
                             VALUES (:id, :lab_no, :patient, :test, :section, :param, '', :unit, :range, :sub_table, '', :sort, 1)",
                            [
                                'id' => $resId,
                                'lab_no' => $labNo,
                                'patient' => $patientName,
                                'test' => $label,
                                'section' => $p['section'] ?? null,
                                'param' => $p['name'],
                                'unit' => $p['unit'] ?? '',
                                'range' => $p['reference_range'] ?? $p['normal_value'] ?? '',
                                'sub_table' => $p['sub_table'] ?? null,
                                'sort' => $sortOrder++,
                            ]
                        );
                    }
                }
                continue;
            }

            if ($detailed !== []) {
                foreach ($stubs as $old) {
                    $this->db->execute('DELETE FROM results WHERE id = :id', ['id' => $old['id']]);
                }
                continue;
            }

            if ($existingForTest === []) {
                $resId = 'RES-' . bin2hex(random_bytes(5));
                $this->db->execute(
                    "INSERT INTO results (id, lab_no, patient, test, section, parameter, value, unit, reference_range, sub_table, flag, sort_order, is_visible)
                     VALUES (:id, :lab_no, :patient, :test, NULL, :param, '', :unit, :range, NULL, '', :sort, 1)",
                    [
                        'id' => $resId,
                        'lab_no' => $labNo,
                        'patient' => $patientName,
                        'test' => $label,
                        'param' => $label,
                        'unit' => $obj['unit'] ?? '—',
                        'range' => $obj['normal_range'] ?? $obj['reference_value'] ?? $obj['normal_value'] ?? '—',
                        'sort' => $sortOrder++,
                    ]
                );
            }
        }

        // Clean empty stubs
        $this->db->execute(
            "DELETE FROM results WHERE lab_no = :lab_no AND (parameter IS NULL OR parameter = '') AND (value IS NULL OR value = '')",
            ['lab_no' => $labNo]
        );

        return $this->getResultsByLabNo($labNo);
    }

    public function saveResultsBatch(string $labNo, array $items): bool
    {
        foreach ($items as $item) {
            $id = $item['id'] ?? '';
            if ($id === '') continue;
            $visible = array_key_exists('is_visible', $item)
                ? ((int)$item['is_visible'] ? 1 : 0)
                : null;
            $this->db->execute(
                "UPDATE results SET 
                    value = :value, 
                    unit = COALESCE(:unit, unit), 
                    reference_range = COALESCE(:range, reference_range), 
                    flag = :flag,
                    is_visible = COALESCE(:is_visible, is_visible)
                 WHERE id = :id AND lab_no = :lab_no",
                [
                    'id' => $id,
                    'lab_no' => $labNo,
                    'value' => $item['value'] ?? '',
                    'unit' => $item['unit'] ?? null,
                    'range' => $item['reference_range'] ?? null,
                    'flag' => $item['flag'] ?? '',
                    'is_visible' => $visible,
                ]
            );
        }
        return true;
    }

    public function verifyAllByLabNo(string $labNo, string $verifiedBy): bool
    {
        $ok = $this->db->execute(
            "UPDATE results SET verified_at = NOW(), verified_by = :by WHERE lab_no = :lab_no",
            ['lab_no' => $labNo, 'by' => $verifiedBy]
        );
        if ($ok) {
            $this->db->execute(
                "UPDATE lab_entries SET status = 'verified' WHERE lab_no = :lab_no",
                ['lab_no' => $labNo]
            );
        }
        return $ok;
    }

    public function saveResultEntry(string $id, array $data): bool
    {
        return $this->db->execute(
            "UPDATE results SET parameter = :parameter, value = :value, unit = :unit, flag = :flag WHERE id = :id",
            [
                'id' => $id,
                'parameter' => $data['parameter'] ?? null,
                'value' => $data['value'] ?? null,
                'unit' => $data['unit'] ?? null,
                'flag' => $data['flag'] ?? null,
            ]
        );
    }

    public function findResult(string $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM results WHERE id = :id OR lab_no = :lab LIMIT 1",
            ['id' => $id, 'lab' => $id]
        );
    }

    public function getUnverifiedResults(): array
    {
        // One row per lab visit that has entered values still waiting for sign-off
        return $this->db->fetchAll(
            "SELECT
                r.lab_no,
                MAX(r.patient) AS patient,
                GROUP_CONCAT(DISTINCT r.test ORDER BY r.test SEPARATOR ', ') AS test,
                COUNT(*) AS param_count,
                SUM(CASE WHEN r.value IS NOT NULL AND r.value != '' THEN 1 ELSE 0 END) AS filled_count,
                MIN(r.id) AS id
             FROM results r
             WHERE r.verified_at IS NULL
               AND EXISTS (
                    SELECT 1 FROM results r2
                    WHERE r2.lab_no = r.lab_no
                      AND r2.verified_at IS NULL
                      AND r2.value IS NOT NULL AND r2.value != ''
               )
             GROUP BY r.lab_no
             ORDER BY r.lab_no DESC"
        );
    }

    public function verifyResult(string $id, string $verifiedBy): bool
    {
        $ok = $this->db->execute(
            "UPDATE results SET verified_at = NOW(), verified_by = :by WHERE id = :id",
            ['id' => $id, 'by' => $verifiedBy]
        );
        if ($ok) {
            $row = $this->findResult($id);
            if ($row) {
                $this->db->execute(
                    "UPDATE lab_entries SET status = 'verified' WHERE lab_no = :lab_no",
                    ['lab_no' => $row['lab_no']]
                );
            }
        }
        return $ok;
    }

    public function updateSampleStatus(string $sampleId, string $status): bool
    {
        $recv = in_array($status, ['received', 'processing', 'completed'], true) ? date('Y-m-d H:i') : null;
        $ok = $this->db->execute(
            "UPDATE samples SET status = :status, received_at = COALESCE(:recv, received_at) WHERE id = :id",
            ['id' => $sampleId, 'status' => $status, 'recv' => $recv]
        );
        if ($ok) {
            $sample = $this->db->fetchOne("SELECT lab_no FROM samples WHERE id = :id", ['id' => $sampleId]);
            if ($sample) {
                $map = [
                    'received' => 'received',
                    'processing' => 'processing',
                    'completed' => 'completed',
                    'collected' => 'collected',
                ];
                if (isset($map[$status])) {
                    $this->db->execute(
                        "UPDATE lab_entries SET sample_status = :ss, status = CASE WHEN status = 'pending' THEN 'collected' ELSE status END WHERE lab_no = :lab_no",
                        ['ss' => $map[$status], 'lab_no' => $sample['lab_no']]
                    );
                }
            }
        }
        return $ok;
    }

    public function getImagingScans(): array
    {
        return $this->db->fetchAll("SELECT * FROM imaging_scans ORDER BY created_at DESC");
    }

    public function findImagingScan(string $scanNo): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM imaging_scans WHERE scan_no = :scan_no OR id = :id LIMIT 1",
            ['scan_no' => $scanNo, 'id' => $scanNo]
        );
    }

    public function createImagingScan(array $data): array
    {
        $id = 'IMG-' . bin2hex(random_bytes(5));
        $scanNo = $data['scan_no'] ?? ($this->modalityPrefix($data['modality'] ?? 'xray') . '-' . date('Y') . '-' . sprintf('%03d', rand(100, 999)));

        $ok = $this->db->execute(
            "INSERT INTO imaging_scans (id, scan_no, patient, patient_id, modality, study, status, scan_date, radiologist, clinical_notes)
             VALUES (:id, :scan_no, :patient, :patient_id, :modality, :study, :status, :scan_date, :radiologist, :clinical_notes)",
            [
                'id' => $id,
                'scan_no' => $scanNo,
                'patient' => $data['patient'] ?? '',
                'patient_id' => $data['patient_id'] ?? null,
                'modality' => $data['modality'] ?? 'X-Ray',
                'study' => $data['study'] ?? '',
                'status' => $data['status'] ?? 'pending',
                'scan_date' => $data['scan_date'] ?? date('Y-m-d'),
                'radiologist' => $data['radiologist'] ?? null,
                'clinical_notes' => $data['clinical_notes'] ?? null,
            ]
        );

        return ['success' => $ok, 'scan_no' => $scanNo, 'id' => $id];
    }

    public function saveImagingFindings(string $scanNo, array $data): bool
    {
        return $this->db->execute(
            "UPDATE imaging_scans SET radiologist = :rad, findings = :findings, impression = :impression, status = :status WHERE scan_no = :scan_no",
            [
                'scan_no' => $scanNo,
                'rad' => $data['radiologist'] ?? null,
                'findings' => $data['findings'] ?? null,
                'impression' => $data['impression'] ?? null,
                'status' => $data['status'] ?? 'reported',
            ]
        );
    }

    public function getCollectionCenters(): array
    {
        return $this->db->fetchAll("SELECT * FROM collection_centers ORDER BY name");
    }

    public function countPendingSamples(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM samples WHERE status NOT IN ('completed')"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countSamplesToday(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM samples WHERE DATE(created_at) = CURDATE()"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countPendingResults(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM results WHERE verified_at IS NULL AND (value IS NULL OR value = '')"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countPendingVerification(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM results WHERE verified_at IS NULL AND value IS NOT NULL AND value != ''"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countVerifiedToday(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM results WHERE DATE(verified_at) = CURDATE()"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countCritical(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM results WHERE flag IN ('critical','H','L') AND verified_at IS NULL"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countImagingPending(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM imaging_scans WHERE status IN ('pending','in_progress')"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countImagingReported(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM imaging_scans WHERE status IN ('reported','completed')"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countImagingToday(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM imaging_scans WHERE scan_date = CURDATE()"
        );
        return (int)($row['cnt'] ?? 0);
    }

    private function modalityPrefix(string $modality): string
    {
        return match (strtolower($modality)) {
            'ct', 'ct scan' => 'CT',
            'us', 'ultrasound' => 'US',
            'ecg' => 'ECG',
            default => 'XR',
        };
    }
}
