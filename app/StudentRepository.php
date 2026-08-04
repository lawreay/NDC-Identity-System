<?php

final class StudentRepository
{
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

    public function generateStudentNumber(int $id, string $firstName, string $lastName): string
    {
        $existing = $this->findById($id);
        if ($existing && !empty($existing['student_number'])) {
            return (string) $existing['student_number'];
        }

        $cleanFirst = preg_replace('/[^A-Za-z]/', '', $firstName) ?: 'STU';
        $cleanLast = preg_replace('/[^A-Za-z]/', '', $lastName) ?: 'STU';

        $prefix = strtoupper(substr($cleanLast, 0, 3) . substr($cleanFirst, 0, 2));
        $prefix = $prefix !== '' ? $prefix : 'STU';

        $statement = $this->connection->prepare('SELECT student_number FROM students WHERE student_number LIKE :prefix LIMIT 1');
        $statement->execute([':prefix' => $prefix . '%']);
        $matches = $statement->fetchAll();

        $sequence = count($matches) + 1;
        $studentNumber = $prefix . str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);

        $this->connection->prepare('UPDATE students SET student_number = :student_number WHERE id = :id')->execute([
            ':student_number' => $studentNumber,
            ':id' => $id,
        ]);

        return $studentNumber;
    }
}
