<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/SettingsRepository.php';
require_once __DIR__ . '/../app/TemplateDesigner/TemplateDesignerService.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$service = new TemplateDesignerService();
$repository = new StudentRepository(Database::getConnection());
$settingsRepository = new SettingsRepository(Database::getConnection());

$message = '';
$messageType = 'success';
$templateId = trim((string) ($_GET['template'] ?? ''));

try {
    $student = $repository->findById($id);
    $templates = $service->listTemplates();
    $defaultTemplate = $service->getDefaultTemplate();
    $defaultTemplateId = $service->getDefaultTemplateId();
    $selectedTemplate = null;

    if (!$student) {
        throw new RuntimeException('Student not found.');
    }

    if ($templateId !== '') {
        $selectedTemplate = $service->getTemplate($templateId);
        if ($selectedTemplate === null) {
            $message = 'Selected template not found. Falling back to default template.';
            $messageType = 'warning';
            $templateId = '';
        }
    }

    if ($selectedTemplate === null) {
        $selectedTemplate = $defaultTemplate;
        $templateId = $defaultTemplateId ?? '';
    }

    if ($selectedTemplate === null) {
        $message = 'No template selected and no default template is configured. Please set a default template in the Template Designer.';
        $messageType = 'danger';
    }

    if ($selectedTemplate !== null) {
        if (empty(trim((string) ($student['student_number'] ?? '')))) {
            $student['student_number'] = $repository->generateStudentNumber($id, (string) ($student['first_name'] ?? ''), (string) ($student['last_name'] ?? ''));
            $message = 'Student number generated automatically for preview.';
            $messageType = 'info';
        }

        $student['full_name'] = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
        $appSettings = $settingsRepository->getAll();
        $organization = [
            'name' => $appSettings['organization_name'] ?? 'NDC',
            'address' => $appSettings['organization_address'] ?? 'Ntcheu',
            'phone' => $appSettings['organization_phone'] ?? '+265 999 000 000',
            'email' => $appSettings['organization_email'] ?? 'info@ndc.edu',
            'website' => $appSettings['organization_website'] ?? 'https://ndc.edu',
            'logo_path' => $appSettings['organization_logo_path'] ?? '',
            'authorized_name' => $appSettings['principal_signature_name'] ?? $appSettings['authorized_name'] ?? 'Authorized Officer',
            'authorized_signature_path' => $appSettings['principal_signature_path'] ?? $appSettings['authorized_signature_path'] ?? '',
        ];
        $theme = [
            'primary_color' => '#0b5ed7',
            'secondary_color' => '#0a7e8c',
            'accent_color' => '#f4b400',
        ];
        $frontPreview = $service->renderTemplate($selectedTemplate, $student, $organization, $theme, 'front');
        $backPreview = $service->renderTemplate($selectedTemplate, $student, $organization, $theme, 'back');
    }
} catch (Throwable $exception) {
    $student = null;
    $templates = [];
    $frontPreview = '';
    $backPreview = '';
    $message = $exception->getMessage();
    $messageType = 'danger';
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student ID Card Preview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f6f8fb; }
        .preview-frame { border: 1px solid #d9eef; border-radius: 12px; background: #fff; min-height: 360px; padding: 16px; overflow:auto; }
        .template-selector { min-width: 220px; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Student ID Card Preview</h1>
            <p class="text-muted mb-0">Preview the generated card using the selected template.</p>
        </div>
        <a href="student-profile.php?id=<?= $id ?>" class="btn btn-outline-secondary">Back to profile</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= escape($messageType) ?>"><?= escape($message) ?></div>
    <?php endif; ?>

    <?php if ($student === null): ?>
        <div class="alert alert-warning">Student data could not be loaded.</div>
    <?php else: ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center">
                <div>
                    <h2 class="h5 mb-1"><?= escape(trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''))) ?></h2>
                    <p class="text-muted mb-0">Student #: <?= escape((string) ($student['student_number'] ?? '')) ?></p>
                </div>
                <div class="ms-auto">
                    <form method="get" class="d-flex gap-2 align-items-center">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <label class="visually-hidden" for="templateSelect">Template</label>
                        <select id="templateSelect" name="template" class="form-select template-selector">
                            <option value="">Default Template</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?= escape((string) ($template['id'] ?? '')) ?>" <?= (($templateId !== '' && ($template['id'] ?? '') === $templateId) || ($templateId === '' && ($template['id'] ?? '') === $defaultTemplateId)) ? 'selected' : '' ?>>
                                    <?= escape((string) ($template['name'] ?? 'Untitled')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">Apply</button>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($selectedTemplate === null): ?>
            <div class="alert alert-info">Set a default template in the Template Designer to preview cards here.</div>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h2 class="h6 mb-3">Front</h2>
                            <div class="preview-frame"><?= $frontPreview ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h2 class="h6 mb-3">Back</h2>
                            <div class="preview-frame"><?= $backPreview ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
