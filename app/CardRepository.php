<?php

final class CardRepository
{
    private bool $schemaEnsured = false;

    public function __construct(private PDO $connection)
    {
    }

    /** @return array<string, mixed> */
    public function getOrCreateActiveCard(int $studentId, ?string $expiresAt = null): array
    {
        if ($studentId <= 0) {
            throw new RuntimeException('A valid student is required to issue an ID card.');
        }
        $this->ensureSchema();

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

    /**
     * Reissues a student's card after their identity details have changed.
     * Any previously active QR becomes revoked, preventing an outdated printed
     * card from being verified as current. The existing expiry date is kept.
     *
     * @return array<string, mixed>
     */
    public function reissueCard(int $studentId): array
    {
        if ($studentId <= 0) {
            throw new RuntimeException('A valid student is required to recalculate an ID card.');
        }

        $this->ensureSchema();
        $startedTransaction = false;

        try {
            if (!$this->connection->inTransaction()) {
                $this->connection->beginTransaction();
                $startedTransaction = true;
            }

            $activeCardQuery = $this->connection->prepare(
                "SELECT expires_at
                 FROM student_id_cards
                 WHERE student_id = :student_id AND status = 'ACTIVE'
                 ORDER BY id DESC
                 LIMIT 1
                 FOR UPDATE"
            );
            $activeCardQuery->execute([':student_id' => $studentId]);
            $activeCard = $activeCardQuery->fetch();

            $expiresAt = is_array($activeCard) ? $this->normalizeDate((string) ($activeCard['expires_at'] ?? '')) : null;
            if ($expiresAt === null || $expiresAt < date('Y-m-d')) {
                $expiresAt = date('Y-m-d', strtotime('+1 year'));
            }

            $revoke = $this->connection->prepare(
                "UPDATE student_id_cards
                 SET status = 'REVOKED', revoked_at = NOW()
                 WHERE student_id = :student_id AND status = 'ACTIVE'"
            );
            $revoke->execute([':student_id' => $studentId]);

            $guid = $this->newGuid();
            $insert = $this->connection->prepare(
                "INSERT INTO student_id_cards (student_id, guid, issued_at, expires_at, status)
                 VALUES (:student_id, :guid, NOW(), :expires_at, 'ACTIVE')"
            );
            $insert->execute([
                ':student_id' => $studentId,
                ':guid' => $guid,
                ':expires_at' => $expiresAt,
            ]);

            if ($startedTransaction) {
                $this->connection->commit();
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
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('Unable to recalculate the student ID card.', 0, $exception);
        }
    }

    /**
     * Reissues every currently active ID card. Students without an issued card
     * are intentionally skipped so this does not create cards unexpectedly.
     */
    public function reissueAllActiveCards(): int
    {
        $this->ensureSchema();

        try {
            $students = $this->connection->query(
                "SELECT DISTINCT student_id
                 FROM student_id_cards
                 WHERE status = 'ACTIVE'
                 ORDER BY student_id"
            )->fetchAll();

            if ($students === []) {
                return 0;
            }

            $this->connection->beginTransaction();
            $count = 0;
            foreach ($students as $student) {
                $studentId = (int) ($student['student_id'] ?? 0);
                if ($studentId <= 0) {
                    continue;
                }

                $this->reissueCard($studentId);
                $count++;
            }
            $this->connection->commit();

            return $count;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('Unable to recalculate active ID cards.', 0, $exception);
        }
    }

    /** @return array<string, mixed>|null */
    public function findByGuid(string $guid): ?array
    {
        $guid = strtolower(trim($guid));
        if (!$this->isGuid($guid)) {
            return null;
        }
        $this->ensureSchema();

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
        $this->ensureSchema();

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
        $this->ensureSchema();

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

    /**
     * The application has no general migration runner. Keep the table migration
     * here so existing installations receive the verification columns as soon
     * as a card is previewed or exported.
     */
    private function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        try {
            $this->connection->exec(
                "CREATE TABLE IF NOT EXISTS student_id_cards (\n                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n                    student_id INT NOT NULL,\n                    guid CHAR(36) NOT NULL,\n                    issued_at DATETIME NOT NULL,\n                    expires_at DATE NOT NULL,\n                    status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',\n                    revoked_at DATETIME NULL,\n                    PRIMARY KEY (id),\n                    UNIQUE KEY student_id_cards_guid_unique (guid),\n                    KEY student_id_cards_student_status_index (student_id, status),\n                    KEY student_id_cards_status_expiry_index (status, expires_at)\n                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            $columns = $this->columns();
            if (!isset($columns['student_id'])) {
                throw new RuntimeException('The existing student_id_cards table is missing student_id and cannot be upgraded automatically.');
            }

            if (!isset($columns['guid'])) {
                $this->connection->exec('ALTER TABLE student_id_cards ADD COLUMN guid CHAR(36) NULL AFTER student_id');
            }
            if (!isset($columns['issued_at'])) {
                $this->connection->exec('ALTER TABLE student_id_cards ADD COLUMN issued_at DATETIME NULL');
            }
            if (!isset($columns['expires_at'])) {
                $this->connection->exec('ALTER TABLE student_id_cards ADD COLUMN expires_at DATE NULL');
            }
            if (!isset($columns['status'])) {
                $this->connection->exec("ALTER TABLE student_id_cards ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE'");
            }
            if (!isset($columns['revoked_at'])) {
                $this->connection->exec('ALTER TABLE student_id_cards ADD COLUMN revoked_at DATETIME NULL');
            }

            // Legacy records need identifiers and dates before these fields can
            // become required. UUID() is evaluated per affected row by MySQL.
            $this->connection->exec("UPDATE student_id_cards SET guid = UUID() WHERE guid IS NULL OR guid = ''");
            $this->connection->exec('UPDATE student_id_cards SET issued_at = NOW() WHERE issued_at IS NULL');
            $this->connection->exec('UPDATE student_id_cards SET expires_at = DATE_ADD(CURDATE(), INTERVAL 1 YEAR) WHERE expires_at IS NULL');
            $this->connection->exec('ALTER TABLE student_id_cards MODIFY guid CHAR(36) NOT NULL');
            $this->connection->exec('ALTER TABLE student_id_cards MODIFY issued_at DATETIME NOT NULL');
            $this->connection->exec('ALTER TABLE student_id_cards MODIFY expires_at DATE NOT NULL');

            // Older releases allowed only one row per student through a
            // `unique_student_card` index. Recalculation retains the old card
            // as revoked history, so remove only that legacy one-column unique
            // index. The GUID uniqueness rule remains intact.
            $this->dropLegacyUniqueStudentIndex();
            $this->ensureIndex('student_id_cards_guid_unique', 'CREATE UNIQUE INDEX student_id_cards_guid_unique ON student_id_cards (guid)');
            $this->ensureIndex('student_id_cards_student_status_index', 'CREATE INDEX student_id_cards_student_status_index ON student_id_cards (student_id, status)');
            $this->ensureIndex('student_id_cards_status_expiry_index', 'CREATE INDEX student_id_cards_status_expiry_index ON student_id_cards (status, expires_at)');
            $this->schemaEnsured = true;
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to prepare the card-verification database table. Please apply the student ID card migration.', 0, $exception);
        }
    }

    /** @return array<string, bool> */
    private function columns(): array
    {
        $statement = $this->connection->query('SHOW COLUMNS FROM student_id_cards');
        $columns = [];
        foreach ($statement->fetchAll() as $column) {
            $name = strtolower((string) ($column['Field'] ?? ''));
            if ($name !== '') {
                $columns[$name] = true;
            }
        }
        return $columns;
    }

    private function ensureIndex(string $name, string $statement): void
    {
        $indexes = $this->connection->query('SHOW INDEX FROM student_id_cards')->fetchAll();
        $exists = false;
        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === $name) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $this->connection->exec($statement);
        }
    }

    private function dropLegacyUniqueStudentIndex(): void
    {
        $indexes = $this->connection->query('SHOW INDEX FROM student_id_cards')->fetchAll();
        $byName = [];
        foreach ($indexes as $index) {
            $name = (string) ($index['Key_name'] ?? '');
            if ($name === '' || $name === 'PRIMARY') {
                continue;
            }

            $byName[$name]['unique'] = ((int) ($index['Non_unique'] ?? 1)) === 0;
            $byName[$name]['columns'][(int) ($index['Seq_in_index'] ?? 0)] = strtolower((string) ($index['Column_name'] ?? ''));
        }

        foreach ($byName as $name => $index) {
            ksort($index['columns']);
            $columns = array_values($index['columns']);
            if (($index['unique'] ?? false) !== true || $columns !== ['student_id']) {
                continue;
            }

            $quotedName = '`' . str_replace('`', '``', $name) . '`';
            $this->connection->exec('DROP INDEX ' . $quotedName . ' ON student_id_cards');
        }
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
