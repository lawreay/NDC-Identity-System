-- Enrol every other active student in the programme recorded on their student profile.
-- Aaron (SFRAAA01) is excluded because his separate ICT enrolment is intentional.
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
INNER JOIN academic_programmes AS p
    ON p.code = CASE TRIM(s.program)
        WHEN 'Tailoring and Fashion designing' THEN 'TFD'
        WHEN 'Electrical installation' THEN 'ESE'
        WHEN 'Catering and hospitality' THEN 'CH'
        WHEN 'Saloon and cosmetology' THEN 'COSMETOLOGY'
        WHEN 'ICT/Digital Skills' THEN 'ICT'
        WHEN 'C&H' THEN 'CH'
        WHEN 'ICT' THEN 'ICT'
        WHEN 'TFD' THEN 'TFD'
    END
INNER JOIN academic_terms AS t
    ON t.academic_year = '2026'
    AND t.name = 'FIRST TERM'
WHERE s.status = 'Active'
  AND s.student_number <> 'SFRAAA01'
ON DUPLICATE KEY UPDATE
    status = 'completed',
    enrolled_at = VALUES(enrolled_at);
