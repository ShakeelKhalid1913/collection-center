-- LabFlow LMS — MySQL schema sketch (Phase 2 backend)
-- MVP UI uses data/mock.php until this is wired up.

CREATE TABLE organizations (
    id            CHAR(36) PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE branches (
    id              CHAR(36) PRIMARY KEY,
    organization_id CHAR(36) NOT NULL,
    code            VARCHAR(32) NOT NULL,
    name            VARCHAR(255) NOT NULL,
    branch_type     ENUM('main_lab','collection_center','imaging') NOT NULL,
    address         TEXT,
    phone           VARCHAR(64),
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
);

CREATE TABLE users (
    id              CHAR(36) PRIMARY KEY,
    organization_id CHAR(36) NOT NULL,
    branch_id       CHAR(36) NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    name            VARCHAR(255) NOT NULL,
    role            VARCHAR(64) NOT NULL,
    portal          ENUM('main_lab','collection_center','imaging','admin') NOT NULL,
    is_active       TINYINT(1) DEFAULT 1
);

CREATE TABLE patients (
    id              CHAR(36) PRIMARY KEY,
    organization_id CHAR(36) NOT NULL,
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
    emergency_name  VARCHAR(255),
    emergency_phone VARCHAR(32),
    photo_path      VARCHAR(512),
    internal_notes  TEXT,
    patient_type    VARCHAR(32),
    panel_code      VARCHAR(64),
    created_by      CHAR(36),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patients_org_phone (organization_id, phone),
    INDEX idx_patients_cnic (organization_id, cnic)
);

CREATE TABLE tests (
    id              CHAR(36) PRIMARY KEY,
    organization_id CHAR(36) NOT NULL,
    code            VARCHAR(32) NOT NULL,
    name            VARCHAR(255) NOT NULL,
    category        VARCHAR(64),
    price           DECIMAL(12,2) NOT NULL,
    sample_type     VARCHAR(64),
    unit            VARCHAR(32),
    normal_range    VARCHAR(64),
    result_type     VARCHAR(32),
    report_template VARCHAR(64)
);

CREATE TABLE lab_entries (
    id              CHAR(36) PRIMARY KEY,
    organization_id CHAR(36) NOT NULL,
    branch_id       CHAR(36) NOT NULL,
    lab_no          VARCHAR(32) NOT NULL UNIQUE,
    patient_id      CHAR(36) NOT NULL,
    doctor_id       CHAR(36) NULL,
    external_doctor VARCHAR(255) NULL,
    route_id        VARCHAR(64) NULL,
    priority        VARCHAR(32) DEFAULT 'Normal',
    status          VARCHAR(32) NOT NULL,
    subtotal        DECIMAL(12,2) NOT NULL,
    discount        DECIMAL(12,2) DEFAULT 0,
    discount_pct    DECIMAL(5,2) DEFAULT 0,
    total           DECIMAL(12,2) NOT NULL,
    paid            DECIMAL(12,2) DEFAULT 0,
    payment_mode    VARCHAR(32),
    sample_status   VARCHAR(32),
    clinical_notes  TEXT,
    created_by      CHAR(36),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE lab_entry_tests (
    lab_entry_id CHAR(36) NOT NULL,
    test_id      CHAR(36) NOT NULL,
    price        DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (lab_entry_id, test_id)
);

CREATE TABLE samples (
    id           CHAR(36) PRIMARY KEY,
    lab_entry_id CHAR(36) NOT NULL,
    sample_type  VARCHAR(64),
    status       VARCHAR(32) NOT NULL,
    received_at  DATETIME NULL
);

CREATE TABLE results (
    id           CHAR(36) PRIMARY KEY,
    lab_entry_id CHAR(36) NOT NULL,
    test_id      CHAR(36) NOT NULL,
    parameter    VARCHAR(128),
    value        VARCHAR(64),
    unit         VARCHAR(32),
    flag         VARCHAR(16),
    verified_at  DATETIME NULL,
    verified_by  CHAR(36) NULL
);

CREATE TABLE reports (
    id           CHAR(36) PRIMARY KEY,
    lab_entry_id CHAR(36) NOT NULL,
    pdf_path     VARCHAR(512),
    generated_at DATETIME,
    sent_whatsapp_at DATETIME NULL
);

CREATE TABLE lab_settings (
    organization_id CHAR(36) PRIMARY KEY,
    lab_name        VARCHAR(255),
    header_text     TEXT,
    footer_text     TEXT,
    logo_path       VARCHAR(512),
    address         TEXT,
    phone           VARCHAR(64)
);
