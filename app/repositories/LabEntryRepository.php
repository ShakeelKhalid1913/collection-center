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
    }

    public function getAll(string $orgId = 'ORG-001', int $limit = 100): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM lab_entries WHERE organization_id = ? ORDER BY created_at DESC LIMIT {$limit}",
            [$orgId]
        );
    }

    public function findByLabNo(string $labNo): ?array
    {
        return $this->db->fetchOne("SELECT * FROM lab_entries WHERE lab_no = :lab_no LIMIT 1", ['lab_no' => $labNo]);
    }

    public function create(array $data): array
    {
        $id = 'LAB-' . bin2hex(random_bytes(6));
        $labNo = $data['lab_no'] ?? ('L-' . date('Y') . '-' . sprintf('%04d', rand(1000, 9999)));

        $sql = "INSERT INTO lab_entries 
                (id, organization_id, branch_id, lab_no, patient_id, patient_name, tests, doctor, route, priority, status, sample_status, amount, paid, discount, branch, clinical_notes)
                VALUES 
                (:id, :org_id, :branch_id, :lab_no, :patient_id, :patient_name, :tests, :doctor, :route, :priority, :status, :sample_status, :amount, :paid, :discount, :branch, :clinical_notes)";

        $ok = $this->db->execute($sql, [
            'id' => $id,
            'org_id' => $data['organization_id'] ?? 'ORG-001',
            'branch_id' => $data['branch_id'] ?? 'BR-GULBERG',
            'lab_no' => $labNo,
            'patient_id' => $data['patient_id'] ?? '',
            'patient_name' => $data['patient_name'] ?? $data['patient'] ?? '',
            'tests' => is_array($data['tests']) ? implode(', ', $data['tests']) : ($data['tests'] ?? ''),
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
        ]);

        if ($ok) {
            // Create a pending result stub so lab can enter values
            $tests = is_array($data['tests']) ? implode(', ', $data['tests']) : ($data['tests'] ?? 'General');
            $this->db->execute(
                "INSERT INTO results (id, lab_no, patient, test, due) VALUES (:id, :lab_no, :patient, :test, 'Today')",
                [
                    'id' => 'RES-' . bin2hex(random_bytes(5)),
                    'lab_no' => $labNo,
                    'patient' => $data['patient_name'] ?? $data['patient'] ?? '',
                    'test' => $tests,
                ]
            );

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

    public function updateEntry(string $labNo, array $data): bool
    {
        $fields = [];
        $params = ['lab_no' => $labNo];
        $allowed = ['tests', 'doctor', 'route', 'priority', 'status', 'sample_status', 'amount', 'paid', 'discount', 'clinical_notes'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = :{$f}";
                $params[$f] = $data[$f];
            }
        }
        if (empty($fields)) return true;
        return $this->db->execute(
            "UPDATE lab_entries SET " . implode(', ', $fields) . " WHERE lab_no = :lab_no",
            $params
        );
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
}
