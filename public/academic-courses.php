<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/AcademicCourseRepository.php';
require_once __DIR__ . '/../app/AcademicProgrammeRepository.php';

use App\Auth;

$user = Auth::requireLogin();
$canManage = ($user['role'] ?? '') === 'Administrator';
$errors = [];
$success = '';
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$form = ['programme_id' => 0, 'programme_ids' => [], 'code' => '', 'name' => '', 'credits' => 0, 'semester' => '', 'course_type' => 'core', 'is_compulsory' => 1, 'status' => 'active'];

try {
    $connection = Database::getConnection();
    $repository = new AcademicCourseRepository($connection);
    $programmeRepository = new AcademicProgrammeRepository($connection);
    $programmes = $programmeRepository->active();

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
            $errors[] = 'Only administrators can manage academic courses.';
        }
        $editingId = (int) ($_POST['id'] ?? 0);
        $form = $repository->normalize($_POST);
        $errors = array_merge($errors, $repository->validate($form));
        if ($errors === []) {
            if ($editingId > 0) {
                $repository->update($editingId, $form);
                $success = 'Course updated.';
            } else {
                $repository->create($form);
                $success = 'Course created.';
                $form = ['programme_id' => 0, 'programme_ids' => [], 'code' => '', 'name' => '', 'credits' => 0, 'semester' => '', 'course_type' => 'core', 'is_compulsory' => 1, 'status' => 'active'];
            }
        }
    }

    $courses = $repository->all();
} catch (Throwable $exception) {
    $courses = [];
    $programmes = [];
    $errors[] = 'Academic courses are unavailable. Run database/migrations/20260919_create_academic_foundation.sql and database/migrations/20260919_create_academic_course_programmes.sql. ' . $exception->getMessage();
}

function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Academic Courses</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link href="assets/app.css" rel="stylesheet"></head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/header.php'; ?>
<div class="container py-4">
    <div class="ndc-page-heading"><div><div class="ndc-eyebrow">Academic</div><h1 class="h3 mb-1">Courses</h1><p class="text-muted mb-0">Attach courses and credits to programmes.</p></div></div>
    <?php require __DIR__ . '/partials/academic-nav.php'; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e((string) $error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-4">
            <form method="post" class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3"><?= $editingId > 0 ? 'Edit Course' : 'Add Course' ?></h2>
                    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int) $editingId ?>">
                    <div class="mb-3"><label class="form-label">Programmes</label><div class="border rounded p-2 bg-light" style="max-height: 220px; overflow-y: auto;"><?php $selectedProgrammes = array_map('intval', (array) ($form['programme_ids'] ?? [])); foreach ($programmes as $programme): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="programme_ids[]" id="programme<?= (int) $programme['id'] ?>" value="<?= (int) $programme['id'] ?>" <?= in_array((int) $programme['id'], $selectedProgrammes, true) ? 'checked' : '' ?> <?= !$canManage ? 'disabled' : '' ?>><label class="form-check-label" for="programme<?= (int) $programme['id'] ?>"><?= e((string) $programme['code'] . ' - ' . (string) $programme['name']) ?></label></div><?php endforeach; ?></div><div class="form-text">Tick every programme that should take this course.</div></div>
                    <div class="mb-3"><label class="form-label">Course code</label><input name="code" class="form-control" value="<?= e((string) $form['code']) ?>" required <?= !$canManage ? 'disabled' : '' ?>></div>
                    <div class="mb-3"><label class="form-label">Course name</label><input name="name" class="form-control" value="<?= e((string) $form['name']) ?>" required <?= !$canManage ? 'disabled' : '' ?>></div>
                    <div class="row g-2"><div class="col-6 mb-3"><label class="form-label">Credits</label><input type="number" step="0.01" min="0" name="credits" class="form-control" value="<?= e((string) $form['credits']) ?>" <?= !$canManage ? 'disabled' : '' ?>></div><div class="col-6 mb-3"><label class="form-label">Semester</label><input name="semester" class="form-control" value="<?= e((string) $form['semester']) ?>" <?= !$canManage ? 'disabled' : '' ?>></div></div>
                    <div class="mb-3"><label class="form-label">Type</label><select name="course_type" class="form-select" <?= !$canManage ? 'disabled' : '' ?>><?php foreach (['core', 'elective', 'optional'] as $type): ?><option value="<?= e($type) ?>" <?= $form['course_type'] === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option><?php endforeach; ?></select></div>
                    <div class="form-check mb-3"><input id="isCompulsory" class="form-check-input" type="checkbox" name="is_compulsory" value="1" <?= !empty($form['is_compulsory']) ? 'checked' : '' ?> <?= !$canManage ? 'disabled' : '' ?>><label class="form-check-label" for="isCompulsory">Compulsory course</label></div>
                    <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select" <?= !$canManage ? 'disabled' : '' ?>><?php foreach (['active', 'inactive'] as $status): ?><option value="<?= e($status) ?>" <?= $form['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="card-footer bg-white text-end"><?php if ($canManage): ?><button class="btn btn-primary">Save course</button><?php else: ?><span class="text-muted small">View only</span><?php endif; ?></div>
            </form>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-sm"><div class="card-body"><h2 class="h5 mb-3">Course List</h2><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Programmes</th><th>Code</th><th>Course</th><th>Credits</th><th>Type</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($courses as $course): ?><tr><td><?= e((string) ($course['programme_codes'] ?: $course['programme_code'])) ?></td><td><?= e((string) $course['code']) ?></td><td><?= e((string) $course['name']) ?></td><td><?= e((string) $course['credits']) ?></td><td><?= e((string) $course['course_type']) ?><?= (int) $course['is_compulsory'] === 1 ? ' / compulsory' : '' ?></td><td><span class="badge text-bg-<?= ($course['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>"><?= e((string) $course['status']) ?></span></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="academic-courses.php?edit=<?= (int) $course['id'] ?>">Edit</a></td></tr><?php endforeach; ?></tbody></table></div></div></div>
        </div>
    </div>
</div>
</body></html>
