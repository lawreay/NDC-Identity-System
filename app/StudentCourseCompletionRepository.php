<?php

final class StudentCourseCompletionRepository
{
    private const STATUSES = ['completed', 'incomplete', 'failed', 'exempted'];

    public function __construct(private PDO $connection)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->connection->query(
            'SELECT cc.id, cc.student_id, cc.course_id, cc.academic_term_id, cc.status, cc.completed_at, cc.remarks,
                    s.student_number, s.first_name, s.last_name,
                    c.code AS course_code, c.name AS course_name,
                    t.name AS term_name, t.academic_year, t.semester
             FROM student_course_completions cc
             INNER JOIN students s ON s.id = cc.student_id
             INNER JOIN academic_courses c ON c.id = cc.course_id
             INNER JOIN academic_terms t ON t.id = cc.academic_term_id
             ORDER BY cc.completed_at DESC, s.first_name, s.last_name, c.code'
        )->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function forStudent(int $studentId): array
    {
        $statement = $this->connection->prepare(
            'SELECT cc.id, cc.course_id, cc.academic_term_id, cc.status, cc.completed_at, cc.remarks,
                    c.code AS course_code, c.name AS course_name,
                    t.name AS term_name, t.academic_year, t.semester
             FROM student_course_completions cc
             INNER JOIN academic_courses c ON c.id = cc.course_id
             INNER JOIN academic_terms t ON t.id = cc.academic_term_id
             WHERE cc.student_id = :student_id
             ORDER BY t.academic_year DESC, t.semester DESC, c.code'
        );
        $statement->execute([':student_id' => $studentId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, student_id, course_id, academic_term_id, status, completed_at, remarks
             FROM student_course_completions
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
        $statement = $this->connection->prepare(
            'INSERT INTO student_course_completions (student_id, course_id, academic_term_id, status, completed_at, remarks)
             VALUES (:student_id, :course_id, :academic_term_id, :status, :completed_at, :remarks)'
        );
        $statement->execute($this->params($data));

        return (int) $this->connection->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $data = $this->normalize($data);
        $statement = $this->connection->prepare(
            'UPDATE student_course_completions
             SET student_id = :student_id, course_id = :course_id, academic_term_id = :academic_term_id,
                 status = :status, completed_at = :completed_at, remarks = :remarks
             WHERE id = :id'
        );
        $params = $this->params($data);
        $params[':id'] = $id;

        return $statement->execute($params);
    }

    /** @return array<int, string> */
    public function validate(array $data): array
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
        if (!in_array($data['status'], self::STATUSES, true)) {
            $errors[] = 'Choose a valid completion status.';
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    public function normalize(array $data): array
    {
        $status = strtolower(trim((string) ($data['status'] ?? 'completed')));

        return [
            'student_id' => (int) ($data['student_id'] ?? 0),
            'course_id' => (int) ($data['course_id'] ?? 0),
            'academic_term_id' => (int) ($data['academic_term_id'] ?? 0),
            'status' => in_array($status, self::STATUSES, true) ? $status : 'completed',
            'completed_at' => $this->dateOrNull((string) ($data['completed_at'] ?? '')),
            'remarks' => trim((string) ($data['remarks'] ?? '')),
        ];
    }

    public function seedStandardSemesterOneCourses(): int
    {
        $created = 0;
        $common = [
            ['001EPNSP', 'Entrepreneurship', 3],
            ['001CML', 'Communication Language', 3],
        ];
        $technical = [
            ['S1TD', 'Technical Drawing', 4],
            ['S1TECH', 'Technology', 4],
            ['S1NUM', 'Numeracy', 3],
            ['S1SCI', 'Science', 3],
        ];

        $programmes = $this->connection
            ->query("SELECT id, code, name FROM academic_programmes WHERE status = 'active' ORDER BY name")
            ->fetchAll();

        foreach ($common as [$code, $name, $credits]) {
            $created += $this->ensureCourse((int) ($programmes[0]['id'] ?? 0), $code, $name, $credits, array_column($programmes, 'id'));
        }

        foreach ($programmes as $programme) {
            $programmeName = strtolower((string) ($programme['name'] ?? ''));
            if ($programmeName === 'business studies') {
                continue;
            }

            foreach ($technical as [$suffix, $name, $credits]) {
                $code = strtoupper((string) ($programme['code'] ?? 'PRG')) . $suffix;
                $created += $this->ensureCourse((int) $programme['id'], $code, $name, $credits, [(int) $programme['id']]);
            }
        }

        return $created;
    }

    public function seedSemesterOneCompletions(?int $termId = null): int
    {
        $termId = $termId ?: $this->firstSemesterTermId();
        if ($termId <= 0) {
            throw new RuntimeException('Create a Semester 1 academic term before marking completions.');
        }

        $statement = $this->connection->prepare(
            "INSERT IGNORE INTO student_course_completions (student_id, course_id, academic_term_id, status, completed_at, remarks)
             SELECT s.id, c.id, :term_id, 'completed', CURDATE(), 'Semester 1 completed'
             FROM students s
             INNER JOIN academic_programmes p ON p.status = 'active' AND (
                LOWER(TRIM(s.program)) = LOWER(TRIM(p.name))
                OR LOWER(TRIM(s.program)) = LOWER(TRIM(p.code))
                OR (LOWER(TRIM(s.program)) IN ('ict/digital skills', 'ict') AND p.code = 'ICT')
                OR (LOWER(TRIM(s.program)) IN ('tailoring and fashion designing', 'tailoring and fashion design', 'tfd') AND p.code = 'TFD')
                OR (LOWER(TRIM(s.program)) IN ('electrical installation', 'electrical', 'ese') AND p.code = 'ESE')
                OR (LOWER(TRIM(s.program)) IN ('catering and hospitality', 'c&h', 'ch') AND p.code = 'CH')
                OR (LOWER(TRIM(s.program)) IN ('saloon and cosmetology', 'salon and cosmetology', 'cosmetology') AND p.code = 'COSMETOLOGY')
                OR (LOWER(TRIM(s.program)) = 'business studies' AND p.code = 'BUSINESSSTUD')
             )
             INNER JOIN academic_course_programmes cp ON cp.programme_id = p.id
             INNER JOIN academic_courses c ON c.id = cp.course_id AND COALESCE(c.semester, '') IN ('1', 'Semester 1', 'SEM 1')
             WHERE LOWER(COALESCE(s.status, 'active')) = 'active'"
        );
        $statement->execute([':term_id' => $termId]);

        return $statement->rowCount();
    }

    private function ensureCourse(int $primaryProgrammeId, string $code, string $name, float $credits, array $programmeIds): int
    {
        if ($primaryProgrammeId <= 0) {
            return 0;
        }

        $statement = $this->connection->prepare('SELECT id FROM academic_courses WHERE code = :code LIMIT 1');
        $statement->execute([':code' => $code]);
        $courseId = (int) ($statement->fetchColumn() ?: 0);
        $created = 0;

        if ($courseId <= 0) {
            $statement = $this->connection->prepare(
                "INSERT INTO academic_courses (programme_id, code, name, credits, semester, course_type, is_compulsory, status)
                 VALUES (:programme_id, :code, :name, :credits, '1', 'core', 1, 'active')"
            );
            $statement->execute([
                ':programme_id' => $primaryProgrammeId,
                ':code' => $code,
                ':name' => $name,
                ':credits' => $credits,
            ]);
            $courseId = (int) $this->connection->lastInsertId();
            $created = 1;
        }

        $link = $this->connection->prepare('INSERT IGNORE INTO academic_course_programmes (course_id, programme_id) VALUES (:course_id, :programme_id)');
        foreach ($programmeIds as $programmeId) {
            $programmeId = (int) $programmeId;
            if ($programmeId > 0) {
                $link->execute([':course_id' => $courseId, ':programme_id' => $programmeId]);
            }
        }

        return $created;
    }

    private function firstSemesterTermId(): int
    {
        $statement = $this->connection->query(
            "SELECT id FROM academic_terms
             WHERE semester = '1' OR LOWER(name) LIKE '%first%'
             ORDER BY academic_year DESC, start_date DESC, id DESC
             LIMIT 1"
        );

        return (int) ($statement->fetchColumn() ?: 0);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function params(array $data): array
    {
        return [
            ':student_id' => $data['student_id'],
            ':course_id' => $data['course_id'],
            ':academic_term_id' => $data['academic_term_id'],
            ':status' => $data['status'],
            ':completed_at' => $data['completed_at'],
            ':remarks' => $data['remarks'] !== '' ? $data['remarks'] : null,
        ];
    }

    private function dateOrNull(string $date): ?string
    {
        $date = trim($date);
        if ($date === '') {
            return date('Y-m-d');
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed && $parsed->format('Y-m-d') === $date ? $date : null;
    }
}
