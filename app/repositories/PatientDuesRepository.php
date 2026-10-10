<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;

class PatientDuesRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensurePaymentsTable();
    }

    private function ensurePaymentsTable(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $sql = "CREATE TABLE IF NOT EXISTS due_payments (
            id VARCHAR(64) PRIMARY KEY,
            organization_id VARCHAR(64) NOT NULL DEFAULT 'ORG-001',
            lab_no VARCHAR(64) NOT NULL,
            patient_id VARCHAR(64) NOT NULL,
            patient_name VARCHAR(255) NOT NULL,
            amount_paid DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            previous_due DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            remaining_due DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            payment_mode VARCHAR(50) NOT NULL DEFAULT 'Cash',
            receipt_no VARCHAR(100) NULL,
            notes TEXT NULL,
            collected_by VARCHAR(64) NULL,
            payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )";

        try {
            $this->db->execute($sql);
        } catch (\Throwable $e) {}
    }

    public function getDuesList(string $orgId = 'ORG-001', string $search = '', string $branch = ''): array
    {
        // Calculate due = (amount - paid - discount)
        $where = [
            "e.organization_id = :org_id",
            "(e.amount - COALESCE(e.paid, 0) - COALESCE(e.discount, 0)) > 0"
        ];
        $params = ['org_id' => $orgId];

        if ($search !== '') {
            $where[] = "(e.lab_no LIKE :q OR e.patient_name LIKE :q OR e.patient_id LIKE :q OR p.phone LIKE :q)";
            $params['q'] = '%' . $search . '%';
        }
        if ($branch !== '') {
            $where[] = "e.branch = :branch";
            $params['branch'] = $branch;
        }

        $sql = "SELECT e.*, 
            (e.amount - COALESCE(e.paid, 0) - COALESCE(e.discount, 0)) AS balance_due,
            p.phone, p.patient_no, p.title AS patient_title, p.gender, p.age
            FROM lab_entries e
            LEFT JOIN patients p ON (e.patient_id = p.id OR e.patient_id = p.patient_no)
            WHERE " . implode(' AND ', $where) . "
            ORDER BY balance_due DESC, e.created_at DESC LIMIT 150";

        return $this->db->fetchAll($sql, $params);
    }

    public function collectDue(string $labNo, float $payingAmount, string $paymentMode = 'Cash', ?string $notes = null, ?string $userId = null, string $orgId = 'ORG-001'): array
    {
        if ($payingAmount <= 0) {
            return ['success' => false, 'error' => 'Amount paid must be greater than zero.'];
        }

        $entry = $this->db->fetchOne("SELECT * FROM lab_entries WHERE lab_no = :lab_no AND organization_id = :org_id", [
            'lab_no' => $labNo,
            'org_id' => $orgId,
        ]);

        if (!$entry) {
            return ['success' => false, 'error' => 'Lab entry not found.'];
        }

        $total = (float)$entry['amount'];
        $paid = (float)($entry['paid'] ?? 0);
        $discount = (float)($entry['discount'] ?? 0);
        $prevDue = max(0.0, $total - $paid - $discount);

        if ($prevDue <= 0.0) {
            return ['success' => false, 'error' => 'No outstanding balance on this case.'];
        }

        $pay = min($payingAmount, $prevDue);
        $newPaid = $paid + $pay;
        $remainingDue = max(0.0, $prevDue - $pay);

        // Update lab_entries
        $ok = $this->db->execute(
            "UPDATE lab_entries SET paid = :paid WHERE lab_no = :lab_no AND organization_id = :org_id",
            ['paid' => $newPaid, 'lab_no' => $labNo, 'org_id' => $orgId]
        );

        if (!$ok) {
            return ['success' => false, 'error' => 'Failed to update lab entry balance.'];
        }

        // Record due payment receipt
        $paymentId = 'PAY-' . date('ymd') . '-' . rand(1000, 9999);
        $receiptNo = 'RCP-DUE-' . rand(10000, 99999);

        $this->db->execute(
            "INSERT INTO due_payments (
                id, organization_id, lab_no, patient_id, patient_name, amount_paid, previous_due, remaining_due, payment_mode, receipt_no, notes, collected_by, payment_date
            ) VALUES (
                :id, :org_id, :lab_no, :patient_id, :patient_name, :amount_paid, :previous_due, :remaining_due, :payment_mode, :receipt_no, :notes, :collected_by, NOW()
            )",
            [
                'id' => $paymentId,
                'org_id' => $orgId,
                'lab_no' => $labNo,
                'patient_id' => $entry['patient_id'] ?? '',
                'patient_name' => $entry['patient_name'] ?? '',
                'amount_paid' => $pay,
                'previous_due' => $prevDue,
                'remaining_due' => $remainingDue,
                'payment_mode' => $paymentMode,
                'receipt_no' => $receiptNo,
                'notes' => $notes,
                'collected_by' => $userId,
            ]
        );

        return [
            'success' => true,
            'payment_id' => $paymentId,
            'receipt_no' => $receiptNo,
            'paid' => $pay,
            'remaining_due' => $remainingDue,
        ];
    }

    public function getPaymentHistory(string $orgId = 'ORG-001', int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM due_payments WHERE organization_id = :org_id ORDER BY payment_date DESC LIMIT {$limit}",
            ['org_id' => $orgId]
        );
    }

    public function getDuesStats(string $orgId = 'ORG-001'): array
    {
        $today = date('Y-m-d');
        $duesRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS total_patients, 
             COALESCE(SUM(amount - COALESCE(paid, 0) - COALESCE(discount, 0)), 0) AS total_due 
             FROM lab_entries 
             WHERE organization_id = :org_id AND (amount - COALESCE(paid, 0) - COALESCE(discount, 0)) > 0",
            ['org_id' => $orgId]
        );

        $recoveredToday = $this->db->fetchOne(
            "SELECT COALESCE(SUM(amount_paid), 0) AS total_recovered 
             FROM due_payments 
             WHERE organization_id = :org_id AND DATE(payment_date) = :today",
            ['org_id' => $orgId, 'today' => $today]
        );

        return [
            'total_due' => (float)($duesRow['total_due'] ?? 0),
            'pending_cases' => (int)($duesRow['total_patients'] ?? 0),
            'recovered_today' => (float)($recoveredToday['total_recovered'] ?? 0),
        ];
    }
}
