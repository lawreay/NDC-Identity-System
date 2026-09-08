<?php

final class SettingsRepository
{
    private const DEFAULT_THEME = [
        'primary_color' => '#0b5ed7',
        'secondary_color' => '#0a7e8c',
        'accent_color' => '#f4b400',
    ];

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

    /**
     * Return the configured card theme with safe, normalized hex colors.
     *
     * @param array<string, mixed> $settings
     * @return array{primary_color: string, secondary_color: string, accent_color: string}
     */
    public static function themeFromSettings(array $settings): array
    {
        $theme = [];
        foreach (self::DEFAULT_THEME as $key => $default) {
            $value = strtoupper(trim((string) ($settings[$key] ?? '')));
            if (preg_match('/^#[0-9A-F]{6}$/', $value) !== 1) {
                $value = strtoupper($default);
            }
            $theme[$key] = $value;
        }

        return $theme;
    }
}
