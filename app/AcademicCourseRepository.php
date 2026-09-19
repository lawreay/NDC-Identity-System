<?php

final class AcademicCourseRepository
{
    private const STATUSES = ['active', 'inactive'];
    private const TYPES = ['core', 'elective', 'optional'];

    public function __construct(private PDO $connection)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->connection->query(
            'SELECT c.id, c.programme_id, c.code, c.name, c.credits, c.semester, c.course_type, c.is_compulsory, c.status,
                    p.code AS programme_code, p.name AS programme_name
             FROM academic_courses c
             INNER JOIN academic_programmes p ON p.id = c.programme_id
             ORDER BY p.name, c.semester, c.code'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->connection->prepare('SELECT id, programme_id, code, name, credits, semester, course_type, is_compulsory, status FROM academic_courses WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(array $data): int
    {
        $data = $this->normalize($data);
        $statement = $this->connection->prepare(
            'INSERT INTO academic_courses (programme_id, code, name, credits, semester, course_type, is_compulsory, status)
             VALUES (:programme_id, :code, :name, :credits, :semester, :course_type, :is_compulsory, :status)'
        );
        $statement->execute($this->params($data));
        return (int) $this->connection->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $data = $this->normalize($data);
        $statement = $this->connection->prepare(
            'UPDATE academic_courses
             SET programme_id = :programme_id, code = :code, name = :name, credits = :credits, semester = :semester,
                 course_type = :course_type, is_compulsory = :is_compulsory, status = :status
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
        if ($data['programme_id'] <= 0) {
            $errors[] = 'Programme is required.';
        }
        if ($data['code'] === '') {
            $errors[] = 'Course code is required.';
        }
        if ($data['name'] === '') {
            $errors[] = 'Course name is required.';
        }
        if ($data['credits'] < 0) {
            $errors[] = 'Credits cannot be negative.';
        }
        if (!in_array($data['course_type'], self::TYPES, true)) {
            $errors[] = 'Choose a valid course type.';
        }
        if (!in_array($data['status'], self::STATUSES, true)) {
            $errors[] = 'Choose a valid course status.';
        }
        return $errors;
    }

    /** @return array<string, mixed> */
    public function normalize(array $data): array
    {
        $status = strtolower(trim((string) ($data['status'] ?? 'active')));
        $type = strtolower(trim((string) ($data['course_type'] ?? 'core')));
        return [
            'programme_id' => (int) ($data['programme_id'] ?? 0),
            'code' => strtoupper(trim((string) ($data['code'] ?? ''))),
            'name' => trim((string) ($data['name'] ?? '')),
            'credits' => max(0, (float) ($data['credits'] ?? 0)),
            'semester' => trim((string) ($data['semester'] ?? '')),
            'course_type' => in_array($type, self::TYPES, true) ? $type : 'core',
            'is_compulsory' => !empty($data['is_compulsory']) ? 1 : 0,
            'status' => in_array($status, self::STATUSES, true) ? $status : 'active',
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function params(array $data): array
    {
        return [
            ':programme_id' => $data['programme_id'],
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':credits' => $data['credits'],
            ':semester' => $data['semester'] !== '' ? $data['semester'] : null,
            ':course_type' => $data['course_type'],
            ':is_compulsory' => $data['is_compulsory'],
            ':status' => $data['status'],
        ];
    }
}
