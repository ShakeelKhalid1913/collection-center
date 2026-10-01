<?php

declare(strict_types=1);

/**
 * Import a full patient report (patient + visit + result values) into the DB.
 *
 * Web Usage:
 *   URL/database/import_patient_report.php
 *   URL/database/import_patient_report.php?report=master_cc5424087.php
 *   URL/database/import_patient_report.php?all=1
 *
 * CLI Usage:
 *   php app/database/import_patient_report.php app/database/reports/master_cc5424087.php
 *   php app/database/import_patient_report.php --list
 */

require_once __DIR__ . '/../includes/bootstrap.php';

use App\Database\Database;

function import_report_usage(): void
{
    echo "Usage:\n";
    echo "  php app/database/import_patient_report.php --all\n";
    echo "  php app/database/import_patient_report.php <report-php-file>\n";
    echo "  php app/database/import_patient_report.php --list\n";
}

/**
 * @param array<string,mixed> $report
 * @param string $orgId
 * @param callable|null $logger
 * @return array{ok:bool, lab_no?:string, patient_no?:string, patient_name?:string, filled_count?:int, message:string, logs:array<string>}
 */
function import_patient_report(array $report, string $orgId = 'ORG-001', ?callable $logger = null): array
{
    $logs = [];
    $log = static function (string $msg) use (&$logs, $logger): void {
        $logs[] = $msg;
        if ($logger !== null) {
            $logger($msg);
        } elseif (PHP_SAPI === 'cli') {
            echo $msg . "\n";
        }
    };

    try {
        $db = Database::getInstance();
        if (!$db->isConnected() || $db->getPdo() === null) {
            return [
                'ok' => false,
                'message' => 'Could not connect to MariaDB database. Ensure database server is running and configured, or run /database/setup.php first.',
                'logs' => $logs,
            ];
        }

        $patientData = $report['patient'] ?? [];
        $visitData = $report['visit'] ?? [];
        $panels = $report['panels'] ?? [];

        if ($patientData === [] || $visitData === [] || $panels === []) {
            return ['ok' => false, 'message' => 'Report must include patient, visit, and panels.', 'logs' => $logs];
        }

        $labNo = trim((string)($visitData['lab_no'] ?? ''));
        if ($labNo === '') {
            return ['ok' => false, 'message' => 'visit.lab_no is required.', 'logs' => $logs];
        }

        $log("Starting import for Lab No: {$labNo}...");

        // 1) Ensure catalog parameters exist for each panel
        foreach ($panels as $panel) {
            $testName = (string)($panel['test'] ?? '');
            $params = $panel['parameters'] ?? [];
            $methodology = isset($panel['methodology']) ? (string)$panel['methodology'] : null;
            if ($testName === '' && empty($params)) {
                continue;
            }
            ensure_report_panel_parameters($testName, $params, $orgId, $log, $methodology);
        }

        // 2) Parse patient name and title from report
        $fullName = trim((string)($patientData['full_name'] ?? $patientData['name'] ?? 'Unknown'));
        $title = trim((string)($patientData['title'] ?? ''));
        if ($title === '' && preg_match('/^(MASTER|MR|MRS|MS|MISS|DR)\.?\s+(.+)$/i', $fullName, $m)) {
            $title = ucfirst(strtolower($m[1]));
            $fullName = trim($m[2]);
        }
        if ($title === '' && strcasecmp($fullName, 'MASTER') === 0) {
            $title = 'Master';
            $fullName = 'MASTER';
        }
        $intendedDisplayName = trim(($title !== '' ? $title . ' ' : '') . $fullName);

        // Find existing patient: first by exact MR/patient_no
        $mrNo = trim((string)($patientData['patient_no'] ?? $patientData['mr_no'] ?? ''));
        $existing = null;
        if ($mrNo !== '') {
            $existing = patient_repo()->findById($mrNo);
        }

        // Only fallback search by phone if MR not found AND the patient name also matches
        if (!$existing && !empty($patientData['phone'])) {
            $hits = patient_repo()->search((string)$patientData['phone'], $orgId);
            foreach ($hits as $hit) {
                $hitName = trim((string)($hit['full_name'] ?? ''));
                if (strcasecmp($hitName, $fullName) === 0) {
                    $existing = $hit;
                    break;
                }
            }
        }

        if ($existing) {
            $patientId = (string)$existing['id'];
            $patientNo = (string)($existing['patient_no'] ?? $mrNo);
            $patientName = $intendedDisplayName !== '' ? $intendedDisplayName : trim((string)(($existing['title'] ?? '') . ' ' . ($existing['full_name'] ?? '')));
            // Ensure patient record has latest details
            $pdo = \App\Database\Database::getInstance()->getPdo();
            if ($pdo) {
                $pdo->prepare('UPDATE patients SET title = ?, full_name = ?, relation_of = ?, phone = ?, age = ?, gender = ? WHERE id = ?')
                    ->execute([
                        $title,
                        $fullName,
                        (string)($patientData['relation_of'] ?? $patientData['fh_name'] ?? ''),
                        (string)($patientData['phone'] ?? $patientData['contact'] ?? ''),
                        (int)($patientData['age'] ?? 0),
                        (string)($patientData['gender'] ?? 'Male'),
                        $patientId,
                    ]);
            }
            $log("Using patient: {$patientName} ({$patientNo})");
        } else {
            $res = patient_repo()->create([
                'organization_id' => $orgId,
                'patient_no' => $mrNo,
                'title' => $title,
                'full_name' => $fullName,
                'relation_of' => (string)($patientData['relation_of'] ?? $patientData['fh_name'] ?? ''),
                'phone' => (string)($patientData['phone'] ?? $patientData['contact'] ?? ''),
                'cnic' => (string)($patientData['cnic'] ?? ''),
                'age' => (int)($patientData['age'] ?? 0),
                'gender' => (string)($patientData['gender'] ?? 'Male'),
                'blood_group' => (string)($patientData['blood_group'] ?? ''),
                'address' => (string)($patientData['address'] ?? ''),
                'doctor' => (string)($visitData['doctor'] ?? $patientData['referred_by'] ?? ''),
                'referring_doctor' => (string)($visitData['doctor'] ?? $patientData['referred_by'] ?? ''),
                'branch' => (string)($visitData['branch'] ?? 'CC-01'),
            ]);
            if (empty($res['success'])) {
                return ['ok' => false, 'message' => 'Could not create patient: ' . ($res['error'] ?? 'Unknown error'), 'logs' => $logs];
            }
            $patientId = (string)($res['id'] ?? '');
            $created = patient_repo()->findById($patientId) ?: patient_repo()->findById($mrNo);
            $patientNo = (string)($created['patient_no'] ?? $mrNo);
            $patientName = $intendedDisplayName;
            $log("Created new patient: {$patientName} ({$patientNo})");
        }

        // 3) Lab entry — replace if same lab_no already exists
        $existingEntry = lab_repo()->findByLabNo($labNo);
        $testNames = [];
        foreach ($panels as $panel) {
            $tn = trim((string)($panel['test'] ?? ''));
            if ($tn !== '') {
                $testNames[] = $tn;
            }
        }
        $testsCsv = implode(', ', $testNames);
        $pdo = \App\Database\Database::getInstance()->getPdo();

        if ($existingEntry) {
            // Wipe old results for clean re-import
            if ($pdo) {
                $pdo->prepare('DELETE FROM results WHERE lab_no = ?')->execute([$labNo]);
                $pdo->prepare('UPDATE lab_entries SET patient_id = ?, patient_name = ?, tests = ?, doctor = ?, status = ?, sample_status = ?, clinical_notes = ? WHERE lab_no = ?')
                    ->execute([
                        $patientId !== '' ? $patientId : $patientNo,
                        $patientName,
                        $testsCsv,
                        (string)($visitData['doctor'] ?? $patientData['referred_by'] ?? 'Walk-in / Self'),
                        'verified',
                        'completed',
                        (string)($visitData['clinical_notes'] ?? ''),
                        $labNo,
                    ]);
            }
            $log("Updated existing lab entry: {$labNo} for {$patientName} ({$testsCsv})");
        } else {
            $created = lab_repo()->create([
                'organization_id' => $orgId,
                'lab_no' => $labNo,
                'patient_id' => $patientId !== '' ? $patientId : $patientNo,
                'patient_name' => $patientName,
                'tests' => $testsCsv,
                'doctor' => (string)($visitData['doctor'] ?? $patientData['referred_by'] ?? 'Walk-in / Self'),
                'route' => (string)($visitData['route'] ?? 'Laboratory — Biochemistry'),
                'priority' => (string)($visitData['priority'] ?? 'Normal'),
                'status' => 'verified',
                'sample_status' => 'completed',
                'amount' => (float)($visitData['amount'] ?? 0),
                'paid' => (float)($visitData['paid'] ?? 0),
                'branch' => (string)($visitData['branch'] ?? 'Collection Center'),
                'clinical_notes' => (string)($visitData['clinical_notes'] ?? ''),
            ]);
            if (empty($created['success'])) {
                return ['ok' => false, 'message' => 'Could not create lab entry ' . $labNo, 'logs' => $logs];
            }
            $labNo = (string)$created['lab_no'];
            $log("Created new lab entry: {$labNo} for {$patientName} ({$testsCsv})");
        }

        // Re-init parameter sheets from catalog
        $rows = result_repo()->ensureResultsInitialized(
            $labNo,
            $testsCsv,
            $patientName,
            $orgId
        );
        $log("Initialized " . count($rows) . " result parameters from catalog for {$patientName}.");

        // Optional page grouping
        if (!empty($visitData['page_map']) && is_array($visitData['page_map'])) {
            result_repo()->assignPrintPages($labNo, $visitData['page_map']);
            $rows = result_repo()->getResultsByLabNo($labNo);
            $log("Assigned print pages according to visit page_map.");
        }

        // 4) Fill values
        $byTest = [];
        foreach ($rows as $r) {
            $byTest[strtolower(trim((string)$r['test']))][] = $r;
        }

        $batch = [];
        $filled = 0;
        foreach ($panels as $panel) {
            $testName = trim((string)($panel['test'] ?? ''));
            $params = $panel['parameters'] ?? [];
            if ($testName === '') {
                continue;
            }

            $resultRows = find_result_rows_for_test($byTest, $testName);
            foreach ($params as $p) {
                $paramName = trim((string)($p['name'] ?? ''));
                $value = array_key_exists('value', $p) ? trim((string)$p['value']) : '';
                if ($paramName === '' || $value === '' || $value === '-') {
                    // Still update range/unit/flag empty if provided and row found
                    if ($paramName === '' || ($value === '' || $value === '-')) {
                        if ($value === '-' || $value === '') {
                            $value = '';
                        }
                    }
                }

                $row = match_result_row($resultRows, $paramName);
                if (!$row) {
                    $log("  ! no match: {$testName} → {$paramName}");
                    continue;
                }

                $range = trim((string)($p['range'] ?? $row['reference_range'] ?? ''));
                $unit = trim((string)($p['unit'] ?? $row['unit'] ?? ''));
                $flag = '';
                if ($value !== '' && is_numeric($value)) {
                    $flag = compute_result_flag($value, $range);
                } elseif (!empty($p['flag'])) {
                    $flag = strtoupper(trim((string)$p['flag']));
                }

                $batch[] = [
                    'id' => $row['id'],
                    'value' => $value,
                    'unit' => $unit !== '' ? $unit : null,
                    'reference_range' => $range !== '' ? $range : null,
                    'flag' => $flag,
                    'is_visible' => isset($p['show']) ? ((int)$p['show'] ? 1 : 0) : 1,
                ];
                if ($value !== '') {
                    $filled++;
                }
            }
        }

        if ($batch !== []) {
            result_repo()->saveResultsBatch($labNo, $batch);
            $log("Saved {$filled} result value(s) across " . count($batch) . " parameter entries.");
        }

        // Stamp dates / reporter if columns exist
        maybe_stamp_visit_meta($labNo, $visitData);

        result_repo()->verifyAllByLabNo($labNo, (string)($visitData['reported_by'] ?? 'Imported Report'));
        $log("Verified report status for {$labNo}.");

        return [
            'ok' => true,
            'lab_no' => $labNo,
            'patient_no' => $patientNo,
            'patient_name' => $patientName,
            'filled_count' => $filled,
            'message' => "Imported {$labNo} for {$patientNo} ({$patientName}) — filled {$filled} result value(s).",
            'logs' => $logs,
        ];
    } catch (\Throwable $e) {
        $log("ERROR: " . $e->getMessage());
        return [
            'ok' => false,
            'message' => "Import failed: " . $e->getMessage(),
            'logs' => $logs,
        ];
    }
}

/**
 * @param array<string,array<int,array<string,mixed>>> $byTest
 * @return list<array<string,mixed>>
 */
function find_result_rows_for_test(array $byTest, string $testName): array
{
    $key = strtolower($testName);
    if (!empty($byTest[$key])) {
        return $byTest[$key];
    }
    foreach ($byTest as $k => $rows) {
        if ($k === $key || str_contains($k, $key) || str_contains($key, $k)) {
            return $rows;
        }
        // short code match e.g. CRP
        $short = preg_replace('/\s*\(.*$/', '', $key) ?? $key;
        if (str_starts_with($k, $short) || str_starts_with($short, explode(' ', $k)[0])) {
            return $rows;
        }
    }
    return [];
}

/**
 * @param list<array<string,mixed>> $rows
 */
function match_result_row(array $rows, string $paramName): ?array
{
    $want = strtolower(trim($paramName));
    $want = preg_replace('/\s+/', ' ', $want) ?? $want;

    foreach ($rows as $r) {
        $have = strtolower(trim((string)($r['parameter'] ?? '')));
        $have = preg_replace('/\s+/', ' ', $have) ?? $have;
        if ($have === $want) {
            return $r;
        }
    }
    foreach ($rows as $r) {
        $have = strtolower(trim((string)($r['parameter'] ?? '')));
        if ($have !== '' && (str_contains($have, $want) || str_contains($want, $have))) {
            return $r;
        }
    }
    // strip punctuation
    $norm = static function (string $s): string {
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '', $s) ?? $s;
        return $s;
    };
    $wantN = $norm($want);
    foreach ($rows as $r) {
        $haveN = $norm((string)($r['parameter'] ?? ''));
        if ($haveN !== '' && ($haveN === $wantN || str_contains($haveN, $wantN) || str_contains($wantN, $haveN))) {
            return $r;
        }
    }
    return null;
}

/**
 * Create / replace test_parameters so result sheets match the report.
 *
 * @param list<array{section?:string,name:string,unit?:string,range?:string,sub_table?:?string}> $params
 */
function ensure_report_panel_parameters(string $testName, array $params, string $orgId, ?callable $logger = null, ?string $methodology = null): void
{
    $test = test_repo()->findByCode($testName, $orgId);
    if (!$test) {
        // Try looser match
        foreach (test_repo()->getTests($orgId) as $t) {
            if (strcasecmp((string)$t['name'], $testName) === 0) {
                $test = $t;
                break;
            }
        }
    }
    if (!$test) {
        $cleanCode = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', $testName) ?? 'TEST');
        $cleanCode = trim(substr($cleanCode, 0, 24), '-');
        $createRes = test_repo()->createTest([
            'organization_id' => $orgId,
            'code' => $cleanCode !== '' ? $cleanCode : 'TST-' . bin2hex(random_bytes(3)),
            'name' => $testName,
            'category' => 'General',
            'sample_type' => 'Serum',
            'methodology' => $methodology ?? '',
        ]);
        if (!empty($createRes['success'])) {
            $test = test_repo()->findById((string)$createRes['id']);
            $msg = "  ✓ catalog test auto-created: {$testName}";
            if ($logger) {
                $logger($msg);
            } elseif (PHP_SAPI === 'cli') {
                echo $msg . "\n";
            }
        }
    }
    if (!$test) {
        $msg = "  ! catalog test not found: {$testName} (skipping param seed)";
        if ($logger) {
            $logger($msg);
        } elseif (PHP_SAPI === 'cli') {
            echo $msg . "\n";
        }
        return;
    }

    if ($methodology !== null && trim($methodology) !== '') {
        test_repo()->updateMethodology((string)$test['id'], trim($methodology));
        $mMsg = "  ✓ methodology saved to test {$test['name']}";
        if ($logger) {
            $logger($mMsg);
        } elseif (PHP_SAPI === 'cli') {
            echo $mMsg . "\n";
        }
    }

    $toSave = [];
    foreach ($params as $i => $p) {
        $name = trim((string)($p['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $toSave[] = [
            'section' => trim((string)($p['section'] ?? '')),
            'name' => $name,
            'unit' => trim((string)($p['unit'] ?? '')),
            'normal_value' => trim((string)($p['range'] ?? '')),
            'reference_range' => trim((string)($p['range'] ?? '')),
            'sub_table' => $p['sub_table'] ?? null,
            'sort_order' => $i + 1,
        ];
    }
    if ($toSave === []) {
        return;
    }
    test_repo()->saveParameters((string)$test['id'], $toSave);
    $msg = "  ✓ parameters seeded for {$test['name']}";
    if ($logger) {
        $logger($msg);
    } elseif (PHP_SAPI === 'cli') {
        echo $msg . "\n";
    }
}

function maybe_stamp_visit_meta(string $labNo, array $visitData): void
{
    $pdo = \App\Database\Database::getInstance()->getPdo();
    if (!$pdo) {
        return;
    }
    // Best-effort: update created_at if provided
    $registered = trim((string)($visitData['registered_on'] ?? ''));
    if ($registered !== '') {
        $ts = strtotime(str_replace('/', '-', $registered));
        if ($ts) {
            try {
                $pdo->prepare('UPDATE lab_entries SET created_at = ? WHERE lab_no = ?')
                    ->execute([date('Y-m-d H:i:s', $ts), $labNo]);
            } catch (\Throwable $ignored) {
            }
        }
    }
}

// ==========================================
// EXECUTION: CLI or BROWSER
// ==========================================

$reportsDir = __DIR__ . '/reports';
$availableReports = [];
if (is_dir($reportsDir)) {
    foreach (glob($reportsDir . '/*.php') ?: [] as $f) {
        $availableReports[] = basename($f);
    }
}
sort($availableReports);

$isCli = (PHP_SAPI === 'cli');

if ($isCli) {
    $arg = $argv[1] ?? '';
    if ($arg === '-h' || $arg === '--help') {
        import_report_usage();
        exit(0);
    }

    if ($arg === '--list') {
        if ($availableReports === []) {
            echo "No reports found in {$reportsDir}\n";
            exit(0);
        }
        foreach ($availableReports as $f) {
            echo $f . "\n";
        }
        exit(0);
    }

    if ($arg === '--all' || $arg === '-a') {
        if ($availableReports === []) {
            echo "No reports found in {$reportsDir}\n";
            exit(0);
        }
        echo "Importing all " . count($availableReports) . " report(s)…\n";
        $allOk = true;
        foreach ($availableReports as $f) {
            $p = $reportsDir . '/' . $f;
            /** @var mixed $rep */
            $rep = require $p;
            if (!is_array($rep)) {
                echo "✗ FAIL: {$f} did not return an array.\n";
                $allOk = false;
                continue;
            }
            echo "\n=== Importing {$f} ===\n";
            $res = import_patient_report($rep);
            echo ($res['ok'] ? '✓ OK: ' : '✗ FAIL: ') . $res['message'] . "\n";
            if (!empty($res['lab_no'])) {
                echo "  Preview: /portals/main-lab/reports/preview.php?lab_no=" . rawurlencode((string)$res['lab_no']) . "\n";
            }
            if (!$res['ok']) {
                $allOk = false;
            }
        }
        exit($allOk ? 0 : 1);
    }

    // If no argument is provided in CLI, default to master_cc5424087.php if it exists
    if ($arg === '') {
        if (in_array('master_cc5424087.php', $availableReports, true)) {
            $arg = 'master_cc5424087.php';
            echo "No report specified. Defaulting to: master_cc5424087.php\n";
        } else {
            import_report_usage();
            exit(1);
        }
    }

    $path = $arg;
    if (!str_contains($path, '/') && !str_contains($path, '\\')) {
        $path = $reportsDir . '/' . $path;
    }
    if (!is_file($path)) {
        fwrite(STDERR, "Report file not found: {$path}\n");
        exit(1);
    }

    /** @var mixed $report */
    $report = require $path;
    if (!is_array($report)) {
        fwrite(STDERR, "Report file must return an array.\n");
        exit(1);
    }

    echo "Importing " . basename($path) . "…\n";
    $result = import_patient_report($report);
    echo ($result['ok'] ? 'OK: ' : 'FAIL: ') . $result['message'] . "\n";
    if (!empty($result['lab_no'])) {
        echo "Preview: /portals/main-lab/reports/preview.php?lab_no=" . rawurlencode((string)$result['lab_no']) . "\n";
        echo "Results: /portals/main-lab/results/entry.php?lab_no=" . rawurlencode((string)$result['lab_no']) . "\n";
    }
    exit($result['ok'] ? 0 : 1);
}

// ------------------------------------------
// BROWSER (HTTP) EXECUTION
// ------------------------------------------

$selectedReport = trim((string)($_GET['report'] ?? $_GET['file'] ?? ''));
$importAll = isset($_GET['all']) && ($_GET['all'] === '1' || $_GET['all'] === 'true');
$isJson = isset($_GET['format']) && strtolower((string)$_GET['format']) === 'json';

$filesToImport = [];

if ($importAll) {
    $filesToImport = $availableReports;
} elseif ($selectedReport !== '') {
    $cleanName = basename($selectedReport);
    if (!str_ends_with(strtolower($cleanName), '.php')) {
        $cleanName .= '.php';
    }
    $filesToImport = [$cleanName];
} else {
    // Default when opening URL/database/import_patient_report.php directly
    if (in_array('master_cc5424087.php', $availableReports, true)) {
        $filesToImport = ['master_cc5424087.php'];
    } elseif (!empty($availableReports)) {
        $filesToImport = [$availableReports[0]];
    }
}

$results = [];
foreach ($filesToImport as $reportFilename) {
    $fullPath = $reportsDir . '/' . $reportFilename;
    if (!is_file($fullPath)) {
        $results[] = [
            'file' => $reportFilename,
            'ok' => false,
            'message' => "Report file not found: {$reportFilename}",
            'logs' => ["File does not exist: {$fullPath}"],
        ];
        continue;
    }

    /** @var mixed $reportData */
    $reportData = require $fullPath;
    if (!is_array($reportData)) {
        $results[] = [
            'file' => $reportFilename,
            'ok' => false,
            'message' => "File {$reportFilename} did not return a valid PHP array.",
            'logs' => ["Invalid report structure in {$fullPath}"],
        ];
        continue;
    }

    $res = import_patient_report($reportData);
    $res['file'] = $reportFilename;
    $results[] = $res;
}

if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => !empty($results) && array_reduce($results, static fn($c, $r) => $c && !empty($r['ok']), true),
        'results' => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$allSuccess = !empty($results) && array_reduce($results, static fn($c, $r) => $c && !empty($r['ok']), true);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Import Patient Report — Health LMS Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-page: #0f172a;
            --card-bg: #1e293b;
            --card-border: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent-primary: #3b82f6;
            --accent-hover: #2563eb;
            --success-color: #10b981;
            --success-bg: rgba(16, 185, 129, 0.12);
            --danger-color: #ef4444;
            --danger-bg: rgba(239, 68, 68, 0.12);
            --info-bg: rgba(59, 130, 246, 0.1);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-main);
            min-height: 100vh;
            padding: 2.5rem 1rem;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        .container {
            width: 100%;
            max-width: 900px;
        }
        .header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: gap;
        }
        .header-title h1 {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #fff;
        }
        .header-title p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
        }
        .banner {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .banner--success {
            background: var(--success-bg);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success-color);
        }
        .banner--danger {
            background: var(--danger-bg);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--danger-color);
        }
        .banner-icon {
            font-size: 1.25rem;
            line-height: 1;
        }
        .banner-text h3 {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
        }
        .banner-text p {
            font-size: 0.88rem;
            color: var(--text-main);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 1rem;
        }
        .stat-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 0.35rem;
        }
        .stat-value {
            font-size: 1.15rem;
            font-weight: 700;
            color: #fff;
            word-break: break-all;
        }
        .actions-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.25rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 0.88rem;
            padding: 0.65rem 1.2rem;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
            border: none;
        }
        .btn--primary {
            background: var(--accent-primary);
            color: #fff;
        }
        .btn--primary:hover {
            background: var(--accent-hover);
        }
        .btn--outline {
            background: transparent;
            color: var(--text-main);
            border: 1px solid var(--card-border);
        }
        .btn--outline:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: #64748b;
        }
        .btn--sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .report-list {
            list-style: none;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            overflow: hidden;
            background: rgba(15, 23, 42, 0.4);
        }
        .report-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.85rem 1.15rem;
            border-bottom: 1px solid var(--card-border);
            font-size: 0.9rem;
        }
        .report-item:last-child {
            border-bottom: none;
        }
        .report-filename {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 500;
            color: #93c5fd;
        }
        .report-badge {
            font-size: 0.72rem;
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            font-weight: 600;
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
            margin-left: 0.5rem;
        }
        pre.log-console {
            background: #090d16;
            color: #cbd5e1;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.82rem;
            padding: 1.15rem;
            border-radius: 8px;
            border: 1px solid #1e293b;
            overflow-x: auto;
            line-height: 1.6;
            max-height: 340px;
            overflow-y: auto;
        }
        details summary {
            cursor: pointer;
            user-select: none;
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        details summary:hover {
            color: #fff;
        }
        .footer-nav {
            margin-top: 2rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .footer-nav a {
            color: #60a5fa;
            text-decoration: none;
            margin: 0 0.5rem;
        }
        .footer-nav a:hover {
            text-decoration: underline;
        }
        .tip-box {
            background: var(--info-bg);
            border: 1px solid rgba(59, 130, 246, 0.25);
            border-radius: 8px;
            padding: 0.9rem 1.15rem;
            margin-top: 1.25rem;
            font-size: 0.85rem;
            color: #bfdbfe;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-title">
                <h1>Patient Report Importer</h1>
                <p>Health LMS Pro · MariaDB Database Utility</p>
            </div>
            <div>
                <a href="?all=1" class="btn btn--outline btn--sm">Import All Reports</a>
            </div>
        </header>

        <?php foreach ($results as $item): ?>
            <div class="card">
                <?php if (!empty($item['ok'])): ?>
                    <div class="banner banner--success">
                        <span class="banner-icon">✓</span>
                        <div class="banner-text">
                            <h3>Import Succeeded: <?= htmlspecialchars($item['file'] ?? '') ?></h3>
                            <p><?= htmlspecialchars($item['message'] ?? 'Import finished successfully.') ?></p>
                        </div>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-label">Lab Number</div>
                            <div class="stat-value"><?= htmlspecialchars((string)($item['lab_no'] ?? '—')) ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">MR Number</div>
                            <div class="stat-value"><?= htmlspecialchars((string)($item['patient_no'] ?? '—')) ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Patient Name</div>
                            <div class="stat-value"><?= htmlspecialchars((string)($item['patient_name'] ?? '—')) ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Results Filled</div>
                            <div class="stat-value"><?= (int)($item['filled_count'] ?? 0) ?> value(s)</div>
                        </div>
                    </div>

                    <div class="actions-group">
                        <?php if (!empty($item['lab_no'])): ?>
                            <a href="/portals/main-lab/reports/preview.php?lab_no=<?= rawurlencode((string)$item['lab_no']) ?>" target="_blank" class="btn btn--primary">
                                📄 View Print Preview
                            </a>
                            <a href="/portals/main-lab/results/entry.php?lab_no=<?= rawurlencode((string)$item['lab_no']) ?>" target="_blank" class="btn btn--outline">
                                🧪 Enter / Edit Results
                            </a>
                        <?php endif; ?>
                        <a href="?report=<?= rawurlencode($item['file']) ?>" class="btn btn--outline">
                            🔄 Re-import This Report
                        </a>
                    </div>

                <?php else: ?>
                    <div class="banner banner--danger">
                        <span class="banner-icon">⚠</span>
                        <div class="banner-text">
                            <h3>Import Failed: <?= htmlspecialchars($item['file'] ?? '') ?></h3>
                            <p><?= htmlspecialchars($item['message'] ?? 'An error occurred during import.') ?></p>
                        </div>
                    </div>

                    <div class="actions-group">
                        <a href="/database/setup.php" class="btn btn--primary">
                            🛠 Run Database Setup (/database/setup.php)
                        </a>
                        <a href="?report=<?= rawurlencode($item['file']) ?>" class="btn btn--outline">
                            🔄 Retry Import
                        </a>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 1.5rem;">
                    <details <?= empty($item['ok']) ? 'open' : '' ?>>
                        <summary>View Execution Logs (<?= count($item['logs'] ?? []) ?> lines)</summary>
                        <pre class="log-console"><?= htmlspecialchars(implode("\n", $item['logs'] ?? [])) ?></pre>
                    </details>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="card">
            <div class="section-title">
                <span>Available Reports in <code>app/database/reports/</code></span>
                <span style="font-size: 0.8rem; font-weight: 500; color: var(--text-muted);"><?= count($availableReports) ?> file(s) found</span>
            </div>

            <?php if ($availableReports === []): ?>
                <p style="color: var(--text-muted); font-size: 0.9rem;">No report files found in <code>app/database/reports/</code>.</p>
            <?php else: ?>
                <ul class="report-list">
                    <?php foreach ($availableReports as $f): ?>
                        <li class="report-item">
                            <div>
                                <span class="report-filename"><?= htmlspecialchars($f) ?></span>
                                <?php if ($f === 'master_cc5424087.php'): ?>
                                    <span class="report-badge">Default</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="?report=<?= rawurlencode($f) ?>" class="btn btn--outline btn--sm">
                                    Run Import
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="tip-box">
                💡 <strong>Deploying to remote MariaDB?</strong>
                Before importing patient data on a fresh MariaDB deployment, make sure the schema and catalog are seeded by visiting
                <a href="/database/setup.php" style="color: #93c5fd; text-decoration: underline; font-weight: 600;">/database/setup.php</a> once.
            </div>
        </div>

        <nav class="footer-nav">
            <a href="/database/setup.php">Database Setup</a> &bull;
            <a href="/portals/main-lab/dashboard.php">Main Lab Portal</a> &bull;
            <a href="/portals/collection-center/dashboard.php">Collection Center</a> &bull;
            <a href="/login.php">Login</a>
        </nav>
    </div>
</body>
</html>
