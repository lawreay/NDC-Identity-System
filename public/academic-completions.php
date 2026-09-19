<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/AcademicCourseRepository.php';
require_once __DIR__ . '/../app/AcademicTermRepository.php';
require_once __DIR__ . '/../app/StudentCourseCompletionRepository.php';

use App\Auth;

$user = Auth::requireLogin();
$canManage = ($user['role'] ?? '') === 'Administrator';
$errors = [];
$success = '';
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$form = ['student_id' => 0, 'course_id' => 0, 'academic_term_id' => 0, 'status' => 'completed', 'completed_at' => date('Y-m-d'), 'remarks' => ''];

try {
    $connection = Database::getConnection();
    $repository = new StudentCourseCompletionRepository($connection);
    $studentRepository = new StudentRepository($connection);
    $courseRepository = new AcademicCourseRepository($connection);
    $termRepository = new AcademicTermRepository($connection);

    if ($editingId > 0) {
        $existing = $repository->find($editingId);
        if ($existing) {
            foreach ($form as $field => $_) {
                $form[$field] = $existing[$field] ?? $form[$field];
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Auth::requireCsrf();
        if (!$canManage) {
            $errors[] = 'Only administrators can manage course completions.';
        }

        $action = (string) ($_POST['action'] ?? 'save');

        if ($errors === [] && $action === 'seed_courses') {
            $created = $repository->seedStandardSemesterOneCourses();
            $success = $created . ' Semester 1 course' . ($created === 1 ? '' : 's') . ' added. Existing courses were kept.';
        } elseif ($errors === [] && $action === 'mark_semester_one') {
            $completed = $repository->seedSemesterOneCompletions((int) ($_POST['seed_term_id'] ?? 0));
            $success = $completed . ' course completion record' . ($completed === 1 ? '' : 's') . ' created. Existing records were skipped.';
        } else {
            $editingId = (int) ($_POST['id'] ?? 0);
            $form = $repository->normalize($_POST);
            $errors = array_merge($errors, $repository->validate($form));
            if ($errors === []) {
                if ($editingId > 0) {
                    $repository->update($editingId, $form);
                    $success = 'Course completion updated.';
                } else {
                    $repository->create($form);
                    $success = 'Course completion recorded.';
                    $form = ['student_id' => 0, 'course_id' => 0, 'academic_term_id' => 0, 'status' => 'completed', 'completed_at' => date('Y-m-d'), 'remarks' => ''];
                }
            }
        }
    }

    $students = $studentRepository->search('');
    $courses = $courseRepository->all();
    $terms = $termRepository->active();
    $completions = $repository->all();
} catch (Throwable $exception) {
    $students = $courses = $terms = $completions = [];
    $errors[] = 'Course completions are unavailable. Run the academic migrations including database/migrations/20260919_create_course_completions.sql. ' . $exception->getMessage();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Completions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/header.php'; ?>
<div class="container py-4">
    <div class="ndc-page-heading">
        <div>
            <div class="ndc-eyebrow">Academic</div>
            <h1 class="h3 mb-1">Course Completions</h1>
            <p class="text-muted mb-0">Record completed courses for students and bulk-mark Semester 1 as completed.</p>
        </div>
    </div>
    <?php require __DIR__ . '/partials/academic-nav.php'; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e((string) $error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Semester 1 setup</h2>
            <div class="row g-3">
                <div class="col-lg-6">
                    <form method="post" class="border rounded p-3 bg-light h-100">
                        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                        <input type="hidden" name="action" value="seed_courses">
                        <h3 class="h6">Add standard Semester 1 courses</h3>
                        <p class="text-muted small mb-3">Adds Entrepreneurship and Communication Language as shared courses, plus Technical Drawing, Technology, Numeracy, and Science to every non-Business Studies programme.</p>
                        <?php if ($canManage): ?><button class="btn btn-primary"><i class="bi bi-journal-plus me-1"></i>Add/repair Semester 1 courses</button><?php else: ?><span class="text-muted small">View only</span><?php endif; ?>
                    </form>
                </div>
                <div class="col-lg-6">
                    <form method="post" class="border rounded p-3 bg-light h-100">
                        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                        <input type="hidden" name="action" value="mark_semester_one">
                        <h3 class="h6">Mark Semester 1 completed</h3>
                        <p class="text-muted small mb-3">Creates completion records for active students using their programme and all Semester 1 courses assigned to that programme.</p>
                        <div class="mb-3">
                            <label class="form-label">Term</label>
                            <select name="seed_term_id" class="form-select" <?= !$canManage ? 'disabled' : '' ?>>
                                <option value="0">Auto-detect Semester 1 / FIRST TERM</option>
                                <?php foreach ($terms as $term): ?><option value="<?= (int) $term['id'] ?>"><?= e((string) $term['academic_year'] . ' - ' . (string) $term['name'] . ' ' . (string) ($term['semester'] ?? '')) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($canManage): ?><button class="btn btn-success"><i class="bi bi-check2-square me-1"></i>Mark Semester 1 complete</button><?php else: ?><span class="text-muted small">View only</span><?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <form method="post" class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3"><?= $editingId > 0 ? 'Edit Completion' : 'Record Completion' ?></h2>
                    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $editingId ?>">
                    <input type="hidden" name="action" value="save">
                    <div class="mb-3"><label class="form-label">Student</label><select name="student_id" class="form-select" required <?= !$canManage ? 'disabled' : '' ?>><option value="">Select student</option><?php foreach ($students as $student): ?><?php $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')); ?><option value="<?= (int) $student['id'] ?>" <?= (int) $form['student_id'] === (int) $student['id'] ? 'selected' : '' ?>><?= e((string) ($student['student_number'] ?? '') . ' - ' . $name . ' / ' . (string) ($student['program'] ?? '')) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Course</label><select name="course_id" class="form-select" required <?= !$canManage ? 'disabled' : '' ?>><option value="">Select course</option><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>" <?= (int) $form['course_id'] === (int) $course['id'] ? 'selected' : '' ?>><?= e((string) $course['code'] . ' - ' . (string) $course['name'] . ' / ' . (string) ($course['programme_codes'] ?: $course['programme_code'])) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Term</label><select name="academic_term_id" class="form-select" required <?= !$canManage ? 'disabled' : '' ?>><option value="">Select term</option><?php foreach ($terms as $term): ?><option value="<?= (int) $term['id'] ?>" <?= (int) $form['academic_term_id'] === (int) $term['id'] ? 'selected' : '' ?>><?= e((string) $term['academic_year'] . ' - ' . (string) $term['name'] . ' ' . (string) ($term['semester'] ?? '')) ?></option><?php endforeach; ?></select></div>
                    <div class="row g-2"><div class="col-6 mb-3"><label class="form-label">Status</label><select name="status" class="form-select" <?= !$canManage ? 'disabled' : '' ?>><?php foreach (['completed', 'incomplete', 'failed', 'exempted'] as $status): ?><option value="<?= e($status) ?>" <?= $form['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div><div class="col-6 mb-3"><label class="form-label">Date</label><input type="date" name="completed_at" class="form-control" value="<?= e((string) $form['completed_at']) ?>" <?= !$canManage ? 'disabled' : '' ?>></div></div>
                    <div class="mb-3"><label class="form-label">Remarks</label><input name="remarks" class="form-control" value="<?= e((string) $form['remarks']) ?>" <?= !$canManage ? 'disabled' : '' ?>></div>
                </div>
                <div class="card-footer bg-white text-end"><?php if ($canManage): ?><button class="btn btn-primary">Save completion</button><?php else: ?><span class="text-muted small">View only</span><?php endif; ?></div>
            </form>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-sm"><div class="card-body"><h2 class="h5 mb-3">Completion List</h2><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Student</th><th>Course</th><th>Term</th><th>Status</th><th>Date</th><th></th></tr></thead><tbody><?php foreach ($completions as $completion): ?><?php $name = trim((string) ($completion['first_name'] ?? '') . ' ' . (string) ($completion['last_name'] ?? '')); ?><tr><td><?= e((string) ($completion['student_number'] ?? '') . ' - ' . $name) ?></td><td><?= e((string) $completion['course_code'] . ' - ' . (string) $completion['course_name']) ?></td><td><?= e((string) $completion['academic_year'] . ' - ' . (string) $completion['term_name']) ?></td><td><span class="badge text-bg-<?= ($completion['status'] ?? '') === 'completed' ? 'success' : 'secondary' ?>"><?= e((string) $completion['status']) ?></span></td><td><?= e((string) ($completion['completed_at'] ?? '')) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="academic-completions.php?edit=<?= (int) $completion['id'] ?>">Edit</a></td></tr><?php endforeach; ?></tbody></table></div></div></div>
        </div>
    </div>
</div>
</body>
</html>
