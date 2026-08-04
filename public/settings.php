<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/SettingsRepository.php';

$errors = [];
$success = '';

try {
    $repository = new SettingsRepository(Database::getConnection());
    $settings = $repository->getAll();
} catch (Throwable $exception) {
    $settings = [];
    $errors[] = 'Unable to load settings: ' . $exception->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = [
        'organization_name' => trim((string) ($_POST['organization_name'] ?? '')),
        'organization_address' => trim((string) ($_POST['organization_address'] ?? '')),
        'organization_phone' => trim((string) ($_POST['organization_phone'] ?? '')),
        'organization_email' => trim((string) ($_POST['organization_email'] ?? '')),
        'organization_website' => trim((string) ($_POST['organization_website'] ?? '')),
        'organization_logo_path' => trim((string) ($_POST['organization_logo_path'] ?? '')),
        'principal_signature_name' => trim((string) ($_POST['principal_signature_name'] ?? '')),
        'principal_signature_path' => trim((string) ($_POST['principal_signature_path'] ?? '')),
        'campus_name' => trim((string) ($_POST['campus_name'] ?? '')),
        'school_name' => trim((string) ($_POST['school_name'] ?? '')),
        'academic_programs' => trim((string) ($_POST['academic_programs'] ?? '')),
    ];

    $uploadRoot = __DIR__ . '/uploads/settings';
    if (!is_dir($uploadRoot)) {
        mkdir($uploadRoot, 0777, true);
    }

    $uploadedLogo = $_FILES['organization_logo_file'] ?? null;
    if (is_array($uploadedLogo) && !empty($uploadedLogo['tmp_name'])) {
        $logoPath = storeUpload($uploadedLogo, $uploadRoot, 'school_logo');
        if ($logoPath !== null) {
            $input['organization_logo_path'] = $logoPath;
        }
    }

    $uploadedSignature = $_FILES['authorized_signature_file'] ?? null;
    if (is_array($uploadedSignature) && !empty($uploadedSignature['tmp_name'])) {
        $signaturePath = storeUpload($uploadedSignature, $uploadRoot, 'authorized_signature');
        if ($signaturePath !== null) {
            $input['authorized_signature_path'] = $signaturePath;
        }
    }

    try {
        if ($repository->save($input)) {
            $success = 'Settings saved successfully.';
            $settings = array_merge($settings, $input);
        } else {
            $errors[] = 'Unable to save settings.';
        }
    } catch (Throwable $exception) {
        $errors[] = 'Unable to save settings: ' . $exception->getMessage();
    }
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function storeUpload(array $file, string $uploadRoot, string $baseName): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed, true)) {
        return null;
    }

    $filename = $baseName . '.' . $extension;
    $destination = $uploadRoot . DIRECTORY_SEPARATOR . $filename;
    if (is_uploaded_file($file['tmp_name'])) {
        move_uploaded_file($file['tmp_name'], $destination);
    } else {
        copy($file['tmp_name'], $destination);
    }

    return 'uploads/settings/' . $filename;
}

function getPreviewSrc(?string $path): ?string
{
    if ($path === null || trim($path) === '') {
        return null;
    }

    $path = trim(str_replace('\\', '/', $path));
    if (preg_match('#^(data:|https?://)#i', $path)) {
        return $path;
    }

    $path = ltrim($path, '/');
    $candidate = __DIR__ . '/' . $path;
    if (is_file($candidate)) {
        return $path;
    }

    return null;
}

$logoPreview = getPreviewSrc($settings['organization_logo_path'] ?? '');
$signaturePreview = getPreviewSrc($settings['principal_signature_path'] ?? $settings['authorized_signature_path'] ?? '');

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Organization Settings</h1>
            <p class="text-muted mb-0">Manage school and authorized signature settings used by ID templates.</p>
        </div>
        <a href="template-designer.php" class="btn btn-outline-secondary">Back to Template Designer</a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= escape($success) ?></div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">School Name</label>
                <input type="text" name="school_name" class="form-control" value="<?= escape($settings['school_name'] ?? $settings['organization_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Campus Name</label>
                <input type="text" name="campus_name" class="form-control" value="<?= escape($settings['campus_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Organization Name</label>
                <input type="text" name="organization_name" class="form-control" value="<?= escape($settings['organization_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Academic Programs</label>
                <textarea name="academic_programs" class="form-control" rows="4" placeholder="Enter one program per line"><?= escape($settings['academic_programs'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Authorized Signatory Name</label>
                <input type="text" name="principal_signature_name" class="form-control" value="<?= escape($settings['principal_signature_name'] ?? $settings['authorized_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Organization Address</label>
                <input type="text" name="organization_address" class="form-control" value="<?= escape($settings['organization_address'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Organization Phone</label>
                <input type="text" name="organization_phone" class="form-control" value="<?= escape($settings['organization_phone'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Organization Email</label>
                <input type="email" name="organization_email" class="form-control" value="<?= escape($settings['organization_email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Organization Website</label>
                <input type="text" name="organization_website" class="form-control" value="<?= escape($settings['organization_website'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">School Logo Path</label>
                <input type="text" name="organization_logo_path" class="form-control" value="<?= escape($settings['organization_logo_path'] ?? '') ?>" placeholder="uploads/logo.png or /assets/logo.svg">
            </div>
            <div class="col-md-6">
                <label class="form-label">Upload School Logo</label>
                <input type="file" name="organization_logo_file" class="form-control" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                <?php if ($logoPreview): ?>
                    <div class="mt-2">
                        <img src="<?= escape($logoPreview) ?>" alt="Current school logo" class="img-fluid rounded border" style="max-height:120px;">
                        <div class="text-muted small mt-1">Current logo preview</div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Authorized Signature Path</label>
                <input type="text" name="principal_signature_path" class="form-control" value="<?= escape($settings['principal_signature_path'] ?? $settings['authorized_signature_path'] ?? '') ?>" placeholder="uploads/signature.png">
            </div>
            <div class="col-md-6">
                <label class="form-label">Upload Authorized Signature</label>
                <input type="file" name="authorized_signature_file" class="form-control" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                <?php if ($signaturePreview): ?>
                    <div class="mt-2">
                        <img src="<?= escape($signaturePreview) ?>" alt="Current authorized signature" class="img-fluid rounded border" style="max-height:120px;">
                        <div class="text-muted small mt-1">Current signature preview</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</div>
</body>
</html>
