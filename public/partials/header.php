<?php
require_once __DIR__ . '/../../app/Auth.php';

use App\Auth;

$user = Auth::user();
$userName = (string) ($user['name'] ?? 'User');
$userRole = (string) ($user['role'] ?? '');
?><nav class="navbar navbar-expand-lg navbar-dark ndc-navbar mb-4">
    <div class="container">
        <a class="navbar-brand ndc-brand" href="students.php">
            <span class="ndc-brand-mark" aria-hidden="true"><i class="bi bi-person-vcard-fill"></i></span>
            <span>NDC Identity System</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ndcNavigation" aria-controls="ndcNavigation" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="ndcNavigation">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 ms-lg-auto mt-3 mt-lg-0">
                <span class="navbar-text me-lg-2"><i class="bi bi-person-circle me-1" aria-hidden="true"></i>Hello, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($userRole === 'Administrator'): ?>
                <a class="btn btn-outline-light btn-sm" href="settings.php"><i class="bi bi-gear me-1" aria-hidden="true"></i>Settings</a>
                <a class="btn btn-outline-light btn-sm" href="template-designer.php"><i class="bi bi-palette me-1" aria-hidden="true"></i>Designer</a>
            <?php endif; ?>
                <a class="btn btn-outline-light btn-sm" href="logout.php"><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Logout</a>
            </div>
        </div>
    </div>
</nav>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
