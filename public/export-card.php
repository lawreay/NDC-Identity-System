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

// Require user to be authenticated
Auth::requireLogin();

// Verify CSRF token
try {
    Auth::requireCsrf();
} catch (Throwable $exception) {
    http_response_code(403);
    exit('Security token invalid. Please try again.');
}

// Get and validate student ID
$studentId = (int) ($_POST['student_id'] ?? 0);
if ($studentId <= 0) {
    http_response_code(400);
    exit('Invalid student ID');
}

try {
    // Load student data
    $repository = new StudentRepository(Database::getConnection());
    $student = $repository->findById($studentId);

    if (!$student) {
        http_response_code(404);
        exit('Student not found');
    }

    // Get template service
    $service = new TemplateDesignerService();
    $settingsRepository = new SettingsRepository(Database::getConnection());

    // Export the template currently shown in the preview when provided.
    $templateId = trim((string) ($_POST['template_id'] ?? ''));
    $template = $templateId !== '' ? $service->getTemplate($templateId) : $service->getDefaultTemplate();
    if (!$template) {
        http_response_code(500);
        exit($templateId !== '' ? 'The selected template could not be loaded.' : 'No default template configured. Please set a default template in the Template Designer.');
    }

    // Prepare student data
    if (empty(trim((string) ($student['student_number'] ?? '')))) {
        $student['student_number'] = $repository->generateStudentNumber(
            $studentId,
            (string) ($student['first_name'] ?? ''),
            (string) ($student['last_name'] ?? '')
        );
    }

    $student['full_name'] = trim(
        (string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')
    );

    // Load organization settings
    $appSettings = $settingsRepository->getAll();
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

    // Theme colors
    $theme = [
        'primary_color' => '#0b5ed7',
        'secondary_color' => '#0a7e8c',
        'accent_color' => '#f4b400',
    ];

    // Render front and back HTML
    $frontHtml = $service->renderTemplate($template, $student, $organization, $theme, 'front');
    $backHtml = $service->renderTemplate($template, $student, $organization, $theme, 'back');

    // Export to PDF
    $exportService = new CardExportService();
    $pdfPath = $exportService->exportCardPdf(
        $frontHtml,
        $backHtml,
        $student['student_number']
    );

    // Send file headers
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="card_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $student['student_number']) . '.pdf"');
    header('Content-Length: ' . filesize($pdfPath));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output file
    if (readfile($pdfPath) === false) {
        throw new RuntimeException('Failed to read PDF file');
    }

    // Clean up temporary file
    CardExportService::cleanupFile($pdfPath);
    exit;

} catch (RuntimeException $exception) {
    // Handle expected runtime exceptions
    http_response_code(500);
    echo 'Export failed: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
} catch (Throwable $exception) {
    // Handle unexpected exceptions
    http_response_code(500);
    echo 'An unexpected error occurred. Please try again.';
    
    // Log error for debugging
    error_log('Card export error: ' . $exception->getMessage());
    exit;
}
