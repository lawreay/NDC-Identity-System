<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';

use App\Auth;

Auth::requireLogin();

$stats = ['programmes' => 0, 'courses' => 0, 'terms' => 0, 'enrolments' => 0];
$error = '';

try {
    $connection = Database::getConnection();
    $stats['programmes'] = (int) $connection->query('SELECT COUNT(*) FROM academic_programmes')->fetchColumn();
    $stats['courses'] = (int) $connection->query('SELECT COUNT(*) FROM academic_courses')->fetchColumn();
    $stats['terms'] = (int) $connection->query('SELECT COUNT(*) FROM academic_terms')->fetchColumn();
    $stats['enrolments'] = (int) $connection->query('SELECT COUNT(*) FROM student_enrolments')->fetchColumn();
} catch (Throwable $exception) {
    $error = 'Academic tables are not ready. Run database/migrations/20260919_create_academic_foundation.sql first.';
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
    <title>Academic Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/header.php'; ?>
<div class="container py-4">
    <div class="ndc-page-heading">
        <div>
            <div class="ndc-eyebrow"><i class="bi bi-mortarboard-fill me-1" aria-hidden="true"></i>Academic Foundation</div>
            <h1 class="h3 mb-1">Academic Dashboard</h1>
            <p class="text-muted mb-0">Manage programmes, courses, terms, and student enrolments.</p>
        </div>
    </div>
    <?php require __DIR__ . '/partials/academic-nav.php'; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-warning"><?= e($error) ?></div>
    <?php endif; ?>
    <div class="row g-3">
        <?php foreach ([['Programmes', 'programmes', 'bi-diagram-3-fill'], ['Courses', 'courses', 'bi-journal-bookmark-fill'], ['Terms', 'terms', 'bi-calendar3'], ['Enrolments', 'enrolments', 'bi-person-check-fill']] as $item): ?>
            <div class="col-md-3">
                <div class="ndc-stat-card">
                    <span class="ndc-stat-icon ndc-stat-icon-blue"><i class="bi <?= e($item[2]) ?>" aria-hidden="true"></i></span>
                    <div><span class="ndc-stat-label"><?= e($item[0]) ?></span><strong><?= number_format($stats[$item[1]]) ?></strong><small>Academic foundation</small></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
