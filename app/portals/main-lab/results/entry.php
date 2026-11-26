<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$message = '';
if (!empty($_GET['saved'])) {
    $message = flash_success('Results saved successfully. Ready for next entry.');
}
$orgId = current_user()['organization_id'] ?? 'ORG-001';

$labNo = trim((string)($_GET['lab_no'] ?? $_GET['id'] ?? $_POST['lab_no'] ?? ''));
$activeTest = trim((string)($_GET['test'] ?? $_POST['active_test'] ?? ''));

$entry = null;
if ($labNo !== '') {
    $entry = lab_repo()->findByLabNo($labNo);
    if (!$entry) {
        $singleRes = result_repo()->findResult($labNo);
        if ($singleRes && !empty($singleRes['lab_no'])) {
            $labNo = $singleRes['lab_no'];
            $entry = lab_repo()->findByLabNo($labNo);
        }
    }
}

if (!$entry) {
    $allEntries = lab_repo()->getAll();
    foreach ($allEntries as $e) {
        if (($e['status'] ?? '') !== 'verified' && ($e['status'] ?? '') !== 'completed') {
            $entry = $e;
            $labNo = $e['lab_no'];
            break;
        }
    }
    if (!$entry && !empty($allEntries[0])) {
        $entry = $allEntries[0];
        $labNo = $entry['lab_no'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'save_new_template') {
    $tplTitle = trim((string)($_POST['template_title'] ?? ''));
    $tplContent = trim((string)($_POST['template_content'] ?? ''));
    $tplDept = trim((string)($_POST['template_department'] ?? 'Radiology'));
    if ($tplTitle !== '' && $tplContent !== '') {
        $newTplId = template_repo()->create([
            'organization_id' => $orgId,
            'title' => $tplTitle,
            'department' => $tplDept,
            'content' => $tplContent,
            'is_private' => 0,
            'created_by' => current_user()['id'] ?? 'admin',
        ]);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'id' => $newTplId, 'title' => $tplTitle, 'content' => $tplContent]);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Template title and findings text cannot be empty.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax_save_page_grouping'])) {
    require_permission('report_edit');
    $targetLabNo = trim((string)($_POST['lab_no'] ?? $_GET['lab_no'] ?? $labNo ?? ''));
    if ($targetLabNo === '') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Lab number is missing.']);
        exit;
    }

    $pageMap = [];
    if (!empty($_POST['page_map_test']) && is_array($_POST['page_map_test'])) {
        $pageMap = \App\Repositories\ResultRepository::pageMapFromParallel(
            $_POST['page_map_test'],
            $_POST['page_map_page'] ?? []
        );
    } elseif (!empty($_POST['page_map']) && is_array($_POST['page_map'])) {
        $pageMap = $_POST['page_map'];
    }

    if ($pageMap !== []) {
        result_repo()->assignPrintPages($targetLabNo, $pageMap);
    }

    audit_log('UPDATE_PAGE_GROUPING', 'results', $targetLabNo, 'Updated print page grouping');

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'pages_count' => count(array_unique(array_values($pageMap))),
        'page_map' => $pageMap
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['lab_no'])) {
    require_permission('report_edit');
    $postLabNo = trim($_POST['lab_no']);
    $activeTest = trim((string)($_POST['active_test'] ?? $activeTest));
    $resItems = $_POST['results'] ?? [];
    $notes = trim((string)($_POST['clinical_notes'] ?? ''));

    if (isset($_POST['toggle_report_lock'])) {
        $lockAction = (string)$_POST['toggle_report_lock'];
        if ($lockAction === 'lock') {
            lab_repo()->lockReport($postLabNo, current_user()['name'] ?? 'Staff');
            audit_log('LOCK_REPORT', 'lab_entries', $postLabNo, "Report locked by " . (current_user()['name'] ?? 'Staff'));
            header('Location: /portals/results/entry.php?lab_no=' . urlencode($postLabNo) . '&test=' . urlencode($activeTest) . '&report_locked=1');
            exit;
        } elseif ($lockAction === 'unlock') {
            if (!is_admin_user()) {
                $message = flash_error('Security Restriction: Only an Admin account can unlock a locked diagnostic report.');
            } else {
                lab_repo()->unlockReport($postLabNo);
                audit_log('UNLOCK_REPORT', 'lab_entries', $postLabNo, "Report unlocked by Admin " . (current_user()['name'] ?? 'Admin'));
                header('Location: /portals/results/entry.php?lab_no=' . urlencode($postLabNo) . '&test=' . urlencode($activeTest) . '&report_unlocked=1');
                exit;
            }
        }
    }

    $curEntryForLock = lab_repo()->findByLabNo($postLabNo);
    $isReportLocked = !empty($curEntryForLock['is_report_locked']);
    if ($isReportLocked && !is_admin_user()) {
        if (!empty($_POST['ajax_save'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Security Lock: This report is locked. Only an Admin account can modify test results.']);
            exit;
        }
        $message = flash_error('Security Lock: This report has been reviewed and locked. Test results can only be modified with an Admin account.');
        $entry = $curEntryForLock;
        $labNo = $postLabNo;
    } else {
        // Automatically advance transit lifecycle to result_entered
        lab_repo()->updateTransitStatus($postLabNo, 'result_entered');
        audit_log('UPDATE_RESULTS', 'results', $postLabNo, "Updated test results for active test '{$activeTest}'");

    $batchData = [];
    foreach ($resItems as $rId => $item) {
        $val = trim((string)($item['value'] ?? ''));
        $flag = trim((string)($item['flag'] ?? ''));
        $range = trim((string)($item['range'] ?? ''));
        $isVisible = isset($item['show']) ? 1 : 0;

        $isCbcPost = (bool)preg_match('/\b(cbc|complete blood|haematology|hematology)\b/i', $activeTest);
        if (!$isCbcPost || $val === '') {
            $flag = '';
        } else {
            $auto = compute_result_flag($val, $range);
            // Auto-only: prefer computed flag whenever range is numeric
            if ($auto !== '' || is_numeric($val)) {
                $flag = $auto;
            }
        }

        $subTable = array_key_exists('sub_table', $item) ? trim((string)$item['sub_table']) : null;
        $resultNote = array_key_exists('result_note', $item) ? trim((string)$item['result_note']) : null;

        $batchData[] = [
            'id' => $rId,
            'value' => $val,
            'unit' => isset($item['unit']) ? trim((string)$item['unit']) : null,
            'reference_range' => isset($item['range']) ? trim((string)$item['range']) : null,
            'flag' => $flag,
            'is_visible' => $isVisible,
            'sub_table' => $subTable,
            'result_note' => $resultNote,
        ];
    }

    if (!empty($batchData)) {
        result_repo()->saveResultsBatch($postLabNo, $batchData);
    }

    // Determine overall completion status for lab_entries
    $allResultsAfterSave = result_repo()->getResultsByLabNo($postLabNo);
    $hasValues = false;
    $allFilled = true;
    foreach ($allResultsAfterSave as $r) {
        if ((int)($r['is_visible'] ?? 1) === 0) {
            continue;
        }
        $v = trim((string)($r['value'] ?? ''));
        if ($v !== '' && $v !== '-' && $v !== '—' && strcasecmp($v, 'pending') !== 0) {
            $hasValues = true;
        } else {
            $allFilled = false;
        }
    }
    if ($hasValues) {
        $curEntry = lab_repo()->findByLabNo($postLabNo);
        if (($curEntry['status'] ?? '') !== 'verified') {
            $newEntryStatus = $allFilled ? 'completed' : 'in_progress';
            lab_repo()->updateStatus($postLabNo, $newEntryStatus);
        }
    }

    $pageMap = [];
    if (!empty($_POST['page_map_test']) && is_array($_POST['page_map_test'])) {
        $pageMap = \App\Repositories\ResultRepository::pageMapFromParallel(
            $_POST['page_map_test'],
            $_POST['page_map_page'] ?? []
        );
    } elseif (!empty($_POST['page_map']) && is_array($_POST['page_map'])) {
        $pageMap = $_POST['page_map'];
    }
    if ($pageMap !== []) {
        result_repo()->assignPrintPages($postLabNo, $pageMap);
    }
    if (!empty($_POST['ajax_save_page_grouping'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'pages_count' => count(array_unique(array_values($pageMap))),
            'page_map' => $pageMap
        ]);
        exit;
    }

    $entryUpdates = [];
    if (isset($_POST['clinical_notes'])) {
        $entryUpdates['clinical_notes'] = trim((string)$_POST['clinical_notes']);
    }
    if (isset($_POST['reporting_doctor'])) {
        $entryUpdates['reporting_doctor'] = trim((string)$_POST['reporting_doctor']);
    }
    if (isset($_POST['reporting_doctor_quals'])) {
        $entryUpdates['reporting_doctor_quals'] = trim((string)$_POST['reporting_doctor_quals']);
    }
    if (isset($_POST['reporting_doctor_desig'])) {
        $entryUpdates['reporting_doctor_desig'] = trim((string)$_POST['reporting_doctor_desig']);
    }
    if (!empty($entryUpdates)) {
        lab_repo()->updateEntry($postLabNo, $entryUpdates);
    }

    if (isset($_POST['test_methodology'])) {
        $testMethodology = trim((string)$_POST['test_methodology']);
        $testToUpdateId = trim((string)($_POST['active_test_id'] ?? ''));
        if ($testToUpdateId !== '') {
            test_repo()->updateMethodology($testToUpdateId, $testMethodology);
        } elseif ($activeTest !== '') {
            $tObj = test_repo()->findByCode($activeTest, $orgId);
            if ($tObj && !empty($tObj['id'])) {
                test_repo()->updateMethodology((string)$tObj['id'], $testMethodology);
            }
        }
    }

    if (!empty($_POST['ajax_save'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'saved_at' => date('H:i:s')]);
        exit;
    }

    if (!empty($_POST['jump_to_test'])) {
        header('Location: /portals/results/entry.php?lab_no=' . urlencode($postLabNo) . '&test=' . urlencode((string)$_POST['jump_to_test']) . '&saved=1');
        exit;
    }

    if (!empty($_POST['goto_print'])) {
        header('Location: /portals/reports/preview.php?lab_no=' . urlencode($postLabNo) . '&autoprint=1');
        exit;
    }

    if (!empty($_POST['send_verify']) || !empty($_POST['goto_preview'])) {
        if (!empty($_POST['send_verify'])) {
            result_repo()->verifyAllByLabNo($postLabNo, current_user()['name'] ?? 'Laboratory Staff');
        }
        header('Location: /portals/reports/preview.php?lab_no=' . urlencode($postLabNo));
        exit;
    }

    if (!empty($_POST['save_and_next'])) {
        // Group tests on this visit to find next test
        $byTestPost = [];
        foreach ($allResultsAfterSave as $r) {
            $tName = trim((string)($r['test'] ?? 'Test'));
            if ($tName === '') {
                $tName = 'Test';
            }
            $byTestPost[$tName] = true;
        }
        $testNamesPost = array_keys($byTestPost);
        $testIndexPost = array_search($activeTest, $testNamesPost, true);
        $nextTestName = null;
        if ($testIndexPost !== false && $testIndexPost < count($testNamesPost) - 1) {
            $nextTestName = $testNamesPost[$testIndexPost + 1];
        }

        if ($nextTestName !== null && $nextTestName !== '') {
            header('Location: /portals/results/entry.php?lab_no=' . urlencode($postLabNo) . '&test=' . urlencode($nextTestName) . '&saved=1');
            exit;
        }

        // All tests for this patient completed -> DO NOT auto-advance to another patient.
        // Stay on current patient and display completion banner so staff can verify or manually search next.
        header('Location: /portals/results/entry.php?lab_no=' . urlencode($postLabNo) . '&test=' . urlencode($activeTest) . '&saved=1&patient_finished=1');
        exit;
    }

        $message = flash_success('Results saved. Unchecked rows stay off the printed report.');
        $entry = lab_repo()->findByLabNo($postLabNo);
        $labNo = $postLabNo;
    }
}

if (!$entry) {
    $content = page_header('Enter Results', 'No patient tests found yet.');
    $content .= card(
        '<p class="p-6 text-slate-600">Register a patient and book tests first, then open this screen to type results.</p>' .
        '<div class="px-6 pb-6"><a class="btn btn-primary" href="/portals/patients/records.php">Open patient list</a></div>'
    );
    $portal = current_portal();
    $activeKey = ($portal === 'imaging') ? 'station-lab' : 'results-entry';
    render_page('Enter Results', $portal, $activeKey, $content);
    exit;
}

$patient = patient_repo()->findById((string)($entry['patient_id'] ?? '')) ?: [];
$mrNo = e((string)($patient['patient_no'] ?? ($entry['patient_id'] ?? '—')));
$patientName = e((string)($entry['patient_name'] ?? ($patient['full_name'] ?? '—')));
$testsOrdered = e(normalize_tests_list((string)($entry['tests'] ?? '')));
$doctor = e((string)($entry['doctor'] ?? 'Walk-in / Self'));
$status = e((string)($entry['status'] ?? 'pending'));
$age = e((string)($patient['age'] ?? '—'));
$gender = e((string)($patient['gender'] ?? '—'));
$phone = e((string)($patient['phone'] ?? '—'));
$regAt = e(format_datetime_report($entry['created_at'] ?? null));
$notes = e((string)($entry['clinical_notes'] ?? ''));
$labNoSafe = e($labNo);

$resultRows = result_repo()->ensureResultsInitialized(
    $labNo,
    (string)($entry['tests'] ?? ''),
    (string)($entry['patient_name'] ?? ($patient['full_name'] ?? '')),
    $orgId
);

// Group rows by test name (CBC, LFT, …)
$byTest = [];
foreach ($resultRows as $r) {
    $tName = trim((string)($r['test'] ?? 'Test'));
    if ($tName === '') {
        $tName = 'Test';
    }
    $byTest[$tName][] = $r;
}
$testNames = array_keys($byTest);
if ($activeTest === '' || !isset($byTest[$activeTest])) {
    $activeTest = $testNames[0] ?? '';
}
$testCount = count($testNames);
$testIndex = max(0, array_search($activeTest, $testNames, true));
$prevTest = $testIndex > 0 ? $testNames[$testIndex - 1] : null;
$nextTest = $testIndex < $testCount - 1 ? $testNames[$testIndex + 1] : null;
$saveAndNextBtnHtml = $nextTest
    ? ('<i class="fa-solid fa-forward-step"></i> Save &amp; Next Test (' . e($nextTest) . ') &rarr;')
    : ('<i class="fa-solid fa-check"></i> Save Patient Results');

$baseUrl = '/portals/results/entry.php?lab_no=' . urlencode($labNo);
$previewLink = '/portals/reports/preview.php?lab_no=' . urlencode($labNo);
$addTestLink = '/portals/lab-entries/edit.php?lab_no=' . urlencode($labNo);
$historyMr = (string)($patient['patient_no'] ?? ($entry['patient_id'] ?? ''));
$historyLink = '/portals/patients/history.php?id=' . urlencode($historyMr);

// Patient switcher & live search dataset (searches by Lab No, MR No, or Name in real-time)
$allEntries = lab_repo()->getEntriesWithPatients($orgId, 300);
$switcherOpts = '';
$searchVisits = [];
foreach ($allEntries as $ae) {
    $sel = $ae['lab_no'] === $labNo ? ' selected' : '';
    $testsLabel = normalize_tests_list((string)($ae['tests'] ?? ''));
    $switcherOpts .= '<option value="' . e($ae['lab_no']) . '"' . $sel . '>'
        . e($ae['lab_no']) . ' — ' . e($ae['patient_name']) . ' (' . e($testsLabel) . ')</option>';

    $searchVisits[] = [
        'lab_no' => (string)$ae['lab_no'],
        'mr_no' => (string)($ae['mr_no'] ?? ''),
        'patient_name' => (string)($ae['patient_name'] ?? $ae['full_name'] ?? ''),
        'tests' => $testsLabel,
        'status' => (string)($ae['status'] ?? 'pending'),
        'age' => (string)($ae['age'] ?? ''),
        'gender' => (string)($ae['gender'] ?? ''),
        'doctor' => (string)($ae['doctor'] ?? ''),
        'date' => format_date($ae['created_at'] ?? null),
        'is_current' => ($ae['lab_no'] === $labNo),
    ];
}
$searchVisitsJson = json_encode($searchVisits, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

$testSelectOpts = '';
foreach ($testNames as $i => $tn) {
    $sel = $tn === $activeTest ? ' selected' : '';
    $testSelectOpts .= '<option value="' . e($tn) . '"' . $sel . '>' . e($tn) . '</option>';
}

$activeRows = $byTest[$activeTest] ?? [];

// Resolve catalog meta for dropdown options and methodology on the active test
$activeTestMeta = test_repo()->findByCode($activeTest, $orgId);
$activeTestId = (string)($activeTestMeta['id'] ?? '');
$activeTestMethodology = (string)($activeTestMeta['methodology'] ?? '');
$testResultType = !empty($activeTestMeta['result_type']) ? strtolower((string)$activeTestMeta['result_type']) : '';
$testResultOptions = parse_result_options((string)($activeTestMeta['result_options'] ?? ''));
if ($testResultOptions === null && $testResultType === 'options') {
    $testResultOptions = parse_result_options((string)($activeTestMeta['normal_range'] ?? $activeTestMeta['reference_value'] ?? ''));
}

$isImagingActive = is_narrative_imaging_test($activeTest, $activeTestMeta['category'] ?? null);
$imagingTemplates = template_repo()->getAll($orgId);
$tplSelectOpts = '';
$customTemplatesOptGroup = '';
$tplJsonMap = [];
if (!empty($imagingTemplates)) {
    $customTemplatesOptGroup = '<optgroup label="⭐ Saved Custom Templates">';
    foreach ($imagingTemplates as $tpl) {
        $tId = (string)$tpl['id'];
        $tTitle = (string)$tpl['title'];
        $tplSelectOpts .= '<option value="' . e($tId) . '">' . e($tTitle) . '</option>';
        $customTemplatesOptGroup .= '<option value="tpl:' . e($tId) . '">' . e($tTitle) . '</option>';
        $tplJsonMap[$tId] = (string)$tpl['content'];
    }
    $customTemplatesOptGroup .= '</optgroup>';
}
$tplJsonMapEncoded = json_encode($tplJsonMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

// Built-in standard normal imaging report presets
$clinicalPresets = [
    'us_whole_abd' => "EXAMINATION: ULTRASOUND WHOLE ABDOMEN & PELVIS\n\nLIVER:\nNormal in size, smooth contour and uniform parenchymal echotexture. No focal solid or cystic mass lesion seen. Intrahepatic biliary tree and portal vein are normal in caliber.\n\nGALLBLADDER:\nNormal in size and shape. Wall thickness is normal (< 3 mm). Acoustic lumen is completely clear; no calculus, sludge or mass lesion seen. Common bile duct (CBD) is normal.\n\nPANCREAS:\nVisualized head, body and tail appear normal in size and echogenicity. Main pancreatic duct is not dilated.\n\nSPLEEN:\nNormal in size and parenchymal echotexture. No splenomegaly or focal lesion noted.\n\nKIDNEYS:\nBoth kidneys are normal in size, shape and cortical thickness. Corticomedullary differentiation is well preserved. No calculus or hydronephrosis seen bilaterally.\n\nURINARY BLADDER:\nWell distended with thin, smooth walls and clear lumen. No intraluminal calculus or mass.\n\nPELVIS / PROSTATE:\nVisualized pelvic structures are unremarkable. No free fluid in abdomen or pelvis.\n\nIMPRESSION:\nNORMAL ULTRASOUND EXAMINATION OF WHOLE ABDOMEN & PELVIS.",

    'us_upper_abd' => "EXAMINATION: ULTRASOUND UPPER ABDOMEN\n\nLIVER:\nNormal in size, shape and parenchymal echotexture. No focal mass, abscess, or nodule seen. Intrahepatic ducts are not dilated. Portal vein is normal in caliber.\n\nGALLBLADDER:\nNormal lumen without gallstones, sludge, or wall thickening. Common bile duct (CBD) measures within normal limits.\n\nPANCREAS:\nVisualized portions appear normal in size and echogenicity without mass or calcification.\n\nSPLEEN:\nNormal in size with homogenous parenchymal echotexture. No focal lesion or splenomegaly.\n\nKIDNEYS:\nBoth kidneys are normal in size, shape, and cortical thickness. No calculus or hydronephrosis seen.\n\nIMPRESSION:\nNORMAL ULTRASOUND EXAMINATION OF UPPER ABDOMEN.",

    'us_kub' => "EXAMINATION: ULTRASOUND KUB & PROSTATE\n\nRIGHT KIDNEY:\nMeasures normal in size and anatomical position. Corticomedullary differentiation is well preserved. Normal parenchymal thickness. No evidence of renal calculus, hydronephrosis, or focal lesion.\n\nLEFT KIDNEY:\nMeasures normal in size and anatomical position. Corticomedullary differentiation is well preserved. Normal parenchymal thickness. No evidence of renal calculus, hydronephrosis, or focal lesion.\n\nURETERS:\nBoth ureters are not dilated; no obstructive ureteric calculus visualized.\n\nURINARY BLADDER:\nAdequately distended with clear acoustic lumen. Bladder wall thickness is smooth and uniform (< 3 mm). No intraluminal calculus, mass, or diverticulum seen.\n- Pre-void volume: Adequate.\n- Post-void residual (PVR): Insignificant (< 20 ml).\n\nPROSTATE:\nNormal in size, shape, and echotexture with smooth capsule. Estimated volume is within normal age-related limits. Median lobe is not enlarged.\n\nIMPRESSION:\nNORMAL ULTRASOUND KUB & PROSTATE. NO EVIDENCE OF NEPHROLITHIASIS OR URINARY TRACT OBSTRUCTION.",

    'us_pelvis' => "EXAMINATION: ULTRASOUND PELVIS (FEMALE / GYNAECOLOGICAL)\n\nURINARY BLADDER:\nWell distended with clear lumen and smooth walls.\n\nUTERUS:\n- Position: Anteverted.\n- Size & Contour: Normal in size and regular in contour.\n- Myometrium: Homogeneous echotexture. No focal leiomyoma (fibroid), adenomyosis, or localized mass lesion seen.\n- Endometrium: Central, regular, and well-defined endometrial stripe measuring within normal physiological limits for cycle phase.\n\nCERVIX:\nNormal in appearance. No Nabothian cyst or mass identified.\n\nRIGHT OVARY:\nVisualized and measures normal in size with normal follicular pattern. No cystic or solid mass lesion seen.\n\nLEFT OVARY:\nVisualized and measures normal in size with normal follicular pattern. No cystic or solid mass lesion seen.\n\nADNEXAE & POUCH OF DOUGLAS (POD):\nBoth adnexal regions are free. No adnexal mass, localized collection, or free fluid in the Pouch of Douglas.\n\nIMPRESSION:\nNORMAL PELVIC ULTRASOUND EXAMINATION. NORMAL APPEARANCE OF UTERUS AND BOTH OVARIES.",

    'us_obstetric' => "EXAMINATION: OBSTETRIC ULTRASOUND SCAN (ANTENATAL)\n\nFETAL VIABILITY & NUMBER:\n- Number of fetuses: Single live intrauterine fetus.\n- Cardiac Activity: Present and regular (Fetal Heart Rate: ~144 bpm).\n- Fetal Movements: Active somatic and limb movements noted.\n- Presentation: Cephalic / Vertex.\n\nFETAL BIOMETRY:\n- Biparietal Diameter (BPD): Normal for gestational age.\n- Femur Length (FL): Normal for gestational age.\n- Abdominal Circumference (AC): Normal for gestational age.\n- Head Circumference (HC): Normal for gestational age.\n- Average Gestational Age (EGA): Consistent with dates.\n- Expected Date of Delivery (EDD): Consistent with biometry.\n\nAMNIOTIC FLUID (LIQUOR):\nAdequate in quantity. Amniotic fluid index (AFI) is within normal limits.\n\nPLACENTA:\n- Position: Anterior / Fundal, Grade I maturity.\n- Relation to Os: Lower margin is well clear of the internal cervical os. No retroplacental hematoma seen.\n\nIMPRESSION:\nSINGLE LIVE INTRAUTERINE PREGNANCY CORRESPONDING TO GESTATIONAL AGE WITH ACTIVE FETAL MOVEMENTS AND NORMAL LIQUOR / PLACENTAL POSITION.",

    'us_anomaly' => "EXAMINATION: TARGETED FETAL ANOMALY SCAN (2ND TRIMESTER)\n\nFETAL BIOMETRY & VIABILITY:\n- Single live fetus in cephalic presentation.\n- Normal regular fetal cardiac activity and somatic movements.\n- Fetal biometry (BPD, HC, AC, FL) corresponds to clinical gestational age.\n\nANATOMICAL SURVEY:\n- Head & Brain: Intact skull contour, symmetrical lateral ventricles, cavum septum pellucidum visualized, normal posterior fossa & cerebellum.\n- Face: Both orbits, lens, nasal bone, and intact upper lip visualized.\n- Spine: Complete and intact vertebrae from cervical to sacral region in sagittal and transverse planes.\n- Thorax & Heart: Intact diaphragm, normal 4-chamber cardiac view, normal outflow tracts (LVOT/RVOT), and normal cardiac rhythm.\n- Abdomen: Intact anterior abdominal wall, normal stomach bubble, intact cord insertion. Both kidneys and bladder visualized.\n- Limbs: Bilateral upper and lower long bones, hands, and feet visualized and appear normal.\n- Amniotic Fluid: Adequate in volume.\n- Placenta: Normal position and echotexture, clear of internal os. 3-vessel umbilical cord identified.\n\nIMPRESSION:\nTARGETED ANOMALY SCAN REVEALS NO GROSS CONGENITAL OR STRUCTURAL ANOMALY DETECTED AT PRESENT GESTATIONAL AGE.",

    'us_tvs' => "EXAMINATION: TRANSVAGINAL ULTRASOUND SCAN (TVS)\n\nUTERUS:\n- Position: Anteverted.\n- Myometrium: Homogeneous echotexture throughout. No focal intramural, subserosal, or submucosal fibroid.\n- Endometrium: Distinct trilaminar / secretory endometrial stripe measuring normal in thickness. Cavity is empty with regular margins.\n- Cervix: Normal length and internal os closed.\n\nRIGHT OVARY:\nNormal in volume and location. Follicles are peripheral and physiological in size. No complex ovarian mass.\n\nLEFT OVARY:\nNormal in volume and location. Follicles are peripheral and physiological in size. No complex ovarian mass.\n\nPOUCH OF DOUGLAS:\nFree of fluid, localized collection, or endometriotic deposits.\n\nIMPRESSION:\nNORMAL TRANSVAGINAL ULTRASOUND STUDY (TVS). UNREMARKABLE PELVIC ORGANS.",

    'us_scrotal' => "EXAMINATION: ULTRASOUND SCROTUM & COLOR DOPPLER\n\nRIGHT TESTIS:\nNormal in size, shape, and homogenous echotexture. Normal intratesticular vascular flow demonstrated on Color and Spectral Doppler. No focal intratesticular mass or calcification.\n- Right Epididymis: Normal size and echogenicity.\n- Tunica Vaginalis: No hydrocele.\n\nLEFT TESTIS:\nNormal in size, shape, and homogenous echotexture. Normal intratesticular vascular flow demonstrated on Color and Spectral Doppler. No focal intratesticular mass or calcification.\n- Left Epididymis: Normal size and echogenicity.\n- Tunica Vaginalis: No hydrocele.\n\nPAMPINIFORM PLEXUS:\nVenous channels demonstrate normal diameter bilaterally (< 2.0 mm). No retrograde venous reflux elicited during Valsalva maneuver. No evidence of varicocele bilaterally.\n\nIMPRESSION:\nNORMAL SCROTAL AND TESTICULAR COLOR DOPPLER STUDY. NO EVIDENCE OF EPIDIDYMO-ORCHITIS, HYDROCELE, OR VARICOCELE.",

    'us_thyroid' => "EXAMINATION: ULTRASOUND THYROID & NECK\n\nRIGHT THYROID LOBE:\nNormal in dimensions and parenchymal echogenicity. No focal solid nodule, cystic lesion, or calcification identified. Normal vascularity on color Doppler.\n\nLEFT THYROID LOBE:\nNormal in dimensions and parenchymal echogenicity. No focal solid nodule, cystic lesion, or calcification identified. Normal vascularity on color Doppler.\n\nISTHMUS:\nNormal in thickness (< 3 mm) and uniform in texture.\n\nCERVICAL LYMPH NODES:\nNo pathologically enlarged or suspicious cervical lymph nodes identified bilaterally.\n\nIMPRESSION:\nNORMAL ULTRASOUND EXAMINATION OF THYROID GLAND. NO EVIDENCE OF GOITRE, NODULE, OR CYSTIC LESION.",

    'us_breast' => "EXAMINATION: BILATERAL BREAST ULTRASOUND (SONOMAMMOGRAPHY)\n\nRIGHT BREAST:\nNormal fibroglandular parenchyma with normal ductal pattern. No focal solid mass lesion, cyst, or architectural distortion seen. Retroareolar ducts are not dilated.\n\nLEFT BREAST:\nNormal fibroglandular parenchyma with normal ductal pattern. No focal solid mass lesion, cyst, or architectural distortion seen. Retroareolar ducts are not dilated.\n\nAXILLARY REGIONS:\nVisualized bilateral axillary lymph nodes show normal preserved fatty hilum with benign sonographic morphology. No suspicious lymphadenopathy.\n\nACR BI-RADS ASSESSMENT: CATEGORY 1 (NEGATIVE / NORMAL STUDY).\n\nIMPRESSION:\nNORMAL BILATERAL BREAST ULTRASOUND (BI-RADS CATEGORY 1). NO SONOGRAPHIC EVIDENCE OF SOLID OR CYSTIC LESION.",

    'us_follicular' => "EXAMINATION: ULTRASOUND FOLLICULAR STUDY (FOLLICULOMETRY)\n\nDAY OF CYCLE: Day _____\n\nENDOMETRIUM:\n- Thickness: _____ mm.\n- Echopattern: Triple-line pattern (Trilaminar) / Secretory.\n\nRIGHT OVARY:\n- Dominant Follicle: _____ x _____ mm.\n- Secondary Follicles: Multiple small developing follicles.\n\nLEFT OVARY:\n- Dominant Follicle: _____ x _____ mm.\n- Secondary Follicles: Multiple small developing follicles.\n\nFREE FLUID IN POUCH OF DOUGLAS:\nNone / Minimal physiological fluid.\n\nIMPRESSION:\nFOLLICULAR STUDY SHOWING MATURING DOMINANT FOLLICLE AS NOTED ABOVE.",

    'us_soft_tissue' => "EXAMINATION: ULTRASOUND SOFT TISSUE / SWELLING\n\nSITE OF EXAMINATION: ____________________\n\nFINDINGS:\n- High-frequency sonographic examination of the region demonstrates normal subcutaneous tissue and muscular planes.\n- No localized cystic lesion, solid mass, abscess, or hematoma identified.\n- Overlying skin and deep fascia appear intact.\n- Color Doppler shows normal physiological vascularity without hypervascularity.\n\nIMPRESSION:\nNORMAL ULTRASOUND EVALUATION OF SOFT TISSUES. NO FOCAL MASS LESION OR FLUID COLLECTION NOTED.",

    'xray_chest' => "EXAMINATION: CHEST X-RAY (PA VIEW)\n\nCLINICAL INDICATION: Routine / Pre-employment / Pre-operative evaluation\n\nFINDINGS:\n- Lung Fields: Both lung fields are clear and normally aerated. No focal pulmonary infiltration, consolidation, nodule, or mass seen.\n- Pleural Spaces: Both costophrenic and cardiophrenic sulci are sharp and clear. No evidence of pleural effusion or pneumothorax.\n- Cardiac Silhouette: Cardiac size and contour are within normal limits. Normal cardiothoracic ratio (< 50%).\n- Mediastinum & Hila: Mediastinal contours and hila appear normal. Trachea is central in position.\n- Bony Cage & Soft Tissues: Ribs, clavicles, and thoracic cage are unremarkable with no acute bony injury. Soft tissues appear normal.\n\nIMPRESSION:\nNORMAL RADIOLOGICAL STUDY OF CHEST (PA VIEW). NO ACTIVE CARDIOPULMONARY LESION DETECTED.",

    'xray_chest_abnormal' => "EXAMINATION: CHEST X-RAY (PA VIEW)\n\nCLINICAL INDICATION: Productive cough, fever, shortness of breath\n\nFINDINGS:\n- Lung Fields: Inhomogeneous patchy airspace opacification / consolidation noted in the right lower zone, suspicious of infective etiology. Left lung field is clear.\n- Pleural Spaces: Minimal blunting of the right costophrenic angle. Left costophrenic angle is clear.\n- Cardiac Silhouette: Normal in size and contour.\n- Mediastinum & Hila: Mediastinal contours and hila appear normal.\n- Bony Cage & Soft Tissues: Unremarkable.\n\nIMPRESSION:\nRIGHT LOWER ZONE INHOMOGENEOUS CONSOLIDATION / INFILTRATE, COMPATIBLE WITH PNEUMONIC CONSOLIDATION. CLINICAL AND LAB CORRELATION RECOMMENDED.",

    'xray_cervical' => "EXAMINATION: CERVICAL SPINE X-RAY (AP & LATERAL VIEWS)\n\nCLINICAL INDICATION: Neck pain and stiffness radiating to shoulder\n\nFINDINGS:\n- Normal physiological cervical lordosis is preserved / mildly straightened.\n- Vertebral body heights and alignment are maintained. No acute fracture or dislocation.\n- Intervertebral disc spaces at C5-C6 and C6-C7 show mild narrowing with anterior marginal osteophytosis, consistent with early degenerative spondylotic changes.\n- Posterior elements, pedicles, and facet joints appear unremarkable.\n- Prevertebral soft tissue shadow is within normal limits.\n\nIMPRESSION:\nEARLY CERVICAL SPONDYLOSIS WITH C5-C6 AND C6-C7 DISC SPACE NARROWING. NO EVIDENCE OF ACUTE FRACTURE.",

    'xray_ls_spine' => "EXAMINATION: LUMBOSACRAL (L-S) SPINE X-RAY (AP & LATERAL VIEWS)\n\nCLINICAL INDICATION: Chronic lower back pain / Sciatica\n\nFINDINGS:\n- Normal lumbar lordotic curvature is preserved.\n- Vertebral body heights are maintained throughout the lumbar spine. No acute compression fracture or spondylolisthesis.\n- L4-L5 and L5-S1 intervertebral disc spaces show mild-to-moderate narrowing with marginal osteophytic spurring and endplate sclerosis.\n- Pedicles, laminae, and transverse processes appear intact.\n- Sacroiliac joints are symmetrical with unremarkable joint margins.\n- Psoas shadows are symmetrically visualized.\n\nIMPRESSION:\nLUMBOSACRAL SPONDYLOSIS WITH L4-L5 AND L5-S1 DEGENERATIVE DISC DISEASE. NO ACUTE SKELETAL INJURY.",

    'xray_knee' => "EXAMINATION: KNEE JOINT X-RAY (AP & LATERAL VIEWS)\n\nCLINICAL INDICATION: Knee pain, crepitus on walking\n\nFINDINGS:\n- Bone mineralization is normal.\n- Medial tibiofemoral joint space demonstrates mild-to-moderate narrowing with marginal tibial spine spurring and subchondral sclerosis.\n- Lateral tibiofemoral and patellofemoral compartments are relatively preserved.\n- No evidence of radiopaque loose body, acute fracture, or joint effusion.\n- Soft tissues around the knee joint appear unremarkable.\n\nIMPRESSION:\nPRIMARY OSTEOARTHRITIS OF THE KNEE JOINT (GRADE II KELLGREN-LAWRENCE) WITH MEDIAL COMPARTMENT PREDOMINANCE.",

    'xray_pelvis' => "EXAMINATION: PELVIS WITH BOTH HIPS (AP VIEW)\n\nCLINICAL INDICATION: Hip / Pelvic pain\n\nFINDINGS:\n- Pelvic ring is complete and intact. No fracture, diastasis, or destructive bone lesion.\n- Both femoral heads are spherical and well-seated within the acetabula. Shenton's lines are intact bilaterally.\n- Hip joint spaces are preserved bilaterally with no significant degenerative narrowing or osteophytosis.\n- Sacroiliac and pubic symphysis joints appear normal.\n- Visualized pelvic soft tissues and gas shadow are unremarkable.\n\nIMPRESSION:\nNORMAL RADIOLOGICAL STUDY OF PELVIS AND BOTH HIP JOINTS. NO FRACTURE OR GROSS ARTHRITIC CHANGE.",

    'xray_abdomen' => "EXAMINATION: ABDOMEN ERECT & SUPINE PLAIN X-RAY (KUB)\n\nCLINICAL INDICATION: Abdominal colic / Rule out obstruction or perforation\n\nFINDINGS:\n- Bowel Gas Pattern: Normal physiological distribution of gas within the stomach and colon. No abnormally dilated small or large bowel loops.\n- Air-Fluid Levels: No multiple or pathological step-ladder air-fluid levels seen.\n- Free Gas: No evidence of pneumoperitoneum (no free crescentic gas under the right or left hemidiaphragms on erect view).\n- Radiopaque Shadows: No obvious radio-opaque calculus visualized along the anatomical course of the urinary tract (KUB).\n- Soft Tissues & Skeleton: Psoas outlines and flank stripes are preserved. Bony pelvis and lower ribs are intact.\n\nIMPRESSION:\nNORMAL ABDOMINAL GAS PATTERN. NO RADIOLOGICAL EVIDENCE OF MECHANICAL BOWEL OBSTRUCTION, CALCULUS, OR PNEUMOPERITONEUM.",

    'ecg_12' => "EXAMINATION: 12-LEAD ELECTROCARDIOGRAM (ECG)\n\nCLINICAL INDICATION: Routine cardiac evaluation / Pre-operative screening\n\nMEASUREMENTS & PARAMETERS:\n- Rhythm: Normal Sinus Rhythm\n- Heart Rate: 72 beats per minute (bpm)\n- P Wave: Normal morphology and amplitude (0.08 sec)\n- PR Interval: 0.16 sec (Normal range: 0.12 - 0.20 sec)\n- QRS Duration: 0.08 sec (Normal range: 0.06 - 0.10 sec)\n- QTc Interval: 412 ms (Normal range: < 440 ms for males, < 460 ms for females)\n- Cardiac Axis: Normal axis (+45° to +60°)\n\nREPOLARIZATION & MORPHOLOGY:\n- ST Segment: Isoelectric across all limb and precordial leads; no ST elevation or depression.\n- T Waves: Upright and normal morphology in leads I, II, aVF, and V3-V6. Inverted in aVR.\n- Pathological Q Waves: Absent.\n\nIMPRESSION:\nNORMAL 12-LEAD ELECTROCARDIOGRAM (NORMAL SINUS RHYTHM, RATE 72 BPM, NO EVIDENCE OF ISCHEMIA OR CONDUCTION ABNORMALITY).",

    'ecg_tachy' => "EXAMINATION: 12-LEAD ELECTROCARDIOGRAM (ECG)\n\nCLINICAL INDICATION: Palpitations, anxiety, fever\n\nMEASUREMENTS & PARAMETERS:\n- Rhythm: Sinus Tachycardia\n- Heart Rate: 115 beats per minute (bpm)\n- P Wave: Precedes every QRS complex, normal axis\n- PR Interval: 0.14 sec\n- QRS Duration: 0.08 sec\n- QTc: 420 ms\n- Axis: Normal\n\nREPOLARIZATION:\n- ST Segment: Isoelectric, no significant ST elevation or depression.\n- T Waves: Normal morphology.\n\nIMPRESSION:\nSINUS TACHYCARDIA (RATE 115 BPM). NO ACUTE ST-T WAVE ISCHEMIC CHANGES. CORRELATE CLINICALLY WITH FEVER, ANXIETY, DEHYDRATION, OR THYROID STATUS.",

    'ecg_ischemia' => "EXAMINATION: 12-LEAD ELECTROCARDIOGRAM (ECG)\n\nCLINICAL INDICATION: Chest tightness, retrosternal discomfort\n\nMEASUREMENTS & PARAMETERS:\n- Rhythm: Normal Sinus Rhythm\n- Heart Rate: 78 bpm\n- PR Interval: 0.16 sec\n- QRS Duration: 0.09 sec\n- Axis: Normal\n\nREPOLARIZATION:\n- ST-T Changes: Horizontal / downsloping ST-segment depression (~1.5 mm) with symmetrical T-wave inversions noted in leads V4, V5, V6, I, and aVL (Anterolateral leads).\n- Inferior leads (II, III, aVF) show flat T waves.\n\nIMPRESSION:\nANTEROLATERAL ST-T CHANGES SUGGESTIVE OF MYOCARDIAL ISCHEMIA. CLINICAL CORRELATION AND CARDIAC ENZYMES (TROPONIN-I) WITH CARDIOLOGIST REVIEW RECOMMENDED.",

    'ecg_lvh' => "EXAMINATION: 12-LEAD ELECTROCARDIOGRAM (ECG)\n\nCLINICAL INDICATION: Long-standing systemic hypertension\n\nMEASUREMENTS & PARAMETERS:\n- Rhythm: Normal Sinus Rhythm\n- Heart Rate: 68 bpm\n- Axis: Left Axis Deviation (-15°)\n- Sokolow-Lyon Criteria: S in V1 (18 mm) + R in V5 (22 mm) = 40 mm (Positive for LVH > 35 mm)\n- Cornell Voltage: R in aVL + S in V3 meets criteria for LVH.\n- Repolarization: ST-segment depression and asymmetric T-wave inversion in lateral leads (I, aVL, V5, V6), consistent with LV 'strain' pattern.\n\nIMPRESSION:\nLEFT VENTRICULAR HYPERTROPHY (LVH) WITH SECONDARY REPOLARIZATION (STRAIN) PATTERN. COMPATIBLE WITH HYPERTENSIVE HEART DISEASE. 2D ECHOCARDIOGRAPHY ADVISED.",

    'echo_normal' => "TRANSTHORACIC 2D ECHOCARDIOGRAPHY & COLOR DOPPLER REPORT\n\nCLINICAL INDICATION: Cardiac assessment / Pre-op fitness / Evaluation\n\nM-MODE & 2D MEASUREMENTS:\n- Left Ventricle Internal Diameter Diastole (LVIDd): 4.6 cm (Normal: 3.5 - 5.6 cm)\n- Left Ventricle Internal Diameter Systole (LVIDs): 2.9 cm (Normal: 2.0 - 4.0 cm)\n- Interventricular Septum Diastole (IVSd): 0.9 cm (Normal: 0.6 - 1.1 cm)\n- Posterior Wall Diastole (PWd): 0.9 cm (Normal: 0.6 - 1.1 cm)\n- Left Atrium Diameter (LA): 3.2 cm (Normal: 1.9 - 4.0 cm)\n- Aortic Root Diameter (Ao): 2.8 cm (Normal: 2.0 - 3.7 cm)\n- Ejection Fraction (EF): 62% (Simpson's biplane, Normal: 55 - 75%)\n- Fractional Shortening (FS): 36% (Normal: 28 - 44%)\n\nCHAMBER SIZES & LV FUNCTION:\n- All four cardiac chambers (LV, RV, LA, RA) are normal in size and dimensions.\n- Left ventricular global and regional systolic function is preserved.\n- No resting regional wall motion abnormality (RWMA) identified.\n- Right ventricular systolic function is normal (TAPSE: 22 mm).\n\nVALVULAR ASSESSMENT & COLOR DOPPLER:\n- Mitral Valve: Leaflets are thin, pliable with normal opening and closure. No mitral stenosis (MS). Trace physiological mitral regurgitation (MR).\n- Aortic Valve: Trileaflet aortic valve with good excursion. No aortic stenosis (AS) or regurgitation (AR).\n- Tricuspid Valve: Normal leaflet morphology. Estimated pulmonary artery systolic pressure (PASP) is normal (~24 mmHg).\n- Pulmonic Valve: Normal.\n\nPERICARDIUM & OTHERS:\n- Pericardium is normal. No pericardial effusion seen.\n- No intracardiac thrombus, vegetative mass, or shunt visualized.\n- Interatrial and interventricular septa appear intact.\n\nIMPRESSION:\n1. NORMAL 2D ECHOCARDIOGRAM WITH COLOR DOPPLER STUDY.\n2. PRESERVED LEFT VENTRICULAR SYSTOLIC FUNCTION (EF = 62%).\n3. NO REGIONAL WALL MOTION ABNORMALITY AT REST.\n4. NORMAL VALVULAR MORPHOLOGY AND FUNCTION.",

    'echo_lvh' => "TRANSTHORACIC 2D ECHOCARDIOGRAPHY & COLOR DOPPLER REPORT\n\nCLINICAL INDICATION: Chronic Hypertension / Dyspnea on exertion\n\nM-MODE & 2D MEASUREMENTS:\n- LVIDd: 4.8 cm\n- LVIDs: 3.1 cm\n- IVSd: 1.3 cm (Increased, Normal: 0.6 - 1.1 cm)\n- PWd: 1.2 cm (Increased, Normal: 0.6 - 1.1 cm)\n- Left Atrium (LA): 4.1 cm (Mildly dilated)\n- Ejection Fraction (EF): 60% (Preserved)\n\nCHAMBER SIZES & LV FUNCTION:\n- Concentric Left Ventricular Hypertrophy (LVH) noted with thickened septum and posterior wall.\n- Global LV systolic performance is preserved (EF: 60%).\n- No resting regional wall motion abnormality.\n- Right ventricle and right atrium are normal in size.\n\nDOPPLER & DIASTOLIC FUNCTION:\n- Mitral inflow Doppler demonstrates impaired LV relaxation pattern (E/A ratio < 0.8, prolonged deceleration time), consistent with Grade I (Mild) LV Diastolic Dysfunction.\n- Mild trivial Mitral Regurgitation (MR).\n- Aortic valve shows sclerotic changes without significant gradient.\n- No pericardial effusion or intracardiac mass.\n\nIMPRESSION:\n1. CONCENTRIC LEFT VENTRICULAR HYPERTROPHY (LVH) WITH MILD LEFT ATRIAL ENLARGEMENT.\n2. PRESERVED LV SYSTOLIC FUNCTION (EF = 60%).\n3. GRADE I (IMPAIRED RELAXATION) LV DIASTOLIC DYSFUNCTION.\n4. COMPATIBLE WITH HYPERTENSIVE HEART DISEASE.",

    'echo_rwma' => "TRANSTHORACIC 2D ECHOCARDIOGRAPHY & COLOR DOPPLER REPORT\n\nCLINICAL INDICATION: Post-Myocardial Infarction / Ischemic Heart Disease\n\nM-MODE & 2D MEASUREMENTS:\n- LVIDd: 5.6 cm (Dilated)\n- LVIDs: 4.3 cm (Dilated)\n- IVSd: 0.9 cm\n- PWd: 0.9 cm\n- Left Atrium: 4.3 cm (Dilated)\n- Ejection Fraction (EF): 40% (Moderately reduced, Simpson's biplane)\n\nREGIONAL WALL MOTION ANALYSIS (RWMA):\n- Anterior wall, anteroseptum, and apex are hypokinetic to akinetic.\n- Inferior, posterior, and lateral walls demonstrate compensatory hyperkinesia.\n\nVALVULAR ASSESSMENT & DOPPLER:\n- Mild-to-moderate functional Mitral Regurgitation (MR) secondary to annular dilatation.\n- Aortic, tricuspid, and pulmonic valves appear unremarkable.\n- Estimated PASP is mildly elevated (~36 mmHg).\n- No intracardiac clot or LV apical thrombus detected on current study.\n- No pericardial effusion.\n\nIMPRESSION:\n1. DILATED LEFT VENTRICLE WITH MODERATELY REDUCED LV SYSTOLIC FUNCTION (EF = 40%).\n2. REGIONAL WALL MOTION ABNORMALITY (ANTEROSEPTAL AND APICAL HYPOKINESIA / AKINESIA), COMPATIBLE WITH CORONARY ARTERY DISEASE (LAD TERRITORY).\n3. MILD-TO-MODERATE FUNCTIONAL MITRAL REGURGITATION."
];

// Determine matching normal preset for active test
$activeTestHaystack = strtolower($activeTest . ' ' . ($activeTestMeta['category'] ?? '') . ' ' . ($activeTestMeta['test_name'] ?? ''));
$matchedPresetKey = 'us_whole_abd';
if (str_contains($activeTestHaystack, 'x-ray') || str_contains($activeTestHaystack, 'xray') || str_contains($activeTestHaystack, 'radiograph')) {
    if (str_contains($activeTestHaystack, 'cervical')) {
        $matchedPresetKey = 'xray_cervical';
    } elseif (str_contains($activeTestHaystack, 'ls') || str_contains($activeTestHaystack, 'l-s') || str_contains($activeTestHaystack, 'lumbar') || str_contains($activeTestHaystack, 'lumbosacral')) {
        $matchedPresetKey = 'xray_ls_spine';
    } elseif (str_contains($activeTestHaystack, 'knee')) {
        $matchedPresetKey = 'xray_knee';
    } elseif (str_contains($activeTestHaystack, 'pelvis') || str_contains($activeTestHaystack, 'hip')) {
        $matchedPresetKey = 'xray_pelvis';
    } elseif (str_contains($activeTestHaystack, 'abdomen') || str_contains($activeTestHaystack, 'erect')) {
        $matchedPresetKey = 'xray_abdomen';
    } else {
        $matchedPresetKey = 'xray_chest';
    }
} elseif (str_contains($activeTestHaystack, 'echo')) {
    $matchedPresetKey = 'echo_normal';
} elseif (str_contains($activeTestHaystack, 'ecg') || str_contains($activeTestHaystack, 'ekg') || str_contains($activeTestHaystack, 'electrocardiogra')) {
    $matchedPresetKey = 'ecg_12';
} elseif (str_contains($activeTestHaystack, 'ultrasound') || str_contains($activeTestHaystack, 'usg') || str_contains($activeTestHaystack, 'u/s') || str_contains($activeTestHaystack, 'sonograph') || str_contains($activeTestHaystack, 'doppler')) {
    if (str_contains($activeTestHaystack, 'kub') || str_contains($activeTestHaystack, 'kidney') || str_contains($activeTestHaystack, 'prostate')) {
        $matchedPresetKey = 'us_kub';
    } elseif (str_contains($activeTestHaystack, 'whole') || str_contains($activeTestHaystack, 'abdomen') || str_contains($activeTestHaystack, 'abd')) {
        $matchedPresetKey = str_contains($activeTestHaystack, 'upper') ? 'us_upper_abd' : 'us_whole_abd';
    } elseif (str_contains($activeTestHaystack, 'female') || str_contains($activeTestHaystack, 'pelvis') || str_contains($activeTestHaystack, 'gynae') || str_contains($activeTestHaystack, 'gyne')) {
        $matchedPresetKey = 'us_pelvis';
    } elseif (str_contains($activeTestHaystack, 'obs') || str_contains($activeTestHaystack, 'obstetric') || str_contains($activeTestHaystack, 'pregnan') || str_contains($activeTestHaystack, 'antenatal') || str_contains($activeTestHaystack, 'gravid')) {
        $matchedPresetKey = 'us_obstetric';
    } elseif (str_contains($activeTestHaystack, 'anomaly')) {
        $matchedPresetKey = 'us_anomaly';
    } elseif (str_contains($activeTestHaystack, 'tvs') || str_contains($activeTestHaystack, 'transvaginal')) {
        $matchedPresetKey = 'us_tvs';
    } elseif (str_contains($activeTestHaystack, 'scrot') || str_contains($activeTestHaystack, 'testic')) {
        $matchedPresetKey = 'us_scrotal';
    } elseif (str_contains($activeTestHaystack, 'thyroid') || str_contains($activeTestHaystack, 'neck')) {
        $matchedPresetKey = 'us_thyroid';
    } elseif (str_contains($activeTestHaystack, 'breast') || str_contains($activeTestHaystack, 'mammog')) {
        $matchedPresetKey = 'us_breast';
    } elseif (str_contains($activeTestHaystack, 'follicular') || str_contains($activeTestHaystack, 'folliculometry')) {
        $matchedPresetKey = 'us_follicular';
    } elseif (str_contains($activeTestHaystack, 'soft tissue') || str_contains($activeTestHaystack, 'swelling')) {
        $matchedPresetKey = 'us_soft_tissue';
    } elseif (str_contains($activeTestHaystack, 'upper abd')) {
        $matchedPresetKey = 'us_upper_abd';
    } else {
        $matchedPresetKey = 'us_whole_abd';
    }
}

$imagingRow = $activeRows[0] ?? null;
$imagingResId = (string)($imagingRow['id'] ?? 'RES-IMG-' . bin2hex(random_bytes(4)));
$currentImagingVal = (string)($imagingRow['value'] ?? '');
if ($currentImagingVal === 'Pending' || $currentImagingVal === '-' || $currentImagingVal === '—') {
    $currentImagingVal = '';
}

// Auto-populate normal report preset if entry value is currently empty
if ($isImagingActive && $currentImagingVal === '') {
    if (isset($clinicalPresets[$matchedPresetKey])) {
        $currentImagingVal = $clinicalPresets[$matchedPresetKey];
    }
}
$currentImagingValSafe = e($currentImagingVal);
$currentImagingFontSize = (!empty($imagingRow['unit']) && preg_match('/^[0-9]+(?:\.[0-9]+)?px$/', (string)$imagingRow['unit']))
    ? (string)$imagingRow['unit']
    : '14px';

// Load doctors for report signatory selection
$imagingDocs = setting_repo()->getSignatories($orgId, 'imaging');
$labDocs = setting_repo()->getSignatories($orgId, 'laboratory');

$allReportDoctors = [];
foreach ($imagingDocs as $doc) {
    $dName = trim((string)($doc['name'] ?? ''));
    if ($dName !== '') {
        $allReportDoctors[$dName] = [
            'name' => $dName,
            'qualifications' => (string)($doc['qualifications'] ?? 'M.B.B.S, F.C.P.S (Radiology)'),
            'designation' => (string)($doc['designation'] ?? 'Consultant Radiologist & Sonologist'),
            'type' => 'Radiology & Imaging',
        ];
    }
}
foreach ($labDocs as $doc) {
    $dName = trim((string)($doc['name'] ?? ''));
    if ($dName !== '' && !isset($allReportDoctors[$dName])) {
        $allReportDoctors[$dName] = [
            'name' => $dName,
            'qualifications' => (string)($doc['qualifications'] ?? 'M.B.B.S, F.C.P.S (Pathology)'),
            'designation' => (string)($doc['designation'] ?? 'Consultant Pathologist'),
            'type' => 'Laboratory Medicine',
        ];
    }
}

// Fallbacks if no signatories are configured yet
if (empty($allReportDoctors)) {
    $allReportDoctors['Dr. Imran Sheikh'] = [
        'name' => 'Dr. Imran Sheikh',
        'qualifications' => 'M.B.B.S, F.C.P.S (Radiology)',
        'designation' => 'Consultant Radiologist & Sonologist',
        'type' => 'Radiology & Imaging',
    ];
    $allReportDoctors['Dr. A. K. Khan'] = [
        'name' => 'Dr. A. K. Khan',
        'qualifications' => 'M.B.B.S, M.Phil, F.C.P.S (Pathology)',
        'designation' => 'Consultant Pathologist & Lab Director',
        'type' => 'Laboratory Medicine',
    ];
}

$curReportDocName = trim((string)($entry['reporting_doctor'] ?? ''));
if ($curReportDocName === '') {
    $curReportDocName = array_key_first($allReportDoctors) ?? 'Dr. Imran Sheikh';
} elseif (!isset($allReportDoctors[$curReportDocName])) {
    $allReportDoctors[$curReportDocName] = [
        'name' => $curReportDocName,
        'qualifications' => 'M.B.B.S, F.C.P.S (Radiology)',
        'designation' => 'Consultant Radiologist & Sonologist',
        'type' => 'Radiology & Imaging',
    ];
}
$selectedDoc = $allReportDoctors[$curReportDocName] ?? reset($allReportDoctors);
$selectedDocName = e($selectedDoc['name'] ?? $curReportDocName);

// Doctor Qualifications (prioritize saved value on entry, else fallback to doctor profile)
$curReportDocQuals = trim((string)($entry['reporting_doctor_quals'] ?? ''));
if ($curReportDocQuals === '') {
    $curReportDocQuals = (string)($selectedDoc['qualifications'] ?? 'M.B.B.S, F.C.P.S (Radiology)');
}

// Doctor Designation (prioritize saved value on entry, else fallback to doctor profile)
$curReportDocDesig = trim((string)($entry['reporting_doctor_desig'] ?? ''));
if ($curReportDocDesig === '') {
    $curReportDocDesig = (string)($selectedDoc['designation'] ?? 'Consultant Radiologist & Sonologist');
}

$curReportDocNameSafe = e($curReportDocName);
$curReportDocQualsSafe = e($curReportDocQuals);
$curReportDocDesigSafe = e($curReportDocDesig);

$docSelectOpts = '<option value="">— Choose Doctor Preset —</option>';
foreach ($allReportDoctors as $dName => $dInfo) {
    $sel = $dName === $curReportDocName ? ' selected' : '';
    $docSelectOpts .= '<option value="' . e($dName) . '"' . $sel . '>' . e($dName) . ' (' . e($dInfo['designation']) . ')</option>';
}
$docJsonMapEncoded = json_encode($allReportDoctors, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

// Page grouping state (per test)
$pageAssignments = [];
$maxPage = 4;
foreach ($resultRows as $rr) {
    $tn = trim((string)($rr['test'] ?? 'Test'));
    $pg = max(1, (int)($rr['print_page'] ?? 1));
    $pageAssignments[$tn] = $pg;
    $maxPage = max($maxPage, $pg);
}
foreach ($testNames as $tn) {
    if (!isset($pageAssignments[$tn])) {
        $pageAssignments[$tn] = 1;
    }
}

// Build department metadata for Free Page Grouping dialog
$testDeptMap = [];
$testDetailsForModal = [];
foreach ($testNames as $tn) {
    $dInfo = get_test_department_info($tn);
    $dName = $dInfo['department_name'] ?? 'Laboratory';
    $testDeptMap[$tn] = $dName;
    $testDetailsForModal[] = [
        'name' => $tn,
        'page' => (int)($pageAssignments[$tn] ?? 1),
        'department' => $dName,
    ];
}
$testDetailsJson = json_encode($testDetailsForModal, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$isCbcActive = (bool)preg_match('/\b(cbc|complete blood|haematology|hematology)\b/i', $activeTest);
$tableColspan = $isCbcActive ? 5 : 4;
$tableBody = '';
$currentSection = null;
$firstInput = true;

foreach ($activeRows as $r) {
    $sec = trim((string)($r['section'] ?? ''));
    if ($sec !== '' && $sec !== $currentSection) {
        $currentSection = $sec;
        $tableBody .= '<tr class="bg-slate-100"><td colspan="' . $tableColspan . '" class="px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-700">'
            . e($sec) . '</td></tr>';
    }

    $rId = e($r['id']);
    $paramName = e($r['parameter'] ?: ($r['test'] ?? 'Test'));
    $val = e($r['value'] ?? '');
    $unit = e($r['unit'] ?? '');
    $rangeRaw = (string)($r['reference_range'] ?: ($r['range'] ?? '—'));
    $range = e($rangeRaw);
    $flag = strtolower((string)($r['flag'] ?? ''));
    if ($flag === '' && ($r['value'] ?? '') !== '') {
        $flag = strtolower(compute_result_flag((string)$r['value'], $rangeRaw));
    }
    $shown = !isset($r['is_visible']) || (int)$r['is_visible'] === 1;
    $showChecked = $shown ? ' checked' : '';
    $rowDim = $shown ? '' : ' opacity-50';
    $autofocus = $firstInput ? ' autofocus' : '';
    $firstInput = false;

    $valClass = 'field text-sm font-bold w-28 text-center result-value-input';
    if ($isCbcActive && ($flag === 'l' || $flag === 'h' || $flag === 'critical')) {
        $valClass .= ' text-blue-700 border-blue-400';
    }

    $flagDisplay = '—';
    if ($isCbcActive) {
        $hasRange = preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*[\-–]\s*([0-9]+(?:\.[0-9]+)?)$/', $rangeRaw)
            || preg_match('/^[<>]\s*([0-9]+(?:\.[0-9]+)?)$/', $rangeRaw);
        if ($flag === 'l') {
            $flagDisplay = '<span class="font-bold" style="color:#2563eb" title="Low">↓ Low</span>';
        } elseif ($flag === 'h') {
            $flagDisplay = '<span class="font-bold" style="color:#dc2626" title="High">↑ High</span>';
        } elseif ($flag === 'critical') {
            $flagDisplay = '<span class="font-bold" style="color:#dc2626" title="Critical">! Critical</span>';
        } elseif ($hasRange && ($r['value'] ?? '') !== '' && is_numeric((string)$r['value'])) {
            $flagDisplay = '<span class="font-bold" style="color:#16a34a" title="Normal">✓ Normal</span>';
        }
    }

    $subValRaw = (string)($r['sub_table'] ?? '');
    $subHtml = criteria_table_builder_field('results[' . $rId . '][sub_table]', $subValRaw);
    $resultNote = e($r['result_note'] ?? '');

    $rowOptions = null;
    if ($testResultOptions !== null && !empty($testResultOptions)) {
        // High priority: Test Catalog configured options!
        $rowOptions = $testResultOptions;
    } elseif ($rangeRaw !== '' && $rangeRaw !== '—') {
        $rowOptions = parse_result_options($rangeRaw);
    }

    // Auto-detect qualitative dropdown options for serology, screening, and rapid tests
    $combinedName = strtolower($paramName . ' ' . $activeTest . ' ' . $rangeRaw);
    $activeCat = strtolower((string)($activeTestMeta['category'] ?? ''));

    $isCbcInvestigation = (bool)preg_match('/\b(cbc|complete blood|haematology|hematology)\b/i', $activeTest);
    $hasNumericRange = preg_match('/^[0-9]/', $rangeRaw) || preg_match('/[0-9]+\s*[\-–]\s*[0-9]+/', $rangeRaw);

    // Screening/Serology investigations (HIV, HBsAg, HCV, VDRL, H. Pylori, Malaria / MP) must always be Negative / Positive
    $isScreeningInvestigation = !$isCbcInvestigation && !$hasNumericRange && (
        preg_match('/hiv|hbsag|hbs\s*ag|hbeag|hbe\s*ag|hcv|vdrl|tpha|pylori|malaria|\bmp\b|screening/i', $combinedName)
        || in_array($activeCat, ['serology', 'immunology', 'rapid tests'], true)
    );

    // Numeric investigations: CBC parameters, anything with numeric range, or explicit numeric tests
    $isExplicitlyNumeric = $isCbcInvestigation || $hasNumericRange || (
        !$isScreeningInvestigation && (
            ($testResultType === 'numeric')
            || preg_match('/hba1c|glycated|\ba1c\b|sugar|glucose|cholesterol|triglyceride|urea|creatinine|uric|bilirubin|tsh|ft3|ft4|vitamin|ferritin|calcium/i', $combinedName)
        )
    );

    $isQualitative = !$isExplicitlyNumeric && (
        $isScreeningInvestigation
        || preg_match('/non\s*reactive|reactive|negative|positive|detected|non\s*detected|not\s*detected/i', $rangeRaw)
        || preg_match('/widal|typhidot|pregnancy|ict|rapid|covid|antigen|antibody|dengue|syphilis|chlamydia/i', $combinedName)
    );

    if ($isExplicitlyNumeric) {
        $rowOptions = null;
    } elseif ($rowOptions === null && $isScreeningInvestigation) {
        $rowOptions = ['Negative', 'Positive'];
    } elseif ($rowOptions === null && $isQualitative) {
        $rowOptions = ['Negative', 'Positive'];
    } elseif ($rowOptions === null && preg_match('/blood\s*group|rh\s*factor/i', $combinedName)) {
        $rowOptions = ['A Positive (A+)', 'B Positive (B+)', 'O Positive (O+)', 'AB Positive (AB+)', 'A Negative (A-)', 'B Negative (B-)', 'O Negative (O-)', 'AB Negative (AB-)'];
    }

    if ($rowOptions !== null && $isScreeningInvestigation) {
        $rowOptions = ['Negative', 'Positive'];
    } elseif ($rowOptions !== null && $isQualitative) {
        $qualitativeStandard = ['Negative', 'Trace', '1+ (OnePlus)', '2+ (TwoPlus)', '3+ (ThreePlus)', '4+ (FourPlus)', 'OnePlus', 'Positive', 'Non Detected', 'Detected', 'Nil'];
        foreach ($qualitativeStandard as $qs) {
            $exists = false;
            foreach ($rowOptions as $ro) {
                if (strcasecmp($ro, $qs) === 0) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $rowOptions[] = $qs;
            }
        }
    }

    if ($rowOptions !== null) {
        // Also add OnePlus, 1+, 2+, 3+, Trace, Nil for urine, chemical examinations
        if (!$isScreeningInvestigation && (preg_match('/protein|glucose|sugar|urine|urinalysis|chemical|albumin|ketone|bile|blood|leukocyte|nitrite|urobilinogen/i', $combinedName))) {
            $urineStandard = ['Negative', 'Trace', '1+ (OnePlus)', '2+ (TwoPlus)', '3+ (ThreePlus)', '4+ (FourPlus)', 'OnePlus', 'Positive', 'Nil'];
            foreach ($urineStandard as $us) {
                $exists = false;
                foreach ($rowOptions as $ro) {
                    if (strcasecmp($ro, $us) === 0) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $rowOptions[] = $us;
                }
            }
        }

        $curVal = trim((string)($r['value'] ?? ''));
        if ($curVal === '' || strcasecmp($curVal, 'pending') === 0) {
            $prefNormal = trim((string)($activeTestMeta['normal_value'] ?: ($activeTestMeta['reference_value'] ?: ($activeTestMeta['normal_range'] ?? ''))));
            $foundPref = false;
            if ($prefNormal !== '' && $prefNormal !== '—') {
                foreach ($rowOptions as $opt) {
                    if (strcasecmp($opt, $prefNormal) === 0) {
                        $curVal = $opt;
                        $foundPref = true;
                        break;
                    }
                }
            }
            if (!$foundPref) {
                if (in_array('Negative', $rowOptions, true)) {
                    $curVal = 'Negative';
                } elseif (in_array('Non Reactive', $rowOptions, true)) {
                    $curVal = 'Non Reactive';
                } elseif ($rangeRaw !== '—' && $rangeRaw !== '') {
                    $curVal = $rangeRaw;
                }
            }
        }
        $optHtml = '<option value="">— Select Result —</option>';
        $matched = false;
        foreach ($rowOptions as $opt) {
            $sel = strcasecmp($curVal, $opt) === 0 ? ' selected' : '';
            if ($sel !== '') {
                $matched = true;
            }
            $optHtml .= '<option value="' . e($opt) . '"' . $sel . '>' . e($opt) . '</option>';
        }
        // Preserve custom values like 'DONE', etc. so they are selectable and never lost
        if ($curVal !== '' && !$matched) {
            $optHtml .= '<option value="' . e($curVal) . '" selected>' . e($curVal) . '</option>';
        }
        $valToDisplay = $curVal !== '' ? $curVal : ($val !== '' ? $val : '');
        $valueControl = '<div class="flex items-center gap-1.5 justify-end">'
            . '<input type="text" name="results[' . $rId . '][value]" value="' . e($valToDisplay) . '" class="' . $valClass . ' flex-1 min-w-[70px] text-center font-bold text-slate-800 bg-white border-2 border-indigo-200 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-100 rounded-xl py-1.5 px-2 shadow-xs" placeholder="Result / custom" data-result-input data-ref-range="' . $range . '"' . $autofocus . '>'
            . '<select class="field text-xs font-semibold py-1.5 px-2 bg-slate-50 hover:bg-slate-100 border border-slate-300 rounded-xl max-w-[135px] shrink-0 text-slate-700 cursor-pointer shadow-2xs focus:ring-1 focus:ring-indigo-400" onchange="if(this.value){ const inp = this.parentElement.querySelector(\'input.result-value-input\'); if(inp){ inp.value = this.value; inp.focus(); inp.dispatchEvent(new Event(\'input\', {bubbles:true})); inp.dispatchEvent(new Event(\'change\', {bubbles:true})); } }" title="Select preset option or type custom value in field">'
            . $optHtml . '</select>'
            . '</div>';
    } else {
        $valueControl = '<input type="text" name="results[' . $rId . '][value]" value="' . $val . '" class="' . $valClass . ' w-full" placeholder="—" data-result-input data-ref-range="' . $range . '"' . $autofocus . '>';
    }

    $flagTd = '';
    if ($isCbcActive) {
        $flagTd = "\n        <td class=\"px-2 py-1.5 align-top text-sm text-center\" data-flag-display>{$flagDisplay}</td>";
    }

    $tableBody .= <<<HTML
    <tr class="border-b border-slate-100{$rowDim}" data-result-row>
        <td class="px-2.5 py-1.5 align-top">
            <label class="inline-flex items-start gap-2 cursor-pointer">
                <input type="checkbox" name="results[{$rId}][show]" value="1" class="mt-1"{$showChecked}>
                <span class="w-full">
                    <span class="block text-[10px] uppercase text-slate-400 font-semibold">Show</span>
                    <span class="font-semibold text-slate-900 text-sm results-entry-param">{$paramName}</span>
                    {$subHtml}
                </span>
            </label>
            <input type="hidden" name="results[{$rId}][flag]" value="{$flag}" data-auto-flag>
        </td>
        <td class="px-2 py-1.5 text-sm align-top text-right">
            <input type="text" name="results[{$rId}][range]" value="{$range}" class="field text-xs w-32 py-1 px-2 font-mono text-right" placeholder="—" title="Reference range / number (e.g. 12-16)" data-ref-range-input>
        </td>
        <td class="px-2 py-1.5 text-sm align-top text-right">
            <input type="text" name="results[{$rId}][unit]" value="{$unit}" class="field text-xs w-20 py-1 px-2 text-right" placeholder="—" title="Unit of measurement (e.g. g/dL, mg/dL)">
        </td>
        <td class="px-2 py-1.5 align-top min-w-[13rem] text-right">
            {$valueControl}
            <div class="mt-1">
                <textarea name="results[{$rId}][result_note]" rows="2" class="field text-xs w-full py-1 px-2 text-slate-700 placeholder:text-slate-400 text-right" placeholder="Note / sub-result" title="Extra finding or note shown directly below result on report">{$resultNote}</textarea>
            </div>
        </td>{$flagTd}
    </tr>
    HTML;
}

if ($tableBody === '') {
    $tableBody = '<tr><td colspan="' . $tableColspan . '" class="px-4 py-8 text-center text-slate-500">No parameters for this test yet. Add the test from the catalog, or pick another test on the left.</td></tr>';
}

$pageBoard = '';

$prevBtn = $prevTest
    ? '<button type="submit" name="jump_to_test" value="' . e($prevTest) . '" class="btn btn-secondary text-xs flex-1 cursor-pointer">← Previous</button>'
    : '<span class="btn btn-secondary text-xs flex-1 opacity-40 pointer-events-none">← Previous</span>';
$nextBtn = $nextTest
    ? '<button type="submit" name="jump_to_test" value="' . e($nextTest) . '" class="btn btn-secondary text-xs flex-1 cursor-pointer">Next →</button>'
    : '<span class="btn btn-secondary text-xs flex-1 opacity-40 pointer-events-none">Next →</span>';

$activeTestLabel = e($activeTest);
$activeTestIdSafe = e($activeTestId);
$activeTestMethodologySafe = e($activeTestMethodology);
$posLabel = $testCount > 0 ? (($testIndex + 1) . ' of ' . $testCount) : '0 of 0';
$baseUrlJs = e($baseUrl);

$bSettings = branding_settings();
$reportFontKey = $bSettings['report_font'] ?? 'times_bold_italic';
$allFonts = supported_document_fonts();
$fontConf = $allFonts[$reportFontKey] ?? $allFonts['times_bold_italic'];
$entryFontFamily = $fontConf['family'];
$entryFontWeight = $fontConf['weight'];
$entryFontStyle = $fontConf['style'];
$entryStyleAttr = htmlspecialchars("--report-body-font: {$entryFontFamily}; --report-body-weight: {$entryFontWeight}; --report-body-style: {$entryFontStyle};", ENT_COMPAT, 'UTF-8');
$isCbcAttr = $isCbcActive ? '1' : '0';
$flagTh = $isCbcActive ? '<th class="px-2.5 py-2.5 text-center" style="width: 5%;">Flag</th>' : '';
$paramThWidth = $isCbcActive ? '37%' : '40%';
$rangeThWidth = $isCbcActive ? '18%' : '20%';

$isReportLocked = !empty($entry['is_report_locked']);
$isAdmin = is_admin_user();
$reportLockedBy = e((string)($entry['report_locked_by'] ?? 'Staff'));
$reportLockedAt = !empty($entry['report_locked_at']) ? date('d M Y, h:i A', strtotime($entry['report_locked_at'])) : '';

if (isset($_GET['report_locked'])) {
    $message = flash_success('Diagnostic report is now locked. Employees cannot modify the test results.');
}
if (isset($_GET['report_unlocked'])) {
    $message = flash_success('Diagnostic report has been unlocked by Admin.');
}

$patientFinishedBanner = '';
if (!empty($_GET['patient_finished'])) {
    $patientFinishedBanner = <<<HTML
    <div class="mb-5 bg-gradient-to-r from-emerald-600 via-teal-700 to-slate-900 text-white rounded-3xl p-4 sm:p-5 shadow-lg border border-emerald-400/30 flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-white/20 text-emerald-200 flex items-center justify-center text-2xl flex-shrink-0 shadow-inner">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <div class="text-xs font-black uppercase tracking-wider text-emerald-200 flex items-center gap-2">
                    <span>Patient Results Completed &amp; Saved</span>
                    <span class="bg-emerald-500/30 text-white px-2 py-0.5 rounded-full text-[10px]">Saved</span>
                </div>
                <div class="text-sm font-bold text-white mt-0.5">
                    All results for <strong>{$patientName}</strong> (Lab No: <code class="bg-black/30 px-2 py-0.5 rounded text-emerald-300 font-mono">{$labNoSafe}</code>) have been saved successfully.
                </div>
                <div class="text-[11px] text-emerald-100/80 mt-0.5">
                    Screen remains on this patient to avoid accidental entries. When ready, click below or search the next patient.
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap flex-shrink-0">
            <a href="{$previewLink}" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-white text-emerald-950 hover:bg-emerald-50 font-bold text-xs shadow-md transition-all">
                <i class="fa-solid fa-file-medical text-emerald-700"></i> View / Print Report &rarr;
            </a>
            <button type="button" onclick="focusPatientSearch()" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-slate-900/90 hover:bg-black text-white font-bold text-xs border border-white/20 shadow-md transition-all cursor-pointer">
                <i class="fa-solid fa-magnifying-glass text-[#c2f13c]"></i> Search Next Patient
            </button>
        </div>
    </div>
HTML;
}

$reportLockBannerHtml = '';
if ($isReportLocked) {
    $unlockActionHtml = $isAdmin
        ? '<button type="submit" name="toggle_report_lock" value="unlock" form="results-workspace" class="btn btn-secondary text-xs font-bold text-rose-800 bg-white border border-rose-300 hover:bg-rose-100 shadow-2xs py-2 px-3.5"><i class="fa-solid fa-lock-open mr-1.5 text-rose-600"></i> Unlock Report (Admin)</button>'
        : '<span class="inline-flex items-center gap-1.5 text-xs font-bold text-rose-800 bg-white px-3 py-2 rounded-xl border border-rose-200 shadow-2xs"><i class="fa-solid fa-shield-halved text-rose-600"></i> Locked (Admin Only)</span>';

    $lockSubtext = $isAdmin
        ? 'Locked on ' . $reportLockedAt . ' by <strong>' . $reportLockedBy . '</strong>. You are logged in as Admin — you can edit values or unlock.'
        : 'Reviewed and locked on ' . $reportLockedAt . ' by <strong>' . $reportLockedBy . '</strong>. To ensure medical report integrity, staff editing is disabled. Only an Admin account can unlock or edit.';

    $reportLockBannerHtml = <<<HTML
    <div class="mb-5 p-4 bg-rose-50 border-2 border-rose-300 rounded-3xl flex flex-wrap items-center justify-between gap-3 text-rose-900 shadow-card">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-2xl bg-rose-600 text-white flex items-center justify-center font-bold text-base shadow-sm">
                <i class="fa-solid fa-lock"></i>
            </span>
            <div>
                <div class="text-sm font-extrabold text-rose-950 flex items-center gap-1.5">
                    DIAGNOSTIC REPORT LOCKED <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full bg-rose-200 text-rose-900">Protected</span>
                </div>
                <div class="text-xs text-rose-800">{$lockSubtext}</div>
            </div>
        </div>
        <div>{$unlockActionHtml}</div>
    </div>
HTML;
}

$sidebarButtonsHtml = '';
if ($isReportLocked && !$isAdmin) {
    $sidebarButtonsHtml = <<<HTML
    <div class="p-3 bg-rose-50 border border-rose-200 rounded-2xl text-center space-y-1">
        <i class="fa-solid fa-lock text-rose-600 text-base"></i>
        <div class="text-xs font-bold text-rose-900">Report is Locked</div>
        <p class="text-[11px] text-rose-700">Only an Admin ID can unlock or edit results.</p>
    </div>
    <a href="{$previewLink}" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm shadow-md transition-all"><i class="fa-solid fa-file-medical"></i> View Report Preview &rarr;</a>
    <a href="/portals/archive.php" class="w-full flex items-center justify-center gap-2 px-4 py-1.5 rounded-xl text-slate-500 hover:text-slate-800 font-bold text-xs transition-all">Back to History</a>
HTML;
} else {
    $unlockBtnSidebar = ($isReportLocked && $isAdmin)
        ? '<button type="submit" name="toggle_report_lock" value="unlock" class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-white hover:bg-rose-50 text-rose-700 font-bold text-xs border border-rose-300 shadow-2xs transition-all"><i class="fa-solid fa-lock-open mr-1 text-rose-600"></i> Unlock Report (Admin)</button>'
        : '<button type="submit" name="toggle_report_lock" value="lock" onclick="return confirm(\'Lock this report? Once locked, test results can only be unlocked or edited from an Admin ID.\');" class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-900 font-bold text-xs border border-amber-300 shadow-2xs transition-all" title="Lock report once reviewed to prevent any modifications by employees"><i class="fa-solid fa-lock mr-1 text-amber-600"></i> Lock Report</button>';

    $sidebarButtonsHtml = <<<HTML
    <button type="submit" name="save_and_next" value="1" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs sm:text-sm shadow-md transition-all">{$saveAndNextBtnHtml}</button>
    <button type="submit" name="goto_preview" value="1" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm shadow-md transition-all"><i class="fa-solid fa-file-medical"></i> Save &amp; View Report &rarr;</button>
    <button type="submit" name="send_verify" value="1" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs sm:text-sm shadow-sm transition-all"><i class="fa-solid fa-circle-check text-[#c2f13c]"></i> Save &amp; Verify</button>
    <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs border border-slate-300 shadow-2xs transition-all"><i class="fa-solid fa-floppy-disk text-slate-400"></i> Save as Draft</button>
    {$unlockBtnSidebar}
    <a href="{$addTestLink}" class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs border border-slate-200 transition-all"><i class="fa-solid fa-plus"></i> Add / Change Tests</a>
    <a href="/portals/archive.php" class="w-full flex items-center justify-center gap-2 px-4 py-1.5 rounded-xl text-slate-500 hover:text-slate-800 font-bold text-xs transition-all">Back to History</a>
HTML;
}

$headerLockAction = '';
if ($isReportLocked && $isAdmin) {
    $headerLockAction = '<button type="submit" form="results-workspace" name="toggle_report_lock" value="unlock" class="btn btn-secondary text-xs font-bold text-rose-700 bg-rose-50 border-rose-300"><i class="fa-solid fa-lock-open mr-1"></i> Unlock Report</button>';
} elseif (!$isReportLocked) {
    $headerLockAction = '<button type="submit" form="results-workspace" name="toggle_report_lock" value="lock" onclick="return confirm(\'Lock this report? Once locked, test results can only be unlocked or edited from an Admin ID.\');" class="btn btn-secondary text-xs font-bold text-amber-800 bg-amber-50 border-amber-300" title="Lock report once reviewed"><i class="fa-solid fa-lock mr-1 text-amber-600"></i> Lock Report</button>';
}

$content = workflow_stepper('results', $labNo, $entry['patient_id'] ?? null);
$content .= page_header(
    'Enter Results',
    '',
    '<div class="flex items-center gap-2">' .
    $headerLockAction .
    '<button type="submit" form="results-workspace" name="goto_preview" value="1" class="btn btn-secondary text-xs"><i class="fa-solid fa-file-medical mr-1"></i> Save &amp; Preview Report &rarr;</button>' .
    '</div>'
);
$content .= $message;
$content .= $patientFinishedBanner;
$content .= $reportLockBannerHtml;

$content .= <<<HTML
<style>
.results-entry-table,
.results-entry-table th,
.results-entry-table td,
.results-entry-table input,
.results-entry-table select,
.results-entry-table textarea {
    font-family: {$entryFontFamily} !important;
}
.results-entry-table td,
.results-entry-table .results-entry-param,
.results-entry-table input {
    font-weight: {$entryFontWeight} !important;
    font-style: {$entryFontStyle} !important;
}
</style>
<form method="post" id="results-workspace" data-results-entry data-is-cbc="{$isCbcAttr}" style="{$entryStyleAttr}">
<input type="hidden" name="lab_no" value="{$labNoSafe}">
<input type="hidden" name="active_test" value="{$activeTestLabel}">
<input type="hidden" name="jump_to_test" id="jump_to_test_input" value="">

<div class="mb-5 bg-white border border-slate-200/90 rounded-3xl p-4 sm:p-5 shadow-card">
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-3">
        <!-- Live Real-Time Google-Style Patient Search -->
        <div class="relative flex-1" id="patient-search-container">
            <div class="relative flex items-center">
                <i class="fa-solid fa-magnifying-glass absolute left-4 text-slate-400 text-sm pointer-events-none"></i>
                <input 
                    type="text" 
                    id="patient-search-input" 
                    class="w-full pl-11 pr-24 py-2.5 text-sm rounded-2xl border border-slate-200 focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10 transition-all font-semibold placeholder:text-slate-400 outline-none bg-slate-50/50 focus:bg-white" 
                    placeholder="Search patient by Lab No (e.g. 1004), MR No (e.g. MR0747), or Name…" 
                    autocomplete="off"
                    spellcheck="false"
                >
                <div class="absolute right-3 flex items-center gap-1.5">
                    <button type="button" id="patient-search-clear" class="hidden text-slate-400 hover:text-slate-600 p-1 text-xs" title="Clear search">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <span class="hidden sm:inline-block px-2 py-0.5 text-[10px] font-mono font-bold text-slate-400 bg-slate-100 border border-slate-200 rounded-lg">Press /</span>
                </div>
            </div>

            <!-- Instant Autocomplete Dropdown List -->
            <div 
                id="patient-search-dropdown" 
                class="hidden absolute left-0 right-0 top-full mt-2 bg-white border border-slate-200 rounded-2xl shadow-2xl z-50 overflow-hidden max-h-[420px] overflow-y-auto"
            >
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider flex justify-between items-center">
                    <span id="patient-search-header">Recent Patient Visits</span>
                    <span class="text-[10px] text-slate-400 font-normal">Use ↑ ↓ arrows &amp; Enter to jump</span>
                </div>
                <div id="patient-search-list" class="divide-y divide-slate-100">
                    <!-- Dynamic live cards -->
                </div>
            </div>
        </div>

        <!-- Quick Switcher Dropdown -->
        <div class="flex items-center gap-2 text-xs shrink-0 flex-wrap">
            <span id="autosave-status" class="inline-flex items-center text-[11px] font-semibold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-xl border border-slate-200">
                <i class="fa-solid fa-cloud text-slate-400 mr-1.5"></i> Auto-save ready
            </span>
            <span class="text-slate-400 hidden xl:inline font-bold">or visit:</span>
            <select class="field text-xs py-2 max-w-[200px] text-slate-700 font-semibold" onchange="if(this.value) location.href='/portals/results/entry.php?lab_no='+encodeURIComponent(this.value)">
                {$switcherOpts}
            </select>
        </div>
    </div>
</div>

<div class="grid gap-5 lg:grid-cols-[300px_minmax(0,1fr)] items-start">
    <aside class="space-y-4 lg:sticky lg:top-4">
        <div class="bg-white border border-slate-200/90 rounded-3xl p-5 shadow-card space-y-2.5">
            {$sidebarButtonsHtml}
        </div>

        <div class="bg-white border border-slate-200/90 rounded-3xl p-5 shadow-card space-y-3">
            <div>
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Current test</div>
                <div class="font-extrabold text-slate-900 text-base leading-snug mt-1">{$activeTestLabel}</div>
                <div class="text-xs text-slate-500 font-medium mt-1">{$posLabel} on this visit</div>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-700 block mb-1">Jump to test</label>
                <select class="field text-xs w-full font-semibold" onchange="if(this.value){ document.getElementById('jump_to_test_input').value=this.value; this.form.submit(); }">
                    {$testSelectOpts}
                </select>
            </div>
            <div class="flex gap-2 pt-1">{$prevBtn}{$nextBtn}</div>
            <p class="text-[11px] text-slate-500 leading-relaxed font-medium">Each booked test has its own parameter sheet. Flags auto-calculate from reference ranges. Press Enter to move down.</p>
        </div>

        <div class="bg-white border border-slate-200/90 rounded-3xl p-5 shadow-card text-xs text-slate-600 space-y-1.5">
            <div><span class="font-bold text-slate-800">Status:</span> {$status}</div>
            <div><span class="font-bold text-slate-800">Doctor:</span> {$doctor}</div>
            <div><span class="font-bold text-slate-800">All tests:</span> {$testsOrdered}</div>
        </div>
    </aside>
HTML;

    $testContentHtml = '';
    if ($isImagingActive) {
        $testContentHtml = <<<HTML
        <!-- Dedicated Radiology & Cardiology Diagnostic Imaging Section -->
        <div class="px-5 py-3.5 bg-gradient-to-r from-teal-50 via-cyan-50 to-indigo-50 border-b border-teal-200">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-teal-600 text-white shadow-sm">
                        <i class="fa-solid fa-feather-pointed text-sm"></i>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <strong class="text-teal-950 text-base font-extrabold tracking-wide">{$activeTestLabel}</strong>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-900 border border-emerald-300 flex items-center gap-1">
                                <i class="fa-solid fa-check text-emerald-600"></i> Normal Report Loaded
                            </span>
                        </div>
                        <p class="text-xs text-teal-800 font-medium mt-0.5">
                            Standard normal clinical report is already loaded below. Edit findings directly, or switch template from the dropdown.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1.5 bg-white border border-teal-300 rounded-xl px-2.5 py-1.5 shadow-2xs">
                        <label for="us-report-type-select" class="text-[11px] font-bold text-teal-900 uppercase tracking-wide">Template:</label>
                        <select id="us-report-type-select" onchange="if(this.value) applyUltrasoundReportType(this.value)" class="text-xs font-semibold text-slate-800 bg-transparent border-none focus:ring-0 py-0 pl-1 pr-6 cursor-pointer">
                            <option value="">— Switch / Choose Template —</option>
                            {$customTemplatesOptGroup}
                            <optgroup label="🩻 Digital X-Ray (Radiography)">
                                <option value="xray_chest">Chest X-Ray PA View (Normal)</option>
                                <option value="xray_chest_abnormal">Chest X-Ray (Infiltration / Consolidation)</option>
                                <option value="xray_cervical">Cervical Spine X-Ray (AP &amp; Lateral)</option>
                                <option value="xray_ls_spine">Lumbosacral (L-S) Spine X-Ray (AP &amp; Lateral)</option>
                                <option value="xray_knee">Knee Joint X-Ray (AP &amp; Lateral)</option>
                                <option value="xray_pelvis">Pelvis AP View X-Ray</option>
                                <option value="xray_abdomen">Abdomen Plain / Erect &amp; Supine (KUB)</option>
                            </optgroup>
                            <optgroup label="🩺 Ultrasound &amp; Color Doppler">
                                <option value="us_whole_abd">Ultrasound Whole Abdomen &amp; Pelvis</option>
                                <option value="us_upper_abd">Ultrasound Upper Abdomen (Hepatobiliary)</option>
                                <option value="us_kub">Ultrasound KUB &amp; Prostate</option>
                                <option value="us_pelvis">Ultrasound Pelvis (Female / Gynaecological)</option>
                                <option value="us_obstetric">Ultrasound Obstetric / Antenatal (Pregnancy)</option>
                                <option value="us_anomaly">Ultrasound 2nd Trimester Fetal Anomaly Scan</option>
                                <option value="us_tvs">Ultrasound TVS (Transvaginal Sonography)</option>
                                <option value="us_scrotal">Ultrasound Scrotum &amp; Testicular Color Doppler</option>
                                <option value="us_thyroid">Ultrasound Thyroid &amp; Neck</option>
                                <option value="us_breast">Ultrasound Breast (Bilateral / Sonomammography)</option>
                                <option value="us_follicular">Ultrasound Follicular Tracking / Study</option>
                                <option value="us_soft_tissue">Ultrasound Soft Tissue Swelling</option>
                            </optgroup>
                            <optgroup label="💓 12-Lead ECG (Electrocardiogram)">
                                <option value="ecg_12">12-Lead ECG (Normal Sinus Rhythm - 72 bpm)</option>
                                <option value="ecg_tachy">12-Lead ECG (Sinus Tachycardia - 115 bpm)</option>
                                <option value="ecg_ischemia">12-Lead ECG (Ischemic ST-T Changes / Inversion)</option>
                                <option value="ecg_lvh">12-Lead ECG (Left Ventricular Hypertrophy - LVH)</option>
                            </optgroup>
                            <optgroup label="🫀 2D Echocardiography &amp; Color Doppler (ECHO)">
                                <option value="echo_normal">2D Echocardiogram (Normal Study - EF 62%)</option>
                                <option value="echo_lvh">2D Echocardiogram (Concentric LVH &amp; Diastolic Dysfunction)</option>
                                <option value="echo_rwma">2D Echocardiogram (Ischemic Heart Disease / RWMA / EF 40%)</option>
                            </optgroup>
                        </select>
                    </div>

                    <button type="button" onclick="openSaveTemplateModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs shadow-xs transition-all cursor-pointer" title="Save current report text as a new template for future use">
                        <i class="fa-solid fa-folder-plus"></i> + Save as New Template
                    </button>
                    <a href="/portals/imaging/templates.php" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white hover:bg-teal-50 text-teal-800 font-bold text-xs border border-teal-300 shadow-2xs" title="Open Radiology Report Templates Library to add or edit standard formats">
                        <i class="fa-solid fa-folder-open text-teal-600"></i> Manage Templates
                    </a>
                    <button type="button" onclick="clearImagingBox()" class="px-2.5 py-1.5 rounded-xl bg-white hover:bg-rose-50 text-slate-500 hover:text-rose-700 border border-slate-200 text-xs font-semibold transition-colors" title="Clear current findings text">
                        <i class="fa-solid fa-eraser mr-1"></i> Clear
                    </button>
                </div>
            </div>
        </div>

        <div class="p-5 bg-white space-y-3">
            <input type="hidden" name="results[{$imagingResId}][id]" value="{$imagingResId}">
            <input type="hidden" name="results[{$imagingResId}][show]" value="1">
            <input type="hidden" id="imaging-narrative-fontsize" name="results[{$imagingResId}][unit]" value="{$currentImagingFontSize}">
            <input type="hidden" name="results[{$imagingResId}][range]" value="">
            <input type="hidden" name="results[{$imagingResId}][flag]" value="">

            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 px-1">
                <span class="font-bold text-slate-700 flex items-center gap-1.5">
                    <i class="fa-solid fa-feather text-teal-600"></i> Report Body &amp; Clinical Findings (Word / Markdown Mode):
                </span>
                <div class="flex items-center gap-2">
                    <span id="active-study-indicator" class="text-xs font-semibold text-teal-700 bg-teal-50 px-2 py-0.5 rounded border border-teal-200">
                        Ultrasound Narrative Mode
                    </span>
                </div>
            </div>

            <!-- Word-style Ribbon Toolbar Card -->
            <div class="border-2 border-teal-300 rounded-2xl overflow-hidden shadow-inner bg-slate-50">
                <!-- Toolbar Row -->
                <div class="bg-gradient-to-r from-slate-100 via-slate-50 to-teal-50/50 p-2.5 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Mode Tabs: Editor vs Live Print Preview -->
                        <div class="inline-flex rounded-xl bg-slate-200/80 p-0.5 shadow-2xs">
                            <button type="button" id="narrative-tab-editor" onclick="switchNarrativeView('editor')" class="px-3 py-1 text-xs font-bold rounded-lg bg-white text-slate-800 shadow-2xs cursor-pointer">
                                <i class="fa-solid fa-pen-to-square text-teal-600 mr-1.5"></i> Editor
                            </button>
                            <button type="button" id="narrative-tab-preview" onclick="switchNarrativeView('preview')" class="px-3 py-1 text-xs font-bold rounded-lg text-slate-600 hover:text-slate-900 cursor-pointer">
                                <i class="fa-solid fa-eye text-indigo-600 mr-1.5"></i> Live Print Preview
                            </button>
                        </div>

                        <!-- Actions Strip (Editor mode only) -->
                        <div id="narrative-toolbar-actions" class="flex flex-wrap items-center gap-1.5 transition-opacity">
                            <!-- Text Formatting: Bold, Italic, Underline -->
                            <div class="inline-flex rounded-lg border border-slate-300 bg-white p-0.5 shadow-2xs">
                                <button type="button" onclick="formatNarrativeText('bold')" class="px-2.5 py-1 text-xs font-black text-slate-800 hover:bg-teal-50 hover:text-teal-900 rounded cursor-pointer" title="Bold (Ctrl+B)">
                                    <strong style="font-weight:900;">B</strong>
                                </button>
                                <button type="button" onclick="formatNarrativeText('italic')" class="px-2.5 py-1 text-xs italic font-serif text-slate-700 hover:bg-teal-50 hover:text-teal-900 rounded border-x border-slate-200 cursor-pointer" title="Italic (Ctrl+I)">
                                    <em>I</em>
                                </button>
                                <button type="button" onclick="formatNarrativeText('underline')" class="px-2.5 py-1 text-xs underline font-bold text-slate-700 hover:bg-teal-50 hover:text-teal-900 rounded cursor-pointer" title="Underline (Ctrl+U)">
                                    <u>U</u>
                                </button>
                            </div>

                            <!-- Headings & Sizing -->
                            <div class="inline-flex rounded-lg border border-slate-300 bg-white p-0.5 shadow-2xs">
                                <button type="button" onclick="formatNarrativeText('h1')" class="px-2 py-1 text-[11px] font-black text-slate-800 hover:bg-slate-100 rounded cursor-pointer" title="Large Heading / Title (# Title)">
                                    H1
                                </button>
                                <button type="button" onclick="formatNarrativeText('h2')" class="px-2 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-100 rounded border-x border-slate-200 cursor-pointer" title="Section Subheading (## Section)">
                                    H2
                                </button>
                                <button type="button" onclick="formatNarrativeText('h3')" class="px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-100 rounded cursor-pointer" title="Subsection (### Title)">
                                    H3
                                </button>
                            </div>

                            <!-- Bullet / Numbered Lists & Empty Spacer -->
                            <div class="inline-flex rounded-lg border border-slate-300 bg-white p-0.5 shadow-2xs">
                                <button type="button" onclick="formatNarrativeText('bullet')" class="px-2.5 py-1 text-xs text-slate-700 hover:bg-slate-100 rounded cursor-pointer" title="Bullet List (- item)">
                                    <i class="fa-solid fa-list-ul"></i>
                                </button>
                                <button type="button" onclick="formatNarrativeText('number')" class="px-2.5 py-1 text-xs text-slate-700 hover:bg-slate-100 rounded border-x border-slate-200 cursor-pointer" title="Numbered List (1. item)">
                                    <i class="fa-solid fa-list-ol"></i>
                                </button>
                                <button type="button" onclick="formatNarrativeText('spacer')" class="px-2.5 py-1 text-[11px] font-bold text-teal-800 hover:bg-teal-50 rounded cursor-pointer" title="Add Paragraph / Blank Line Spacer">
                                    <i class="fa-solid fa-arrows-up-down text-[10px] mr-1"></i> Empty Line
                                </button>
                            </div>

                            <!-- Clinical Section Quick Badges -->
                            <div class="inline-flex rounded-lg border border-teal-200 bg-teal-50/70 p-0.5 shadow-2xs">
                                <button type="button" onclick="insertMedicalSection('FINDINGS')" class="px-2 py-0.5 text-[11px] font-bold text-teal-800 hover:bg-teal-100 rounded cursor-pointer" title="Add FINDINGS Header">
                                    + Findings
                                </button>
                                <button type="button" onclick="insertMedicalSection('IMPRESSION')" class="px-2 py-0.5 text-[11px] font-bold text-rose-800 hover:bg-rose-100 rounded border-l border-teal-200 cursor-pointer" title="Add IMPRESSION Header">
                                    + Impression
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Print Font Size -->
                    <div class="flex items-center gap-1.5 text-xs">
                        <span class="text-[10px] font-bold uppercase text-slate-500">Report Print Font:</span>
                        <div class="inline-flex rounded-lg border border-slate-300 bg-white p-0.5 shadow-2xs">
                            <button type="button" data-sz="13px" onclick="setNarrativeFontSize('13px')" class="sz-btn px-2 py-0.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-100 rounded cursor-pointer">Small (13px)</button>
                            <button type="button" data-sz="14px" onclick="setNarrativeFontSize('14px')" class="sz-btn px-2 py-0.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-100 rounded border-x border-slate-200 cursor-pointer">Standard (14px)</button>
                            <button type="button" data-sz="16px" onclick="setNarrativeFontSize('16px')" class="sz-btn px-2 py-0.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-100 rounded cursor-pointer">Large (16px)</button>
                            <button type="button" data-sz="18px" onclick="setNarrativeFontSize('18px')" class="sz-btn px-2 py-0.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-100 rounded border-l border-slate-200 cursor-pointer">XL (18px)</button>
                        </div>
                    </div>
                </div>

                <!-- Textarea Editor Area -->
                <div id="narrative-editor-container" class="relative">
                    <textarea id="imaging-narrative-textarea" name="results[{$imagingResId}][value]" rows="22" class="w-full font-mono text-sm leading-relaxed p-4 border-0 focus:ring-0 focus:outline-none bg-white resize-y" placeholder="Type or paste the full clinical narrative report here...&#10;&#10;Use the toolbar above or keyboard shortcuts:&#10;• Ctrl+B for Bold&#10;• Ctrl+I for Italic&#10;• Ctrl+U for Underline&#10;• Headings (#, ##) and bullet lists (- item) are automatically styled&#10;• Click 'Live Print Preview' to see printed layout anytime">{$currentImagingValSafe}</textarea>
                </div>

                <!-- Live Print Preview Container -->
                <div id="narrative-preview-container" class="hidden p-6 bg-slate-100 min-h-[380px] border-t border-slate-200 overflow-y-auto">
                    <div class="max-w-3xl mx-auto bg-white p-8 rounded-2xl shadow-md border border-slate-300">
                        <div class="text-center border-b-2 border-slate-900 pb-2 mb-5">
                            <div class="text-lg font-black uppercase tracking-wider text-slate-900">{$activeTestLabel}</div>
                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-0.5">Clinical Diagnostic Imaging Report (Print Preview)</div>
                        </div>
                        <div id="narrative-live-preview-content" class="lab-report__imaging-narrative text-slate-900 leading-relaxed font-sans min-h-[220px]">
                            <!-- Injected live via JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-[11px] text-slate-500 flex items-center justify-between">
                <span><i class="fa-solid fa-circle-info text-teal-600 mr-1"></i> Supports Word-like formatting (<strong>Bold</strong>, <em>Italic</em>, <u>Underline</u>, Headings, Lists, Empty lines). Prints cleanly as a full-page report under <strong>{$activeTestLabel}</strong>.</span>
                <span class="text-slate-400">Shortcuts: Ctrl+B (Bold), Ctrl+I (Italic), Ctrl+U (Underline).</span>
            </p>
        </div>
HTML;
    } else {
        $testContentHtml = <<<HTML
        <div class="px-5 py-3 bg-blue-50/70 border-b border-blue-100 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <strong class="text-blue-950 text-sm tracking-wide font-extrabold">{$activeTestLabel}</strong>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                        <i class="fa-solid fa-check text-[9px]"></i> Only Entered Results Print
                    </span>
                </div>
                <p class="text-[11px] text-blue-800 mt-0.5">
                    Untouched or unchecked parameters are automatically hidden from the final patient report.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="autoHideEmptyParams()" class="px-2.5 py-1 rounded-xl bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold border border-slate-300 shadow-2xs transition-colors cursor-pointer" title="Untick Show checkbox on all parameters that currently have no value entered">
                    <i class="fa-solid fa-filter text-indigo-600 mr-1"></i> Hide Untouched
                </button>
                <button type="button" onclick="showAllParams()" class="px-2.5 py-1 rounded-xl bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold border border-slate-300 shadow-2xs transition-colors cursor-pointer" title="Tick Show checkbox on all parameters">
                    <i class="fa-solid fa-check-double text-teal-600 mr-1"></i> Show All
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm results-entry-table">
                <thead class="bg-slate-800 text-white text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-3 py-2.5 text-left" style="width: {$paramThWidth};">Parameter</th>
                        <th class="px-2.5 py-2.5 text-right" style="width: {$rangeThWidth};">Reference Range</th>
                        <th class="px-2.5 py-2.5 text-right" style="width: 10%;">Unit</th>
                        <th class="px-2.5 py-2.5 text-right" style="width: 30%;">Result</th>
                        {$flagTh}
                    </tr>
                </thead>
                <tbody>
                    {$tableBody}
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 p-4 bg-slate-50/70 space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-800">
                        <i class="fa-solid fa-microscope text-teal-700 mr-1.5"></i>
                        Test Methodology &amp; Clinical Notes ({$activeTestLabel})
                    </label>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        Belongs to the <strong>{$activeTestLabel}</strong> test entity in the test catalog. Automatically prints as a clinical narrative box under this test on the patient report.
                    </p>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-teal-100 text-teal-800">
                    tests.methodology
                </span>
            </div>
            <textarea name="test_methodology" rows="5" class="field w-full font-mono text-xs leading-relaxed" placeholder="Enter methodology paragraphs, kit details, principle, interpretation, comments... (e.g. Methodologies: ..., Comments: ..., Interpretation: ...)">{$activeTestMethodologySafe}</textarea>
            <input type="hidden" name="active_test_id" value="{$activeTestIdSafe}">
        </div>
HTML;
    }

    $reportNotesAndDoctorHtml = '';
    $saveTplModalHtml = '';
    if ($isImagingActive) {
        $reportNotesAndDoctorHtml = <<<HTML
        <div class="border-t border-slate-200 p-5 bg-slate-50/60">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                <!-- Left: Clinical Notes for Report (4 cols) -->
                <div class="lg:col-span-4 space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wide text-rose-700 flex items-center gap-1.5">
                        <i class="fa-solid fa-comment-medical text-rose-500"></i> Notes for report
                    </label>
                    <textarea name="clinical_notes" rows="6" class="field w-full text-xs font-medium bg-white border-slate-300 rounded-2xl p-3 shadow-2xs" placeholder="Optional clinical recommendation / note for the doctor (e.g. correlate with clinical picture)…">{$notes}</textarea>
                    <p class="text-[11px] text-slate-400">Prints as a clinical recommendation note at the bottom of the report.</p>
                </div>

                <!-- Right: Doctor on Report (Text Field + Qualifications + Designation + Print Button) (8 cols) -->
                <div class="lg:col-span-8 bg-white border-2 border-teal-300 rounded-3xl p-5 shadow-sm space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-teal-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-teal-600 text-white shadow-xs">
                                <i class="fa-solid fa-user-doctor text-sm"></i>
                            </span>
                            <div>
                                <h4 class="text-sm font-extrabold text-teal-950">Reporting Doctor &amp; Radiologist Signatory</h4>
                                <p class="text-[11px] text-teal-700 font-medium">Prints in the signatory block at the bottom of the diagnostic report.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-[10px] font-bold uppercase text-slate-400">Preset:</span>
                            <select id="reporting-doctor-select" class="field text-xs font-bold text-slate-800 bg-teal-50/50 hover:bg-teal-50 border border-teal-300 rounded-xl py-1 px-2.5 cursor-pointer shadow-2xs" onchange="handleReportingDoctorSelect(this.value)" title="Choose from registered doctor presets">
                                {$docSelectOpts}
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Doctor Name -->
                        <div class="sm:col-span-2 space-y-1">
                            <label class="block text-xs font-bold text-slate-800">
                                Doctor Full Name <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="reporting_doctor" id="reporting-doctor-input" value="{$curReportDocNameSafe}" class="field text-sm font-bold text-slate-900 w-full bg-white border-teal-300 focus:border-teal-600 focus:ring-2 focus:ring-teal-100 rounded-xl py-2 px-3 shadow-xs" placeholder="e.g. Dr. Imran Sheikh" oninput="handleReportingDoctorInput(this.value)">
                        </div>

                        <!-- Doctor Qualifications / Degrees -->
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-800">
                                Doctor Qualifications / Degrees
                            </label>
                            <input type="text" name="reporting_doctor_quals" id="reporting-doctor-quals-input" value="{$curReportDocQualsSafe}" class="field text-xs font-semibold text-slate-900 w-full bg-white border-teal-200 focus:border-teal-600 rounded-xl py-2 px-3 shadow-2xs" placeholder="e.g. M.B.B.S, F.C.P.S (Radiology), M.C.P.S">
                            <p class="text-[10px] text-slate-400">Degrees, fellowships &amp; certifications</p>
                        </div>

                        <!-- Doctor Designation / Title -->
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-800">
                                Designation / Department Title
                            </label>
                            <input type="text" name="reporting_doctor_desig" id="reporting-doctor-desig-input" value="{$curReportDocDesigSafe}" class="field text-xs font-semibold text-slate-900 w-full bg-white border-teal-200 focus:border-teal-600 rounded-xl py-2 px-3 shadow-2xs" placeholder="e.g. Consultant Radiologist &amp; Sonologist">
                            <p class="text-[10px] text-slate-400">Position printed under doctor name</p>
                        </div>
                    </div>

                    <!-- Direct Save Final Report & Print Button in this Doctor block -->
                    <div class="pt-3 border-t border-teal-100 flex flex-wrap items-center justify-between gap-3">
                        <span class="text-xs text-slate-500 font-medium">
                            <i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> All changes auto-save as you type.
                        </span>
                        <div class="flex items-center gap-2">
                            <button type="submit" class="btn btn-secondary text-xs font-bold py-2 px-3">
                                <i class="fa-solid fa-floppy-disk text-slate-400 mr-1"></i> Save Draft
                            </button>
                            <button type="submit" name="goto_print" value="1" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs sm:text-sm shadow-md hover:shadow-lg transition-all cursor-pointer">
                                <i class="fa-solid fa-print"></i> Save Final Report &amp; Print &rarr;
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        HTML;

        $saveTplModalHtml = <<<HTML
        <!-- Modal for Saving Custom Radiology Template -->
        <div id="save-template-modal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden">
                <div class="px-5 py-4 bg-gradient-to-r from-teal-600 to-cyan-700 text-white flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-folder-plus text-base"></i>
                        <h3 class="font-extrabold text-sm">Save New Radiology Report Template</h3>
                    </div>
                    <button type="button" onclick="closeSaveTemplateModal()" class="text-white/80 hover:text-white p-1 text-sm cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="p-5 space-y-3.5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Template Title / Study Name</label>
                        <input type="text" id="new-tpl-title" class="field text-xs font-semibold w-full" placeholder="e.g. Ultrasound KUB Custom, Chest X-Ray Normal">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Modality / Department</label>
                        <select id="new-tpl-dept" class="field text-xs font-semibold w-full">
                            <option value="Ultrasound">Ultrasound &amp; Sonography</option>
                            <option value="Radiology">Digital X-Ray / Radiography</option>
                            <option value="Cardiology">ECG / Echocardiography</option>
                            <option value="CT Scan">CT Scan</option>
                            <option value="MRI">MRI</option>
                            <option value="General">General Radiology</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Report Findings &amp; Format Content</label>
                        <textarea id="new-tpl-content" rows="8" class="field text-xs font-mono w-full leading-relaxed" placeholder="Template text..."></textarea>
                        <p class="text-[10px] text-slate-400 mt-1">This text is copied from your editor and will be saved in your Radiology Templates folder for future 1-click loading.</p>
                    </div>
                    <div id="new-tpl-error" class="hidden text-xs text-rose-600 font-bold"></div>
                </div>
                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeSaveTemplateModal()" class="btn btn-secondary text-xs cursor-pointer">Cancel</button>
                    <button type="button" onclick="submitSaveNewTemplate()" id="save-tpl-submit-btn" class="btn btn-primary text-xs bg-teal-600 hover:bg-teal-700 border-teal-600 text-white font-bold cursor-pointer">
                        <i class="fa-solid fa-floppy-disk mr-1"></i> Save to Templates Folder
                    </button>
                </div>
            </div>
        </div>
        HTML;
    } else {
        $reportNotesAndDoctorHtml = <<<HTML
        <div class="border-t border-slate-200 p-4 space-y-2">
            <label class="block text-xs font-bold uppercase tracking-wide text-rose-700">Notes for report</label>
            <textarea name="clinical_notes" rows="3" class="field w-full text-sm" placeholder="Optional note for the doctor (e.g. correlate with clinical picture)…">{$notes}</textarea>
        </div>
        HTML;
    }

    $fieldsetOpen = ($isReportLocked && !$isAdmin) ? '<fieldset disabled style="border:none;margin:0;padding:0;min-width:0;" class="opacity-80">' : '';
    $fieldsetClose = ($isReportLocked && !$isAdmin) ? '</fieldset>' : '';

    $bottomActionsHtml = '';
    if ($isReportLocked && !$isAdmin) {
        $bottomActionsHtml = <<<HTML
        <div class="border-t border-slate-200 bg-rose-50/70 px-4 py-3 flex flex-wrap gap-2.5 items-center justify-between">
            <div class="flex items-center gap-2 text-xs font-bold text-rose-800">
                <i class="fa-solid fa-lock text-rose-600"></i> Report Locked by {$reportLockedBy} — Editing is disabled. Only an Admin account can unlock or edit results.
            </div>
            <a href="{$previewLink}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm shadow-md transition-all">
                <i class="fa-solid fa-file-medical"></i> View Final Report &rarr;
            </a>
        </div>
HTML;
    } else {
        $bottomLockBtn = (!$isReportLocked)
            ? '<button type="submit" name="toggle_report_lock" value="lock" onclick="return confirm(\'Lock this report? Once locked, test results can only be unlocked or edited from an Admin ID.\');" class="btn btn-secondary text-xs text-amber-900 bg-amber-50 border-amber-300 font-bold hover:bg-amber-100" title="Lock report once reviewed to prevent changes by employees"><i class="fa-solid fa-lock mr-1 text-amber-600"></i> Lock Report</button>'
            : '<button type="submit" name="toggle_report_lock" value="unlock" class="btn btn-secondary text-xs text-rose-700 bg-white border-rose-300 font-bold hover:bg-rose-50"><i class="fa-solid fa-lock-open mr-1 text-rose-600"></i> Unlock Report (Admin)</button>';

        $bottomActionsHtml = <<<HTML
        <div class="border-t border-slate-200 bg-slate-50 px-4 py-3 flex flex-wrap gap-2.5 items-center justify-between">
            <div class="flex flex-wrap gap-2">
                <button type="submit" name="save_and_next" value="1" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs sm:text-sm shadow-md transition-all">
                    {$saveAndNextBtnHtml}
                </button>
                <button type="submit" class="btn btn-secondary text-xs"><i class="fa-solid fa-floppy-disk text-slate-400 mr-1"></i> Save as Draft</button>
                <button type="submit" name="send_verify" value="1" class="btn btn-secondary text-xs"><i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> Save &amp; Verify</button>
                {$bottomLockBtn}
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" name="goto_preview" value="1" class="btn btn-secondary text-xs font-bold py-2.5 px-4">
                    <i class="fa-solid fa-file-medical mr-1 text-teal-600"></i> View Final Report
                </button>
                <button type="submit" name="goto_print" value="1" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm shadow-md hover:shadow-lg transition-all cursor-pointer">
                    <i class="fa-solid fa-print"></i> Save Final Report &amp; Print &rarr;
                </button>
            </div>
        </div>
HTML;
    }

    $content .= <<<HTML
    <div class="bg-white border border-slate-200/90 rounded-3xl shadow-card overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50/70 p-5">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-xs font-medium">
                <div><span class="block text-[10px] uppercase text-slate-400 font-extrabold tracking-wider">Lab No</span><strong class="font-mono text-base font-black text-slate-900">{$labNoSafe}</strong></div>
                <div><span class="block text-[10px] uppercase text-slate-400 font-extrabold tracking-wider">MR No</span><strong class="font-mono text-sm font-bold text-slate-800">{$mrNo}</strong></div>
                <div><span class="block text-[10px] uppercase text-slate-400 font-extrabold tracking-wider">Patient</span><strong class="text-sm font-bold text-slate-900">{$patientName}</strong></div>
                <div><span class="block text-[10px] uppercase text-slate-400 font-extrabold tracking-wider">Age / Sex</span><strong class="text-slate-800">{$age} yrs / {$gender}</strong></div>
                <div><span class="block text-[10px] uppercase text-slate-400 font-extrabold tracking-wider">Phone</span><span class="text-slate-700">{$phone}</span></div>
                <div><span class="block text-[10px] uppercase text-slate-400 font-extrabold tracking-wider">Registered</span><span class="text-slate-700">{$regAt}</span></div>
                <div class="sm:col-span-2"><span class="block text-[10px] uppercase text-slate-400 font-extrabold tracking-wider">Referring doctor</span><span class="text-slate-800 font-semibold">{$doctor}</span></div>
            </div>
        </div>

        {$fieldsetOpen}
        {$testContentHtml}
        {$reportNotesAndDoctorHtml}
        {$fieldsetClose}
        {$bottomActionsHtml}
    </div>
</div>
</form>

{$saveTplModalHtml}
HTML;

$content .= '<script>window.__LAB_SEARCH_VISITS__ = ' . $searchVisitsJson . ';</script>';
$content .= <<<'SCRIPT'
<script>
window.focusPatientSearch = function() {
    const el = document.getElementById('patient-search-input');
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.focus();
        el.select();
        el.dispatchEvent(new Event('input', { bubbles: true }));
    }
};
(function() {
    const visits = window.__LAB_SEARCH_VISITS__ || [];
    const container = document.getElementById('patient-search-container');
    const input = document.getElementById('patient-search-input');
    const dropdown = document.getElementById('patient-search-dropdown');
    const list = document.getElementById('patient-search-list');
    const header = document.getElementById('patient-search-header');
    const clearBtn = document.getElementById('patient-search-clear');
    
    if (!container || !input || !dropdown || !list) return;

    let selectedIndex = -1;
    let currentFiltered = [];

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function highlightMatch(text, query) {
        if (!text) return '';
        const safe = escapeHtml(text);
        if (!query) return safe;
        const qSafe = escapeRegex(query.trim());
        if (!qSafe) return safe;
        const reg = new RegExp('(' + qSafe + ')', 'gi');
        return safe.replace(reg, '<mark class="bg-amber-200 text-amber-950 font-bold px-0.5 rounded">$1</mark>');
    }

    function statusBadge(status) {
        const s = (status || '').toLowerCase();
        if (s === 'verified' || s === 'completed') {
            return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
        }
        if (s === 'in-progress' || s === 'partial') {
            return 'bg-blue-100 text-blue-800 border border-blue-200';
        }
        if (s === 'sample-collected') {
            return 'bg-purple-100 text-purple-800 border border-purple-200';
        }
        return 'bg-amber-100 text-amber-800 border border-amber-200';
    }

    function renderResults(query) {
        const q = (query || '').trim().toLowerCase();
        if (clearBtn) {
            clearBtn.classList.toggle('hidden', q === '');
        }

        if (q === '') {
            currentFiltered = visits.slice(0, 10);
            if (header) header.textContent = 'Recent Patient Visits (' + currentFiltered.length + ')';
        } else {
            currentFiltered = visits.filter(v => {
                const lab = (v.lab_no || '').toLowerCase();
                const mr = (v.mr_no || '').toLowerCase();
                const name = (v.patient_name || '').toLowerCase();
                return lab.includes(q) || mr.includes(q) || name.includes(q);
            });
            if (header) {
                header.textContent = currentFiltered.length > 0 
                    ? 'Found ' + currentFiltered.length + ' matching patient' + (currentFiltered.length === 1 ? '' : 's')
                    : 'No matching patients';
            }
        }

        selectedIndex = -1;

        if (currentFiltered.length === 0) {
            list.innerHTML = `
                <div class="p-6 text-center text-slate-500">
                    <i class="fa-solid fa-magnifying-glass text-2xl text-slate-300 mb-2 block"></i>
                    <div class="text-sm font-semibold text-slate-700">No matching patients found</div>
                    <p class="text-xs text-slate-400 mt-1">
                        No match for "<span class="font-mono font-medium text-slate-600">${escapeHtml(query)}</span>" on Lab No, MR No, or Patient Name.
                    </p>
                </div>
            `;
            return;
        }

        let html = '';
        currentFiltered.forEach((item, idx) => {
            const isCurrent = item.is_current;
            html += `
                <div 
                    class="patient-search-item px-3.5 py-2.5 cursor-pointer transition-colors flex items-center justify-between gap-3 text-left ${isCurrent ? 'bg-teal-50/40' : 'hover:bg-slate-50'}"
                    data-index="${idx}"
                    data-lab-no="${escapeHtml(item.lab_no)}"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-slate-900 text-sm item-patient-name">${highlightMatch(item.patient_name, q)}</span>
                            ${isCurrent ? '<span class="text-[9px] uppercase font-bold tracking-wider px-1.5 py-0.5 bg-teal-100 text-teal-800 rounded">Current</span>' : ''}
                            <span class="text-xs text-slate-400 font-normal">
                                ${item.age ? item.age + ' yrs' : ''} ${item.gender ? '· ' + item.gender : ''}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 mt-1 flex-wrap text-xs text-slate-600">
                            <span class="inline-flex items-center font-mono font-bold text-teal-800 bg-teal-50 border border-teal-200 px-1.5 py-0.5 rounded text-[11px]">
                                <i class="fa-solid fa-flask-vial mr-1 text-[10px] text-teal-600"></i>${highlightMatch(item.lab_no, q)}
                            </span>
                            ${item.mr_no ? `
                                <span class="inline-flex items-center font-mono text-slate-700 bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded text-[11px]">
                                    <span class="text-slate-400 mr-1 text-[9px] font-sans font-bold uppercase">MR</span>${highlightMatch(item.mr_no, q)}
                                </span>
                            ` : ''}
                            <span class="text-slate-500 truncate max-w-xs text-[11px]" title="${escapeHtml(item.tests)}">
                                <i class="fa-solid fa-vial text-slate-400 mr-1"></i>${escapeHtml(item.tests)}
                            </span>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full uppercase tracking-wider ${statusBadge(item.status)}">
                            ${escapeHtml(item.status)}
                        </span>
                        <div class="text-[10px] text-slate-400 mt-1">${escapeHtml(item.date || '')}</div>
                    </div>
                </div>
            `;
        });
        list.innerHTML = html;

        list.querySelectorAll('.patient-search-item').forEach(el => {
            el.addEventListener('click', () => {
                const lab = el.getAttribute('data-lab-no');
                if (lab) navigateToPatient(lab);
            });
            el.addEventListener('mouseenter', () => {
                const idx = parseInt(el.getAttribute('data-index'), 10);
                setSelectedIndex(idx, false);
            });
        });
    }

    function setSelectedIndex(idx, scrollTo) {
        const items = list.querySelectorAll('.patient-search-item');
        items.forEach(el => el.classList.remove('bg-teal-100/70', 'ring-1', 'ring-teal-400'));
        selectedIndex = idx;
        if (selectedIndex >= 0 && selectedIndex < items.length) {
            const item = items[selectedIndex];
            item.classList.add('bg-teal-100/70', 'ring-1', 'ring-teal-400');
            if (scrollTo) {
                item.scrollIntoView({ block: 'nearest' });
            }
        }
    }

    function navigateToPatient(labNo) {
        if (!labNo) return;
        window.location.href = '/portals/results/entry.php?lab_no=' + encodeURIComponent(labNo);
    }

    function showDropdown() {
        renderResults(input.value);
        dropdown.classList.remove('hidden');
    }

    function hideDropdown() {
        dropdown.classList.add('hidden');
        selectedIndex = -1;
    }

    input.addEventListener('focus', showDropdown);
    input.addEventListener('input', () => {
        showDropdown();
    });

    input.addEventListener('keydown', (e) => {
        if (dropdown.classList.contains('hidden')) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                showDropdown();
                e.preventDefault();
                return;
            }
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const count = currentFiltered.length;
            if (count > 0) {
                const next = selectedIndex < count - 1 ? selectedIndex + 1 : 0;
                setSelectedIndex(next, true);
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const count = currentFiltered.length;
            if (count > 0) {
                const prev = selectedIndex > 0 ? selectedIndex - 1 : count - 1;
                setSelectedIndex(prev, true);
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && currentFiltered[selectedIndex]) {
                navigateToPatient(currentFiltered[selectedIndex].lab_no);
            } else if (currentFiltered.length > 0) {
                navigateToPatient(currentFiltered[0].lab_no);
            }
        } else if (e.key === 'Escape') {
            hideDropdown();
            input.blur();
        }
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            input.value = '';
            input.focus();
            showDropdown();
        });
    }

    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) {
            hideDropdown();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
            e.preventDefault();
            input.focus();
            input.select();
        }
    });
})();

(function() {
    const form = document.getElementById('results-workspace');
    if (!form) return;

    const statusEl = document.getElementById('autosave-status');
    let autoSaveTimer = null;
    let isSaving = false;

    async function doAutoSave() {
        if (isSaving) return;
        if (statusEl) {
            statusEl.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin text-amber-500 mr-1.5"></i> Saving…';
            statusEl.className = 'inline-flex items-center text-[11px] font-semibold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-xl border border-amber-200';
        }
        isSaving = true;

        try {
            const formData = new FormData(form);
            formData.append('ajax_save', '1');

            const resp = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (resp.ok) {
                if (statusEl) {
                    statusEl.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-600 mr-1.5"></i> Saved';
                    statusEl.className = 'inline-flex items-center text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-xl border border-emerald-200';
                }
            } else {
                throw new Error('Save failed');
            }
        } catch (err) {
            console.error('Autosave error:', err);
            if (statusEl) {
                statusEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-rose-500 mr-1.5"></i> Not saved';
                statusEl.className = 'inline-flex items-center text-[11px] font-semibold text-rose-700 bg-rose-50 px-2.5 py-1 rounded-xl border border-rose-200';
            }
        } finally {
            isSaving = false;
        }
    }

    function queueAutoSave() {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(doAutoSave, 400);
    }

    form.querySelectorAll('input, select, textarea').forEach(el => {
        if (el.type === 'hidden' && el.name !== 'test_methodology') return;
        el.addEventListener('change', queueAutoSave);
        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
            el.addEventListener('blur', queueAutoSave);
        }
    });

    document.querySelectorAll('a[href*="/portals/reports/preview.php"]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'goto_preview';
            input.value = '1';
            form.appendChild(input);
            form.submit();
        });
    });
})();
</script>
SCRIPT;

$content .= '<script>';
$content .= 'window.__IMAGING_TEMPLATES_MAP__ = ' . $tplJsonMapEncoded . ';';
$content .= 'window.__DOCTOR_DETAILS_MAP__ = ' . $docJsonMapEncoded . ';';
$content .= 'window.__IMAGING_PRESETS__ = ' . json_encode($clinicalPresets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ';';
$content .= 'window.__IMAGING_PRESETS__.us_abd = window.__IMAGING_PRESETS__.us_whole_abd;';
$content .= <<<'JSSCRIPT'
function handleReportingDoctorSelect(docName) {
    const map = window.__DOCTOR_DETAILS_MAP__ || {};
    const inp = document.getElementById('reporting-doctor-input');
    const qualsInp = document.getElementById('reporting-doctor-quals-input');
    const desigInp = document.getElementById('reporting-doctor-desig-input');

    if (inp && docName) {
        inp.value = docName;
        inp.dispatchEvent(new Event('input', {bubbles:true}));
        inp.dispatchEvent(new Event('change', {bubbles:true}));
    }

    const doc = map[docName] || {};
    if (qualsInp && doc.qualifications) {
        qualsInp.value = doc.qualifications;
        qualsInp.dispatchEvent(new Event('input', {bubbles:true}));
        qualsInp.dispatchEvent(new Event('change', {bubbles:true}));
    }
    if (desigInp && doc.designation) {
        desigInp.value = doc.designation;
        desigInp.dispatchEvent(new Event('input', {bubbles:true}));
        desigInp.dispatchEvent(new Event('change', {bubbles:true}));
    }
}

function handleReportingDoctorInput(docName) {
    const sel = document.getElementById('reporting-doctor-select');
    if (sel) {
        let found = false;
        const target = (docName || '').trim().toLowerCase();
        for (let i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value && sel.options[i].value.trim().toLowerCase() === target) {
                sel.selectedIndex = i;
                found = true;
                break;
            }
        }
        if (!found) {
            sel.value = '';
        }
    }
}

function setNarrativeFontSize(sz) {
    const ta = document.getElementById('imaging-narrative-textarea');
    const hiddenInput = document.getElementById('imaging-narrative-fontsize');
    const previewContent = document.getElementById('narrative-live-preview-content');
    if (ta) {
        ta.style.fontSize = sz;
    }
    if (hiddenInput) {
        hiddenInput.value = sz;
    }
    if (previewContent) {
        previewContent.style.fontSize = sz;
    }
    document.querySelectorAll('.sz-btn').forEach(btn => {
        if (btn.getAttribute('data-sz') === sz) {
            btn.classList.add('bg-teal-50', 'text-teal-800', 'font-bold');
            btn.classList.remove('text-slate-600');
        } else {
            btn.classList.remove('bg-teal-50', 'text-teal-800', 'font-bold');
            btn.classList.add('text-slate-600');
        }
    });
}

function formatNarrativeText(action) {
    const ta = document.getElementById('imaging-narrative-textarea');
    if (!ta) return;

    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const val = ta.value;
    const selected = val.substring(start, end);
    let replacement = '';

    switch (action) {
        case 'bold':
            if (selected) {
                replacement = '**' + selected + '**';
            } else {
                replacement = '**bold text**';
            }
            break;
        case 'italic':
            if (selected) {
                replacement = '*' + selected + '*';
            } else {
                replacement = '*italic text*';
            }
            break;
        case 'underline':
            if (selected) {
                replacement = '<u>' + selected + '</u>';
            } else {
                replacement = '<u>underlined text</u>';
            }
            break;
        case 'h1':
            replacement = (start > 0 && val[start - 1] !== '\n' ? '\n' : '') + '# ' + (selected || 'MAIN HEADING') + '\n';
            break;
        case 'h2':
            replacement = (start > 0 && val[start - 1] !== '\n' ? '\n' : '') + '## ' + (selected || 'SECTION HEADING') + ':\n';
            break;
        case 'h3':
            replacement = (start > 0 && val[start - 1] !== '\n' ? '\n' : '') + '### ' + (selected || 'Subheading') + '\n';
            break;
        case 'bullet':
            if (selected) {
                const sLines = selected.split('\n');
                replacement = sLines.map(l => (l.trim().startsWith('-') ? l : '- ' + l)).join('\n');
            } else {
                replacement = (start > 0 && val[start - 1] !== '\n' ? '\n' : '') + '- Parameter: Normal finding';
            }
            break;
        case 'number':
            if (selected) {
                const sLines = selected.split('\n');
                replacement = sLines.map((l, idx) => ((idx + 1) + '. ' + l.replace(/^[0-9]+[\.\)]\s*/, ''))).join('\n');
            } else {
                replacement = (start > 0 && val[start - 1] !== '\n' ? '\n' : '') + '1. First observation\n2. Second observation';
            }
            break;
        case 'spacer':
            replacement = '\n\n';
            break;
        default:
            return;
    }

    ta.focus();
    ta.setRangeText(replacement, start, end, 'end');
    if (!selected) {
        if (action === 'bold') ta.setSelectionRange(start + 2, start + 11);
        else if (action === 'italic') ta.setSelectionRange(start + 1, start + 12);
        else if (action === 'underline') ta.setSelectionRange(start + 3, start + 18);
    }
}

function insertMedicalSection(name) {
    const ta = document.getElementById('imaging-narrative-textarea');
    if (!ta) return;
    const start = ta.selectionStart;
    const val = ta.value;
    const prefix = (start > 0 && val[start - 1] !== '\n' ? '\n\n' : (start > 1 && val.substring(start - 2, start) !== '\n\n' ? '\n' : ''));
    let block = '';
    if (name === 'FINDINGS') {
        block = prefix + 'FINDINGS:\n- Region / Parameter: Normal findings\n\n';
    } else if (name === 'IMPRESSION') {
        block = prefix + 'IMPRESSION:\nNORMAL RADIOLOGICAL STUDY. CLINICAL CORRELATION ADVISED.\n';
    } else {
        block = prefix + '## ' + name + ':\n';
    }
    ta.focus();
    ta.setRangeText(block, start, start, 'end');
}

function switchNarrativeView(mode) {
    const editContainer = document.getElementById('narrative-editor-container');
    const prevContainer = document.getElementById('narrative-preview-container');
    const tabEdit = document.getElementById('narrative-tab-editor');
    const tabPrev = document.getElementById('narrative-tab-preview');
    const actions = document.getElementById('narrative-toolbar-actions');
    const ta = document.getElementById('imaging-narrative-textarea');
    const previewContent = document.getElementById('narrative-live-preview-content');

    if (mode === 'preview') {
        if (ta && previewContent) {
            previewContent.innerHTML = parseNarrativeMarkdownClient(ta.value);
            const currentSz = document.getElementById('imaging-narrative-fontsize')?.value || '14px';
            previewContent.style.fontSize = currentSz;
        }
        if (editContainer) editContainer.classList.add('hidden');
        if (prevContainer) prevContainer.classList.remove('hidden');
        if (actions) actions.classList.add('opacity-40', 'pointer-events-none');
        if (tabEdit) {
            tabEdit.classList.remove('bg-white', 'text-slate-800', 'shadow-2xs');
            tabEdit.classList.add('text-slate-600');
        }
        if (tabPrev) {
            tabPrev.classList.add('bg-white', 'text-teal-800', 'shadow-2xs');
            tabPrev.classList.remove('text-slate-600');
        }
    } else {
        if (prevContainer) prevContainer.classList.add('hidden');
        if (editContainer) editContainer.classList.remove('hidden');
        if (actions) actions.classList.remove('opacity-40', 'pointer-events-none');
        if (tabPrev) {
            tabPrev.classList.remove('bg-white', 'text-teal-800', 'shadow-2xs');
            tabPrev.classList.add('text-slate-600');
        }
        if (tabEdit) {
            tabEdit.classList.add('bg-white', 'text-slate-800', 'shadow-2xs');
            tabEdit.classList.remove('text-slate-600');
        }
        if (ta) ta.focus();
    }
}

function parseNarrativeMarkdownClient(rawText) {
    if (!rawText || !rawText.trim()) {
        return '<p class="text-slate-400 italic">No narrative report findings entered yet...</p>';
    }

    const lines = rawText.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
    let html = [];
    let inUl = false;
    let inOl = false;

    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const trimmed = line.trim();

        if (trimmed === '') {
            if (inUl) { html.push('</ul>'); inUl = false; }
            if (inOl) { html.push('</ol>'); inOl = false; }
            html.push('<div class="narrative-spacer" style="height: 10px; min-height: 10px;"></div>');
            continue;
        }

        let lineSafe = escapeHtml(trimmed);

        // Restore whitelisted formatting tags
        lineSafe = lineSafe.replace(/&lt;u&gt;(.*?)&lt;\/u&gt;/gi, '<u style="text-decoration: underline; text-underline-offset: 2.5px;">$1</u>');
        lineSafe = lineSafe.replace(/&lt;(b|strong)&gt;(.*?)&lt;\/\1&gt;/gi, '<strong style="font-weight: 700; color: #0f172a;">$2</strong>');
        lineSafe = lineSafe.replace(/&lt;(i|em)&gt;(.*?)&lt;\/\1&gt;/gi, '<em>$2</em>');
        lineSafe = lineSafe.replace(/&lt;mark&gt;(.*?)&lt;\/mark&gt;/gi, '<mark style="background: #fef08a; padding: 1px 4px; border-radius: 3px; font-weight: 600;">$1</mark>');

        // Markdown inline formats
        lineSafe = lineSafe.replace(/\*\*(.*?)\*\*/g, '<strong style="font-weight: 700; color: #0f172a;">$1</strong>');
        lineSafe = lineSafe.replace(/__(.*?)__/g, '<strong style="font-weight: 700; color: #0f172a;">$1</strong>');
        lineSafe = lineSafe.replace(/(?<!\*)\*(?!\*)(.*?)(?<!\*)\*(?!\*)/g, '<em>$1</em>');
        lineSafe = lineSafe.replace(/==(.*?)==/g, '<mark style="background: #fef08a; padding: 1px 4px; border-radius: 3px; font-weight: 600;">$1</mark>');

        // Headings (#, ##, ###)
        if (/^###\s+(.+)$/.test(trimmed)) {
            if (inUl) { html.push('</ul>'); inUl = false; }
            if (inOl) { html.push('</ol>'); inOl = false; }
            const hContent = lineSafe.replace(/^###\s+/, '');
            html.push('<div style="font-size: 1.05em; font-weight: 700; color: #1e293b; margin: 8px 0 3px 0;">' + hContent + '</div>');
            continue;
        }
        if (/^##\s+(.+)$/.test(trimmed)) {
            if (inUl) { html.push('</ul>'); inUl = false; }
            if (inOl) { html.push('</ol>'); inOl = false; }
            const hContent = lineSafe.replace(/^##\s+/, '');
            html.push('<div style="font-size: 1.15em; font-weight: 800; color: #0f172a; text-transform: uppercase; margin: 10px 0 4px 0; letter-spacing: 0.3px;">' + hContent + '</div>');
            continue;
        }
        if (/^#\s+(.+)$/.test(trimmed)) {
            if (inUl) { html.push('</ul>'); inUl = false; }
            if (inOl) { html.push('</ol>'); inOl = false; }
            const hContent = lineSafe.replace(/^#\s+/, '');
            html.push('<div style="font-size: 1.25em; font-weight: 900; color: #0f172a; text-transform: uppercase; margin: 12px 0 5px 0; letter-spacing: 0.5px; border-bottom: 1.5px solid #0f172a; padding-bottom: 3px;">' + hContent + '</div>');
            continue;
        }

        // Bullet list
        if (/^[\-\*•]\s+(.+)$/.test(trimmed)) {
            if (inOl) { html.push('</ol>'); inOl = false; }
            if (!inUl) { html.push('<ul style="margin: 4px 0 6px 0; padding-left: 20px; list-style-type: disc;">'); inUl = true; }
            let itemContent = lineSafe.replace(/^[\-\*•]\s+/, '');
            const im = itemContent.match(/^([^:]+):\s+(.+)$/);
            if (im) {
                itemContent = '<strong style="font-weight: 700; color: #0f172a;">' + im[1] + ':</strong> ' + im[2];
            }
            html.push('<li style="margin-bottom: 3px;">' + itemContent + '</li>');
            continue;
        }

        // Numbered list
        if (/^([0-9]+)[\.\)]\s+(.+)$/.test(trimmed)) {
            if (inUl) { html.push('</ul>'); inUl = false; }
            if (!inOl) { html.push('<ol style="margin: 4px 0 6px 0; padding-left: 20px; list-style-type: decimal;">'); inOl = true; }
            let itemContent = lineSafe.replace(/^[0-9]+[\.\)]\s+/, '');
            html.push('<li style="margin-bottom: 3px;">' + itemContent + '</li>');
            continue;
        }

        if (inUl) { html.push('</ul>'); inUl = false; }
        if (inOl) { html.push('</ol>'); inOl = false; }

        // Prominent Impression
        if (/^(IMPRESSION|CONCLUSION|DIAGNOSTIC OPINION|RECOMMENDATIONS):/i.test(trimmed)) {
            html.push('<div style="font-size: 1.1em; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-top: 14px; margin-bottom: 5px; border-top: 1.5px dashed #94a3b8; padding-top: 6px;">' + lineSafe + '</div>');
            continue;
        }

        // Organ / header auto-bold
        const hm = lineSafe.match(/^([A-Z0-9\s\/\-\(\)&]{2,45}):(\s*.*)$/);
        if (hm) {
            const sTitle = hm[1].trim();
            const sBody = hm[2].trim();
            if (sBody === '') {
                html.push('<div style="font-size: 1.05em; font-weight: 800; color: #0f172a; margin-top: 8px; margin-bottom: 3px;">' + sTitle + ':</div>');
            } else {
                html.push('<div style="margin-bottom: 3px;"><strong style="font-weight: 700; color: #0f172a;">' + sTitle + ':</strong> ' + sBody + '</div>');
            }
            continue;
        }

        html.push('<div style="margin-bottom: 3px;">' + lineSafe + '</div>');
    }

    if (inUl) html.push('</ul>');
    if (inOl) html.push('</ol>');

    return html.join('\n');
}

function openSaveTemplateModal() {
    const ta = document.getElementById('imaging-narrative-textarea');
    const contentBox = document.getElementById('new-tpl-content');
    const titleBox = document.getElementById('new-tpl-title');
    const modal = document.getElementById('save-template-modal');
    const errBox = document.getElementById('new-tpl-error');
    if (!modal) return;
    if (errBox) errBox.classList.add('hidden');
    if (contentBox && ta) {
        contentBox.value = ta.value.trim();
    }
    if (titleBox && !titleBox.value) {
        const ind = document.getElementById('active-study-indicator');
        titleBox.value = (ind ? ind.textContent.trim().replace(' Loaded', '') : 'Radiology Study') + ' Template';
    }
    modal.classList.remove('hidden');
    if (titleBox) titleBox.focus();
}

function closeSaveTemplateModal() {
    const modal = document.getElementById('save-template-modal');
    if (modal) modal.classList.add('hidden');
}

async function submitSaveNewTemplate() {
    const titleBox = document.getElementById('new-tpl-title');
    const contentBox = document.getElementById('new-tpl-content');
    const deptBox = document.getElementById('new-tpl-dept');
    const errBox = document.getElementById('new-tpl-error');
    const btn = document.getElementById('save-tpl-submit-btn');

    const title = titleBox ? titleBox.value.trim() : '';
    const content = contentBox ? contentBox.value.trim() : '';
    const dept = deptBox ? deptBox.value : 'Ultrasound';

    if (!title || !content) {
        if (errBox) {
            errBox.textContent = 'Please provide both template title and template findings content.';
            errBox.classList.remove('hidden');
        }
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-1"></i> Saving...';
    }

    try {
        const fd = new FormData();
        fd.append('action', 'save_new_template');
        fd.append('template_title', title);
        fd.append('template_department', dept);
        fd.append('template_content', content);

        const resp = await fetch(window.location.href, {
            method: 'POST',
            body: fd
        });
        const res = await resp.json();
        if (res && res.ok) {
            // Add to dropdown
            const select = document.getElementById('us-report-type-select');
            if (select) {
                const opt = document.createElement('option');
                opt.value = 'tpl:' + res.id;
                opt.textContent = res.title;
                opt.selected = true;
                select.appendChild(opt);
            }
            if (window.__IMAGING_TEMPLATES_MAP__) {
                window.__IMAGING_TEMPLATES_MAP__[res.id] = res.content;
            }
            closeSaveTemplateModal();
            alert('Template "' + res.title + '" saved successfully to Radiology Templates folder!');
        } else {
            if (errBox) {
                errBox.textContent = res.error || 'Failed to save template.';
                errBox.classList.remove('hidden');
            }
        }
    } catch (e) {
        if (errBox) {
            errBox.textContent = 'Network or server error occurred while saving template.';
            errBox.classList.remove('hidden');
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1"></i> Save to Templates Folder';
        }
    }
}

function applyUltrasoundReportType(key) {
    const ta = document.getElementById('imaging-narrative-textarea');
    const select = document.getElementById('us-report-type-select');
    const indicator = document.getElementById('active-study-indicator');
    if (!ta || !key) return;

    let text = null;
    let label = '';
    if (key.startsWith('tpl:')) {
        const tplId = key.substring(4);
        text = (window.__IMAGING_TEMPLATES_MAP__ || {})[tplId];
    } else {
        text = (window.__IMAGING_PRESETS__ || {})[key];
    }

    if (!text) return;

    if (ta.value.trim() && !confirm('Replace current report content with the selected template?')) {
        return;
    }

    ta.value = text;
    ta.dispatchEvent(new Event('input', {bubbles:true}));
    ta.dispatchEvent(new Event('change', {bubbles:true}));

    if (select && select.value !== key) {
        select.value = key;
    }
    if (indicator && select) {
        const selectedOpt = select.options[select.selectedIndex];
        label = selectedOpt ? selectedOpt.text : 'Template';
        indicator.textContent = label + ' Loaded';
    }
    ta.focus();
}

function loadImagingPreset(key) {
    applyUltrasoundReportType(key);
}

function clearImagingBox() {
    const ta = document.getElementById('imaging-narrative-textarea');
    if (!ta) return;
    if (confirm('Clear report narrative text?')) {
        ta.value = '';
        ta.dispatchEvent(new Event('input', {bubbles:true}));
        ta.dispatchEvent(new Event('change', {bubbles:true}));
        ta.focus();
    }
}
JSSCRIPT;
$content .= '</script>';

$modalHtml = <<<HTML
<!-- Floating Page Grouping Trigger Button -->
<button type="button" id="floating-page-grouping-btn" onclick="openPageGroupingModal()" class="fixed z-40 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white font-bold text-xs sm:text-sm px-4 py-3 rounded-2xl shadow-xl flex items-center gap-2.5 hover:shadow-2xl transition-all cursor-pointer transform hover:-translate-y-0.5 border border-indigo-400/30" style="bottom: calc(1.25rem + 54px); right: 1.25rem;">
    <i class="fa-solid fa-table-cells-large text-base"></i>
    <span>Page Grouping</span>
    <span id="floating-page-badge" class="bg-indigo-950/70 text-indigo-100 text-[11px] font-bold px-2.5 py-0.5 rounded-full border border-indigo-400/40">{$maxPage} Pages</span>
</button>

<!-- Free Page Grouping Modal Dialog -->
<div id="pageGroupingModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-2 sm:p-4">
    <div class="relative w-full max-w-7xl max-w-[96vw] bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[92vh]">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-slate-50/80">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-lg shadow-sm">
                    <i class="fa-solid fa-table-cells-large"></i>
                </span>
                <div>
                    <h3 class="text-base font-black text-slate-900 tracking-tight">Free Page Grouping</h3>
                    <p class="text-xs text-slate-500">Drag tests to pages or reorder them • Unlimited pages • Complete flexibility</p>
                </div>
            </div>
            <button type="button" onclick="closePageGroupingModal()" class="w-9 h-9 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 flex items-center justify-center transition-all cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Toolbar / Quick Action Buttons -->
        <div class="flex flex-wrap items-center justify-between gap-2 px-6 py-3 bg-white border-b border-slate-100">
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="pageGroupingAction('one_per_page')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-bold text-xs transition-all cursor-pointer" title="Put each test on its own separate page">
                    <i class="fa-solid fa-file-lines text-indigo-600"></i> 1 Test Per Page
                </button>
                <button type="button" onclick="pageGroupingAction('by_dept')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-teal-50 hover:bg-teal-100 text-teal-700 border border-teal-200 font-bold text-xs transition-all cursor-pointer" title="Group tests by laboratory department (Hematology, Biochemistry, etc.)">
                    <i class="fa-solid fa-layer-group text-teal-600"></i> Group by Department
                </button>
                <button type="button" onclick="pageGroupingAction('combine_all')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 font-bold text-xs transition-all cursor-pointer" title="Combine all tests onto Page 1">
                    <i class="fa-solid fa-compress text-slate-500"></i> Combine on 1 Page
                </button>
            </div>
            <button type="button" onclick="pageGroupingAddPage()" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-all shadow-xs cursor-pointer">
                <i class="fa-solid fa-plus text-xs"></i> Add Page
            </button>
        </div>

        <!-- Modal Body: Defined Pages Grid -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-5 bg-slate-50/50" id="modal-pages-container">
            <!-- Dynamically populated by JS -->
        </div>

        <!-- Modal Footer -->
        <div class="flex items-center justify-between px-6 py-3.5 border-t border-slate-200 bg-white">
            <div class="text-xs font-semibold text-slate-500" id="modal-grouping-summary">
                {$testCount} total tests • {$maxPage} pages
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="closePageGroupingModal()" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 font-bold text-xs transition-all cursor-pointer">
                    Cancel
                </button>
                <button type="button" id="save-grouping-btn" onclick="savePageGroupingFromModal()" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-all flex items-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-floppy-disk"></i> Save Grouping
                </button>
            </div>
        </div>
    </div>
</div>
HTML;
$content .= $modalHtml;

$modalJsInit = 'window.__PAGE_GROUPING_TESTS__ = ' . $testDetailsJson . ';';
$modalJsInit .= 'window.__PAGE_GROUPING_MAX_PAGE__ = ' . (int)$maxPage . ';';
$modalJsInit .= 'window.__PAGE_GROUPING_LAB_NO__ = ' . json_encode($labNo) . ';';
$content .= '<script>' . $modalJsInit . '</script>';

$content .= <<<'MODALSCRIPT'
<script>
let groupingData = Array.isArray(window.__PAGE_GROUPING_TESTS__) ? window.__PAGE_GROUPING_TESTS__ : [];
let groupingMaxPage = Math.max(4, parseInt(window.__PAGE_GROUPING_MAX_PAGE__) || 4);

function openPageGroupingModal() {
    const modal = document.getElementById('pageGroupingModal');
    if (!modal) return;
    renderPageGroupingGrid();
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closePageGroupingModal() {
    const modal = document.getElementById('pageGroupingModal');
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

function renderPageGroupingGrid() {
    const container = document.getElementById('modal-pages-container');
    if (!container) return;

    groupingMaxPage = Math.max(4, groupingData.reduce((max, t) => Math.max(max, parseInt(t.page) || 1), groupingMaxPage));

    const byPage = {};
    for (let p = 1; p <= groupingMaxPage; p++) {
        byPage[p] = [];
    }
    groupingData.forEach((t) => {
        const p = parseInt(t.page) || 1;
        if (!byPage[p]) byPage[p] = [];
        byPage[p].push(t);
    });

    let html = '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">';
    for (let p = 1; p <= groupingMaxPage; p++) {
        const tests = byPage[p] || [];
        const testCount = tests.length;
        const testCountLabel = testCount === 1 ? '1 test' : `${testCount} tests`;
        
        let chipsHtml = '';
        if (tests.length === 0) {
            chipsHtml = '<div class="p-3 text-center text-xs text-slate-400 border border-dashed border-slate-300 rounded-xl bg-slate-50/50">Empty page (drag tests here)</div>';
        } else {
            tests.forEach((t) => {
                let moveOptions = '';
                for (let op = 1; op <= groupingMaxPage; op++) {
                    const sel = op === p ? ' selected' : '';
                    moveOptions += `<option value="${op}"${sel}>Page ${op}</option>`;
                }
                
                chipsHtml += `
                    <div class="modal-test-chip flex items-center justify-between gap-1.5 p-2 bg-white border border-slate-200/90 rounded-xl shadow-2xs hover:border-indigo-400 transition-all cursor-grab active:cursor-grabbing select-none" draggable="true" data-test="${escapeHtmlModal(t.name)}">
                        <div class="flex items-center gap-1.5 min-w-0 pointer-events-none">
                            <span class="text-slate-400"><i class="fa-solid fa-grip-vertical"></i></span>
                            <div class="truncate">
                                <div class="text-xs font-bold text-slate-800 truncate leading-tight">${escapeHtmlModal(t.name)}</div>
                                <span class="inline-block text-[10px] font-semibold text-slate-500 bg-slate-100 px-1 py-0.2 rounded">${escapeHtmlModal(t.department || 'Lab')}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <select class="text-[10px] font-semibold border border-slate-300 rounded-lg px-1.5 py-1 bg-white text-slate-700 focus:ring-1 focus:ring-indigo-500 cursor-pointer" onchange="moveTestToPage('${escapeJsModal(t.name)}', this.value)">
                                ${moveOptions}
                            </select>
                            <button type="button" class="w-5 h-5 flex items-center justify-center text-slate-400 hover:text-indigo-600 rounded cursor-pointer" onclick="shiftTestOrder('${escapeJsModal(t.name)}', -1)" title="Move up"><i class="fa-solid fa-chevron-up text-[9px]"></i></button>
                            <button type="button" class="w-5 h-5 flex items-center justify-center text-slate-400 hover:text-indigo-600 rounded cursor-pointer" onclick="shiftTestOrder('${escapeJsModal(t.name)}', 1)" title="Move down"><i class="fa-solid fa-chevron-down text-[9px]"></i></button>
                        </div>
                    </div>
                `;
            });
        }

        html += `
            <div class="modal-page-card bg-white border border-slate-200 rounded-2xl p-3 shadow-sm flex flex-col justify-between" data-modal-page="${p}">
                <div>
                    <div class="flex items-center justify-between pb-2.5 mb-2.5 border-b border-slate-100">
                        <div class="flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-md bg-indigo-100 text-indigo-700 font-black text-[11px] flex items-center justify-center">${p}</span>
                            <span class="text-xs font-extrabold text-slate-900">Page ${p}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full ${testCount > 0 ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-500'}">${testCountLabel}</span>
                            ${testCount === 0 && p > 4 ? `<button type="button" onclick="removeEmptyPage(${p})" class="text-rose-500 hover:text-rose-700 text-xs px-1 py-0.5 rounded hover:bg-rose-50 cursor-pointer" title="Remove extra page"><i class="fa-solid fa-trash-can"></i></button>` : ''}
                        </div>
                    </div>
                    <div class="space-y-1.5 min-h-[60px]" data-modal-page-list="${p}">
                        ${chipsHtml}
                    </div>
                </div>
            </div>
        `;
    }
    html += '</div>';
    container.innerHTML = html;

    const summaryEl = document.getElementById('modal-grouping-summary');
    if (summaryEl) {
        summaryEl.textContent = `${groupingData.length} total tests • ${groupingMaxPage} pages`;
    }

    initModalDragDrop();
}

function moveTestToPage(testName, newPage) {
    const t = groupingData.find(item => item.name === testName);
    if (t) {
        t.page = parseInt(newPage);
        renderPageGroupingGrid();
    }
}

function shiftTestOrder(testName, direction) {
    const item = groupingData.find(t => t.name === testName);
    if (!item) return;
    const pageNo = item.page;
    const pageTests = groupingData.filter(t => t.page === pageNo);
    const pIdx = pageTests.findIndex(t => t.name === testName);
    if (pIdx < 0) return;
    const targetPIdx = pIdx + direction;
    if (targetPIdx < 0 || targetPIdx >= pageTests.length) return;
    const otherTestName = pageTests[targetPIdx].name;
    const idx1 = groupingData.findIndex(t => t.name === testName);
    const idx2 = groupingData.findIndex(t => t.name === otherTestName);
    if (idx1 >= 0 && idx2 >= 0) {
        const temp = groupingData[idx1];
        groupingData[idx1] = groupingData[idx2];
        groupingData[idx2] = temp;
        renderPageGroupingGrid();
    }
}

function pageGroupingAddPage() {
    groupingMaxPage++;
    renderPageGroupingGrid();
}

function removeEmptyPage(pageNo) {
    groupingData.forEach(t => {
        if (t.page > pageNo) {
            t.page--;
        }
    });
    groupingMaxPage = Math.max(4, groupingMaxPage - 1);
    renderPageGroupingGrid();
}

function pageGroupingAction(action) {
    if (action === 'one_per_page') {
        groupingData.forEach((t, i) => {
            t.page = i + 1;
        });
        groupingMaxPage = Math.max(4, groupingData.length);
    } else if (action === 'by_dept') {
        const depts = {};
        let currentP = 1;
        groupingData.forEach((t) => {
            const d = t.department || 'General';
            if (!depts[d]) {
                depts[d] = currentP++;
            }
            t.page = depts[d];
        });
        groupingMaxPage = Math.max(4, currentP - 1);
    } else if (action === 'combine_all') {
        groupingData.forEach((t) => {
            t.page = 1;
        });
        groupingMaxPage = Math.max(4, 1);
    }
    renderPageGroupingGrid();
}

function initModalDragDrop() {
    const chips = document.querySelectorAll('.modal-test-chip');
    const lists = document.querySelectorAll('[data-modal-page-list]');
    let draggedName = null;

    chips.forEach(chip => {
        chip.addEventListener('dragstart', (e) => {
            draggedName = chip.getAttribute('data-test');
            e.dataTransfer.setData('text/plain', draggedName);
            e.dataTransfer.effectAllowed = 'move';
            chip.classList.add('opacity-40');
        });

        chip.addEventListener('dragend', () => {
            chip.classList.remove('opacity-40');
            document.querySelectorAll('.modal-test-chip').forEach(c => {
                c.classList.remove('border-t-2', 'border-t-indigo-600', 'border-b-2', 'border-b-indigo-600');
            });
            document.querySelectorAll('.modal-page-card').forEach(pc => {
                pc.classList.remove('ring-2', 'ring-indigo-400');
            });
            draggedName = null;
        });

        chip.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = 'move';
            const rect = chip.getBoundingClientRect();
            const midY = rect.top + rect.height / 2;
            if (e.clientY < midY) {
                chip.classList.add('border-t-2', 'border-t-indigo-600');
                chip.classList.remove('border-b-2', 'border-b-indigo-600');
            } else {
                chip.classList.add('border-b-2', 'border-b-indigo-600');
                chip.classList.remove('border-t-2', 'border-t-indigo-600');
            }
        });

        chip.addEventListener('dragleave', () => {
            chip.classList.remove('border-t-2', 'border-t-indigo-600', 'border-b-2', 'border-b-indigo-600');
        });

        chip.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            chip.classList.remove('border-t-2', 'border-t-indigo-600', 'border-b-2', 'border-b-indigo-600');
            const targetName = chip.getAttribute('data-test');
            if (!draggedName || draggedName === targetName) return;

            const rect = chip.getBoundingClientRect();
            const insertAfter = e.clientY >= rect.top + rect.height / 2;

            const draggedIdx = groupingData.findIndex(t => t.name === draggedName);
            if (draggedIdx < 0) return;
            const draggedItem = groupingData.splice(draggedIdx, 1)[0];

            const targetIdx = groupingData.findIndex(t => t.name === targetName);
            if (targetIdx < 0) {
                groupingData.push(draggedItem);
            } else {
                const targetItem = groupingData[targetIdx];
                draggedItem.page = targetItem.page;
                const insertIdx = insertAfter ? targetIdx + 1 : targetIdx;
                groupingData.splice(insertIdx, 0, draggedItem);
            }
            renderPageGroupingGrid();
        });
    });

    lists.forEach(list => {
        const pageCard = list.closest('.modal-page-card');
        const pageNo = parseInt(pageCard.getAttribute('data-modal-page')) || 1;

        list.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            pageCard.classList.add('ring-2', 'ring-indigo-400');
        });

        list.addEventListener('dragleave', (e) => {
            if (!list.contains(e.relatedTarget)) {
                pageCard.classList.remove('ring-2', 'ring-indigo-400');
            }
        });

        list.addEventListener('drop', (e) => {
            e.preventDefault();
            pageCard.classList.remove('ring-2', 'ring-indigo-400');
            if (!draggedName) return;

            const draggedIdx = groupingData.findIndex(t => t.name === draggedName);
            if (draggedIdx >= 0) {
                const draggedItem = groupingData.splice(draggedIdx, 1)[0];
                draggedItem.page = pageNo;
                let lastPageTestIdx = -1;
                for (let i = groupingData.length - 1; i >= 0; i--) {
                    if (groupingData[i].page === pageNo) {
                        lastPageTestIdx = i;
                        break;
                    }
                }
                if (lastPageTestIdx >= 0) {
                    groupingData.splice(lastPageTestIdx + 1, 0, draggedItem);
                } else {
                    groupingData.push(draggedItem);
                }
                renderPageGroupingGrid();
            }
        });
    });
}

async function savePageGroupingFromModal() {
    const saveBtn = document.getElementById('save-grouping-btn');
    const origHtml = saveBtn ? saveBtn.innerHTML : '';
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving…';
    }

    try {
        const curLabNo = window.__PAGE_GROUPING_LAB_NO__ || document.querySelector('input[name="lab_no"]')?.value || '';
        const fd = new FormData();
        fd.append('ajax_save_page_grouping', '1');
        fd.append('lab_no', curLabNo);
        groupingData.forEach((t) => {
            fd.append(`page_map[${t.name}]`, t.page);
            fd.append('page_map_test[]', t.name);
            fd.append('page_map_page[]', t.page);
        });

        const resp = await fetch(window.location.href, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await resp.json();
        if (data && data.ok) {
            const badge = document.getElementById('floating-page-badge');
            if (badge) {
                badge.textContent = `${groupingMaxPage} Pages`;
            }

            if (saveBtn) {
                saveBtn.className = 'px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold text-xs flex items-center gap-1.5';
                saveBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Grouping Saved!';
            }

            setTimeout(() => {
                closePageGroupingModal();
                if (new URLSearchParams(window.location.search).get('open_grouping') === '1') {
                    window.location.href = '/portals/reports/preview.php?lab_no=' + encodeURIComponent(curLabNo);
                } else {
                    const u = new URL(window.location.href);
                    u.searchParams.set('_t', Date.now());
                    window.location.href = u.toString();
                }
            }, 300);
        } else {
            throw new Error((data && data.error) ? data.error : 'Save failed');
        }
    } catch (err) {
        console.error('Error saving page grouping:', err);
        alert('Could not save page grouping: ' + (err.message || 'Please check your connection.'));
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = origHtml;
        }
    }
}

async function quickApplyOnePerPage() {
    if (!groupingData || groupingData.length === 0) {
        alert('No tests to distribute.');
        return;
    }
    if (!confirm('Distribute each test to 1 test per page?')) return;
    groupingData.forEach((t, i) => {
        t.page = i + 1;
    });
    groupingMaxPage = groupingData.length;
    await savePageGroupingFromModal();
}

function escapeHtmlModal(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function escapeJsModal(str) {
    if (!str) return '';
    return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function autoHideEmptyParams() {
    let count = 0;
    document.querySelectorAll('tr[data-result-row]').forEach(row => {
        const input = row.querySelector('input.result-value-input');
        const showCb = row.querySelector('input[type="checkbox"][name*="[show]"]');
        if (input && showCb) {
            const val = input.value.trim();
            if (val === '' || val === '-' || val === '—' || val.toLowerCase() === 'pending') {
                showCb.checked = false;
                row.classList.add('opacity-50');
                count++;
            } else {
                showCb.checked = true;
                row.classList.remove('opacity-50');
            }
        }
    });
}

function showAllParams() {
    document.querySelectorAll('tr[data-result-row]').forEach(row => {
        const showCb = row.querySelector('input[type="checkbox"][name*="[show]"]');
        if (showCb) {
            showCb.checked = true;
            row.classList.remove('opacity-50');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    if (new URLSearchParams(window.location.search).get('open_grouping') === '1') {
        setTimeout(function() {
            if (typeof openPageGroupingModal === 'function') {
                openPageGroupingModal();
            }
        }, 150);
    }

    const narrativeTa = document.getElementById('imaging-narrative-textarea');
    if (narrativeTa) {
        narrativeTa.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                if (e.key === 'b' || e.key === 'B') {
                    e.preventDefault();
                    formatNarrativeText('bold');
                } else if (e.key === 'i' || e.key === 'I') {
                    e.preventDefault();
                    formatNarrativeText('italic');
                } else if (e.key === 'u' || e.key === 'U') {
                    e.preventDefault();
                    formatNarrativeText('underline');
                }
            }
        });

        const hiddenSz = document.getElementById('imaging-narrative-fontsize');
        const defaultSz = hiddenSz && hiddenSz.value ? hiddenSz.value : '14px';
        setNarrativeFontSize(defaultSz);
    }

    // Auto check/uncheck row state when user enters result value
    document.addEventListener('input', function(e) {
        if (e.target && e.target.matches('input.result-value-input')) {
            const row = e.target.closest('tr[data-result-row]');
            if (row) {
                const showCb = row.querySelector('input[type="checkbox"][name*="[show]"]');
                if (showCb && e.target.value.trim() !== '') {
                    showCb.checked = true;
                    row.classList.remove('opacity-50');
                }
            }
        }
    });

    // Dim/undim row on Show checkbox toggle
    document.addEventListener('change', function(e) {
        if (e.target && e.target.matches('input[type="checkbox"][name*="[show]"]')) {
            const row = e.target.closest('tr[data-result-row]');
            if (row) {
                if (e.target.checked) {
                    row.classList.remove('opacity-50');
                } else {
                    row.classList.add('opacity-50');
                }
            }
        }
    });
});
</script>
MODALSCRIPT;

$portal = current_portal();
$activeKey = ($portal === 'imaging') ? 'station-lab' : 'results-entry';
render_page('Enter Results', $portal, $activeKey, $content);
