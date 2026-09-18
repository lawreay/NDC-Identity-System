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
        'guardian_name',
        'guardian_relationship',
        'guardian_phone',
        'guardian_alt_phone',
        'guardian_email',
        'guardian_address',
        'status',
        'photo_path',
    ];

    private const DETAIL_COLUMNS = 'id, student_number, photo_path, first_name, last_name, gender, date_of_birth, district, traditional_authority, village, phone_number, qualification, program, class_level, billing_category, guardian_name, guardian_relationship, guardian_phone, guardian_alt_phone, guardian_email, guardian_address, status';

    private bool $schemaEnsured = false;

    public function __construct(private PDO $connection)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query = ''): array
    {
        $this->ensureProfileSchema();
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
        $this->ensureProfileSchema();
        $statement = $this->connection->prepare('SELECT ' . self::DETAIL_COLUMNS . ' FROM students WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $id]);

        $student = $statement->fetch();

        return $student === false ? null : $student;
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    public function findByIds(array $ids): array
    {
        $this->ensureProfileSchema();
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $parameters = [];
        foreach ($ids as $index => $id) {
            $placeholder = ':id_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $id;
        }

        $statement = $this->connection->prepare(
            'SELECT ' . self::DETAIL_COLUMNS . ' FROM students WHERE id IN (' . implode(', ', $placeholders) . ') ORDER BY first_name, last_name, id'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function updatePhoto(int $id, string $photoPath): bool
    {
        $this->ensureProfileSchema();
        $statement = $this->connection->prepare('UPDATE students SET photo_path = :photo_path WHERE id = :id');
        return $statement->execute([
            ':photo_path' => $photoPath,
            ':id' => $id,
        ]);
    }

    public function create(array $data): int
    {
        $this->ensureProfileSchema();
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
        $this->ensureProfileSchema();
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

    public function delete(int $id): bool
    {
        $this->ensureProfileSchema();
        if ($id <= 0) {
            return false;
        }

        $startedTransaction = false;

        try {
            if (!$this->connection->inTransaction()) {
                $this->connection->beginTransaction();
                $startedTransaction = true;
            }

            try {
                $this->connection
                    ->prepare('DELETE FROM student_id_cards WHERE student_id = :id')
                    ->execute([':id' => $id]);
            } catch (PDOException $exception) {
                if ((string) $exception->getCode() !== '42S02') {
                    throw $exception;
                }
            }

            $statement = $this->connection->prepare('DELETE FROM students WHERE id = :id');
            $statement->execute([':id' => $id]);
            $deleted = $statement->rowCount() === 1;

            if ($startedTransaction) {
                $this->connection->commit();
            }

            return $deleted;
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    public function studentNumberExists(string $studentNumber, ?int $ignoreId = null): bool
    {
        $this->ensureProfileSchema();
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
        $this->ensureProfileSchema();
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

    private function ensureProfileSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        $columns = $this->columns();
        $additions = [
            'guardian_name' => "ALTER TABLE students ADD COLUMN guardian_name VARCHAR(160) NULL AFTER billing_category",
            'guardian_relationship' => "ALTER TABLE students ADD COLUMN guardian_relationship VARCHAR(80) NULL AFTER guardian_name",
            'guardian_phone' => "ALTER TABLE students ADD COLUMN guardian_phone VARCHAR(40) NULL AFTER guardian_relationship",
            'guardian_alt_phone' => "ALTER TABLE students ADD COLUMN guardian_alt_phone VARCHAR(40) NULL AFTER guardian_phone",
            'guardian_email' => "ALTER TABLE students ADD COLUMN guardian_email VARCHAR(160) NULL AFTER guardian_alt_phone",
            'guardian_address' => "ALTER TABLE students ADD COLUMN guardian_address TEXT NULL AFTER guardian_email",
        ];

        foreach ($additions as $column => $statement) {
            if (!isset($columns[$column])) {
                $this->connection->exec($statement);
            }
        }

        $this->schemaEnsured = true;
    }

    /** @return array<string, bool> */
    private function columns(): array
    {
        $statement = $this->connection->query('SHOW COLUMNS FROM students');
        $columns = [];
        foreach ($statement->fetchAll() as $column) {
            $name = strtolower((string) ($column['Field'] ?? ''));
            if ($name !== '') {
                $columns[$name] = true;
            }
        }

        return $columns;
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
