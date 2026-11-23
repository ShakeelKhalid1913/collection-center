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
            $this->seedStandardUltrasoundTemplates('ORG-001');
            $this->seedAllModalityTemplates('ORG-001');
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
                'id' => 'TPL-ECHO-01',
                'title' => '2D Echocardiography & Color Doppler (Normal Study)',
                'department' => 'Cardiology',
                'is_private' => 0,
                'content' => "TRANSTHORACIC ECHOCARDIOGRAPHY REPORT\n\nMEASUREMENTS:\n- Left Ventricle (LVIDd): 4.6 cm (Normal: 3.5 - 5.6 cm)\n- Left Ventricle (LVIDs): 2.9 cm (Normal: 2.0 - 4.0 cm)\n- Interventricular Septum (IVSd): 0.9 cm (Normal: 0.6 - 1.1 cm)\n- Posterior Wall (PWd): 0.9 cm (Normal: 0.6 - 1.1 cm)\n- Left Atrium (LA): 3.2 cm (Normal: 1.9 - 4.0 cm)\n- Aortic Root (Ao): 2.8 cm (Normal: 2.0 - 3.7 cm)\n- Ejection Fraction (EF): 62% (Normal: 55 - 75%)\n\nVALVULAR ASSESSMENT:\n- Mitral Valve: Leaflets normal, good mobility. No MS or significant MR.\n- Aortic Valve: Trileaflet, opens well. No AS or AR.\n- Tricuspid Valve: Normal. Estimated PASP within normal limits.\n- Pulmonic Valve: Normal.\n\nCHAMBER & FUNCTION:\n- All four cardiac chambers are normal in size.\n- Left ventricular systolic function is normal.\n- No regional wall motion abnormality (RWMA) at rest.\n- No pericardial effusion or intracardiac clot/mass seen.\n\nIMPRESSION: Normal 2D Transthoracic Echocardiogram with preserved LV systolic function (EF = 62%).",
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

    public function seedStandardUltrasoundTemplates(string $orgId): void
    {
        $usTemplates = [
            [
                'id' => 'TPL-US-ABD-01',
                'title' => 'Ultrasound Whole Abdomen & Pelvis (Normal)',
                'department' => 'Ultrasound',
                'is_private' => 0,
                'content' => "EXAMINATION: ULTRASOUND WHOLE ABDOMEN & PELVIS\n\nLIVER:\nNormal in size, smooth contour and uniform parenchymal echotexture. No focal solid or cystic mass lesion seen. Intrahepatic biliary tree and portal vein are normal in caliber.\n\nGALLBLADDER:\nNormal in size and shape. Wall thickness is normal (< 3 mm). Acoustic lumen is completely clear; no calculus, sludge or mass lesion seen. Common bile duct (CBD) is normal.\n\nPANCREAS:\nVisualized head, body and tail appear normal in size and echogenicity. Main pancreatic duct is not dilated.\n\nSPLEEN:\nNormal in size and parenchymal echotexture. No splenomegaly or focal lesion noted.\n\nKIDNEYS:\nBoth kidneys are normal in size, shape and cortical thickness. Corticomedullary differentiation is well preserved. No calculus or hydronephrosis seen bilaterally.\n\nURINARY BLADDER:\nWell distended with thin, smooth walls and clear lumen. No intraluminal calculus or mass.\n\nPELVIS / PROSTATE:\nVisualized pelvic structures are unremarkable. No free fluid in abdomen or pelvis.\n\nIMPRESSION:\nNORMAL ULTRASOUND EXAMINATION OF WHOLE ABDOMEN & PELVIS.",
            ],
            [
                'id' => 'TPL-US-KUB-01',
                'title' => 'Ultrasound KUB & Prostate (Urinary System)',
                'department' => 'Ultrasound',
                'is_private' => 0,
                'content' => "EXAMINATION: ULTRASOUND KUB & PROSTATE\n\nRIGHT KIDNEY:\nMeasures normal in size and anatomical position. Corticomedullary differentiation is well preserved. Normal parenchymal thickness. No evidence of renal calculus, hydronephrosis, or focal lesion.\n\nLEFT KIDNEY:\nMeasures normal in size and anatomical position. Corticomedullary differentiation is well preserved. Normal parenchymal thickness. No evidence of renal calculus, hydronephrosis, or focal lesion.\n\nURETERS:\nBoth ureters are not dilated; no obstructive ureteric calculus visualized.\n\nURINARY BLADDER:\nAdequately distended with clear acoustic lumen. Bladder wall thickness is smooth and uniform (< 3 mm). No intraluminal calculus, mass, or diverticulum seen.\n- Pre-void volume: Adequate.\n- Post-void residual (PVR): Insignificant (< 20 ml).\n\nPROSTATE:\nNormal in size, shape, and echotexture with smooth capsule. Estimated volume is within normal age-related limits. Median lobe is not enlarged.\n\nIMPRESSION:\nNORMAL ULTRASOUND KUB & PROSTATE. NO EVIDENCE OF NEPHROLITHIASIS OR URINARY TRACT OBSTRUCTION.",
            ],
            [
                'id' => 'TPL-US-PELV-01',
                'title' => 'Ultrasound Pelvis (Female / Gynaecological)',
                'department' => 'Ultrasound',
                'is_private' => 0,
                'content' => "EXAMINATION: ULTRASOUND PELVIS (FEMALE / GYNAECOLOGICAL)\n\nURINARY BLADDER:\nWell distended with clear lumen and smooth walls.\n\nUTERUS:\n- Position: Anteverted.\n- Size & Contour: Normal in size and regular in contour.\n- Myometrium: Homogeneous echotexture. No focal leiomyoma (fibroid), adenomyosis, or localized mass lesion seen.\n- Endometrium: Central, regular, and well-defined endometrial stripe measuring within normal physiological limits for cycle phase.\n\nCERVIX:\nNormal in appearance. No Nabothian cyst or mass identified.\n\nRIGHT OVARY:\nVisualized and measures normal in size with normal follicular pattern. No cystic or solid mass lesion seen.\n\nLEFT OVARY:\nVisualized and measures normal in size with normal follicular pattern. No cystic or solid mass lesion seen.\n\nADNEXAE & POUCH OF DOUGLAS (POD):\nBoth adnexal regions are free. No adnexal mass, localized collection, or free fluid in the Pouch of Douglas.\n\nIMPRESSION:\nNORMAL PELVIC ULTRASOUND EXAMINATION. NORMAL APPEARANCE OF UTERUS AND BOTH OVARIES.",
            ],
            [
                'id' => 'TPL-US-OBS-01',
                'title' => 'Ultrasound Obstetric / Antenatal (Routine Pregnancy)',
                'department' => 'Ultrasound',
                'is_private' => 0,
                'content' => "EXAMINATION: OBSTETRIC ULTRASOUND SCAN (ANTENATAL)\n\nFETAL VIABILITY & NUMBER:\n- Number of fetuses: Single live intrauterine fetus.\n- Cardiac Activity: Present and regular (Fetal Heart Rate: ~144 bpm).\n- Fetal Movements: Active somatic and limb movements noted.\n- Presentation: Cephalic / Vertex.\n\nFETAL BIOMETRY:\n- Biparietal Diameter (BPD): Normal for gestational age.\n- Femur Length (FL): Normal for gestational age.\n- Abdominal Circumference (AC): Normal for gestational age.\n- Head Circumference (HC): Normal for gestational age.\n- Average Gestational Age (EGA): Consistent with dates.\n- Expected Date of Delivery (EDD): Consistent with biometry.\n\nAMNIOTIC FLUID (LIQUOR):\nAdequate in quantity. Amniotic fluid index (AFI) is within normal limits.\n\nPLACENTA:\n- Position: Anterior / Fundal, Grade I maturity.\n- Relation to Os: Lower margin is well clear of the internal cervical os. No retroplacental hematoma seen.\n\nIMPRESSION:\nSINGLE LIVE INTRAUTERINE PREGNANCY CORRESPONDING TO GESTATIONAL AGE WITH ACTIVE FETAL MOVEMENTS AND NORMAL LIQUOR / PLACENTAL POSITION.",
            ],
            [
                'id' => 'TPL-US-ANOM-01',
                'title' => 'Ultrasound 2nd Trimester Fetal Anomaly Scan',
                'department' => 'Ultrasound',
                'is_private' => 0,
                'content' => "EXAMINATION: TARGETED FETAL ANOMALY SCAN (2ND TRIMESTER)\n\nFETAL BIOMETRY & VIABILITY:\n- Single live fetus in cephalic presentation.\n- Normal regular fetal cardiac activity and somatic movements.\n- Fetal biometry (BPD, HC, AC, FL) corresponds to clinical gestational age.\n\nANATOMICAL SURVEY:\n- Head & Brain: Intact skull contour, symmetrical lateral ventricles, cavum septum pellucidum visualized, normal posterior fossa & cerebellum.\n- Face: Both orbits, lens, nasal bone, and intact upper lip visualized.\n- Spine: Complete and intact vertebrae from cervical to sacral region in sagittal and transverse planes.\n- Thorax & Heart: Intact diaphragm, normal 4-chamber cardiac view, normal outflow tracts (LVOT/RVOT), and normal cardiac rhythm.\n- Abdomen: Intact anterior abdominal wall, normal stomach bubble, intact cord insertion. Both kidneys and bladder visualized.\n- Limbs: Bilateral upper and lower long bones, hands, and feet visualized and appear normal.\n- Amniotic Fluid: Adequate in volume.\n- Placenta: Normal position and echotexture, clear of internal os. 3-vessel umbilical cord identified.\n\nIMPRESSION:\nTARGETED ANOMALY SCAN REVEALS NO GROSS CONGENITAL OR STRUCTURAL ANOMALY DETECTED AT PRESENT GESTATIONAL AGE.",
            ],
            [
                'id' => 'TPL-US-UPPER-01',
                'title' => 'Ultrasound Upper Abdomen (Hepatobiliary)',
                'department' => 'Ultrasound',
                'is_private' => 0,
                'content' => "EXAMINATION: ULTRASOUND UPPER ABDOMEN\n\nLIVER:\nNormal in size, shape and parenchymal echotexture. No focal mass, abscess, or nodule seen. Intrahepatic ducts are not dilated. Portal vein is normal in caliber.\n\nGALLBLADDER:\nNormal lumen without gallstones, sludge, or wall thickening. Common bile duct (CBD) measures within normal limits.\n\nPANCREAS:\nVisualized portions appear normal in size and echogenicity without mass or calcification.\n\nSPLEEN:\nNormal in size with homogenous parenchymal echotexture. No focal lesion or splenomegaly.\n\nKIDNEYS:\nBoth kidneys are normal in size, shape, and cortical thickness. No calculus or hydronephrosis seen.\n\nIMPRESSION:\nNORMAL ULTRASOUND EXAMINATION OF UPPER ABDOMEN.",
            ],
            [
                'id' => 'TPL-US-TVS-01',
                'title' => 'Ultrasound TVS (Transvaginal Sonography)',
                'department' => 'Ultrasound',
                'is_private' => 0,
                'content' => "EXAMINATION: TRANSVAGINAL ULTRASOUND SCAN (TVS)\n\nUTERUS:\n- Position: Anteverted.\n- Myometrium: Homogeneous echotexture throughout. No focal intramural, subserosal, or submucosal fibroid.\n- Endometrium: Distinct trilaminar / secretory endometrial stripe measuring normal in thickness. Cavity is empty with regular margins.\n- Cervix: Normal length and internal os closed.\n\nRIGHT OVARY:\nNormal in volume and location. Follicles are peripheral and physiological in size. No complex ovarian mass.\n\nLEFT OVARY:\nNormal in volume and location. Follicles are peripheral and physiological in size. No complex ovarian mass.\n\nPOUCH OF DOUGLAS:\nFree of fluid, localized collection, or endometriotic deposits.\n\nIMPRESSION:\nNORMAL TRANSVAGINAL ULTRASOUND STUDY (TVS). UNREMARKABLE PELVIC ORGANS.",
            ],
            [
                'id' => 'TPL-US-SCROT-01',
                'title' => 'Ultrasound Scrotum & Testicular Color Doppler',
                'department' => 'Ultrasound',
                'is_private' => 0,
                'content' => "EXAMINATION: ULTRASOUND SCROTUM & COLOR DOPPLER\n\nRIGHT TESTIS:\nNormal in size, shape, and homogenous echotexture. Normal intratesticular vascular flow demonstrated on Color and Spectral Doppler. No focal intratesticular mass or calcification.\n- Right Epididymis: Normal size and echogenicity.\n- Tunica Vaginalis: No hydrocele.\n\nLEFT TESTIS:\nNormal in size, shape, and homogenous echotexture. Normal intratesticular vascular flow demonstrated on Color and Spectral Doppler. No focal intratesticular mass or calcification.\n- Left Epididymis: Normal size and echogenicity.\n- Tunica Vaginalis: No hydrocele.\n\nPAMPINIFORM PLEXUS:\nVenous channels demonstrate normal diameter bilaterally (< 2.0 mm). No retrograde venous reflux elicited during Valsalva maneuver. No evidence of varicocele bilaterally.\n\nIMPRESSION:\nNORMAL SCROTAL AND TESTICULAR COLOR DOPPLER STUDY. NO EVIDENCE OF EPIDIDYMO-ORCHITIS, HYDROCELE, OR VARICOCELE.",
            ],
            [
                'id' => 'TPL-US-THYR-01',
                'title' => 'Ultrasound Thyroid & Neck',
                'department' => 'Ultrasound',
                'is_private' => 0,
                'content' => "EXAMINATION: ULTRASOUND THYROID & NECK\n\nRIGHT THYROID LOBE:\nNormal in dimensions and parenchymal echogenicity. No focal solid nodule, cystic lesion, or calcification identified. Normal vascularity on color Doppler.\n\nLEFT THYROID LOBE:\nNormal in dimensions and parenchymal echogenicity. No focal solid nodule, cystic lesion, or calcification identified. Normal vascularity on color Doppler.\n\nISTHMUS:\nNormal in thickness (< 3 mm) and uniform in texture.\n\nCERVICAL LYMPH NODES:\nNo pathologically enlarged or suspicious cervical lymph nodes identified bilaterally.\n\nIMPRESSION:\nNORMAL ULTRASOUND EXAMINATION OF THYROID GLAND. NO EVIDENCE OF GOITRE, NODULE, OR CYSTIC LESION.",
            ],
            [
                'id' => 'TPL-US-BRST-01',
                'title' => 'Ultrasound Breast (Bilateral / Sonomammography)',
                'department' => 'Ultrasound',
                'is_private' => 0,
                'content' => "EXAMINATION: BILATERAL BREAST ULTRASOUND (SONOMAMMOGRAPHY)\n\nRIGHT BREAST:\nNormal fibroglandular parenchyma with normal ductal pattern. No focal solid mass lesion, cyst, or architectural distortion seen. Retroareolar ducts are not dilated.\n\nLEFT BREAST:\nNormal fibroglandular parenchyma with normal ductal pattern. No focal solid mass lesion, cyst, or architectural distortion seen. Retroareolar ducts are not dilated.\n\nAXILLARY REGIONS:\nVisualized bilateral axillary lymph nodes show normal preserved fatty hilum with benign sonographic morphology. No suspicious lymphadenopathy.\n\nACR BI-RADS ASSESSMENT: CATEGORY 1 (NEGATIVE / NORMAL STUDY).\n\nIMPRESSION:\nNORMAL BILATERAL BREAST ULTRASOUND (BI-RADS CATEGORY 1). NO SONOGRAPHIC EVIDENCE OF SOLID OR CYSTIC LESION.",
            ],
            [
                'id' => 'TPL-US-FOLL-01',
                'title' => 'Ultrasound Follicular Tracking / Study',
                'department' => 'Ultrasound',
                'is_private' => 0,
                'content' => "EXAMINATION: ULTRASOUND FOLLICULAR STUDY (FOLLICULOMETRY)\n\nDAY OF CYCLE: Day _____\n\nENDOMETRIUM:\n- Thickness: _____ mm.\n- Echopattern: Triple-line pattern (Trilaminar) / Secretory.\n\nRIGHT OVARY:\n- Dominant Follicle: _____ x _____ mm.\n- Secondary Follicles: Multiple small developing follicles.\n\nLEFT OVARY:\n- Dominant Follicle: _____ x _____ mm.\n- Secondary Follicles: Multiple small developing follicles.\n\nFREE FLUID IN POUCH OF DOUGLAS:\nNone / Minimal physiological fluid.\n\nIMPRESSION:\nFOLLICULAR STUDY SHOWING MATURING DOMINANT FOLLICLE AS NOTED ABOVE.",
            ],
            [
                'id' => 'TPL-US-SOFT-01',
                'title' => 'Ultrasound Soft Tissue / Musculoskeletal Swelling',
                'department' => 'Ultrasound',
                'is_private' => 0,
                'content' => "EXAMINATION: ULTRASOUND SOFT TISSUE / SWELLING\n\nSITE OF EXAMINATION: ____________________\n\nFINDINGS:\n- High-frequency sonographic examination of the region demonstrates normal subcutaneous tissue and muscular planes.\n- No localized cystic lesion, solid mass, abscess, or hematoma identified.\n- Overlying skin and deep fascia appear intact.\n- Color Doppler shows normal physiological vascularity without hypervascularity.\n\nIMPRESSION:\nNORMAL ULTRASOUND EVALUATION OF SOFT TISSUES. NO FOCAL MASS LESION OR FLUID COLLECTION NOTED.",
            ],
        ];

        foreach ($usTemplates as $tpl) {
            $existing = $this->db->fetchOne(
                "SELECT id FROM report_templates WHERE id = :id AND organization_id = :org_id",
                ['id' => $tpl['id'], 'org_id' => $orgId]
            );
            if (!$existing) {
                $this->create(array_merge($tpl, ['organization_id' => $orgId, 'created_by' => 'admin']));
            }
        }
    }

    public function seedAllModalityTemplates(string $orgId): void
    {
        $allTemplates = [
            // --- DIGITAL X-RAY TEMPLATES ---
            [
                'id' => 'TPL-XR-CHEST-01',
                'title' => 'Chest X-Ray PA View (Normal)',
                'department' => 'X-Ray',
                'is_private' => 0,
                'content' => "EXAMINATION: CHEST X-RAY (PA VIEW)\n\nCLINICAL INDICATION: Routine / Pre-employment / Pre-operative evaluation\n\nFINDINGS:\n- Lung Fields: Both lung fields are clear and normally aerated. No focal pulmonary infiltration, consolidation, nodule, or mass seen.\n- Pleural Spaces: Both costophrenic and cardiophrenic sulci are sharp and clear. No evidence of pleural effusion or pneumothorax.\n- Cardiac Silhouette: Cardiac size and contour are within normal limits. Normal cardiothoracic ratio (< 50%).\n- Mediastinum & Hila: Mediastinal contours and hila appear normal. Trachea is central in position.\n- Bony Cage & Soft Tissues: Ribs, clavicles, and thoracic cage are unremarkable with no acute bony injury. Soft tissues appear normal.\n\nIMPRESSION:\nNORMAL RADIOLOGICAL STUDY OF CHEST (PA VIEW). NO ACTIVE CARDIOPULMONARY LESION DETECTED.",
            ],
            [
                'id' => 'TPL-XR-CHEST-02',
                'title' => 'Chest X-Ray PA View (Pneumonia / Consolidation)',
                'department' => 'X-Ray',
                'is_private' => 0,
                'content' => "EXAMINATION: CHEST X-RAY (PA VIEW)\n\nCLINICAL INDICATION: Productive cough, fever, shortness of breath\n\nFINDINGS:\n- Lung Fields: Inhomogeneous patchy airspace opacification / consolidation noted in the right lower zone, suspicious of infective etiology. Left lung field is clear.\n- Pleural Spaces: Minimal blunting of the right costophrenic angle. Left costophrenic angle is clear.\n- Cardiac Silhouette: Normal in size and contour.\n- Mediastinum & Hila: Mediastinal contours and hila appear normal.\n- Bony Cage & Soft Tissues: Unremarkable.\n\nIMPRESSION:\nRIGHT LOWER ZONE INHOMOGENEOUS CONSOLIDATION / INFILTRATE, COMPATIBLE WITH PNEUMONIC CONSOLIDATION. CLINICAL AND LAB CORRELATION RECOMMENDED.",
            ],
            [
                'id' => 'TPL-XR-CERV-01',
                'title' => 'Cervical Spine X-Ray (AP & Lateral Views)',
                'department' => 'X-Ray',
                'is_private' => 0,
                'content' => "EXAMINATION: CERVICAL SPINE X-RAY (AP & LATERAL VIEWS)\n\nCLINICAL INDICATION: Neck pain and stiffness radiating to shoulder\n\nFINDINGS:\n- Normal physiological cervical lordosis is preserved / mildly straightened.\n- Vertebral body heights and alignment are maintained. No acute fracture or dislocation.\n- Intervertebral disc spaces at C5-C6 and C6-C7 show mild narrowing with anterior marginal osteophytosis, consistent with early degenerative spondylotic changes.\n- Posterior elements, pedicles, and facet joints appear unremarkable.\n- Prevertebral soft tissue shadow is within normal limits.\n\nIMPRESSION:\nEARLY CERVICAL SPONDYLOSIS WITH C5-C6 AND C6-C7 DISC SPACE NARROWING. NO EVIDENCE OF ACUTE FRACTURE.",
            ],
            [
                'id' => 'TPL-XR-LS-01',
                'title' => 'Lumbosacral (L-S) Spine X-Ray (AP & Lateral Views)',
                'department' => 'X-Ray',
                'is_private' => 0,
                'content' => "EXAMINATION: LUMBOSACRAL (L-S) SPINE X-RAY (AP & LATERAL VIEWS)\n\nCLINICAL INDICATION: Chronic lower back pain / Sciatica\n\nFINDINGS:\n- Normal lumbar lordotic curvature is preserved.\n- Vertebral body heights are maintained throughout the lumbar spine. No acute compression fracture or spondylolisthesis.\n- L4-L5 and L5-S1 intervertebral disc spaces show mild-to-moderate narrowing with marginal osteophytic spurring and endplate sclerosis.\n- Pedicles, laminae, and transverse processes appear intact.\n- Sacroiliac joints are symmetrical with unremarkable joint margins.\n- Psoas shadows are symmetrically visualized.\n\nIMPRESSION:\nLUMBOSACRAL SPONDYLOSIS WITH L4-L5 AND L5-S1 DEGENERATIVE DISC DISEASE. NO ACUTE SKELETAL INJURY.",
            ],
            [
                'id' => 'TPL-XR-KNEE-01',
                'title' => 'Knee Joint X-Ray (AP & Lateral Views)',
                'department' => 'X-Ray',
                'is_private' => 0,
                'content' => "EXAMINATION: KNEE JOINT X-RAY (AP & LATERAL VIEWS)\n\nCLINICAL INDICATION: Knee pain, crepitus on walking\n\nFINDINGS:\n- Bone mineralization is normal.\n- Medial tibiofemoral joint space demonstrates mild-to-moderate narrowing with marginal tibial spine spurring and subchondral sclerosis.\n- Lateral tibiofemoral and patellofemoral compartments are relatively preserved.\n- No evidence of radiopaque loose body, acute fracture, or joint effusion.\n- Soft tissues around the knee joint appear unremarkable.\n\nIMPRESSION:\nPRIMARY OSTEOARTHRITIS OF THE KNEE JOINT (GRADE II KELLGREN-LAWRENCE) WITH MEDIAL COMPARTMENT PREDOMINANCE.",
            ],
            [
                'id' => 'TPL-XR-PELV-01',
                'title' => 'Pelvis AP View X-Ray',
                'department' => 'X-Ray',
                'is_private' => 0,
                'content' => "EXAMINATION: PELVIS WITH BOTH HIPS (AP VIEW)\n\nCLINICAL INDICATION: Hip / Pelvic pain\n\nFINDINGS:\n- Pelvic ring is complete and intact. No fracture, diastasis, or destructive bone lesion.\n- Both femoral heads are spherical and well-seated within the acetabula. Shenton's lines are intact bilaterally.\n- Hip joint spaces are preserved bilaterally with no significant degenerative narrowing or osteophytosis.\n- Sacroiliac and pubic symphysis joints appear normal.\n- Visualized pelvic soft tissues and gas shadow are unremarkable.\n\nIMPRESSION:\nNORMAL RADIOLOGICAL STUDY OF PELVIS AND BOTH HIP JOINTS. NO FRACTURE OR GROSS ARTHRITIC CHANGE.",
            ],
            [
                'id' => 'TPL-XR-ABD-01',
                'title' => 'Abdomen Plain / Erect & Supine X-Ray (KUB)',
                'department' => 'X-Ray',
                'is_private' => 0,
                'content' => "EXAMINATION: ABDOMEN ERECT & SUPINE PLAIN X-RAY (KUB)\n\nCLINICAL INDICATION: Abdominal colic / Rule out obstruction or perforation\n\nFINDINGS:\n- Bowel Gas Pattern: Normal physiological distribution of gas within the stomach and colon. No abnormally dilated small or large bowel loops.\n- Air-Fluid Levels: No multiple or pathological step-ladder air-fluid levels seen.\n- Free Gas: No evidence of pneumoperitoneum (no free crescentic gas under the right or left hemidiaphragms on erect view).\n- Radiopaque Shadows: No obvious radio-opaque calculus visualized along the anatomical course of the urinary tract (KUB).\n- Soft Tissues & Skeleton: Psoas outlines and flank stripes are preserved. Bony pelvis and lower ribs are intact.\n\nIMPRESSION:\nNORMAL ABDOMINAL GAS PATTERN. NO RADIOLOGICAL EVIDENCE OF MECHANICAL BOWEL OBSTRUCTION, CALCULUS, OR PNEUMOPERITONEUM.",
            ],

            // --- 12-LEAD ECG (ELECTROCARDIOGRAM) TEMPLATES ---
            [
                'id' => 'TPL-ECG-01',
                'title' => '12-Lead Electrocardiogram (Normal Sinus Rhythm)',
                'department' => 'ECG',
                'is_private' => 0,
                'content' => "EXAMINATION: 12-LEAD ELECTROCARDIOGRAM (ECG)\n\nCLINICAL INDICATION: Routine cardiac evaluation / Pre-operative screening\n\nMEASUREMENTS & PARAMETERS:\n- Rhythm: Normal Sinus Rhythm\n- Heart Rate: 72 beats per minute (bpm)\n- P Wave: Normal morphology and amplitude (0.08 sec)\n- PR Interval: 0.16 sec (Normal range: 0.12 - 0.20 sec)\n- QRS Duration: 0.08 sec (Normal range: 0.06 - 0.10 sec)\n- QTc Interval: 412 ms (Normal range: < 440 ms for males, < 460 ms for females)\n- Cardiac Axis: Normal axis (+45° to +60°)\n\nREPOLARIZATION & MORPHOLOGY:\n- ST Segment: Isoelectric across all limb and precordial leads; no ST elevation or depression.\n- T Waves: Upright and normal morphology in leads I, II, aVF, and V3-V6. Inverted in aVR.\n- Pathological Q Waves: Absent.\n\nIMPRESSION:\nNORMAL 12-LEAD ELECTROCARDIOGRAM (NORMAL SINUS RHYTHM, RATE 72 BPM, NO EVIDENCE OF ISCHEMIA OR CONDUCTION ABNORMALITY).",
            ],
            [
                'id' => 'TPL-ECG-02',
                'title' => '12-Lead Electrocardiogram (Sinus Tachycardia)',
                'department' => 'ECG',
                'is_private' => 0,
                'content' => "EXAMINATION: 12-LEAD ELECTROCARDIOGRAM (ECG)\n\nCLINICAL INDICATION: Palpitations, anxiety, fever\n\nMEASUREMENTS & PARAMETERS:\n- Rhythm: Sinus Tachycardia\n- Heart Rate: 115 beats per minute (bpm)\n- P Wave: Precedes every QRS complex, normal axis\n- PR Interval: 0.14 sec\n- QRS Duration: 0.08 sec\n- QTc: 420 ms\n- Axis: Normal\n\nREPOLARIZATION:\n- ST Segment: Isoelectric, no significant ST elevation or depression.\n- T Waves: Normal morphology.\n\nIMPRESSION:\nSINUS TACHYCARDIA (RATE 115 BPM). NO ACUTE ST-T WAVE ISCHEMIC CHANGES. CORRELATE CLINICALLY WITH FEVER, ANXIETY, DEHYDRATION, OR THYROID STATUS.",
            ],
            [
                'id' => 'TPL-ECG-03',
                'title' => '12-Lead Electrocardiogram (Ischemic ST-T Changes)',
                'department' => 'ECG',
                'is_private' => 0,
                'content' => "EXAMINATION: 12-LEAD ELECTROCARDIOGRAM (ECG)\n\nCLINICAL INDICATION: Chest tightness, retrosternal discomfort\n\nMEASUREMENTS & PARAMETERS:\n- Rhythm: Normal Sinus Rhythm\n- Heart Rate: 78 bpm\n- PR Interval: 0.16 sec\n- QRS Duration: 0.09 sec\n- Axis: Normal\n\nREPOLARIZATION:\n- ST-T Changes: Horizontal / downsloping ST-segment depression (~1.5 mm) with symmetrical T-wave inversions noted in leads V4, V5, V6, I, and aVL (Anterolateral leads).\n- Inferior leads (II, III, aVF) show flat T waves.\n\nIMPRESSION:\nANTEROLATERAL ST-T CHANGES SUGGESTIVE OF MYOCARDIAL ISCHEMIA. CLINICAL CORRELATION AND CARDIAC ENZYMES (TROPONIN-I) WITH CARDIOLOGIST REVIEW RECOMMENDED.",
            ],
            [
                'id' => 'TPL-ECG-04',
                'title' => '12-Lead Electrocardiogram (Left Ventricular Hypertrophy - LVH)',
                'department' => 'ECG',
                'is_private' => 0,
                'content' => "EXAMINATION: 12-LEAD ELECTROCARDIOGRAM (ECG)\n\nCLINICAL INDICATION: Long-standing systemic hypertension\n\nMEASUREMENTS & PARAMETERS:\n- Rhythm: Normal Sinus Rhythm\n- Heart Rate: 68 bpm\n- Axis: Left Axis Deviation (-15°)\n- Sokolow-Lyon Criteria: S in V1 (18 mm) + R in V5 (22 mm) = 40 mm (Positive for LVH > 35 mm)\n- Cornell Voltage: R in aVL + S in V3 meets criteria for LVH.\n- Repolarization: ST-segment depression and asymmetric T-wave inversion in lateral leads (I, aVL, V5, V6), consistent with LV 'strain' pattern.\n\nIMPRESSION:\nLEFT VENTRICULAR HYPERTROPHY (LVH) WITH SECONDARY REPOLARIZATION (STRAIN) PATTERN. COMPATIBLE WITH HYPERTENSIVE HEART DISEASE. 2D ECHOCARDIOGRAPHY ADVISED.",
            ],

            // --- 2D ECHOCARDIOGRAPHY & COLOR DOPPLER TEMPLATES ---
            [
                'id' => 'TPL-ECHO-01',
                'title' => '2D Echocardiography & Color Doppler (Normal Study - EF 62%)',
                'department' => 'Echocardiography',
                'is_private' => 0,
                'content' => "TRANSTHORACIC 2D ECHOCARDIOGRAPHY & COLOR DOPPLER REPORT\n\nCLINICAL INDICATION: Cardiac assessment / Pre-op fitness / Evaluation\n\nM-MODE & 2D MEASUREMENTS:\n- Left Ventricle Internal Diameter Diastole (LVIDd): 4.6 cm (Normal: 3.5 - 5.6 cm)\n- Left Ventricle Internal Diameter Systole (LVIDs): 2.9 cm (Normal: 2.0 - 4.0 cm)\n- Interventricular Septum Diastole (IVSd): 0.9 cm (Normal: 0.6 - 1.1 cm)\n- Posterior Wall Diastole (PWd): 0.9 cm (Normal: 0.6 - 1.1 cm)\n- Left Atrium Diameter (LA): 3.2 cm (Normal: 1.9 - 4.0 cm)\n- Aortic Root Diameter (Ao): 2.8 cm (Normal: 2.0 - 3.7 cm)\n- Ejection Fraction (EF): 62% (Simpson's biplane, Normal: 55 - 75%)\n- Fractional Shortening (FS): 36% (Normal: 28 - 44%)\n\nCHAMBER SIZES & LV FUNCTION:\n- All four cardiac chambers (LV, RV, LA, RA) are normal in size and dimensions.\n- Left ventricular global and regional systolic function is preserved.\n- No resting regional wall motion abnormality (RWMA) identified.\n- Right ventricular systolic function is normal (TAPSE: 22 mm).\n\nVALVULAR ASSESSMENT & COLOR DOPPLER:\n- Mitral Valve: Leaflets are thin, pliable with normal opening and closure. No mitral stenosis (MS). Trace physiological mitral regurgitation (MR).\n- Aortic Valve: Trileaflet aortic valve with good excursion. No aortic stenosis (AS) or regurgitation (AR).\n- Tricuspid Valve: Normal leaflet morphology. Estimated pulmonary artery systolic pressure (PASP) is normal (~24 mmHg).\n- Pulmonic Valve: Normal.\n\nPERICARDIUM & OTHERS:\n- Pericardium is normal. No pericardial effusion seen.\n- No intracardiac thrombus, vegetative mass, or shunt visualized.\n- Interatrial and interventricular septa appear intact.\n\nIMPRESSION:\n1. NORMAL 2D ECHOCARDIOGRAM WITH COLOR DOPPLER STUDY.\n2. PRESERVED LEFT VENTRICULAR SYSTOLIC FUNCTION (EF = 62%).\n3. NO REGIONAL WALL MOTION ABNORMALITY AT REST.\n4. NORMAL VALVULAR MORPHOLOGY AND FUNCTION.",
            ],
            [
                'id' => 'TPL-ECHO-02',
                'title' => '2D Echocardiogram (Concentric LVH & Grade I Diastolic Dysfunction)',
                'department' => 'Echocardiography',
                'is_private' => 0,
                'content' => "TRANSTHORACIC 2D ECHOCARDIOGRAPHY & COLOR DOPPLER REPORT\n\nCLINICAL INDICATION: Chronic Hypertension / Dyspnea on exertion\n\nM-MODE & 2D MEASUREMENTS:\n- LVIDd: 4.8 cm\n- LVIDs: 3.1 cm\n- IVSd: 1.3 cm (Increased, Normal: 0.6 - 1.1 cm)\n- PWd: 1.2 cm (Increased, Normal: 0.6 - 1.1 cm)\n- Left Atrium (LA): 4.1 cm (Mildly dilated)\n- Ejection Fraction (EF): 60% (Preserved)\n\nCHAMBER SIZES & LV FUNCTION:\n- Concentric Left Ventricular Hypertrophy (LVH) noted with thickened septum and posterior wall.\n- Global LV systolic performance is preserved (EF: 60%).\n- No resting regional wall motion abnormality.\n- Right ventricle and right atrium are normal in size.\n\nDOPPLER & DIASTOLIC FUNCTION:\n- Mitral inflow Doppler demonstrates impaired LV relaxation pattern (E/A ratio < 0.8, prolonged deceleration time), consistent with Grade I (Mild) LV Diastolic Dysfunction.\n- Mild trivial Mitral Regurgitation (MR).\n- Aortic valve shows sclerotic changes without significant gradient.\n- No pericardial effusion or intracardiac mass.\n\nIMPRESSION:\n1. CONCENTRIC LEFT VENTRICULAR HYPERTROPHY (LVH) WITH MILD LEFT ATRIAL ENLARGEMENT.\n2. PRESERVED LV SYSTOLIC FUNCTION (EF = 60%).\n3. GRADE I (IMPAIRED RELAXATION) LV DIASTOLIC DYSFUNCTION.\n4. COMPATIBLE WITH HYPERTENSIVE HEART DISEASE.",
            ],
            [
                'id' => 'TPL-ECHO-03',
                'title' => '2D Echocardiogram (Ischemic Heart Disease / RWMA / Reduced EF 40%)',
                'department' => 'Echocardiography',
                'is_private' => 0,
                'content' => "TRANSTHORACIC 2D ECHOCARDIOGRAPHY & COLOR DOPPLER REPORT\n\nCLINICAL INDICATION: Post-Myocardial Infarction / Ischemic Heart Disease\n\nM-MODE & 2D MEASUREMENTS:\n- LVIDd: 5.6 cm (Dilated)\n- LVIDs: 4.3 cm (Dilated)\n- IVSd: 0.9 cm\n- PWd: 0.9 cm\n- Left Atrium: 4.3 cm (Dilated)\n- Ejection Fraction (EF): 40% (Moderately reduced, Simpson's biplane)\n\nREGIONAL WALL MOTION ANALYSIS (RWMA):\n- Anterior wall, anteroseptum, and apex are hypokinetic to akinetic.\n- Inferior, posterior, and lateral walls demonstrate compensatory hyperkinesia.\n\nVALVULAR ASSESSMENT & DOPPLER:\n- Mild-to-moderate functional Mitral Regurgitation (MR) secondary to annular dilatation.\n- Aortic, tricuspid, and pulmonic valves appear unremarkable.\n- Estimated PASP is mildly elevated (~36 mmHg).\n- No intracardiac clot or LV apical thrombus detected on current study.\n- No pericardial effusion.\n\nIMPRESSION:\n1. DILATED LEFT VENTRICLE WITH MODERATELY REDUCED LV SYSTOLIC FUNCTION (EF = 40%).\n2. REGIONAL WALL MOTION ABNORMALITY (ANTEROSEPTAL AND APICAL HYPOKINESIA / AKINESIA), COMPATIBLE WITH CORONARY ARTERY DISEASE (LAD TERRITORY).\n3. MILD-TO-MODERATE FUNCTIONAL MITRAL REGURGITATION.",
            ],
        ];

        foreach ($allTemplates as $tpl) {
            $existing = $this->db->fetchOne(
                "SELECT id FROM report_templates WHERE id = :id AND organization_id = :org_id",
                ['id' => $tpl['id'], 'org_id' => $orgId]
            );
            if (!$existing) {
                $this->create(array_merge($tpl, ['organization_id' => $orgId, 'created_by' => 'admin']));
            } else {
                $this->update($tpl['id'], $tpl, $orgId);
            }
        }
    }

    public function getAll(?string $orgId = null, ?string $userId = null, string $department = ''): array
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
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

    public function getById(string $id, ?string $orgId = null): ?array
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        return $this->db->fetchOne("SELECT * FROM report_templates WHERE id = :id AND organization_id = :org_id", [
            'id' => $id,
            'org_id' => $orgId,
        ]);
    }

    public function create(array $data): bool
    {
        $id = $data['id'] ?? ('TPL-' . date('ymd') . '-' . rand(100, 999));
        $orgId = $data['organization_id'] ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        $sql = "INSERT INTO report_templates (
            id, organization_id, title, department, content, is_private, created_by, created_at
        ) VALUES (
            :id, :org_id, :title, :department, :content, :is_private, :created_by, NOW()
        )";

        return $this->db->execute($sql, [
            'id' => $id,
            'org_id' => $orgId,
            'title' => trim((string)$data['title']),
            'department' => $data['department'] ?? 'General',
            'content' => trim((string)$data['content']),
            'is_private' => !empty($data['is_private']) ? 1 : 0,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    public function update(string $id, array $data, ?string $orgId = null): bool
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
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

    public function delete(string $id, ?string $orgId = null): bool
    {
        $orgId = $orgId ?? (function_exists('current_org_id') ? current_org_id() : 'ORG-001');
        return $this->db->execute("DELETE FROM report_templates WHERE id = :id AND organization_id = :org_id", [
            'id' => $id,
            'org_id' => $orgId,
        ]);
    }
}
