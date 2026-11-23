<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;

class UserRepository
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
            $cols = $this->db->fetchAll("SHOW COLUMNS FROM users");
            $colNames = array_map(fn($c) => strtolower((string)($c['Field'] ?? '')), $cols);

            if (!in_array('password_plain', $colNames, true)) {
                $this->db->execute("ALTER TABLE users ADD COLUMN password_plain VARCHAR(255) NULL AFTER password_hash");
            }
            if (!in_array('permissions', $colNames, true)) {
                $this->db->execute("ALTER TABLE users ADD COLUMN permissions TEXT NULL");
            }
            if (!in_array('is_super_admin', $colNames, true)) {
                $this->db->execute("ALTER TABLE users ADD COLUMN is_super_admin TINYINT(1) DEFAULT 0");
            }
            if (!in_array('organization_id', $colNames, true)) {
                $this->db->execute("ALTER TABLE users ADD COLUMN organization_id VARCHAR(64) NOT NULL DEFAULT 'ORG-001'");
            }
        } catch (\Throwable $ignored) {
        }
    }

    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT u.*, o.name AS organization_name, b.name AS branch_name 
                FROM users u
                LEFT JOIN organizations o ON u.organization_id = o.id
                LEFT JOIN branches b ON u.branch_id = b.id
                WHERE u.email = :email LIMIT 1";

        return $this->db->fetchOne($sql, ['email' => $email]);
    }

    public function findById(string $id): ?array
    {
        $sql = "SELECT u.*, o.name AS organization_name, b.name AS branch_name 
                FROM users u
                LEFT JOIN organizations o ON u.organization_id = o.id
                LEFT JOIN branches b ON u.branch_id = b.id
                WHERE u.id = :id LIMIT 1";

        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    public function getAll(?string $orgId = null): array
    {
        if ($orgId !== null && $orgId !== '') {
            return $this->db->fetchAll(
                "SELECT u.*, o.name AS org_name, b.name AS branch_name 
                 FROM users u
                 LEFT JOIN organizations o ON u.organization_id = o.id
                 LEFT JOIN branches b ON u.branch_id = b.id
                 WHERE u.organization_id = :org_id
                 ORDER BY u.created_at DESC",
                ['org_id' => $orgId]
            );
        }
        return $this->db->fetchAll(
            "SELECT u.*, o.name AS org_name, b.name AS branch_name 
             FROM users u
             LEFT JOIN organizations o ON u.organization_id = o.id
             LEFT JOIN branches b ON u.branch_id = b.id
             ORDER BY u.created_at DESC"
        );
    }

    public function countActive(?string $orgId = null): int
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM users WHERE organization_id = :org_id AND is_active = 1",
            ['org_id' => $orgId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function emailExists(string $email): bool
    {
        $sql = "SELECT COUNT(*) as cnt FROM users WHERE email = :email";
        $row = $this->db->fetchOne($sql, ['email' => $email]);
        return isset($row['cnt']) && (int)$row['cnt'] > 0;
    }

    public function createUser(array $data): array
    {
        $email = trim(strtolower($data['email'] ?? ''));
        if ($this->emailExists($email)) {
            return ['success' => false, 'error' => 'An account with this email address already exists.'];
        }

        $userId = 'USR-' . bin2hex(random_bytes(6));
        $orgId = $data['organization_id'] ?? 'ORG-001';
        $branchId = $data['branch_id'] ?? 'BR-GULBERG';
        $name = trim($data['name'] ?? '');
        // Store underscore form for DB ENUM
        $portal = str_replace('-', '_', $data['portal'] ?? 'collection_center');
        $allowed = ['collection_center', 'main_lab', 'imaging', 'admin'];
        if (!in_array($portal, $allowed, true)) {
            return ['success' => false, 'error' => 'Invalid portal selection.'];
        }
        $role = $data['role'] ?? (ucwords(str_replace('_', ' ', $portal)) . ' Staff');
        $rawPassword = $data['password'] ?? '';

        if ($name === '' || $email === '' || $rawPassword === '') {
            return ['success' => false, 'error' => 'All required fields (name, email, password) must be provided.'];
        }

        if (strlen($rawPassword) < 4) {
            return ['success' => false, 'error' => 'Password must be at least 4 characters long.'];
        }

        $passwordHash = password_hash($rawPassword, PASSWORD_BCRYPT);
        $perms = isset($data['permissions']) ? (is_string($data['permissions']) ? $data['permissions'] : json_encode($data['permissions'])) : null;

        $sql = "INSERT INTO users (id, organization_id, branch_id, email, password_hash, password_plain, name, role, portal, permissions, is_active)
                VALUES (:id, :org_id, :branch_id, :email, :password_hash, :password_plain, :name, :role, :portal, :permissions, 1)";

        $inserted = $this->db->execute($sql, [
            'id' => $userId,
            'org_id' => $orgId,
            'branch_id' => $branchId,
            'email' => $email,
            'password_hash' => $passwordHash,
            'password_plain' => $rawPassword,
            'name' => $name,
            'role' => $role,
            'portal' => $portal,
            'permissions' => $perms,
        ]);

        if ($inserted) {
            $user = $this->findByEmail($email);
            return ['success' => true, 'user' => $user];
        }

        // Auto-recovery: If insert failed due to missing password_plain column on deployed server, retry without it
        $lastErr = $this->db->getLastError();
        if ($lastErr && str_contains(strtolower($lastErr), 'password_plain')) {
            $sqlFallback = "INSERT INTO users (id, organization_id, branch_id, email, password_hash, name, role, portal, permissions, is_active)
                            VALUES (:id, :org_id, :branch_id, :email, :password_hash, :name, :role, :portal, :permissions, 1)";
            $inserted = $this->db->execute($sqlFallback, [
                'id' => $userId,
                'org_id' => $orgId,
                'branch_id' => $branchId,
                'email' => $email,
                'password_hash' => $passwordHash,
                'name' => $name,
                'role' => $role,
                'portal' => $portal,
                'permissions' => $perms,
            ]);
            if ($inserted) {
                $user = $this->findByEmail($email);
                return ['success' => true, 'user' => $user];
            }
            $lastErr = $this->db->getLastError();
        }

        return [
            'success' => false,
            'error' => !empty($lastErr) ? 'Database error: ' . $lastErr : 'Failed to register account. Please try again.',
        ];
    }

    public function updateUserPermissions(string $userId, array $permissions): bool
    {
        return $this->db->execute(
            "UPDATE users SET permissions = :perms WHERE id = :id",
            [
                'id' => $userId,
                'perms' => json_encode($permissions),
            ]
        );
    }

    public function toggleUserStatus(string $userId, int $isActive): bool
    {
        return $this->db->execute(
            "UPDATE users SET is_active = :status WHERE id = :id",
            ['id' => $userId, 'status' => $isActive]
        );
    }

    public function updateUser(string $id, array $data): array
    {
        $existing = $this->findById($id);
        if (!$existing) {
            return ['success' => false, 'error' => 'User not found.'];
        }

        $email = trim(strtolower($data['email'] ?? $existing['email']));
        if ($email !== strtolower((string)$existing['email'])) {
            $duplicate = $this->findByEmail($email);
            if ($duplicate && $duplicate['id'] !== $id) {
                return ['success' => false, 'error' => 'An account with this email address already exists.'];
            }
        }

        $name = trim((string)($data['name'] ?? $existing['name']));
        $portal = str_replace('-', '_', (string)($data['portal'] ?? $existing['portal']));
        $allowed = ['collection_center', 'main_lab', 'imaging', 'admin'];
        if (!in_array($portal, $allowed, true)) {
            $portal = $existing['portal'];
        }

        $branchId = $data['branch_id'] ?? $existing['branch_id'];
        $orgId = $data['organization_id'] ?? $existing['organization_id'];
        $role = $data['role'] ?? (ucwords(str_replace('_', ' ', $portal)) . ' Staff');

        $updates = [
            'name' => $name,
            'email' => $email,
            'portal' => $portal,
            'role' => $role,
            'branch_id' => $branchId,
            'organization_id' => $orgId,
            'id' => $id,
        ];

        $sql = "UPDATE users SET name = :name, email = :email, portal = :portal, role = :role, branch_id = :branch_id, organization_id = :organization_id";

        $rawPassword = trim((string)($data['password'] ?? ''));
        if ($rawPassword !== '') {
            if (strlen($rawPassword) < 4) {
                return ['success' => false, 'error' => 'Password must be at least 4 characters long.'];
            }
            $sql .= ", password_hash = :hash, password_plain = :plain";
            $updates['hash'] = password_hash($rawPassword, PASSWORD_BCRYPT);
            $updates['plain'] = $rawPassword;
        }

        $sql .= " WHERE id = :id";

        $ok = $this->db->execute($sql, $updates);
        if ($ok) {
            return ['success' => true, 'user' => $this->findById($id)];
        }

        $lastErr = $this->db->getLastError();
        if ($lastErr && str_contains(strtolower($lastErr), 'password_plain')) {
            $sqlNoPlain = str_replace(', password_plain = :plain', '', $sql);
            unset($updates['plain']);
            $ok = $this->db->execute($sqlNoPlain, $updates);
            if ($ok) {
                return ['success' => true, 'user' => $this->findById($id)];
            }
            $lastErr = $this->db->getLastError();
        }

        return ['success' => false, 'error' => !empty($lastErr) ? 'Database error: ' . $lastErr : 'Failed to update user.'];
    }

    public function deleteUser(string $id): array
    {
        $existing = $this->findById($id);
        if (!$existing) {
            return ['success' => false, 'error' => 'User not found.'];
        }

        // Cannot delete currently logged in user
        if (isset($_SESSION['user']['id']) && $_SESSION['user']['id'] === $id) {
            return ['success' => false, 'error' => 'You cannot delete your own account while logged in.'];
        }

        $deleted = $this->db->execute("DELETE FROM users WHERE id = :id", ['id' => $id]);
        if ($deleted) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Failed to delete user.'];
    }

    public function authenticate(string $email, string $password): array
    {
        $user = $this->findByEmail(trim(strtolower($email)));

        if (!$user) {
            return ['success' => false, 'error' => 'Invalid email or password.'];
        }

        if (empty($user['is_active'])) {
            return ['success' => false, 'error' => 'This account has been deactivated.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid email or password.'];
        }

        return ['success' => true, 'user' => $user];
    }
}
