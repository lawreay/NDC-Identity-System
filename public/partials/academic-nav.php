<div class="card shadow-sm mb-4 ndc-section-nav">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="academic-dashboard.php" class="btn <?= basename((string) ($_SERVER['PHP_SELF'] ?? '')) === 'academic-dashboard.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">Dashboard</a>
            <a href="academic-programmes.php" class="btn <?= basename((string) ($_SERVER['PHP_SELF'] ?? '')) === 'academic-programmes.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">Programmes</a>
            <a href="academic-courses.php" class="btn <?= basename((string) ($_SERVER['PHP_SELF'] ?? '')) === 'academic-courses.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">Courses</a>
            <a href="academic-terms.php" class="btn <?= basename((string) ($_SERVER['PHP_SELF'] ?? '')) === 'academic-terms.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">Terms</a>
            <a href="academic-enrolments.php" class="btn <?= basename((string) ($_SERVER['PHP_SELF'] ?? '')) === 'academic-enrolments.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">Enrolments</a>
            <a href="academic-results.php" class="btn <?= basename((string) ($_SERVER['PHP_SELF'] ?? '')) === 'academic-results.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">Results</a>
            <a href="academic-checksheet.php" class="btn <?= basename((string) ($_SERVER['PHP_SELF'] ?? '')) === 'academic-checksheet.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">Checksheet</a>
            <a href="academic-eligibility.php" class="btn <?= basename((string) ($_SERVER['PHP_SELF'] ?? '')) === 'academic-eligibility.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">Eligibility</a>
            <a href="academic-certificates.php" class="btn <?= basename((string) ($_SERVER['PHP_SELF'] ?? '')) === 'academic-certificates.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">Certificates</a>
            <a href="academic-completions.php" class="btn <?= basename((string) ($_SERVER['PHP_SELF'] ?? '')) === 'academic-completions.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">Completions</a>
        </div>
    </div>
</div>
