CREATE TABLE IF NOT EXISTS student_id_cards (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    guid CHAR(36) NOT NULL,
    issued_at DATETIME NOT NULL,
    expires_at DATE NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
    revoked_at DATETIME NULL,

    PRIMARY KEY (id),
    UNIQUE KEY student_id_cards_guid_unique (guid),
    KEY student_id_cards_student_status_index (student_id, status),
    KEY student_id_cards_status_expiry_index (status, expires_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;