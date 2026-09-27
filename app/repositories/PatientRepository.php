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
        $sql = "SELECT * FROM patients 
                WHERE organization_id = :org_id 
                AND (full_name LIKE :q OR phone LIKE :q OR cnic LIKE :q OR patient_no LIKE :q)
                ORDER BY created_at DESC LIMIT 30";

        return $this->db->fetchAll($sql, [
            'org_id' => $orgId,
            'q' => '%' . $query . '%',
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

    public function create(array $data): array
    {
        $manualNo = trim((string)($data['patient_no'] ?? ''));
        $patientNo = $manualNo !== '' ? $manualNo : ('MR-' . rand(10000, 99999));
        $id = $data['id'] ?? ('P-' . bin2hex(random_bytes(4)));

        $sql = "INSERT INTO patients (
                    id, organization_id, patient_no, title, full_name, relation, relation_of,
                    phone, phone_alt, email, cnic, blood_group, dob, age, gender,
                    address, city, emergency_name, emergency_phone, internal_notes,
                    patient_type, panel_code, branch, created_by
                ) VALUES (
                    :id, :org_id, :patient_no, :title, :full_name, :relation, :relation_of,
                    :phone, :phone_alt, :email, :cnic, :blood_group, :dob, :age, :gender,
                    :address, :city, :emergency_name, :emergency_phone, :notes,
                    :patient_type, :panel_code, :branch, :created_by
                )";

        $dob = $data['dob'] ?? null;
        if ($dob === '') {
            $dob = null;
        }

        $ok = $this->db->execute($sql, [
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
            'emergency_name' => $data['emergency_name'] ?? '',
            'emergency_phone' => $data['emergency_phone'] ?? '',
            'notes' => $data['notes'] ?? $data['internal_notes'] ?? '',
            'patient_type' => $data['patient_type'] ?? $data['ptype'] ?? 'Walk-in',
            'panel_code' => $data['panel_code'] ?? $data['panel'] ?? '',
            'branch' => $data['branch'] ?? 'CC-01',
            'created_by' => $data['created_by'] ?? null,
        ]);

        return ['success' => $ok, 'id' => $id, 'patient_no' => $patientNo];
    }
}
