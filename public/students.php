<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/StudentRepository.php';

$search = trim($_GET['search'] ?? '');

try {
    $repository = new StudentRepository(Database::getConnection());
    $students = $repository->search($search);
    $errorMessage = null;
} catch (Throwable $exception) {
    $students = [];
    $errorMessage = $exception->getMessage();
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">Student List</h1>
                <p class="text-muted mb-0">Browse students and open their profile.</p>
            </div>
            <a href="template-designer.php" class="btn btn-outline-primary">Template Designer</a>
        </div>

        <form method="get" action="students.php" class="row g-2 mb-4">
            <div class="col-md-8">
                <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search by name, number, program, or class">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Search</button>
            </div>
            <div class="col-md-2">
                <a href="students.php" class="btn btn-outline-secondary w-100">Clear</a>
            </div>
        </form>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($students === []): ?>
            <div class="alert alert-info">No students found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle bg-white shadow-sm rounded">
                    <thead class="table-dark">
                        <tr>
                            <th>Student #</th>
                            <th>Name</th>
                            <th>Program</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($student['student_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($student['program'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($student['class_level'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($student['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <a href="student-profile.php?id=<?= (int) ($student['id'] ?? 0) ?>" class="btn btn-outline-primary btn-sm">Open profile</a>
                                    <a href="student-id-card.php?id=<?= (int) ($student['id'] ?? 0) ?>" class="btn btn-outline-secondary btn-sm ms-1">Preview ID</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
