<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/AppUpdateService.php';

use App\AppUpdateService;
use App\Auth;

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
