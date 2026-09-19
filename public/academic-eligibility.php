<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/AcademicChecksheetService.php';
require_once __DIR__ . '/../app/AcademicEligibilityService.php';

use App\Auth;

Auth::requireLogin();
$studentId = (int) ($_GET['student_id'] ?? 0);
$programmeId = (int) ($_GET['programme_id'] ?? 0);
$students = [];
$programmes = [];
$assessment = null;
$error = '';

try {
    $connection = Database::getConnection();
    $students = (new StudentRepository($connection))->search('');
    $checksheetService = new AcademicChecksheetService($connection);
    if ($studentId > 0) {
        $programmes = $checksheetService->programmesForStudent($studentId);
        $assessment = (new AcademicEligibilityService($checksheetService))->assess($studentId, $programmeId);
        if ($assessment === null) {
            $error = 'The selected student has no enrolment for this programme.';
        } elseif ($programmeId === 0) {
            $programmeId = (int) $assessment['checksheet']['programme']['id'];
        }
    }
} catch (Throwable $exception) {
    $error = 'Eligibility assessment is unavailable. Ensure the academic migrations, including student results, have been applied. ' . $exception->getMessage();
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
    <title>Academic Eligibility</title>
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
            <h1 class="h3 mb-1">Eligibility Assessment</h1>
            <p class="text-muted mb-0">Assess whether a student meets the academic requirements for a programme credential.</p>
        </div>
    </div>
    <?php require __DIR__ . '/partials/academic-nav.php'; ?>
    <?php if ($error !== ''): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>

    <form method="get" class="card shadow-sm mb-4">
        <div class="card-body"><div class="row g-3 align-items-end">
            <div class="col-md-6"><label class="form-label">Student</label><select name="student_id" class="form-select" required><option value="">Select student</option><?php foreach ($students as $student): ?><?php $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')); ?><option value="<?= (int) $student['id'] ?>" <?= $studentId === (int) $student['id'] ? 'selected' : '' ?>><?= e((string) ($student['student_number'] ?? '') . ' - ' . $name) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Programme</label><select name="programme_id" class="form-select" <?= $studentId <= 0 ? 'disabled' : '' ?>><option value="0">Latest enrolled programme</option><?php foreach ($programmes as $programme): ?><option value="<?= (int) $programme['programme_id'] ?>" <?= $programmeId === (int) $programme['programme_id'] ? 'selected' : '' ?>><?= e((string) $programme['code'] . ' - ' . (string) $programme['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Assess</button></div>
        </div></div>
    </form>

    <?php if ($assessment !== null): ?>
        <?php $checksheet = $assessment['checksheet']; $student = $checksheet['student']; $programme = $checksheet['programme']; ?>
        <div class="card shadow-sm mb-4 border-<?= $assessment['eligible'] ? 'success' : 'danger' ?>"><div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3"><div><div class="ndc-eyebrow">Credential readiness</div><h2 class="h4 mb-1"><?= e(trim((string) $student['first_name'] . ' ' . (string) $student['last_name'])) ?></h2><p class="mb-0 text-muted"><?= e((string) $student['student_number'] . ' | ' . (string) $programme['code'] . ' - ' . (string) $programme['name']) ?></p></div><div class="text-lg-end"><div class="small text-muted">Assessment result</div><div class="fs-3 fw-bold text-<?= $assessment['eligible'] ? 'success' : 'danger' ?>"><?= $assessment['eligible'] ? 'ELIGIBLE' : 'INELIGIBLE' ?></div></div></div>
        </div></div>

        <div class="card shadow-sm"><div class="card-body"><h2 class="h5 mb-3">Requirement assessment</h2><div class="list-group list-group-flush"><?php foreach ($assessment['requirements'] as $requirement): ?><div class="list-group-item px-0 d-flex gap-3 align-items-start"><i class="bi bi-<?= $requirement['passed'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?> fs-5" aria-hidden="true"></i><div><div class="fw-semibold"><?= e((string) $requirement['label']) ?></div><div class="text-muted small"><?= e((string) $requirement['detail']) ?></div></div></div><?php endforeach; ?></div></div></div>

        <?php if (!$assessment['eligible']): ?><div class="alert alert-warning mt-4 mb-0"><strong>Outstanding requirements</strong><?php if ($assessment['outstanding_courses'] !== []): ?><ul class="mb-0 mt-2"><?php foreach ($assessment['outstanding_courses'] as $course): ?><li><?= e((string) $course['code'] . ' - ' . (string) $course['name']) ?>: <?= e((string) $course['requirement_status']) ?></li><?php endforeach; ?></ul><?php endif; ?><?php if ($assessment['missing_approved_results'] !== []): ?><div class="<?= $assessment['outstanding_courses'] !== [] ? 'mt-2' : 'mt-0' ?>">Approved results are still needed for: <?= e(implode(', ', array_map(static fn (array $course): string => (string) $course['code'], $assessment['missing_approved_results']))) ?>.</div><?php endif; ?></div><?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
