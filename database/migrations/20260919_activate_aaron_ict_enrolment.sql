-- Enrol SFRAAA01 (Aaron Frackson) in ICT for 2026 First Term.
-- The statement is safe to re-run and also corrects an existing completed record.
INSERT INTO student_enrolments (
    student_id,
    programme_id,
    academic_term_id,
    status,
    enrolled_at
)
SELECT
    s.id,
    p.id,
    t.id,
    'active',
    '2026-09-19'
FROM students AS s
INNER JOIN academic_programmes AS p ON p.code = 'ICT'
INNER JOIN academic_terms AS t
    ON t.academic_year = '2026'
    AND t.name = 'FIRST TERM'
WHERE s.student_number = 'SFRAAA01'
ON DUPLICATE KEY UPDATE
    status = 'active',
    enrolled_at = VALUES(enrolled_at);
