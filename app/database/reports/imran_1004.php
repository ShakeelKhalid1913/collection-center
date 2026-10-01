<?php

declare(strict_types=1);

/**
 * Patient report data — IMRAN / Lab No: 1004 / MR No: MR0747
 * Return shape consumed by import_patient_report.php
 */
return [
    'patient' => [
        'title' => 'Mr.',
        'full_name' => 'IMRAN',
        'mr_no' => 'MR0747',
        'patient_no' => 'MR0747',
        'age' => 28,
        'gender' => 'Male',
        'fh_name' => 'G.M',
        'relation_of' => 'G.M',
        'contact' => '03436977690',
        'phone' => '03436977690',
        'cnic' => '',
        'referred_by' => 'OPD',
    ],
    'visit' => [
        'lab_no' => '1004',
        'doctor' => 'OPD',
        'branch' => 'Collection Center',
        'route' => 'Laboratory — Clinical Pathology',
        'priority' => 'Normal',
        'specimen' => 'Blood',
        'registered_on' => '09/29/2026 18:17',
        'received_on' => '09/29/2026 18:21',
        'reported_on' => '09/29/2026 06:23',
        'reported_by' => 'Imran Afzal',
        'clinical_notes' => 'Specimen Taken: Taken In Lab',
        // Print page layout mapping
        'page_map' => [
            'DEPARTMENT OF MICROBIOLOGY REPORT' => 1,
            'HBV PCR QUANTITATIVE' => 1,
            '24 Hours Urine for Copper' => 1,
            'UCE (Urine Complete Examination)' => 2,
            'MILK CE (Complete Examination)' => 2,
        ],
    ],
    'panels' => [
        [
            'test' => 'DEPARTMENT OF MICROBIOLOGY REPORT',
            'parameters' => [
                [
                    'section' => 'CULTURE & SENSITIVITY',
                    'name' => 'Specimen',
                    'unit' => '',
                    'range' => 'Blood',
                    'value' => 'Blood',
                ],
                [
                    'section' => 'CULTURE & SENSITIVITY',
                    'name' => 'Culture Growth',
                    'unit' => '',
                    'range' => 'No Growth',
                    'value' => 'No Growth After 48 Hours Incubation',
                ],
                [
                    'section' => 'CULTURE & SENSITIVITY',
                    'name' => 'Gram Stain',
                    'unit' => '',
                    'range' => 'Negative',
                    'value' => 'No micro-organism seen',
                ],
            ],
        ],
        [
            'test' => 'HBV PCR QUANTITATIVE',
            'methodology' => "Methodologies:\nHBV Real Time PCR (RT-PCR) is a qualitative and quantitative test for the detection of HBV DNA in cell free biological specimens. DNA was extracted from specimen and amplified by BIORAD CFX96 touch real-time Polymerase Chain Reaction (RT-PCR) using Healgen Hepatitis B Virus (HBV) PCR Kit.\n\nComments:\nHBV DNA may be detectable in absence of anti-HBV antibody titers in case of early diagnosis within the window period of infection. The viral titers may fluctuate therefore a single negative HBV test should not be used to rule out Hepatitis Virus infection. Correlation of result with clinical features and other laboratories findings is highly recommended.\n\nInterpretation:\nThis assay has a result range of 10 to 1,000,000,000 IU/mL (1.00 log to 9.00 log IU/mL) for quantification of hepatitis B virus (HBV) DNA in serum.\nDetected: >10 IU/ml\nNot-Detected: <10 IU/ml",
            'parameters' => [
                [
                    'section' => 'MOLECULAR BIOLOGY',
                    'name' => 'HBV RNA by Real-Time PCR',
                    'unit' => '',
                    'range' => 'Not Detected',
                    'value' => 'Not Detected',
                ],
                [
                    'section' => 'MOLECULAR BIOLOGY',
                    'name' => 'VIRAL LOAD',
                    'unit' => 'IU/ml',
                    'range' => 'See below',
                    'value' => '10',
                ],
            ],
        ],
        [
            'test' => '24 Hours Urine for Copper',
            'parameters' => [
                [
                    'section' => 'SPECIAL CHEMISTRY',
                    'name' => '24 Hours Urine Copper',
                    'unit' => 'mcg',
                    'range' => '10 - 30',
                    'value' => '28',
                ],
                [
                    'section' => 'SPECIAL CHEMISTRY',
                    'name' => 'Total Urine Volume',
                    'unit' => 'ml',
                    'range' => '-',
                    'value' => '500',
                ],
            ],
        ],
        [
            'test' => 'UCE (Urine Complete Examination)',
            'parameters' => [
                // Physical Examination
                ['section' => 'PHYSICAL EXAMINATION', 'name' => 'Color', 'unit' => '', 'range' => 'Pale Yellow', 'value' => 'Yellow'],
                ['section' => 'PHYSICAL EXAMINATION', 'name' => 'Reaction', 'unit' => '', 'range' => 'Acidic / Alkaline', 'value' => 'Acidic'],
                ['section' => 'PHYSICAL EXAMINATION', 'name' => 'Deposit', 'unit' => '', 'range' => 'Clear', 'value' => 'Clear'],
                ['section' => 'PHYSICAL EXAMINATION', 'name' => 'Turbidity', 'unit' => '', 'range' => 'Clear', 'value' => 'Clear'],
                // Chemical Examination
                ['section' => 'CHEMICAL EXAMINATION', 'name' => 'Sp. Gravity', 'unit' => '', 'range' => '1.002 – 1.030', 'value' => '1.015'],
                ['section' => 'CHEMICAL EXAMINATION', 'name' => 'PH', 'unit' => '', 'range' => '4.0 – 8.0', 'value' => '6.0'],
                ['section' => 'CHEMICAL EXAMINATION', 'name' => 'Leukocytes', 'unit' => '', 'range' => 'Negative', 'value' => 'Negative'],
                ['section' => 'CHEMICAL EXAMINATION', 'name' => 'Albumin', 'unit' => '', 'range' => 'Negative', 'value' => 'Negative'],
                ['section' => 'CHEMICAL EXAMINATION', 'name' => 'Bilirubin', 'unit' => '', 'range' => 'Negative', 'value' => 'Negative'],
                ['section' => 'CHEMICAL EXAMINATION', 'name' => 'Urobilinogen', 'unit' => '', 'range' => '< 2.0', 'value' => 'Negative'],
                ['section' => 'CHEMICAL EXAMINATION', 'name' => 'Keytone', 'unit' => '', 'range' => 'Negative', 'value' => 'Negative'],
                ['section' => 'CHEMICAL EXAMINATION', 'name' => 'Sugar', 'unit' => '', 'range' => 'Negative', 'value' => 'Negative'],
                ['section' => 'CHEMICAL EXAMINATION', 'name' => 'Blood', 'unit' => '', 'range' => 'Negative', 'value' => 'Negative'],
                // Microscopic Examination
                ['section' => 'MICROSCOPIC EXAMINATION', 'name' => 'Pus cells', 'unit' => '/HPF', 'range' => '< 5', 'value' => 'Nil'],
                ['section' => 'MICROSCOPIC EXAMINATION', 'name' => 'RBC’s', 'unit' => '/HPF', 'range' => '< 5', 'value' => 'Nil'],
                ['section' => 'MICROSCOPIC EXAMINATION', 'name' => 'Epithelial cells', 'unit' => '/HPF', 'range' => 'Nil', 'value' => 'Nil'],
                ['section' => 'MICROSCOPIC EXAMINATION', 'name' => 'Cast', 'unit' => '/HPF', 'range' => 'Nil', 'value' => 'Nil'],
                ['section' => 'MICROSCOPIC EXAMINATION', 'name' => 'Crystals', 'unit' => '/HPF', 'range' => 'Nil', 'value' => 'Nil'],
                ['section' => 'MICROSCOPIC EXAMINATION', 'name' => 'Bacteria', 'unit' => '/HPF', 'range' => 'Nil', 'value' => 'Nil'],
                ['section' => 'MICROSCOPIC EXAMINATION', 'name' => 'Others', 'unit' => '', 'range' => '-', 'value' => '-'],
            ],
        ],
        [
            'test' => 'MILK CE (Complete Examination)',
            'parameters' => [
                // Right Breast Milk - Physical
                ['section' => 'Physical Examination (Right)', 'name' => 'Color (Right)', 'unit' => '', 'range' => 'Milky White', 'value' => 'Milky White'],
                ['section' => 'Physical Examination (Right)', 'name' => 'SP. Gravity (Right)', 'unit' => '', 'range' => '-', 'value' => '1.005'],
                ['section' => 'Physical Examination (Right)', 'name' => 'PH (Right)', 'unit' => '', 'range' => '-', 'value' => '8.0'],
                // Right Breast Milk - Chemical
                ['section' => 'Chemical Examination (Right)', 'name' => 'Albumin (Right)', 'unit' => '', 'range' => 'Nil', 'value' => 'Nil'],
                ['section' => 'Chemical Examination (Right)', 'name' => 'Blood (Right)', 'unit' => '', 'range' => 'Nil', 'value' => 'Nil'],
                ['section' => 'Chemical Examination (Right)', 'name' => 'Sugar (Right)', 'unit' => '', 'range' => 'Nil', 'value' => 'Nil'],
                ['section' => 'Chemical Examination (Right)', 'name' => 'Leukocytes (Right)', 'unit' => '', 'range' => 'Nil', 'value' => 'Nil'],
                // Right Breast Milk - Microscopic
                ['section' => 'Microscopic Examination (Right)', 'name' => 'Pus Cells (Right)', 'unit' => 'HPF', 'range' => '< 5', 'value' => 'Nil'],
                ['section' => 'Microscopic Examination (Right)', 'name' => 'RBC’s (Right)', 'unit' => 'HPF', 'range' => '< 5', 'value' => 'Nil'],
                ['section' => 'Microscopic Examination (Right)', 'name' => 'Fat Cells (Right)', 'unit' => '', 'range' => 'Nil', 'value' => 'Nil'],

                // Left Breast Milk - Physical
                ['section' => 'Physical Examination (Left)', 'name' => 'Color (Left)', 'unit' => '', 'range' => 'Milky White', 'value' => 'Milky White'],
                ['section' => 'Physical Examination (Left)', 'name' => 'SP. Gravity (Left)', 'unit' => '', 'range' => '-', 'value' => '1.005'],
                ['section' => 'Physical Examination (Left)', 'name' => 'PH (Left)', 'unit' => '', 'range' => '-', 'value' => '8.0'],
                // Left Breast Milk - Chemical
                ['section' => 'Chemical Examination (Left)', 'name' => 'Albumin (Left)', 'unit' => '', 'range' => 'Nil', 'value' => 'Nil'],
                ['section' => 'Chemical Examination (Left)', 'name' => 'Blood (Left)', 'unit' => '', 'range' => 'Nil', 'value' => 'Nil'],
                ['section' => 'Chemical Examination (Left)', 'name' => 'Sugar (Left)', 'unit' => '', 'range' => 'Nil', 'value' => 'Nil'],
                ['section' => 'Chemical Examination (Left)', 'name' => 'Leukocytes (Left)', 'unit' => '', 'range' => 'Nil', 'value' => 'Nil'],
                // Left Breast Milk - Microscopic
                ['section' => 'Microscopic Examination (Left)', 'name' => 'Pus Cells (Left)', 'unit' => 'HPF', 'range' => '< 5', 'value' => '3.4'],
                ['section' => 'Microscopic Examination (Left)', 'name' => 'RBC’s (Left)', 'unit' => 'HPF', 'range' => '< 5', 'value' => 'Nil'],
                ['section' => 'Microscopic Examination (Left)', 'name' => 'Fat Cells (Left)', 'unit' => '', 'range' => 'Nil', 'value' => 'Nil'],
            ],
        ],
    ],
];
