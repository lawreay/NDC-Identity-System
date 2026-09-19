<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/AcademicProgrammeRepository.php';
require_once __DIR__ . '/../app/AcademicTermRepository.php';
require_once __DIR__ . '/../app/StudentEnrolmentRepository.php';

use App\Auth;

$user = Auth::requireLogin();
$canManage = ($user['role'] ?? '') === 'Administrator';
$errors = [];
$success = '';
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$form = ['student_id' => 0, 'programme_id' => 0, 'academic_term_id' => 0, 'status' => 'active', 'enrolled_at' => date('Y-m-d')];

try {
    $connection = Database::getConnection();
    $repository = new StudentEnrolmentRepository($connection);
    $studentRepository = new StudentRepository($connection);
    $programmeRepository = new AcademicProgrammeRepository($connection);
    $termRepository = new AcademicTermRepository($connection);
    $students = $studentRepository->search('');
    $programmes = $programmeRepository->active();
    $terms = $termRepository->active();

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
            $errors[] = 'Only administrators can manage student enrolments.';
        }
        $editingId = (int) ($_POST['id'] ?? 0);
        $form = $repository->normalize($_POST);
        $errors = array_merge($errors, $repository->validate($form));
        if ($errors === []) {
            if ($editingId > 0) {
                $repository->update($editingId, $form);
                $success = 'Enrolment updated.';
            } else {
                $repository->create($form);
                $success = 'Student enrolled.';
                $form = ['student_id' => 0, 'programme_id' => 0, 'academic_term_id' => 0, 'status' => 'active', 'enrolled_at' => date('Y-m-d')];
            }
        }
    }

    $enrolments = $repository->all();
} catch (Throwable $exception) {
    $students = $programmes = $terms = $enrolments = [];
    $errors[] = 'Academic enrolments are unavailable. Run database/migrations/20260919_create_academic_foundation.sql. ' . $exception->getMessage();
}

function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Student Enrolments</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link href="assets/app.css" rel="stylesheet"></head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/header.php'; ?>
<div class="container py-4">
    <div class="ndc-page-heading"><div><div class="ndc-eyebrow">Academic</div><h1 class="h3 mb-1">Student Enrolments</h1><p class="text-muted mb-0">Assign students to programmes and academic terms.</p></div></div>
    <?php require __DIR__ . '/partials/academic-nav.php'; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e((string) $error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-4">
            <form method="post" class="card shadow-sm"><div class="card-body"><h2 class="h5 mb-3"><?= $editingId > 0 ? 'Edit Enrolment' : 'Add Enrolment' ?></h2><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int) $editingId ?>">
                <div class="mb-3"><label class="form-label">Student</label><select name="student_id" class="form-select" required <?= !$canManage ? 'disabled' : '' ?>><option value="">Select student</option><?php foreach ($students as $student): ?><?php $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')); ?><option value="<?= (int) $student['id'] ?>" <?= (int) $form['student_id'] === (int) $student['id'] ? 'selected' : '' ?>><?= e((string) ($student['student_number'] ?? '') . ' - ' . $name) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">Programme</label><select name="programme_id" class="form-select" required <?= !$canManage ? 'disabled' : '' ?>><option value="">Select programme</option><?php foreach ($programmes as $programme): ?><option value="<?= (int) $programme['id'] ?>" <?= (int) $form['programme_id'] === (int) $programme['id'] ? 'selected' : '' ?>><?= e((string) $programme['code'] . ' - ' . (string) $programme['name']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">Academic term</label><select name="academic_term_id" class="form-select" required <?= !$canManage ? 'disabled' : '' ?>><option value="">Select term</option><?php foreach ($terms as $term): ?><option value="<?= (int) $term['id'] ?>" <?= (int) $form['academic_term_id'] === (int) $term['id'] ? 'selected' : '' ?>><?= e((string) $term['academic_year'] . ' - ' . (string) $term['name'] . ' ' . (string) ($term['semester'] ?? '')) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">Enrolled at</label><input type="date" name="enrolled_at" class="form-control" value="<?= e((string) $form['enrolled_at']) ?>" required <?= !$canManage ? 'disabled' : '' ?>></div>
                <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select" <?= !$canManage ? 'disabled' : '' ?>><?php foreach (['active', 'completed', 'withdrawn', 'suspended'] as $status): ?><option value="<?= e($status) ?>" <?= $form['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
            </div><div class="card-footer bg-white text-end"><?php if ($canManage): ?><button class="btn btn-primary">Save enrolment</button><?php else: ?><span class="text-muted small">View only</span><?php endif; ?></div></form>
        </div>
        <div class="col-lg-8"><div class="card shadow-sm"><div class="card-body"><h2 class="h5 mb-3">Enrolment List</h2><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Student</th><th>Programme</th><th>Term</th><th>Enrolled</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($enrolments as $enrolment): ?><?php $name = trim((string) ($enrolment['first_name'] ?? '') . ' ' . (string) ($enrolment['last_name'] ?? '')); ?><tr><td><?= e((string) ($enrolment['student_number'] ?? '') . ' - ' . $name) ?></td><td><?= e((string) $enrolment['programme_code'] . ' - ' . (string) $enrolment['programme_name']) ?></td><td><?= e((string) $enrolment['academic_year'] . ' - ' . (string) $enrolment['term_name']) ?></td><td><?= e((string) $enrolment['enrolled_at']) ?></td><td><span class="badge text-bg-<?= ($enrolment['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>"><?= e((string) $enrolment['status']) ?></span></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="academic-enrolments.php?edit=<?= (int) $enrolment['id'] ?>">Edit</a></td></tr><?php endforeach; ?></tbody></table></div></div></div></div>
    </div>
</div>
</body></html>
