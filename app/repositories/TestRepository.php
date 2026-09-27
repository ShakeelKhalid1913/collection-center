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
            "INSERT INTO tests (id, organization_id, code, name, category, price, sample_type, unit, normal_range, normal_value, reference_value, methodology)
             VALUES (:id, :org_id, :code, :name, :category, :price, :sample_type, :unit, :normal_range, :normal_value, :reference_value, :methodology)",
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
                'normal_value' => $data['normal_value'] ?? '',
                'reference_value' => $data['reference_value'] ?? '',
                'methodology' => $data['methodology'] ?? '',
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

    public function getParameters(string $testId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM test_parameters WHERE test_id = :test_id ORDER BY sort_order",
            ['test_id' => $testId]
        );
    }

    public function getParametersByTestIds(array $testIds): array
    {
        if ($testIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($testIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT * FROM test_parameters WHERE test_id IN ({$placeholders}) ORDER BY test_id, sort_order",
            $testIds
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['test_id']][] = $row;
        }
        return $result;
    }

    public function saveParameters(string $testId, array $params): bool
    {
        $this->db->execute("DELETE FROM test_parameters WHERE test_id = :test_id", ['test_id' => $testId]);
        foreach ($params as $i => $p) {
            $id = 'PRM-' . bin2hex(random_bytes(4));
            $this->db->execute(
                "INSERT INTO test_parameters (id, test_id, name, unit, normal_value, reference_range, sort_order)
                 VALUES (:id, :test_id, :name, :unit, :normal_value, :reference_range, :sort_order)",
                [
                    'id' => $id,
                    'test_id' => $testId,
                    'name' => $p['name'] ?? '',
                    'unit' => $p['unit'] ?? '',
                    'normal_value' => $p['normal_value'] ?? '',
                    'reference_range' => $p['reference_range'] ?? '',
                    'sort_order' => $i,
                ]
            );
        }
        return true;
    }

    public function deleteTest(string $testId): bool
    {
        return $this->db->execute("DELETE FROM tests WHERE id = :id", ['id' => $testId]);
    }

    public function updateTest(string $testId, array $data): bool
    {
        $sets = [];
        $params = ['id' => $testId];
        $allowed = ['code', 'name', 'category', 'price', 'sample_type', 'unit', 'normal_range', 'normal_value', 'reference_value', 'methodology'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }
        if ($sets === []) return true;
        return $this->db->execute("UPDATE tests SET " . implode(', ', $sets) . " WHERE id = :id", $params);
    }

    public function findById(string $testId): ?array
    {
        $row = $this->db->fetchOne("SELECT * FROM tests WHERE id = :id", ['id' => $testId]);
        return $row ?: null;
    }
}
