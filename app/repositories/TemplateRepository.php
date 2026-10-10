<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;

class TemplateRepository
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

        $sql = "CREATE TABLE IF NOT EXISTS report_templates (
            id VARCHAR(64) PRIMARY KEY,
            organization_id VARCHAR(64) NOT NULL DEFAULT 'ORG-001',
            title VARCHAR(255) NOT NULL,
            department VARCHAR(100) NOT NULL DEFAULT 'General',
            content MEDIUMTEXT NOT NULL,
            is_private TINYINT(1) NOT NULL DEFAULT 0,
            created_by VARCHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";

        try {
            $this->db->execute($sql);
            $this->seedInitialTemplates('ORG-001');
        } catch (\Throwable $e) {}
    }

    private function seedInitialTemplates(string $orgId): void
    {
        $count = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM report_templates WHERE organization_id = :org_id", ['org_id' => $orgId]);
        if ((int)($count['cnt'] ?? 0) > 0) {
            return;
        }

        $templates = [
            [
                'id' => 'TPL-US-01',
                'title' => 'Ultrasound Whole Abdomen & Pelvis (Normal)',
                'department' => 'Radiology',
                'is_private' => 0,
                'content' => "LIVER: Normal in size, shape and acoustic texture. No focal mass lesion or intrahepatic biliary dilatation seen.\n\nGALLBLADDER: Normal in size, thin-walled, acoustic lumen is clear. No calculus or mass noted.\n\nPANCREAS & SPLEEN: Normal in size and homogenous texture. No mass lesion noted.\n\nKIDNEYS: Both kidneys are normal in size, position and cortical thickness. Normal corticomedullary differentiation preserved. No calculus or hydronephrosis seen.\n\nURINARY BLADDER: Well distended, smooth wall, lumen clear.\n\nIMPRESSION: Normal ultrasound study of whole abdomen and pelvis.",
            ],
            [
                'id' => 'TPL-XR-01',
                'title' => 'Chest X-Ray PA View (Normal Clinical Finding)',
                'department' => 'Radiology',
                'is_private' => 0,
                'content' => "CHEST PA VIEW:\n\n- Lung fields: Clear with normal bronchovascular markings. No focal airspace consolidation, mass, or cavity noted.\n- Costophrenic & cardiophrenic angles: Sharp and clear bilaterally.\n- Cardiac silhouette: Normal in size and contour. Cardiothoracic ratio is within normal limits (< 50%).\n- Hilar structures & mediastinum: Normal appearance.\n- Bony cage & soft tissues: Intact.\n\nIMPRESSION: Normal radiological study of chest.",
            ],
            [
                'id' => 'TPL-ECG-01',
                'title' => '12-Lead Electrocardiogram (Normal Sinus Rhythm)',
                'department' => 'Cardiology',
                'is_private' => 0,
                'content' => "12-LEAD ECG FINDINGS:\n\n- Rhythm: Normal Sinus Rhythm\n- Heart Rate: 72 bpm\n- PR Interval: 0.16 sec (Normal: 0.12 - 0.20s)\n- QRS Duration: 0.08 sec (Normal: < 0.10s)\n- QTc: 410 ms\n- Axis: Normal cardiac axis (+45°)\n- ST-T wave changes: No significant ST elevation/depression or T wave inversion.\n\nIMPRESSION: Normal 12-lead Electrocardiogram (ECG).",
            ],
            [
                'id' => 'TPL-HISTO-01',
                'title' => 'Histopathology Biopsy Routine Narrative',
                'department' => 'Histopathology',
                'is_private' => 1,
                'content' => "GROSS EXAMINATION:\nReceived specimen labeled as biopsy in formalin container consisting of grayish-white soft tissue pieces measuring 1.2 x 0.8 x 0.4 cm. Entire tissue submitted for processing.\n\nMICROSCOPIC EXAMINATION:\nSections show stratified squamous epithelium with underlying fibrovascular stroma. Mild non-specific chronic inflammatory infiltrate composed of mature lymphocytes and plasma cells is noted. No evidence of cellular atypia, dysplasia, or malignancy seen in the examined sections.\n\nDIAGNOSIS: Non-specific chronic inflammation. Negative for malignancy.",
            ],
            [
                'id' => 'TPL-MICRO-01',
                'title' => 'Urine Culture & Sensitivity (No Growth 48 Hrs)',
                'department' => 'Microbiology',
                'is_private' => 0,
                'content' => "SPECIMEN: Clean catch mid-stream urine (MSU)\n\nCULTURE & SENSITIVITY:\n- Sample inoculated on CLED and MacConkey agar plates and incubated aerobically at 37°C for 48 hours.\n\nRESULT:\nNo bacterial pathogen isolated after 48 hours of aerobic incubation at 37°C (< 1,000 CFU/ml).\n\nCOMMENT: Sterile urine culture.",
            ],
        ];

        foreach ($templates as $tpl) {
            $this->create(array_merge($tpl, ['organization_id' => $orgId, 'created_by' => 'admin']));
        }
    }

    public function getAll(string $orgId = 'ORG-001', ?string $userId = null, string $department = ''): array
    {
        $where = ["organization_id = :org_id"];
        $params = ['org_id' => $orgId];

        if ($userId) {
            $where[] = "(is_private = 0 OR created_by = :user_id OR :user_admin = 'admin')";
            $params['user_id'] = $userId;
            $params['user_admin'] = $userId;
        }

        if ($department !== '') {
            $where[] = "department = :dept";
            $params['dept'] = $department;
        }

        $sql = "SELECT * FROM report_templates WHERE " . implode(' AND ', $where) . " ORDER BY department ASC, title ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getById(string $id, string $orgId = 'ORG-001'): ?array
    {
        return $this->db->fetchOne("SELECT * FROM report_templates WHERE id = :id AND organization_id = :org_id", [
            'id' => $id,
            'org_id' => $orgId,
        ]);
    }

    public function create(array $data): bool
    {
        $id = $data['id'] ?? ('TPL-' . date('ymd') . '-' . rand(100, 999));
        $sql = "INSERT INTO report_templates (
            id, organization_id, title, department, content, is_private, created_by, created_at
        ) VALUES (
            :id, :org_id, :title, :department, :content, :is_private, :created_by, NOW()
        )";

        return $this->db->execute($sql, [
            'id' => $id,
            'org_id' => $data['organization_id'] ?? 'ORG-001',
            'title' => trim((string)$data['title']),
            'department' => $data['department'] ?? 'General',
            'content' => trim((string)$data['content']),
            'is_private' => !empty($data['is_private']) ? 1 : 0,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    public function update(string $id, array $data, string $orgId = 'ORG-001'): bool
    {
        $sql = "UPDATE report_templates SET 
            title = :title, department = :department, content = :content, is_private = :is_private 
            WHERE id = :id AND organization_id = :org_id";

        return $this->db->execute($sql, [
            'id' => $id,
            'org_id' => $orgId,
            'title' => trim((string)$data['title']),
            'department' => $data['department'] ?? 'General',
            'content' => trim((string)$data['content']),
            'is_private' => !empty($data['is_private']) ? 1 : 0,
        ]);
    }

    public function delete(string $id, string $orgId = 'ORG-001'): bool
    {
        return $this->db->execute("DELETE FROM report_templates WHERE id = :id AND organization_id = :org_id", [
            'id' => $id,
            'org_id' => $orgId,
        ]);
    }
}
