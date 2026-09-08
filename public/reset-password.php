<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/PasswordResetService.php';

use App\Auth;
use App\PasswordResetService;

Auth::boot();

$errors = [];
$success = '';
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));

try {
    $pdo = Database::getConnection();
} catch (Throwable $exception) {
    $pdo = null;
    $errors[] = 'Unable to connect to the database.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
    try {
        Auth::requireCsrf();
        (new PasswordResetService($pdo))->resetPassword(
            $token,
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password_confirmation'] ?? '')
        );
        $success = 'Your password has been reset. You can now sign in.';
        $token = '';
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
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 mb-1">Reset password</h1>
                    <p class="text-muted mb-4">Choose a new password for your account.</p>

                    <?php if ($success !== ''): ?>
                        <div class="alert alert-success"><?= escape($success) ?></div>
                        <a href="login.php" class="btn btn-primary w-100">Back to sign in</a>
                    <?php else: ?>
                        <?php if ($errors !== []): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= escape($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post">
                            <input type="hidden" name="_csrf" value="<?= escape(Auth::csrfToken()) ?>">
                            <input type="hidden" name="token" value="<?= escape($token) ?>">
                            <div class="mb-3">
                                <label class="form-label" for="password">New Password</label>
                                <input id="password" type="password" name="password" class="form-control" required autocomplete="new-password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="password_confirmation">Confirm New Password</label>
                                <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Reset password</button>
                        </form>

                        <div class="mt-3 text-center">
                            <a href="login.php">Back to sign in</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
