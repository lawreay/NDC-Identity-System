<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/SettingsRepository.php';
require_once __DIR__ . '/../app/AcademicTranscriptRepository.php';
require_once __DIR__ . '/../app/Services/TranscriptExportService.php';

use App\Auth;
use App\Services\TranscriptExportService;

Auth::requireLogin();
$transcriptId = (int) ($_GET['id'] ?? 0);

try {
    $connection = Database::getConnection();
    $transcript = (new AcademicTranscriptRepository($connection))->find($transcriptId);
    if ($transcript === null) {
        http_response_code(404);
        exit('Transcript not found.');
    }
    if (($transcript['status'] ?? '') !== 'issued') {
        http_response_code(409);
        exit('Only issued transcripts can be exported.');
    }

    $settings = (new SettingsRepository($connection))->getAll();
    $endpoint = trim((string) ($settings['verification_endpoint'] ?? ''));
    if (!preg_match('~^https?://~i', $endpoint)) {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if (preg_match('/^[a-z0-9.-]+(?::\d+)?$/i', $host) === 1) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $directory = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
            $endpoint = $scheme . '://' . $host . $directory . '/verify.php';
        } else {
            $endpoint = 'https://ndc.edu/verify.php';
        }
    }
    $verificationUrl = rtrim($endpoint, '?&') . (str_contains($endpoint, '?') ? '&' : '?')
        . 'credential=transcript&token=' . rawurlencode((string) $transcript['verification_token']);
    $path = (new TranscriptExportService())->renderPdf($transcript, $settings, $verificationUrl);
    $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $transcript['transcript_number']) ?: 'transcript';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: no-store, private');
    readfile($path);
    @unlink($path);
    exit;
} catch (Throwable $exception) {
    http_response_code(500);
    error_log('Transcript export failed: ' . $exception->getMessage());
    echo 'Transcript export is temporarily unavailable.';
}
