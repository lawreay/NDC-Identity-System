<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/StudentRepository.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$uploadMessage = '';
$uploadType = '';

try {
    $repository = new StudentRepository(Database::getConnection());
    $student = $repository->findById($id);
    $errorMessage = null;
} catch (Throwable $exception) {
    $student = null;
    $errorMessage = $exception->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    if (isset($_FILES['photo']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
        $file = $_FILES['photo'];

        if (!in_array($file['type'], $allowedTypes, true)) {
            $uploadMessage = 'Only PNG, JPG, and WebP images are allowed.';
            $uploadType = 'danger';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadMessage = 'The upload failed. Please try again.';
            $uploadType = 'danger';
        } else {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'student_' . $id . '_' . time() . '.' . strtolower($extension);
            $destination = __DIR__ . '/uploads/student_photos/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $uploadMessage = 'The file could not be saved.';
                $uploadType = 'danger';
            } else {
                $relativePath = 'uploads/student_photos/' . $filename;
                $repository->updatePhoto($id, $relativePath);
                $uploadMessage = 'Photo uploaded successfully.';
                $uploadType = 'success';
                $student = $repository->findById($id);
            }
        }
    } else {
        $uploadMessage = 'Please choose a photo to upload.';
        $uploadType = 'warning';
    }
}

$photoPath = $student['photo_path'] ?? '';
$hasPhoto = is_string($photoPath) && trim($photoPath) !== '';
$photoUrl = $hasPhoto ? '/' . ltrim($photoPath, '/') : '';
$fullName = trim(((string) ($student['first_name'] ?? '')) . ' ' . ((string) ($student['last_name'] ?? '')));

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <a href="students.php" class="btn btn-outline-secondary btn-sm mb-4">← Back to students</a>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif (!$student): ?>
            <div class="alert alert-warning">Student not found.</div>
        <?php else: ?>
            <?php if ($uploadMessage !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($uploadType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($uploadMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-4 align-items-start">
                        <div class="col-md-4">
                            <?php if ($hasPhoto): ?>
                                <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Student photo" class="img-fluid rounded border" style="max-height: 320px; object-fit: cover; width: 100%;">
                            <?php else: ?>
                                <div class="border rounded d-flex flex-column justify-content-center align-items-center text-center p-4 bg-light" style="min-height: 280px;">
                                    <div class="display-6 text-muted mb-2">📷</div>
                                    <h5 class="mb-2">No photo available</h5>
                                    <p class="text-muted mb-0">Upload a photo to add one here.</p>
                                </div>
                            <?php endif; ?>

                            <form method="post" enctype="multipart/form-data" class="mt-3">
                                <input type="hidden" name="id" value="<?= (int) ($student['id'] ?? 0) ?>">
                                <label class="form-label">Upload photo</label>
                                <input type="file" name="photo" accept="image/png,image/jpeg,image/jpg,image/webp" class="form-control">
                                <button type="submit" class="btn btn-primary mt-2">Save photo</button>
                            </form>
                        </div>
                        <div class="col-md-8">
                            <h1 class="h3 mb-3"><?= htmlspecialchars($fullName ?: 'Student profile', ENT_QUOTES, 'UTF-8') ?></h1>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="text-muted small">Student number</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['student_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Status</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Gender</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['gender'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Date of birth</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['date_of_birth'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Program</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['program'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Qualification</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['qualification'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Class level</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['class_level'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Billing category</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['billing_category'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Phone number</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['phone_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">District</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['district'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Traditional authority</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['traditional_authority'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Village</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['village'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
