<?php

namespace App\Services;

use PDO;

final class CredentialVerificationService
{
    public function __construct(private PDO $connection)
    {
    }

    /** @return array{state:string, credential:array<string, mixed>|null} */
    public function verify(string $type, string $token): array
    {
        $type = strtolower(trim($type));
        $token = strtolower(trim($token));
        if (!in_array($type, ['certificate', 'transcript'], true) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return ['state' => 'INVALID', 'credential' => null];
        }

        $table = $type === 'certificate' ? 'academic_certificates' : 'academic_transcripts';
        $number = $type === 'certificate' ? 'certificate_number' : 'transcript_number';
        $statement = $this->connection->prepare(
            'SELECT c.id, c.verification_token, c.status, c.issued_at, c.revoked_at, c.revocation_reason,
                    c.' . $number . ' AS credential_number,
                    s.student_number, s.first_name, s.last_name,
                    p.code AS programme_code, p.name AS programme_name, p.qualification
             FROM ' . $table . ' c
             INNER JOIN students s ON s.id = c.student_id
             INNER JOIN academic_programmes p ON p.id = c.programme_id
             WHERE c.verification_token = :token
             LIMIT 1'
        );
        $statement->execute([':token' => $token]);
        $credential = $statement->fetch();
        if (!is_array($credential)) {
            return ['state' => 'INVALID', 'credential' => null];
        }
        if (($credential['status'] ?? '') === 'revoked') {
            return ['state' => 'REVOKED', 'credential' => $credential];
        }
        if (($credential['status'] ?? '') !== 'issued') {
            return ['state' => 'INVALID', 'credential' => null];
        }

        return ['state' => 'VALID', 'credential' => $credential];
    }
}
