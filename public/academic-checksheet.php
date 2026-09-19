<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/AcademicChecksheetService.php';

use App\Auth;

Auth::requireLogin();
$studentId = (int) ($_GET['student_id'] ?? 0);
$programmeId = (int) ($_GET['programme_id'] ?? 0);
$students = [];
$programmes = [];
$checksheet = null;
$error = '';

try {
    $connection = Database::getConnection();
    $students = (new StudentRepository($connection))->search('');
    $service = new AcademicChecksheetService($connection);
    if ($studentId > 0) {
        $programmes = $service->programmesForStudent($studentId);
        $checksheet = $service->build($studentId, $programmeId);
        if ($checksheet === null) {
            $error = 'The selected student has no enrolment for this programme.';
        } elseif ($programmeId === 0) {
            $programmeId = (int) $checksheet['programme']['id'];
        }
    }
} catch (Throwable $exception) {
    $error = 'Academic checksheets are unavailable. ' . $exception->getMessage();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function requirementBadgeClass(string $status): string
{
    return match ($status) {
        'complete' => 'success',
        'exempted' => 'info',
        'failed', 'incomplete' => 'danger',
        'awaiting completion' => 'warning',
        default => 'secondary',
    };
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Checksheet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/header.php'; ?>
<div class="container py-4">
    <div class="ndc-page-heading d-flex flex-wrap justify-content-between gap-3">
        <div>
            <div class="ndc-eyebrow">Academic</div>
            <h1 class="h3 mb-1">Academic Checksheet</h1>
            <p class="text-muted mb-0">Programme requirements generated from student academic records.</p>
        </div>
        <?php if ($checksheet !== null): ?><button class="btn btn-outline-secondary align-self-start" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button><?php endif; ?>
    </div>
    <?php require __DIR__ . '/partials/academic-nav.php'; ?>
    <?php if ($error !== ''): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>

    <form method="get" class="card shadow-sm mb-4">
        <div class="card-body"><div class="row g-3 align-items-end">
            <div class="col-md-6"><label class="form-label">Student</label><select name="student_id" class="form-select" required><option value="">Select student</option><?php foreach ($students as $student): ?><?php $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')); ?><option value="<?= (int) $student['id'] ?>" <?= $studentId === (int) $student['id'] ? 'selected' : '' ?>><?= e((string) ($student['student_number'] ?? '') . ' - ' . $name) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Programme</label><select name="programme_id" class="form-select" <?= $studentId <= 0 ? 'disabled' : '' ?>><option value="0">Latest enrolled programme</option><?php foreach ($programmes as $programme): ?><option value="<?= (int) $programme['programme_id'] ?>" <?= $programmeId === (int) $programme['programme_id'] ? 'selected' : '' ?>><?= e((string) $programme['code'] . ' - ' . (string) $programme['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">View checksheet</button></div>
        </div></div>
    </form>

    <?php if ($checksheet !== null): ?>
        <?php $summary = $checksheet['summary']; $student = $checksheet['student']; $programme = $checksheet['programme']; ?>
        <div class="card shadow-sm mb-4"><div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div><div class="ndc-eyebrow">Student academic record</div><h2 class="h4 mb-1"><?= e(trim((string) $student['first_name'] . ' ' . (string) $student['last_name'])) ?></h2><div class="text-muted"><?= e((string) $student['student_number']) ?> | <?= e((string) $programme['code'] . ' - ' . (string) $programme['name']) ?></div></div>
                <div class="text-lg-end"><div class="text-muted small">Academic completion</div><div class="fs-5 fw-bold text-<?= $summary['is_complete'] ? 'success' : 'danger' ?>"><?= $summary['is_complete'] ? 'COMPLETE' : 'INCOMPLETE' ?></div></div>
            </div>
            <div class="row g-3 mt-1"><div class="col-sm-6 col-xl-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Required courses</div><strong><?= (int) $summary['completed_required_courses'] ?> / <?= (int) $summary['required_courses'] ?></strong></div></div><div class="col-sm-6 col-xl-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Required credits</div><strong><?= e(number_format((float) $summary['required_credits'], 2)) ?></strong></div></div><div class="col-sm-6 col-xl-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Completed credits</div><strong><?= e(number_format((float) $summary['completed_credits'], 2)) ?></strong></div></div><div class="col-sm-6 col-xl-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Outstanding required</div><strong><?= (int) $summary['outstanding_required_courses'] ?></strong></div></div></div>
        </div></div>

        <div class="card shadow-sm"><div class="card-body"><h2 class="h5 mb-3">Course requirements</h2><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Course</th><th>Semester</th><th>Type</th><th class="text-end">Credits</th><th>Approved result</th><th>Completion</th><th>Requirement</th></tr></thead><tbody><?php foreach ($checksheet['courses'] as $course): ?><tr><td><div class="fw-semibold"><?= e((string) $course['code']) ?></div><div class="small text-muted"><?= e((string) $course['name']) ?></div></td><td><?= e((string) ($course['semester'] ?: '-')) ?></td><td><?= (int) $course['is_compulsory'] === 1 ? 'Required' : e(ucfirst((string) $course['course_type'])) ?></td><td class="text-end"><?= e(number_format((float) $course['credits'], 2)) ?></td><td><?php if ($course['result_mark'] !== null): ?><?= e(number_format((float) $course['result_mark'], 2)) ?> (<?= e((string) $course['result_grade']) ?>)<?php else: ?><span class="text-muted">None</span><?php endif; ?></td><td><?= e((string) ($course['completion_status'] ?: '-')) ?></td><td><span class="badge text-bg-<?= e(requirementBadgeClass((string) $course['requirement_status'])) ?>"><?= e((string) $course['requirement_status']) ?></span></td></tr><?php endforeach; ?><?php if ($checksheet['courses'] === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No courses are assigned to this programme.</td></tr><?php endif; ?></tbody></table></div></div></div>

        <?php if ($summary['outstanding_required'] !== []): ?><div class="alert alert-warning mt-4 mb-0"><strong>Outstanding requirements:</strong><ul class="mb-0 mt-2"><?php foreach ($summary['outstanding_required'] as $course): ?><li><?= e((string) $course['code'] . ' - ' . (string) $course['name']) ?> (<?= e(number_format((float) $course['credits'], 2)) ?> credits)</li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
