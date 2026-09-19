<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/DatabaseDumpService.php';

use App\Auth;
use App\DatabaseDumpService;

Auth::requireRole('Administrator');

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Auth::requireCsrf();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'download_database_dump') {
            $dumpPath = (new DatabaseDumpService(Database::getConnection()))->createTemporaryDump();

            try {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }

                $filename = 'ndc_identity_database_' . date('Y-m-d_H-i-s') . '.sql';
                header('Content-Type: application/sql; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . (string) filesize($dumpPath));
                header('Cache-Control: no-store, private');
                header('X-Content-Type-Options: nosniff');
                readfile($dumpPath);
            } finally {
                @unlink($dumpPath);
            }

            exit;
        }

        if ($action === 'import_database_dump') {
            if ((string) ($_POST['confirm_database_import'] ?? '') !== '1') {
                throw new RuntimeException('Confirm that the import will replace the current database before continuing.');
            }

            $count = (new DatabaseDumpService(Database::getConnection()))->importUploadedDump($_FILES['database_dump'] ?? []);
            $success = 'Database import complete. ' . $count . ' SQL statements were applied.';
        }
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
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
    <title>Data Backup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/header.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Data Backup</h1>
            <p class="text-muted mb-0">Download or restore the full application database.</p>
        </div>
        <a href="settings.php" class="btn btn-outline-secondary">Back to settings</a>
    </div>

    <div class="card shadow-sm mb-4 ndc-section-nav">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <a href="settings.php" class="btn btn-outline-secondary">App Settings</a>
                <a href="admin-users.php" class="btn btn-outline-secondary">Admin Users</a>
                <a href="data-backup.php" class="btn btn-primary">Data Backup</a>
                <a href="app-update.php" class="btn btn-outline-secondary">Updates</a>
                <a href="template-designer.php" class="btn btn-outline-secondary">Template Designer</a>
            </div>
        </div>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= escape($success) ?></div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= escape((string) $error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <form method="post" class="card shadow-sm h-100" onsubmit="return confirm('Download a complete database backup? The SQL file contains sensitive student and user data.');">
                <div class="card-body">
                    <h2 class="h5 mb-2"><i class="bi bi-database-down me-1" aria-hidden="true"></i>Download Backup</h2>
                    <p class="text-muted">Creates a full SQL backup of students, ID cards, academic records, users, settings, templates metadata, and every database table.</p>
                    <input type="hidden" name="_csrf" value="<?= escape(Auth::csrfToken()) ?>">
                    <input type="hidden" name="action" value="download_database_dump">
                    <button type="submit" class="btn btn-primary">Download SQL Backup</button>
                </div>
                <div class="card-footer bg-white small text-warning-emphasis">
                    Keep this file private. It may contain personal student and user data.
                </div>
            </form>
        </div>

        <div class="col-lg-6">
            <form method="post" enctype="multipart/form-data" class="card shadow-sm h-100" onsubmit="return confirm('Restore this SQL file into the current NDC database? Existing data may be replaced and this cannot be undone.');">
                <div class="card-body">
                    <h2 class="h5 mb-2 text-danger"><i class="bi bi-database-up me-1" aria-hidden="true"></i>Restore Backup</h2>
                    <p class="text-muted">Use this only with a trusted SQL backup from this system. Take a fresh backup before restoring.</p>
                    <input type="hidden" name="_csrf" value="<?= escape(Auth::csrfToken()) ?>">
                    <input type="hidden" name="action" value="import_database_dump">
                    <div class="mb-3">
                        <label for="database_dump" class="form-label">SQL backup file</label>
                        <input id="database_dump" type="file" name="database_dump" class="form-control" accept=".sql,application/sql,text/plain" required>
                    </div>
                    <div class="form-check mb-3">
                        <input id="confirm_database_import" class="form-check-input" type="checkbox" name="confirm_database_import" value="1" required>
                        <label class="form-check-label" for="confirm_database_import">I understand this can replace current data.</label>
                    </div>
                    <button type="submit" class="btn btn-danger">Restore SQL Backup</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
