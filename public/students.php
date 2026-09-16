<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/TemplateDesigner/TemplateDesignerService.php';
require_once __DIR__ . '/../app/Auth.php';

use App\Auth;

Auth::requireLogin();

$search = trim($_GET['search'] ?? '');
$templates = [];
$defaultTemplateId = null;

try {
    $templateService = new TemplateDesignerService();
    $templates = $templateService->listTemplates();
    $defaultTemplateId = $templateService->getDefaultTemplateId();
} catch (Throwable $exception) {
    // Keep the student directory usable if template storage is unavailable.
}

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php require_once __DIR__ . '/partials/header.php'; ?>
    <div class="container py-4">
        <div class="ndc-page-heading">
            <div>
            <div class="ndc-eyebrow"><i class="bi bi-grid-3x3-gap-fill me-1" aria-hidden="true"></i>Directory</div>
                <h1 class="h3 mb-1">Student List</h1>
                <p class="text-muted mb-0">Browse students and open their profile.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="student-form.php" class="btn btn-primary"><i class="bi bi-person-plus-fill me-1" aria-hidden="true"></i>Add Student</a>
                <a href="template-designer.php" class="btn btn-outline-primary"><i class="bi bi-palette me-1" aria-hidden="true"></i>Template Designer</a>
            </div>
        </div>

        <form method="get" action="students.php" class="row g-2 mb-4">
            <div class="col-md-8">
                <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search by name, number, program, or class">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1" aria-hidden="true"></i>Search</button>
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
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <label class="visually-hidden" for="bulkTemplate">ID card template</label>
                        <select id="bulkTemplate" name="template_id" class="form-select" style="min-width: 220px;">
                            <option value="">Default template<?= $defaultTemplateId ? '' : ' (not configured)' ?></option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?= htmlspecialchars((string) ($template['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string) ($template['name'] ?? 'Untitled template'), ENT_QUOTES, 'UTF-8') ?><?= (($template['id'] ?? '') === $defaultTemplateId) ? ' (default)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="btn-group">
                            <button type="submit" name="export_format" value="pdf" class="btn btn-primary"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>Export PDF</button>
                            <button type="submit" name="export_format" value="png_zip" class="btn btn-outline-primary"><i class="bi bi-file-earmark-zip me-1" aria-hidden="true"></i>Export PNG ZIP</button>
                        </div>
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
                            <tr class="js-student-row">
                                <td><input type="checkbox" class="form-check-input js-student-select" name="student_ids[]" value="<?= (int) ($student['id'] ?? 0) ?>" aria-label="Select <?= htmlspecialchars(trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"></td>
                                <td><?= htmlspecialchars((string) ($student['student_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($student['program'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($student['class_level'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($student['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <a href="student-profile.php?id=<?= (int) ($student['id'] ?? 0) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i>Profile</a>
                                    <a href="student-id-card.php?id=<?= (int) ($student['id'] ?? 0) ?>" class="btn btn-outline-secondary btn-sm ms-1"><i class="bi bi-credit-card-2-front me-1" aria-hidden="true"></i>Preview</a>
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
            checkbox.closest('.js-student-row')?.classList.toggle('ndc-selected-row', checkbox.checked);
            checkbox.addEventListener('change', () => {
                checkbox.closest('.js-student-row')?.classList.toggle('ndc-selected-row', checkbox.checked);
                selectAllStudents.checked = studentSelections.length > 0 && [...studentSelections].every((item) => item.checked);
                selectAllStudents.indeterminate = [...studentSelections].some((item) => item.checked) && !selectAllStudents.checked;
            });
        });
    }
</script>
</body>
</html>
