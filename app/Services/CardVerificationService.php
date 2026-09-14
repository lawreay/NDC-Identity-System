<?php

namespace App\Services;

use CardRepository;
use RuntimeException;

final class CardVerificationService
{
    public function __construct(private CardRepository $cards)
    {
    }

    /** @param array<string, mixed> $student @return array<string, mixed> */
    public function issueForStudent(array $student, string $verificationEndpoint = ''): array
    {
        $card = $this->cards->getOrCreateActiveCard((int) ($student['id'] ?? 0));
        $student['card_guid'] = (string) $card['guid'];
        $student['issue_date'] = substr((string) $card['issued_at'], 0, 10);
        $student['expiry_date'] = (string) $card['expires_at'];
        $student['verification_url'] = self::verificationUrl((string) $card['guid'], $verificationEndpoint);

        return $student;
    }

    /** @return array{state:string, card:array<string, mixed>|null} */
    public function verify(string $guid): array
    {
        $card = $this->cards->findByGuid($guid);
        if ($card === null) {
            return ['state' => 'INVALID', 'card' => null];
        }

        if (strtoupper((string) $card['card_status']) === 'REVOKED') {
            return ['state' => 'REVOKED', 'card' => $card];
        }
        if ((string) $card['expires_at'] < date('Y-m-d')) {
            return ['state' => 'EXPIRED', 'card' => $card];
        }

        return ['state' => 'VALID', 'card' => $card];
    }

    public static function verificationUrl(string $guid, string $configuredEndpoint = ''): string
    {
        $endpoint = trim($configuredEndpoint);
        if (!preg_match('~^https?://~i', $endpoint)) {
            $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
            if (preg_match('/^[a-z0-9.-]+(?::\d+)?$/i', $host) === 1) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
                $endpoint = $scheme . '://' . $host . rtrim($scriptDirectory, '/') . '/verify.php';
            } else {
                $endpoint = 'https://ndc.edu/verify.php';
            }
        }

        return rtrim($endpoint, '?&') . (str_contains($endpoint, '?') ? '&' : '?') . 'guid=' . rawurlencode($guid);
    }
}
