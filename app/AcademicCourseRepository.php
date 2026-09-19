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
                    p.code AS programme_code, p.name AS programme_name,
                    GROUP_CONCAT(DISTINCT linked.code ORDER BY linked.name SEPARATOR ", ") AS programme_codes,
                    GROUP_CONCAT(DISTINCT linked.name ORDER BY linked.name SEPARATOR ", ") AS programme_names
             FROM academic_courses c
             INNER JOIN academic_programmes p ON p.id = c.programme_id
             LEFT JOIN academic_course_programmes cp ON cp.course_id = c.id
             LEFT JOIN academic_programmes linked ON linked.id = cp.programme_id
             GROUP BY c.id, c.programme_id, c.code, c.name, c.credits, c.semester, c.course_type, c.is_compulsory, c.status, p.code, p.name
             ORDER BY c.semester, c.code, c.name'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->connection->prepare('SELECT id, programme_id, code, name, credits, semester, course_type, is_compulsory, status FROM academic_courses WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }

        $row['programme_ids'] = $this->programmeIdsForCourse($id);
        if ($row['programme_ids'] === []) {
            $row['programme_ids'] = [(int) $row['programme_id']];
        }

        return $row;
    }

    public function create(array $data): int
    {
        $data = $this->normalize($data);
        $statement = $this->connection->prepare(
            'INSERT INTO academic_courses (programme_id, code, name, credits, semester, course_type, is_compulsory, status)
             VALUES (:programme_id, :code, :name, :credits, :semester, :course_type, :is_compulsory, :status)'
        );
        $statement->execute($this->params($data));
        $id = (int) $this->connection->lastInsertId();
        $this->syncProgrammes($id, $data['programme_ids']);
        return $id;
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
        $updated = $statement->execute($params);
        if ($updated) {
            $this->syncProgrammes($id, $data['programme_ids']);
        }
        return $updated;
    }

    /** @return array<int, string> */
    public function validate(array $data): array
    {
        $data = $this->normalize($data);
        $errors = [];
        if ($data['programme_ids'] === []) {
            $errors[] = 'Choose at least one programme.';
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
        $normalized = [
            'programme_ids' => $this->normalizeProgrammeIds($data['programme_ids'] ?? ($data['programme_id'] ?? [])),
            'code' => strtoupper(trim((string) ($data['code'] ?? ''))),
            'name' => trim((string) ($data['name'] ?? '')),
            'credits' => max(0, (float) ($data['credits'] ?? 0)),
            'semester' => trim((string) ($data['semester'] ?? '')),
            'course_type' => in_array($type, self::TYPES, true) ? $type : 'core',
            'is_compulsory' => !empty($data['is_compulsory']) ? 1 : 0,
            'status' => in_array($status, self::STATUSES, true) ? $status : 'active',
        ];
        $normalized['programme_id'] = $normalized['programme_ids'][0] ?? 0;

        return $normalized;
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

    /** @return array<int, int> */
    private function normalizeProgrammeIds(mixed $value): array
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        $ids = [];
        foreach ($value as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /** @return array<int, int> */
    private function programmeIdsForCourse(int $courseId): array
    {
        $statement = $this->connection->prepare('SELECT programme_id FROM academic_course_programmes WHERE course_id = :course_id ORDER BY programme_id');
        $statement->execute([':course_id' => $courseId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param array<int, int> $programmeIds */
    private function syncProgrammes(int $courseId, array $programmeIds): void
    {
        $this->connection->prepare('DELETE FROM academic_course_programmes WHERE course_id = :course_id')->execute([':course_id' => $courseId]);

        $statement = $this->connection->prepare(
            'INSERT INTO academic_course_programmes (course_id, programme_id) VALUES (:course_id, :programme_id)'
        );
        foreach ($programmeIds as $programmeId) {
            $statement->execute([
                ':course_id' => $courseId,
                ':programme_id' => $programmeId,
            ]);
        }
    }
}
