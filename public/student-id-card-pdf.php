<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/SettingsRepository.php';
require_once __DIR__ . '/../app/TemplateDesigner/TemplateDesignerService.php';
require_once __DIR__ . '/../app/Auth.php';

use App\Auth;

Auth::requireLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$templateId = trim((string) ($_GET['template'] ?? ''));
$autoPrint = isset($_GET['autoprint']) && (string) $_GET['autoprint'] === '1';

$service = new TemplateDesignerService();
$repository = new StudentRepository(Database::getConnection());
$settingsRepository = new SettingsRepository(Database::getConnection());

$message = '';
$student = null;
$frontPreview = '';
$backPreview = '';

try {
    $student = $repository->findById($id);
    if (!$student) {
        throw new RuntimeException('Student not found.');
    }

    $selectedTemplate = null;
    if ($templateId !== '') {
        $selectedTemplate = $service->getTemplate($templateId);
    }

    if ($selectedTemplate === null) {
        $selectedTemplate = $service->getDefaultTemplate();
        $templateId = $service->getDefaultTemplateId() ?? '';
    }

    if ($selectedTemplate === null) {
        throw new RuntimeException('No template selected and no default template is configured.');
    }

    if (empty(trim((string) ($student['student_number'] ?? '')))) {
        $student['student_number'] = $repository->generateStudentNumber(
            $id,
            (string) ($student['first_name'] ?? ''),
            (string) ($student['last_name'] ?? '')
        );
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
} catch (Throwable $exception) {
    $message = $exception->getMessage();
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$studentName = $student === null
    ? 'Student ID Card'
    : trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($studentName !== '' ? $studentName : 'Student ID Card') ?> - A4 PDF</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            background: #111827;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
        }

        .screen-toolbar {
            position: sticky;
            top: 0;
            z-index: 5;
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 14px;
            background: rgba(17, 24, 39, 0.92);
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }

        .screen-toolbar a,
        .screen-toolbar button {
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 6px;
            background: #fff;
            color: #111827;
            cursor: pointer;
            font: 700 14px/1 Arial, Helvetica, sans-serif;
            padding: 11px 14px;
            text-decoration: none;
        }

        .screen-toolbar .secondary {
            background: transparent;
            color: #fff;
        }

        .sheet-wrap {
            display: flex;
            justify-content: center;
            padding: 28px;
        }

        .a4-sheet {
            width: 210mm;
            min-height: 297mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 18mm;
            background: #000;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        .print-card-shell {
            width: min(856px, 100%);
            aspect-ratio: 856 / 540;
            height: auto;
            position: relative;
            overflow: hidden;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 0 0 0.35mm rgba(255, 255, 255, 0.85), 0 9mm 18mm rgba(0, 0, 0, 0.35);
        }

        .print-card-shell .ndc-id-card-wrapper {
            width: 100% !important;
            max-width: none !important;
            height: 100% !important;
            aspect-ratio: auto !important;
            border-radius: 10px !important;
        }

        .side-label {
            width: min(856px, 100%);
            margin-bottom: 3mm;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 1.8px;
            text-align: center;
            text-transform: uppercase;
        }

        .error {
            width: 210mm;
            min-height: 297mm;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24mm;
            background: #000;
            color: #fff;
            font-size: 16px;
            text-align: center;
        }

        @media print {
            html,
            body {
                width: 210mm;
                height: 297mm;
                background: #000;
            }

            .screen-toolbar {
                display: none;
            }

            .sheet-wrap {
                display: block;
                padding: 0;
            }

            .a4-sheet,
            .error {
                width: 210mm;
                height: 297mm;
                min-height: 297mm;
                box-shadow: none;
                page-break-after: avoid;
            }

            .print-card-shell {
                width: 85.6mm;
                height: 54mm;
                border-radius: 2mm;
                box-shadow: 0 0 0 0.35mm rgba(255, 255, 255, 0.9);
            }

            .print-card-shell .ndc-id-card-wrapper {
                width: 856px !important;
                height: 540px !important;
                transform: scale(0.377);
                transform-origin: top left;
            }
        }
    </style>
</head>
<body>
    <div class="screen-toolbar">
        <button type="button" onclick="window.print()">Download / Print PDF</button>
        <a class="secondary" href="student-id-card.php?id=<?= $id ?>&template=<?= urlencode($templateId) ?>">Back to preview</a>
    </div>

    <main class="sheet-wrap">
        <?php if ($message !== ''): ?>
            <div class="error"><?= escape($message) ?></div>
        <?php else: ?>
            <section class="a4-sheet" aria-label="A4 ID card PDF sheet">
                <div>
                    <div class="side-label">Front</div>
                    <div class="print-card-shell"><?= $frontPreview ?></div>
                </div>
                <div>
                    <div class="side-label">Back</div>
                    <div class="print-card-shell"><?= $backPreview ?></div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php if ($autoPrint && $message === ''): ?>
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    <?php endif; ?>
</body>
</html>
