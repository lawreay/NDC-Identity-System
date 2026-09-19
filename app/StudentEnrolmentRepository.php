<?php

final class StudentEnrolmentRepository
{
    private const STATUSES = ['active', 'completed', 'withdrawn', 'suspended'];

    public function __construct(private PDO $connection)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->connection->query(
            'SELECT e.id, e.student_id, e.programme_id, e.academic_term_id, e.status, e.enrolled_at,
                    s.student_number, s.first_name, s.last_name,
                    p.code AS programme_code, p.name AS programme_name,
                    t.name AS term_name, t.academic_year, t.semester
             FROM student_enrolments e
             INNER JOIN students s ON s.id = e.student_id
             INNER JOIN academic_programmes p ON p.id = e.programme_id
             INNER JOIN academic_terms t ON t.id = e.academic_term_id
             ORDER BY e.enrolled_at DESC, s.first_name, s.last_name'
        )->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function forStudent(int $studentId): array
    {
        $statement = $this->connection->prepare(
            'SELECT e.id, e.status, e.enrolled_at, p.code AS programme_code, p.name AS programme_name, t.name AS term_name, t.academic_year, t.semester
             FROM student_enrolments e
             INNER JOIN academic_programmes p ON p.id = e.programme_id
             INNER JOIN academic_terms t ON t.id = e.academic_term_id
             WHERE e.student_id = :student_id
             ORDER BY e.enrolled_at DESC, e.id DESC'
        );
        $statement->execute([':student_id' => $studentId]);
        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->connection->prepare('SELECT id, student_id, programme_id, academic_term_id, status, enrolled_at FROM student_enrolments WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(array $data): int
    {
        $data = $this->normalize($data);
        $statement = $this->connection->prepare(
            'INSERT INTO student_enrolments (student_id, programme_id, academic_term_id, status, enrolled_at)
             VALUES (:student_id, :programme_id, :academic_term_id, :status, :enrolled_at)'
        );
        $statement->execute($this->params($data));
        return (int) $this->connection->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $data = $this->normalize($data);
        $statement = $this->connection->prepare(
            'UPDATE student_enrolments SET student_id = :student_id, programme_id = :programme_id, academic_term_id = :academic_term_id, status = :status, enrolled_at = :enrolled_at WHERE id = :id'
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
        if ($data['programme_id'] <= 0) {
            $errors[] = 'Programme is required.';
        }
        if ($data['academic_term_id'] <= 0) {
            $errors[] = 'Academic term is required.';
        }
        if ($data['enrolled_at'] === '') {
            $errors[] = 'Enrolment date is required.';
        }
        if (!in_array($data['status'], self::STATUSES, true)) {
            $errors[] = 'Choose a valid enrolment status.';
        }
        return $errors;
    }

    /** @return array<string, mixed> */
    public function normalize(array $data): array
    {
        $status = strtolower(trim((string) ($data['status'] ?? 'active')));
        return [
            'student_id' => (int) ($data['student_id'] ?? 0),
            'programme_id' => (int) ($data['programme_id'] ?? 0),
            'academic_term_id' => (int) ($data['academic_term_id'] ?? 0),
            'status' => in_array($status, self::STATUSES, true) ? $status : 'active',
            'enrolled_at' => $this->dateOrToday((string) ($data['enrolled_at'] ?? '')),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function params(array $data): array
    {
        return [
            ':student_id' => $data['student_id'],
            ':programme_id' => $data['programme_id'],
            ':academic_term_id' => $data['academic_term_id'],
            ':status' => $data['status'],
            ':enrolled_at' => $data['enrolled_at'],
        ];
    }

    private function dateOrToday(string $date): string
    {
        $date = trim($date);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $date : date('Y-m-d');
    }
}
