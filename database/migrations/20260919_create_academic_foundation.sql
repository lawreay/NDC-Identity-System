CREATE TABLE IF NOT EXISTS academic_programmes (
    id INT NOT NULL AUTO_INCREMENT,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(180) NOT NULL,
    qualification VARCHAR(120) NOT NULL,
    duration VARCHAR(80) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY academic_programmes_code_unique (code),
    KEY academic_programmes_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_courses (
    id INT NOT NULL AUTO_INCREMENT,
    programme_id INT NOT NULL,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(180) NOT NULL,
    credits DECIMAL(6,2) NOT NULL DEFAULT 0,
    semester VARCHAR(40) NULL,
    course_type VARCHAR(20) NOT NULL DEFAULT 'core',
    is_compulsory TINYINT(1) NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY academic_courses_programme_code_unique (programme_id, code),
    KEY academic_courses_programme_index (programme_id),
    KEY academic_courses_status_index (status),
    CONSTRAINT academic_courses_programme_fk FOREIGN KEY (programme_id) REFERENCES academic_programmes(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_terms (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    semester VARCHAR(40) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY academic_terms_name_year_semester_unique (name, academic_year, semester),
    KEY academic_terms_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_enrolments (
    id INT NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    programme_id INT NOT NULL,
    academic_term_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    enrolled_at DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY student_enrolments_student_programme_term_unique (student_id, programme_id, academic_term_id),
    KEY student_enrolments_student_index (student_id),
    KEY student_enrolments_programme_index (programme_id),
    KEY student_enrolments_term_index (academic_term_id),
    KEY student_enrolments_status_index (status),
    CONSTRAINT student_enrolments_student_fk FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT student_enrolments_programme_fk FOREIGN KEY (programme_id) REFERENCES academic_programmes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT student_enrolments_term_fk FOREIGN KEY (academic_term_id) REFERENCES academic_terms(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_course_programmes (
    course_id INT NOT NULL,
    programme_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (course_id, programme_id),
    KEY academic_course_programmes_programme_index (programme_id),
    CONSTRAINT academic_course_programmes_course_fk FOREIGN KEY (course_id) REFERENCES academic_courses(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT academic_course_programmes_programme_fk FOREIGN KEY (programme_id) REFERENCES academic_programmes(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO academic_course_programmes (course_id, programme_id)
SELECT id, programme_id
FROM academic_courses
WHERE programme_id IS NOT NULL;
