<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/CardRepository.php';
require_once __DIR__ . '/../app/SettingsRepository.php';
require_once __DIR__ . '/../app/AcademicChecksheetService.php';
require_once __DIR__ . '/../app/Services/CardVerificationService.php';
require_once __DIR__ . '/../app/Services/ChecksheetExportService.php';

use App\Services\CardVerificationService;
use App\Services\ChecksheetExportService;

$guid = strtolower(trim((string) ($_GET['guid'] ?? '')));
$programmeId = (int) ($_GET['programme_id'] ?? 0);
$download = (string) ($_GET['download'] ?? '') === '1';

try {
    $connection = Database::getConnection();
    $verification = (new CardVerificationService(new CardRepository($connection)))->verify($guid);
    $card = $verification['card'] ?? null;
    if (($verification['state'] ?? '') !== 'VALID' || !is_array($card)) {
        http_response_code(403);
        exit('A valid active student ID is required to view this checksheet.');
    }

    $checksheet = (new AcademicChecksheetService($connection))->build((int) $card['student_id'], $programmeId);
    if ($checksheet === null) {
        http_response_code(404);
        exit('Academic checksheet not found for this student ID.');
    }

    $settings = (new SettingsRepository($connection))->getAll();
    $path = (new ChecksheetExportService())->renderPdf($checksheet, $settings);
    $studentNumber = (string) ($checksheet['student']['student_number'] ?? 'student');
    $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $studentNumber) ?: 'student';

    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="checksheet_' . $filename . '.pdf"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    @unlink($path);
    exit;
} catch (Throwable $exception) {
    http_response_code(503);
    error_log('Public checksheet export failed: ' . $exception->getMessage());
    echo 'Academic checksheet is temporarily unavailable.';
}
