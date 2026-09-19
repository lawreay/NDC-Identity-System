<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/AcademicTermRepository.php';

use App\Auth;

$user = Auth::requireLogin();
$canManage = ($user['role'] ?? '') === 'Administrator';
$errors = [];
$success = '';
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$form = ['name' => '', 'academic_year' => date('Y'), 'semester' => '', 'start_date' => '', 'end_date' => '', 'status' => 'active'];

try {
    $repository = new AcademicTermRepository(Database::getConnection());
    if ($editingId > 0) {
        $existing = $repository->find($editingId);
        if ($existing) {
            foreach ($form as $field => $_) {
                $form[$field] = (string) ($existing[$field] ?? '');
            }
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Auth::requireCsrf();
        if (!$canManage) {
            $errors[] = 'Only administrators can manage academic terms.';
        }
        $editingId = (int) ($_POST['id'] ?? 0);
        $form = $repository->normalize($_POST);
        $errors = array_merge($errors, $repository->validate($form));
        if ($errors === []) {
            if ($editingId > 0) {
                $repository->update($editingId, $form);
                $success = 'Academic term updated.';
            } else {
                $repository->create($form);
                $success = 'Academic term created.';
                $form = ['name' => '', 'academic_year' => date('Y'), 'semester' => '', 'start_date' => '', 'end_date' => '', 'status' => 'active'];
            }
        }
    }
    $terms = $repository->all();
} catch (Throwable $exception) {
    $terms = [];
    $errors[] = 'Academic terms are unavailable. Run database/migrations/20260919_create_academic_foundation.sql. ' . $exception->getMessage();
}

function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Academic Terms</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link href="assets/app.css" rel="stylesheet"></head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/header.php'; ?>
<div class="container py-4">
    <div class="ndc-page-heading"><div><div class="ndc-eyebrow">Academic</div><h1 class="h3 mb-1">Academic Terms</h1><p class="text-muted mb-0">Create academic years and semesters for enrolments and results.</p></div></div>
    <?php require __DIR__ . '/partials/academic-nav.php'; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e((string) $error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-4">
            <form method="post" class="card shadow-sm"><div class="card-body"><h2 class="h5 mb-3"><?= $editingId > 0 ? 'Edit Term' : 'Add Term' ?></h2><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int) $editingId ?>"><div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="<?= e($form['name']) ?>" required <?= !$canManage ? 'disabled' : '' ?>></div><div class="mb-3"><label class="form-label">Academic year</label><input name="academic_year" class="form-control" value="<?= e($form['academic_year']) ?>" required <?= !$canManage ? 'disabled' : '' ?>></div><div class="mb-3"><label class="form-label">Semester</label><input name="semester" class="form-control" value="<?= e($form['semester']) ?>" <?= !$canManage ? 'disabled' : '' ?>></div><div class="row g-2"><div class="col-6 mb-3"><label class="form-label">Start</label><input type="date" name="start_date" class="form-control" value="<?= e($form['start_date']) ?>" <?= !$canManage ? 'disabled' : '' ?>></div><div class="col-6 mb-3"><label class="form-label">End</label><input type="date" name="end_date" class="form-control" value="<?= e($form['end_date']) ?>" <?= !$canManage ? 'disabled' : '' ?>></div></div><div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select" <?= !$canManage ? 'disabled' : '' ?>><?php foreach (['active', 'inactive', 'closed'] as $status): ?><option value="<?= e($status) ?>" <?= $form['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div></div><div class="card-footer bg-white text-end"><?php if ($canManage): ?><button class="btn btn-primary">Save term</button><?php else: ?><span class="text-muted small">View only</span><?php endif; ?></div></form>
        </div>
        <div class="col-lg-8"><div class="card shadow-sm"><div class="card-body"><h2 class="h5 mb-3">Term List</h2><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Name</th><th>Year</th><th>Semester</th><th>Dates</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($terms as $term): ?><tr><td><?= e((string) $term['name']) ?></td><td><?= e((string) $term['academic_year']) ?></td><td><?= e((string) ($term['semester'] ?? '')) ?></td><td><?= e(trim((string) ($term['start_date'] ?? '') . ' - ' . (string) ($term['end_date'] ?? ''), ' -')) ?></td><td><span class="badge text-bg-<?= ($term['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>"><?= e((string) $term['status']) ?></span></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="academic-terms.php?edit=<?= (int) $term['id'] ?>">Edit</a></td></tr><?php endforeach; ?></tbody></table></div></div></div></div>
    </div>
</div>
</body></html>
