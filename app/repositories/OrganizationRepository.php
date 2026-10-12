<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;

class OrganizationRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll(): array
    {
        return $this->db->fetchAll(
            "SELECT o.*, 
                    (SELECT COUNT(*) FROM branches b WHERE b.organization_id = o.id) AS branch_count,
                    (SELECT COUNT(*) FROM users u WHERE u.organization_id = o.id) AS user_count,
                    (SELECT COUNT(*) FROM patients p WHERE p.organization_id = o.id) AS patient_count,
                    (SELECT COUNT(*) FROM lab_entries le WHERE le.organization_id = o.id) AS entry_count
             FROM organizations o
             ORDER BY o.created_at DESC"
        );
    }

    public function findById(string $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM organizations WHERE id = :id LIMIT 1",
            ['id' => $id]
        );
    }

    public function createOrganization(array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            return ['success' => false, 'error' => 'Company / Organization name is required.'];
        }

        $id = !empty($data['id']) ? trim((string)$data['id']) : ('ORG-' . sprintf('%03d', rand(100, 999)));
        // Check ID uniqueness
        if ($this->findById($id)) {
            $id = 'ORG-' . bin2hex(random_bytes(3));
        }

        $phone = trim((string)($data['phone'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $address = trim((string)($data['address'] ?? ''));

        $ok = $this->db->execute(
            "INSERT INTO organizations (id, name, phone, email, address, is_active)
             VALUES (:id, :name, :phone, :email, :address, 1)",
            [
                'id' => $id,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'address' => $address,
            ]
        );

        if (!$ok) {
            return ['success' => false, 'error' => 'Could not save organization to database.'];
        }

        // Initialize default main lab branch for this organization
        $branchId = 'BR-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $this->db->execute(
            "INSERT IGNORE INTO branches (id, organization_id, code, name, branch_type, address, phone)
             VALUES (:bid, :oid, 'LAB-01', :bname, 'main_lab', :baddr, :bphone)",
            [
                'bid' => $branchId,
                'oid' => $id,
                'bname' => $name . ' (Main Lab)',
                'baddr' => $address,
                'bphone' => $phone,
            ]
        );

        // Initialize default lab_settings row for branding isolation
        $this->db->execute(
            "INSERT IGNORE INTO lab_settings (organization_id, lab_name, address, phone, email, header_text, footer_text)
             VALUES (:oid, :name, :address, :phone, :email, 'Quality diagnostics you can trust', 'Get well soon.')",
            [
                'oid' => $id,
                'name' => $name,
                'address' => $address,
                'phone' => $phone,
                'email' => $email,
            ]
        );

        // Seed independent test catalog from CSV for this new company so their catalog is isolated
        try {
            require_once dirname(__DIR__) . '/database/import_tests_csv.php';
            $csvPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tests.csv';
            $pdo = $this->db->getPdo();
            if ($pdo !== null && is_file($csvPath) && function_exists('import_tests_from_csv')) {
                import_tests_from_csv($pdo, $csvPath, $id);
                if (function_exists('seed_panel_parameters_for_imported_catalog')) {
                    seed_panel_parameters_for_imported_catalog($pdo, $id);
                }
            }
        } catch (\Throwable $ignored) {}

        // Copy default packages for this new company
        try {
            $this->db->execute(
                "INSERT IGNORE INTO packages (id, organization_id, code, name, tests_included, price, regular_price)
                 SELECT CONCAT('PKG-', SUBSTR(MD5(CONCAT(:oid, code)), 1, 6)), :oid, code, name, tests_included, price, regular_price
                 FROM packages WHERE organization_id = 'ORG-001'",
                ['oid' => $id]
            );
        } catch (\Throwable $ignored) {}

        // Copy default doctor signatories for this new company
        try {
            $this->db->execute(
                "INSERT IGNORE INTO report_signatories (id, organization_id, slot_number, name, qualifications, designation, is_active, sort_order)
                 SELECT CONCAT('SIG-', SUBSTR(MD5(CONCAT(:oid, slot_number)), 1, 6)), :oid, slot_number, name, qualifications, designation, is_active, sort_order
                 FROM report_signatories WHERE organization_id = 'ORG-001'",
                ['oid' => $id]
            );
        } catch (\Throwable $ignored) {}

        // Optionally create Company Admin Account if email and password provided
        $adminEmail = trim(strtolower((string)($data['admin_email'] ?? '')));
        $adminPass = (string)($data['admin_password'] ?? '');
        $adminName = trim((string)($data['admin_name'] ?? ($name . ' Admin')));

        if ($adminEmail !== '' && $adminPass !== '') {
            $userRepo = new UserRepository();
            if (!$userRepo->emailExists($adminEmail)) {
                $userRepo->createUser([
                    'organization_id' => $id,
                    'branch_id' => $branchId,
                    'email' => $adminEmail,
                    'password' => $adminPass,
                    'name' => $adminName,
                    'portal' => 'admin',
                    'role' => 'Company Admin',
                ]);
            }
        }

        return ['success' => true, 'id' => $id, 'branch_id' => $branchId];
    }

    public function updateOrganization(string $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];
        foreach (['name', 'phone', 'email', 'address', 'is_active'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = :{$f}";
                $params[$f] = $data[$f];
            }
        }
        if (empty($fields)) return true;

        return $this->db->execute(
            "UPDATE organizations SET " . implode(', ', $fields) . " WHERE id = :id",
            $params
        );
    }
}
