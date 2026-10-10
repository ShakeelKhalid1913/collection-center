<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;

class StockRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $sql1 = "CREATE TABLE IF NOT EXISTS inventory_items (
            id VARCHAR(64) PRIMARY KEY,
            organization_id VARCHAR(64) NOT NULL DEFAULT 'ORG-001',
            name VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL DEFAULT 'Reagent',
            unit VARCHAR(50) NOT NULL DEFAULT 'Tests',
            quantity INT NOT NULL DEFAULT 0,
            min_level INT NOT NULL DEFAULT 10,
            unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            supplier VARCHAR(255) NULL,
            expiry_date DATE NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";

        $sql2 = "CREATE TABLE IF NOT EXISTS purchase_returns (
            id VARCHAR(64) PRIMARY KEY,
            organization_id VARCHAR(64) NOT NULL DEFAULT 'ORG-001',
            item_id VARCHAR(64) NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            reason VARCHAR(255) NOT NULL,
            return_date DATE NOT NULL,
            refund_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            supplier VARCHAR(255) NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Completed',
            created_by VARCHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )";

        try {
            $this->db->execute($sql1);
            $this->db->execute($sql2);
            $this->seedInitialStock('ORG-001');
        } catch (\Throwable $e) {}
    }

    private function seedInitialStock(string $orgId): void
    {
        $count = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM inventory_items WHERE organization_id = :org_id", ['org_id' => $orgId]);
        if ((int)($count['cnt'] ?? 0) > 0) {
            return;
        }

        $items = [
            ['id' => 'STK-001', 'name' => 'CBC Diluent & Lyse Reagent Pack (5L)', 'category' => 'Hematology Reagents', 'unit' => 'Packs', 'quantity' => 12, 'min_level' => 5, 'unit_price' => 14500.00, 'supplier' => 'Sysmex Pakistan', 'expiry_date' => date('Y-m-d', strtotime('+8 months'))],
            ['id' => 'STK-002', 'name' => 'Blood Glucose Test Strips (Pack of 100)', 'category' => 'Consumables', 'unit' => 'Boxes', 'quantity' => 25, 'min_level' => 10, 'unit_price' => 3200.00, 'supplier' => 'Roche Diagnostics', 'expiry_date' => date('Y-m-d', strtotime('+12 months'))],
            ['id' => 'STK-003', 'name' => 'EDTA Lavender Top Vacuum Tubes (K2) 3ml', 'category' => 'Vials & Tubes', 'unit' => 'Trays (100)', 'quantity' => 4, 'min_level' => 8, 'unit_price' => 2400.00, 'supplier' => 'BD Vacutainer', 'expiry_date' => date('Y-m-d', strtotime('+18 months'))],
            ['id' => 'STK-004', 'name' => 'Serum Clot Activator Red Top Tubes 5ml', 'category' => 'Vials & Tubes', 'unit' => 'Trays (100)', 'quantity' => 15, 'min_level' => 6, 'unit_price' => 2200.00, 'supplier' => 'BD Vacutainer', 'expiry_date' => date('Y-m-d', strtotime('+14 months'))],
            ['id' => 'STK-005', 'name' => 'Lipid Profile Enzymatic Reagent Kit', 'category' => 'Biochemistry', 'unit' => 'Kits', 'quantity' => 3, 'min_level' => 4, 'unit_price' => 18000.00, 'supplier' => 'Merck Clinical', 'expiry_date' => date('Y-m-d', strtotime('+4 months'))],
            ['id' => 'STK-006', 'name' => 'Uric Acid Liquid Stable Reagent 100ml', 'category' => 'Biochemistry', 'unit' => 'Bottles', 'quantity' => 6, 'min_level' => 3, 'unit_price' => 5500.00, 'supplier' => 'Randox Laboratories', 'expiry_date' => date('Y-m-d', strtotime('+6 months'))],
            ['id' => 'STK-007', 'name' => 'Disposable Sterile Blood Lancets (200s)', 'category' => 'Consumables', 'unit' => 'Boxes', 'quantity' => 18, 'min_level' => 5, 'unit_price' => 850.00, 'supplier' => 'MediSafe Medical', 'expiry_date' => date('Y-m-d', strtotime('+24 months'))],
            ['id' => 'STK-008', 'name' => 'Urine 10-Parameter Test Strips', 'category' => 'Consumables', 'unit' => 'Bottles', 'quantity' => 2, 'min_level' => 5, 'unit_price' => 2800.00, 'supplier' => 'Siemens Healthineers', 'expiry_date' => date('Y-m-d', strtotime('+2 months'))],
        ];

        foreach ($items as $it) {
            $this->addItem(array_merge($it, ['organization_id' => $orgId]));
        }
    }

    public function getItems(string $orgId = 'ORG-001', string $search = '', string $category = ''): array
    {
        $where = ["organization_id = :org_id"];
        $params = ['org_id' => $orgId];

        if ($search !== '') {
            $where[] = "(name LIKE :q OR supplier LIKE :q OR id LIKE :q)";
            $params['q'] = '%' . $search . '%';
        }
        if ($category !== '') {
            $where[] = "category = :category";
            $params['category'] = $category;
        }

        $sql = "SELECT * FROM inventory_items WHERE " . implode(' AND ', $where) . " ORDER BY quantity ASC, name ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getItemById(string $id, string $orgId = 'ORG-001'): ?array
    {
        return $this->db->fetchOne("SELECT * FROM inventory_items WHERE id = :id AND organization_id = :org_id", [
            'id' => $id,
            'org_id' => $orgId,
        ]);
    }

    public function addItem(array $data): bool
    {
        $id = $data['id'] ?? ('STK-' . rand(100, 999));
        $sql = "INSERT INTO inventory_items (
            id, organization_id, name, category, unit, quantity, min_level, unit_price, supplier, expiry_date, created_at
        ) VALUES (
            :id, :org_id, :name, :category, :unit, :quantity, :min_level, :unit_price, :supplier, :expiry_date, NOW()
        )";

        return $this->db->execute($sql, [
            'id' => $id,
            'org_id' => $data['organization_id'] ?? 'ORG-001',
            'name' => trim((string)$data['name']),
            'category' => $data['category'] ?? 'Reagent',
            'unit' => $data['unit'] ?? 'Units',
            'quantity' => (int)($data['quantity'] ?? 0),
            'min_level' => (int)($data['min_level'] ?? 10),
            'unit_price' => (float)($data['unit_price'] ?? 0),
            'supplier' => !empty($data['supplier']) ? trim((string)$data['supplier']) : null,
            'expiry_date' => !empty($data['expiry_date']) ? $data['expiry_date'] : null,
        ]);
    }

    public function updateItem(string $id, array $data, string $orgId = 'ORG-001'): bool
    {
        $sql = "UPDATE inventory_items SET 
            name = :name, category = :category, unit = :unit, 
            quantity = :quantity, min_level = :min_level, unit_price = :unit_price, 
            supplier = :supplier, expiry_date = :expiry_date 
            WHERE id = :id AND organization_id = :org_id";

        return $this->db->execute($sql, [
            'id' => $id,
            'org_id' => $orgId,
            'name' => trim((string)$data['name']),
            'category' => $data['category'] ?? 'Reagent',
            'unit' => $data['unit'] ?? 'Units',
            'quantity' => (int)($data['quantity'] ?? 0),
            'min_level' => (int)($data['min_level'] ?? 10),
            'unit_price' => (float)($data['unit_price'] ?? 0),
            'supplier' => !empty($data['supplier']) ? trim((string)$data['supplier']) : null,
            'expiry_date' => !empty($data['expiry_date']) ? $data['expiry_date'] : null,
        ]);
    }

    public function adjustStock(string $id, int $adjustment, string $orgId = 'ORG-001'): bool
    {
        $sql = "UPDATE inventory_items SET quantity = GREATEST(0, quantity + :adj) WHERE id = :id AND organization_id = :org_id";
        return $this->db->execute($sql, [
            'adj' => $adjustment,
            'id' => $id,
            'org_id' => $orgId,
        ]);
    }

    public function deleteItem(string $id, string $orgId = 'ORG-001'): bool
    {
        return $this->db->execute("DELETE FROM inventory_items WHERE id = :id AND organization_id = :org_id", [
            'id' => $id,
            'org_id' => $orgId,
        ]);
    }

    public function getReturns(string $orgId = 'ORG-001'): array
    {
        $sql = "SELECT * FROM purchase_returns WHERE organization_id = :org_id ORDER BY return_date DESC, created_at DESC LIMIT 100";
        return $this->db->fetchAll($sql, ['org_id' => $orgId]);
    }

    public function createReturn(array $data): bool
    {
        $id = $data['id'] ?? ('RET-' . date('ymd') . '-' . rand(100, 999));
        $itemId = (string)$data['item_id'];
        $qty = max(1, (int)($data['quantity'] ?? 1));
        $orgId = $data['organization_id'] ?? 'ORG-001';

        $sql = "INSERT INTO purchase_returns (
            id, organization_id, item_id, item_name, quantity, reason, return_date, refund_amount, supplier, status, created_by, created_at
        ) VALUES (
            :id, :org_id, :item_id, :item_name, :quantity, :reason, :return_date, :refund_amount, :supplier, 'Completed', :created_by, NOW()
        )";

        $ok = $this->db->execute($sql, [
            'id' => $id,
            'org_id' => $orgId,
            'item_id' => $itemId,
            'item_name' => $data['item_name'] ?? 'Item',
            'quantity' => $qty,
            'reason' => $data['reason'] ?? 'Damaged / Defective',
            'return_date' => $data['return_date'] ?? date('Y-m-d'),
            'refund_amount' => (float)($data['refund_amount'] ?? 0),
            'supplier' => $data['supplier'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        if ($ok && $itemId !== '') {
            // Deduct stock for the returned items
            $this->adjustStock($itemId, -$qty, $orgId);
        }

        return $ok;
    }

    public function getStats(string $orgId = 'ORG-001'): array
    {
        $totalItems = $this->db->fetchOne("SELECT COUNT(*) AS cnt, COALESCE(SUM(quantity * unit_price), 0) AS total_val FROM inventory_items WHERE organization_id = :org_id", ['org_id' => $orgId]);
        $lowStock = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM inventory_items WHERE organization_id = :org_id AND quantity <= min_level", ['org_id' => $orgId]);
        $returns = $this->db->fetchOne("SELECT COUNT(*) AS cnt, COALESCE(SUM(refund_amount), 0) AS total_refund FROM purchase_returns WHERE organization_id = :org_id", ['org_id' => $orgId]);

        return [
            'total_items' => (int)($totalItems['cnt'] ?? 0),
            'total_stock_value' => (float)($totalItems['total_val'] ?? 0),
            'low_stock_count' => (int)($lowStock['cnt'] ?? 0),
            'total_returns' => (int)($returns['cnt'] ?? 0),
            'total_refunded' => (float)($returns['total_refund'] ?? 0),
        ];
    }
}
