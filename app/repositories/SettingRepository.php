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
        // Avoid loading LONGBLOB on every page — only mime + has flag.
        try {
            $row = $this->db->fetchOne(
                "SELECT organization_id, lab_name, address, phone, email,
                        header_text, footer_text, logo_text,
                        bill_header_text, bill_footer_text,
                        header_image_mime,
                        header_image_ver,
                        CASE WHEN header_image IS NOT NULL AND LENGTH(header_image) > 0 THEN 1 ELSE 0 END AS has_header_image
                 FROM lab_settings WHERE organization_id = :org_id LIMIT 1",
                ['org_id' => $orgId]
            );
            return $row ?: [];
        } catch (\Throwable $ignored) {
            $row = $this->db->fetchOne(
                "SELECT * FROM lab_settings WHERE organization_id = :org_id LIMIT 1",
                ['org_id' => $orgId]
            ) ?: [];
            if ($row !== []) {
                $row['has_header_image'] = !empty($row['header_image']) || !empty($row['header_image_mime']);
                unset($row['header_image']);
            }
            return $row;
        }
    }

    /**
     * @return array{data: string, mime: string}|null
     */
    public function getHeaderImage(string $orgId = 'ORG-001'): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT header_image, header_image_mime FROM lab_settings WHERE organization_id = :org_id LIMIT 1",
            ['org_id' => $orgId]
        );
        if ($row === null || empty($row['header_image'])) {
            return null;
        }
        return [
            'data' => $row['header_image'],
            'mime' => $row['header_image_mime'] ?: 'image/png',
        ];
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
            $ok = $this->db->execute(
                "INSERT INTO lab_settings (organization_id, lab_name, address, phone, email, header_text, footer_text, logo_text, bill_header_text, bill_footer_text)
                 VALUES (:org_id, :lab_name, :address, :phone, :email, :header_text, :footer_text, :logo_text, :bill_header_text, :bill_footer_text)",
                $payload
            );
        } else {
            $ok = $this->db->execute(
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

        if (!$ok) {
            return false;
        }

        if (!empty($data['clear_header_image'])) {
            return $this->clearHeaderImage($orgId);
        }

        if (array_key_exists('header_image', $data) && $data['header_image'] !== null) {
            return $this->saveHeaderImage($orgId, (string)$data['header_image'], (string)($data['header_image_mime'] ?? 'image/png'));
        }

        return true;
    }

    public function saveHeaderImage(string $orgId, string $binary, string $mime): bool
    {
        try {
            return $this->db->execute(
                "UPDATE lab_settings SET
                    header_image = :img,
                    header_image_mime = :mime,
                    header_image_ver = COALESCE(header_image_ver, 0) + 1
                 WHERE organization_id = :org_id",
                [
                    'img' => $binary,
                    'mime' => $mime,
                    'org_id' => $orgId,
                ]
            );
        } catch (\Throwable $ignored) {
            return $this->db->execute(
                "UPDATE lab_settings SET header_image = :img, header_image_mime = :mime WHERE organization_id = :org_id",
                [
                    'img' => $binary,
                    'mime' => $mime,
                    'org_id' => $orgId,
                ]
            );
        }
    }

    public function clearHeaderImage(string $orgId): bool
    {
        return $this->db->execute(
            "UPDATE lab_settings SET header_image = NULL, header_image_mime = NULL WHERE organization_id = :org_id",
            ['org_id' => $orgId]
        );
    }
    public function getSignatories(string $orgId = 'ORG-001'): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM report_signatories WHERE organization_id = :org_id AND is_active = 1 ORDER BY sort_order ASC",
                ['org_id' => $orgId]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function saveSignatories(string $orgId, array $signatories): bool
    {
        try {
            $this->db->execute("DELETE FROM report_signatories WHERE organization_id = :org_id", ['org_id' => $orgId]);
            foreach ($signatories as $index => $sig) {
                if (empty($sig['name'])) {
                    continue;
                }
                $this->db->execute(
                    "INSERT INTO report_signatories (id, organization_id, slot_number, name, qualifications, designation, sort_order)
                     VALUES (:id, :org_id, :slot, :name, :quals, :desig, :sort)",
                    [
                        'id' => 'SIG-' . bin2hex(random_bytes(4)),
                        'org_id' => $orgId,
                        'slot' => $index + 1,
                        'name' => $sig['name'],
                        'quals' => $sig['qualifications'] ?? '',
                        'desig' => $sig['designation'] ?? '',
                        'sort' => $index
                    ]
                );
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
