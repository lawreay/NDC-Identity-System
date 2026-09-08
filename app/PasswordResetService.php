<?php

namespace App;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class PasswordResetService
{
    private const TOKEN_BYTES = 32;
    private const TOKEN_TTL = '+1 hour';

    /**
     * @param array<string, string> $settings
     */
    public function __construct(private PDO $connection, private array $settings = [])
    {
    }

    public function requestReset(string $email): void
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }

        $this->deleteExpiredTokens();

        $statement = $this->connection->prepare(
            'SELECT id, name, email FROM users WHERE email = :email AND is_active = 1 LIMIT 1'
        );
        $statement->execute([':email' => $email]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return;
        }

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new DateTimeImmutable(self::TOKEN_TTL))->format('Y-m-d H:i:s');

        $this->connection->prepare('DELETE FROM password_resets WHERE user_id = :user_id')
            ->execute([':user_id' => (int) $user['id']]);

        $insert = $this->connection->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)'
        );
        $insert->execute([
            ':user_id' => (int) $user['id'],
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
        ]);

        $link = $this->buildResetUrl($token);
        try {
            $this->sendResetEmail((string) $user['email'], (string) $user['name'], $link);
        } catch (Throwable $exception) {
            $this->connection->prepare('DELETE FROM password_resets WHERE user_id = :user_id')
                ->execute([':user_id' => (int) $user['id']]);
            error_log('Password reset email failed: ' . $exception->getMessage());
            throw new RuntimeException('Unable to send the password reset email. Please contact the administrator.');
        }
    }

    public function resetPassword(string $token, string $password, string $confirmation): void
    {
        $token = trim($token);
        if ($token === '' || !ctype_xdigit($token) || strlen($token) !== self::TOKEN_BYTES * 2) {
            throw new RuntimeException('This password reset link is invalid.');
        }

        if ($password === '') {
            throw new RuntimeException('New password is required.');
        }

        if ($password !== $confirmation) {
            throw new RuntimeException('New password and confirmation do not match.');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('New password must be at least 8 characters long.');
        }

        $this->deleteExpiredTokens();

        $statement = $this->connection->prepare(
            'SELECT pr.user_id, u.is_active
             FROM password_resets pr
             INNER JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = :token_hash AND pr.expires_at >= NOW()
             LIMIT 1'
        );
        $statement->execute([':token_hash' => hash('sha256', $token)]);
        $reset = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$reset || (int) ($reset['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('This password reset link is invalid or has expired.');
        }

        $this->connection->beginTransaction();
        try {
            $this->connection->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
                ->execute([
                    ':hash' => Auth::hashPassword($password),
                    ':id' => (int) $reset['user_id'],
                ]);

            $this->connection->prepare('DELETE FROM password_resets WHERE user_id = :user_id')
                ->execute([':user_id' => (int) $reset['user_id']]);

            $this->connection->commit();
        } catch (Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    private function deleteExpiredTokens(): void
    {
        $this->connection->prepare('DELETE FROM password_resets WHERE expires_at < NOW()')->execute();
    }

    private function buildResetUrl(string $token): string
    {
        $configuredUrl = trim((string) ($this->settings['app_url'] ?? $this->settings['site_url'] ?? ''));
        if ($configuredUrl !== '') {
            return rtrim($configuredUrl, '/') . '/reset-password.php?token=' . rawurlencode($token);
        }

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

        $scheme = $isSecure ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        $basePath = $scriptDir === '' || $scriptDir === '.' ? '' : $scriptDir;

        return $scheme . '://' . $host . $basePath . '/reset-password.php?token=' . rawurlencode($token);
    }

    private function sendResetEmail(string $email, string $name, string $link): void
    {
        $appName = trim((string) ($this->settings['organization_name'] ?? $this->settings['school_name'] ?? 'NDC Identity System'));
        $fromEmail = trim((string) (
            $this->settings['smtp_from_email']
            ?? $this->settings['mail_from_email']
            ?? $this->settings['organization_email']
            ?? 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        ));
        $fromName = trim((string) ($this->settings['smtp_from_name'] ?? $this->settings['mail_from_name'] ?? $appName));
        $subject = 'Reset your ' . $appName . ' password';
        $plainBody = "Hello " . ($name !== '' ? $name : 'there') . ",\n\n"
            . "We received a request to reset your password.\n\n"
            . "Open this link to choose a new password:\n"
            . $link . "\n\n"
            . "This link expires in 1 hour. If you did not request this, you can ignore this email.\n\n"
            . $appName;

        $smtpHost = trim((string) ($this->settings['smtp_host'] ?? ''));
        if ($smtpHost !== '') {
            $this->sendViaSmtp($smtpHost, $fromEmail, $fromName, $email, $subject, $plainBody);
            return;
        }

        $headers = [
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'Reply-To: ' . $fromEmail,
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        if (!mail($email, $subject, $plainBody, implode("\r\n", $headers))) {
            throw new RuntimeException('mail() returned false.');
        }
    }

    private function sendViaSmtp(string $host, string $fromEmail, string $fromName, string $toEmail, string $subject, string $body): void
    {
        $port = (int) ($this->settings['smtp_port'] ?? 0);
        $encryption = strtolower(trim((string) ($this->settings['smtp_encryption'] ?? 'tls')));
        if ($port <= 0) {
            $port = $encryption === 'ssl' ? 465 : 587;
        }

        $transportHost = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $socket = @stream_socket_client($transportHost . ':' . $port, $errno, $errstr, 20);
        if (!$socket) {
            throw new RuntimeException('Could not connect to SMTP server: ' . $errstr);
        }

        stream_set_timeout($socket, 20);

        try {
            $this->expectSmtp($socket, [220]);
            $this->smtpCommand($socket, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), [250]);

            if ($encryption === 'tls') {
                $this->smtpCommand($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Could not enable SMTP TLS.');
                }
                $this->smtpCommand($socket, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), [250]);
            }

            $username = trim((string) ($this->settings['smtp_username'] ?? ''));
            $password = (string) ($this->settings['smtp_password'] ?? '');
            if ($username !== '') {
                $this->smtpCommand($socket, 'AUTH LOGIN', [334]);
                $this->smtpCommand($socket, base64_encode($username), [334]);
                $this->smtpCommand($socket, base64_encode($password), [235]);
            }

            $this->smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->smtpCommand($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->smtpCommand($socket, 'DATA', [354]);

            $message = $this->buildSmtpMessage($fromEmail, $fromName, $toEmail, $subject, $body);
            fwrite($socket, $message . "\r\n.\r\n");
            $this->expectSmtp($socket, [250]);
            $this->smtpCommand($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    private function smtpCommand($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expectSmtp($socket, $expectedCodes);
    }

    private function expectSmtp($socket, array $expectedCodes): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('Unexpected SMTP response: ' . trim($response));
        }

        return $response;
    }

    private function buildSmtpMessage(string $fromEmail, string $fromName, string $toEmail, string $subject, string $body): string
    {
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'To: <' . $toEmail . '>',
            'Subject: ' . $this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
        $normalizedBody = str_replace("\n", "\r\n", $normalizedBody);

        return implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody;
    }

    private function formatAddress(string $email, string $name): string
    {
        if ($name === '') {
            return '<' . $email . '>';
        }

        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
