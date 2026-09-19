<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/AcademicProgrammeRepository.php';
require_once __DIR__ . '/../app/AcademicCourseRepository.php';
require_once __DIR__ . '/../app/AcademicTermRepository.php';
require_once __DIR__ . '/../app/StudentResultRepository.php';

use App\Auth;

$user = Auth::requireLogin();
$canManage = ($user['role'] ?? '') === 'Administrator';
$errors = [];
$success = '';
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$filters = [
    'programme_id' => (int) ($_GET['programme_id'] ?? 0),
    'course_id' => (int) ($_GET['course_id'] ?? 0),
    'academic_term_id' => (int) ($_GET['academic_term_id'] ?? 0),
];
$form = [
    'student_id' => 0,
    'course_id' => 0,
    'academic_term_id' => 0,
    'mark' => '',
    'grade' => '',
    'assessed_at' => date('Y-m-d'),
    'remarks' => '',
];

try {
    $connection = Database::getConnection();
    $repository = new StudentResultRepository($connection);
    $studentRepository = new StudentRepository($connection);
    $programmeRepository = new AcademicProgrammeRepository($connection);
    $courseRepository = new AcademicCourseRepository($connection);
    $termRepository = new AcademicTermRepository($connection);

    if ($editingId > 0) {
        $existing = $repository->find($editingId);
        if ($existing === null) {
            $errors[] = 'Result not found.';
            $editingId = 0;
        } elseif (($existing['status'] ?? '') !== 'draft') {
            $errors[] = 'Only draft results can be edited.';
            $editingId = 0;
        } else {
            foreach ($form as $field => $_) {
                $form[$field] = $existing[$field] ?? $form[$field];
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Auth::requireCsrf();
        if (!$canManage) {
            $errors[] = 'Only administrators can manage student results.';
        }

        $action = (string) ($_POST['action'] ?? 'save');
        $resultId = (int) ($_POST['id'] ?? 0);

        if ($errors === [] && $action === 'approve') {
            if ($repository->approve($resultId, (int) ($user['id'] ?? 0))) {
                $success = 'Result approved.';
            } else {
                $errors[] = 'Only a draft result can be approved.';
            }
        } elseif ($errors === [] && $action === 'void') {
            if ($repository->void($resultId)) {
                $success = 'Result voided.';
            } else {
                $errors[] = 'Only draft or approved results can be voided.';
            }
        } elseif ($errors === []) {
            $editingId = $resultId;
            $form = $repository->normalize($_POST);
            $errors = $repository->validate($form, $editingId);
            if ($errors === []) {
                if ($editingId > 0) {
                    $repository->update($editingId, $form);
                    $success = 'Draft result updated.';
                } else {
                    $repository->create($form);
                    $success = 'Draft result saved. Approve it when it has been checked.';
                    $form = [
                        'student_id' => 0,
                        'course_id' => 0,
                        'academic_term_id' => 0,
                        'mark' => '',
                        'grade' => '',
                        'assessed_at' => date('Y-m-d'),
                        'remarks' => '',
                    ];
                }
            }
        }
    }

    $students = $studentRepository->search('');
    $programmes = $programmeRepository->active();
    $courses = $courseRepository->all();
    $terms = $termRepository->active();
    $results = $repository->all($filters);
} catch (Throwable $exception) {
    $students = $programmes = $courses = $terms = $results = [];
    $errors[] = 'Academic results are unavailable. Run database/migrations/20260919_create_student_results.sql. ' . $exception->getMessage();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function resultBadgeClass(string $status): string
{
    return match ($status) {
        'approved' => 'success',
        'void' => 'danger',
        default => 'warning',
    };
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Results</title>
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
            <h1 class="h3 mb-1">Academic Results</h1>
            <p class="text-muted mb-0">Record marks, calculate grades, and approve verified results.</p>
        </div>
    </div>
    <?php require __DIR__ . '/partials/academic-nav.php'; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($errors !== []): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e((string) $error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <form method="post" class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-1"><?= $editingId > 0 ? 'Edit Draft Result' : 'Record Result' ?></h2>
                    <p class="text-muted small mb-3">Grade scale: A 80-100, B 70-79, C 60-69, D 50-59, F below 50.</p>
                    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= $editingId ?>">
                    <input type="hidden" name="action" value="save">
                    <div class="mb-3"><label class="form-label">Student</label><select name="student_id" class="form-select" required <?= !$canManage ? 'disabled' : '' ?>><option value="">Select student</option><?php foreach ($students as $student): ?><?php $studentName = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')); ?><option value="<?= (int) $student['id'] ?>" <?= (int) $form['student_id'] === (int) $student['id'] ? 'selected' : '' ?>><?= e((string) ($student['student_number'] ?? '') . ' - ' . $studentName . ' / ' . (string) ($student['program'] ?? '')) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Course</label><select name="course_id" class="form-select" required <?= !$canManage ? 'disabled' : '' ?>><option value="">Select course</option><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>" <?= (int) $form['course_id'] === (int) $course['id'] ? 'selected' : '' ?>><?= e((string) $course['code'] . ' - ' . (string) $course['name'] . ' / ' . (string) ($course['programme_codes'] ?: $course['programme_code'])) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Term</label><select name="academic_term_id" class="form-select" required <?= !$canManage ? 'disabled' : '' ?>><option value="">Select term</option><?php foreach ($terms as $term): ?><option value="<?= (int) $term['id'] ?>" <?= (int) $form['academic_term_id'] === (int) $term['id'] ? 'selected' : '' ?>><?= e((string) $term['academic_year'] . ' - ' . (string) $term['name'] . ' ' . (string) ($term['semester'] ?? '')) ?></option><?php endforeach; ?></select></div>
                    <div class="row g-2"><div class="col-7 mb-3"><label class="form-label">Mark</label><input id="result-mark" type="number" name="mark" class="form-control" min="0" max="100" step="0.01" value="<?= e((string) $form['mark']) ?>" required <?= !$canManage ? 'disabled' : '' ?>></div><div class="col-5 mb-3"><label class="form-label">Grade</label><input id="result-grade" class="form-control" value="<?= e((string) $form['grade']) ?>" readonly></div></div>
                    <div class="mb-3"><label class="form-label">Assessment date</label><input type="date" name="assessed_at" class="form-control" value="<?= e((string) $form['assessed_at']) ?>" required <?= !$canManage ? 'disabled' : '' ?>></div>
                    <div class="mb-3"><label class="form-label">Remarks</label><input name="remarks" class="form-control" maxlength="255" value="<?= e((string) $form['remarks']) ?>" <?= !$canManage ? 'disabled' : '' ?>></div>
                </div>
                <div class="card-footer bg-white text-end"><?php if ($canManage): ?><button class="btn btn-primary">Save draft result</button><?php else: ?><span class="text-muted small">View only</span><?php endif; ?></div>
            </form>
        </div>
        <div class="col-lg-8">
            <form method="get" class="card shadow-sm mb-4">
                <div class="card-body"><div class="row g-2 align-items-end">
                    <div class="col-md-4"><label class="form-label">Programme</label><select name="programme_id" class="form-select"><option value="0">All programmes</option><?php foreach ($programmes as $programme): ?><option value="<?= (int) $programme['id'] ?>" <?= $filters['programme_id'] === (int) $programme['id'] ? 'selected' : '' ?>><?= e((string) $programme['code'] . ' - ' . (string) $programme['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><label class="form-label">Course</label><select name="course_id" class="form-select"><option value="0">All courses</option><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>" <?= $filters['course_id'] === (int) $course['id'] ? 'selected' : '' ?>><?= e((string) $course['code']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><label class="form-label">Term</label><select name="academic_term_id" class="form-select"><option value="0">All terms</option><?php foreach ($terms as $term): ?><option value="<?= (int) $term['id'] ?>" <?= $filters['academic_term_id'] === (int) $term['id'] ? 'selected' : '' ?>><?= e((string) $term['academic_year'] . ' - ' . (string) $term['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary flex-grow-1">Filter</button><a class="btn btn-outline-secondary" href="academic-results.php">Clear</a></div>
                </div></div>
            </form>
            <div class="card shadow-sm"><div class="card-body"><h2 class="h5 mb-3">Results</h2><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Student</th><th>Course</th><th>Term</th><th class="text-end">Mark</th><th>Grade</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($results as $result): ?><?php $studentName = trim((string) ($result['first_name'] ?? '') . ' ' . (string) ($result['last_name'] ?? '')); ?><tr><td><?= e((string) ($result['student_number'] ?? '') . ' - ' . $studentName) ?></td><td><?= e((string) $result['course_code'] . ' - ' . (string) $result['course_name']) ?></td><td><?= e((string) $result['academic_year'] . ' - ' . (string) $result['term_name']) ?></td><td class="text-end"><?= e(number_format((float) $result['mark'], 2)) ?></td><td><?= e((string) $result['grade']) ?></td><td><span class="badge text-bg-<?= e(resultBadgeClass((string) $result['status'])) ?>"><?= e((string) $result['status']) ?></span></td><td class="text-end text-nowrap"><?php if ($canManage && $result['status'] === 'draft'): ?><a class="btn btn-sm btn-outline-secondary" href="academic-results.php?edit=<?= (int) $result['id'] ?>">Edit</a><form method="post" class="d-inline"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int) $result['id'] ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-sm btn-success">Approve</button></form><?php endif; ?><?php if ($canManage && in_array($result['status'], ['draft', 'approved'], true)): ?><form method="post" class="d-inline"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int) $result['id'] ?>"><input type="hidden" name="action" value="void"><button class="btn btn-sm btn-outline-danger">Void</button></form><?php endif; ?></td></tr><?php endforeach; ?><?php if ($results === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No results match the current filters.</td></tr><?php endif; ?></tbody></table></div></div></div>
        </div>
    </div>
</div>
<script>
(() => {
    const mark = document.getElementById('result-mark');
    const grade = document.getElementById('result-grade');
    if (!mark || !grade) return;
    const calculate = () => {
        const value = Number(mark.value);
        grade.value = !Number.isFinite(value) || value < 0 || value > 100 ? '' : value >= 80 ? 'A' : value >= 70 ? 'B' : value >= 60 ? 'C' : value >= 50 ? 'D' : 'F';
    };
    mark.addEventListener('input', calculate);
    calculate();
})();
</script>
</body>
</html>
