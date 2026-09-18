<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/Services/StudentDataExportService.php';

use App\Auth;
use App\Services\StudentDataExportService;

Auth::requireLogin();

try {
    Auth::requireCsrf();

    $studentIds = array_map('intval', (array) ($_POST['student_ids'] ?? []));
    $studentIds = array_values(array_unique(array_filter($studentIds, static fn (int $id): bool => $id > 0)));
    if ($studentIds === [] || count($studentIds) > 500) {
        throw new RuntimeException($studentIds === [] ? 'Select at least one student to export.' : 'You can export up to 500 students at a time.');
    }

    $fields = StudentDataExportService::normalizeFields((array) ($_POST['export_fields'] ?? []));
    $format = strtolower(trim((string) ($_POST['export_format'] ?? 'csv')));
    if (!in_array($format, ['csv', 'pdf'], true)) {
        throw new RuntimeException('Choose a valid export format.');
    }

    $students = (new StudentRepository(Database::getConnection()))->findByIds($studentIds);
    if ($students === []) {
        throw new RuntimeException('No selected students were found.');
    }

    $exporter = new StudentDataExportService();
    $stamp = date('Y-m-d_H-i-s');

    if ($format === 'pdf') {
        $pdf = $exporter->pdf($students, $fields);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="student_data_' . $stamp . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    $csv = $exporter->csv($students, $fields);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="student_data_' . $stamp . '.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
} catch (Throwable $exception) {
    http_response_code(400);
    echo htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
}
