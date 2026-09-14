<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/SettingsRepository.php';
require_once __DIR__ . '/../app/TemplateDesigner/TemplateDesignerService.php';
require_once __DIR__ . '/../app/Services/CardExportService.php';

use App\Auth;
use App\Services\CardExportService;

Auth::requireLogin();

try {
    Auth::requireCsrf();
} catch (Throwable $exception) {
    http_response_code(403);
    exit('Security token invalid. Please try again.');
}

$format = (string) ($_POST['export_format'] ?? '');
$submittedIds = $_POST['student_ids'] ?? [];
if (!in_array($format, ['pdf', 'png_zip'], true) || !is_array($submittedIds)) {
    http_response_code(400);
    exit('Invalid bulk export request.');
}

$studentIds = array_values(array_unique(array_filter(
    array_map('intval', $submittedIds),
    static fn (int $id): bool => $id > 0
)));
// Keep exports responsive and avoid generating an unbounded download in one request.
if ($studentIds === [] || count($studentIds) > 250) {
    http_response_code(400);
    exit($studentIds === [] ? 'Select at least one student to export.' : 'You can export up to 250 students at a time.');
}

try {
    $connection = Database::getConnection();
    $repository = new StudentRepository($connection);
    $students = $repository->findByIds($studentIds);
    if ($students === []) {
        http_response_code(404);
        exit('No selected students were found.');
    }

    foreach ($students as &$student) {
        if (trim((string) ($student['student_number'] ?? '')) === '') {
            $student['student_number'] = $repository->generateStudentNumber(
                (int) $student['id'],
                (string) ($student['first_name'] ?? ''),
                (string) ($student['last_name'] ?? '')
            );
        }
        $student['full_name'] = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
    }
    unset($student);

    $template = (new TemplateDesignerService())->getDefaultTemplate();
    if (!$template) {
        throw new RuntimeException('No default template configured. Please set a default template in the Template Designer.');
    }

    $appSettings = (new SettingsRepository($connection))->getAll();
    $organization = [
        'name' => $appSettings['organization_name'] ?? 'NDC',
        'school_name' => $appSettings['school_name'] ?? $appSettings['organization_name'] ?? 'NDC',
        'campus_name' => $appSettings['campus_name'] ?? '',
        'academic_programs' => $appSettings['academic_programs'] ?? '',
        'address' => $appSettings['organization_address'] ?? 'Ntcheu',
        'phone' => $appSettings['organization_phone'] ?? '+265 999 000 000',
        'email' => $appSettings['organization_email'] ?? 'info@ndc.edu',
        'website' => $appSettings['organization_website'] ?? 'https://ndc.edu',
        'logo_path' => $appSettings['organization_logo_path'] ?? '',
        'authorized_name' => $appSettings['principal_signature_name'] ?? $appSettings['authorized_name'] ?? 'Authorized Officer',
        'authorized_signature_path' => $appSettings['principal_signature_path'] ?? $appSettings['authorized_signature_path'] ?? '',
    ];
    $theme = SettingsRepository::themeFromSettings($appSettings);

    $exportService = new CardExportService();
    $path = $format === 'pdf'
        ? $exportService->exportCardsPdf($template, $students, $organization, $theme)
        : $exportService->exportCardsPngZip($template, $students, $organization, $theme);
    $extension = $format === 'pdf' ? 'pdf' : 'zip';

    header('Content-Type: ' . ($format === 'pdf' ? 'application/pdf' : 'application/zip'));
    header('Content-Disposition: attachment; filename="student_ids_' . date('Ymd_His') . '.' . $extension . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    if (readfile($path) === false) {
        throw new RuntimeException('Failed to send the export file.');
    }
    CardExportService::cleanupFile($path);
    exit;
} catch (RuntimeException $exception) {
    http_response_code(500);
    echo 'Bulk export failed: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
} catch (Throwable $exception) {
    error_log('Bulk card export error: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine() . PHP_EOL . $exception->getTraceAsString());
    http_response_code(500);
    echo 'An unexpected error occurred. Please try again.';
    exit;
}
