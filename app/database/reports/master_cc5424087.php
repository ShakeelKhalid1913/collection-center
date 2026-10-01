<?php

declare(strict_types=1);

/**
 * Patient report data — MASTER / CC5424087
 * Return shape consumed by import_patient_report.php
 */
return [
    'patient' => [
        'title' => 'Master',
        'full_name' => 'MASTER',
        'mr_no' => 'MR0765',
        'patient_no' => 'MR0765',
        'age' => 28,
        'gender' => 'Male',
        'fh_name' => 'K.M',
        'relation_of' => 'K.M',
        'contact' => '03436977690',
        'phone' => '03436977690',
        'cnic' => '',
        'referred_by' => '',
    ],
    'visit' => [
        'lab_no' => 'CC5424087',
        'doctor' => 'Walk-in / Self',
        'branch' => 'Collection Center',
        'route' => 'Laboratory — Biochemistry',
        'priority' => 'Normal',
        'specimen' => 'SERUM',
        'registered_on' => '09/30/2026 19:41',
        'received_on' => '09/30/2026 19:44',
        'reported_on' => '09/30/2026 07:45',
        'reported_by' => 'Imran Afzal',
        'clinical_notes' => '',
        // Optional print page grouping (test name => page)
        'page_map' => [
            'CRP QNT' => 1,
            'LIPID PROFILE' => 1,
            'RFTs (Renal Function Tests)' => 1,
            'SERUM ELECTROLYTES' => 1,
            'HBA1C (Glycated Hemoglobin)' => 2,
            'SEMEN ANALYSIS' => 2,
        ],
    ],
    'panels' => [
        [
            'test' => 'CRP QNT',
            'parameters' => [
                [
                    'section' => 'BIOCHEMISTRY',
                    'name' => 'C.REACTIVE PROTEIN (CRP)',
                    'unit' => 'mg/L',
                    'range' => '< 06',
                    'value' => '',
                    'sub_table' => "Neonates: <4.1\nChildren (2Months – 15 Y): <2.8\nAdults: <5\nFemale (50-64 Y): <8.5\nFemale >65 Y: <6.6\nMale (50-64 Y): <7.9\nMale >65 Y: <6.8",
                ],
            ],
        ],
        [
            'test' => 'LIPID PROFILE',
            'parameters' => [
                ['section' => 'LIPID PROFILE', 'name' => 'Cholesterol', 'unit' => 'mg/dl', 'range' => '140 - 200', 'value' => '186'],
                ['section' => 'LIPID PROFILE', 'name' => 'Triglycerides', 'unit' => 'mg/dl', 'range' => '50 - 150', 'value' => '182', 'flag' => 'H'],
                ['section' => 'LIPID PROFILE', 'name' => 'HDL Cholesterol', 'unit' => 'mg/dl', 'range' => '35 - 65', 'value' => '42'],
                ['section' => 'LIPID PROFILE', 'name' => 'LDL Cholesterol', 'unit' => 'mg/dl', 'range' => '50 - 150', 'value' => '108'],
                ['section' => 'LIPID PROFILE', 'name' => 'VLDL Cholesterol', 'unit' => 'mg/dl', 'range' => '0 - 30', 'value' => '36', 'flag' => 'H'],
            ],
        ],
        [
            'test' => 'RFTs (Renal Function Tests)',
            'parameters' => [
                ['section' => 'RENAL FUNCTION', 'name' => 'BUN (Blood Urea Nitrogen)', 'unit' => 'mg/dl', 'range' => '7 - 20', 'value' => ''],
                ['section' => 'RENAL FUNCTION', 'name' => 'BLOOD UREA', 'unit' => 'mg/dl', 'range' => '10 - 50', 'value' => ''],
                ['section' => 'RENAL FUNCTION', 'name' => 'SERUM CREATININE', 'unit' => 'mg/dl', 'range' => '0.6 - 1.1', 'value' => ''],
            ],
        ],
        [
            'test' => 'SERUM ELECTROLYTES',
            'parameters' => [
                ['section' => 'SERUM ELECTROLYTES', 'name' => 'SERUM SODIUM', 'unit' => 'meq/L', 'range' => '135 - 145', 'value' => '136'],
                ['section' => 'SERUM ELECTROLYTES', 'name' => 'SERUM POTASSIUM', 'unit' => 'meq/L', 'range' => '3.5 - 5.4', 'value' => '3.5'],
                ['section' => 'SERUM ELECTROLYTES', 'name' => 'SERUM CHLORIDE', 'unit' => 'meq/L', 'range' => '97 - 111', 'value' => '98'],
                ['section' => 'SERUM ELECTROLYTES', 'name' => 'SERUM BICARBONATE', 'unit' => 'meq/L', 'range' => '24 - 33', 'value' => '26'],
            ],
        ],
        [
            'test' => 'HBA1C (Glycated Hemoglobin)',
            'parameters' => [
                [
                    'section' => 'HBA1C',
                    'name' => 'HBA1C',
                    'unit' => '%',
                    'range' => '4.0 - 6.5',
                    'value' => '8.2',
                    'flag' => 'H',
                    'sub_table' => "Normal: <5.7%\nPrediabetes: 5.7–6.4%\nDiabetes: ≥6.5%",
                ],
            ],
        ],
        [
            'test' => 'SEMEN ANALYSIS',
            'parameters' => [
                ['section' => 'Specimen Details', 'name' => 'Place of collection', 'unit' => '', 'range' => 'Home', 'value' => 'Home'],
                ['section' => 'Physical Examination', 'name' => 'Volume', 'unit' => 'ml', 'range' => '2 - 6', 'value' => '2'],
                ['section' => 'Physical Examination', 'name' => 'Liquefaction time', 'unit' => 'min', 'range' => '30 minutes', 'value' => '30'],
                ['section' => 'Physical Examination', 'name' => 'Color', 'unit' => '', 'range' => 'Grayish white to pale yellow', 'value' => 'Grayish white'],
                ['section' => 'Physical Examination', 'name' => 'Consistency', 'unit' => '', 'range' => 'Viscid', 'value' => 'Viscid'],
                ['section' => 'Physical Examination', 'name' => 'PH', 'unit' => '', 'range' => '7.0 - 8.0', 'value' => '8.0'],
                ['section' => 'Sperm Count', 'name' => 'Total Sperm Count', 'unit' => 'Million/ml', 'range' => '40 - 150', 'value' => '50'],
                ['section' => 'Motility', 'name' => 'Active (progressive)', 'unit' => '%', 'range' => '> 40%', 'value' => '50'],
                ['section' => 'Motility', 'name' => 'Sluggish (non-progressive)', 'unit' => '%', 'range' => '', 'value' => '25'],
                ['section' => 'Motility', 'name' => 'Dead (immotile)', 'unit' => '%', 'range' => '', 'value' => '25'],
                ['section' => 'Morphology', 'name' => 'Abnormal', 'unit' => '%', 'range' => '', 'value' => '30'],
                ['section' => 'Morphology', 'name' => 'Head defect', 'unit' => '%', 'range' => '', 'value' => '25'],
                ['section' => 'Morphology', 'name' => 'Tail defect', 'unit' => '%', 'range' => '', 'value' => '25'],
                ['section' => 'Microscopic Examination', 'name' => 'Pus Cells', 'unit' => '', 'range' => '0 - 4', 'value' => '2-3'],
                ['section' => 'Microscopic Examination', 'name' => 'Epithelial Cells', 'unit' => '', 'range' => '', 'value' => 'Rare'],
            ],
        ],
    ],
];
