<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';

use App\Auth;

Auth::boot();

$errors = [];
$next = trim((string) ($_GET['next'] ?? 'students.php'));

// Validate redirect target to prevent open redirect attacks
function isValidRedirectTarget(string $target): bool
{
    if ($target === '') {
        return true;
    }
    
    // Allow only relative URLs that start with / or are simple filenames
    // Prevent protocol-based redirects (http://, https://, //, etc.)
    if (preg_match('/^\//', $target) || preg_match('/^[a-zA-Z0-9._-]+\.php(\?.*)?$/', $target)) {
        return !preg_match('/[\/\\\\:]/', $target) || preg_match('/^\/[^\/]/', $target);
    }
    
    return false;
}

// Sanitize redirect target
$next = isValidRedirectTarget($next) ? $next : 'students.php';

if (Auth::isAuthenticated()) {
    $target = $next !== '' ? $next : 'students.php';
    header('Location: ' . $target);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Auth::requireCsrf();
        Auth::login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        $target = trim((string) ($_POST['next'] ?? ''));
        $target = isValidRedirectTarget($target) ? $target : $next;
        header('Location: ' . ($target !== '' ? $target : 'students.php'));
        exit;
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
    <title>Sign in</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 mb-1">Sign in</h1>
                    <p class="text-muted mb-4">Use your existing account credentials to continue.</p>

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
                        <input type="hidden" name="next" value="<?= escape($next) ?>">
                        <input type="hidden" name="_csrf" value="<?= escape(Auth::csrfToken()) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="email">Email</label>
                            <input id="email" type="email" name="email" class="form-control" required autocomplete="email" autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input id="password" type="password" name="password" class="form-control" required autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Sign in</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
