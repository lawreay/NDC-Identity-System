<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/SettingsRepository.php';
require_once __DIR__ . '/../app/TemplateDesigner/TemplateDesignerService.php';
require_once __DIR__ . '/../app/Auth.php';

use App\Auth;

Auth::requireLogin();

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
            'school_name' => $appSettings['school_name'] ?? $appSettings['organization_name'] ?? 'NDC',
            'campus_name' => $appSettings['campus_name'] ?? '',
            'academic_programs' => $appSettings['academic_programs'] ?? '',
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
        .preview-frame { border: 1px solid #d9e2ef; border-radius: 12px; background: #fff; min-height: 360px; padding: 16px; overflow:auto; display:block; }
        .preview-card-shell { width:856px; height:540px; margin:0 auto; }
        .preview-frame .ndc-id-card-wrapper { box-shadow: 0 10px 30px rgba(0,0,0,0.08); width:856px !important; height:540px !important; min-width:856px !important; min-height:540px !important; max-width:856px !important; max-height:540px !important; aspect-ratio:856/540 !important; display:block !important; }
        .preview-frame .ndc-id-card-wrapper > * { box-sizing:border-box; }
        .template-selector { min-width: 220px; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/partials/header.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Student ID Card Preview</h1>
            <p class="text-muted mb-0">Preview the generated card using the selected template.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($selectedTemplate !== null): ?>
                <form method="post" action="export-card.php">
                    <input type="hidden" name="student_id" value="<?= $id ?>">
                    <input type="hidden" name="template_id" value="<?= escape($templateId) ?>">
                    <input type="hidden" name="_csrf" value="<?= escape(Auth::csrfToken()) ?>">
                    <button type="submit" class="btn btn-primary">Export Preview PDF</button>
                </form>
            <?php endif; ?>
            <a href="student-profile.php?id=<?= $id ?>" class="btn btn-outline-secondary">Back to profile</a>
        </div>
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
                        <?php if ($selectedTemplate !== null): ?>
                            <span class="badge bg-secondary">Preview only</span>
                        <?php endif; ?>
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
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="h6 mb-0">Front</h2>
                                <form method="post" action="export-card-png.php">
                                    <input type="hidden" name="student_id" value="<?= $id ?>">
                                    <input type="hidden" name="template_id" value="<?= escape($templateId) ?>">
                                    <input type="hidden" name="side" value="front">
                                    <input type="hidden" name="_csrf" value="<?= escape(Auth::csrfToken()) ?>">
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Export PNG</button>
                                </form>
                            </div>
                            <div class="preview-frame"><div class="preview-card-shell"><?= $frontPreview ?></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="h6 mb-0">Back</h2>
                                <form method="post" action="export-card-png.php">
                                    <input type="hidden" name="student_id" value="<?= $id ?>">
                                    <input type="hidden" name="template_id" value="<?= escape($templateId) ?>">
                                    <input type="hidden" name="side" value="back">
                                    <input type="hidden" name="_csrf" value="<?= escape(Auth::csrfToken()) ?>">
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Export PNG</button>
                                </form>
                            </div>
                            <div class="preview-frame"><div class="preview-card-shell"><?= $backPreview ?></div></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
    (function () {
        const cardWidth = 856;
        const cardHeight = 540;

        function syncPreviewScales() {
            document.querySelectorAll('.preview-frame').forEach(frame => {
                const shell = frame.querySelector('.preview-card-shell');

                if (shell) {
                    shell.style.width = cardWidth + 'px';
                    shell.style.height = cardHeight + 'px';
                }
            });
        }

        window.addEventListener('resize', syncPreviewScales);
        if ('ResizeObserver' in window) {
            document.querySelectorAll('.preview-frame').forEach(frame => {
                new ResizeObserver(syncPreviewScales).observe(frame);
            });
        }
        syncPreviewScales();
    }());
</script>
</body>
</html>
