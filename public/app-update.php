<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/AppUpdateService.php';
require_once __DIR__ . '/../app/DatabaseDumpService.php';

use App\AppUpdateService;
use App\Auth;
use App\DatabaseDumpService;

Auth::requireRole('Administrator');

$service = new AppUpdateService();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Auth::requireCsrf();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'upload') {
            $service->storeUploadedPackage($_FILES['update_package'] ?? []);
            $success = 'Update package uploaded.';
        } elseif ($action === 'delete') {
            $packageName = (string) ($_POST['package'] ?? '');
            $service->deleteIncomingPackage($packageName);
            $success = 'Update package deleted.';
        } elseif ($action === 'apply') {
            $pending = $service->applyPackage((string) ($_POST['package'] ?? ''));
            $success = 'Update applied. Confirm it before ' . date('H:i:s', (int) $pending['expires_at']) . ' or it will roll back automatically.';
        } elseif ($action === 'confirm') {
            $service->confirmPendingUpdate();
            $success = 'Update confirmed.';
        } elseif ($action === 'rollback') {
            $service->rollbackPendingUpdate('Update rolled back manually.');
            $success = 'Update rolled back.';
        } elseif ($action === 'download_database_dump') {
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
        } elseif ($action === 'import_database_dump') {
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

$pending = $service->pendingUpdate();
$packages = $service->listIncomingPackages();
$incomingPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'updates' . DIRECTORY_SEPARATOR . 'incoming';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatBytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / 1024 / 1024, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' B';
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Updates</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/header.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">App Updates</h1>
            <p class="text-muted mb-0">Upload a ZIP, apply it, then confirm within 10 minutes.</p>
        </div>
        <a href="settings.php" class="btn btn-outline-secondary">Back to settings</a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <a href="settings.php" class="btn btn-outline-secondary">App Settings</a>
                <a href="admin-users.php" class="btn btn-outline-secondary">Admin Users</a>
                <a href="app-update.php" class="btn btn-primary">Updates</a>
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

    <?php if ($pending !== null): ?>
        <?php $remaining = max(0, (int) ($pending['expires_at'] ?? 0) - time()); ?>
        <div class="alert alert-warning">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    <strong>Update waiting for confirmation.</strong>
                    <div>Package: <?= escape((string) ($pending['package'] ?? 'Unknown')) ?></div>
                    <div>Rollback in <span id="rollbackCountdown" data-seconds="<?= $remaining ?>"><?= $remaining ?></span> seconds if not confirmed.</div>
                </div>
                <div class="ms-auto d-flex gap-2">
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?= escape(Auth::csrfToken()) ?>">
                        <input type="hidden" name="action" value="confirm">
                        <button type="submit" class="btn btn-success">Confirm Update</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Roll back to the previous version now?');">
                        <input type="hidden" name="_csrf" value="<?= escape(Auth::csrfToken()) ?>">
                        <input type="hidden" name="action" value="rollback">
                        <button type="submit" class="btn btn-outline-danger">Roll Back Now</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <form method="post" enctype="multipart/form-data" class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Upload ZIP</h2>
                    <input type="hidden" name="_csrf" value="<?= escape(Auth::csrfToken()) ?>">
                    <input type="hidden" name="action" value="upload">
                    <div class="mb-3">
                        <label class="form-label" for="update_package">Update package</label>
                        <input id="update_package" type="file" name="update_package" class="form-control" accept=".zip,application/zip" required>
                    </div>
                    <p class="text-muted small mb-0">You can also drop ZIP files into <?= escape($incomingPath) ?> and refresh this page.</p>
                </div>
                <div class="card-footer bg-white text-end">
                    <button type="submit" class="btn btn-primary">Upload Package</button>
                </div>
            </form>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Available Packages</h2>
                    <?php if ($packages === []): ?>
                        <div class="alert alert-info mb-0">No ZIP packages found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Package</th>
                                        <th>Size</th>
                                        <th>Modified</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($packages as $package): ?>
                                        <tr>
                                            <td><?= escape($package['name']) ?></td>
                                            <td><?= escape(formatBytes($package['size'])) ?></td>
                                            <td><?= escape(date('Y-m-d H:i:s', $package['modified_at'])) ?></td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-1">
                                                    <form method="post" onsubmit="return confirm('Apply this update package now? A backup will be created first.');">
                                                        <input type="hidden" name="_csrf" value="<?= escape(Auth::csrfToken()) ?>">
                                                        <input type="hidden" name="action" value="apply">
                                                        <input type="hidden" name="package" value="<?= escape($package['name']) ?>">
                                                        <button type="submit" class="btn btn-outline-primary btn-sm" <?= $pending !== null ? 'disabled' : '' ?>>Apply</button>
                                                    </form>
                                                    <form method="post" onsubmit="return confirm('Delete this update package permanently?');">
                                                        <input type="hidden" name="_csrf" value="<?= escape(Auth::csrfToken()) ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="package" value="<?= escape($package['name']) ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    <h2 class="h5 mb-1">Database Backup</h2>
                    <p class="text-muted mb-0">Download a full SQL backup of the current database before making major changes or restoring data.</p>
                </div>
                <form method="post" class="ms-md-auto" onsubmit="return confirm('Download a complete database backup? The SQL file contains sensitive student and user data.');">
                    <input type="hidden" name="_csrf" value="<?= escape(Auth::csrfToken()) ?>">
                    <input type="hidden" name="action" value="download_database_dump">
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-database-down me-1"></i>Download SQL Backup</button>
                </form>
            </div>
            <p class="small text-warning-emphasis mb-0 mt-3"><i class="bi bi-shield-lock me-1"></i>Keep backup files private. Imports replace tables and data in the current application database.</p>

            <hr class="my-4">

            <form method="post" enctype="multipart/form-data" onsubmit="return confirm('Restore this SQL file into the current NDC database? Existing tables and data can be replaced and this cannot be undone.');">
                <h3 class="h6 text-danger">Restore Database from SQL</h3>
                <p class="small text-muted">Use a SQL backup for this NDC Identity System. Take a fresh backup first. If the file is invalid, its changes may be only partially applied.</p>
                <input type="hidden" name="_csrf" value="<?= escape(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="import_database_dump">
                <div class="row g-3 align-items-end">
                    <div class="col-md-7">
                        <label for="database_dump" class="form-label">SQL backup file</label>
                        <input id="database_dump" type="file" name="database_dump" class="form-control" accept=".sql,application/sql,text/plain" required>
                    </div>
                    <div class="col-md-5">
                        <div class="form-check mb-2">
                            <input id="confirm_database_import" class="form-check-input" type="checkbox" name="confirm_database_import" value="1" required>
                            <label class="form-check-label" for="confirm_database_import">I understand this replaces current data.</label>
                        </div>
                        <button type="submit" class="btn btn-danger"><i class="bi bi-database-up me-1"></i>Restore SQL Backup</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Protected Paths</h2>
            <p class="text-muted mb-2">Updates do not overwrite runtime data or local configuration.</p>
            <div class="row g-2 small">
                <div class="col-md-4"><code>.env</code></div>
                <div class="col-md-4"><code>storage/updates</code></div>
                <div class="col-md-4"><code>storage/logs</code></div>
                <div class="col-md-4"><code>storage/templates</code></div>
                <div class="col-md-4"><code>public/uploads</code></div>
                <div class="col-md-4"><code>.git</code></div>
            </div>
        </div>
    </div>
</div>
<script>
    const countdown = document.getElementById('rollbackCountdown');
    if (countdown) {
        let seconds = Number.parseInt(countdown.dataset.seconds || '0', 10);
        window.setInterval(() => {
            seconds = Math.max(0, seconds - 1);
            countdown.textContent = String(seconds);
        }, 1000);
    }
</script>
</body>
</html>
