<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/CardRepository.php';
require_once __DIR__ . '/../app/SettingsRepository.php';
require_once __DIR__ . '/../app/Services/CardVerificationService.php';
require_once __DIR__ . '/../app/Services/CertificateExportService.php';
require_once __DIR__ . '/../app/Services/TranscriptExportService.php';

use App\Services\CardVerificationService;
use App\Services\CertificateExportService;
use App\Services\TranscriptExportService;

$guid = strtolower(trim((string) ($_GET['guid'] ?? '')));
$type = strtolower(trim((string) ($_GET['type'] ?? '')));
$documentId = (int) ($_GET['id'] ?? 0);
$download = (string) ($_GET['download'] ?? '') === '1';

if (!in_array($type, ['certificate', 'transcript'], true) || $documentId <= 0) {
    http_response_code(400);
    exit('Invalid academic document request.');
}

try {
    $connection = Database::getConnection();
    $verification = (new CardVerificationService(new CardRepository($connection)))->verify($guid);
    $card = $verification['card'] ?? null;
    if (($verification['state'] ?? '') !== 'VALID' || !is_array($card)) {
        http_response_code(403);
        exit('A valid active student ID is required to view this document.');
    }

    $table = $type === 'certificate' ? 'academic_certificates' : 'academic_transcripts';
    $numberColumn = $type === 'certificate' ? 'certificate_number' : 'transcript_number';
    $statement = $connection->prepare(
        'SELECT d.*, s.student_number, s.first_name, s.last_name,
                p.code AS programme_code, p.name AS programme_name, p.qualification
         FROM ' . $table . ' d
         INNER JOIN students s ON s.id = d.student_id
         INNER JOIN academic_programmes p ON p.id = d.programme_id
         WHERE d.id = :id AND d.student_id = :student_id AND d.status = "issued"
         LIMIT 1'
    );
    $statement->execute([':id' => $documentId, ':student_id' => (int) $card['student_id']]);
    $document = $statement->fetch();
    if (!is_array($document)) {
        http_response_code(404);
        exit('Issued document not found for this student ID.');
    }

    $settings = (new SettingsRepository($connection))->getAll();
    $endpoint = publicVerificationEndpoint((string) ($settings['verification_endpoint'] ?? ''));
    $verificationUrl = rtrim($endpoint, '?&') . (str_contains($endpoint, '?') ? '&' : '?')
        . 'credential=' . $type . '&token=' . rawurlencode((string) $document['verification_token']);
    $path = $type === 'certificate'
        ? (new CertificateExportService())->renderPdf($document, $settings, $verificationUrl)
        : (new TranscriptExportService())->renderPdf($document, $settings, $verificationUrl);
    $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $document[$numberColumn]) ?: $type;

    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '.pdf"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    @unlink($path);
    exit;
} catch (Throwable $exception) {
    http_response_code(503);
    error_log('Public academic document export failed: ' . $exception->getMessage());
    echo 'Academic document is temporarily unavailable.';
}

function publicVerificationEndpoint(string $configuredEndpoint): string
{
    $endpoint = trim($configuredEndpoint);
    if (preg_match('~^https?://~i', $endpoint)) {
        return $endpoint;
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if (preg_match('/^[a-z0-9.-]+(?::\d+)?$/i', $host) === 1) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $directory = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');

        return $scheme . '://' . $host . $directory . '/verify.php';
    }

    return 'https://ndc.edu/verify.php';
}
