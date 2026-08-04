<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/StudentRepository.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

try {
    $repository = new StudentRepository(Database::getConnection());
    $student = $repository->findById($id);
    if ($student) {
        $studentNumber = $repository->generateStudentNumber((int) $student['id'], (string) ($student['first_name'] ?? ''), (string) ($student['last_name'] ?? ''));
        $student = $repository->findById($id);
        $student['student_number'] = $studentNumber;
    }
} catch (Throwable $exception) {
    $student = null;
}

if (!$student) {
    http_response_code(404);
    echo 'Student not found.';
    exit;
}

$fullName = trim(((string) ($student['first_name'] ?? '')) . ' ' . ((string) ($student['last_name'] ?? '')));
$photoPath = $student['photo_path'] ?? '';
$hasPhoto = is_string($photoPath) && trim($photoPath) !== '';
$photoUrl = $hasPhoto ? '/' . ltrim($photoPath, '/') : '';
$studentNumber = (string) ($student['student_number'] ?? '');
$program = (string) ($student['program'] ?? '');
$qualification = (string) ($student['qualification'] ?? '');
$classLevel = (string) ($student['class_level'] ?? '');
$status = (string) ($student['status'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student ID Preview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f4f7fb 0%, #eef3f8 100%);
            min-height: 100vh;
        }
        .id-card {
            width: 100%;
            max-width: 760px;
            margin: 0 auto;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16);
            border: 1px solid #d9e3ef;
            background: #ffffff;
        }
        .id-header {
            background: linear-gradient(90deg, #0f4c81 0%, #1d6fb8 100%);
            color: white;
            padding: 24px 28px;
        }
        .id-body {
            padding: 28px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }
        .student-photo {
            width: 140px;
            height: 170px;
            object-fit: cover;
            border-radius: 16px;
            border: 4px solid #eaf2fa;
            background: #f4f7fb;
        }
        .photo-placeholder {
            width: 140px;
            height: 170px;
            border-radius: 16px;
            border: 2px dashed #c7d7e8;
            background: #f8fbff;
            color: #6c757d;
        }
        .info-label {
            font-size: 0.78rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 0.2rem;
        }
        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: #12324c;
        }
        .chip {
            display: inline-block;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            background: #eaf5ff;
            color: #1565c0;
            font-weight: 600;
            font-size: 0.82rem;
        }
        .footer-bar {
            background: #f2f6fb;
            border-top: 1px solid #e3ebf4;
            padding: 16px 28px;
            color: #4b5b72;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">Student ID Preview</h1>
                <p class="text-muted mb-0">Professional preview only — no PDF or print output.</p>
            </div>
            <a href="student-profile.php?id=<?= (int) $id ?>" class="btn btn-outline-secondary">Back to profile</a>
        </div>

        <div class="id-card">
            <div class="id-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h4 mb-1">NDC Student ID</h2>
                        <p class="mb-0 opacity-75">Official student identification</p>
                    </div>
                    <span class="chip"><?= htmlspecialchars($status ?: 'Active', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>

            <div class="id-body">
                <div class="row g-4 align-items-center">
                    <div class="col-md-4 text-center">
                        <?php if ($hasPhoto): ?>
                            <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Student photo" class="student-photo">
                        <?php else: ?>
                            <div class="photo-placeholder d-flex flex-column justify-content-center align-items-center mx-auto">
                                <div class="display-6 mb-2">📷</div>
                                <small>No photo</small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-8">
                        <div class="mb-3">
                            <div class="info-label">Student Name</div>
                            <div class="h3 mb-0 text-dark"><?= htmlspecialchars($fullName ?: 'Student Name', ENT_QUOTES, 'UTF-8') ?></div>
                        </div>

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="info-label">Student Number</div>
                                <div class="info-value"><?= htmlspecialchars($studentNumber ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label">Program</div>
                                <div class="info-value"><?= htmlspecialchars($program ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label">Qualification</div>
                                <div class="info-value"><?= htmlspecialchars($qualification ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label">Class Level</div>
                                <div class="info-value"><?= htmlspecialchars($classLevel ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-bar d-flex justify-content-between align-items-center">
                <span>Issued by NDC</span>
                <span>Preview only</span>
            </div>
        </div>
    </div>
</body>
</html>
