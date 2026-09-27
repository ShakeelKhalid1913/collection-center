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
        $existing = $this->getResultsByLabNo($labNo);
        // If we already have multiple detailed results or at least one with a parameter filled, return them
        $hasDetailed = false;
        foreach ($existing as $r) {
            if (!empty($r['parameter'])) {
                $hasDetailed = true;
                break;
            }
        }
        if ($hasDetailed) {
            return $existing;
        }

        // Initialize from test catalog
        $testNames = array_filter(array_map('trim', explode(',', $testsString)));
        if (empty($testNames)) {
            return $existing;
        }

        $sortOrder = 1;
        $inserted = [];
        $testRepo = new TestRepository();

        foreach ($testNames as $tName) {
            $testObj = $testRepo->findByCode($tName, $orgId);
            if (!$testObj) {
                // Try finding by name in all tests
                $allTests = $testRepo->getTests($orgId);
                foreach ($allTests as $at) {
                    if (strcasecmp($at['name'], $tName) === 0 || strcasecmp($at['code'], $tName) === 0) {
                        $testObj = $at;
                        break;
                    }
                }
            }

            if ($testObj) {
                $params = $testRepo->getParameters($testObj['id']);
                if (!empty($params)) {
                    // Test has multi-parameters (e.g. CBC)
                    foreach ($params as $p) {
                        $resId = 'RES-' . bin2hex(random_bytes(5));
                        $this->db->execute(
                            "INSERT INTO results (id, lab_no, patient, test, section, parameter, value, unit, reference_range, sub_table, flag, sort_order)
                             VALUES (:id, :lab_no, :patient, :test, :section, :param, '', :unit, :range, :sub_table, '', :sort)",
                            [
                                'id' => $resId,
                                'lab_no' => $labNo,
                                'patient' => $patientName,
                                'test' => $testObj['name'],
                                'section' => $p['section'] ?? null,
                                'param' => $p['name'],
                                'unit' => $p['unit'] ?? '',
                                'range' => $p['reference_range'] ?? $p['normal_value'] ?? '',
                                'sub_table' => $p['sub_table'] ?? null,
                                'sort' => $sortOrder++,
                            ]
                        );
                    }
                    continue;
                }
            }

            // Single parameter or custom test
            $resId = 'RES-' . bin2hex(random_bytes(5));
            $this->db->execute(
                "INSERT INTO results (id, lab_no, patient, test, section, parameter, value, unit, reference_range, sub_table, flag, sort_order)
                 VALUES (:id, :lab_no, :patient, :test, NULL, :param, '', :unit, :range, NULL, '', :sort)",
                [
                    'id' => $resId,
                    'lab_no' => $labNo,
                    'patient' => $patientName,
                    'test' => $tName,
                    'param' => $tName,
                    'unit' => $testObj['unit'] ?? '—',
                    'range' => $testObj['normal_range'] ?? $testObj['reference_value'] ?? '—',
                    'sort' => $sortOrder++,
                ]
            );
        }

        // Clean up empty stub if it exists
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
            $this->db->execute(
                "UPDATE results SET 
                    value = :value, 
                    unit = COALESCE(:unit, unit), 
                    reference_range = COALESCE(:range, reference_range), 
                    flag = :flag 
                 WHERE id = :id AND lab_no = :lab_no",
                [
                    'id' => $id,
                    'lab_no' => $labNo,
                    'value' => $item['value'] ?? '',
                    'unit' => $item['unit'] ?? null,
                    'range' => $item['reference_range'] ?? null,
                    'flag' => $item['flag'] ?? '',
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
