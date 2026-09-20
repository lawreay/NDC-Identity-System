<?php

final class PublicAcademicRecordRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function issuedDocumentsForStudent(int $studentId): array
    {
        $statement = $this->connection->prepare(
            "SELECT id, certificate_number AS document_number, issued_at, 'certificate' AS document_type
             FROM academic_certificates
             WHERE student_id = :student_id AND status = 'issued'
             UNION ALL
             SELECT id, transcript_number AS document_number, issued_at, 'transcript' AS document_type
             FROM academic_transcripts
             WHERE student_id = :student_id AND status = 'issued'
             ORDER BY issued_at DESC, document_type, id DESC"
        );
        $statement->execute([':student_id' => $studentId]);

        return $statement->fetchAll();
    }
}
