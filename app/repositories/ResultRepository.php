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

    public function getUnverifiedResults(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM results WHERE verified_at IS NULL AND value IS NOT NULL AND value != '' ORDER BY created_at DESC"
        );
    }

    public function getCriticalResults(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM results WHERE flag = 'critical' OR flag = 'H' OR flag = 'L' ORDER BY created_at DESC LIMIT 50"
        );
    }

    public function findResult(string $id): ?array
    {
        return $this->db->fetchOne("SELECT * FROM results WHERE id = :id OR lab_no = :id LIMIT 1", ['id' => $id]);
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
            "SELECT * FROM imaging_scans WHERE scan_no = :scan_no OR id = :scan_no LIMIT 1",
            ['scan_no' => $scanNo]
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
