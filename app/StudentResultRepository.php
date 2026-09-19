<?php

final class StudentResultRepository
{
    private const EDITABLE_STATUSES = ['draft'];

    public function __construct(private PDO $connection)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function all(array $filters = []): array
    {
        $programmeId = (int) ($filters['programme_id'] ?? 0);
        $courseId = (int) ($filters['course_id'] ?? 0);
        $termId = (int) ($filters['academic_term_id'] ?? 0);

        $statement = $this->connection->prepare(
            'SELECT r.id, r.student_id, r.course_id, r.academic_term_id, r.mark, r.grade, r.grade_points, r.status,
                    r.assessed_at, r.remarks, r.approved_at, r.approved_by_user_id,
                    s.student_number, s.first_name, s.last_name,
                    c.code AS course_code, c.name AS course_name,
                    t.name AS term_name, t.academic_year, t.semester,
                    approver.name AS approved_by_name,
                    (SELECT GROUP_CONCAT(DISTINCT linked.code ORDER BY linked.code SEPARATOR ", ")
                     FROM academic_course_programmes linked_map
                     INNER JOIN academic_programmes linked ON linked.id = linked_map.programme_id
                     WHERE linked_map.course_id = c.id) AS programme_codes
             FROM student_results r
             INNER JOIN students s ON s.id = r.student_id
             INNER JOIN academic_courses c ON c.id = r.course_id
             INNER JOIN academic_terms t ON t.id = r.academic_term_id
             LEFT JOIN users approver ON approver.id = r.approved_by_user_id
             WHERE (:programme_id = 0 OR EXISTS (
                    SELECT 1 FROM academic_course_programmes filter_map
                    WHERE filter_map.course_id = r.course_id AND filter_map.programme_id = :filter_programme_id
             ))
               AND (:course_id = 0 OR r.course_id = :course_id)
               AND (:term_id = 0 OR r.academic_term_id = :term_id)
             ORDER BY t.academic_year DESC, t.start_date DESC, c.code, s.first_name, s.last_name'
        );
        $statement->execute([
            ':programme_id' => $programmeId,
            ':filter_programme_id' => $programmeId,
            ':course_id' => $courseId,
            ':term_id' => $termId,
        ]);

        return $statement->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function forStudent(int $studentId): array
    {
        $statement = $this->connection->prepare(
            'SELECT r.id, r.course_id, r.academic_term_id, r.mark, r.grade, r.grade_points, r.status, r.assessed_at,
                    c.code AS course_code, c.name AS course_name,
                    t.name AS term_name, t.academic_year, t.semester
             FROM student_results r
             INNER JOIN academic_courses c ON c.id = r.course_id
             INNER JOIN academic_terms t ON t.id = r.academic_term_id
             WHERE r.student_id = :student_id
             ORDER BY t.academic_year DESC, t.start_date DESC, c.code'
        );
        $statement->execute([':student_id' => $studentId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, student_id, course_id, academic_term_id, mark, grade, grade_points, status, assessed_at, remarks,
                    approved_at, approved_by_user_id
             FROM student_results
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function create(array $data): int
    {
        $data = $this->normalize($data);
        $data['status'] = 'draft';
        $statement = $this->connection->prepare(
            'INSERT INTO student_results (student_id, course_id, academic_term_id, mark, grade, grade_points, status, assessed_at, remarks)
             VALUES (:student_id, :course_id, :academic_term_id, :mark, :grade, :grade_points, :status, :assessed_at, :remarks)'
        );
        $statement->execute($this->params($data));

        return (int) $this->connection->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        if (!$this->isDraft($id)) {
            throw new RuntimeException('Only draft results can be edited. Approved results must be voided before replacement.');
        }

        $data = $this->normalize($data);
        $data['status'] = 'draft';
        $params = $this->params($data);
        $params[':id'] = $id;
        $statement = $this->connection->prepare(
            'UPDATE student_results
             SET student_id = :student_id, course_id = :course_id, academic_term_id = :academic_term_id,
                 mark = :mark, grade = :grade, grade_points = :grade_points, status = :status,
                 assessed_at = :assessed_at, remarks = :remarks
             WHERE id = :id'
        );

        return $statement->execute($params);
    }

    public function approve(int $id, int $userId): bool
    {
        $statement = $this->connection->prepare(
            "UPDATE student_results
             SET status = 'approved', approved_at = CURRENT_TIMESTAMP, approved_by_user_id = :user_id
             WHERE id = :id AND status = 'draft'"
        );
        $statement->execute([':id' => $id, ':user_id' => $userId]);

        return $statement->rowCount() === 1;
    }

    public function void(int $id): bool
    {
        $statement = $this->connection->prepare(
            "UPDATE student_results
             SET status = 'void'
             WHERE id = :id AND status IN ('draft', 'approved')"
        );
        $statement->execute([':id' => $id]);

        return $statement->rowCount() === 1;
    }

    /** @return array<int, string> */
    public function validate(array $data, int $exceptId = 0): array
    {
        $data = $this->normalize($data);
        $errors = [];

        if ($data['student_id'] <= 0) {
            $errors[] = 'Student is required.';
        }
        if ($data['course_id'] <= 0) {
            $errors[] = 'Course is required.';
        }
        if ($data['academic_term_id'] <= 0) {
            $errors[] = 'Academic term is required.';
        }
        if ($data['mark'] === null || $data['mark'] < 0 || $data['mark'] > 100) {
            $errors[] = 'Mark must be a number from 0 to 100.';
        }
        if ($data['assessed_at'] === '') {
            $errors[] = 'Assessment date is required.';
        }
        if ($errors === [] && !$this->isCourseAvailableToStudent($data['student_id'], $data['course_id'], $data['academic_term_id'])) {
            $errors[] = 'The student is not enrolled in the programme for this course and term.';
        }
        if ($errors === [] && $this->existsFor($data['student_id'], $data['course_id'], $data['academic_term_id'], $exceptId)) {
            $errors[] = 'A result for this student, course, and term already exists.';
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    public function normalize(array $data): array
    {
        $rawMark = trim((string) ($data['mark'] ?? ''));
        $mark = is_numeric($rawMark) ? round((float) $rawMark, 2) : null;
        $grade = $mark === null ? '' : $this->gradeFor($mark);

        return [
            'student_id' => (int) ($data['student_id'] ?? 0),
            'course_id' => (int) ($data['course_id'] ?? 0),
            'academic_term_id' => (int) ($data['academic_term_id'] ?? 0),
            'mark' => $mark,
            'grade' => $grade,
            'grade_points' => $mark === null ? 0 : $this->gradePointsFor($mark),
            'status' => 'draft',
            'assessed_at' => $this->dateOrEmpty((string) ($data['assessed_at'] ?? '')),
            'remarks' => trim((string) ($data['remarks'] ?? '')),
        ];
    }

    public function gradeFor(float $mark): string
    {
        if ($mark >= 80) {
            return 'A';
        }
        if ($mark >= 70) {
            return 'B';
        }
        if ($mark >= 60) {
            return 'C';
        }
        if ($mark >= 50) {
            return 'D';
        }

        return 'F';
    }

    public function gradePointsFor(float $mark): float
    {
        return match ($this->gradeFor($mark)) {
            'A' => 4.0,
            'B' => 3.0,
            'C' => 2.0,
            'D' => 1.0,
            default => 0.0,
        };
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function params(array $data): array
    {
        return [
            ':student_id' => $data['student_id'],
            ':course_id' => $data['course_id'],
            ':academic_term_id' => $data['academic_term_id'],
            ':mark' => $data['mark'],
            ':grade' => $data['grade'],
            ':grade_points' => $data['grade_points'],
            ':status' => $data['status'],
            ':assessed_at' => $data['assessed_at'],
            ':remarks' => $data['remarks'] !== '' ? $data['remarks'] : null,
        ];
    }

    private function isDraft(int $id): bool
    {
        $statement = $this->connection->prepare("SELECT 1 FROM student_results WHERE id = :id AND status = 'draft'");
        $statement->execute([':id' => $id]);

        return (bool) $statement->fetchColumn();
    }

    private function isCourseAvailableToStudent(int $studentId, int $courseId, int $termId): bool
    {
        $statement = $this->connection->prepare(
            "SELECT 1
             FROM student_enrolments enrolment
             INNER JOIN academic_course_programmes course_programme
                ON course_programme.programme_id = enrolment.programme_id
             WHERE enrolment.student_id = :student_id
               AND enrolment.academic_term_id = :term_id
               AND enrolment.status IN ('active', 'completed')
               AND course_programme.course_id = :course_id
             LIMIT 1"
        );
        $statement->execute([
            ':student_id' => $studentId,
            ':course_id' => $courseId,
            ':term_id' => $termId,
        ]);

        return (bool) $statement->fetchColumn();
    }

    private function existsFor(int $studentId, int $courseId, int $termId, int $exceptId): bool
    {
        $statement = $this->connection->prepare(
            'SELECT 1 FROM student_results
             WHERE student_id = :student_id AND course_id = :course_id AND academic_term_id = :term_id
               AND id <> :except_id
             LIMIT 1'
        );
        $statement->execute([
            ':student_id' => $studentId,
            ':course_id' => $courseId,
            ':term_id' => $termId,
            ':except_id' => $exceptId,
        ]);

        return (bool) $statement->fetchColumn();
    }

    private function dateOrEmpty(string $date): string
    {
        $date = trim($date);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed && $parsed->format('Y-m-d') === $date ? $date : '';
    }
}
