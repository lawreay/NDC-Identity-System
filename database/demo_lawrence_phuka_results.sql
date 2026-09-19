-- Demo academic data for SPHULA01 - Lawrence Phuka.
-- Creates approved ICT results for the completed 2026 FIRST TERM courses.
-- Safe to re-run: existing result records are updated with the same demo values.

INSERT INTO student_results (
    student_id,
    course_id,
    academic_term_id,
    mark,
    grade,
    grade_points,
    status,
    assessed_at,
    remarks,
    approved_at,
    approved_by_user_id
)
SELECT
    s.id,
    c.id,
    t.id,
    CASE c.code
        WHEN '001EPNSP' THEN 78.00
        WHEN '001CML' THEN 74.00
        WHEN 'ICT01TD' THEN 82.00
        WHEN 'ICTS1TD' THEN 80.00
        WHEN 'ICTS1TECH' THEN 76.00
        WHEN 'ICTS1NUM' THEN 72.00
        WHEN 'ICTS1SCI' THEN 79.00
        ELSE 70.00
    END,
    CASE
        WHEN c.code IN ('ICT01TD', 'ICTS1TD') THEN 'A'
        WHEN c.code IN ('001EPNSP', '001CML', 'ICTS1TECH', 'ICTS1NUM', 'ICTS1SCI') THEN 'B'
        ELSE 'B'
    END,
    CASE
        WHEN c.code IN ('ICT01TD', 'ICTS1TD') THEN 4.00
        ELSE 3.00
    END,
    'approved',
    '2026-09-19',
    'Demo result for testing academic credentials',
    NOW(),
    NULL
FROM students AS s
INNER JOIN student_enrolments AS e
    ON e.student_id = s.id
INNER JOIN academic_programmes AS p
    ON p.id = e.programme_id
    AND p.code = 'ICT'
INNER JOIN academic_terms AS t
    ON t.id = e.academic_term_id
    AND t.academic_year = '2026'
    AND t.name = 'FIRST TERM'
INNER JOIN student_course_completions AS cc
    ON cc.student_id = s.id
    AND cc.academic_term_id = t.id
    AND cc.status IN ('completed', 'exempted')
INNER JOIN academic_courses AS c
    ON c.id = cc.course_id
INNER JOIN academic_course_programmes AS cp
    ON cp.course_id = c.id
    AND cp.programme_id = p.id
WHERE s.student_number = 'SPHULA01'
ON DUPLICATE KEY UPDATE
    mark = VALUES(mark),
    grade = VALUES(grade),
    grade_points = VALUES(grade_points),
    status = 'approved',
    assessed_at = VALUES(assessed_at),
    remarks = VALUES(remarks),
    approved_at = NOW(),
    approved_by_user_id = NULL;
