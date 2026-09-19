<?php

final class AcademicTermRepository
{
    private const STATUSES = ['active', 'inactive', 'closed'];

    public function __construct(private PDO $connection)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->connection
            ->query('SELECT id, name, academic_year, semester, start_date, end_date, status FROM academic_terms ORDER BY academic_year DESC, start_date DESC, name')
            ->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function active(): array
    {
        $statement = $this->connection->prepare("SELECT id, name, academic_year, semester FROM academic_terms WHERE status = 'active' ORDER BY academic_year DESC, start_date DESC, name");
        $statement->execute();
        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->connection->prepare('SELECT id, name, academic_year, semester, start_date, end_date, status FROM academic_terms WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(array $data): int
    {
        $data = $this->normalize($data);
        $statement = $this->connection->prepare(
            'INSERT INTO academic_terms (name, academic_year, semester, start_date, end_date, status)
             VALUES (:name, :academic_year, :semester, :start_date, :end_date, :status)'
        );
        $statement->execute($this->params($data));
        return (int) $this->connection->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $data = $this->normalize($data);
        $statement = $this->connection->prepare(
            'UPDATE academic_terms SET name = :name, academic_year = :academic_year, semester = :semester, start_date = :start_date, end_date = :end_date, status = :status WHERE id = :id'
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
        if ($data['name'] === '') {
            $errors[] = 'Term name is required.';
        }
        if ($data['academic_year'] === '') {
            $errors[] = 'Academic year is required.';
        }
        if (!in_array($data['status'], self::STATUSES, true)) {
            $errors[] = 'Choose a valid term status.';
        }
        if ($data['start_date'] !== '' && $data['end_date'] !== '' && $data['end_date'] < $data['start_date']) {
            $errors[] = 'End date cannot be before start date.';
        }
        return $errors;
    }

    /** @return array<string, string> */
    public function normalize(array $data): array
    {
        $status = strtolower(trim((string) ($data['status'] ?? 'active')));
        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'academic_year' => trim((string) ($data['academic_year'] ?? '')),
            'semester' => trim((string) ($data['semester'] ?? '')),
            'start_date' => $this->dateOrEmpty((string) ($data['start_date'] ?? '')),
            'end_date' => $this->dateOrEmpty((string) ($data['end_date'] ?? '')),
            'status' => in_array($status, self::STATUSES, true) ? $status : 'active',
        ];
    }

    /** @param array<string, string> $data @return array<string, string|null> */
    private function params(array $data): array
    {
        return [
            ':name' => $data['name'],
            ':academic_year' => $data['academic_year'],
            ':semester' => $data['semester'] !== '' ? $data['semester'] : null,
            ':start_date' => $data['start_date'] !== '' ? $data['start_date'] : null,
            ':end_date' => $data['end_date'] !== '' ? $data['end_date'] : null,
            ':status' => $data['status'],
        ];
    }

    private function dateOrEmpty(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $date : '';
    }
}
