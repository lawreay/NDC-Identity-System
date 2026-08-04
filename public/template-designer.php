<?php
require_once __DIR__ . '/../app/TemplateDesigner/TemplateDesignerService.php';

$service = new TemplateDesignerService();
$errors = [];
$success = '';
$template = null;
$mode = 'list';
$selectedTemplateId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    $templateId = trim((string) ($_POST['template_id'] ?? ''));

    if ($action === 'delete' && $templateId !== '') {
        $service->deleteTemplate($templateId);
        $success = 'Template deleted.';
        $mode = 'list';
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
    'name' => 'NDC',
    'address' => 'Ntcheu',
    'phone' => '+265 999 000 000',
    'email' => 'info@ndc.edu',
    'website' => 'https://ndc.edu',
    'logo_path' => '',
    'authorized_name' => 'Authorized Officer',
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
    <style>
        body { background: #f6f8fb; }
        .preview-frame { border: 1px solid #d9e2ef; border-radius: 12px; background: #fff; min-height: 320px; padding: 16px; }
        .guide-card { border-left: 4px solid #0d6efd; }
        .code-block { background: #111827; color: #f9fafb; padding: 12px; border-radius: 10px; overflow-x: auto; }
        textarea.form-control { min-height: 280px; font-family: Consolas, monospace; }
    </style>
</head>
<body>
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
                <a href="template-designer.php?edit=new" class="btn btn-primary">Create New Template</a>
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
                                    <span class="badge text-bg-secondary"><?= htmlspecialchars((string) ($item['status'] ?? 'draft'), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="mt-3 d-flex gap-2">
                                    <a href="template-designer.php?preview=<?= urlencode((string) ($item['id'] ?? '')) ?>" class="btn btn-outline-primary btn-sm">Preview</a>
                                    <a href="template-designer.php?edit=<?= urlencode((string) ($item['id'] ?? '')) ?>" class="btn btn-outline-secondary btn-sm">Edit</a>
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
                            <?= $frontPreview ?>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <h3 class="h6">Back Preview</h3>
                        <div class="preview-frame">
                            <?= $backPreview ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h5 mb-3"><?= $selectedTemplateId === '' ? 'Create Template' : 'Edit Template' ?></h2>
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="template_id" value="<?= htmlspecialchars((string) ($template['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="created_at" value="<?= htmlspecialchars((string) ($template['created_at'] ?? date('Y-m-d H:i:s')), ENT_QUOTES, 'UTF-8') ?>">

                            <div class="row g-3">
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
                                <div class="col-md-6">
                                    <label class="form-label">Front Background Image</label>
                                    <input type="file" name="front_background" class="form-control" accept="image/jpeg,image/png,image/webp">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Back Background Image</label>
                                    <input type="file" name="back_background" class="form-control" accept="image/jpeg,image/png,image/webp">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Front HTML</label>
                                    <textarea name="front_html" class="form-control" rows="16"><?= htmlspecialchars((string) ($template['front_html'] ?? $service->defaultFrontHtml()), ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Back HTML</label>
                                    <textarea name="back_html" class="form-control" rows="16"><?= htmlspecialchars((string) ($template['back_html'] ?? $service->defaultBackHtml()), ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                            </div>

                            <div class="mt-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Save Template</button>
                                <a href="template-designer.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm guide-card mb-3">
                    <div class="card-body">
                        <h2 class="h6">Template Development Guide</h2>
                        <div class="accordion" id="guideAccordion">
                            <div class="accordion-item">
                                <h3 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#guideBackground">
                                        Background Images
                                    </button>
                                </h3>
                                <div id="guideBackground" class="accordion-collapse collapse show" data-bs-parent="#guideAccordion">
                                    <div class="accordion-body">
                                        <p class="small mb-2">Use <code>{{template.front_background}}</code> for the front design and <code>{{template.back_background}}</code> for the back design.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h3 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#guideTags">
                                        Available Tags
                                    </button>
                                </h3>
                                <div id="guideTags" class="accordion-collapse collapse" data-bs-parent="#guideAccordion">
                                    <div class="accordion-body">
                                        <div class="small">
                                            <p class="fw-semibold mb-1">Student</p>
                                            <div class="code-block">{{student.photo}}<br>{{student.full_name}}<br>{{student.student_id}}<br>{{student.gender}}<br>{{student.department}}<br>{{student.program}}<br>{{student.class_level}}<br>{{student.qualification}}<br>{{student.issue_date}}<br>{{student.expiry_date}}<br>{{student.status}}<br>{{student.signature}}</div>
                                            <p class="fw-semibold mt-3 mb-1">Organization</p>
                                            <div class="code-block">{{organization.logo}}<br>{{organization.name}}<br>{{organization.address}}<br>{{organization.phone}}<br>{{organization.email}}<br>{{organization.website}}</div>
                                            <p class="fw-semibold mt-3 mb-1">QR</p>
                                            <div class="code-block">{{card.qr_code}}<br>{{card.barcode}}<br>{{card.serial_number}}<br>{{card.verification_code}}</div>
                                            <p class="fw-semibold mt-3 mb-1">Authorized</p>
                                            <div class="code-block">{{authorized.signature}}<br>{{authorized.name}}</div>
                                            <p class="fw-semibold mt-3 mb-1">Theme</p>
                                            <div class="code-block">{{theme.primary_color}}<br>{{theme.secondary_color}}<br>{{theme.accent_color}}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h3 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#guideExamples">
                                        Example Layouts
                                    </button>
                                </h3>
                                <div id="guideExamples" class="accordion-collapse collapse" data-bs-parent="#guideAccordion">
                                    <div class="accordion-body">
                                        <p class="small mb-2"><strong>Front HTML example</strong></p>
                                        <div class="code-block">&lt;div style="padding:24px;background-image:url('{{template.front_background}}');"&gt;<br>  &lt;img src="{{organization.logo}}" alt="logo" style="max-height:56px;"&gt;<br>  &lt;img src="{{student.photo}}" alt="student" style="width:120px;height:140px;object-fit:cover;"&gt;<br>  &lt;h2&gt;{{student.full_name}}&lt;/h2&gt;<br>  &lt;p&gt;{{student.student_id}}&lt;/p&gt;<br>  &lt;div&gt;{{card.qr_code}}&lt;/div&gt;<br>&lt;/div&gt;</div>
                                        <p class="small mt-3 mb-2"><strong>Back HTML example</strong></p>
                                        <div class="code-block">&lt;div style="padding:24px;background-image:url('{{template.back_background}}');"&gt;<br>  &lt;h3&gt;Institution Details&lt;/h3&gt;<br>  &lt;p&gt;{{organization.name}}&lt;/p&gt;<br>  &lt;p&gt;{{organization.address}}&lt;/p&gt;<br>  &lt;div&gt;{{student.signature}}&lt;/div&gt;<br>&lt;/div&gt;</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
