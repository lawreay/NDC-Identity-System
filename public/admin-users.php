<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/UserRepository.php';

use App\Auth;
use App\UserRepository;

Auth::requireRole('Administrator');

$repository = new UserRepository(Database::getConnection());
$errors = [];
$success = '';
$form = [
    'name' => '',
    'email' => '',
    'role' => 'Administrator',
    'is_active' => '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Auth::requireCsrf();
    } catch (Throwable $exception) {
        $errors[] = 'Security token invalid. Please try again.';
    }

    $form['name'] = trim((string) ($_POST['name'] ?? ''));
    $form['email'] = strtolower(trim((string) ($_POST['email'] ?? '')));
    $form['role'] = trim((string) ($_POST['role'] ?? 'Administrator'));
    $form['is_active'] = isset($_POST['is_active']) ? '1' : '0';
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($form['name'] === '') {
        $errors[] = 'Name is required.';
    }
    if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    } elseif ($repository->emailExists($form['email'])) {
        $errors[] = 'That email address is already in use.';
    }
    if (!in_array($form['role'], ['Administrator', 'Staff'], true)) {
        $errors[] = 'Please choose a valid role.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Password and confirmation do not match.';
    }

    if ($errors === []) {
        try {
            $repository->create([
                'name' => $form['name'],
                'email' => $form['email'],
                'password_hash' => Auth::hashPassword($password),
                'role' => $form['role'],
                'is_active' => $form['is_active'] === '1',
            ]);
            $success = 'Admin user created successfully.';
            $form = [
                'name' => '',
                'email' => '',
                'role' => 'Administrator',
                'is_active' => '1',
            ];
        } catch (Throwable $exception) {
            $errors[] = 'Unable to create user: ' . $exception->getMessage();
        }
    }
}

try {
    $users = $repository->listUsers();
} catch (Throwable $exception) {
    $users = [];
    $errors[] = 'Unable to load users: ' . $exception->getMessage();
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
    <title>Admin Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/header.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Admin Users</h1>
            <p class="text-muted mb-0">Create administrator accounts for trusted staff.</p>
        </div>
        <a href="settings.php" class="btn btn-outline-secondary">Back to settings</a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <a href="settings.php" class="btn btn-outline-secondary">App Settings</a>
                <a href="admin-users.php" class="btn btn-primary">Admin Users</a>
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
        <div class="col-lg-5">
            <form method="post" class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Create User</h2>
                    <input type="hidden" name="_csrf" value="<?= escape(Auth::csrfToken()) ?>">

                    <div class="mb-3">
                        <label class="form-label" for="name">Name</label>
                        <input id="name" type="text" name="name" class="form-control" value="<?= escape($form['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input id="email" type="email" name="email" class="form-control" value="<?= escape($form['email']) ?>" required autocomplete="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="role">Role</label>
                        <select id="role" name="role" class="form-select">
                            <?php foreach (['Administrator', 'Staff'] as $role): ?>
                                <option value="<?= escape($role) ?>" <?= $form['role'] === $role ? 'selected' : '' ?>><?= escape($role) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input id="password" type="password" name="password" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">Confirm password</label>
                        <input id="confirm_password" type="password" name="confirm_password" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="form-check">
                        <input id="is_active" type="checkbox" name="is_active" value="1" class="form-check-input" <?= $form['is_active'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active user</label>
                    </div>
                </div>
                <div class="card-footer bg-white text-end">
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Existing Users</h2>
                    <?php if ($users === []): ?>
                        <div class="alert alert-info mb-0">No users found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?= escape((string) ($user['name'] ?? '')) ?></td>
                                            <td><?= escape((string) ($user['email'] ?? '')) ?></td>
                                            <td><?= escape((string) ($user['role'] ?? '')) ?></td>
                                            <td>
                                                <span class="badge <?= ((int) ($user['is_active'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                                    <?= ((int) ($user['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive' ?>
                                                </span>
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
</div>
</body>
</html>
