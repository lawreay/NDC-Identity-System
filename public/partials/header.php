<?php
require_once __DIR__ . '/../../app/Auth.php';

use App\Auth;

$user = Auth::user();
$userName = (string) ($user['name'] ?? 'User');
$userRole = (string) ($user['role'] ?? '');
?><nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="students.php">NDC Identity System</a>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <span class="navbar-text text-light">Hello, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($userRole === 'Administrator'): ?>
                <a class="btn btn-outline-light btn-sm" href="template-designer.php">Designer</a>
            <?php endif; ?>
            <a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a>
        </div>
    </div>
</nav>
