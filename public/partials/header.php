<?php
require_once __DIR__ . '/../../app/Auth.php';

use App\Auth;

$user = Auth::user();
$userName = (string) ($user['name'] ?? 'User');
$userRole = (string) ($user['role'] ?? '');
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$studentPages = ['students.php', 'student-profile.php', 'student-form.php', 'student-id-card.php'];
?><nav class="navbar navbar-expand-lg navbar-dark ndc-navbar mb-4">
    <div class="container">
        <a class="navbar-brand ndc-brand" href="dashboard.php">
            <span class="ndc-brand-mark" aria-hidden="true"><i class="bi bi-person-vcard-fill"></i></span>
            <span>NDC Identity System</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ndcNavigation" aria-controls="ndcNavigation" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="ndcNavigation">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-1 ms-lg-4 me-lg-auto mt-3 mt-lg-0">
                <a class="ndc-nav-link" href="dashboard.php" <?= $currentPage === 'dashboard.php' || $currentPage === 'index.php' ? 'aria-current="page"' : '' ?>><i class="bi bi-grid-1x2-fill me-1" aria-hidden="true"></i>Dashboard</a>
                <a class="ndc-nav-link" href="students.php" <?= in_array($currentPage, $studentPages, true) ? 'aria-current="page"' : '' ?>><i class="bi bi-people-fill me-1" aria-hidden="true"></i>Students</a>
                <?php if ($userRole === 'Administrator'): ?>
                    <a class="ndc-nav-link" href="settings.php" <?= $currentPage === 'settings.php' || $currentPage === 'admin-users.php' || $currentPage === 'app-update.php' ? 'aria-current="page"' : '' ?>><i class="bi bi-sliders2 me-1" aria-hidden="true"></i>Settings</a>
                <?php endif; ?>
            </div>
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 mt-3 mt-lg-0">
                <span class="ndc-user-chip"><i class="bi bi-person-circle me-1" aria-hidden="true"></i><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($userRole === 'Administrator'): ?>
                    <a class="btn btn-outline-light btn-sm" href="template-designer.php"><i class="bi bi-palette me-1" aria-hidden="true"></i>Designer</a>
                <?php endif; ?>
                <a class="btn btn-light btn-sm ndc-logout-btn" href="logout.php"><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Sign out</a>
            </div>
        </div>
    </div>
</nav>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
