<?php
require_once __DIR__ . '/../app/Database.php';

$configPath = __DIR__ . '/../storage/id_templates.json';
$templates = [];
if (file_exists($configPath)) {
    $decoded = json_decode((string) file_get_contents($configPath), true);
    if (is_array($decoded)) {
        $templates = $decoded;
    }
}

if (!isset($templates['templates']) || !is_array($templates['templates'])) {
    $templates = [
        'active' => 'template_1',
        'templates' => [
            'template_1' => [
                'name' => 'Template 1',
                'config' => [
                    'student_id_card' => [
                        'front' => [
                            'header' => [
                                'organization_logo' => true,
                                'organization_name' => 'Ntcheu Development Center',
                                'card_title' => 'STUDENT IDENTITY CARD'
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $templateKey = trim((string) ($_POST['template_key'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    $layout = trim((string) ($_POST['layout'] ?? 'standard'));

    if ($templateKey !== '' && $name !== '') {
        $templates['templates'][$templateKey] = [
            'name' => $name,
            'config' => [
                'student_id_card' => [
                    'front' => [
                        'header' => [
                            'organization_name' => $title,
                            'card_title' => strtoupper($name)
                        ]
                    ],
                    'design' => [
                        'orientation' => 'landscape',
                        'theme' => $layout === 'modern' ? 'modern' : 'standard',
                        'primary_color' => '#0B5ED7',
                        'secondary_color' => '#0A7E8C',
                        'accent_color' => '#F4B400'
                    ]
                ]
            ]
        ];
        $templates['active'] = $templateKey;
        file_put_contents($configPath, json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

$activeTemplateKey = (string) ($templates['active'] ?? 'template_1');
$activeTemplate = $templates['templates'][$activeTemplateKey] ?? $templates['templates']['template_1'] ?? null;
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Templates Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">ID Templates Settings</h1>
                <p class="text-muted mb-0">Manage multiple student ID template styles.</p>
            </div>
            <a href="student-profile.php?id=1" class="btn btn-outline-secondary">Back</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Create or update a template</h2>
                        <form method="post">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Template Key</label>
                                    <input type="text" name="template_key" class="form-control" placeholder="e.g. classic" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Template Name</label>
                                    <input type="text" name="name" class="form-control" placeholder="e.g. Classic" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="title" class="form-control" placeholder="NDC Student ID" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Layout</label>
                                    <select name="layout" class="form-select">
                                        <option value="standard">Standard</option>
                                        <option value="modern">Modern</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3">Save template</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Saved templates</h2>
                        <?php if (empty($templates['templates'])): ?>
                            <p class="text-muted mb-0">No templates saved yet.</p>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($templates['templates'] as $key => $template): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= htmlspecialchars((string) ($template['name'] ?? $key), ENT_QUOTES, 'UTF-8') ?></strong>
                                            <div class="small text-muted"><?= htmlspecialchars((string) (($template['config']['student_id_card']['front']['header']['organization_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                                        </div>
                                        <?php if ($activeTemplateKey === $key): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($activeTemplate): ?>
            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">Current active template</h2>
                    <div class="p-3 rounded border" style="background:#f8fbff;">
                        <strong><?= htmlspecialchars((string) ($activeTemplate['name'] ?? 'Template'), ENT_QUOTES, 'UTF-8') ?></strong><br>
                        <span class="text-muted"><?= htmlspecialchars((string) (($activeTemplate['config']['student_id_card']['front']['header']['organization_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
