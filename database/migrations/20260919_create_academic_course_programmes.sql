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
