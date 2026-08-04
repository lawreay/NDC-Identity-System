<?php

final class SettingsRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * @return array<string, string>
     */
    public function getAll(): array
    {
        $statement = $this->connection->prepare('SELECT setting_key, setting_value FROM settings');
        $statement->execute();

        return (array) $statement->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function get(string $key, string $default = ''): string
    {
        $statement = $this->connection->prepare('SELECT setting_value FROM settings WHERE setting_key = :setting_key LIMIT 1');
        $statement->execute([':setting_key' => $key]);
        $value = $statement->fetchColumn();

        return $value === false ? $default : (string) $value;
    }

    /**
     * @param array<string, string> $settings
     */
    public function save(array $settings): bool
    {
        $statement = $this->connection->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :setting_value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );

        foreach ($settings as $key => $value) {
            if (!$statement->execute([':setting_key' => $key, ':setting_value' => $value])) {
                return false;
            }
        }

        return true;
    }
}
