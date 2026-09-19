<?php

final class AcademicTranscriptRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->connection->query(
            'SELECT t.id, t.student_id, t.programme_id, t.transcript_number, t.verification_token, t.status,
                    t.requested_at, t.issued_at, t.revoked_at, t.revocation_reason,
                    s.student_number, s.first_name, s.last_name, p.code AS programme_code, p.name AS programme_name
             FROM academic_transcripts t
             INNER JOIN students s ON s.id = t.student_id
             INNER JOIN academic_programmes p ON p.id = t.programme_id
             ORDER BY t.created_at DESC, t.id DESC'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT t.*, s.student_number, s.first_name, s.last_name,
                    p.code AS programme_code, p.name AS programme_name, p.qualification
             FROM academic_transcripts t
             INNER JOIN students s ON s.id = t.student_id
             INNER JOIN academic_programmes p ON p.id = t.programme_id
             WHERE t.id = :id
             LIMIT 1'
        );
        $statement->execute([':id' => $id]);
        $transcript = $statement->fetch();

        return is_array($transcript) ? $transcript : null;
    }

    public function createDraft(int $studentId, int $programmeId, int $userId): int
    {
        $snapshot = $this->buildSnapshot($studentId, $programmeId);
        if ($snapshot === null) {
            throw new RuntimeException('The student does not have an enrolment for the selected programme.');
        }
        if ($snapshot['results'] === []) {
            throw new RuntimeException('An official transcript requires at least one approved result.');
        }

        $this->connection->beginTransaction();
        try {
            $statement = $this->connection->prepare(
                "INSERT INTO academic_transcripts
                    (student_id, programme_id, transcript_number, verification_token, status, transcript_snapshot, requested_by_user_id)
                 VALUES (:student_id, :programme_id, '', :verification_token, 'draft', :transcript_snapshot, :requested_by_user_id)"
            );
            $statement->execute([
                ':student_id' => $studentId,
                ':programme_id' => $programmeId,
                ':verification_token' => bin2hex(random_bytes(32)),
                ':transcript_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                ':requested_by_user_id' => $userId > 0 ? $userId : null,
            ]);
            $id = (int) $this->connection->lastInsertId();
            $number = sprintf('NDC-TRN-%s-%06d', date('Y'), $id);
            $this->connection->prepare('UPDATE academic_transcripts SET transcript_number = :number WHERE id = :id')
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

    public function issue(int $id, int $userId): bool
    {
        $transcript = $this->find($id);
        if ($transcript === null || ($transcript['status'] ?? '') !== 'draft') {
            return false;
        }

        $snapshot = $this->buildSnapshot((int) $transcript['student_id'], (int) $transcript['programme_id']);
        if ($snapshot === null || $snapshot['results'] === []) {
            throw new RuntimeException('The transcript cannot be issued without a current programme enrolment and approved results.');
        }

        $statement = $this->connection->prepare(
            "UPDATE academic_transcripts
             SET status = 'issued', issued_at = CURRENT_TIMESTAMP, issued_by_user_id = :user_id, transcript_snapshot = :snapshot
             WHERE id = :id AND status = 'draft'"
        );
        $statement->execute([
            ':id' => $id,
            ':user_id' => $userId > 0 ? $userId : null,
            ':snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
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
            "UPDATE academic_transcripts
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

    /** @return array<string, mixed>|null */
    private function buildSnapshot(int $studentId, int $programmeId): ?array
    {
        $studentStatement = $this->connection->prepare(
            'SELECT s.id, s.student_number, s.first_name, s.last_name, p.id AS programme_id, p.code AS programme_code,
                    p.name AS programme_name, p.qualification
             FROM students s
             INNER JOIN student_enrolments e ON e.student_id = s.id AND e.programme_id = :programme_id
             INNER JOIN academic_programmes p ON p.id = e.programme_id
             WHERE s.id = :student_id
             ORDER BY e.enrolled_at DESC, e.id DESC
             LIMIT 1'
        );
        $studentStatement->execute([':student_id' => $studentId, ':programme_id' => $programmeId]);
        $identity = $studentStatement->fetch();
        if (!is_array($identity)) {
            return null;
        }

        $resultStatement = $this->connection->prepare(
            'SELECT r.mark, r.grade, r.grade_points, r.assessed_at,
                    c.code AS course_code, c.name AS course_name, c.credits, c.semester,
                    term.name AS term_name, term.academic_year, term.semester AS term_semester
             FROM student_results r
             INNER JOIN academic_course_programmes cp ON cp.course_id = r.course_id AND cp.programme_id = :programme_id
             INNER JOIN academic_courses c ON c.id = r.course_id
             INNER JOIN academic_terms term ON term.id = r.academic_term_id
             WHERE r.student_id = :student_id AND r.status = "approved"
               AND EXISTS (
                    SELECT 1 FROM student_enrolments e
                    WHERE e.student_id = r.student_id AND e.programme_id = :enrolment_programme_id
                      AND e.academic_term_id = r.academic_term_id
               )
             ORDER BY term.academic_year, term.start_date, c.code'
        );
        $resultStatement->execute([
            ':student_id' => $studentId,
            ':programme_id' => $programmeId,
            ':enrolment_programme_id' => $programmeId,
        ]);
        $results = $resultStatement->fetchAll();

        return [
            'generated_at' => date('c'),
            'student' => [
                'student_number' => $identity['student_number'],
                'first_name' => $identity['first_name'],
                'last_name' => $identity['last_name'],
            ],
            'programme' => [
                'code' => $identity['programme_code'],
                'name' => $identity['programme_name'],
                'qualification' => $identity['qualification'],
            ],
            'results' => $results,
        ];
    }
}
