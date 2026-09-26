<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;

class TestRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getTests(string $orgId = 'ORG-001'): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM tests WHERE organization_id = :org_id ORDER BY category, name",
            ['org_id' => $orgId]
        );
    }

    public function getPackages(string $orgId = 'ORG-001'): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM packages WHERE organization_id = :org_id ORDER BY name",
            ['org_id' => $orgId]
        );
    }

    public function countTests(string $orgId = 'ORG-001'): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM tests WHERE organization_id = :org_id",
            ['org_id' => $orgId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countPackages(string $orgId = 'ORG-001'): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM packages WHERE organization_id = :org_id",
            ['org_id' => $orgId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function findByCodes(array $codes, string $orgId = 'ORG-001'): array
    {
        if ($codes === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $params = array_values($codes);
        $params[] = $orgId;
        return $this->db->fetchAll(
            "SELECT * FROM tests WHERE code IN ({$placeholders}) AND organization_id = ?",
            $params
        );
    }

    public function createTest(array $data): array
    {
        $id = 'TST-' . bin2hex(random_bytes(4));
        $code = strtoupper(trim($data['code'] ?? ''));
        if ($code === '' || trim($data['name'] ?? '') === '') {
            return ['success' => false, 'error' => 'Code and name are required.'];
        }
        $ok = $this->db->execute(
            "INSERT INTO tests (id, organization_id, code, name, category, price, sample_type, unit, normal_range)
             VALUES (:id, :org_id, :code, :name, :category, :price, :sample_type, :unit, :normal_range)",
            [
                'id' => $id,
                'org_id' => $data['organization_id'] ?? 'ORG-001',
                'code' => $code,
                'name' => trim($data['name']),
                'category' => $data['category'] ?? 'General',
                'price' => (float)($data['price'] ?? 0),
                'sample_type' => $data['sample_type'] ?? $data['sample'] ?? 'Blood',
                'unit' => $data['unit'] ?? '—',
                'normal_range' => $data['normal_range'] ?? $data['range'] ?? '—',
            ]
        );
        return ['success' => $ok, 'id' => $id];
    }

    public function createPackage(array $data): array
    {
        $id = 'PKG-' . bin2hex(random_bytes(4));
        $code = strtoupper(trim($data['code'] ?? ''));
        if ($code === '' || trim($data['name'] ?? '') === '') {
            return ['success' => false, 'error' => 'Code and name are required.'];
        }
        $ok = $this->db->execute(
            "INSERT INTO packages (id, organization_id, code, name, tests_included, price, regular_price)
             VALUES (:id, :org_id, :code, :name, :tests, :price, :regular)",
            [
                'id' => $id,
                'org_id' => $data['organization_id'] ?? 'ORG-001',
                'code' => $code,
                'name' => trim($data['name']),
                'tests' => $data['tests_included'] ?? $data['tests'] ?? '',
                'price' => (float)($data['price'] ?? 0),
                'regular' => (float)($data['regular_price'] ?? $data['regular'] ?? $data['price'] ?? 0),
            ]
        );
        return ['success' => $ok, 'id' => $id];
    }
}
