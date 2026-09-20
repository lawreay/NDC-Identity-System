<?php

final class AcademicChecksheetService
{
    public function __construct(private PDO $connection)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function programmesForStudent(int $studentId): array
    {
        $statement = $this->connection->prepare(
            'SELECT e.programme_id, p.code, p.name, p.qualification,
                    MAX(e.enrolled_at) AS latest_enrolled_at
             FROM student_enrolments e
             INNER JOIN academic_programmes p ON p.id = e.programme_id
             WHERE e.student_id = :student_id
             GROUP BY e.programme_id, p.code, p.name, p.qualification
             ORDER BY latest_enrolled_at DESC, p.name'
        );
        $statement->execute([':student_id' => $studentId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function build(int $studentId, int $programmeId = 0): ?array
    {
        $student = $this->student($studentId);
        if ($student === null) {
            return null;
        }

        $programme = $this->programmeForStudent($studentId, $programmeId);
        if ($programme === null) {
            return null;
        }

        $courses = $this->coursesForProgramme((int) $programme['id']);
        $completions = $this->completionsForProgramme($studentId, (int) $programme['id']);
        $results = $this->approvedResultsForProgramme($studentId, (int) $programme['id']);

        $completedCredits = 0.0;
        $requiredCredits = 0.0;
        $completedRequiredCourses = 0;
        $requiredCourses = 0;
        $outstandingRequiredCourses = [];

        foreach ($courses as &$course) {
            $courseId = (int) $course['id'];
            $completion = $completions[$courseId] ?? null;
            $result = $results[$courseId] ?? null;
            $completionStatus = strtolower((string) ($completion['status'] ?? ''));
            $isCompleted = in_array($completionStatus, ['completed', 'exempted'], true);
            $isRequired = (int) $course['is_compulsory'] === 1;

            $course['completion_status'] = $completion['status'] ?? null;
            $course['completed_at'] = $completion['completed_at'] ?? null;
            $course['result_mark'] = $result['mark'] ?? null;
            $course['result_grade'] = $result['grade'] ?? null;
            $course['result_status'] = $result['status'] ?? null;
            $course['is_completed'] = $isCompleted;
            $course['requirement_status'] = $isCompleted
                ? 'complete'
                : ($completionStatus !== '' ? $completionStatus : ($result !== null ? 'awaiting completion' : 'outstanding'));

            if ($isCompleted) {
                $completedCredits += (float) $course['credits'];
            }
            if ($isRequired) {
                $requiredCourses++;
                $requiredCredits += (float) $course['credits'];
                if ($isCompleted) {
                    $completedRequiredCourses++;
                } else {
                    $outstandingRequiredCourses[] = $course;
                }
            }
        }
        unset($course);

        return [
            'student' => $student,
            'programme' => $programme,
            'courses' => $courses,
            'summary' => [
                'required_courses' => $requiredCourses,
                'completed_required_courses' => $completedRequiredCourses,
                'outstanding_required_courses' => count($outstandingRequiredCourses),
                'required_credits' => $requiredCredits,
                'completed_credits' => $completedCredits,
                'outstanding_required' => $outstandingRequiredCourses,
                'is_complete' => $requiredCourses > 0 && $outstandingRequiredCourses === [],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function student(int $studentId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, student_number, first_name, last_name FROM students WHERE id = :id LIMIT 1'
        );
        $statement->execute([':id' => $studentId]);
        $student = $statement->fetch();

        return is_array($student) ? $student : null;
    }

    /** @return array<string, mixed>|null */
    private function programmeForStudent(int $studentId, int $programmeId): ?array
    {
        $statement = $this->connection->prepare(
            "SELECT p.id, p.code, p.name, p.qualification, e.status AS enrolment_status, e.enrolled_at
             FROM student_enrolments e
             INNER JOIN academic_programmes p ON p.id = e.programme_id
             WHERE e.student_id = :student_id
               AND (:programme_id = 0 OR e.programme_id = :selected_programme_id)
             ORDER BY CASE e.status WHEN 'active' THEN 0 WHEN 'completed' THEN 1 ELSE 2 END,
                      e.enrolled_at DESC, e.id DESC
             LIMIT 1"
        );
        $statement->execute([
            ':student_id' => $studentId,
            ':programme_id' => $programmeId,
            ':selected_programme_id' => $programmeId,
        ]);
        $programme = $statement->fetch();

        return is_array($programme) ? $programme : null;
    }

    /** @return array<int, array<string, mixed>> */
    private function coursesForProgramme(int $programmeId): array
    {
        $statement = $this->connection->prepare(
            "SELECT c.id, c.code, c.name, c.credits, c.semester, c.course_type, c.is_compulsory, c.status
             FROM academic_course_programmes cp
             INNER JOIN academic_courses c ON c.id = cp.course_id
             WHERE cp.programme_id = :programme_id
             ORDER BY COALESCE(c.semester, ''), c.code, c.name"
        );
        $statement->execute([':programme_id' => $programmeId]);

        return $statement->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    private function completionsForProgramme(int $studentId, int $programmeId): array
    {
        $statement = $this->connection->prepare(
            "SELECT cc.course_id, cc.status, cc.completed_at, cc.remarks
             FROM student_course_completions cc
             INNER JOIN academic_course_programmes cp
                ON cp.course_id = cc.course_id AND cp.programme_id = :programme_id
             WHERE cc.student_id = :student_id
               AND EXISTS (
                   SELECT 1 FROM student_enrolments e
                   WHERE e.student_id = cc.student_id
                     AND e.programme_id = :enrolment_programme_id
                     AND e.academic_term_id = cc.academic_term_id
               )
             ORDER BY cc.course_id,
                      CASE cc.status WHEN 'completed' THEN 0 WHEN 'exempted' THEN 1 WHEN 'incomplete' THEN 2 ELSE 3 END,
                      cc.completed_at DESC, cc.id DESC"
        );
        $statement->execute([
            ':student_id' => $studentId,
            ':programme_id' => $programmeId,
            ':enrolment_programme_id' => $programmeId,
        ]);

        return $this->keyByCourse($statement->fetchAll());
    }

    /** @return array<int, array<string, mixed>> */
    private function approvedResultsForProgramme(int $studentId, int $programmeId): array
    {
        $statement = $this->connection->prepare(
            "SELECT r.course_id, r.mark, r.grade, r.status, r.assessed_at
             FROM student_results r
             INNER JOIN academic_course_programmes cp
                ON cp.course_id = r.course_id AND cp.programme_id = :programme_id
             WHERE r.student_id = :student_id
               AND r.status = 'approved'
               AND EXISTS (
                   SELECT 1 FROM student_enrolments e
                   WHERE e.student_id = r.student_id
                     AND e.programme_id = :enrolment_programme_id
                     AND e.academic_term_id = r.academic_term_id
               )
             ORDER BY r.course_id, r.assessed_at DESC, r.id DESC"
        );
        $statement->execute([
            ':student_id' => $studentId,
            ':programme_id' => $programmeId,
            ':enrolment_programme_id' => $programmeId,
        ]);

        return $this->keyByCourse($statement->fetchAll());
    }

    /** @param array<int, array<string, mixed>> $records @return array<int, array<string, mixed>> */
    private function keyByCourse(array $records): array
    {
        $byCourse = [];
        foreach ($records as $record) {
            $courseId = (int) ($record['course_id'] ?? 0);
            if ($courseId > 0 && !isset($byCourse[$courseId])) {
                $byCourse[$courseId] = $record;
            }
        }

        return $byCourse;
    }
}
