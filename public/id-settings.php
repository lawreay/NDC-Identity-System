<?php
require_once __DIR__ . '/../app/Database.php';

function writeTemplatesConfig(string $configPath, array $templates): void
{
    file_put_contents($configPath, json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function defaultFieldList(): array
{
    return [
        ['key' => 'full_name', 'label' => 'Full Name', 'enabled' => true, 'required' => true],
        ['key' => 'student_id', 'label' => 'Student ID', 'enabled' => true, 'required' => true],
        ['key' => 'program', 'label' => 'Program', 'enabled' => true, 'required' => false],
        ['key' => 'status', 'label' => 'Status', 'enabled' => true, 'required' => false],
    ];
}

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
                'title' => 'STUDENT IDENTITY CARD',
                'accent' => '#0B5ED7',
                'secondary' => '#0A7E8C',
                'layout' => 'landscape',
                'payload' => [
                    'student_id_card' => [
                        'front' => [
                            'header' => [
                                'organization_logo' => true,
                                'organization_name' => 'Ntcheu Development Center',
                                'card_title' => 'STUDENT IDENTITY CARD'
                            ],
                            'student' => [
                                'photo' => true,
                                'full_name' => true,
                                'student_id' => true,
                                'gender' => true,
                                'qualification' => true,
                                'program' => true,
                                'department' => true,
                                'issue_date' => true,
                                'expiry_date' => true,
                                'status' => true
                            ],
                            'verification' => [
                                'qr_code' => true,
                                'show_verification_text' => 'Scan to Verify',
                                'show_raw_url' => false
                            ],
                            'footer' => [
                                'organization_short_name' => 'NDC'
                            ]
                        ],
                        'back' => [
                            'header' => [
                                'organization_logo' => true,
                                'title' => 'CARD INFORMATION'
                            ],
                            'notice' => [
                                'enabled' => true,
                                'text' => 'This card remains the property of Ntcheu Development Center. If found, please return it to the address below.'
                            ],
                            'organization' => [
                                'address' => true,
                                'phone' => true,
                                'email' => true,
                                'website' => true
                            ],
                            'signatures' => [
                                'student_signature' => true,
                                'authorized_signature' => true
                            ],
                            'verification' => [
                                'qr_code' => true,
                                'verification_code' => true
                            ]
                        ],
                        'design' => [
                            'orientation' => 'landscape',
                            'card_size' => 'CR80',
                            'rounded_corners' => true,
                            'theme' => 'modern',
                            'photo_shape' => 'rounded_rectangle',
                            'show_watermark' => true,
                            'show_security_pattern' => true,
                            'show_hologram_placeholder' => true,
                            'primary_color' => '#0B5ED7',
                            'secondary_color' => '#0A7E8C',
                            'accent_color' => '#F4B400'
                        ]
                    ]
                ]
            ],
            'template_2' => [
                'name' => 'Template 2',
                'title' => 'Student ID Template 2',
                'accent' => '#1d4ed8',
                'secondary' => '#0f766e',
                'layout' => 'portrait',
                'payload' => [
                    'fields' => [
                        [
                            'key' => 'photo',
                            'label' => 'Photo',
                            'enabled' => true,
                            'required' => true
                        ],
                        [
                            'key' => 'full_name',
                            'label' => 'Full Name',
                            'enabled' => true,
                            'required' => true
                        ],
                        [
                            'key' => 'student_id',
                            'label' => 'Student ID',
                            'enabled' => true,
                            'required' => true
                        ],
                        [
                            'key' => 'gender',
                            'label' => 'Gender',
                            'enabled' => true
                        ],
                        [
                            'key' => 'qualification',
                            'label' => 'Qualification',
                            'enabled' => true
                        ],
                        [
                            'key' => 'program',
                            'label' => 'Program',
                            'enabled' => true
                        ],
                        [
                            'key' => 'department',
                            'label' => 'Department',
                            'enabled' => true
                        ],
                        [
                            'key' => 'issue_date',
                            'label' => 'Issue Date',
                            'enabled' => true
                        ],
                        [
                            'key' => 'expiry_date',
                            'label' => 'Expiry Date',
                            'enabled' => true
                        ],
                        [
                            'key' => 'status',
                            'label' => 'Status',
                            'enabled' => true
                        ],
                        [
                            'key' => 'qr_code',
                            'label' => 'QR Code',
                            'enabled' => true
                        ],
                        [
                            'key' => 'student_signature',
                            'label' => 'Student Signature',
                            'enabled' => true
                        ],
                        [
                            'key' => 'authorized_signature',
                            'label' => 'Authorized Signature',
                            'enabled' => true
                        ]
                    ]
                ]
            ]
        ]
    ];
}

if (isset($_GET['activate']) && is_string($_GET['activate'])) {
    $activateKey = trim((string) $_GET['activate']);
    if ($activateKey !== '' && isset($templates['templates'][$activateKey])) {
        $templates['active'] = $activateKey;
        writeTemplatesConfig($configPath, $templates);
        header('Location: id-settings.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = trim((string) ($_POST['form_action'] ?? 'save_template'));

    if ($formAction === 'save_fields') {
        $editTemplateKey = trim((string) ($_POST['edit_template_key'] ?? ''));
        if ($editTemplateKey !== '' && isset($templates['templates'][$editTemplateKey])) {
            $fieldKeys = $_POST['field_key'] ?? [];
            $fieldLabels = $_POST['field_label'] ?? [];
            $fieldEnabled = $_POST['field_enabled'] ?? [];
            $fieldRequired = $_POST['field_required'] ?? [];
            $fields = [];
            $count = max(count($fieldKeys), count($fieldLabels));

            for ($index = 0; $index < $count; $index++) {
                $key = trim((string) ($fieldKeys[$index] ?? ''));
                $label = trim((string) ($fieldLabels[$index] ?? ''));
                if ($key === '' && $label === '') {
                    continue;
                }

                if ($key === '') {
                    $key = strtolower(str_replace(' ', '_', $label));
                }

                $enabled = isset($fieldEnabled[$index]) && ((string) $fieldEnabled[$index]) === '1';
                $required = isset($fieldRequired[$index]) && ((string) $fieldRequired[$index]) === '1';

                $fields[] = [
                    'key' => $key,
                    'label' => $label ?: ucfirst(str_replace('_', ' ', $key)),
                    'enabled' => $enabled,
                    'required' => $required,
                ];
            }

            if ($fields === []) {
                $fields = defaultFieldList();
            }

            $templates['templates'][$editTemplateKey]['payload']['fields'] = $fields;
            $templates['active'] = $editTemplateKey;
            writeTemplatesConfig($configPath, $templates);
        }
    } else {
        $templateKey = trim((string) ($_POST['template_key'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $accent = trim((string) ($_POST['accent'] ?? '#0f4c81'));
        $secondary = trim((string) ($_POST['secondary'] ?? '#1d6fb8'));
        $layout = trim((string) ($_POST['layout'] ?? 'standard'));

        if ($templateKey !== '' && $name !== '' && $title !== '') {
            $existingTemplate = $templates['templates'][$templateKey] ?? [];
            $payload = is_array($existingTemplate['payload'] ?? null) ? $existingTemplate['payload'] : [];
            $templates['templates'][$templateKey] = [
                'name' => $name,
                'title' => $title,
                'accent' => $accent ?: '#0f4c81',
                'secondary' => $secondary ?: '#1d6fb8',
                'layout' => $layout ?: 'standard',
                'payload' => $payload,
            ];
            $templates['active'] = $templateKey;
            writeTemplatesConfig($configPath, $templates);
        }
    }
}

$activeTemplateKey = (string) ($templates['active'] ?? 'template_1');
$activeTemplate = $templates['templates'][$activeTemplateKey] ?? $templates['templates']['template_1'] ?? null;
$editorTemplateKey = $activeTemplateKey;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_template_key'])) {
    $editorTemplateKey = trim((string) $_POST['edit_template_key']);
}
$editorTemplate = $templates['templates'][$editorTemplateKey] ?? null;
$editorFields = [];
if (is_array($editorTemplate['payload']['fields'] ?? null)) {
    $editorFields = $editorTemplate['payload']['fields'];
} elseif (is_array($editorTemplate['payload']['student_id_card']['front']['student'] ?? null)) {
    $editorFields = [];
    foreach ($editorTemplate['payload']['student_id_card']['front']['student'] as $key => $value) {
        $editorFields[] = ['key' => $key, 'label' => ucfirst(str_replace('_', ' ', $key)), 'enabled' => (bool) $value, 'required' => false];
    }
} else {
    $editorFields = defaultFieldList();
}
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
                                <div class="col-md-6">
                                    <label class="form-label">Accent Color</label>
                                    <input type="color" name="accent" class="form-control form-control-color" value="#0f4c81">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Secondary Color</label>
                                    <input type="color" name="secondary" class="form-control form-control-color" value="#1d6fb8">
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
                                            <div class="small text-muted"><?= htmlspecialchars((string) ($template['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($activeTemplateKey === $key): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <a href="id-settings.php?activate=<?= urlencode((string) $key) ?>" class="btn btn-sm btn-outline-primary">Activate</a>
                                            <?php endif; ?>
                                        </div>
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
                        <span class="text-muted"><?= htmlspecialchars((string) ($activeTemplate['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mt-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Edit visible fields</h2>
                <p class="text-muted small mb-3">Choose a template and update which fields should appear in the preview card.</p>
                <form method="post">
                    <input type="hidden" name="form_action" value="save_fields">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label">Template</label>
                            <select name="edit_template_key" class="form-select">
                                <?php foreach ($templates['templates'] as $key => $template): ?>
                                    <option value="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>" <?= $editorTemplateKey === $key ? 'selected' : '' ?>><?= htmlspecialchars((string) ($template['name'] ?? $key), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">Save fields</button>
                        </div>
                    </div>

                    <div class="mt-4">
                        <?php foreach ($editorFields as $index => $field): ?>
                            <div class="row g-2 align-items-end mb-2">
                                <div class="col-md-3">
                                    <label class="form-label small">Field key</label>
                                    <input type="text" name="field_key[]" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($field['key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Label</label>
                                    <input type="text" name="field_label[]" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($field['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" name="field_enabled[]" value="1" <?= !empty($field['enabled']) ? 'checked' : '' ?>>
                                        <label class="form-check-label small">Enabled</label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" name="field_required[]" value="1" <?= !empty($field['required']) ? 'checked' : '' ?>>
                                        <label class="form-check-label small">Required</label>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-3">
                                <input type="text" name="field_key[]" class="form-control form-control-sm" placeholder="new_field_key">
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="field_label[]" class="form-control form-control-sm" placeholder="New field label">
                            </div>
                            <div class="col-md-2">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="field_enabled[]" value="1" checked>
                                    <label class="form-check-label small">Enabled</label>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="field_required[]" value="1">
                                    <label class="form-check-label small">Required</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
