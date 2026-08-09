<?php

namespace App;

use PDO;
use RuntimeException;

final class Auth
{
    private const SESSION_KEY = 'auth_user';
    private const CSRF_KEY = 'csrf_token';

    public static function boot(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function isAuthenticated(): bool
    {
        self::boot();
        return isset($_SESSION[self::SESSION_KEY]['id']);
    }

    public static function user(): ?array
    {
        self::boot();
        $user = $_SESSION[self::SESSION_KEY] ?? null;
        return is_array($user) ? $user : null;
    }

    public static function requireLogin(): array
    {
        self::boot();
        if (!self::isAuthenticated()) {
            $redirectTo = rawurlencode($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: login.php?next=' . $redirectTo);
            exit;
        }

        return self::user() ?? [];
    }

    public static function requireRole(array|string $roles): array
    {
        $user = self::requireLogin();
        $roleList = array_map('strval', (array) $roles);
        if ($user === [] || !in_array($user['role'] ?? '', $roleList, true)) {
            http_response_code(403);
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Access denied</title></head><body><h1>Access denied</h1><p>You do not have permission to view this page.</p></body></html>';
            exit;
        }

        return $user;
    }

    public static function login(string $email, string $password): array
    {
        self::boot();

        $email = strtolower(trim($email));
        if ($email === '' || $password === '') {
            throw new RuntimeException('Please enter both your email and password.');
        }

        $pdo = \Database::getConnection();
        $statement = $pdo->prepare('SELECT id, name, email, password_hash, role, is_active FROM users WHERE email = :email LIMIT 1');
        $statement->execute([':email' => $email]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            self::recordLoginAttempt($email, false, 'user_not_found');
            throw new RuntimeException('Invalid email or password.');
        }

        if ((int) ($user['is_active'] ?? 0) !== 1) {
            self::recordLoginAttempt($email, false, 'account_disabled');
            throw new RuntimeException('This account has been disabled.');
        }

        $storedHash = (string) ($user['password_hash'] ?? '');
        if (!self::verifyPassword($password, $storedHash)) {
            self::recordLoginAttempt($email, false, 'invalid_password');
            throw new RuntimeException('Invalid email or password.');
        }

        if (self::shouldRehash($storedHash)) {
            $newHash = self::hashPassword($password);
            $updateStatement = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
            $updateStatement->execute([':hash' => $newHash, ':id' => (int) $user['id']]);
        }

        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ];

        self::recordLoginAttempt($email, true, 'success');

        return $_SESSION[self::SESSION_KEY];
    }

    public static function logout(): void
    {
        self::boot();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function csrfToken(): string
    {
        self::boot();
        if (empty($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::CSRF_KEY];
    }

    public static function requireCsrf(): void
    {
        self::boot();
        if ($_SERVER['REQUEST_METHOD'] ?? 'GET' !== 'POST') {
            return;
        }

        $token = $_POST['_csrf'] ?? '';
        if (!is_string($token) || !hash_equals(self::csrfToken(), $token)) {
            throw new RuntimeException('This form has expired or is invalid. Please try again.');
        }
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword(string $password, string $storedHash): bool
    {
        if ($storedHash === '') {
            return false;
        }

        return password_verify($password, $storedHash);
    }

    private static function recordLoginAttempt(string $email, bool $success, string $reason = ''): void
    {
        $logDir = __DIR__ . '/../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $entry = [
            'ts' => date('c'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'email' => $email,
            'success' => $success ? 1 : 0,
            'reason' => $reason,
        ];

        $file = $logDir . '/login_attempts.log';
        @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function shouldRehash(string $storedHash): bool
    {
        if ($storedHash === '') {
            return false;
        }

        if (!str_starts_with($storedHash, '$2')) {
            return true;
        }

        return password_needs_rehash($storedHash, PASSWORD_DEFAULT);
    }
}
