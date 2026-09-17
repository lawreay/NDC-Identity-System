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

$studentsMissingSurname = array_values(array_filter(
    $students,
    static fn (array $student): bool => trim((string) ($student['last_name'] ?? '')) === ''
));

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

        <form method="get" action="students.php" class="row g-2 mb-3">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search by name, number, program, or class">
            </div>
            <div class="col-md-2">
                <label class="visually-hidden" for="statusFilter">Filter by status</label>
                <select id="statusFilter" class="form-select" aria-label="Filter students by status">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1" aria-hidden="true"></i>Search</button>
            </div>
            <div class="col-md-2">
                <a href="students.php" class="btn btn-outline-secondary w-100">Clear</a>
            </div>
        </form>

        <div class="ndc-directory-summary mb-4">
            <span><strong><?= number_format(count($students)) ?></strong> records</span>
            <span><i class="bi bi-check-circle-fill text-success me-1" aria-hidden="true"></i><strong><?= number_format(count(array_filter($students, static fn (array $student): bool => strtolower((string) ($student['status'] ?? '')) === 'active'))) ?></strong> active</span>
            <span class="<?= $studentsMissingSurname !== [] ? 'text-warning-emphasis' : '' ?>"><i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i><strong><?= number_format(count($studentsMissingSurname)) ?></strong> missing surname</span>
            <span class="ms-auto" id="selectedStudentSummary">0 selected</span>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($students === []): ?>
            <div class="alert alert-info">No students found.</div>
        <?php else: ?>
            <?php if ($studentsMissingSurname !== []): ?>
                <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                    <span><?= count($studentsMissingSurname) ?> student<?= count($studentsMissingSurname) === 1 ? '' : 's' ?> <?= count($studentsMissingSurname) === 1 ? 'is' : 'are' ?> missing a surname. Marked rows should be completed before issuing or recalculating IDs.</span>
                </div>
            <?php endif; ?>
            <form method="post" action="export-cards-bulk.php" id="bulkExportForm">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="ndc-bulk-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
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
                            <button type="submit" name="export_format" value="pdf" class="btn btn-primary js-bulk-export" disabled><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>Export PDF</button>
                            <button type="submit" name="export_format" value="png_zip" class="btn btn-outline-primary js-bulk-export" disabled><i class="bi bi-file-earmark-zip me-1" aria-hidden="true"></i>Export PNG ZIP</button>
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
                            <?php
                            $studentName = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
                            $missingSurname = trim((string) ($student['last_name'] ?? '')) === '';
                            ?>
                            <tr class="js-student-row<?= $missingSurname ? ' table-warning' : '' ?>" data-student-status="<?= htmlspecialchars(strtolower((string) ($student['status'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                                <td><input type="checkbox" class="form-check-input js-student-select" name="student_ids[]" value="<?= (int) ($student['id'] ?? 0) ?>" aria-label="Select <?= htmlspecialchars(trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"></td>
                                <td><?= htmlspecialchars((string) ($student['student_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($missingSurname): ?>
                                        <span class="badge text-bg-warning ms-1" title="Surname missing"><i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>Surname missing</span>
                                    <?php endif; ?>
                                </td>
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
    const selectedStudentSummary = document.getElementById('selectedStudentSummary');
    const statusFilter = document.getElementById('statusFilter');
    const studentRows = document.querySelectorAll('.js-student-row');
    const bulkExportForm = document.getElementById('bulkExportForm');
    const bulkExportButtons = document.querySelectorAll('.js-bulk-export');
    const updateSelectionSummary = () => {
        const selected = [...studentSelections].filter((item) => item.checked).length;
        if (selectedStudentSummary) selectedStudentSummary.textContent = `${selected} selected`;
        bulkExportButtons.forEach((button) => { button.disabled = selected === 0; });
    };
    if (selectAllStudents) {
        selectAllStudents.addEventListener('change', () => {
            studentSelections.forEach((checkbox) => {
                if (checkbox.closest('.js-student-row')?.hidden !== true) checkbox.checked = selectAllStudents.checked;
            });
            updateSelectionSummary();
        });
        studentSelections.forEach((checkbox) => {
            checkbox.closest('.js-student-row')?.classList.toggle('ndc-selected-row', checkbox.checked);
            checkbox.addEventListener('change', () => {
                checkbox.closest('.js-student-row')?.classList.toggle('ndc-selected-row', checkbox.checked);
                selectAllStudents.checked = studentSelections.length > 0 && [...studentSelections].every((item) => item.checked);
                selectAllStudents.indeterminate = [...studentSelections].some((item) => item.checked) && !selectAllStudents.checked;
                updateSelectionSummary();
            });
        });
    }
    if (bulkExportForm) {
        bulkExportForm.addEventListener('submit', (event) => {
            if ([...studentSelections].every((item) => !item.checked)) {
                event.preventDefault();
                window.alert('Select at least one student to export.');
            }
        });
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', () => {
            const value = statusFilter.value;
            studentRows.forEach((row) => {
                row.hidden = value !== '' && row.dataset.studentStatus !== value;
                if (row.hidden) {
                    const checkbox = row.querySelector('.js-student-select');
                    if (checkbox) checkbox.checked = false;
                }
            });
            if (selectAllStudents) {
                selectAllStudents.checked = false;
                selectAllStudents.indeterminate = false;
            }
            updateSelectionSummary();
        });
    }
    updateSelectionSummary();
</script>
</body>
</html>
