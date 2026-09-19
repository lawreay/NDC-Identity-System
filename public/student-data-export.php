<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/Services/StudentDataExportService.php';

use App\Auth;
use App\Services\StudentDataExportService;

Auth::requireLogin();

$search = trim((string) ($_GET['search'] ?? ''));
$preselectedStudentId = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;
$exportFields = StudentDataExportService::availableFields();
$defaultExportFields = StudentDataExportService::defaultFields();

try {
    $repository = new StudentRepository(Database::getConnection());
    $students = $repository->search($search);
    if ($preselectedStudentId > 0 && !array_filter($students, static fn (array $student): bool => (int) ($student['id'] ?? 0) === $preselectedStudentId)) {
        $student = $repository->findById($preselectedStudentId);
        if ($student !== null) {
            array_unshift($students, $student);
        }
    }
    $errorMessage = null;
} catch (Throwable $exception) {
    $students = [];
    $errorMessage = $exception->getMessage();
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Data Export</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/header.php'; ?>
<div class="container py-4">
    <div class="ndc-page-heading">
        <div>
            <div class="ndc-eyebrow"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i>Reports</div>
            <h1 class="h3 mb-1">Student Data Export</h1>
            <p class="text-muted mb-0">Select students and choose exactly which profile fields to export.</p>
        </div>
        <a href="students.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to students</a>
    </div>

    <form method="get" action="student-data-export.php" class="row g-2 mb-3">
        <div class="col-md-8">
            <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search by name, number, program, or class">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1" aria-hidden="true"></i>Search</button>
        </div>
        <div class="col-md-2">
            <a href="student-data-export.php" class="btn btn-outline-secondary w-100">Clear</a>
        </div>
    </form>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($students === []): ?>
        <div class="alert alert-info">No students found.</div>
    <?php else: ?>
        <form method="post" action="export-student-data.php" id="studentDataExportForm">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

            <div class="ndc-bulk-toolbar mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <div class="fw-semibold">Export options</div>
                        <small class="text-muted"><span id="selectedStudentSummary">0 selected</span>. CSV opens in Excel; PDF is best for printable reports.</small>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="export_format" value="csv" class="btn btn-success js-data-export" disabled><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i>Export Excel CSV</button>
                        <button type="submit" name="export_format" value="pdf" class="btn btn-outline-danger js-data-export" disabled><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>Export PDF</button>
                    </div>
                </div>
                <div class="ndc-export-fields mt-3">
                    <?php foreach ($exportFields as $field => $label): ?>
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="export_fields[]" value="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($field, $defaultExportFields, true) ? 'checked' : '' ?>>
                            <span class="form-check-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle bg-white shadow-sm rounded">
                    <thead class="table-dark">
                    <tr>
                        <th scope="col" style="width: 44px;"><input type="checkbox" class="form-check-input" id="selectAllStudents" aria-label="Select all students"></th>
                        <th>Student #</th>
                        <th>Name</th>
                        <th>Program</th>
                        <th>Class</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $student): ?>
                        <?php
                        $studentId = (int) ($student['id'] ?? 0);
                        $studentName = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
                        ?>
                        <tr class="js-student-row">
                            <td><input type="checkbox" class="form-check-input js-student-select" name="student_ids[]" value="<?= $studentId ?>" <?= $studentId === $preselectedStudentId ? 'checked' : '' ?> aria-label="Select <?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?>"></td>
                            <td><?= htmlspecialchars((string) ($student['student_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($student['program'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($student['class_level'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($student['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    <?php endif; ?>
</div>
<script>
    const selectAllStudents = document.getElementById('selectAllStudents');
    const studentSelections = document.querySelectorAll('.js-student-select');
    const selectedStudentSummary = document.getElementById('selectedStudentSummary');
    const exportButtons = document.querySelectorAll('.js-data-export');
    const exportForm = document.getElementById('studentDataExportForm');

    function updateSelectionSummary() {
        const selected = [...studentSelections].filter((item) => item.checked).length;
        if (selectedStudentSummary) selectedStudentSummary.textContent = `${selected} selected`;
        exportButtons.forEach((button) => { button.disabled = selected === 0; });
        if (selectAllStudents) {
            selectAllStudents.checked = studentSelections.length > 0 && [...studentSelections].every((item) => item.checked);
            selectAllStudents.indeterminate = [...studentSelections].some((item) => item.checked) && !selectAllStudents.checked;
        }
    }

    if (selectAllStudents) {
        selectAllStudents.addEventListener('change', () => {
            studentSelections.forEach((checkbox) => { checkbox.checked = selectAllStudents.checked; });
            updateSelectionSummary();
        });
    }

    studentSelections.forEach((checkbox) => {
        checkbox.closest('.js-student-row')?.classList.toggle('ndc-selected-row', checkbox.checked);
        checkbox.addEventListener('change', () => {
            checkbox.closest('.js-student-row')?.classList.toggle('ndc-selected-row', checkbox.checked);
            updateSelectionSummary();
        });
    });

    if (exportForm) {
        exportForm.addEventListener('submit', (event) => {
            if ([...studentSelections].every((item) => !item.checked)) {
                event.preventDefault();
                window.alert('Select at least one student to export.');
            }
        });
    }

    updateSelectionSummary();
</script>
</body>
</html>
