<?php

declare(strict_types=1);

$GLOBALS['mock_patients'] = [
    [
        'id' => 'P-10482',
        'title' => 'Mrs',
        'name' => 'Ayesha Khan',
        'relation' => 'Self',
        'phone' => '0300-1122334',
        'age' => 34,
        'gender' => 'Female',
        'cnic' => '35202-1234567-1',
        'blood_group' => 'B+',
        'email' => 'ayesha.k@email.com',
        'address' => 'House 12, Block C, Gulberg III, Faisalabad',
        'notes' => 'Prefers morning slots',
        'registered' => '2026-09-16',
        'branch' => 'CC-01',
    ],
    [
        'id' => 'P-10481',
        'title' => 'Mr',
        'name' => 'Muhammad Ali',
        'relation' => 'Self',
        'phone' => '0321-9988776',
        'age' => 45,
        'gender' => 'Male',
        'cnic' => '35201-7654321-9',
        'blood_group' => 'O+',
        'email' => '',
        'address' => '45 Model Town Link Road, Faisalabad',
        'notes' => '',
        'registered' => '2026-09-16',
        'branch' => 'CC-01',
    ],
    [
        'id' => 'P-10480',
        'title' => 'Ms',
        'name' => 'Sana Ahmed',
        'relation' => 'Daughter of',
        'phone' => '0333-4455667',
        'age' => 28,
        'gender' => 'Female',
        'cnic' => '35203-9876543-2',
        'blood_group' => 'A+',
        'email' => 'sana.ahmed@email.com',
        'address' => 'Flat 4B, Johar Town, Faisalabad',
        'notes' => 'Corporate account: Apex Foods',
        'registered' => '2026-09-15',
        'branch' => 'CC-02',
    ],
    [
        'id' => 'P-10479',
        'title' => 'Mr',
        'name' => 'Hassan Raza',
        'relation' => 'Self',
        'phone' => '0345-2233445',
        'age' => 52,
        'gender' => 'Male',
        'cnic' => '35204-1122334-4',
        'blood_group' => 'AB+',
        'email' => 'hassan.raza@email.com',
        'address' => '19 DHA Phase 5, Faisalabad',
        'notes' => 'Diabetic — fasting tests only',
        'registered' => '2026-09-15',
        'branch' => 'CC-01',
    ],
    [
        'id' => 'P-10478',
        'title' => 'Baby',
        'name' => 'Ahmed Malik',
        'relation' => 'Son of',
        'phone' => '0301-5566778',
        'age' => 2,
        'gender' => 'Male',
        'cnic' => '',
        'blood_group' => 'Unknown',
        'email' => '',
        'address' => 'Canal Road, Faisalabad',
        'notes' => 'Pediatric draw — experienced phlebotomist',
        'registered' => '2026-09-14',
        'branch' => 'CC-01',
    ],
];

$GLOBALS['mock_doctors'] = [
    ['id' => 'D-01', 'name' => 'Dr. Imran Sheikh', 'specialty' => 'General Physician'],
    ['id' => 'D-02', 'name' => 'Dr. Fatima Noor', 'specialty' => 'Gynecologist'],
    ['id' => 'D-03', 'name' => 'Dr. Usman Malik', 'specialty' => 'Cardiologist'],
    ['id' => 'D-04', 'name' => 'Dr. Nadia Hussain', 'specialty' => 'Endocrinologist'],
    ['id' => 'D-05', 'name' => 'Walk-in / Self', 'specialty' => '—'],
];

$GLOBALS['mock_tests'] = [
    ['code' => 'CBC', 'name' => 'Complete Blood Count', 'category' => 'Hematology', 'price' => 1200, 'sample' => 'Blood', 'unit' => '—', 'range' => '—'],
    ['code' => 'HB', 'name' => 'Hemoglobin', 'category' => 'Hematology', 'price' => 350, 'sample' => 'Blood', 'unit' => 'g/dL', 'range' => '13–17'],
    ['code' => 'ESR', 'name' => 'ESR', 'category' => 'Hematology', 'price' => 400, 'sample' => 'Blood', 'unit' => 'mm/hr', 'range' => '0–20'],
    ['code' => 'FBS', 'name' => 'Fasting Blood Sugar', 'category' => 'Biochemistry', 'price' => 450, 'sample' => 'Blood', 'unit' => 'mg/dL', 'range' => '70–100'],
    ['code' => 'RBS', 'name' => 'Random Blood Sugar', 'category' => 'Biochemistry', 'price' => 400, 'sample' => 'Blood', 'unit' => 'mg/dL', 'range' => '70–140'],
    ['code' => 'LFT', 'name' => 'Liver Function Test', 'category' => 'Biochemistry', 'price' => 2800, 'sample' => 'Blood', 'unit' => '—', 'range' => '—'],
    ['code' => 'RFT', 'name' => 'Renal Function Test', 'category' => 'Biochemistry', 'price' => 2500, 'sample' => 'Blood', 'unit' => '—', 'range' => '—'],
    ['code' => 'LIPID', 'name' => 'Lipid Profile', 'category' => 'Biochemistry', 'price' => 2200, 'sample' => 'Blood', 'unit' => '—', 'range' => '—'],
    ['code' => 'TFT', 'name' => 'Thyroid Profile (T3/T4/TSH)', 'category' => 'Hormones', 'price' => 3200, 'sample' => 'Blood', 'unit' => '—', 'range' => '—'],
    ['code' => 'UDR', 'name' => 'Urine Complete (DR)', 'category' => 'Clinical Pathology', 'price' => 500, 'sample' => 'Urine', 'unit' => '—', 'range' => '—'],
    ['code' => 'CXR', 'name' => 'Chest X-Ray PA', 'category' => 'Radiology', 'price' => 1800, 'sample' => '—', 'unit' => '—', 'range' => '—'],
];

$GLOBALS['mock_packages'] = [
    [
        'code' => 'PKG-01',
        'name' => 'Basic Health Panel',
        'tests' => 'CBC, FBS, Urine DR',
        'price' => 2200,
        'regular' => 2550,
    ],
    [
        'code' => 'PKG-02',
        'name' => 'Executive Checkup',
        'tests' => 'CBC, LFT, Lipid Profile, RFT',
        'price' => 6500,
        'regular' => 8700,
    ],
    [
        'code' => 'PKG-03',
        'name' => 'Diabetes Screen',
        'tests' => 'FBS, HbA1c, Urine DR',
        'price' => 2800,
        'regular' => 3200,
    ],
    [
        'code' => 'PKG-04',
        'name' => 'Thyroid + CBC',
        'tests' => 'TFT, CBC',
        'price' => 3800,
        'regular' => 4400,
    ],
];

$GLOBALS['mock_routes'] = [
    ['id' => 'RT-MAIN', 'label' => 'Main Lab — Pathology (HQ)'],
    ['id' => 'RT-BIO', 'label' => 'Main Lab — Biochemistry Bench'],
    ['id' => 'RT-HEMA', 'label' => 'Main Lab — Hematology Bench'],
    ['id' => 'RT-IMG', 'label' => 'Radiology / CT Suite'],
    ['id' => 'RT-OUT', 'label' => 'External referral lab'],
];

$GLOBALS['mock_lab_entries'] = [
    ['lab_no' => 'L-2026-0891', 'patient' => 'Ayesha Khan', 'patient_id' => 'P-10482', 'tests' => 'CBC, FBS', 'status' => 'pending', 'sample_status' => 'pending', 'amount' => 1650, 'paid' => 1650, 'discount' => 0, 'doctor' => 'Dr. Fatima Noor', 'route' => 'Main Lab — Pathology', 'priority' => 'Normal', 'date' => '2026-09-16', 'branch' => 'CC-01'],
    ['lab_no' => 'L-2026-0890', 'patient' => 'Muhammad Ali', 'patient_id' => 'P-10481', 'tests' => 'LFT', 'status' => 'collected', 'sample_status' => 'collected', 'amount' => 2800, 'paid' => 1500, 'discount' => 200, 'doctor' => 'Dr. Usman Malik', 'route' => 'Main Lab — Biochemistry', 'priority' => 'Urgent', 'date' => '2026-09-16', 'branch' => 'CC-01'],
    ['lab_no' => 'L-2026-0889', 'patient' => 'Sana Ahmed', 'patient_id' => 'P-10480', 'tests' => 'Basic Health Panel', 'status' => 'completed', 'sample_status' => 'received', 'amount' => 2200, 'paid' => 2200, 'discount' => 0, 'doctor' => 'Walk-in / Self', 'route' => 'Main Lab — Pathology', 'priority' => 'Normal', 'date' => '2026-09-15', 'branch' => 'CC-02'],
    ['lab_no' => 'L-2026-0888', 'patient' => 'Hassan Raza', 'patient_id' => 'P-10479', 'tests' => 'FBS, HB', 'status' => 'critical', 'sample_status' => 'completed', 'amount' => 800, 'paid' => 800, 'discount' => 0, 'doctor' => 'Dr. Nadia Hussain', 'route' => 'Main Lab — Biochemistry', 'priority' => 'STAT', 'date' => '2026-09-15', 'branch' => 'CC-01'],
    ['lab_no' => 'L-2026-0887', 'patient' => 'Ahmed Malik', 'patient_id' => 'P-10478', 'tests' => 'CBC', 'status' => 'pending', 'sample_status' => 'pending', 'amount' => 1200, 'paid' => 0, 'discount' => 0, 'doctor' => 'Dr. Imran Sheikh', 'route' => 'Main Lab — Hematology', 'priority' => 'Normal', 'date' => '2026-09-14', 'branch' => 'CC-01'],
];

$GLOBALS['mock_samples'] = [
    ['id' => 'S-441', 'lab_no' => 'L-2026-0891', 'patient' => 'Ayesha Khan', 'sample' => 'Blood', 'status' => 'received', 'received_at' => '2026-09-16 09:12'],
    ['id' => 'S-440', 'lab_no' => 'L-2026-0890', 'patient' => 'Muhammad Ali', 'sample' => 'Blood', 'status' => 'processing', 'received_at' => '2026-09-16 08:45'],
    ['id' => 'S-439', 'lab_no' => 'L-2026-0889', 'patient' => 'Sana Ahmed', 'sample' => 'Blood', 'status' => 'completed', 'received_at' => '2026-09-15 16:20'],
];

$GLOBALS['mock_results_pending'] = [
    ['lab_no' => 'L-2026-0891', 'patient' => 'Ayesha Khan', 'test' => 'CBC', 'due' => 'Today'],
    ['lab_no' => 'L-2026-0890', 'patient' => 'Muhammad Ali', 'test' => 'LFT', 'due' => 'Today'],
];

$GLOBALS['mock_imaging'] = [
    ['scan_no' => 'XR-2026-120', 'patient' => 'Hassan Raza', 'modality' => 'X-Ray', 'study' => 'Chest PA', 'status' => 'pending', 'date' => '2026-09-16'],
    ['scan_no' => 'CT-2026-045', 'patient' => 'Ayesha Khan', 'modality' => 'CT Scan', 'study' => 'Brain Plain', 'status' => 'completed', 'date' => '2026-09-15'],
];

$GLOBALS['mock_lab_settings'] = [
    'name' => 'City Diagnostic Laboratory',
    'address' => '12-A Main Boulevard, Faisalabad',
    'phone' => '+92 42 111 222 333',
    'email' => 'reports@citylab.pk',
    'header' => 'City Diagnostic Laboratory — Accredited Pathology Services',
    'footer' => 'This report is electronically verified. For queries call reception.',
    'logo_text' => 'CDL',
];

$GLOBALS['mock_collection_centers'] = [
    ['name' => 'Gulberg Collection Point', 'code' => 'CC-01', 'patients_today' => 18],
    ['name' => 'Model Town Collection Point', 'code' => 'CC-02', 'patients_today' => 14],
];

function mock(string $key): array
{
    return $GLOBALS[$key] ?? [];
}
