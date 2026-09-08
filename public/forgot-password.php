<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/SettingsRepository.php';
require_once __DIR__ . '/../app/PasswordResetService.php';

use App\Auth;
use App\PasswordResetService;

Auth::boot();

$errors = [];
$success = '';
$email = trim((string) ($_POST['email'] ?? ''));

try {
    $pdo = Database::getConnection();
    $settings = (new SettingsRepository($pdo))->getAll();
} catch (Throwable $exception) {
    $pdo = null;
    $settings = [];
    $errors[] = 'Unable to connect to the database.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
    try {
        Auth::requireCsrf();
        (new PasswordResetService($pdo, $settings))->requestReset($email);
        $success = 'If an active account exists for that email, a password reset link has been sent.';
        $email = '';
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
    <title>Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 mb-1">Forgot password</h1>
                    <p class="text-muted mb-4">Enter your account email and we will send a reset link.</p>

                    <?php if ($success !== ''): ?>
                        <div class="alert alert-success"><?= escape($success) ?></div>
                    <?php endif; ?>

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
                        <div class="mb-3">
                            <label class="form-label" for="email">Email</label>
                            <input id="email" type="email" name="email" class="form-control" value="<?= escape($email) ?>" required autocomplete="email" autofocus>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Send reset link</button>
                    </form>

                    <div class="mt-3 text-center">
                        <a href="login.php">Back to sign in</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
