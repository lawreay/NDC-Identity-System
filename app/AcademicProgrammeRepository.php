<?php

final class AcademicProgrammeRepository
{
    private const STATUSES = ['active', 'inactive'];

    public function __construct(private PDO $connection)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->connection
            ->query('SELECT id, code, name, qualification, duration, status, created_at, updated_at FROM academic_programmes ORDER BY name, code')
            ->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function active(): array
    {
        $statement = $this->connection->prepare("SELECT id, code, name, qualification FROM academic_programmes WHERE status = 'active' ORDER BY name, code");
        $statement->execute();
        return $statement->fetchAll();
    }

    /**
     * @return array<int, string>
     */
    public function activeNames(): array
    {
        return array_values(array_map(
            static fn (array $programme): string => (string) ($programme['name'] ?? ''),
            array_filter($this->active(), static fn (array $programme): bool => trim((string) ($programme['name'] ?? '')) !== '')
        ));
    }

    /**
     * Imports legacy programme names from the old settings textarea.
     *
     * @param array<int, string> $names
     */
    public function seedFromNames(array $names): int
    {
        $created = 0;
        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $code = $this->codeForName($name);
            $statement = $this->connection->prepare('SELECT id FROM academic_programmes WHERE code = :code OR name = :name LIMIT 1');
            $statement->execute([':code' => $code, ':name' => $name]);
            if ($statement->fetch()) {
                continue;
            }

            $this->create([
                'code' => $code,
                'name' => $name,
                'qualification' => $name,
                'duration' => '',
                'status' => 'active',
            ]);
            $created++;
        }

        return $created;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->connection->prepare('SELECT id, code, name, qualification, duration, status FROM academic_programmes WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(array $data): int
    {
        $data = $this->normalize($data);
        $statement = $this->connection->prepare(
            'INSERT INTO academic_programmes (code, name, qualification, duration, status) VALUES (:code, :name, :qualification, :duration, :status)'
        );
        $statement->execute($this->params($data));
        return (int) $this->connection->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $data = $this->normalize($data);
        $statement = $this->connection->prepare(
            'UPDATE academic_programmes SET code = :code, name = :name, qualification = :qualification, duration = :duration, status = :status WHERE id = :id'
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
        if ($data['code'] === '') {
            $errors[] = 'Programme code is required.';
        }
        if ($data['name'] === '') {
            $errors[] = 'Programme name is required.';
        }
        if ($data['qualification'] === '') {
            $errors[] = 'Qualification is required.';
        }
        if (!in_array($data['status'], self::STATUSES, true)) {
            $errors[] = 'Choose a valid programme status.';
        }
        return $errors;
    }

    /** @return array<string, string> */
    public function normalize(array $data): array
    {
        $status = strtolower(trim((string) ($data['status'] ?? 'active')));
        return [
            'code' => strtoupper(trim((string) ($data['code'] ?? ''))),
            'name' => trim((string) ($data['name'] ?? '')),
            'qualification' => trim((string) ($data['qualification'] ?? '')),
            'duration' => trim((string) ($data['duration'] ?? '')),
            'status' => in_array($status, self::STATUSES, true) ? $status : 'active',
        ];
    }

    /** @param array<string, string> $data @return array<string, string|null> */
    private function params(array $data): array
    {
        return [
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':qualification' => $data['qualification'],
            ':duration' => $data['duration'] !== '' ? $data['duration'] : null,
            ':status' => $data['status'],
        ];
    }

    private function codeForName(string $name): string
    {
        $letters = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $name) ?? '');
        if ($letters !== '') {
            return substr($letters, 0, 12);
        }

        return 'PROG' . strtoupper(substr(hash('crc32b', $name), 0, 6));
    }
}
