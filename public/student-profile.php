<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/CardRepository.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/Services/ImageUploadService.php';

use App\Auth;
use App\Services\ImageUploadService;

Auth::requireLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$uploadMessage = '';
$uploadType = '';
$cardMessage = '';
$cardMessageType = '';
$card = null;
$cardRepository = null;

try {
    $repository = new StudentRepository(Database::getConnection());
    $cardRepository = new CardRepository(Database::getConnection());
    $student = $repository->findById($id);
    $errorMessage = null;
} catch (Throwable $exception) {
    $student = null;
    $errorMessage = $exception->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    try {
        Auth::requireCsrf();
    } catch (Throwable $csrfException) {
        $uploadMessage = 'Security token invalid. Please try again.';
        $uploadType = 'danger';
        $csrfException = null; // Use variable to avoid unused warning
    }
    
    $action = (string) ($_POST['action'] ?? 'upload_photo');
    if ($uploadMessage === '' && $action === 'revoke_card') {
        $currentUser = Auth::user();
        if (($currentUser['role'] ?? '') !== 'Administrator') {
            $cardMessage = 'Only administrators can revoke an ID card.';
            $cardMessageType = 'danger';
        } elseif (!$cardRepository instanceof CardRepository) {
            $cardMessage = 'Card verification is not available.';
            $cardMessageType = 'danger';
        } else {
            try {
                $issuedCard = $cardRepository->findLatestByStudentId($id);
                if (!$issuedCard || ($issuedCard['status'] ?? '') !== 'ACTIVE') {
                    $cardMessage = 'There is no active ID card to revoke.';
                    $cardMessageType = 'warning';
                } elseif ($cardRepository->revokeCard((string) $issuedCard['guid'])) {
                    $cardMessage = 'The ID card was revoked. Its verification QR will now report REVOKED.';
                    $cardMessageType = 'success';
                } else {
                    $cardMessage = 'Unable to revoke the ID card.';
                    $cardMessageType = 'danger';
                }
            } catch (Throwable $exception) {
                $cardMessage = $exception->getMessage();
                $cardMessageType = 'danger';
            }
        }
    } elseif ($uploadMessage === '') {
        $file = $_FILES['photo'] ?? [];
        $hasSelectedFile = is_array($file) && !empty($file['name']) && (($file['size'] ?? 0) > 0);

        if (!$hasSelectedFile) {
            $uploadMessage = 'Please choose a photo to upload.';
            $uploadType = 'warning';
        } else {
        $fileTooLarge = (int) ($file['size'] ?? 0) > 5 * 1024 * 1024;

        if ($fileTooLarge) {
            $uploadMessage = 'File size must not exceed 5 MB.';
            $uploadType = 'danger';
        } elseif (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $uploadMessage = 'The upload failed. Please try again.';
            $uploadType = 'danger';
        } else {
            try {
                $relativePath = (new ImageUploadService())->storeStudentPhoto($file, __DIR__ . '/uploads/student_photos', $id);
                $repository->updatePhoto($id, $relativePath);
                $uploadMessage = 'Photo uploaded successfully.';
                $uploadType = 'success';
                $student = $repository->findById($id);
            } catch (Throwable $exception) {
                $uploadMessage = $exception->getMessage();
                $uploadType = 'danger';
            }
        }
        }
    }
}

if ($student && $cardRepository instanceof CardRepository) {
    try {
        $card = $cardRepository->findLatestByStudentId($id);
    } catch (Throwable $exception) {
        $card = null;
    }
}

$photoPath = $student['photo_path'] ?? '';
$hasPhoto = is_string($photoPath) && trim($photoPath) !== '';
$photoUrl = $hasPhoto ? '/' . ltrim($photoPath, '/') : '';
$fullName = trim(((string) ($student['first_name'] ?? '')) . ' ' . ((string) ($student['last_name'] ?? '')));
$notice = '';
if (isset($_GET['created'])) {
    $notice = 'Student created successfully.';
} elseif (isset($_GET['updated'])) {
    $notice = 'Student profile updated successfully.';
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/photo-upload-editor.css">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/header.php'; ?>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="students.php" class="btn btn-outline-secondary btn-sm">← Back to students</a>
            
            <?php if ($student && $student !== null): ?>
                <div class="btn-group" role="group">
                    <a href="student-form.php?id=<?= (int) ($student['id'] ?? 0) ?>" class="btn btn-outline-secondary btn-sm">
                        Edit Profile
                    </a>
                    <a href="student-id-card.php?id=<?= (int) ($student['id'] ?? 0) ?>" class="btn btn-outline-primary btn-sm">
                        Preview Card
                    </a>
                    
                    <form method="post" action="export-card.php" class="js-export-form" style="display:inline;">
                        <input type="hidden" name="student_id" value="<?= (int) ($student['id'] ?? 0) ?>">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-primary btn-sm js-export-button" title="Export student ID card as PDF">
                            Export PDF
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($notice !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif (!$student): ?>
            <div class="alert alert-warning">Student not found.</div>
        <?php else: ?>
            <?php if ($uploadMessage !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($uploadType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($uploadMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($cardMessage !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($cardMessageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cardMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-4 align-items-start">
                        <div class="col-md-4">
                            <?php if ($hasPhoto): ?>
                                <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Student photo" class="img-fluid rounded border" style="max-height: 320px; object-fit: cover; width: 100%;">
                            <?php else: ?>
                                <div class="border rounded d-flex flex-column justify-content-center align-items-center text-center p-4 bg-light" style="min-height: 280px;">
                                    <div class="display-6 text-muted mb-2"></div>
                                    <h5 class="mb-2">No photo available</h5>
                                    <p class="text-muted mb-0">Upload a photo to add one here.</p>
                                </div>
                            <?php endif; ?>

                            <form method="post" enctype="multipart/form-data" class="mt-3">
                                <input type="hidden" name="id" value="<?= (int) ($student['id'] ?? 0) ?>">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <label class="form-label" for="profilePhoto">Upload photo</label>
                                <input id="profilePhoto" type="file" name="photo" accept="image/png,image/jpeg,image/webp" class="form-control" data-photo-input>
                                <div class="photo-upload-editor mt-3" data-photo-editor data-input-id="profilePhoto" hidden>
                                    <div class="photo-upload-stage border rounded" data-photo-stage>
                                        <img data-photo-source alt="Selected student photo">
                                    </div>
                                    <div class="form-text mt-2">Drag the photo to position it. Drag the blue border to move the crop area, or drag a blue corner to resize it.</div>
                                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Photo framing mode">
                                            <input id="profilePhotoCrop" class="btn-check" type="radio" name="profile_photo_mode" value="crop" data-photo-mode checked>
                                            <label class="btn btn-outline-secondary" for="profilePhotoCrop">Crop</label>
                                            <input id="profilePhotoFit" class="btn-check" type="radio" name="profile_photo_mode" value="fit" data-photo-mode>
                                            <label class="btn btn-outline-secondary" for="profilePhotoFit">Fit on white</label>
                                        </div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-photo-reset>Reset</button>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mt-2">
                                        <label class="small text-muted" for="profilePhotoZoom">Image size</label>
                                        <input id="profilePhotoZoom" class="form-range m-0" type="range" data-photo-zoom min="1" max="3" step="0.01" value="1">
                                    </div>
                                </div>
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
                            <div class="border-top mt-4 pt-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <div class="text-muted small">Latest ID card</div>
                                        <?php if ($card): ?>
                                            <div class="fw-semibold"><?= htmlspecialchars((string) ($card['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · Expires <?= htmlspecialchars((string) ($card['expires_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="small text-muted font-monospace mt-1"><?= htmlspecialchars((string) ($card['guid'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php else: ?>
                                            <div class="fw-semibold">No card issued yet</div>
                                            <div class="small text-muted">Opening the card preview or exporting it issues the card.</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($card && ($card['status'] ?? '') === 'ACTIVE' && (Auth::user()['role'] ?? '') === 'Administrator'): ?>
                                        <form method="post" onsubmit="return confirm('Revoke this ID card? Its QR code will immediately report that it is revoked.');">
                                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="revoke_card">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Revoke ID card</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<script src="assets/photo-upload-editor.js"></script>
<script>
    document.querySelectorAll('.js-export-form').forEach(form => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('.js-export-button');
            if (!button) {
                return;
            }

            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span>Preparing...</span>';
        });
    });
</script>
</body>
</html>
