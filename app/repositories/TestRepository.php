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
        if ($done || !$this->db->isConnected()) {
            return;
        }
        $done = true;
        try {
            $this->db->execute(
                "CREATE TABLE IF NOT EXISTS organization_test_prices (
                    organization_id VARCHAR(64) NOT NULL,
                    test_id VARCHAR(64) NOT NULL,
                    price DECIMAL(10,2) NOT NULL,
                    b2b_price DECIMAL(10,2) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (organization_id, test_id),
                    KEY idx_org_test_org (organization_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
            );
            $this->db->execute(
                "CREATE TABLE IF NOT EXISTS organization_package_prices (
                    organization_id VARCHAR(64) NOT NULL,
                    package_id VARCHAR(64) NOT NULL,
                    price DECIMAL(10,2) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (organization_id, package_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
            );

            $cols = $this->db->fetchAll("SHOW COLUMNS FROM tests");
            $existing = array_column($cols, 'Field');
            $toAdd = [
                'methodology' => "ALTER TABLE tests ADD COLUMN methodology TEXT NULL",
                'normal_value' => "ALTER TABLE tests ADD COLUMN normal_value VARCHAR(128) NULL",
                'reference_value' => "ALTER TABLE tests ADD COLUMN reference_value VARCHAR(128) NULL",
                'result_type' => "ALTER TABLE tests ADD COLUMN result_type VARCHAR(32) NULL",
                'result_options' => "ALTER TABLE tests ADD COLUMN result_options VARCHAR(255) NULL",
                'report_template' => "ALTER TABLE tests ADD COLUMN report_template VARCHAR(64) NULL",
            ];
            foreach ($toAdd as $col => $sql) {
                if (!in_array($col, $existing, true)) {
                    $this->db->execute($sql);
                }
            }

            // Ensure screening / serology tests (HIV, HBsAg, HCV, VDRL, H. Pylori, MP) are strictly Negative / Positive
            $this->db->execute(
                "UPDATE tests SET result_options = 'Negative, Positive', normal_range = 'Negative', reference_value = 'Negative', normal_value = 'Negative', category = 'Serology', result_type = 'options' 
                 WHERE code IN ('ANTI-HIV-SCREENING', '030211', '00291', '00290', 'ANTI-HCV-SCREENING', 'HBSAG-SCREENING', 'VDRL-SCREENING', 'H-PYLORI-SCREENING', 'MP-MALARIA-PARASITE') OR name LIKE '%HIV%' OR name LIKE '%HCV%' OR name LIKE '%HBSAG%' OR name LIKE '%VDRL%' OR name LIKE '%PYLORI%' OR name LIKE '%Malaria%' OR name LIKE '%MP (%'"
            );

            // Ensure HbA1c is strictly numeric input field (never qualitative dropdown)
            $this->db->execute(
                "UPDATE tests SET result_type = 'numeric', result_options = NULL, unit = '%', reference_value = '< 5.7 %', normal_value = '< 5.7'
                 WHERE code LIKE '%hba1c%' OR name LIKE '%hba1c%' OR name LIKE '%glycated%'"
            );
        } catch (\Throwable $ignored) {
        }
    }

    public function updateMethodology(string $testId, string $methodology): bool
    {
        return $this->db->execute(
            "UPDATE tests SET methodology = :methodology WHERE id = :id",
            ['id' => $testId, 'methodology' => $methodology]
        );
    }

    public function updateOrganizationPrice(string $orgId, string $testId, float $price, ?float $b2bPrice = null): bool
    {
        return $this->db->execute(
            "INSERT INTO organization_test_prices (organization_id, test_id, price, b2b_price)
             VALUES (:org_id, :test_id, :price, :b2b_price)
             ON DUPLICATE KEY UPDATE 
                price = VALUES(price),
                b2b_price = VALUES(b2b_price),
                updated_at = CURRENT_TIMESTAMP",
            [
                'org_id' => $orgId,
                'test_id' => $testId,
                'price' => $price,
                'b2b_price' => $b2bPrice,
            ]
        );
    }

    public function updateOrganizationPackagePrice(string $orgId, string $packageId, float $price): bool
    {
        return $this->db->execute(
            "INSERT INTO organization_package_prices (organization_id, package_id, price)
             VALUES (:org_id, :package_id, :price)
             ON DUPLICATE KEY UPDATE 
                price = VALUES(price),
                updated_at = CURRENT_TIMESTAMP",
            [
                'org_id' => $orgId,
                'package_id' => $packageId,
                'price' => $price,
            ]
        );
    }

    public function getTests(?string $orgId = null): array
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        return $this->db->fetchAll(
            "SELECT t.*,
                    COALESCE(otp.price, t.price) AS price,
                    COALESCE(otp.b2b_price, ROUND(COALESCE(otp.price, t.price) * 0.75, 0)) AS b2b_price,
                    CASE WHEN otp.price IS NOT NULL THEN 1 ELSE 0 END AS has_custom_price
             FROM tests t
             LEFT JOIN organization_test_prices otp 
                 ON otp.test_id = t.id AND otp.organization_id = :org_id
             ORDER BY t.category, t.name",
            ['org_id' => $orgId]
        );
    }

    public function getPackages(?string $orgId = null): array
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        return $this->db->fetchAll(
            "SELECT p.*,
                    COALESCE(opp.price, p.price) AS price,
                    CASE WHEN opp.price IS NOT NULL THEN 1 ELSE 0 END AS has_custom_price
             FROM packages p
             LEFT JOIN organization_package_prices opp
                 ON opp.package_id = p.id AND opp.organization_id = :org_id
             ORDER BY p.name",
            ['org_id' => $orgId]
        );
    }

    public function countTests(?string $orgId = null): int
    {
        $row = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM tests");
        return (int)($row['cnt'] ?? 0);
    }

    public function countPackages(?string $orgId = null): int
    {
        $row = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM packages");
        return (int)($row['cnt'] ?? 0);
    }

    public function findByCodes(array $codes, ?string $orgId = null): array
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        if ($codes === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $params = array_values($codes);
        $params[] = $orgId;
        return $this->db->fetchAll(
            "SELECT t.*,
                    COALESCE(otp.price, t.price) AS price,
                    COALESCE(otp.b2b_price, ROUND(COALESCE(otp.price, t.price) * 0.75, 0)) AS b2b_price
             FROM tests t
             LEFT JOIN organization_test_prices otp 
                 ON otp.test_id = t.id AND otp.organization_id = ?
             WHERE t.code IN ({$placeholders})",
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

    public function updatePackage(string $id, array $data, ?string $orgId = null): bool
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        return $this->db->execute(
            "UPDATE packages 
             SET code = :code, name = :name, tests_included = :tests, price = :price, regular_price = :regular 
             WHERE (id = :id OR code = :id2) AND organization_id = :org_id",
            [
                'id' => $id,
                'id2' => $id,
                'org_id' => $orgId,
                'code' => strtoupper(trim((string)($data['code'] ?? ''))),
                'name' => trim((string)($data['name'] ?? '')),
                'tests' => trim((string)($data['tests_included'] ?? $data['tests'] ?? '')),
                'price' => (float)($data['price'] ?? 0),
                'regular' => (float)($data['regular_price'] ?? $data['regular'] ?? $data['price'] ?? 0),
            ]
        );
    }

    public function deletePackage(string $id, ?string $orgId = null): bool
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        return $this->db->execute(
            "DELETE FROM packages WHERE (id = :id OR code = :id2) AND organization_id = :org_id",
            ['id' => $id, 'id2' => $id, 'org_id' => $orgId]
        );
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
                "INSERT INTO test_parameters (id, test_id, section, name, unit, normal_value, reference_range, sub_table, result_note, sort_order)
                 VALUES (:id, :test_id, :section, :name, :unit, :normal_value, :reference_range, :sub_table, :result_note, :sort_order)",
                [
                    'id' => $id,
                    'test_id' => $testId,
                    'section' => !empty($p['section']) ? trim($p['section']) : null,
                    'name' => $p['name'] ?? '',
                    'unit' => $p['unit'] ?? '',
                    'normal_value' => $p['normal_value'] ?? '',
                    'reference_range' => $p['reference_range'] ?? '',
                    'sub_table' => !empty($p['sub_table']) ? trim($p['sub_table']) : null,
                    'result_note' => !empty($p['result_note']) ? trim($p['result_note']) : null,
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

    public function findByCode(string $code, ?string $orgId = null): ?array
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        // 1) Exact code (case-insensitive)
        $row = $this->db->fetchOne(
            "SELECT t.*,
                    COALESCE(otp.price, t.price) AS price,
                    COALESCE(otp.b2b_price, ROUND(COALESCE(otp.price, t.price) * 0.75, 0)) AS b2b_price
             FROM tests t
             LEFT JOIN organization_test_prices otp 
                 ON otp.test_id = t.id AND otp.organization_id = :org_id
             WHERE UPPER(t.code) = UPPER(:code)
             LIMIT 1",
            ['code' => $code, 'org_id' => $orgId]
        );
        if ($row) {
            return $row;
        }

        // 2) Exact name
        $row = $this->db->fetchOne(
            "SELECT t.*,
                    COALESCE(otp.price, t.price) AS price,
                    COALESCE(otp.b2b_price, ROUND(COALESCE(otp.price, t.price) * 0.75, 0)) AS b2b_price
             FROM tests t
             LEFT JOIN organization_test_prices otp 
                 ON otp.test_id = t.id AND otp.organization_id = :org_id
             WHERE t.name = :name
             LIMIT 1",
            ['name' => $code, 'org_id' => $orgId]
        );
        if ($row) {
            return $row;
        }

        // 2b) High-accuracy Serology Aliases (HIV, HCV, HBsAg, VDRL, etc.)
        $cleanSearch = preg_replace('/[-\/_]+/', ' ', strtolower($code));
        $cleanSearch = preg_replace('/\s+/', ' ', trim((string)$cleanSearch));

        if (preg_match('/\b(elisa|antibodies\s+elisa)\b/i', $cleanSearch) && preg_match('/\bhiv\b/i', $cleanSearch)) {
            $row = $this->db->fetchOne(
                "SELECT t.*, COALESCE(otp.price, t.price) AS price, COALESCE(otp.b2b_price, ROUND(COALESCE(otp.price, t.price) * 0.75, 0)) AS b2b_price,
                        (SELECT COUNT(*) FROM test_parameters tp WHERE tp.test_id = t.id) AS param_count
                 FROM tests t LEFT JOIN organization_test_prices otp ON otp.test_id = t.id AND otp.organization_id = :org_id
                 WHERE t.code = '030211' OR t.name LIKE '%Anti HIV Antibodies Elisa%' LIMIT 1",
                ['org_id' => $orgId]
            );
            if ($row) return $row;
        }
        if (preg_match('/^(anti\s*)?hiv(\s*(screening|rapid|test|1\s*2|antibodies|strip|card))?$/i', $cleanSearch) || preg_match('/\bhiv\b/i', $cleanSearch)) {
            $row = $this->db->fetchOne(
                "SELECT t.*, COALESCE(otp.price, t.price) AS price, COALESCE(otp.b2b_price, ROUND(COALESCE(otp.price, t.price) * 0.75, 0)) AS b2b_price,
                        (SELECT COUNT(*) FROM test_parameters tp WHERE tp.test_id = t.id) AS param_count
                 FROM tests t LEFT JOIN organization_test_prices otp ON otp.test_id = t.id AND otp.organization_id = :org_id
                 WHERE t.code = 'ANTI-HIV-SCREENING' OR t.name = 'ANTI HIV SCREENING' LIMIT 1",
                ['org_id' => $orgId]
            );
            if ($row) return $row;
        }
        if (preg_match('/^(anti\s*)?hcv(\s*(screening|rapid|test|antibodies|strip|card))?$/i', $cleanSearch) || preg_match('/\bhcv\b/i', $cleanSearch)) {
            $row = $this->db->fetchOne(
                "SELECT t.*, COALESCE(otp.price, t.price) AS price, COALESCE(otp.b2b_price, ROUND(COALESCE(otp.price, t.price) * 0.75, 0)) AS b2b_price,
                        (SELECT COUNT(*) FROM test_parameters tp WHERE tp.test_id = t.id) AS param_count
                 FROM tests t LEFT JOIN organization_test_prices otp ON otp.test_id = t.id AND otp.organization_id = :org_id
                 WHERE t.code = 'ANTI-HCV-SCREENING' OR t.name = 'ANTI HCV SCREENING' LIMIT 1",
                ['org_id' => $orgId]
            );
            if ($row) return $row;
        }
        if (preg_match('/^(hbs\s*ag|hbsag)(\s*(screening|rapid|test|strip|card))?$/i', $cleanSearch) || preg_match('/\bhbsag\b/i', $cleanSearch)) {
            $row = $this->db->fetchOne(
                "SELECT t.*, COALESCE(otp.price, t.price) AS price, COALESCE(otp.b2b_price, ROUND(COALESCE(otp.price, t.price) * 0.75, 0)) AS b2b_price,
                        (SELECT COUNT(*) FROM test_parameters tp WHERE tp.test_id = t.id) AS param_count
                 FROM tests t LEFT JOIN organization_test_prices otp ON otp.test_id = t.id AND otp.organization_id = :org_id
                 WHERE t.code = 'HBSAG-SCREENING' OR t.name = 'HBSAG SCREENING' LIMIT 1",
                ['org_id' => $orgId]
            );
            if ($row) return $row;
        }

        // 3) Smart prefix: "CBC" → "CBC (Complete Blood Count)", never "CBC For (Dengue)"
        // Prefer: name starts with "CODE (", then tests that have parameters, then shorter names.
        $row = $this->db->fetchOne(
            "SELECT t.*,
                    COALESCE(otp.price, t.price) AS price,
                    COALESCE(otp.b2b_price, ROUND(COALESCE(otp.price, t.price) * 0.75, 0)) AS b2b_price,
                    (SELECT COUNT(*) FROM test_parameters tp WHERE tp.test_id = t.id) AS param_count
             FROM tests t
             LEFT JOIN organization_test_prices otp 
                 ON otp.test_id = t.id AND otp.organization_id = :org_id
             WHERE (
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
                    COALESCE(otp.price, t.price) AS price,
                    COALESCE(otp.b2b_price, ROUND(COALESCE(otp.price, t.price) * 0.75, 0)) AS b2b_price,
                    (SELECT COUNT(*) FROM test_parameters tp WHERE tp.test_id = t.id) AS param_count
             FROM tests t
             LEFT JOIN organization_test_prices otp 
                 ON otp.test_id = t.id AND otp.organization_id = :org_id
             WHERE LOWER(t.name) LIKE :contains
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

    public function findById(string $testId, ?string $orgId = null): ?array
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        $row = $this->db->fetchOne(
            "SELECT t.*,
                    COALESCE(otp.price, t.price) AS price,
                    COALESCE(otp.b2b_price, ROUND(COALESCE(otp.price, t.price) * 0.75, 0)) AS b2b_price
             FROM tests t
             LEFT JOIN organization_test_prices otp 
                 ON otp.test_id = t.id AND otp.organization_id = :org_id
             WHERE t.id = :id
             LIMIT 1",
            ['id' => $testId, 'org_id' => $orgId]
        );
        return $row ?: null;
    }
}
