<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/StudentRepository.php';

$configPath = __DIR__ . '/../storage/id_templates.json';
$templateConfig = [];
if (file_exists($configPath)) {
    $decoded = json_decode((string) file_get_contents($configPath), true);
    if (is_array($decoded)) {
        $templateConfig = $decoded;
    }
}

$catalog = $templateConfig['id_templates'] ?? [];
$templates = is_array($catalog['templates'] ?? null) ? $catalog['templates'] : [];
$defaultTemplateId = (string) ($catalog['default_template'] ?? 'modern_blue');
$activeTemplateKey = (string) ($templateConfig['active'] ?? $defaultTemplateId);
$activeTemplate = null;
foreach ($templates as $template) {
    if (is_array($template) && (($template['id'] ?? '') === $activeTemplateKey || ($template['name'] ?? '') === $activeTemplateKey)) {
        $activeTemplate = $template;
        break;
    }
}
if (!$activeTemplate && isset($templates[0]) && is_array($templates[0])) {
    $activeTemplate = $templates[0];
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$action = trim((string) ($_GET['action'] ?? 'preview'));
$regenerate = ($action === 'regenerate');

try {
    $repository = new StudentRepository(Database::getConnection());
    $student = $repository->findById($id);
    if ($student) {
        if ($regenerate) {
            $studentNumber = $repository->generateStudentNumber((int) $student['id'], (string) ($student['first_name'] ?? ''), (string) ($student['last_name'] ?? ''));
        } else {
            $studentNumber = $repository->generateStudentNumber((int) $student['id'], (string) ($student['first_name'] ?? ''), (string) ($student['last_name'] ?? ''));
        }
        $student = $repository->findById($id);
        $student['student_number'] = $studentNumber;
    }
} catch (Throwable $exception) {
    $student = null;
}

if (!$student) {
    http_response_code(404);
    echo 'Student not found.';
    exit;
}

if ($action === 'download') {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="student-id-' . (int) $id . '.html"');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$fullName = trim(((string) ($student['first_name'] ?? '')) . ' ' . ((string) ($student['last_name'] ?? '')));
$photoPath = $student['photo_path'] ?? '';
$hasPhoto = is_string($photoPath) && trim($photoPath) !== '';
$photoUrl = $hasPhoto ? '/' . ltrim($photoPath, '/') : '';
$studentNumber = (string) ($student['student_number'] ?? '');
$program = (string) ($student['program'] ?? '');
$qualification = (string) ($student['qualification'] ?? '');
$classLevel = (string) ($student['class_level'] ?? '');
$status = (string) ($student['status'] ?? '');
$activeTemplateKey = (string) ($templateConfig['active'] ?? 'template_1');
$activeTemplate = $templateConfig['templates'][$activeTemplateKey] ?? null;
$templatePayload = is_array($activeTemplate['payload'] ?? null) ? $activeTemplate['payload'] : [];
$payload = $templatePayload['student_id_card'] ?? [];
$front = $payload['front'] ?? [];
$design = $payload['design'] ?? [];
$header = $front['header'] ?? [];
$studentFields = $front['student'] ?? [];
$verification = $front['verification'] ?? [];
$footer = $front['footer'] ?? [];
$primaryColor = (string) ($design['primary_color'] ?? '#0B5ED7');
$secondaryColor = (string) ($design['secondary_color'] ?? '#0A7E8C');
$accentColor = (string) ($design['accent_color'] ?? '#F4B400');
$cardTitle = (string) ($header['card_title'] ?? 'NDC Student ID');
$organizationName = (string) ($header['organization_name'] ?? 'Ntcheu Development Center');
$organizationShortName = (string) ($footer['organization_short_name'] ?? 'NDC');
$cardLayout = strtolower((string) ($activeTemplate['layout'] ?? 'standard'));
$cardLayoutClass = $cardLayout === 'portrait' ? 'layout-portrait' : ($cardLayout === 'landscape' ? 'layout-landscape' : 'layout-standard');
$templateMode = isset($templatePayload['fields']) ? 'field-list' : 'structured';
$visibleFields = [];
if (is_array($templatePayload['fields'] ?? null)) {
    foreach ($templatePayload['fields'] as $field) {
        if (!is_array($field)) {
            continue;
        }
        if (!empty($field['enabled'])) {
            $visibleFields[] = $field;
        }
    }
}
$fieldValue = function (string $key) use ($student, $studentNumber, $program, $qualification, $classLevel, $status): string {
    switch ($key) {
        case 'full_name':
            return trim(((string) ($student['first_name'] ?? '')) . ' ' . ((string) ($student['last_name'] ?? '')));
        case 'student_id':
            return (string) $studentNumber;
        case 'gender':
            return (string) ($student['gender'] ?? '');
        case 'qualification':
            return (string) $qualification;
        case 'program':
            return (string) $program;
        case 'department':
            return (string) ($student['department'] ?? '');
        case 'issue_date':
            return (string) date('Y-m-d');
        case 'expiry_date':
            return (string) date('Y-m-d', strtotime('+1 year'));
        case 'status':
            return (string) $status;
        case 'class_level':
            return (string) $classLevel;
        default:
            return '';
    }
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student ID Preview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f4f7fb 0%, #eef3f8 100%);
            min-height: 100vh;
        }
        .id-card {
            width: 100%;
            max-width: 760px;
            margin: 0 auto;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16);
            border: 1px solid #d9e3ef;
            background: #ffffff;
        }
        .id-header {
            background: linear-gradient(90deg, <?= htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8') ?> 0%, <?= htmlspecialchars($secondaryColor, ENT_QUOTES, 'UTF-8') ?> 100%);
            color: white;
            padding: 24px 28px;
        }
        .id-body {
            padding: 28px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }
        .id-card.layout-portrait {
            max-width: 560px;
        }
        .id-card.layout-landscape {
            max-width: 760px;
        }
        .id-card.layout-standard {
            max-width: 760px;
        }
        .template-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            background: rgba(11, 94, 215, 0.08);
            color: #0b5ed7;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }
        .summary-card {
            border: 1px solid #e6edf5;
            border-radius: 18px;
            padding: 1rem 1.1rem;
            background: linear-gradient(135deg, #ffffff 0%, #f7fbff 100%);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }
        .detail-card {
            border: 1px solid #e6edf5;
            border-radius: 16px;
            padding: 0.9rem 1rem;
            background: #ffffff;
            height: 100%;
        }
        .signature-placeholder {
            min-height: 70px;
            border: 1px dashed #bed2e7;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            background: #f8fbff;
            font-size: 0.9rem;
        }
        .student-photo {
            width: 140px;
            height: 170px;
            object-fit: cover;
            border-radius: 16px;
            border: 4px solid #eaf2fa;
            background: #f4f7fb;
        }
        .photo-placeholder {
            width: 140px;
            height: 170px;
            border-radius: 16px;
            border: 2px dashed #c7d7e8;
            background: #f8fbff;
            color: #6c757d;
        }
        .info-label {
            font-size: 0.78rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 0.2rem;
        }
        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: #12324c;
        }
        .chip {
            display: inline-block;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            background: #eaf5ff;
            color: #1565c0;
            font-weight: 600;
            font-size: 0.82rem;
        }
        .footer-bar {
            background: #f2f6fb;
            border-top: 1px solid #e3ebf4;
            padding: 16px 28px;
            color: #4b5b72;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .container {
                max-width: 100% !important;
                padding: 0 !important;
            }
            .btn, .d-flex.justify-content-between.align-items-center.mb-4 {
                display: none !important;
            }
            .id-card {
                box-shadow: none !important;
                border: 1px solid #d9e3ef;
                border-radius: 0;
                max-width: 100%;
            }
            .id-header, .id-body, .footer-bar {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">Student ID Preview</h1>
                <p class="text-muted mb-0">Professional preview only — no PDF or print output.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="student-profile.php?id=<?= (int) $id ?>" class="btn btn-outline-secondary">Back to profile</a>
                <a href="student-id-preview.php?id=<?= (int) $id ?>&action=preview" class="btn btn-outline-primary">Preview</a>
                <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
                <a href="student-id-preview.php?id=<?= (int) $id ?>&action=download" class="btn btn-success">Download</a>
                <a href="student-id-preview.php?id=<?= (int) $id ?>&action=regenerate" class="btn btn-warning text-dark">Regenerate</a>
            </div>
        </div>

        <div class="id-card <?= htmlspecialchars($cardLayoutClass, ENT_QUOTES, 'UTF-8') ?>">
            <div class="id-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h4 mb-1"><?= htmlspecialchars($cardTitle ?: 'NDC Student ID', ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($organizationName ?: 'Ntcheu Development Center', ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <span class="chip"><?= htmlspecialchars($status ?: 'Active', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>

            <?php if ($templateMode === 'field-list'): ?>
                <div class="id-body">
                    <div class="template-badge"><?= htmlspecialchars((string) ($activeTemplate['name'] ?? 'Template'), ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars(ucfirst($cardLayout), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="row g-4 align-items-start">
                        <div class="col-md-4 text-center">
                            <?php if ($hasPhoto): ?>
                                <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Student photo" class="student-photo">
                            <?php else: ?>
                                <div class="photo-placeholder d-flex flex-column justify-content-center align-items-center mx-auto">
                                    <div class="display-6 mb-2">📷</div>
                                    <small>No photo</small>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-8">
                            <div class="summary-card mb-3">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="info-label">Student Name</div>
                                        <div class="h4 mb-1 text-dark"><?= htmlspecialchars($fullName ?: 'Student Name', ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                    <span class="chip"><?= htmlspecialchars($status ?: 'Active', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="row g-2 mt-2">
                                    <div class="col-sm-6">
                                        <div class="info-label">Student Number</div>
                                        <div class="info-value"><?= htmlspecialchars($studentNumber ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="info-label">Program</div>
                                        <div class="info-value"><?= htmlspecialchars($program ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <?php foreach ($visibleFields as $field): ?>
                                    <?php
                                    $fieldKey = (string) ($field['key'] ?? '');
                                    $fieldLabel = (string) ($field['label'] ?? ucfirst($fieldKey));
                                    if ($fieldKey === 'photo') {
                                        continue;
                                    }
                                    $fieldValueText = $fieldValue($fieldKey);
                                    if ($fieldKey === 'qr_code') {
                                        echo '<div class="col-12"><div class="detail-card"><div class="info-label">' . htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') . '</div><div class="signature-placeholder mt-2">QR placeholder</div></div></div>';
                                    } elseif ($fieldKey === 'student_signature' || $fieldKey === 'authorized_signature') {
                                        echo '<div class="col-12"><div class="detail-card"><div class="info-label">' . htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') . '</div><div class="signature-placeholder mt-2">Signature line</div></div></div>';
                                    } else {
                                        echo '<div class="col-sm-6"><div class="detail-card"><div class="info-label">' . htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') . '</div><div class="info-value">' . htmlspecialchars($fieldValueText ?: '—', ENT_QUOTES, 'UTF-8') . '</div></div></div>';
                                    }
                                    ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="id-body">
                    <div class="row g-4 align-items-center">
                        <div class="col-md-4 text-center">
                            <?php if ($hasPhoto): ?>
                                <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Student photo" class="student-photo">
                            <?php else: ?>
                                <div class="photo-placeholder d-flex flex-column justify-content-center align-items-center mx-auto">
                                    <div class="display-6 mb-2">📷</div>
                                    <small>No photo</small>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-8">
                            <div class="mb-3">
                                <div class="info-label">Student Name</div>
                                <div class="h3 mb-0 text-dark"><?= htmlspecialchars($fullName ?: 'Student Name', ENT_QUOTES, 'UTF-8') ?></div>
                            </div>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="info-label">Student Number</div>
                                    <div class="info-value"><?= htmlspecialchars($studentNumber ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="info-label">Program</div>
                                    <div class="info-value"><?= htmlspecialchars($program ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="info-label">Qualification</div>
                                    <div class="info-value"><?= htmlspecialchars($qualification ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="info-label">Class Level</div>
                                    <div class="info-value"><?= htmlspecialchars($classLevel ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="footer-bar d-flex justify-content-between align-items-center">
                <span>Issued by <?= htmlspecialchars($organizationShortName ?: 'NDC', ENT_QUOTES, 'UTF-8') ?></span>
                <span>Preview only</span>
            </div>
        </div>
    </div>
</body>
</html>
