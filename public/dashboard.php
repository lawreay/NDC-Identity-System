<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/StudentRepository.php';

use App\Auth;

Auth::requireLogin();

$connection = null;
$stats = [
    'students' => 0,
    'active_students' => 0,
    'missing_surnames' => 0,
    'active_cards' => 0,
    'expired_cards' => 0,
];
$recentStudents = [];
$errors = [];

try {
    $connection = Database::getConnection();
    $stats['students'] = (int) $connection->query('SELECT COUNT(*) FROM students')->fetchColumn();
    $stats['active_students'] = (int) $connection->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();
    $stats['missing_surnames'] = (int) $connection->query("SELECT COUNT(*) FROM students WHERE last_name IS NULL OR TRIM(last_name) = ''")->fetchColumn();
    $recentStudents = $connection->query(
        'SELECT id, student_number, first_name, last_name, program, status FROM students ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT 6'
    )->fetchAll();
} catch (Throwable $exception) {
    $errors[] = 'Unable to load student statistics: ' . $exception->getMessage();
}

if ($connection instanceof PDO) {
    try {
        $stats['active_cards'] = (int) $connection->query("SELECT COUNT(*) FROM student_id_cards WHERE status = 'ACTIVE' AND expires_at >= CURDATE()")->fetchColumn();
        $stats['expired_cards'] = (int) $connection->query("SELECT COUNT(*) FROM student_id_cards WHERE status = 'ACTIVE' AND expires_at < CURDATE()")->fetchColumn();
    } catch (Throwable $exception) {
        // Card verification may not have been configured yet; keep zero values and show the setup hint below.
    }
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard · NDC Identity System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/partials/header.php'; ?>
<main class="container pb-5">
    <div class="ndc-page-heading ndc-hero-heading">
        <div>
            <div class="ndc-eyebrow"><i class="bi bi-grid-1x2-fill me-1" aria-hidden="true"></i>Overview</div>
            <h1 class="display-6 mb-2">Good day, <?= escape((string) (Auth::user()['name'] ?? 'there')) ?>.</h1>
            <p class="text-muted mb-0">Keep student records, identity cards, and verification in one clear workspace.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="student-form.php" class="btn btn-primary"><i class="bi bi-person-plus-fill me-1" aria-hidden="true"></i>Add student</a>
            <a href="students.php" class="btn btn-light border"><i class="bi bi-people-fill me-1" aria-hidden="true"></i>Open directory</a>
        </div>
    </div>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-warning" role="alert"><i class="bi bi-info-circle me-2" aria-hidden="true"></i><?= escape($error) ?></div>
    <?php endforeach; ?>

    <section class="row g-3 mb-4" aria-label="Key statistics">
        <div class="col-6 col-xl-3">
            <div class="ndc-stat-card">
                <div class="ndc-stat-icon ndc-stat-icon-blue"><i class="bi bi-people-fill" aria-hidden="true"></i></div>
                <div><span class="ndc-stat-label">All students</span><strong><?= number_format($stats['students']) ?></strong><small><?= number_format($stats['active_students']) ?> active</small></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="ndc-stat-card">
                <div class="ndc-stat-icon ndc-stat-icon-green"><i class="bi bi-person-vcard-fill" aria-hidden="true"></i></div>
                <div><span class="ndc-stat-label">Active cards</span><strong><?= number_format($stats['active_cards']) ?></strong><small>valid today</small></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="ndc-stat-card">
                <div class="ndc-stat-icon ndc-stat-icon-amber"><i class="bi bi-clock-history" aria-hidden="true"></i></div>
                <div><span class="ndc-stat-label">Expired cards</span><strong><?= number_format($stats['expired_cards']) ?></strong><small>need attention</small></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="ndc-stat-card <?= $stats['missing_surnames'] > 0 ? 'ndc-stat-card-alert' : '' ?>">
                <div class="ndc-stat-icon ndc-stat-icon-red"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i></div>
                <div><span class="ndc-stat-label">Data checks</span><strong><?= number_format($stats['missing_surnames']) ?></strong><small>missing surnames</small></div>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-lg-8">
            <section class="card ndc-panel h-100">
                <div class="card-body p-0">
                    <div class="ndc-panel-heading px-4 pt-4">
                        <div><h2 class="h5 mb-1">Recent student records</h2><p class="text-muted small mb-0">The latest profiles changed in the directory.</p></div>
                        <a href="students.php" class="btn btn-sm btn-light border">View all <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
                    </div>
                    <?php if ($recentStudents === []): ?>
                        <div class="p-4"><div class="alert alert-info mb-0">No student records are available yet.</div></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table ndc-modern-table align-middle">
                                <thead><tr><th>Student</th><th>Program</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($recentStudents as $student): ?>
                                    <?php $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')); ?>
                                    <tr>
                                        <td><a class="ndc-table-primary" href="student-profile.php?id=<?= (int) $student['id'] ?>"><?= escape($name !== '' ? $name : 'Unnamed student') ?></a><span class="d-block text-muted small"><?= escape((string) ($student['student_number'] ?? '')) ?></span></td>
                                        <td><?= escape((string) ($student['program'] ?? 'Not set')) ?></td>
                                        <td><span class="badge ndc-status-badge ndc-status-<?= strtolower((string) ($student['status'] ?? '')) === 'active' ? 'active' : 'inactive' ?>"><?= escape((string) ($student['status'] ?? 'Unknown')) ?></span></td>
                                        <td class="text-end"><a class="btn btn-sm btn-light border" href="student-profile.php?id=<?= (int) $student['id'] ?>" aria-label="Open <?= escape($name) ?>"><i class="bi bi-chevron-right" aria-hidden="true"></i></a></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="card ndc-panel h-100">
                <div class="card-body">
                    <h2 class="h5 mb-1">Quick actions</h2>
                    <p class="text-muted small mb-3">Common tasks, one click away.</p>
                    <div class="ndc-action-list">
                        <a href="student-form.php" class="ndc-action-item"><span class="ndc-action-icon"><i class="bi bi-person-plus-fill" aria-hidden="true"></i></span><span><strong>Add a student</strong><small>Create a new profile</small></span><i class="bi bi-chevron-right ms-auto" aria-hidden="true"></i></a>
                        <a href="students.php" class="ndc-action-item"><span class="ndc-action-icon"><i class="bi bi-file-earmark-arrow-down-fill" aria-hidden="true"></i></span><span><strong>Export ID cards</strong><small>PDF or PNG ZIP</small></span><i class="bi bi-chevron-right ms-auto" aria-hidden="true"></i></a>
                        <a href="verify.php" class="ndc-action-item"><span class="ndc-action-icon"><i class="bi bi-qr-code-scan" aria-hidden="true"></i></span><span><strong>Verify a card</strong><small>Check a QR or GUID</small></span><i class="bi bi-chevron-right ms-auto" aria-hidden="true"></i></a>
                        <?php if ((Auth::user()['role'] ?? '') === 'Administrator'): ?>
                            <a href="settings.php" class="ndc-action-item"><span class="ndc-action-icon"><i class="bi bi-sliders2" aria-hidden="true"></i></span><span><strong>Manage settings</strong><small>Branding and security</small></span><i class="bi bi-chevron-right ms-auto" aria-hidden="true"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
</body>
</html>
