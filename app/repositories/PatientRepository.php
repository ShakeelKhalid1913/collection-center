<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;

class PatientRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll(string $orgId = 'ORG-001', int $limit = 100): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM patients WHERE organization_id = ? ORDER BY created_at DESC LIMIT {$limit}",
            [$orgId]
        );
    }

    public function findById(string $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM patients WHERE id = :id OR patient_no = :patient_no LIMIT 1",
            ['id' => $id, 'patient_no' => $id]
        );
    }

    public function search(string $query, string $orgId = 'ORG-001'): array
    {
        $like = '%' . $query . '%';
        $sql = "SELECT * FROM patients 
                WHERE organization_id = :org_id 
                AND (
                    full_name LIKE :q_name
                    OR phone LIKE :q_phone
                    OR cnic LIKE :q_cnic
                    OR patient_no LIKE :q_mr
                    OR id LIKE :q_id
                )
                ORDER BY created_at DESC LIMIT 30";

        return $this->db->fetchAll($sql, [
            'org_id' => $orgId,
            'q_name' => $like,
            'q_phone' => $like,
            'q_cnic' => $like,
            'q_mr' => $like,
            'q_id' => $like,
        ]);
    }

    public function countAll(string $orgId = 'ORG-001'): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM patients WHERE organization_id = :org_id",
            ['org_id' => $orgId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    private function ensureReferringDoctorColumn(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;
        try {
            $cols = $this->db->fetchAll("SHOW COLUMNS FROM patients LIKE 'referring_doctor'");
            if (empty($cols)) {
                $this->db->execute("ALTER TABLE patients ADD COLUMN referring_doctor VARCHAR(255) NULL AFTER emergency_phone");
            }
        } catch (\Throwable $e) {}
    }

    public function create(array $data): array
    {
        $this->ensureReferringDoctorColumn();
        $manualNo = trim((string)($data['patient_no'] ?? ''));
        $patientNo = $manualNo !== '' ? $manualNo : ('MR-' . rand(10000, 99999));
        $id = $data['id'] ?? ('P-' . bin2hex(random_bytes(4)));

        $doctor = trim((string)($data['referring_doctor'] ?? $data['doctor'] ?? ''));

        $hasDocCol = true;
        try {
            $cols = $this->db->fetchAll("SHOW COLUMNS FROM patients LIKE 'referring_doctor'");
            $hasDocCol = !empty($cols);
        } catch (\Throwable $e) {
            $hasDocCol = false;
        }

        $createdAt = !empty($data['created_at'])
            ? date('Y-m-d H:i:s', strtotime((string)$data['created_at']))
            : (!empty($data['entry_time'])
                ? date('Y-m-d H:i:s', strtotime((string)$data['entry_time']))
                : date('Y-m-d H:i:s'));

        if ($hasDocCol) {
            $sql = "INSERT INTO patients (
                        id, organization_id, patient_no, title, full_name, relation, relation_of,
                        phone, phone_alt, email, cnic, blood_group, dob, age, gender,
                        address, city, emergency_name, emergency_phone, referring_doctor, internal_notes,
                        patient_type, panel_code, branch, created_by, created_at
                    ) VALUES (
                        :id, :org_id, :patient_no, :title, :full_name, :relation, :relation_of,
                        :phone, :phone_alt, :email, :cnic, :blood_group, :dob, :age, :gender,
                        :address, :city, :emergency_name, :emergency_phone, :referring_doctor, :notes,
                        :patient_type, :panel_code, :branch, :created_by, :created_at
                    )";
        } else {
            $sql = "INSERT INTO patients (
                        id, organization_id, patient_no, title, full_name, relation, relation_of,
                        phone, phone_alt, email, cnic, blood_group, dob, age, gender,
                        address, city, emergency_name, emergency_phone, internal_notes,
                        patient_type, panel_code, branch, created_by, created_at
                    ) VALUES (
                        :id, :org_id, :patient_no, :title, :full_name, :relation, :relation_of,
                        :phone, :phone_alt, :email, :cnic, :blood_group, :dob, :age, :gender,
                        :address, :city, :emergency_name, :emergency_phone, :notes,
                        :patient_type, :panel_code, :branch, :created_by, :created_at
                    )";
        }

        $dob = $data['dob'] ?? null;
        if ($dob === '') {
            $dob = null;
        }

        $params = [
            'id' => $id,
            'org_id' => $data['organization_id'] ?? 'ORG-001',
            'patient_no' => $patientNo,
            'title' => $data['title'] ?? '',
            'full_name' => $data['full_name'] ?? $data['name'] ?? '',
            'relation' => $data['relation'] ?? 'Self',
            'relation_of' => $data['relation_of'] ?? '',
            'phone' => $data['phone'] ?? '',
            'phone_alt' => $data['phone_alt'] ?? $data['phone2'] ?? '',
            'email' => $data['email'] ?? '',
            'cnic' => $data['cnic'] ?? '',
            'blood_group' => $data['blood_group'] ?? '',
            'dob' => $dob,
            'age' => (int)($data['age'] ?? 0),
            'gender' => $data['gender'] ?? 'Male',
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'emergency_name' => $doctor !== '' ? $doctor : ($data['emergency_name'] ?? ''),
            'emergency_phone' => $data['emergency_phone'] ?? '',
            'notes' => $data['notes'] ?? $data['internal_notes'] ?? '',
            'patient_type' => $data['patient_type'] ?? $data['ptype'] ?? 'Walk-in',
            'panel_code' => $data['panel_code'] ?? $data['panel'] ?? '',
            'branch' => $data['branch'] ?? 'CC-01',
            'created_by' => $data['created_by'] ?? null,
            'created_at' => $createdAt,
        ];

        if ($hasDocCol) {
            $params['referring_doctor'] = $doctor;
        }

        $ok = $this->db->execute($sql, $params);

        if ($ok && $doctor !== '') {
            try {
                $this->db->execute("UPDATE patients SET referring_doctor = :doc WHERE id = :id", ['doc' => $doctor, 'id' => $id]);
            } catch (\Throwable $ignored) {}

            try {
                $pName = trim((string)($data['full_name'] ?? $data['name'] ?? ''));
                $this->db->execute(
                    "UPDATE lab_entries SET doctor = :doc WHERE (patient_id = :pid OR patient_name = :pname) AND (doctor IS NULL OR doctor = '' OR doctor LIKE 'Walk%')",
                    ['doc' => $doctor, 'pid' => $id, 'pname' => $pName]
                );
            } catch (\Throwable $ignored) {}
        }

        return ['success' => $ok, 'id' => $id, 'patient_no' => $patientNo];
    }

    public function update(string $id, array $data): bool
    {
        $this->ensureReferringDoctorColumn();
        $fields = [];
        $params = ['id' => $id];

        $allowed = [
            'title', 'full_name', 'relation', 'relation_of', 'phone', 'phone_alt',
            'email', 'cnic', 'blood_group', 'dob', 'age', 'gender', 'address',
            'city', 'emergency_name', 'emergency_phone', 'internal_notes',
            'patient_type', 'panel_code', 'branch', 'referring_doctor'
        ];

        // Map aliases
        if (isset($data['name']) && !isset($data['full_name'])) {
            $data['full_name'] = $data['name'];
        }
        if (isset($data['notes']) && !isset($data['internal_notes'])) {
            $data['internal_notes'] = $data['notes'];
        }
        if (isset($data['doctor']) && !isset($data['referring_doctor'])) {
            $data['referring_doctor'] = $data['doctor'];
        }

        $doctor = trim((string)($data['referring_doctor'] ?? $data['doctor'] ?? ''));
        if ($doctor !== '' && empty($data['emergency_name'])) {
            $data['emergency_name'] = $doctor;
        }

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = :{$col}";
                $params[$col] = $data[$col];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE patients SET " . implode(', ', $fields) . " WHERE id = :id OR patient_no = :id";
        $ok = $this->db->execute($sql, $params);

        if ($ok && $doctor !== '') {
            try {
                // Update doctor on all lab orders belonging to this patient
                $this->db->execute(
                    "UPDATE lab_entries SET doctor = :doc WHERE patient_id = :id OR patient_id IN (SELECT patient_no FROM patients WHERE id = :id)",
                    ['doc' => $doctor, 'id' => $id]
                );
            } catch (\Throwable $ignored) {}
        }

        return $ok;
    }
}
