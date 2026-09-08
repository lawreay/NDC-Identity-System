<?php

final class StudentRepository
{
    private const EDITABLE_FIELDS = [
        'student_number',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'district',
        'traditional_authority',
        'village',
        'phone_number',
        'qualification',
        'program',
        'class_level',
        'billing_category',
        'status',
        'photo_path',
    ];

    public function __construct(private PDO $connection)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query = ''): array
    {
        $sql = 'SELECT id, student_number, first_name, last_name, gender, program, class_level, status FROM students';
        $params = [];

        if (trim($query) !== '') {
            $term = '%' . trim($query) . '%';
            $sql .= ' WHERE student_number LIKE :term OR first_name LIKE :term OR last_name LIKE :term OR program LIKE :term OR class_level LIKE :term';
            $params[':term'] = $term;
        }

        $sql .= ' ORDER BY first_name, last_name, id';

        $statement = $this->connection->prepare($sql);

        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }

        $statement->execute();

        return $statement->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, student_number, photo_path, first_name, last_name, gender, date_of_birth, district, traditional_authority, village, phone_number, qualification, program, class_level, billing_category, status FROM students WHERE id = :id LIMIT 1'
        );
        $statement->execute([':id' => $id]);

        $student = $statement->fetch();

        return $student === false ? null : $student;
    }

    public function updatePhoto(int $id, string $photoPath): bool
    {
        $statement = $this->connection->prepare('UPDATE students SET photo_path = :photo_path WHERE id = :id');
        return $statement->execute([
            ':photo_path' => $photoPath,
            ':id' => $id,
        ]);
    }

    public function create(array $data): int
    {
        $data = $this->normalizeData($data);
        if (trim((string) ($data['student_number'] ?? '')) === '') {
            $data['student_number'] = $this->nextStudentNumber((string) ($data['first_name'] ?? ''), (string) ($data['last_name'] ?? ''));
        }

        $fields = array_values(array_filter(self::EDITABLE_FIELDS, static fn (string $field): bool => $field !== 'photo_path' || ($data[$field] ?? null) !== null));
        $columns = implode(', ', $fields);
        $placeholders = implode(', ', array_map(static fn (string $field): string => ':' . $field, $fields));
        $statement = $this->connection->prepare("INSERT INTO students ($columns) VALUES ($placeholders)");
        $statement->execute($this->parametersFor($data, $fields));

        return (int) $this->connection->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        if ($id <= 0 || $this->findById($id) === null) {
            return false;
        }

        $data = $this->normalizeData($data);
        $fields = array_values(array_filter(self::EDITABLE_FIELDS, static fn (string $field): bool => array_key_exists($field, $data)));
        if ($fields === []) {
            return false;
        }

        $assignments = implode(', ', array_map(static fn (string $field): string => "$field = :$field", $fields));
        $parameters = $this->parametersFor($data, $fields);
        $parameters[':id'] = $id;
        $statement = $this->connection->prepare("UPDATE students SET $assignments WHERE id = :id");

        return $statement->execute($parameters);
    }

    public function studentNumberExists(string $studentNumber, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM students WHERE student_number = :student_number';
        $parameters = [':student_number' => $studentNumber];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $parameters[':ignore_id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetch() !== false;
    }

    public function generateStudentNumber(int $id, string $firstName, string $lastName): string
    {
        $existing = $this->findById($id);
        if ($existing && !empty($existing['student_number'])) {
            return (string) $existing['student_number'];
        }

        $studentNumber = $this->nextStudentNumber($firstName, $lastName);

        $this->connection->prepare('UPDATE students SET student_number = :student_number WHERE id = :id')->execute([
            ':student_number' => $studentNumber,
            ':id' => $id,
        ]);

        return $studentNumber;
    }

    private function nextStudentNumber(string $firstName, string $lastName): string
    {
        $prefix = $this->studentNumberPrefix($firstName, $lastName);
        $statement = $this->connection->prepare('SELECT student_number FROM students WHERE student_number LIKE :prefix');
        $statement->execute([':prefix' => $prefix . '%']);

        $highestSequence = 0;
        foreach ($statement->fetchAll() as $row) {
            $studentNumber = (string) ($row['student_number'] ?? '');
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $studentNumber, $matches)) {
                $highestSequence = max($highestSequence, (int) $matches[1]);
            }
        }

        $sequence = $highestSequence + 1;
        do {
            $candidate = $prefix . str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
            $sequence++;
        } while ($this->studentNumberExists($candidate));

        return $candidate;
    }

    private function studentNumberPrefix(string $firstName, string $lastName): string
    {
        $cleanFirst = preg_replace('/[^A-Za-z]/', '', $firstName) ?: 'STU';
        $cleanLast = preg_replace('/[^A-Za-z]/', '', $lastName) ?: 'STU';

        return 'S' . strtoupper(substr($cleanLast, 0, 3) . substr($cleanFirst, 0, 2));
    }

    private function normalizeData(array $data): array
    {
        $normalized = [];
        foreach (self::EDITABLE_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            $normalized[$field] = $value === '' ? null : $value;
        }

        return $normalized;
    }

    private function parametersFor(array $data, array $fields): array
    {
        $parameters = [];
        foreach ($fields as $field) {
            $parameters[':' . $field] = $data[$field] ?? null;
        }

        return $parameters;
    }
}
