<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/SettingsRepository.php';
require_once __DIR__ . '/../app/AcademicChecksheetService.php';
require_once __DIR__ . '/../app/AcademicEligibilityService.php';
require_once __DIR__ . '/../app/AcademicCertificateRepository.php';
require_once __DIR__ . '/../app/Services/CertificateExportService.php';

use App\Auth;
use App\Services\CertificateExportService;

Auth::requireLogin();
$certificateId = (int) ($_GET['id'] ?? 0);

try {
    $connection = Database::getConnection();
    $certificateRepository = new AcademicCertificateRepository(
        $connection,
        new AcademicEligibilityService(new AcademicChecksheetService($connection))
    );
    $certificate = $certificateRepository->find($certificateId);
    if ($certificate === null) {
        http_response_code(404);
        exit('Certificate not found.');
    }
    if (($certificate['status'] ?? '') !== 'issued') {
        http_response_code(409);
        exit('Only issued certificates can be exported.');
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
    $verificationUrl = rtrim($endpoint, '?&')
        . (str_contains($endpoint, '?') ? '&' : '?')
        . 'credential=certificate&token=' . rawurlencode((string) $certificate['verification_token']);
    $path = (new CertificateExportService())->renderPdf($certificate, $settings, $verificationUrl);
    $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $certificate['certificate_number']) ?: 'certificate';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: no-store, private');
    readfile($path);
    @unlink($path);
    exit;
} catch (Throwable $exception) {
    http_response_code(500);
    error_log('Certificate export failed: ' . $exception->getMessage());
    echo 'Certificate export is temporarily unavailable.';
}
