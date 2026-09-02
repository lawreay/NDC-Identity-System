<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/SettingsRepository.php';
require_once __DIR__ . '/../app/TemplateDesigner/TemplateDesignerService.php';
require_once __DIR__ . '/../app/Auth.php';

use App\Auth;

Auth::requireLogin();

$service = new TemplateDesignerService();
$errors = [];
$success = '';
$template = null;
$mode = 'list';
$selectedTemplateId = '';
$defaultTemplateId = $service->getDefaultTemplateId();

if (isset($_GET['export']) && is_string($_GET['export']) && $_GET['export'] !== '') {
    $exportPath = $service->exportTemplate($_GET['export']);
    if ($exportPath !== null && is_file($exportPath)) {
        // Safely create filename without path traversal or header injection
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($exportPath));
        if ($safeFilename === '') {
            $safeFilename = 'template_export.ndctemplate';
        }
        
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
        header('Content-Length: ' . filesize($exportPath));
        readfile($exportPath);
        unlink($exportPath);
        exit;
    }
    $errors[] = 'Unable to export the selected template.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    $templateId = trim((string) ($_POST['template_id'] ?? ''));

    if ($action === 'delete' && $templateId !== '') {
        $service->deleteTemplate($templateId);
        $success = 'Template deleted.';
        $mode = 'list';
    } elseif ($action === 'duplicate' && $templateId !== '') {
        $duplicate = $service->duplicateTemplate($templateId);
        if ($duplicate === null) {
            $errors[] = 'Unable to duplicate the selected template.';
        } else {
            $success = 'Template duplicated successfully.';
        }
        $mode = 'list';
    } elseif ($action === 'set_default' && $templateId !== '') {
        $service->setDefaultTemplate($templateId);
        $success = 'Default template updated.';
        $defaultTemplateId = $templateId;
        $mode = 'list';
    } elseif ($action === 'import') {
        $result = $service->importTemplate($_FILES['template_package'] ?? []);
        if (!empty($result['errors'])) {
            $errors = $result['errors'];
        } else {
            $success = 'Template imported successfully.';
            $mode = 'list';
        }
    } else {
        $input = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'draft')),
            'front_html' => (string) ($_POST['front_html'] ?? ''),
            'back_html' => (string) ($_POST['back_html'] ?? ''),
            'created_by' => trim((string) ($_POST['created_by'] ?? 'Administrator')),
            'created_at' => trim((string) ($_POST['created_at'] ?? date('Y-m-d H:i:s'))),
        ];

        $errors = $service->validateTemplate($input['front_html'], $input['back_html']);
        if ($errors === []) {
            $files = [
                'front_background' => $_FILES['front_background'] ?? null,
                'back_background' => $_FILES['back_background'] ?? null,
            ];

            if ($templateId !== '') {
                $service->updateTemplate($templateId, $input, $files);
                $success = 'Template updated successfully.';
            } else {
                $service->createTemplate($input, $files);
                $success = 'Template created successfully.';
            }

            $mode = 'list';
        } else {
            $mode = 'form';
            $selectedTemplateId = $templateId;
            $template = $input;
        }
    }
}

if (isset($_GET['edit']) && is_string($_GET['edit']) && $_GET['edit'] !== '') {
    $mode = 'form';
    $selectedTemplateId = $_GET['edit'];
    $template = $service->getTemplate($selectedTemplateId);
}

if (isset($_GET['preview']) && is_string($_GET['preview']) && $_GET['preview'] !== '') {
    $mode = 'preview';
    $selectedTemplateId = $_GET['preview'];
    $template = $service->getTemplate($selectedTemplateId);
}

$templates = $service->listTemplates();
$defaultTemplateId = $service->getDefaultTemplateId();

require_once __DIR__ . '/../app/SettingsRepository.php';
$settingsRepository = new SettingsRepository(Database::getConnection());
$appSettings = $settingsRepository->getAll();

$student = [
    'full_name' => 'Moses Banda',
    'student_number' => 'BND001',
    'photo_path' => '',
    'gender' => 'Male',
    'department' => 'ICT',
    'program' => 'Software Development',
    'class_level' => 'Level 3',
    'qualification' => 'Certificate',
    'status' => 'Active',
    'issue_date' => '2026-01-20',
    'expiry_date' => '2027-01-20',
];
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

$frontPreview = '';
$backPreview = '';
if ($template !== null && is_array($template)) {
    $frontPreview = $service->renderTemplate($template, $student, $organization, $theme, 'front');
    $backPreview = $service->renderTemplate($template, $student, $organization, $theme, 'back');
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Template Designer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css">
    <style>
        body { background: #f6f8fb; }
        .preview-frame { border: 1px solid #d9e2ef; border-radius: 12px; background: #fff; min-height: 360px; padding: 16px; overflow:auto; display:block; }
        .preview-card-shell { width:856px; height:540px; margin:0 auto; }
        .preview-frame .ndc-id-card-wrapper { box-shadow: 0 10px 30px rgba(0,0,0,0.08); width:856px !important; height:540px !important; min-width:856px !important; min-height:540px !important; max-width:856px !important; max-height:540px !important; aspect-ratio:856/540 !important; display:block !important; }
        .preview-frame .ndc-id-card-wrapper > * { box-sizing:border-box; }
        .guide-card { border-left: 4px solid #0d6efd; }
        .code-block { background: #111827; color: #f9fafb; padding: 12px; border-radius: 10px; overflow-x: auto; }
        .editor { min-height: 420px; border: 1px solid #d9e2ef; border-radius: 10px; background: #fff; }
        .editor-toolbar { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; margin-bottom: 1rem; }
        .editor-tab { cursor: pointer; }
        .editor-tab.active { background: #0d6efd; color: #fff; }
        .tag-toolbox { gap: 0.5rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); }
        .tag-toolbar { border: 1px solid #dee2e6; border-radius: 0.75rem; padding: 0.75rem; background: #fff; }
        .tag-toolbar .tag-button { margin: 0 0.25rem 0.35rem 0; }
        .tag-button { display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem; }
        .field-panel { border: 1px solid #dee2e6; border-radius: 0.75rem; padding: 1rem; background: #fff; }
        .background-preview { width: 100%; min-height: 120px; border: 1px solid #d9e2ef; border-radius: 12px; background: #f8f9fa; background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; color: #6c757d; text-align: center; }
        .background-preview img { max-width: 100%; height: auto; border-radius: 12px; }
        .toggle-button-group .btn { min-width: 120px; }
        .CodeMirror { height: 420px; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/partials/header.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Template Designer</h1>
            <p class="text-muted mb-0">Create and manage custom HTML/CSS ID card templates.</p>
        </div>
        <a href="students.php" class="btn btn-outline-secondary">Back to students</a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger">
            <strong>Please fix the following issues:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($mode === 'list'): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h5 mb-1">Templates</h2>
                    <p class="text-muted mb-0">Manage unlimited front and back card layouts without changing source code.</p>
                </div>
                <div class="btn-group" role="group">
                    <a href="settings.php" class="btn btn-outline-secondary">Settings</a>
                    <a href="template-designer.php?edit=new" class="btn btn-primary">Create New Template</a>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h3 class="h6 mb-3">Import Template Package</h3>
                <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
                    <input type="hidden" name="action" value="import">
                    <div class="col-md-8">
                        <label class="form-label">Template Package</label>
                        <input type="file" name="template_package" class="form-control" accept=".ndctemplate,.zip" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-secondary">Import Template</button>
                    </div>
                </form>
                <p class="text-muted small mt-3 mb-0">Imported templates can be shared between environments and reused across sites.</p>
            </div>
        </div>

        <?php if ($templates === []): ?>
            <div class="alert alert-info">No templates yet. Create your first design.</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($templates as $item): ?>
                    <div class="col-lg-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h3 class="h6 mb-1"><?= htmlspecialchars((string) ($item['name'] ?? 'Untitled'), ENT_QUOTES, 'UTF-8') ?></h3>
                                        <p class="text-muted small mb-0"><?= htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge text-bg-secondary"><?= htmlspecialchars((string) ($item['status'] ?? 'draft'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (($item['id'] ?? '') === $defaultTemplateId): ?>
                                            <span class="badge bg-success">Default</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mt-3 d-flex flex-wrap gap-2">
                                    <a href="template-designer.php?preview=<?= urlencode((string) ($item['id'] ?? '')) ?>" class="btn btn-outline-primary btn-sm">Preview</a>
                                    <a href="template-designer.php?edit=<?= urlencode((string) ($item['id'] ?? '')) ?>" class="btn btn-outline-secondary btn-sm">Edit</a>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="duplicate">
                                        <input type="hidden" name="template_id" value="<?= htmlspecialchars((string) ($item['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <button class="btn btn-outline-info btn-sm" type="submit">Duplicate</button>
                                    </form>
                                    <a href="template-designer.php?export=<?= urlencode((string) ($item['id'] ?? '')) ?>" class="btn btn-outline-secondary btn-sm">Export</a>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="set_default">
                                        <input type="hidden" name="template_id" value="<?= htmlspecialchars((string) ($item['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <button class="btn btn-outline-warning btn-sm" type="submit" <?= (($item['id'] ?? '') === $defaultTemplateId) ? 'disabled' : '' ?>>Set Default</button>
                                    </form>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this template?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="template_id" value="<?= htmlspecialchars((string) ($item['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($mode === 'preview'): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h5 mb-1">Preview</h2>
                        <p class="text-muted mb-0">This preview mirrors the generated HTML output.</p>
                    </div>
                    <a href="template-designer.php" class="btn btn-outline-secondary">Back</a>
                </div>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <h3 class="h6">Front Preview</h3>
                        <div class="preview-frame">
                            <div class="preview-card-shell"><?= $frontPreview ?></div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <h3 class="h6">Back Preview</h3>
                        <div class="preview-frame">
                            <div class="preview-card-shell"><?= $backPreview ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-xl-7">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-3"><?= $selectedTemplateId === '' ? 'Create Template' : 'Edit Template' ?></h2>
                        <form method="post" enctype="multipart/form-data" id="templateDesignerForm">
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="template_id" value="<?= htmlspecialchars((string) ($template['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="created_at" value="<?= htmlspecialchars((string) ($template['created_at'] ?? date('Y-m-d H:i:s')), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="remove_front_background" id="remove_front_background" value="0">
                            <input type="hidden" name="remove_back_background" id="remove_back_background" value="0">

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Template Name</label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars((string) ($template['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="draft" <?= (($template['status'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>Draft</option>
                                        <option value="active" <?= (($template['status'] ?? 'draft') === 'active') ? 'selected' : '' ?>>Active</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars((string) ($template['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Front Background</label>
                                    <div class="background-preview mb-2" id="frontBackgroundPreview" style="background-image: url('<?= htmlspecialchars((string) $service->renderBackgroundImageTag($template['front_background_path'] ?? ''), ENT_QUOTES, 'UTF-8') ?>');">
                                        <?php if (empty($template['front_background_path'])): ?>
                                            No front background uploaded
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <label class="btn btn-outline-secondary btn-sm mb-0">
                                            Replace<input type="file" name="front_background" accept="image/jpeg,image/png,image/webp" class="form-control d-none" id="frontBackgroundInput">
                                        </label>
                                        <button type="button" class="btn btn-outline-danger btn-sm" id="removeFrontBackgroundBtn" <?= empty($template['front_background_path']) ? 'disabled' : '' ?>>Remove</button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Back Background</label>
                                    <div class="background-preview mb-2" id="backBackgroundPreview" style="background-image: url('<?= htmlspecialchars((string) $service->renderBackgroundImageTag($template['back_background_path'] ?? ''), ENT_QUOTES, 'UTF-8') ?>');">
                                        <?php if (empty($template['back_background_path'])): ?>
                                            No back background uploaded
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <label class="btn btn-outline-secondary btn-sm mb-0">
                                            Replace<input type="file" name="back_background" accept="image/jpeg,image/png,image/webp" class="form-control d-none" id="backBackgroundInput">
                                        </label>
                                        <button type="button" class="btn btn-outline-danger btn-sm" id="removeBackBackgroundBtn" <?= empty($template['back_background_path']) ? 'disabled' : '' ?>>Remove</button>
                                    </div>
                                </div>
                            </div>

                            <div class="card shadow-sm mb-4">
                                <div class="card-body">
                                    <div class="editor-toolbar mb-3">
                                        <button type="button" id="frontTab" class="btn btn-sm btn-outline-primary editor-tab active">Front HTML</button>
                                        <button type="button" id="backTab" class="btn btn-sm btn-outline-primary editor-tab">Back HTML</button>
                                        <div class="ms-auto text-muted small">Insert tags using the toolbox on the right.</div>
                                    </div>
                                    <div id="frontEditor" class="editor"></div>
                                    <div id="backEditor" class="editor d-none"></div>
                                    <textarea name="front_html" id="frontHtmlInput" class="form-control d-none"><?= htmlspecialchars((string) ($template['front_html'] ?? $service->defaultFrontHtml()), ENT_QUOTES, 'UTF-8') ?></textarea>
                                    <textarea name="back_html" id="backHtmlInput" class="form-control d-none"><?= htmlspecialchars((string) ($template['back_html'] ?? $service->defaultBackHtml()), ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mb-4">
                                <button type="submit" class="btn btn-primary">Save Template</button>
                                <a href="template-designer.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h2 class="h6 mb-3">Live Preview</h2>
                        <div class="btn-group toggle-button-group mb-3" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary active" data-preview-side="front">Front</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-preview-side="back">Back</button>
                        </div>
                        <div class="preview-frame" id="livePreviewFront"></div>
                        <div class="preview-frame d-none" id="livePreviewBack"></div>
                    </div>
                </div>
                <div class="card shadow-sm guide-card mb-3">
                    <div class="card-body">
                        <h2 class="h6">Tag Toolbox</h2>
                        <div class="tag-toolbox mt-3">
                            <div class="tag-toolbar">
                                <div class="fw-semibold mb-2">Student</div>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{student.full_name}}">Full Name</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{student.student_id}}">Student ID</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{student.photo}}">Photo</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{student.gender}}">Gender</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{student.date_of_birth}}">DOB</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{student.program}}">Program</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{student.department}}">Department</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{student.class_level}}">Class</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{student.qualification}}">Qualification</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{student.issue_date}}">Issue Date</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{student.expiry_date}}">Expiry Date</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{student.status}}">Status</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{student.signature}}">Signature</button>
                            </div>
                            <div class="tag-toolbar">
                                <div class="fw-semibold mb-2">Settings</div>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{organization.logo}}">Logo</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{organization.name}}">Name</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{organization.school_name}}">School Name</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{organization.campus_name}}">Campus</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{organization.address}}">Address</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{organization.phone}}">Phone</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{organization.email}}">Email</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{organization.website}}">Website</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{organization.academic_programs}}">Programs</button>
                            </div>
                            <div class="tag-toolbar">
                                <div class="fw-semibold mb-2">Card</div>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{card.qr_code}}">QR Code</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{card.barcode}}">Barcode</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{card.serial_number}}">Serial</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{card.verification_code}}">Verify Code</button>
                            </div>
                            <div class="tag-toolbar">
                                <div class="fw-semibold mb-2">Signature</div>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{authorized.signature}}">Signature Image</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{authorized.name}}">Signatory Name</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{principal.signature}}">Principal Sig.</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{principal.name}}">Principal Name</button>
                            </div>
                            <div class="tag-toolbar">
                                <div class="fw-semibold mb-2">Template</div>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{template.front_background}}">Front Background</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{template.back_background}}">Back Background</button>
                                <button type="button" class="btn btn-sm btn-outline-primary tag-button" data-tag="background-image:url('{{template.front_background}}');background-size:cover;background-position:center;">Front BG CSS</button>
                                <button type="button" class="btn btn-sm btn-outline-primary tag-button" data-tag="background-image:url('{{template.back_background}}');background-size:cover;background-position:center;">Back BG CSS</button>
                            </div>
                            <div class="tag-toolbar">
                                <div class="fw-semibold mb-2">Theme</div>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{theme.primary_color}}">Primary Color</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-button" data-tag="{{theme.secondary_color}}">Secondary Color</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm guide-card">
                    <div class="card-body">
                        <h2 class="h6">Template Guide</h2>
                        <p class="small mb-2">Upload front/back images above, then use the template background tags inside <code>background-image:url(...)</code>.</p>
                        <p class="small mb-1"><strong>Available tags</strong></p>
                        <div class="code-block mb-2">{{student.full_name}}<br>{{student.student_id}}<br>{{organization.logo}}<br>{{authorized.signature}}<br>{{card.qr_code}}<br>{{theme.primary_color}}</div>
                        <p class="small mb-1"><strong>Example</strong></p>
                        <div class="code-block">&lt;div style="background-image:url('{{template.front_background}}');"&gt;&lt;img src="{{organization.logo}}"&gt;&lt;h2&gt;{{student.full_name}}&lt;/h2&gt;&lt;/div&gt;</div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/addon/edit/closebrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/addon/edit/matchbrackets.min.js"></script>
<script>
    const previewCardWidth = 856;
    const previewCardHeight = 540;

    function syncPreviewScales() {
        document.querySelectorAll('.preview-frame').forEach(frame => {
            const shell = frame.querySelector('.preview-card-shell');

            if (shell) {
                shell.style.width = previewCardWidth + 'px';
                shell.style.height = previewCardHeight + 'px';
            }
        });
    }

    window.addEventListener('resize', syncPreviewScales);
    if ('ResizeObserver' in window) {
        document.querySelectorAll('.preview-frame').forEach(frame => {
            new ResizeObserver(syncPreviewScales).observe(frame);
        });
    }

    const frontEditorElement = document.getElementById('frontEditor');
    const backEditorElement = document.getElementById('backEditor');
    const frontHtmlInput = document.getElementById('frontHtmlInput');
    const backHtmlInput = document.getElementById('backHtmlInput');

    if (frontEditorElement && backEditorElement && frontHtmlInput && backHtmlInput) {
        const frontEditor = CodeMirror(frontEditorElement, {
            value: frontHtmlInput.value,
            mode: 'htmlmixed',
            lineNumbers: true,
            autoCloseBrackets: true,
            matchBrackets: true,
            theme: 'default',
        });

        const backEditor = CodeMirror(backEditorElement, {
            value: backHtmlInput.value,
            mode: 'htmlmixed',
            lineNumbers: true,
            autoCloseBrackets: true,
            matchBrackets: true,
            theme: 'default',
        });

        const debounce = (fn, delay) => {
            let timer;
            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => fn(...args), delay);
            };
        };

        const livePreviewValues = {
        'student.full_name': <?= json_encode($student['full_name'] ?? 'Student Name', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'student.student_id': <?= json_encode($student['student_number'] ?? 'BND001', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'student.gender': <?= json_encode($student['gender'] ?? 'Male', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'student.date_of_birth': <?= json_encode($student['date_of_birth'] ?? '1999-01-20', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'student.department': <?= json_encode($student['department'] ?? 'ICT', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'student.program': <?= json_encode($student['program'] ?? 'Software Development', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'student.class_level': <?= json_encode($student['class_level'] ?? 'Level 3', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'student.qualification': <?= json_encode($student['qualification'] ?? 'Certificate', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'student.issue_date': <?= json_encode($student['issue_date'] ?? '2026-01-20', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'student.expiry_date': <?= json_encode($student['expiry_date'] ?? '2027-01-20', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'student.status': <?= json_encode($student['status'] ?? 'Active', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'student.signature': '<div style="display:inline-block;width:140px;height:44px;border-bottom:2px solid #111;padding-top:24px;text-align:center;font-size:12px;color:#111;">Student signature</div>',
        'organization.name': <?= json_encode($organization['name'] ?? 'NDC', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'organization.school_name': <?= json_encode($organization['school_name'] ?? 'NDC', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'organization.campus_name': <?= json_encode($organization['campus_name'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'organization.academic_programs': <?= json_encode(nl2br(htmlspecialchars((string) ($organization['academic_programs'] ?? ''), ENT_QUOTES, 'UTF-8')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'organization.address': <?= json_encode($organization['address'] ?? 'Ntcheu', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'organization.phone': <?= json_encode($organization['phone'] ?? '+265 999 000 000', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'organization.email': <?= json_encode($organization['email'] ?? 'info@ndc.edu', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'organization.website': <?= json_encode($organization['website'] ?? 'https://ndc.edu', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'authorized.name': <?= json_encode($organization['authorized_name'] ?? 'Authorized Officer', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'authorized.signature': <?= json_encode($service->authorizedSignatureHtml($organization['authorized_signature_path'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'principal.name': <?= json_encode($organization['authorized_name'] ?? 'Authorized Officer', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'principal.signature': <?= json_encode($service->authorizedSignatureHtml($organization['authorized_signature_path'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'organization.signature': <?= json_encode($service->authorizedSignatureHtml($organization['authorized_signature_path'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'theme.primary_color': <?= json_encode($theme['primary_color'] ?? '#0b5ed7', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'theme.secondary_color': <?= json_encode($theme['secondary_color'] ?? '#0a7e8c', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'theme.accent_color': <?= json_encode($theme['accent_color'] ?? '#f4b400', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'template.front_background': <?= json_encode($service->renderBackgroundImageTag($template['front_background_path'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'template.back_background': <?= json_encode($service->renderBackgroundImageTag($template['back_background_path'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'card.qr_code': '<div style="display:inline-flex;align-items:center;justify-content:center;width:90px;height:90px;border:2px dashed #999;font-size:11px;color:#666;">QR</div>',
        'card.barcode': '<div style="display:inline-flex;align-items:center;justify-content:center;width:140px;height:44px;border:2px dashed #999;font-size:11px;color:#666;">Barcode</div>',
        'card.serial_number': <?= json_encode($student['student_number'] ?? 'BND001', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'card.verification_code': <?= json_encode($student['student_number'] ?? 'BND001', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        'student.photo': 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==',
        'organization.logo': <?= json_encode($service->renderImageTag($organization['logo_path'] ?? '', 'Organization logo'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    };

    function renderTemplateHtml(html) {
        let result = html;
        Object.entries(livePreviewValues).forEach(([key, value]) => {
            const pattern = new RegExp('\{\{\s*' + key.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&') + '\s*\}\}', 'g');
            result = result.replace(pattern, value);
        });
        return result;
    }

    function normalizeViewportFontSizes(html) {
        return html.replace(/font-size\s*:\s*([0-9]*\.?[0-9]+)vw/gi, (match, size) => {
            const normalized = (Number.parseFloat(size) * 13).toFixed(2).replace(/\.?0+$/, '');
            return 'font-size:' + normalized + 'px';
        });
    }

    function renderLivePreview() {
        const front = normalizeViewportFontSizes(renderTemplateHtml(frontEditor.getValue()));
        const back = normalizeViewportFontSizes(renderTemplateHtml(backEditor.getValue()));
        document.getElementById('livePreviewFront').innerHTML = wrapPreviewCard(front);
        document.getElementById('livePreviewBack').innerHTML = wrapPreviewCard(back);
        frontHtmlInput.value = frontEditor.getValue();
        backHtmlInput.value = backEditor.getValue();
        syncPreviewScales();
    }

    function wrapPreviewCard(html) {
        return '<div class="preview-card-shell"><div class="ndc-id-card-wrapper" style="width:856px;height:540px;min-width:856px;min-height:540px;max-width:856px;max-height:540px;aspect-ratio:856/540;box-sizing:border-box;overflow:hidden;position:relative;background:#fff;border:1px solid #d1d5db;border-radius:10px;display:block;">' + html + '</div></div>';
    }

    const debouncedRender = debounce(renderLivePreview, 450);
    frontEditor.on('change', debouncedRender);
    backEditor.on('change', debouncedRender);

    document.querySelectorAll('.tag-button').forEach(button => {
        button.addEventListener('click', () => {
            const tag = button.dataset.tag;
            const activeEditor = document.getElementById('frontEditor').classList.contains('d-none') ? backEditor : frontEditor;
            const doc = activeEditor.getDoc();
            const cursor = doc.getCursor();
            doc.replaceRange(tag, cursor);
            activeEditor.focus();
        });
    });

    document.querySelectorAll('[data-preview-side]').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-preview-side]').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            const side = button.dataset.previewSide;
            document.getElementById('livePreviewFront').classList.toggle('d-none', side !== 'front');
            document.getElementById('livePreviewBack').classList.toggle('d-none', side !== 'back');
            syncPreviewScales();
        });
    });

    document.getElementById('frontTab').addEventListener('click', () => {
        document.getElementById('frontEditor').classList.remove('d-none');
        document.getElementById('backEditor').classList.add('d-none');
        document.getElementById('frontTab').classList.add('active');
        document.getElementById('backTab').classList.remove('active');
        frontEditor.refresh();
    });

    document.getElementById('backTab').addEventListener('click', () => {
        document.getElementById('backEditor').classList.remove('d-none');
        document.getElementById('frontEditor').classList.add('d-none');
        document.getElementById('backTab').classList.add('active');
        document.getElementById('frontTab').classList.remove('active');
        backEditor.refresh();
    });

    document.getElementById('removeFrontBackgroundBtn').addEventListener('click', () => {
        document.getElementById('frontBackgroundPreview').style.backgroundImage = 'none';
        document.getElementById('frontBackgroundPreview').textContent = 'No front background uploaded';
        document.getElementById('remove_front_background').value = '1';
        document.getElementById('frontBackgroundInput').value = '';
        livePreviewValues['template.front_background'] = '';
        renderLivePreview();
    });

    document.getElementById('removeBackBackgroundBtn').addEventListener('click', () => {
        document.getElementById('backBackgroundPreview').style.backgroundImage = 'none';
        document.getElementById('backBackgroundPreview').textContent = 'No back background uploaded';
        document.getElementById('remove_back_background').value = '1';
        document.getElementById('backBackgroundInput').value = '';
        livePreviewValues['template.back_background'] = '';
        renderLivePreview();
    });

    function bindBackgroundPreview(inputId, previewId, removeId, removeFieldId, tagKey, emptyText) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        const removeButton = document.getElementById(removeId);
        const removeField = document.getElementById(removeFieldId);

        input.addEventListener('change', () => {
            const file = input.files && input.files[0] ? input.files[0] : null;
            if (!file) {
                return;
            }

            const reader = new FileReader();
            reader.addEventListener('load', () => {
                const imageUrl = String(reader.result || '');
                preview.style.backgroundImage = "url('" + imageUrl + "')";
                preview.textContent = '';
                removeField.value = '0';
                removeButton.disabled = false;
                livePreviewValues[tagKey] = imageUrl;
                renderLivePreview();
            });
            reader.readAsDataURL(file);
        });
    }

    bindBackgroundPreview('frontBackgroundInput', 'frontBackgroundPreview', 'removeFrontBackgroundBtn', 'remove_front_background', 'template.front_background', 'No front background uploaded');
    bindBackgroundPreview('backBackgroundInput', 'backBackgroundPreview', 'removeBackBackgroundBtn', 'remove_back_background', 'template.back_background', 'No back background uploaded');

    renderLivePreview();
    } else {
        syncPreviewScales();
    }
</script>
</body>
</html>
