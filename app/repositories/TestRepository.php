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
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        foreach ([
            "ALTER TABLE tests ADD COLUMN methodology TEXT NULL",
            "ALTER TABLE tests ADD COLUMN normal_value VARCHAR(128) NULL",
            "ALTER TABLE tests ADD COLUMN reference_value VARCHAR(128) NULL",
            "ALTER TABLE tests ADD COLUMN result_type VARCHAR(32) NULL",
            "ALTER TABLE tests ADD COLUMN result_options VARCHAR(255) NULL",
            "ALTER TABLE tests ADD COLUMN report_template VARCHAR(64) NULL",
        ] as $sql) {
            try {
                $this->db->execute($sql);
            } catch (\Throwable $ignored) {
            }
        }
    }

    public function updateMethodology(string $testId, string $methodology): bool
    {
        return $this->db->execute(
            "UPDATE tests SET methodology = :methodology WHERE id = :id",
            ['id' => $testId, 'methodology' => $methodology]
        );
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
            "INSERT INTO tests (id, organization_id, code, name, category, price, sample_type, unit, normal_range, normal_value, reference_value, methodology, result_type, result_options, report_template)
             VALUES (:id, :org_id, :code, :name, :category, :price, :sample_type, :unit, :normal_range, :normal_value, :reference_value, :methodology, :result_type, :result_options, :report_template)",
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
                'result_type' => $data['result_type'] ?? 'Numeric',
                'result_options' => $data['result_options'] ?? '',
                'report_template' => $data['report_template'] ?? 'default',
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
                "INSERT INTO test_parameters (id, test_id, section, name, unit, normal_value, reference_range, sub_table, sort_order)
                 VALUES (:id, :test_id, :section, :name, :unit, :normal_value, :reference_range, :sub_table, :sort_order)",
                [
                    'id' => $id,
                    'test_id' => $testId,
                    'section' => !empty($p['section']) ? trim($p['section']) : null,
                    'name' => $p['name'] ?? '',
                    'unit' => $p['unit'] ?? '',
                    'normal_value' => $p['normal_value'] ?? '',
                    'reference_range' => $p['reference_range'] ?? '',
                    'sub_table' => !empty($p['sub_table']) ? trim($p['sub_table']) : null,
                    'sort_order' => $i + 1,
                ]
            );
        }

        // Clean up or synchronize results table
        $test = $this->findById($testId);
        if ($test) {
            $tName = trim((string)($test['name'] ?? ''));
            $tCode = trim((string)($test['code'] ?? ''));
            if ($params === []) {
                // Test now has 0 parameters:
                // Delete orphaned parameter rows where parameter != test name
                $this->db->execute(
                    "DELETE FROM results 
                     WHERE (LOWER(test) = LOWER(:name) OR LOWER(test) = LOWER(:code))
                       AND LOWER(parameter) != LOWER(:name)
                       AND LOWER(parameter) != LOWER(:code)
                       AND (value IS NULL OR value = '' OR value = 'Pending' OR value = '-' OR value = '—')",
                    ['name' => $tName, 'code' => $tCode]
                );
                // For any rows that remain, convert them to single test row
                $this->db->execute(
                    "UPDATE results 
                     SET parameter = :name, section = NULL,
                         unit = CASE WHEN unit IS NULL OR unit = '' OR unit = '—' THEN :unit ELSE unit END,
                         reference_range = CASE WHEN reference_range IS NULL OR reference_range = '' OR reference_range = '—' THEN :range ELSE reference_range END
                     WHERE (LOWER(test) = LOWER(:name) OR LOWER(test) = LOWER(:code))",
                    [
                        'name' => $tName,
                        'code' => $tCode,
                        'unit' => $test['unit'] ?? '—',
                        'range' => $test['reference_value'] ?: ($test['normal_value'] ?: ($test['normal_range'] ?? '—')),
                    ]
                );
            } else {
                $paramNames = [];
                foreach ($params as $p) {
                    $nm = trim((string)($p['name'] ?? ''));
                    if ($nm !== '') {
                        $paramNames[] = $nm;
                    }
                }
                if ($paramNames !== []) {
                    $inPlaceholders = implode(',', array_fill(0, count($paramNames), '?'));
                    $delSql = "DELETE FROM results 
                               WHERE (LOWER(test) = LOWER(?) OR LOWER(test) = LOWER(?))
                                 AND parameter NOT IN ({$inPlaceholders})
                                 AND (value IS NULL OR value = '' OR value = 'Pending' OR value = '-' OR value = '—')";
                    $this->db->execute($delSql, array_merge([$tName, $tCode], $paramNames));
                }
            }
        }

        return true;
    }

    public function findByCode(string $code, string $orgId = 'ORG-001'): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        // 1) Exact code (case-insensitive)
        $row = $this->db->fetchOne(
            "SELECT * FROM tests WHERE organization_id = :org_id AND UPPER(code) = UPPER(:code) LIMIT 1",
            ['code' => $code, 'org_id' => $orgId]
        );
        if ($row) {
            return $row;
        }

        // 2) Exact name
        $row = $this->db->fetchOne(
            "SELECT * FROM tests WHERE organization_id = :org_id AND name = :name LIMIT 1",
            ['name' => $code, 'org_id' => $orgId]
        );
        if ($row) {
            return $row;
        }

        // 3) Smart prefix: "CBC" → "CBC (Complete Blood Count)", never "CBC For (Dengue)"
        // Prefer: name starts with "CODE (", then tests that have parameters, then shorter names.
        $row = $this->db->fetchOne(
            "SELECT t.*,
                    (SELECT COUNT(*) FROM test_parameters tp WHERE tp.test_id = t.id) AS param_count
             FROM tests t
             WHERE t.organization_id = :org_id
               AND (
                    t.name LIKE :paren
                    OR t.name LIKE :space
                    OR t.name LIKE :paren_s
                    OR t.name LIKE :space_s
               )
             ORDER BY
                CASE
                    WHEN t.name LIKE :paren2 THEN 0
                    WHEN t.name LIKE :paren_s2 THEN 1
                    ELSE 2
                END,
                CASE WHEN LOWER(t.name) LIKE '%complete blood count%' THEN 0 ELSE 1 END,
                param_count DESC,
                LENGTH(t.name) ASC
             LIMIT 1",
            [
                'org_id' => $orgId,
                'paren' => $code . ' (%',
                'paren2' => $code . ' (%',
                'space' => $code . ' %',
                'paren_s' => $code . 's (%',
                'paren_s2' => $code . 's (%',
                'space_s' => $code . 's %',
            ]
        );
        if ($row) {
            return $row;
        }

        // 4) Contains match: "Complete Blood Count" → "CBC (Complete Blood Count)"
        //    "Liver Function Test" → "LFTs (Liver Function Tests)"
        $row = $this->db->fetchOne(
            "SELECT t.*,
                    (SELECT COUNT(*) FROM test_parameters tp WHERE tp.test_id = t.id) AS param_count
             FROM tests t
             WHERE t.organization_id = :org_id
               AND LOWER(t.name) LIKE :contains
             ORDER BY param_count DESC, LENGTH(t.name) ASC
             LIMIT 1",
            [
                'org_id' => $orgId,
                'contains' => '%' . strtolower($code) . '%',
            ]
        );
        return $row ?: null;
    }

    public function deleteTest(string $testId): bool
    {
        return $this->db->execute("DELETE FROM tests WHERE id = :id", ['id' => $testId]);
    }

    public function updateTest(string $testId, array $data): bool
    {
        $sets = [];
        $params = ['id' => $testId];
        $allowed = ['code', 'name', 'category', 'price', 'sample_type', 'unit', 'normal_range', 'normal_value', 'reference_value', 'methodology', 'result_type', 'result_options', 'report_template'];
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
