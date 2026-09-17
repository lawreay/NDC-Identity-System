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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
</head>
<body class="ndc-login-page">
<main class="ndc-login-shell">
    <div class="row g-0">
        <div class="col-lg-6">
            <section class="ndc-login-aside">
                <div>
                    <div class="ndc-brand mb-5"><span class="ndc-brand-mark" aria-hidden="true"><i class="bi bi-person-vcard-fill"></i></span><span>NDC Identity System</span></div>
                    <div class="ndc-eyebrow text-white-50">Secure identity management</div>
                    <h1 class="mb-3">Everything your institution needs to issue with confidence.</h1>
                    <p class="mb-0">Manage student profiles, design professional ID cards, and verify credentials from one trusted workspace.</p>
                    <div class="ndc-login-bullets">
                        <div class="ndc-login-bullet"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Fast, accurate student records</span></div>
                        <div class="ndc-login-bullet"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Printable PDF and PNG ID cards</span></div>
                        <div class="ndc-login-bullet"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>QR-powered public verification</span></div>
                    </div>
                </div>
                <small class="text-white-50">Authorized staff only</small>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="ndc-login-form">
                    <div class="ndc-eyebrow"><i class="bi bi-shield-lock-fill me-1" aria-hidden="true"></i>Welcome back</div>
                    <h2 class="h3 mb-1">Sign in to your workspace</h2>
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
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-envelope" aria-hidden="true"></i></span>
                                <input id="email" type="email" name="email" class="form-control" required autocomplete="email" autofocus>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-key" aria-hidden="true"></i></span>
                                <input id="password" type="password" name="password" class="form-control" required autocomplete="current-password">
                                <button type="button" class="btn btn-outline-secondary" id="togglePassword" aria-label="Show password"><i class="bi bi-eye" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Sign in</button>
                    </form>

                    <div class="mt-3 text-center">
                        <a href="forgot-password.php">Forgot password?</a>
                    </div>
            </section>
        </div>
    </div>
</main>
<script>
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');
    if (passwordInput && togglePassword) {
        togglePassword.addEventListener('click', () => {
            const visible = passwordInput.type === 'text';
            passwordInput.type = visible ? 'password' : 'text';
            togglePassword.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
            togglePassword.querySelector('i').className = visible ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    }
</script>
</body>
</html>
