<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/Auth.php';

use App\Auth;

Auth::requireLogin();

$search = trim($_GET['search'] ?? '');

try {
    $repository = new StudentRepository(Database::getConnection());
    $students = $repository->search($search);
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
    <title>Students</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php require_once __DIR__ . '/partials/header.php'; ?>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">Student List</h1>
                <p class="text-muted mb-0">Browse students and open their profile.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="student-form.php" class="btn btn-primary">Add Student</a>
                <a href="template-designer.php" class="btn btn-outline-primary">Template Designer</a>
            </div>
        </div>

        <form method="get" action="students.php" class="row g-2 mb-4">
            <div class="col-md-8">
                <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search by name, number, program, or class">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Search</button>
            </div>
            <div class="col-md-2">
                <a href="students.php" class="btn btn-outline-secondary w-100">Clear</a>
            </div>
        </form>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($students === []): ?>
            <div class="alert alert-info">No students found.</div>
        <?php else: ?>
            <form method="post" action="export-cards-bulk.php" id="bulkExportForm">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <small class="text-muted">Select students, then download both sides of each ID card. Maximum 250 students per export.</small>
                    <div class="btn-group">
                        <button type="submit" name="export_format" value="pdf" class="btn btn-primary">Export selected PDF</button>
                        <button type="submit" name="export_format" value="png_zip" class="btn btn-outline-primary">Export selected PNG ZIP</button>
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
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><input type="checkbox" class="form-check-input js-student-select" name="student_ids[]" value="<?= (int) ($student['id'] ?? 0) ?>" aria-label="Select <?= htmlspecialchars(trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"></td>
                                <td><?= htmlspecialchars((string) ($student['student_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($student['program'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($student['class_level'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($student['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <a href="student-profile.php?id=<?= (int) ($student['id'] ?? 0) ?>" class="btn btn-outline-primary btn-sm">Open profile</a>
                                    <a href="student-id-card.php?id=<?= (int) ($student['id'] ?? 0) ?>" class="btn btn-outline-secondary btn-sm ms-1">Preview ID</a>
                                </td>
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
    if (selectAllStudents) {
        selectAllStudents.addEventListener('change', () => {
            studentSelections.forEach((checkbox) => { checkbox.checked = selectAllStudents.checked; });
        });
        studentSelections.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                selectAllStudents.checked = studentSelections.length > 0 && [...studentSelections].every((item) => item.checked);
                selectAllStudents.indeterminate = [...studentSelections].some((item) => item.checked) && !selectAllStudents.checked;
            });
        });
    }
</script>
</body>
</html>
