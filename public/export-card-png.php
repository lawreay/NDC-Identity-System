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

$studentId = (int) ($_POST['student_id'] ?? 0);
$templateId = trim((string) ($_POST['template_id'] ?? ''));
$side = trim((string) ($_POST['side'] ?? ''));

if ($studentId <= 0 || !in_array($side, ['front', 'back'], true)) {
    http_response_code(400);
    exit('Invalid PNG export request.');
}

try {
    $repository = new StudentRepository(Database::getConnection());
    $student = $repository->findById($studentId);
    if (!$student) {
        http_response_code(404);
        exit('Student not found.');
    }

    $service = new TemplateDesignerService();
    $template = $templateId !== '' ? $service->getTemplate($templateId) : $service->getDefaultTemplate();
    if (!$template) {
        http_response_code(404);
        exit('The selected template could not be loaded.');
    }

    if (empty(trim((string) ($student['student_number'] ?? '')))) {
        $student['student_number'] = $repository->generateStudentNumber(
            $studentId,
            (string) ($student['first_name'] ?? ''),
            (string) ($student['last_name'] ?? '')
        );
    }
    $student['full_name'] = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));

    $appSettings = (new SettingsRepository(Database::getConnection()))->getAll();
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
    $theme = [
        'primary_color' => '#0b5ed7',
        'secondary_color' => '#0a7e8c',
        'accent_color' => '#f4b400',
    ];

    $cardHtml = $service->renderTemplate($template, $student, $organization, $theme, $side);
    $pngPath = (new CardExportService())->exportCardPng(
        $cardHtml,
        (string) $student['student_number'],
        $side
    );

    $safeNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $student['student_number']) ?: 'export';
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="card_' . $safeNumber . '_' . $side . '.png"');
    header('Content-Length: ' . filesize($pngPath));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($pngPath);
    CardExportService::cleanupFile($pngPath);
    exit;
} catch (RuntimeException $exception) {
    http_response_code(500);
    echo 'PNG export failed: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
} catch (Throwable $exception) {
    error_log('Card PNG export error: ' . $exception->getMessage());
    http_response_code(500);
    echo 'An unexpected error occurred while exporting the PNG.';
    exit;
}
