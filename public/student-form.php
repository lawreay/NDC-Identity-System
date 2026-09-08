<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/SettingsRepository.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/Services/ImageUploadService.php';

use App\Auth;
use App\Services\ImageUploadService;

Auth::requireLogin();

$connection = Database::getConnection();
$repository = new StudentRepository($connection);
$settingsRepository = new SettingsRepository($connection);
$settings = $settingsRepository->getAll();
$imageUploadService = new ImageUploadService();
$programOptions = optionLines((string) ($settings['academic_programs'] ?? ''));
$id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['id'] ?? 0);
$isEdit = $id > 0;
$errors = [];
$student = null;

$fields = [
    'student_number',
    'first_name',
    'last_name',
    'gender',
    'date_of_birth',
    'district',
    'traditional_authority',
    'village',
    'phone_number',
    'qualification',
    'program',
    'class_level',
    'billing_category',
    'status',
];

$form = array_fill_keys($fields, '');
$form['status'] = 'Active';

if ($isEdit) {
    $student = $repository->findById($id);
    if (!$student) {
        http_response_code(404);
        $errors[] = 'Student not found.';
        $isEdit = false;
    } else {
        foreach ($fields as $field) {
            $form[$field] = (string) ($student[$field] ?? '');
        }
        if ($form['program'] !== '' && !in_array($form['program'], $programOptions, true)) {
            $programOptions[] = $form['program'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Auth::requireCsrf();
    } catch (Throwable $exception) {
        $errors[] = 'Security token invalid. Please try again.';
    }

    foreach ($fields as $field) {
        $form[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $form['billing_category'] = billingCategoryForQualification($form['qualification']);

    if ($form['first_name'] === '') {
        $errors[] = 'First name is required.';
    }
    if ($form['last_name'] === '') {
        $errors[] = 'Last name is required.';
    }
    if (!in_array($form['qualification'], ['MSCE', 'JCE'], true)) {
        $errors[] = 'Please choose a valid qualification.';
    }
    if ($programOptions !== [] && !in_array($form['program'], $programOptions, true)) {
        $errors[] = 'Please choose a program from app settings.';
    }
    if ($form['student_number'] !== '' && $repository->studentNumberExists($form['student_number'], $isEdit ? $id : null)) {
        $errors[] = 'That student number is already in use.';
    }

    $photoPath = null;
    $uploadedPhoto = $_FILES['photo'] ?? null;
    if (is_array($uploadedPhoto) && !empty($uploadedPhoto['name'])) {
        if (($uploadedPhoto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'The photo upload failed. Please try again.';
        } elseif ((int) ($uploadedPhoto['size'] ?? 0) > 5 * 1024 * 1024) {
            $errors[] = 'Photo size must not exceed 5 MB.';
        }
    }

    if ($errors === []) {
        try {
            $data = $form;
            $data['photo_path'] = $isEdit ? ($student['photo_path'] ?? null) : null;

            if ($isEdit) {
                $repository->update($id, $data);
                if (is_array($uploadedPhoto) && !empty($uploadedPhoto['name'])) {
                    $photoPath = $imageUploadService->storeStudentPhoto($uploadedPhoto, __DIR__ . '/uploads/student_photos', $id);
                    $repository->updatePhoto($id, $photoPath);
                }
                header('Location: student-profile.php?id=' . $id . '&updated=1');
                exit;
            }

            $newId = $repository->create($data);
            if (is_array($uploadedPhoto) && !empty($uploadedPhoto['name'])) {
                $photoPath = $imageUploadService->storeStudentPhoto($uploadedPhoto, __DIR__ . '/uploads/student_photos', $newId);
                $repository->updatePhoto($newId, $photoPath);
            }
            header('Location: student-profile.php?id=' . $newId . '&created=1');
            exit;
        } catch (Throwable $exception) {
            $errors[] = 'Unable to save student: ' . $exception->getMessage();
        }
    }
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * @return array<int, string>
 */
function optionLines(string $value): array
{
    $parts = preg_split('/\r\n|\r|\n|,/', $value) ?: [];
    $options = [];
    foreach ($parts as $part) {
        $option = trim($part);
        if ($option !== '' && !in_array($option, $options, true)) {
            $options[] = $option;
        }
    }

    return $options;
}

function billingCategoryForQualification(string $qualification): string
{
    return match ($qualification) {
        'MSCE' => 'Formal',
        'JCE' => 'Informal',
        default => '',
    };
}

$title = $isEdit ? 'Edit Student Profile' : 'Add Student';
$submitLabel = $isEdit ? 'Save Profile' : 'Add Student';
$billingCategory = billingCategoryForQualification($form['qualification']);
if ($billingCategory !== '') {
    $form['billing_category'] = $billingCategory;
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/header.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><?= escape($title) ?></h1>
            <p class="text-muted mb-0"><?= $isEdit ? 'Update all stored student profile details.' : 'Create a new student record for ID card generation.' ?></p>
        </div>
        <a href="<?= $isEdit ? 'student-profile.php?id=' . $id : 'students.php' ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= escape((string) $error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="card shadow-sm">
        <div class="card-body">
            <input type="hidden" name="_csrf" value="<?= escape(Auth::csrfToken()) ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= $id ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="student_number">Student number</label>
                    <input id="student_number" type="text" name="student_number" class="form-control" value="<?= escape($form['student_number']) ?>" placeholder="Auto-generated, e.g. SPHULA01">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="first_name">First name</label>
                    <input id="first_name" type="text" name="first_name" class="form-control" value="<?= escape($form['first_name']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="last_name">Last name</label>
                    <input id="last_name" type="text" name="last_name" class="form-control" value="<?= escape($form['last_name']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="gender">Gender</label>
                    <select id="gender" name="gender" class="form-select">
                        <?php foreach (['', 'Female', 'Male', 'Other'] as $option): ?>
                            <option value="<?= escape($option) ?>" <?= $form['gender'] === $option ? 'selected' : '' ?>><?= escape($option === '' ? 'Select gender' : $option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="date_of_birth">Date of birth</label>
                    <input id="date_of_birth" type="date" name="date_of_birth" class="form-control" value="<?= escape($form['date_of_birth']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <?php foreach (['Active', 'Inactive', 'Graduated', 'Suspended'] as $option): ?>
                            <option value="<?= escape($option) ?>" <?= $form['status'] === $option ? 'selected' : '' ?>><?= escape($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="qualification">Qualification</label>
                    <select id="qualification" name="qualification" class="form-select" required>
                        <option value="">Select qualification</option>
                        <?php foreach (['MSCE', 'JCE'] as $option): ?>
                            <option value="<?= escape($option) ?>" <?= $form['qualification'] === $option ? 'selected' : '' ?>><?= escape($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="program">Program</label>
                    <select id="program" name="program" class="form-select" <?= $programOptions === [] ? 'disabled' : '' ?>>
                        <?php if ($programOptions === []): ?>
                            <option value="">Add programs under Settings first</option>
                        <?php else: ?>
                            <option value="">Select program</option>
                            <?php if ($form['program'] !== '' && !in_array($form['program'], $programOptions, true)): ?>
                                <option value="<?= escape($form['program']) ?>" selected><?= escape($form['program']) ?></option>
                            <?php endif; ?>
                            <?php foreach ($programOptions as $option): ?>
                                <option value="<?= escape($option) ?>" <?= $form['program'] === $option ? 'selected' : '' ?>><?= escape($option) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="class_level">Class level</label>
                    <input id="class_level" type="text" name="class_level" class="form-control" value="<?= escape($form['class_level']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="billing_category">Billing category</label>
                    <input id="billing_category" type="text" class="form-control" value="<?= escape($form['billing_category']) ?>" readonly>
                    <input id="billing_category_hidden" type="hidden" name="billing_category" value="<?= escape($form['billing_category']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="phone_number">Phone number</label>
                    <input id="phone_number" type="text" name="phone_number" class="form-control" value="<?= escape($form['phone_number']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="district">District</label>
                    <input id="district" type="text" name="district" class="form-control" value="<?= escape($form['district']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="traditional_authority">Traditional authority</label>
                    <input id="traditional_authority" type="text" name="traditional_authority" class="form-control" value="<?= escape($form['traditional_authority']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="village">Village</label>
                    <input id="village" type="text" name="village" class="form-control" value="<?= escape($form['village']) ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="photo">Photo</label>
                    <input id="photo" type="file" name="photo" class="form-control" accept="image/png,image/jpeg,image/webp">
                    <?php if ($isEdit && !empty($student['photo_path'])): ?>
                        <div class="form-text">Leave blank to keep the current photo.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="<?= $isEdit ? 'student-profile.php?id=' . $id : 'students.php' ?>" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary"><?= escape($submitLabel) ?></button>
        </div>
    </form>
</div>
<script>
    const qualification = document.getElementById('qualification');
    const billingCategory = document.getElementById('billing_category');
    const billingCategoryHidden = document.getElementById('billing_category_hidden');
    const billingMap = {
        MSCE: 'Formal',
        JCE: 'Informal'
    };

    if (qualification && billingCategory && billingCategoryHidden) {
        qualification.addEventListener('change', () => {
            const value = billingMap[qualification.value] || '';
            billingCategory.value = value;
            billingCategoryHidden.value = value;
        });
    }
</script>
</body>
</html>
