<?php

$loadEnv = static function (string $path): void {
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        $value = trim($value, "\"'");
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
};

$loadEnv(dirname(__DIR__) . '/.env');

$env = static function (string $key, string $default = ''): string {
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
};

return [
    'host' => $env('DB_HOST', 'sql305.infinityfree.com'),
    'port' => (int) $env('DB_PORT', '3306'),
    'name' => $env('DB_NAME', 'if0_42519572_ndc'),
    'user' => $env('DB_USER', 'if0_42519572'),
    'pass' => $env('DB_PASS'),
];
