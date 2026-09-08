<?php

namespace App;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listUsers(): array
    {
        $statement = $this->connection->prepare('SELECT id, name, email, role, is_active FROM users ORDER BY name, email, id');
        $statement->execute();

        return $statement->fetchAll();
    }

    public function emailExists(string $email): bool
    {
        $statement = $this->connection->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $statement->execute([':email' => strtolower(trim($email))]);

        return $statement->fetch() !== false;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $columns = [
            'name' => trim((string) ($data['name'] ?? '')),
            'email' => strtolower(trim((string) ($data['email'] ?? ''))),
            'password_hash' => (string) ($data['password_hash'] ?? ''),
            'role' => (string) ($data['role'] ?? 'Administrator'),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];

        $availableColumns = $this->tableColumns();
        $now = date('Y-m-d H:i:s');
        if (in_array('created_at', $availableColumns, true)) {
            $columns['created_at'] = $now;
        }
        if (in_array('updated_at', $availableColumns, true)) {
            $columns['updated_at'] = $now;
        }

        $columns = array_filter(
            $columns,
            static fn (mixed $value, string $column): bool => in_array($column, $availableColumns, true),
            ARRAY_FILTER_USE_BOTH
        );

        $columnNames = array_keys($columns);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columnNames);
        $statement = $this->connection->prepare(
            'INSERT INTO users (' . implode(', ', $columnNames) . ') VALUES (' . implode(', ', $placeholders) . ')'
        );

        $parameters = [];
        foreach ($columns as $column => $value) {
            $parameters[':' . $column] = $value;
        }
        $statement->execute($parameters);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @return array<int, string>
     */
    private function tableColumns(): array
    {
        $statement = $this->connection->prepare('SHOW COLUMNS FROM users');
        $statement->execute();

        return array_map(
            static fn (array $row): string => (string) ($row['Field'] ?? ''),
            $statement->fetchAll()
        );
    }
}
