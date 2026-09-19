<?php

final class AcademicCertificateRepository
{
    private const STATUSES = ['draft', 'pending_approval', 'issued', 'revoked'];

    public function __construct(
        private PDO $connection,
        private AcademicEligibilityService $eligibilityService
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->connection->query(
            'SELECT c.id, c.student_id, c.programme_id, c.certificate_number, c.verification_token, c.status,
                    c.requested_at, c.submitted_at, c.issued_at, c.revoked_at, c.revocation_reason,
                    s.student_number, s.first_name, s.last_name,
                    p.code AS programme_code, p.name AS programme_name,
                    requester.name AS requested_by_name, issuer.name AS issued_by_name
             FROM academic_certificates c
             INNER JOIN students s ON s.id = c.student_id
             INNER JOIN academic_programmes p ON p.id = c.programme_id
             LEFT JOIN users requester ON requester.id = c.requested_by_user_id
             LEFT JOIN users issuer ON issuer.id = c.issued_by_user_id
             ORDER BY c.created_at DESC, c.id DESC'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT c.*, s.student_number, s.first_name, s.last_name,
                    p.code AS programme_code, p.name AS programme_name, p.qualification
             FROM academic_certificates c
             INNER JOIN students s ON s.id = c.student_id
             INNER JOIN academic_programmes p ON p.id = c.programme_id
             WHERE c.id = :id
             LIMIT 1'
        );
        $statement->execute([':id' => $id]);
        $certificate = $statement->fetch();

        return is_array($certificate) ? $certificate : null;
    }

    public function createDraft(int $studentId, int $programmeId, int $requestedByUserId): int
    {
        $assessment = $this->eligibilityService->assess($studentId, $programmeId);
        if ($assessment === null) {
            throw new RuntimeException('The student does not have an enrolment for the selected programme.');
        }
        if (!$assessment['eligible']) {
            throw new RuntimeException('The student is not eligible for a certificate. Complete the outstanding academic requirements first.');
        }

        $this->connection->beginTransaction();
        try {
            $token = bin2hex(random_bytes(32));
            $statement = $this->connection->prepare(
                "INSERT INTO academic_certificates
                    (student_id, programme_id, certificate_number, verification_token, status, eligibility_snapshot, requested_by_user_id)
                 VALUES (:student_id, :programme_id, '', :verification_token, 'draft', :eligibility_snapshot, :requested_by_user_id)"
            );
            $statement->execute([
                ':student_id' => $studentId,
                ':programme_id' => $programmeId,
                ':verification_token' => $token,
                ':eligibility_snapshot' => $this->snapshot($assessment),
                ':requested_by_user_id' => $requestedByUserId > 0 ? $requestedByUserId : null,
            ]);
            $id = (int) $this->connection->lastInsertId();
            $number = sprintf('NDC-CERT-%s-%06d', date('Y'), $id);
            $this->connection->prepare('UPDATE academic_certificates SET certificate_number = :number WHERE id = :id')
                ->execute([':number' => $number, ':id' => $id]);
            $this->connection->commit();

            return $id;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function submitForApproval(int $id): bool
    {
        return $this->transition($id, 'draft', "UPDATE academic_certificates SET status = 'pending_approval', submitted_at = CURRENT_TIMESTAMP WHERE id = :id AND status = 'draft'");
    }

    public function issue(int $id, int $userId): bool
    {
        $certificate = $this->find($id);
        if ($certificate === null || ($certificate['status'] ?? '') !== 'pending_approval') {
            return false;
        }

        $assessment = $this->eligibilityService->assess((int) $certificate['student_id'], (int) $certificate['programme_id']);
        if ($assessment === null || !$assessment['eligible']) {
            throw new RuntimeException('The certificate cannot be issued because the student is no longer eligible.');
        }

        $statement = $this->connection->prepare(
            "UPDATE academic_certificates
             SET status = 'issued', issued_at = CURRENT_TIMESTAMP, issued_by_user_id = :user_id,
                 eligibility_snapshot = :eligibility_snapshot
             WHERE id = :id AND status = 'pending_approval'"
        );
        $statement->execute([
            ':id' => $id,
            ':user_id' => $userId > 0 ? $userId : null,
            ':eligibility_snapshot' => $this->snapshot($assessment),
        ]);

        return $statement->rowCount() === 1;
    }

    public function revoke(int $id, int $userId, string $reason): bool
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A revocation reason is required.');
        }

        $statement = $this->connection->prepare(
            "UPDATE academic_certificates
             SET status = 'revoked', revoked_at = CURRENT_TIMESTAMP, revoked_by_user_id = :user_id, revocation_reason = :reason
             WHERE id = :id AND status = 'issued'"
        );
        $statement->execute([
            ':id' => $id,
            ':user_id' => $userId > 0 ? $userId : null,
            ':reason' => substr($reason, 0, 255),
        ]);

        return $statement->rowCount() === 1;
    }

    private function transition(int $id, string $from, string $sql): bool
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute([':id' => $id]);

        return $statement->rowCount() === 1;
    }

    /** @param array<string, mixed> $assessment */
    private function snapshot(array $assessment): string
    {
        $checksheet = $assessment['checksheet'];
        $snapshot = [
            'assessed_at' => date('c'),
            'eligible' => (bool) $assessment['eligible'],
            'student' => $checksheet['student'],
            'programme' => $checksheet['programme'],
            'summary' => $checksheet['summary'],
            'requirements' => $assessment['requirements'],
        ];

        return json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
