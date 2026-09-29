<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;

class DoctorShareRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        if (!$this->db->isConnected()) {
            return;
        }
        $pdo = $this->db->getPdo();
        if ($pdo === null) {
            return;
        }
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
    }

    public function getAll(string $orgId = 'ORG-001'): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM doctor_shares WHERE organization_id = ? ORDER BY doctor_name ASC',
            [$orgId]
        );
    }

    public function find(string $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM doctor_shares WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function save(array $data): bool
    {
        $id = trim((string)($data['id'] ?? ''));
        $orgId = $data['organization_id'] ?? 'ORG-001';
        $name = trim((string)($data['doctor_name'] ?? ''));
        $percent = (float)($data['commission_percent'] ?? 0);
        $notes = (string)($data['notes'] ?? '');
        $active = !empty($data['is_active']) ? 1 : 0;

        if ($name === '') {
            return false;
        }

        if ($id !== '') {
            return $this->db->execute(
                'UPDATE doctor_shares
                 SET doctor_name = :doctor_name, commission_percent = :commission_percent, notes = :notes, is_active = :is_active
                 WHERE id = :id AND organization_id = :org_id',
                [
                    'id' => $id,
                    'org_id' => $orgId,
                    'doctor_name' => $name,
                    'commission_percent' => $percent,
                    'notes' => $notes,
                    'is_active' => $active,
                ]
            );
        }

        $newId = 'DS-' . bin2hex(random_bytes(5));
        return $this->db->execute(
            'INSERT INTO doctor_shares (id, organization_id, doctor_name, commission_percent, notes, is_active)
             VALUES (:id, :org_id, :doctor_name, :commission_percent, :notes, :is_active)
             ON DUPLICATE KEY UPDATE
                commission_percent = VALUES(commission_percent),
                notes = VALUES(notes),
                is_active = VALUES(is_active)',
            [
                'id' => $newId,
                'org_id' => $orgId,
                'doctor_name' => $name,
                'commission_percent' => $percent,
                'notes' => $notes,
                'is_active' => $active,
            ]
        );
    }

    public function delete(string $id, string $orgId = 'ORG-001'): bool
    {
        return $this->db->execute(
            'DELETE FROM doctor_shares WHERE id = :id AND organization_id = :org',
            ['id' => $id, 'org' => $orgId]
        );
    }

    /**
     * Summarize referral commission from lab entries grouped by doctor.
     */
    public function commissionSummary(string $orgId = 'ORG-001', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $shares = $this->getAll($orgId);
        $rateMap = [];
        foreach ($shares as $s) {
            if (!(int)($s['is_active'] ?? 1)) {
                continue;
            }
            $rateMap[mb_strtolower(trim((string)$s['doctor_name']))] = (float)$s['commission_percent'];
        }

        $sql = 'SELECT doctor, COUNT(*) AS visits, COALESCE(SUM(amount),0) AS gross, COALESCE(SUM(paid),0) AS collected
                FROM lab_entries
                WHERE organization_id = :org';
        $params = ['org' => $orgId];
        if ($dateFrom) {
            $sql .= ' AND DATE(created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= ' AND DATE(created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }
        $sql .= ' GROUP BY doctor ORDER BY collected DESC';

        $rows = $this->db->fetchAll($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            $doctor = (string)($row['doctor'] ?? 'Walk-in / Self');
            $key = mb_strtolower(trim($doctor));
            $rate = $rateMap[$key] ?? 0.0;
            $collected = (float)($row['collected'] ?? 0);
            $out[] = [
                'doctor' => $doctor,
                'visits' => (int)($row['visits'] ?? 0),
                'gross' => (float)($row['gross'] ?? 0),
                'collected' => $collected,
                'commission_percent' => $rate,
                'commission_amount' => round($collected * $rate / 100, 0),
                'configured' => isset($rateMap[$key]),
            ];
        }
        return $out;
    }
}
