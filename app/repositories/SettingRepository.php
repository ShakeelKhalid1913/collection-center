<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;

class SettingRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getSettings(string $orgId = 'ORG-001'): array
    {
        return $this->db->fetchOne(
            "SELECT * FROM lab_settings WHERE organization_id = :org_id LIMIT 1",
            ['org_id' => $orgId]
        ) ?: [];
    }

    public function save(array $data, string $orgId = 'ORG-001'): bool
    {
        $existing = $this->getSettings($orgId);
        $payload = [
            'org_id' => $orgId,
            'lab_name' => $data['lab_name'] ?? $data['name'] ?? ($existing['lab_name'] ?? ''),
            'address' => $data['address'] ?? ($existing['address'] ?? ''),
            'phone' => $data['phone'] ?? ($existing['phone'] ?? ''),
            'email' => $data['email'] ?? ($existing['email'] ?? ''),
            'header_text' => $data['header_text'] ?? $data['header'] ?? ($existing['header_text'] ?? ''),
            'footer_text' => $data['footer_text'] ?? $data['footer'] ?? ($existing['footer_text'] ?? ''),
            'logo_text' => $data['logo_text'] ?? $data['logo'] ?? ($existing['logo_text'] ?? 'HLP'),
            'bill_header_text' => $data['bill_header_text'] ?? $data['bill_header'] ?? ($existing['bill_header_text'] ?? $data['header_text'] ?? $data['header'] ?? ''),
            'bill_footer_text' => $data['bill_footer_text'] ?? $data['bill_footer'] ?? ($existing['bill_footer_text'] ?? $data['footer_text'] ?? $data['footer'] ?? ''),
        ];

        if ($existing === []) {
            return $this->db->execute(
                "INSERT INTO lab_settings (organization_id, lab_name, address, phone, email, header_text, footer_text, logo_text, bill_header_text, bill_footer_text)
                 VALUES (:org_id, :lab_name, :address, :phone, :email, :header_text, :footer_text, :logo_text, :bill_header_text, :bill_footer_text)",
                $payload
            );
        }

        return $this->db->execute(
            "UPDATE lab_settings SET
                lab_name = :lab_name,
                address = :address,
                phone = :phone,
                email = :email,
                header_text = :header_text,
                footer_text = :footer_text,
                logo_text = :logo_text,
                bill_header_text = :bill_header_text,
                bill_footer_text = :bill_footer_text
             WHERE organization_id = :org_id",
            $payload
        );
    }
}
