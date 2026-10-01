-- Production Schema for Health LMS Pro (MariaDB / MySQL)

CREATE TABLE IF NOT EXISTS organizations (
    id            VARCHAR(64) PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS branches (
    id              VARCHAR(64) PRIMARY KEY,
    organization_id VARCHAR(64) NOT NULL,
    code            VARCHAR(32) NOT NULL,
    name            VARCHAR(255) NOT NULL,
    branch_type     ENUM('main_lab','collection_center','imaging') NOT NULL,
    address         TEXT,
    phone           VARCHAR(64),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id              VARCHAR(64) PRIMARY KEY,
    organization_id VARCHAR(64) NOT NULL,
    branch_id       VARCHAR(64) NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    name            VARCHAR(255) NOT NULL,
    role            VARCHAR(64) NOT NULL,
    portal          ENUM('main_lab','collection_center','imaging','admin') NOT NULL,
    is_active       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS patients (
    id              VARCHAR(64) PRIMARY KEY,
    organization_id VARCHAR(64) NOT NULL,
    patient_no      VARCHAR(32) NOT NULL,
    title           VARCHAR(16),
    full_name       VARCHAR(255) NOT NULL,
    relation        VARCHAR(64),
    relation_of     VARCHAR(255),
    phone           VARCHAR(32),
    phone_alt       VARCHAR(32),
    email           VARCHAR(255),
    cnic            VARCHAR(32),
    blood_group     VARCHAR(16),
    dob             DATE NULL,
    age             SMALLINT,
    gender          VARCHAR(16),
    address         TEXT,
    city            VARCHAR(64),
    referring_doctor VARCHAR(255),
    emergency_name  VARCHAR(255),
    emergency_phone VARCHAR(32),
    photo_path      VARCHAR(512),
    internal_notes  TEXT,
    patient_type    VARCHAR(32),
    panel_code      VARCHAR(64),
    branch          VARCHAR(32) DEFAULT 'CC-01',
    created_by      VARCHAR(64),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patients_phone (phone),
    INDEX idx_patients_cnic (cnic),
    INDEX idx_patients_no (patient_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tests (
    id              VARCHAR(64) PRIMARY KEY,
    organization_id VARCHAR(64) NOT NULL,
    code            VARCHAR(32) NOT NULL,
    name            VARCHAR(255) NOT NULL,
    category        VARCHAR(64),
    price           DECIMAL(12,2) NOT NULL,
    sample_type     VARCHAR(64),
    unit            VARCHAR(32),
    normal_range    VARCHAR(64),
    normal_value    VARCHAR(128) NULL,
    reference_value VARCHAR(128) NULL,
    methodology     TEXT NULL,
    result_type     VARCHAR(32),
    result_options  TEXT NULL,
    report_template VARCHAR(64),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tests_code (code),
    INDEX idx_tests_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_parameters (
    id              VARCHAR(64) PRIMARY KEY,
    test_id         VARCHAR(64) NOT NULL,
    section         VARCHAR(128) NULL,
    name            VARCHAR(255) NOT NULL,
    unit            VARCHAR(32),
    normal_value    VARCHAR(128),
    reference_range VARCHAR(128),
    sub_table       TEXT NULL,
    sort_order      INT DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
    INDEX idx_test_params_test (test_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_signatories (
    id              VARCHAR(64) PRIMARY KEY,
    organization_id VARCHAR(64) NOT NULL,
    slot_number     INT NOT NULL DEFAULT 1,
    name            VARCHAR(255) NOT NULL,
    qualifications  TEXT,
    designation     VARCHAR(255),
    is_active       TINYINT(1) DEFAULT 1,
    sort_order      INT DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_signatories_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS packages (
    id              VARCHAR(64) PRIMARY KEY,
    organization_id VARCHAR(64) NOT NULL,
    code            VARCHAR(32) NOT NULL,
    name            VARCHAR(255) NOT NULL,
    tests_included  TEXT NOT NULL,
    price           DECIMAL(12,2) NOT NULL,
    regular_price   DECIMAL(12,2) NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lab_entries (
    id              VARCHAR(64) PRIMARY KEY,
    organization_id VARCHAR(64) NOT NULL,
    branch_id       VARCHAR(64) NULL,
    lab_no          VARCHAR(32) NOT NULL UNIQUE,
    patient_id      VARCHAR(64) NOT NULL,
    patient_name    VARCHAR(255) NOT NULL,
    tests           TEXT NOT NULL,
    doctor          VARCHAR(255) NULL,
    route           VARCHAR(255) NULL,
    priority        VARCHAR(32) DEFAULT 'Normal',
    status          VARCHAR(32) NOT NULL DEFAULT 'pending',
    sample_status   VARCHAR(32) DEFAULT 'pending',
    amount          DECIMAL(12,2) NOT NULL,
    paid            DECIMAL(12,2) DEFAULT 0,
    discount        DECIMAL(12,2) DEFAULT 0,
    branch          VARCHAR(32) DEFAULT 'CC-01',
    clinical_notes  TEXT,
    created_by      VARCHAR(64),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lab_no (lab_no),
    INDEX idx_patient_id (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lab_entry_tests (
    lab_entry_id VARCHAR(64) NOT NULL,
    test_id      VARCHAR(64) NOT NULL,
    price        DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (lab_entry_id, test_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS samples (
    id           VARCHAR(64) PRIMARY KEY,
    lab_no       VARCHAR(32) NOT NULL,
    patient      VARCHAR(255) NOT NULL,
    sample       VARCHAR(64) NOT NULL,
    status       VARCHAR(32) NOT NULL DEFAULT 'received',
    received_at  VARCHAR(64) NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS results (
    id              VARCHAR(64) PRIMARY KEY,
    lab_no          VARCHAR(32) NOT NULL,
    patient         VARCHAR(255) NOT NULL,
    test            VARCHAR(255) NOT NULL,
    due             VARCHAR(64) DEFAULT 'Today',
    section         VARCHAR(128) NULL,
    parameter       VARCHAR(128) NULL,
    value           VARCHAR(64) NULL,
    unit            VARCHAR(32) NULL,
    reference_range VARCHAR(128) NULL,
    sub_table       TEXT NULL,
    flag            VARCHAR(16) NULL,
    sort_order      INT DEFAULT 0,
    print_page      INT NOT NULL DEFAULT 1,
    is_visible      TINYINT(1) NOT NULL DEFAULT 1,
    verified_at     DATETIME NULL,
    verified_by     VARCHAR(64) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_results_lab (lab_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS imaging_scans (
    id           VARCHAR(64) PRIMARY KEY,
    scan_no      VARCHAR(32) NOT NULL UNIQUE,
    patient      VARCHAR(255) NOT NULL,
    patient_id   VARCHAR(64) NULL,
    modality     VARCHAR(64) NOT NULL,
    study        VARCHAR(255) NOT NULL,
    status       VARCHAR(32) NOT NULL DEFAULT 'pending',
    scan_date    DATE NOT NULL,
    radiologist  VARCHAR(255) NULL,
    clinical_notes TEXT NULL,
    findings     TEXT NULL,
    impression   TEXT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lab_settings (
    organization_id VARCHAR(64) PRIMARY KEY,
    lab_name        VARCHAR(255),
    address         TEXT,
    phone           VARCHAR(64),
    email           VARCHAR(255),
    header_text     TEXT,
    footer_text     TEXT,
    logo_text       VARCHAR(32),
    bill_header_text TEXT,
    bill_footer_text TEXT,
    header_image     LONGBLOB NULL,
    header_image_mime VARCHAR(64) NULL,
    header_image_ver INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS collection_centers (
    id              VARCHAR(64) PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    code            VARCHAR(32) NOT NULL UNIQUE,
    patients_today  INT DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS waste_records (
    id              VARCHAR(64) PRIMARY KEY,
    organization_id VARCHAR(64) NOT NULL,
    title           VARCHAR(255) NOT NULL,
    notes           TEXT,
    record_date     DATE NOT NULL,
    mou_image       LONGBLOB NULL,
    mou_image_mime  VARCHAR(64) NULL,
    slip_image      LONGBLOB NULL,
    slip_image_mime VARCHAR(64) NULL,
    created_by      VARCHAR(128) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_waste_org_date (organization_id, record_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS doctor_shares (
    id                  VARCHAR(64) PRIMARY KEY,
    organization_id     VARCHAR(64) NOT NULL,
    doctor_name         VARCHAR(255) NOT NULL,
    commission_percent  DECIMAL(5,2) NOT NULL DEFAULT 0,
    notes               TEXT,
    is_active           TINYINT(1) DEFAULT 1,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_doctor_shares_org (organization_id),
    UNIQUE KEY uq_doctor_share_org_name (organization_id, doctor_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
