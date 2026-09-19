<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/CardRepository.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/Services/ImageUploadService.php';
require_once __DIR__ . '/../app/TemplateDesigner/TemplateDesignerService.php';
require_once __DIR__ . '/../app/StudentEnrolmentRepository.php';

use App\Auth;
use App\Services\ImageUploadService;

Auth::requireLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$uploadMessage = '';
$uploadType = '';
$cardMessage = '';
$cardMessageType = '';
$profileMessage = '';
$profileMessageType = '';
$card = null;
$cardRepository = null;
$templates = [];
$defaultTemplateId = '';
$academicEnrolments = [];

try {
    $repository = new StudentRepository(Database::getConnection());
    $cardRepository = new CardRepository(Database::getConnection());
    $student = $repository->findById($id);
    $errorMessage = null;
} catch (Throwable $exception) {
    $student = null;
    $errorMessage = $exception->getMessage();
}

try {
    $templateService = new TemplateDesignerService();
    $templates = $templateService->listTemplates();
    $defaultTemplateId = (string) ($templateService->getDefaultTemplateId() ?? '');
} catch (Throwable $exception) {
    // Profile details and editing remain available if template storage is unavailable.
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
    if ($uploadMessage === '' && $action === 'recalculate_card') {
        $currentUser = Auth::user();
        if (($currentUser['role'] ?? '') !== 'Administrator') {
            $cardMessage = 'Only administrators can recalculate an issued ID card.';
            $cardMessageType = 'danger';
        } elseif (!$cardRepository instanceof CardRepository) {
            $cardMessage = 'Card verification is not available.';
            $cardMessageType = 'danger';
        } else {
            try {
                $replacementCard = $cardRepository->reissueCard($id);
                $cardMessage = 'ID recalculated. The new verification code is ' . (string) $replacementCard['guid'] . '. Previous printed cards are now invalid.';
                $cardMessageType = 'success';
            } catch (Throwable $exception) {
                $cardMessage = $exception->getMessage();
                $cardMessageType = 'danger';
            }
        }
    } elseif ($uploadMessage === '' && $action === 'revoke_card') {
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
    } elseif ($uploadMessage === '' && $action === 'delete_student') {
        $currentUser = Auth::user();
        if (($currentUser['role'] ?? '') !== 'Administrator') {
            $profileMessage = 'Only administrators can delete a student.';
            $profileMessageType = 'danger';
        } elseif ($student === null) {
            $profileMessage = 'Student not found.';
            $profileMessageType = 'warning';
        } else {
            try {
                if ($repository->delete($id)) {
                    header('Location: students.php?deleted=1');
                    exit;
                }

                $profileMessage = 'Unable to delete the student.';
                $profileMessageType = 'danger';
            } catch (Throwable $exception) {
                $profileMessage = $exception->getMessage();
                $profileMessageType = 'danger';
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

if ($student) {
    try {
        $academicEnrolments = (new StudentEnrolmentRepository(Database::getConnection()))->forStudent($id);
    } catch (Throwable $exception) {
        $academicEnrolments = [];
    }
}

function studentPhotoUrl(string $photoPath): string
{
    $normalizedPath = str_replace('\\', '/', ltrim(trim($photoPath), '/'));
    if (!str_starts_with($normalizedPath, 'uploads/student_photos/')) {
        return '';
    }

    return is_file(__DIR__ . '/' . $normalizedPath) ? $normalizedPath : '';
}

$photoPath = $student['photo_path'] ?? '';
$photoUrl = studentPhotoUrl(is_string($photoPath) ? $photoPath : '');
$hasPhoto = $photoUrl !== '';
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
                <div class="d-flex flex-wrap justify-content-end gap-2">
                    <a href="student-form.php?id=<?= (int) ($student['id'] ?? 0) ?>" class="btn btn-outline-secondary btn-sm">
                        Edit Profile
                    </a>
                    <a href="student-id-card.php?id=<?= (int) ($student['id'] ?? 0) ?>" class="btn btn-outline-primary btn-sm">
                        Preview Card
                    </a>
                    <a href="student-data-export.php?student_id=<?= (int) ($student['id'] ?? 0) ?>" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i>Export Data
                    </a>
                    <?php if ((Auth::user()['role'] ?? '') === 'Administrator'): ?>
                        <form method="post" onsubmit="return confirm('Delete this student permanently? Their ID card history will also be removed.');">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="delete_student">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-trash me-1" aria-hidden="true"></i>Delete Student
                            </button>
                        </form>
                    <?php endif; ?>
                    <form method="get" action="student-id-card.php" class="d-flex flex-wrap gap-2">
                        <input type="hidden" name="id" value="<?= (int) ($student['id'] ?? 0) ?>">
                        <input type="hidden" name="export" value="pdf">
                        <label class="visually-hidden" for="profileTemplate">ID card template</label>
                        <select id="profileTemplate" name="template" class="form-select form-select-sm" style="min-width: 190px;">
                            <option value="">Default template</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?= htmlspecialchars((string) ($template['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= (($template['id'] ?? '') === $defaultTemplateId) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) ($template['name'] ?? 'Untitled template'), ENT_QUOTES, 'UTF-8') ?><?= (($template['id'] ?? '') === $defaultTemplateId) ? ' (default)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm" title="Export the selected ID card template as PDF">
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
            <?php if ($profileMessage !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($profileMessageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($profileMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-4 align-items-start">
                        <div class="col-md-4">
                            <?php if ($hasPhoto): ?>
                                <div class="student-profile-photo-preview">
                                    <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Student photo">
                                </div>
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
                                <label class="form-label" for="profilePhoto">Choose a new photo</label>
                                <input id="profilePhoto" type="file" name="photo" accept="image/png,image/jpeg,image/webp" class="form-control" data-photo-input>
                                <?php if ($hasPhoto): ?>
                                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-photo-edit-existing>
                                        <i class="bi bi-sliders me-1" aria-hidden="true"></i>Adjust current photo
                                    </button>
                                <?php endif; ?>
                                <div class="photo-upload-editor mt-3" data-photo-editor data-input-id="profilePhoto" data-photo-existing-url="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" hidden>
                                    <div class="photo-upload-stage border rounded" data-photo-stage>
                                        <img data-photo-source alt="Selected student photo">
                                    </div>
                                    <div class="photo-output-preview mt-3" data-photo-output-preview-wrap hidden>
                                        <div class="small fw-semibold mb-1">Final ID photo preview</div>
                                        <img data-photo-output-preview alt="Exact photo that will be saved and shown on the profile">
                                    </div>
                                    <div class="form-text mt-2">Drag the photo to position it. Drag the frame or its corners to adjust the 4:5 ID-card crop. Use the mouse wheel to zoom.</div>
                                    <div class="photo-editor-toolbar mt-3">
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Photo framing mode">
                                            <input id="profilePhotoCrop" class="btn-check" type="radio" name="profile_photo_mode" value="crop" data-photo-mode checked>
                                            <label class="btn btn-outline-secondary" for="profilePhotoCrop">Crop</label>
                                            <input id="profilePhotoFit" class="btn-check" type="radio" name="profile_photo_mode" value="fit" data-photo-mode>
                                            <label class="btn btn-outline-secondary" for="profilePhotoFit">Fit on white</label>
                                        </div>
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Photo adjustments">
                                            <button type="button" class="btn btn-outline-secondary" data-photo-rotate-left title="Rotate left" aria-label="Rotate left"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
                                            <button type="button" class="btn btn-outline-secondary" data-photo-rotate-right title="Rotate right" aria-label="Rotate right"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
                                            <button type="button" class="btn btn-outline-secondary" data-photo-flip title="Mirror photo" aria-label="Mirror photo"><i class="bi bi-symmetry-horizontal" aria-hidden="true"></i></button>
                                            <button type="button" class="btn btn-outline-secondary" data-photo-reset title="Reset adjustments" aria-label="Reset adjustments"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></button>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mt-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-photo-zoom-out aria-label="Zoom out"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
                                        <label class="visually-hidden" for="profilePhotoZoom">Image size</label>
                                        <input id="profilePhotoZoom" class="form-range m-0" type="range" data-photo-zoom min="1" max="3" step="0.01" value="1">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-photo-zoom-in aria-label="Zoom in"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
                                        <span class="small photo-zoom-value" data-photo-zoom-value>100%</span>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary mt-3">Save photo</button>
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
                                    <div class="text-muted small">Mode of study</div>
                                    <div class="fw-semibold"><?= htmlspecialchars(match (strtoupper((string) ($student['qualification'] ?? ''))) {
                                        'MSCE' => 'Formal',
                                        'JCE' => 'Informal',
                                        default => '',
                                    }, ENT_QUOTES, 'UTF-8') ?></div>
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
                                <div class="col-12">
                                    <hr class="my-2">
                                    <h2 class="h5 mb-1">Parent or guardian</h2>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Name</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['guardian_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Relationship</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['guardian_relationship'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Phone</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['guardian_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Alternative phone</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['guardian_alt_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Email</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($student['guardian_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Address</div>
                                    <div class="fw-semibold"><?= nl2br(htmlspecialchars((string) ($student['guardian_address'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                                </div>
                            </div>
                            <div class="border-top mt-4 pt-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                    <div>
                                        <h2 class="h5 mb-1">Academic enrolments</h2>
                                        <p class="text-muted small mb-0">Programme assignments from the Academic module.</p>
                                    </div>
                                    <a href="academic-enrolments.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-mortarboard-fill me-1" aria-hidden="true"></i>Manage enrolments</a>
                                </div>
                                <?php if ($academicEnrolments === []): ?>
                                    <div class="alert alert-light border mb-4">No academic enrolment recorded yet.</div>
                                <?php else: ?>
                                    <div class="table-responsive mb-4">
                                        <table class="table table-sm align-middle">
                                            <thead><tr><th>Programme</th><th>Term</th><th>Enrolled</th><th>Status</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($academicEnrolments as $enrolment): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars((string) ($enrolment['programme_code'] ?? '') . ' - ' . (string) ($enrolment['programme_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars(trim((string) ($enrolment['academic_year'] ?? '') . ' ' . (string) ($enrolment['term_name'] ?? '') . ' ' . (string) ($enrolment['semester'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars((string) ($enrolment['enrolled_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><span class="badge text-bg-secondary"><?= htmlspecialchars((string) ($enrolment['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>

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
                                    <?php if ((Auth::user()['role'] ?? '') === 'Administrator'): ?>
                                        <div class="d-flex flex-wrap gap-2">
                                            <form method="post" onsubmit="return confirm('Recalculate this ID? A new verification QR will be created and every previously printed ID for this student will become invalid.');">
                                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="recalculate_card">
                                                <button type="submit" class="btn btn-outline-warning btn-sm">Recalculate ID</button>
                                            </form>
                                            <?php if ($card && ($card['status'] ?? '') === 'ACTIVE'): ?>
                                                <form method="post" onsubmit="return confirm('Revoke this ID card? Its QR code will immediately report that it is revoked.');">
                                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="revoke_card">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Revoke ID card</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
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
