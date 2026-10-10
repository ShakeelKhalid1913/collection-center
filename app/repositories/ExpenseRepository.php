<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;

class ExpenseRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $sql = "CREATE TABLE IF NOT EXISTS expenses (
            id VARCHAR(64) PRIMARY KEY,
            organization_id VARCHAR(64) NOT NULL DEFAULT 'ORG-001',
            branch VARCHAR(64) NOT NULL DEFAULT 'CC-01',
            title VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL DEFAULT 'Other',
            amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            payment_mode VARCHAR(50) NOT NULL DEFAULT 'Cash',
            receipt_no VARCHAR(100) NULL,
            notes TEXT NULL,
            expense_date DATE NOT NULL,
            created_by VARCHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )";
        try {
            $this->db->execute($sql);
        } catch (\Throwable $e) {}
    }

    public function getAll(string $orgId = 'ORG-001', array $filters = []): array
    {
        $where = ["organization_id = :org_id"];
        $params = ['org_id' => $orgId];

        if (!empty($filters['category'])) {
            $where[] = "category = :category";
            $params['category'] = $filters['category'];
        }
        if (!empty($filters['branch'])) {
            $where[] = "branch = :branch";
            $params['branch'] = $filters['branch'];
        }
        if (!empty($filters['from_date'])) {
            $where[] = "expense_date >= :from_date";
            $params['from_date'] = $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $where[] = "expense_date <= :to_date";
            $params['to_date'] = $filters['to_date'];
        }
        if (!empty($filters['q'])) {
            $where[] = "(title LIKE :q OR receipt_no LIKE :q OR notes LIKE :q)";
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sql = "SELECT * FROM expenses WHERE " . implode(' AND ', $where) . " ORDER BY expense_date DESC, created_at DESC LIMIT 200";
        return $this->db->fetchAll($sql, $params);
    }

    public function create(array $data): bool
    {
        $id = $data['id'] ?? ('EXP-' . date('ymd') . '-' . rand(1000, 9999));
        $sql = "INSERT INTO expenses (
            id, organization_id, branch, title, category, amount, payment_mode, receipt_no, notes, expense_date, created_by, created_at
        ) VALUES (
            :id, :org_id, :branch, :title, :category, :amount, :payment_mode, :receipt_no, :notes, :expense_date, :created_by, NOW()
        )";

        return $this->db->execute($sql, [
            'id' => $id,
            'org_id' => $data['organization_id'] ?? 'ORG-001',
            'branch' => $data['branch'] ?? 'CC-01',
            'title' => trim((string)$data['title']),
            'category' => $data['category'] ?? 'Other',
            'amount' => (float)($data['amount'] ?? 0),
            'payment_mode' => $data['payment_mode'] ?? 'Cash',
            'receipt_no' => !empty($data['receipt_no']) ? trim((string)$data['receipt_no']) : null,
            'notes' => !empty($data['notes']) ? trim((string)$data['notes']) : null,
            'expense_date' => $data['expense_date'] ?? date('Y-m-d'),
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    public function delete(string $id, string $orgId = 'ORG-001'): bool
    {
        return $this->db->execute("DELETE FROM expenses WHERE id = :id AND organization_id = :org_id", [
            'id' => $id,
            'org_id' => $orgId,
        ]);
    }

    public function getStats(string $orgId = 'ORG-001'): array
    {
        $today = date('Y-m-d');
        $thisMonth = date('Y-m-01');

        $todayRow = $this->db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE organization_id = :org_id AND expense_date = :today",
            ['org_id' => $orgId, 'today' => $today]
        );

        $monthRow = $this->db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS cnt FROM expenses WHERE organization_id = :org_id AND expense_date >= :month_start",
            ['org_id' => $orgId, 'month_start' => $thisMonth]
        );

        $topCat = $this->db->fetchOne(
            "SELECT category, SUM(amount) AS total FROM expenses WHERE organization_id = :org_id GROUP BY category ORDER BY total DESC LIMIT 1",
            ['org_id' => $orgId]
        );

        return [
            'today_total' => (float)($todayRow['total'] ?? 0),
            'month_total' => (float)($monthRow['total'] ?? 0),
            'month_count' => (int)($monthRow['cnt'] ?? 0),
            'top_category' => $topCat['category'] ?? 'None',
        ];
    }
}
