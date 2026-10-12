<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;

class B2BRepository
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
                CREATE TABLE IF NOT EXISTS b2b_clients (
                    id                  VARCHAR(64) PRIMARY KEY,
                    organization_id     VARCHAR(64) NOT NULL,
                    name                VARCHAR(255) NOT NULL,
                    code                VARCHAR(32) NULL,
                    contact_person      VARCHAR(255) NULL,
                    phone               VARCHAR(64) NULL,
                    email               VARCHAR(255) NULL,
                    address             TEXT NULL,
                    discount_percent    DECIMAL(5,2) DEFAULT 20.00,
                    is_active           TINYINT(1) DEFAULT 1,
                    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_b2b_org (organization_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Seed default partner labs if empty
            $cnt = $this->db->fetchOne("SELECT COUNT(*) AS c FROM b2b_clients WHERE organization_id = 'ORG-001'");
            if (empty($cnt['c'])) {
                $defaultB2B = [
                    ['B2B-01', 'ORG-001', 'Shifa Clinical Referral Lab', 'SHIFA-01', 'Dr. Tariq Mahmood', '0300-9876543', 'info@shifalab.pk', 'Hospital Road, Faisalabad', 25.00],
                    ['B2B-02', 'ORG-001', 'Chughtai Partner Point', 'CHUG-02', 'Farhan Qureshi', '0321-4567890', 'referral@partnerlab.pk', 'Sargodha Road, Faisalabad', 20.00],
                    ['B2B-03', 'ORG-001', 'Al-Khidmat Diagnostic Center', 'AKH-03', 'Dr. Asad Rizvi', '0333-7890123', 'alkhidmat@diagnostics.pk', 'Civil Lines, Faisalabad', 30.00],
                ];
                foreach ($defaultB2B as $row) {
                    $this->db->execute(
                        "INSERT INTO b2b_clients (id, organization_id, name, code, contact_person, phone, email, address, discount_percent, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                        $row
                    );
                }
            }
        } catch (\Throwable $e) {}

        // Add b2b_price column to tests if missing
        try {
            $cols = $this->db->fetchAll("SHOW COLUMNS FROM tests LIKE 'b2b_price'");
            if (empty($cols)) {
                $this->db->execute("ALTER TABLE tests ADD COLUMN b2b_price DECIMAL(12,2) NULL AFTER price");
                // Set default b2b_price to 75% of regular price
                $this->db->execute("UPDATE tests SET b2b_price = ROUND(price * 0.75, 0) WHERE b2b_price IS NULL");
            }
        } catch (\Throwable $e) {}

        // Add B2B and modality columns to lab_entries if missing
        try {
            $cols = $this->db->fetchAll("SHOW COLUMNS FROM lab_entries LIKE 'is_b2b'");
            if (empty($cols)) {
                $this->db->execute("ALTER TABLE lab_entries ADD COLUMN is_b2b TINYINT(1) DEFAULT 0 AFTER discount");
                $this->db->execute("ALTER TABLE lab_entries ADD COLUMN b2b_client_id VARCHAR(64) NULL AFTER is_b2b");
                $this->db->execute("ALTER TABLE lab_entries ADD COLUMN b2b_client_name VARCHAR(255) NULL AFTER b2b_client_id");
            }
        } catch (\Throwable $e) {}

        try {
            $cols = $this->db->fetchAll("SHOW COLUMNS FROM lab_entries LIKE 'modality'");
            if (empty($cols)) {
                $this->db->execute("ALTER TABLE lab_entries ADD COLUMN modality VARCHAR(64) DEFAULT 'Laboratory' AFTER b2b_client_name");
            }
        } catch (\Throwable $e) {}

        // Add columns to imaging_scans if missing
        try {
            $cols = $this->db->fetchAll("SHOW COLUMNS FROM imaging_scans LIKE 'organization_id'");
            if (empty($cols)) {
                $this->db->execute("ALTER TABLE imaging_scans ADD COLUMN organization_id VARCHAR(64) NOT NULL DEFAULT 'ORG-001' AFTER id");
                $this->db->execute("ALTER TABLE imaging_scans ADD COLUMN branch_id VARCHAR(64) NULL AFTER organization_id");
                $this->db->execute("ALTER TABLE imaging_scans ADD COLUMN amount DECIMAL(12,2) DEFAULT 0 AFTER impression");
                $this->db->execute("ALTER TABLE imaging_scans ADD COLUMN paid DECIMAL(12,2) DEFAULT 0 AFTER amount");
                $this->db->execute("ALTER TABLE imaging_scans ADD COLUMN discount DECIMAL(12,2) DEFAULT 0 AFTER paid");
                $this->db->execute("ALTER TABLE imaging_scans ADD COLUMN is_b2b TINYINT(1) DEFAULT 0 AFTER discount");
                $this->db->execute("ALTER TABLE imaging_scans ADD COLUMN b2b_client_name VARCHAR(255) NULL AFTER is_b2b");
            }
        } catch (\Throwable $e) {}
    }

    public function getActiveClients(?string $orgId = null): array
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        return $this->db->fetchAll(
            "SELECT * FROM b2b_clients WHERE organization_id = :org AND is_active = 1 ORDER BY name ASC",
            ['org' => $orgId]
        );
    }

    public function getAllClients(?string $orgId = null): array
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        return $this->db->fetchAll(
            "SELECT * FROM b2b_clients WHERE organization_id = :org ORDER BY created_at DESC",
            ['org' => $orgId]
        );
    }

    public function findById(string $id, ?string $orgId = null): ?array
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        return $this->db->fetchOne(
            "SELECT * FROM b2b_clients WHERE id = :id AND organization_id = :org LIMIT 1",
            ['id' => $id, 'org' => $orgId]
        );
    }

    public function saveClient(array $data, ?string $orgId = null): bool
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        $id = !empty($data['id']) ? (string)$data['id'] : ('B2B-' . bin2hex(random_bytes(4)));
        $name = trim((string)($data['name'] ?? ''));
        $code = trim((string)($data['code'] ?? ''));
        $contact = trim((string)($data['contact_person'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $address = trim((string)($data['address'] ?? ''));
        $discount = (float)($data['discount_percent'] ?? 20.0);
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        if ($name === '') {
            return false;
        }

        $existing = $this->findById($id, $orgId);
        if ($existing) {
            return $this->db->execute(
                "UPDATE b2b_clients 
                 SET name = :name, code = :code, contact_person = :contact, phone = :phone, email = :email, 
                     address = :address, discount_percent = :disc, is_active = :active
                 WHERE id = :id AND organization_id = :org",
                [
                    'id' => $id,
                    'org' => $orgId,
                    'name' => $name,
                    'code' => $code,
                    'contact' => $contact,
                    'phone' => $phone,
                    'email' => $email,
                    'address' => $address,
                    'disc' => $discount,
                    'active' => $isActive,
                ]
            );
        }

        return $this->db->execute(
            "INSERT INTO b2b_clients (id, organization_id, name, code, contact_person, phone, email, address, discount_percent, is_active)
             VALUES (:id, :org, :name, :code, :contact, :phone, :email, :address, :disc, :active)",
            [
                'id' => $id,
                'org' => $orgId,
                'name' => $name,
                'code' => $code,
                'contact' => $contact,
                'phone' => $phone,
                'email' => $email,
                'address' => $address,
                'disc' => $discount,
                'active' => $isActive,
            ]
        );
    }

    public function deleteClient(string $id, string $orgId = 'ORG-001'): bool
    {
        return $this->db->execute(
            "DELETE FROM b2b_clients WHERE id = :id AND organization_id = :org",
            ['id' => $id, 'org' => $orgId]
        );
    }

    public function getTestRates(string $orgId = 'ORG-001', string $q = ''): array
    {
        $sql = "SELECT id, code, name, category, price, COALESCE(b2b_price, ROUND(price * 0.75, 0)) AS b2b_price 
                FROM tests 
                WHERE organization_id = :org";
        $params = ['org' => $orgId];

        if ($q !== '') {
            $sql .= " AND (code LIKE :q OR name LIKE :q OR category LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }

        $sql .= " ORDER BY category ASC, name ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function updateTestB2BPrice(string $testId, float $b2bPrice, string $orgId = 'ORG-001'): bool
    {
        return $this->db->execute(
            "UPDATE tests SET b2b_price = :b2b WHERE id = :id AND organization_id = :org",
            ['id' => $testId, 'b2b' => $b2bPrice, 'org' => $orgId]
        );
    }

    public function updateBulkDiscount(float $discountPercent, string $orgId = 'ORG-001'): int
    {
        $factor = (100.0 - max(0, min(100, $discountPercent))) / 100.0;
        return $this->db->execute(
            "UPDATE tests SET b2b_price = ROUND(price * :factor, 0) WHERE organization_id = :org",
            ['factor' => $factor, 'org' => $orgId]
        ) ? 1 : 0;
    }
}
