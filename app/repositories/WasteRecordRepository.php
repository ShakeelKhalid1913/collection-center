<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;

class WasteRecordRepository
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
        $pdo->exec("CREATE TABLE IF NOT EXISTS waste_records (
            id              VARCHAR(64) PRIMARY KEY,
            organization_id VARCHAR(64) NOT NULL,
            title           VARCHAR(255) NOT NULL,
            notes           TEXT,
            record_date     DATE NOT NULL,
            mou_image       LONGBLOB NULL,
            mou_image_mime  VARCHAR(64) NULL,
            slip_image      LONGBLOB NULL,
            slip_image_mime VARCHAR(64) NULL,
            created_by      VARCHAR(128) NULL,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_waste_org_date (organization_id, record_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function getAll(string $orgId = 'ORG-001', int $limit = 100): array
    {
        return $this->db->fetchAll(
            "SELECT id, organization_id, title, notes, record_date, created_by, created_at,
                    (mou_image IS NOT NULL) AS has_mou,
                    (slip_image IS NOT NULL) AS has_slip,
                    mou_image_mime, slip_image_mime
             FROM waste_records
             WHERE organization_id = ?
             ORDER BY record_date DESC, created_at DESC
             LIMIT {$limit}",
            [$orgId]
        );
    }

    public function find(string $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM waste_records WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function create(array $data): bool
    {
        $id = 'WR-' . bin2hex(random_bytes(6));
        return $this->db->execute(
            "INSERT INTO waste_records
                (id, organization_id, title, notes, record_date, mou_image, mou_image_mime, slip_image, slip_image_mime, created_by)
             VALUES
                (:id, :org_id, :title, :notes, :record_date, :mou_image, :mou_image_mime, :slip_image, :slip_image_mime, :created_by)",
            [
                'id' => $id,
                'org_id' => $data['organization_id'] ?? 'ORG-001',
                'title' => $data['title'] ?? 'Waste Record',
                'notes' => $data['notes'] ?? '',
                'record_date' => $data['record_date'] ?? date('Y-m-d'),
                'mou_image' => $data['mou_image'] ?? null,
                'mou_image_mime' => $data['mou_image_mime'] ?? null,
                'slip_image' => $data['slip_image'] ?? null,
                'slip_image_mime' => $data['slip_image_mime'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]
        );
    }

    public function delete(string $id, string $orgId = 'ORG-001'): bool
    {
        return $this->db->execute(
            'DELETE FROM waste_records WHERE id = :id AND organization_id = :org',
            ['id' => $id, 'org' => $orgId]
        );
    }
}
