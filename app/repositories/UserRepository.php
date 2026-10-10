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

    public function getAll(string $orgId = 'ORG-001'): array
    {
        return $this->db->fetchAll(
            "SELECT u.*, b.name AS branch_name 
             FROM users u
             LEFT JOIN branches b ON u.branch_id = b.id
             WHERE u.organization_id = :org_id
             ORDER BY u.created_at DESC",
            ['org_id' => $orgId]
        );
    }

    public function countActive(string $orgId = 'ORG-001'): int
    {
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

        $sql = "INSERT INTO users (id, organization_id, branch_id, email, password_hash, name, role, portal, permissions, is_active)
                VALUES (:id, :org_id, :branch_id, :email, :password_hash, :name, :role, :portal, :permissions, 1)";

        $inserted = $this->db->execute($sql, [
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

        return ['success' => false, 'error' => 'Failed to register account. Please try again.'];
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
