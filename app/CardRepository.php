<?php

final class CardRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /** @return array<string, mixed> */
    public function getOrCreateActiveCard(int $studentId, ?string $expiresAt = null): array
    {
        if ($studentId <= 0) {
            throw new RuntimeException('A valid student is required to issue an ID card.');
        }

        $statement = $this->connection->prepare(
            "SELECT id, student_id, guid, issued_at, expires_at, status, revoked_at\n             FROM student_id_cards\n             WHERE student_id = :student_id AND status = 'ACTIVE'\n             ORDER BY id DESC\n             LIMIT 1"
        );
        try {
            $statement->execute([':student_id' => $studentId]);
            $card = $statement->fetch();
        } catch (PDOException $exception) {
            throw new RuntimeException('Card verification is not set up. Apply the student ID card migration before issuing cards.', 0, $exception);
        }

        if (is_array($card)) {
            return $card;
        }

        $expiresAt = $this->normalizeDate($expiresAt) ?? date('Y-m-d', strtotime('+1 year'));
        $guid = $this->newGuid();
        $insert = $this->connection->prepare(
            "INSERT INTO student_id_cards (student_id, guid, issued_at, expires_at, status)\n             VALUES (:student_id, :guid, NOW(), :expires_at, 'ACTIVE')"
        );
        try {
            $insert->execute([
                ':student_id' => $studentId,
                ':guid' => $guid,
                ':expires_at' => $expiresAt,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to issue the student ID card.', 0, $exception);
        }

        return [
            'id' => (int) $this->connection->lastInsertId(),
            'student_id' => $studentId,
            'guid' => $guid,
            'issued_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
            'status' => 'ACTIVE',
            'revoked_at' => null,
        ];
    }

    /** @return array<string, mixed>|null */
    public function findByGuid(string $guid): ?array
    {
        $guid = strtolower(trim($guid));
        if (!$this->isGuid($guid)) {
            return null;
        }

        $statement = $this->connection->prepare(
            'SELECT c.id AS card_id, c.student_id, c.guid, c.issued_at, c.expires_at, c.status AS card_status, c.revoked_at,\n                    s.student_number, s.first_name, s.last_name, s.program, s.photo_path, s.status AS student_status\n             FROM student_id_cards c\n             INNER JOIN students s ON s.id = c.student_id\n             WHERE c.guid = :guid\n             LIMIT 1'
        );
        try {
            $statement->execute([':guid' => $guid]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Card verification is not set up. Apply the student ID card migration.', 0, $exception);
        }

        $card = $statement->fetch();
        return is_array($card) ? $card : null;
    }

    /** @return array<string, mixed>|null */
    public function findLatestByStudentId(int $studentId): ?array
    {
        if ($studentId <= 0) {
            return null;
        }

        $statement = $this->connection->prepare(
            'SELECT id, student_id, guid, issued_at, expires_at, status, revoked_at\n             FROM student_id_cards\n             WHERE student_id = :student_id\n             ORDER BY id DESC\n             LIMIT 1'
        );
        try {
            $statement->execute([':student_id' => $studentId]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Card verification is not set up. Apply the student ID card migration.', 0, $exception);
        }

        $card = $statement->fetch();
        return is_array($card) ? $card : null;
    }

    public function revokeCard(string $guid): bool
    {
        return $this->updateStatus($guid, 'REVOKED');
    }

    public function updateStatus(string $guid, string $status): bool
    {
        $guid = strtolower(trim($guid));
        $status = strtoupper(trim($status));
        if (!$this->isGuid($guid) || !in_array($status, ['ACTIVE', 'REVOKED'], true)) {
            return false;
        }

        $statement = $this->connection->prepare('UPDATE student_id_cards SET status = :status, revoked_at = :revoked_at WHERE guid = :guid');
        return $statement->execute([
            ':guid' => $guid,
            ':status' => $status,
            ':revoked_at' => $status === 'REVOKED' ? date('Y-m-d H:i:s') : null,
        ]) && $statement->rowCount() === 1;
    }

    private function normalizeDate(?string $date): ?string
    {
        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $date : null;
    }

    private function isGuid(string $guid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $guid) === 1;
    }

    private function newGuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
