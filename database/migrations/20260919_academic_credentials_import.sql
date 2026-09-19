-- NDC Identity System: complete academic and credential module import.
-- Prerequisite: the base `students` and `users` tables must already exist.
-- Import this one file with phpMyAdmin or MySQL.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS academic_programmes (
    id INT NOT NULL AUTO_INCREMENT, code VARCHAR(32) NOT NULL, name VARCHAR(180) NOT NULL,
    qualification VARCHAR(120) NOT NULL, duration VARCHAR(80) NULL, status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY academic_programmes_code_unique (code), KEY academic_programmes_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_courses (
    id INT NOT NULL AUTO_INCREMENT, programme_id INT NOT NULL, code VARCHAR(32) NOT NULL, name VARCHAR(180) NOT NULL,
    credits DECIMAL(6,2) NOT NULL DEFAULT 0, semester VARCHAR(40) NULL, course_type VARCHAR(20) NOT NULL DEFAULT 'core',
    is_compulsory TINYINT(1) NOT NULL DEFAULT 1, status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY academic_courses_programme_code_unique (programme_id, code),
    KEY academic_courses_programme_index (programme_id), KEY academic_courses_status_index (status),
    CONSTRAINT academic_courses_programme_fk FOREIGN KEY (programme_id) REFERENCES academic_programmes(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_terms (
    id INT NOT NULL AUTO_INCREMENT, name VARCHAR(120) NOT NULL, academic_year VARCHAR(20) NOT NULL, semester VARCHAR(40) NULL,
    start_date DATE NULL, end_date DATE NULL, status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY academic_terms_name_year_semester_unique (name, academic_year, semester), KEY academic_terms_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_enrolments (
    id INT NOT NULL AUTO_INCREMENT, student_id INT NOT NULL, programme_id INT NOT NULL, academic_term_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active', enrolled_at DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY student_enrolments_student_programme_term_unique (student_id, programme_id, academic_term_id),
    KEY student_enrolments_student_index (student_id), KEY student_enrolments_programme_index (programme_id),
    KEY student_enrolments_term_index (academic_term_id), KEY student_enrolments_status_index (status),
    CONSTRAINT student_enrolments_student_fk FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT student_enrolments_programme_fk FOREIGN KEY (programme_id) REFERENCES academic_programmes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT student_enrolments_term_fk FOREIGN KEY (academic_term_id) REFERENCES academic_terms(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_course_programmes (
    course_id INT NOT NULL, programme_id INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (course_id, programme_id), KEY academic_course_programmes_programme_index (programme_id),
    CONSTRAINT academic_course_programmes_course_fk FOREIGN KEY (course_id) REFERENCES academic_courses(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT academic_course_programmes_programme_fk FOREIGN KEY (programme_id) REFERENCES academic_programmes(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO academic_course_programmes (course_id, programme_id)
SELECT id, programme_id FROM academic_courses WHERE programme_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS student_course_completions (
    id INT NOT NULL AUTO_INCREMENT, student_id INT NOT NULL, course_id INT NOT NULL, academic_term_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'completed', completed_at DATE NULL, remarks VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY student_course_completions_unique (student_id, course_id, academic_term_id),
    KEY student_course_completions_student_index (student_id), KEY student_course_completions_course_index (course_id),
    KEY student_course_completions_term_index (academic_term_id), KEY student_course_completions_status_index (status),
    CONSTRAINT student_course_completions_student_fk FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT student_course_completions_course_fk FOREIGN KEY (course_id) REFERENCES academic_courses(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT student_course_completions_term_fk FOREIGN KEY (academic_term_id) REFERENCES academic_terms(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_course_enrolments (
    id INT NOT NULL AUTO_INCREMENT, student_id INT NOT NULL, course_id INT NOT NULL, academic_term_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'enrolled', enrolled_at DATE NOT NULL, withdrawn_at DATE NULL, remarks VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY student_course_enrolments_unique (student_id, course_id, academic_term_id),
    KEY student_course_enrolments_student_index (student_id), KEY student_course_enrolments_course_index (course_id),
    KEY student_course_enrolments_term_index (academic_term_id), KEY student_course_enrolments_status_index (status),
    CONSTRAINT student_course_enrolments_student_fk FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT student_course_enrolments_course_fk FOREIGN KEY (course_id) REFERENCES academic_courses(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT student_course_enrolments_term_fk FOREIGN KEY (academic_term_id) REFERENCES academic_terms(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_results (
    id INT NOT NULL AUTO_INCREMENT, student_id INT NOT NULL, course_id INT NOT NULL, academic_term_id INT NOT NULL,
    mark DECIMAL(5,2) NOT NULL, grade VARCHAR(4) NOT NULL, grade_points DECIMAL(4,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'draft', assessed_at DATE NOT NULL, remarks VARCHAR(255) NULL,
    approved_at DATETIME NULL, approved_by_user_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY student_results_student_course_term_unique (student_id, course_id, academic_term_id),
    KEY student_results_student_index (student_id), KEY student_results_course_index (course_id), KEY student_results_term_index (academic_term_id),
    KEY student_results_status_index (status), KEY student_results_approved_by_index (approved_by_user_id),
    CONSTRAINT student_results_student_fk FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT student_results_course_fk FOREIGN KEY (course_id) REFERENCES academic_courses(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT student_results_term_fk FOREIGN KEY (academic_term_id) REFERENCES academic_terms(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT student_results_approved_by_fk FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_certificates (
    id INT NOT NULL AUTO_INCREMENT, student_id INT NOT NULL, programme_id INT NOT NULL,
    certificate_number VARCHAR(64) NOT NULL, verification_token CHAR(64) NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'draft',
    eligibility_snapshot LONGTEXT NOT NULL, requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, requested_by_user_id INT NULL,
    submitted_at DATETIME NULL, issued_at DATETIME NULL, issued_by_user_id INT NULL,
    revoked_at DATETIME NULL, revoked_by_user_id INT NULL, revocation_reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY academic_certificates_number_unique (certificate_number), UNIQUE KEY academic_certificates_token_unique (verification_token),
    KEY academic_certificates_student_index (student_id), KEY academic_certificates_programme_index (programme_id), KEY academic_certificates_status_index (status),
    CONSTRAINT academic_certificates_student_fk FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT academic_certificates_programme_fk FOREIGN KEY (programme_id) REFERENCES academic_programmes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT academic_certificates_requested_by_fk FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT academic_certificates_issued_by_fk FOREIGN KEY (issued_by_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT academic_certificates_revoked_by_fk FOREIGN KEY (revoked_by_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_transcripts (
    id INT NOT NULL AUTO_INCREMENT, student_id INT NOT NULL, programme_id INT NOT NULL,
    transcript_number VARCHAR(64) NOT NULL, verification_token CHAR(64) NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'draft',
    transcript_snapshot LONGTEXT NOT NULL, requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, requested_by_user_id INT NULL,
    issued_at DATETIME NULL, issued_by_user_id INT NULL, revoked_at DATETIME NULL, revoked_by_user_id INT NULL, revocation_reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY academic_transcripts_number_unique (transcript_number), UNIQUE KEY academic_transcripts_token_unique (verification_token),
    KEY academic_transcripts_student_index (student_id), KEY academic_transcripts_programme_index (programme_id), KEY academic_transcripts_status_index (status),
    CONSTRAINT academic_transcripts_student_fk FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT academic_transcripts_programme_fk FOREIGN KEY (programme_id) REFERENCES academic_programmes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT academic_transcripts_requested_by_fk FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT academic_transcripts_issued_by_fk FOREIGN KEY (issued_by_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT academic_transcripts_revoked_by_fk FOREIGN KEY (revoked_by_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enrol Aaron Frackson in ICT and every other active student in the programme on their profile.
INSERT INTO student_enrolments (student_id, programme_id, academic_term_id, status, enrolled_at)
SELECT s.id, p.id, t.id, 'active', '2026-09-19'
FROM students s INNER JOIN academic_programmes p ON p.code = 'ICT'
INNER JOIN academic_terms t ON t.academic_year = '2026' AND t.name = 'FIRST TERM'
WHERE s.student_number = 'SFRAAA01'
ON DUPLICATE KEY UPDATE status = 'active', enrolled_at = VALUES(enrolled_at);

INSERT INTO student_enrolments (student_id, programme_id, academic_term_id, status, enrolled_at)
SELECT s.id, p.id, t.id, 'completed', '2026-09-19'
FROM students s
INNER JOIN academic_programmes p ON p.code = CASE TRIM(s.program)
    WHEN 'Tailoring and Fashion designing' THEN 'TFD' WHEN 'Electrical installation' THEN 'ESE'
    WHEN 'Catering and hospitality' THEN 'CH' WHEN 'Saloon and cosmetology' THEN 'COSMETOLOGY'
    WHEN 'ICT/Digital Skills' THEN 'ICT' WHEN 'C&H' THEN 'CH' WHEN 'ICT' THEN 'ICT' WHEN 'TFD' THEN 'TFD'
END
INNER JOIN academic_terms t ON t.academic_year = '2026' AND t.name = 'FIRST TERM'
WHERE s.status = 'Active' AND s.student_number <> 'SFRAAA01'
ON DUPLICATE KEY UPDATE status = 'completed', enrolled_at = VALUES(enrolled_at);
