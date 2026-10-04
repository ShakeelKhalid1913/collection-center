<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;

class ResultRepository
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
        if ($done) {
            return;
        }
        $done = true;
        foreach ([
            'ALTER TABLE results ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1',
            'ALTER TABLE results ADD COLUMN print_page INT NOT NULL DEFAULT 1',
        ] as $sql) {
            try {
                $this->db->execute($sql);
            } catch (\Throwable $ignored) {
                // Column already exists
            }
        }
    }

    public function getSamples(): array
    {
        return $this->db->fetchAll("SELECT * FROM samples ORDER BY created_at DESC");
    }

    public function getPendingResults(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM results WHERE verified_at IS NULL AND (value IS NULL OR value = '') ORDER BY created_at DESC"
        );
    }

    public function getResultsByLabNo(string $labNo): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM results WHERE lab_no = :lab_no ORDER BY print_page ASC, sort_order ASC, id ASC",
            ['lab_no' => $labNo]
        );
    }

    /**
     * Assign each booked test name to a print page (1-based).
     * Array key order is used as print order within each page.
     *
     * @param array<string,int|string> $testToPage
     */
    public function assignPrintPages(string $labNo, array $testToPage): bool
    {
        $byPage = [];
        foreach ($testToPage as $testName => $page) {
            $testName = trim((string)$testName);
            if ($testName === '') {
                continue;
            }
            $byPage[max(1, (int)$page)][] = $testName;
        }
        if ($byPage === []) {
            return true;
        }
        ksort($byPage, SORT_NUMERIC);

        $ok = true;
        $sort = 1;
        foreach ($byPage as $pageNo => $tests) {
            foreach ($tests as $testName) {
                $rows = $this->db->fetchAll(
                    "SELECT id FROM results WHERE lab_no = :lab_no AND test = :test ORDER BY sort_order ASC, id ASC",
                    ['lab_no' => $labNo, 'test' => $testName]
                );
                if ($rows === []) {
                    // Soft fallback: match ignoring case
                    $rows = $this->db->fetchAll(
                        "SELECT id FROM results WHERE lab_no = :lab_no AND LOWER(test) = LOWER(:test) ORDER BY sort_order ASC, id ASC",
                        ['lab_no' => $labNo, 'test' => $testName]
                    );
                }
                foreach ($rows as $row) {
                    $ok = $this->db->execute(
                        "UPDATE results SET print_page = :page, sort_order = :sort WHERE id = :id AND lab_no = :lab_no",
                        [
                            'page' => $pageNo,
                            'sort' => $sort++,
                            'id' => $row['id'],
                            'lab_no' => $labNo,
                        ]
                    ) && $ok;
                }
            }
        }
        return $ok;
    }

    /**
     * Build map from parallel page_map_test[] / page_map_page[] POST arrays
     * (avoids broken PHP keys when test names contain brackets/spaces).
     *
     * @param array<int,string> $tests
     * @param array<int,string|int> $pages
     * @return array<string,int>
     */
    public static function pageMapFromParallel(array $tests, array $pages): array
    {
        $map = [];
        foreach ($tests as $i => $testName) {
            $testName = trim((string)$testName);
            if ($testName === '') {
                continue;
            }
            $map[$testName] = max(1, (int)($pages[$i] ?? 1));
        }
        return $map;
    }

    /**
     * Remember current print_page per test name for this visit.
     *
     * @return array<string,int>
     */
    public function getPrintPageByTest(string $labNo): array
    {
        $rows = $this->db->fetchAll(
            "SELECT test, MIN(print_page) AS print_page FROM results WHERE lab_no = :lab_no GROUP BY test",
            ['lab_no' => $labNo]
        );
        $map = [];
        foreach ($rows as $r) {
            $t = trim((string)($r['test'] ?? ''));
            if ($t !== '') {
                $map[$t] = max(1, (int)($r['print_page'] ?? 1));
            }
        }
        return $map;
    }

    public function ensureResultsInitialized(string $labNo, string $testsString, string $patientName, string $orgId = 'ORG-001'): array
    {
        $testRepo = new TestRepository();
        $existing = $this->getResultsByLabNo($labNo);
        $savedPages = $this->getPrintPageByTest($labNo);

        // Expand packages → individual test names, then resolve each to catalog
        $rawNames = array_filter(array_map('trim', explode(',', $testsString)));
        $resolvedTests = []; // list of ['label' => display name, 'obj' => test row|null]

        foreach ($rawNames as $tName) {
            // Package? expand its tests list
            $pkg = $this->db->fetchOne(
                "SELECT * FROM packages WHERE organization_id = :org AND (name = :n OR code = :c) LIMIT 1",
                ['org' => $orgId, 'n' => $tName, 'c' => $tName]
            );
            if ($pkg && !empty($pkg['tests_included'])) {
                // Drop any old stub row named after the package itself
                $this->db->execute(
                    "DELETE FROM results WHERE lab_no = :lab_no AND LOWER(test) = LOWER(:pkg)",
                    ['lab_no' => $labNo, 'pkg' => $tName]
                );
                foreach (array_filter(array_map('trim', explode(',', (string)$pkg['tests_included']))) as $piece) {
                    $obj = $testRepo->findByCode($piece, $orgId);
                    $resolvedTests[] = [
                        'label' => $obj['name'] ?? $piece,
                        'obj' => $obj,
                        'booked_as' => $piece,
                    ];
                }
                continue;
            }

            $obj = $testRepo->findByCode($tName, $orgId);
            $resolvedTests[] = [
                'label' => $obj['name'] ?? $tName,
                'obj' => $obj,
                'booked_as' => $tName,
            ];
        }

        if ($resolvedTests === []) {
            return $existing;
        }

        // Index existing rows by normalized test title
        $byTest = [];
        foreach ($existing as $r) {
            $key = strtolower(trim((string)($r['test'] ?? '')));
            $byTest[$key][] = $r;
        }

        $sortOrder = 1;
        foreach ($existing as $r) {
            $sortOrder = max($sortOrder, ((int)($r['sort_order'] ?? 0)) + 1);
        }

        foreach ($resolvedTests as $rt) {
            $label = $rt['label'];
            $obj = $rt['obj'];
            $bookedAs = (string)($rt['booked_as'] ?? $label);
            $key = strtolower($label);
            $alsoKeys = [
                strtolower($bookedAs),
                strtolower((string)($obj['code'] ?? '')),
            ];
            if ($obj) {
                $alsoKeys[] = strtolower((string)$obj['name']);
                if (preg_match('/^([A-Za-z0-9]+)\s*\(/', (string)$obj['name'], $m)) {
                    $alsoKeys[] = strtolower($m[1]);
                    $alsoKeys[] = rtrim(strtolower($m[1]), 's');
                }
            }
            $alsoKeys[] = rtrim(strtolower($bookedAs), 's');

            $existingForTest = $byTest[$key] ?? [];
            foreach (array_unique(array_filter($alsoKeys)) as $ak) {
                if (!empty($byTest[$ak])) {
                    $existingForTest = array_merge($existingForTest, $byTest[$ak]);
                }
            }

            $seenIds = [];
            $deduped = [];
            foreach ($existingForTest as $row) {
                $id = $row['id'] ?? '';
                if ($id === '' || isset($seenIds[$id])) {
                    continue;
                }
                $seenIds[$id] = true;
                $deduped[] = $row;
            }
            $existingForTest = $deduped;

            $params = $obj ? $testRepo->getParameters((string)$obj['id']) : [];

            $stubs = [];
            $detailed = [];
            foreach ($existingForTest as $row) {
                $p = trim((string)($row['parameter'] ?? ''));
                $t = trim((string)($row['test'] ?? ''));
                if ($p === '' || strcasecmp($p, $t) === 0 || strcasecmp($p, $label) === 0 || strcasecmp($p, $bookedAs) === 0) {
                    $stubs[] = $row;
                } else {
                    $detailed[] = $row;
                }
            }

            $keepPage = 1;
            foreach (array_merge($detailed, $stubs) as $old) {
                $keepPage = max($keepPage, (int)($old['print_page'] ?? 1));
            }
            if (isset($savedPages[$label])) {
                $keepPage = max(1, (int)$savedPages[$label]);
            }

            if (!empty($params)) {
                // Multi-parameter test in catalog
                foreach ($stubs as $old) {
                    $this->db->execute('DELETE FROM results WHERE id = :id', ['id' => $old['id']]);
                }

                $catalogParamMap = [];
                foreach ($params as $p) {
                    $catalogParamMap[strtolower(trim((string)$p['name']))] = $p;
                }

                $existingParamMap = [];
                foreach ($detailed as $old) {
                    $pName = strtolower(trim((string)($old['parameter'] ?? '')));
                    if ($pName !== '') {
                        $existingParamMap[$pName][] = $old;
                    }
                }

                // Delete any rows whose parameter was removed from catalog
                foreach ($detailed as $old) {
                    $pName = strtolower(trim((string)($old['parameter'] ?? '')));
                    if (!isset($catalogParamMap[$pName])) {
                        $this->db->execute('DELETE FROM results WHERE id = :id', ['id' => $old['id']]);
                    }
                }

                // Insert missing parameters or update existing ones
                foreach ($params as $p) {
                    $pName = strtolower(trim((string)$p['name']));
                    if (!isset($existingParamMap[$pName]) || empty($existingParamMap[$pName])) {
                        $resId = 'RES-' . bin2hex(random_bytes(5));
                        $this->db->execute(
                            "INSERT INTO results (id, lab_no, patient, test, section, parameter, value, unit, reference_range, sub_table, flag, sort_order, is_visible, print_page)
                             VALUES (:id, :lab_no, :patient, :test, :section, :param, '', :unit, :range, :sub_table, '', :sort, 1, :print_page)",
                            [
                                'id' => $resId,
                                'lab_no' => $labNo,
                                'patient' => $patientName,
                                'test' => $label,
                                'section' => !empty($p['section']) ? trim((string)$p['section']) : null,
                                'param' => $p['name'],
                                'unit' => $p['unit'] ?? '',
                                'range' => $p['reference_range'] ?: ($p['normal_value'] ?? ''),
                                'sub_table' => !empty($p['sub_table']) ? trim((string)$p['sub_table']) : null,
                                'sort' => $sortOrder++,
                                'print_page' => $keepPage,
                            ]
                        );
                    } else {
                        $existingRow = $existingParamMap[$pName][0];
                        $this->db->execute(
                            "UPDATE results SET 
                                section = :sec,
                                unit = CASE WHEN unit IS NULL OR unit = '' OR unit = '—' THEN :unit ELSE unit END,
                                reference_range = CASE WHEN reference_range IS NULL OR reference_range = '' OR reference_range = '—' THEN :range ELSE reference_range END,
                                sub_table = COALESCE(sub_table, :sub_table)
                             WHERE id = :id",
                            [
                                'sec' => !empty($p['section']) ? trim((string)$p['section']) : null,
                                'unit' => $p['unit'] ?? '',
                                'range' => $p['reference_range'] ?: ($p['normal_value'] ?? ''),
                                'sub_table' => !empty($p['sub_table']) ? trim((string)$p['sub_table']) : null,
                                'id' => $existingRow['id'],
                            ]
                        );
                        for ($di = 1; $di < count($existingParamMap[$pName]); $di++) {
                            $this->db->execute('DELETE FROM results WHERE id = :id', ['id' => $existingParamMap[$pName][$di]['id']]);
                        }
                    }
                }
                continue;
            }

            // Test has 0 parameters in catalog (single test)
            if ($obj) {
                $unitVal = $obj['unit'] ?? '—';
                $rangeVal = $obj['reference_value'] ?: ($obj['normal_value'] ?: ($obj['normal_range'] ?? '—'));

                // If detailed parameter rows exist from before when this test had parameters, remove them
                if ($detailed !== []) {
                    $keepVal = '';
                    $keepFlag = '';
                    foreach ($detailed as $dRow) {
                        $v = trim((string)($dRow['value'] ?? ''));
                        if ($v !== '' && strcasecmp($v, 'Pending') !== 0 && $v !== '-' && $v !== '—') {
                            $keepVal = $v;
                            $keepFlag = (string)($dRow['flag'] ?? '');
                            break;
                        }
                    }
                    foreach ($detailed as $dRow) {
                        $this->db->execute('DELETE FROM results WHERE id = :id', ['id' => $dRow['id']]);
                    }
                    if ($stubs === []) {
                        $resId = 'RES-' . bin2hex(random_bytes(5));
                        $this->db->execute(
                            "INSERT INTO results (id, lab_no, patient, test, section, parameter, value, unit, reference_range, sub_table, flag, sort_order, is_visible, print_page)
                             VALUES (:id, :lab_no, :patient, :test, NULL, :param, :val, :unit, :range, NULL, :flag, :sort, 1, :print_page)",
                            [
                                'id' => $resId,
                                'lab_no' => $labNo,
                                'patient' => $patientName,
                                'test' => $label,
                                'param' => $label,
                                'val' => $keepVal,
                                'unit' => $unitVal,
                                'range' => $rangeVal,
                                'flag' => $keepFlag,
                                'sort' => $sortOrder++,
                                'print_page' => $keepPage,
                            ]
                        );
                    }
                }

                if ($stubs !== []) {
                    $firstStub = $stubs[0];
                    $this->db->execute(
                        "UPDATE results SET 
                            parameter = :param,
                            section = NULL,
                            unit = CASE WHEN unit IS NULL OR unit = '' OR unit = '—' THEN :unit ELSE unit END,
                            reference_range = CASE WHEN reference_range IS NULL OR reference_range = '' OR reference_range = '—' THEN :range ELSE reference_range END
                         WHERE id = :id",
                        [
                            'param' => $label,
                            'unit' => $unitVal,
                            'range' => $rangeVal,
                            'id' => $firstStub['id'],
                        ]
                    );
                    for ($si = 1; $si < count($stubs); $si++) {
                        $this->db->execute('DELETE FROM results WHERE id = :id', ['id' => $stubs[$si]['id']]);
                    }
                } elseif ($detailed === [] && $existingForTest === []) {
                    $resId = 'RES-' . bin2hex(random_bytes(5));
                    $this->db->execute(
                        "INSERT INTO results (id, lab_no, patient, test, section, parameter, value, unit, reference_range, sub_table, flag, sort_order, is_visible, print_page)
                         VALUES (:id, :lab_no, :patient, :test, NULL, :param, '', :unit, :range, NULL, '', :sort, 1, :print_page)",
                        [
                            'id' => $resId,
                            'lab_no' => $labNo,
                            'patient' => $patientName,
                            'test' => $label,
                            'param' => $label,
                            'unit' => $unitVal,
                            'range' => $rangeVal,
                            'sort' => $sortOrder++,
                            'print_page' => $keepPage,
                        ]
                    );
                }
                continue;
            }

            // Fallback for non-catalog tests
            if ($existingForTest === []) {
                $resId = 'RES-' . bin2hex(random_bytes(5));
                $this->db->execute(
                    "INSERT INTO results (id, lab_no, patient, test, section, parameter, value, unit, reference_range, sub_table, flag, sort_order, is_visible, print_page)
                     VALUES (:id, :lab_no, :patient, :test, NULL, :param, '', '—', '—', NULL, '', :sort, 1, :print_page)",
                    [
                        'id' => $resId,
                        'lab_no' => $labNo,
                        'patient' => $patientName,
                        'test' => $label,
                        'param' => $label,
                        'sort' => $sortOrder++,
                        'print_page' => $keepPage,
                    ]
                );
            }
        }

        // Clean empty stubs
        $this->db->execute(
            "DELETE FROM results WHERE lab_no = :lab_no AND (parameter IS NULL OR parameter = '') AND (value IS NULL OR value = '')",
            ['lab_no' => $labNo]
        );

        return $this->getResultsByLabNo($labNo);
    }

    public function saveResultsBatch(string $labNo, array $items): bool
    {
        foreach ($items as $item) {
            $id = $item['id'] ?? '';
            if ($id === '') continue;
            $visible = array_key_exists('is_visible', $item)
                ? ((int)$item['is_visible'] ? 1 : 0)
                : null;
            $hasSub = array_key_exists('sub_table', $item);
            $subVal = $hasSub ? ($item['sub_table'] !== null && trim((string)$item['sub_table']) !== '' ? (string)$item['sub_table'] : null) : null;

            $this->db->execute(
                "UPDATE results SET 
                    value = :value, 
                    unit = COALESCE(:unit, unit), 
                    reference_range = COALESCE(:range, reference_range), 
                    flag = :flag,
                    is_visible = COALESCE(:is_visible, is_visible),
                    sub_table = CASE WHEN :has_sub = 1 THEN :sub_table ELSE sub_table END
                 WHERE id = :id AND lab_no = :lab_no",
                [
                    'id' => $id,
                    'lab_no' => $labNo,
                    'value' => $item['value'] ?? '',
                    'unit' => $item['unit'] ?? null,
                    'range' => $item['reference_range'] ?? null,
                    'flag' => $item['flag'] ?? '',
                    'is_visible' => $visible,
                    'has_sub' => $hasSub ? 1 : 0,
                    'sub_table' => $subVal,
                ]
            );
        }
        return true;
    }

    public function verifyAllByLabNo(string $labNo, string $verifiedBy): bool
    {
        $ok = $this->db->execute(
            "UPDATE results SET verified_at = NOW(), verified_by = :by WHERE lab_no = :lab_no",
            ['lab_no' => $labNo, 'by' => $verifiedBy]
        );
        if ($ok) {
            $this->db->execute(
                "UPDATE lab_entries SET status = 'verified' WHERE lab_no = :lab_no",
                ['lab_no' => $labNo]
            );
        }
        return $ok;
    }

    public function saveResultEntry(string $id, array $data): bool
    {
        return $this->db->execute(
            "UPDATE results SET parameter = :parameter, value = :value, unit = :unit, flag = :flag WHERE id = :id",
            [
                'id' => $id,
                'parameter' => $data['parameter'] ?? null,
                'value' => $data['value'] ?? null,
                'unit' => $data['unit'] ?? null,
                'flag' => $data['flag'] ?? null,
            ]
        );
    }

    public function findResult(string $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM results WHERE id = :id OR lab_no = :lab LIMIT 1",
            ['id' => $id, 'lab' => $id]
        );
    }

    public function getUnverifiedResults(): array
    {
        // One row per lab visit that has entered values still waiting for sign-off
        return $this->db->fetchAll(
            "SELECT
                r.lab_no,
                MAX(r.patient) AS patient,
                GROUP_CONCAT(DISTINCT r.test ORDER BY r.test SEPARATOR ', ') AS test,
                COUNT(*) AS param_count,
                SUM(CASE WHEN r.value IS NOT NULL AND r.value != '' THEN 1 ELSE 0 END) AS filled_count,
                MIN(r.id) AS id
             FROM results r
             WHERE r.verified_at IS NULL
               AND EXISTS (
                    SELECT 1 FROM results r2
                    WHERE r2.lab_no = r.lab_no
                      AND r2.verified_at IS NULL
                      AND r2.value IS NOT NULL AND r2.value != ''
               )
             GROUP BY r.lab_no
             ORDER BY r.lab_no DESC"
        );
    }

    public function verifyResult(string $id, string $verifiedBy): bool
    {
        $ok = $this->db->execute(
            "UPDATE results SET verified_at = NOW(), verified_by = :by WHERE id = :id",
            ['id' => $id, 'by' => $verifiedBy]
        );
        if ($ok) {
            $row = $this->findResult($id);
            if ($row) {
                $this->db->execute(
                    "UPDATE lab_entries SET status = 'verified' WHERE lab_no = :lab_no",
                    ['lab_no' => $row['lab_no']]
                );
            }
        }
        return $ok;
    }

    public function updateSampleStatus(string $sampleId, string $status): bool
    {
        $recv = in_array($status, ['received', 'processing', 'completed'], true) ? date('Y-m-d H:i') : null;
        $ok = $this->db->execute(
            "UPDATE samples SET status = :status, received_at = COALESCE(:recv, received_at) WHERE id = :id",
            ['id' => $sampleId, 'status' => $status, 'recv' => $recv]
        );
        if ($ok) {
            $sample = $this->db->fetchOne("SELECT lab_no FROM samples WHERE id = :id", ['id' => $sampleId]);
            if ($sample) {
                $map = [
                    'received' => 'received',
                    'processing' => 'processing',
                    'completed' => 'completed',
                    'collected' => 'collected',
                ];
                if (isset($map[$status])) {
                    $this->db->execute(
                        "UPDATE lab_entries SET sample_status = :ss, status = CASE WHEN status = 'pending' THEN 'collected' ELSE status END WHERE lab_no = :lab_no",
                        ['ss' => $map[$status], 'lab_no' => $sample['lab_no']]
                    );
                }
            }
        }
        return $ok;
    }

    public function getImagingScans(): array
    {
        return $this->db->fetchAll("SELECT * FROM imaging_scans ORDER BY created_at DESC");
    }

    public function findImagingScan(string $scanNo): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM imaging_scans WHERE scan_no = :scan_no OR id = :id LIMIT 1",
            ['scan_no' => $scanNo, 'id' => $scanNo]
        );
    }

    public function createImagingScan(array $data): array
    {
        $id = 'IMG-' . bin2hex(random_bytes(5));
        $scanNo = $data['scan_no'] ?? ($this->modalityPrefix($data['modality'] ?? 'xray') . '-' . date('Y') . '-' . sprintf('%03d', rand(100, 999)));

        $ok = $this->db->execute(
            "INSERT INTO imaging_scans (id, scan_no, patient, patient_id, modality, study, status, scan_date, radiologist, clinical_notes)
             VALUES (:id, :scan_no, :patient, :patient_id, :modality, :study, :status, :scan_date, :radiologist, :clinical_notes)",
            [
                'id' => $id,
                'scan_no' => $scanNo,
                'patient' => $data['patient'] ?? '',
                'patient_id' => $data['patient_id'] ?? null,
                'modality' => $data['modality'] ?? 'X-Ray',
                'study' => $data['study'] ?? '',
                'status' => $data['status'] ?? 'pending',
                'scan_date' => $data['scan_date'] ?? date('Y-m-d'),
                'radiologist' => $data['radiologist'] ?? null,
                'clinical_notes' => $data['clinical_notes'] ?? null,
            ]
        );

        return ['success' => $ok, 'scan_no' => $scanNo, 'id' => $id];
    }

    public function saveImagingFindings(string $scanNo, array $data): bool
    {
        return $this->db->execute(
            "UPDATE imaging_scans SET radiologist = :rad, findings = :findings, impression = :impression, status = :status WHERE scan_no = :scan_no",
            [
                'scan_no' => $scanNo,
                'rad' => $data['radiologist'] ?? null,
                'findings' => $data['findings'] ?? null,
                'impression' => $data['impression'] ?? null,
                'status' => $data['status'] ?? 'reported',
            ]
        );
    }

    public function getCollectionCenters(): array
    {
        return $this->db->fetchAll("SELECT * FROM collection_centers ORDER BY name");
    }

    public function countPendingSamples(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM samples WHERE status NOT IN ('completed')"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countSamplesToday(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM samples WHERE DATE(created_at) = CURDATE()"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countPendingResults(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM results WHERE verified_at IS NULL AND (value IS NULL OR value = '')"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countPendingVerification(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM results WHERE verified_at IS NULL AND value IS NOT NULL AND value != ''"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countVerifiedToday(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM results WHERE DATE(verified_at) = CURDATE()"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countCritical(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM results WHERE flag IN ('critical','H','L') AND verified_at IS NULL"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countImagingPending(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM imaging_scans WHERE status IN ('pending','in_progress')"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countImagingReported(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM imaging_scans WHERE status IN ('reported','completed')"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countImagingToday(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM imaging_scans WHERE scan_date = CURDATE()"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countTotalBiomarkers(): int
    {
        $row = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM results");
        return (int)($row['cnt'] ?? 0);
    }

    public function countOutOfRange(): int
    {
        $row = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM results WHERE flag IN ('critical','H','L')");
        return (int)($row['cnt'] ?? 0);
    }

    public function countInReview(): int
    {
        $row = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM results WHERE verified_at IS NULL");
        return (int)($row['cnt'] ?? 0);
    }

    public function countInRange(): int
    {
        $row = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM results WHERE (verified_at IS NOT NULL OR (value IS NOT NULL AND value != '')) AND (flag IS NULL OR flag = '' OR flag = 'normal')");
        return (int)($row['cnt'] ?? 0);
    }

    public function getRealAverageTATMinutes(): int
    {
        $row = $this->db->fetchOne(
            "SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, le.created_at, r.verified_at))) AS avg_tat
             FROM results r
             JOIN lab_entries le ON le.lab_no = r.lab_no
             WHERE r.verified_at IS NOT NULL"
        );
        $tat = (int)($row['avg_tat'] ?? 0);
        if ($tat > 0) {
            return $tat;
        }

        // If no verified results yet, measure average processing duration of current active samples
        $row2 = $this->db->fetchOne(
            "SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, NOW()))) AS avg_elapsed
             FROM lab_entries WHERE status IN ('pending','collected','processing','received')"
        );
        return max(15, (int)($row2['avg_elapsed'] ?? 28));
    }

    public function getRealVerificationRate(): float
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN verified_at IS NOT NULL THEN 1 ELSE 0 END) AS verified
             FROM results"
        );
        $total = (int)($row['total'] ?? 0);
        if ($total === 0) {
            return 100.0;
        }
        $verified = (int)($row['verified'] ?? 0);
        return round(($verified / $total) * 100, 1);
    }

    public function getRealDepartmentCounts(int $limit = 4): array
    {
        $lim = (int)$limit;
        return $this->db->fetchAll(
            "SELECT COALESCE(NULLIF(t.category, ''), 'General Pathology') AS dept,
                    COUNT(DISTINCT r.id) AS biomarker_count,
                    COUNT(DISTINCT r.lab_no) AS order_count,
                    SUM(CASE WHEN r.flag IN ('critical','H','L') THEN 1 ELSE 0 END) AS out_of_range
             FROM results r
             LEFT JOIN tests t ON (t.name = r.test OR t.code = r.test)
             GROUP BY COALESCE(NULLIF(t.category, ''), 'General Pathology')
             ORDER BY biomarker_count DESC
             LIMIT {$lim}"
        );
    }

    public function getRealOrganMetrics(array $keywords): array
    {
        if (empty($keywords)) {
            return ['total' => 0, 'out_of_range' => 0, 'in_review' => 0, 'in_range' => 0];
        }

        $clauses = [];
        $params = [];
        foreach ($keywords as $i => $kw) {
            $clauses[] = "(test LIKE :kt{$i} OR parameter LIKE :kp{$i} OR section LIKE :ks{$i})";
            $params["kt{$i}"] = '%' . $kw . '%';
            $params["kp{$i}"] = '%' . $kw . '%';
            $params["ks{$i}"] = '%' . $kw . '%';
        }
        $where = implode(' OR ', $clauses);

        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN flag IN ('critical','H','L') THEN 1 ELSE 0 END) AS out_of_range,
                       SUM(CASE WHEN verified_at IS NULL AND (value IS NULL OR value = '') THEN 1 ELSE 0 END) AS in_review,
                       SUM(CASE WHEN (verified_at IS NOT NULL OR (value IS NOT NULL AND value != '')) AND (flag IS NULL OR flag = '' OR flag = 'normal') THEN 1 ELSE 0 END) AS in_range
                FROM results
                WHERE {$where}";

        $row = $this->db->fetchOne($sql, $params);
        return [
            'total' => (int)($row['total'] ?? 0),
            'out_of_range' => (int)($row['out_of_range'] ?? 0),
            'in_review' => (int)($row['in_review'] ?? 0),
            'in_range' => (int)($row['in_range'] ?? 0),
        ];
    }

    private function modalityPrefix(string $modality): string
    {
        return match (strtolower($modality)) {
            'ct', 'ct scan' => 'CT',
            'us', 'ultrasound' => 'US',
            'ecg' => 'ECG',
            default => 'XR',
        };
    }
}
