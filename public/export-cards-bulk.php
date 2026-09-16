<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/CardRepository.php';
require_once __DIR__ . '/../app/SettingsRepository.php';
require_once __DIR__ . '/../app/TemplateDesigner/TemplateDesignerService.php';
require_once __DIR__ . '/../app/Services/CardVerificationService.php';

use App\Auth;
use App\Services\CardVerificationService;

Auth::requireLogin();

try {
    Auth::requireCsrf();
} catch (Throwable $exception) {
    http_response_code(403);
    exit('Security token invalid. Please try again.');
}

$format = (string) ($_POST['export_format'] ?? '');
$templateId = trim((string) ($_POST['template_id'] ?? ''));
$submittedIds = $_POST['student_ids'] ?? [];
if (!in_array($format, ['pdf', 'png_zip'], true) || !is_array($submittedIds)) {
    http_response_code(400);
    exit('Invalid bulk export request.');
}

$studentIds = array_values(array_unique(array_filter(
    array_map('intval', $submittedIds),
    static fn (int $id): bool => $id > 0
)));
// Keep exports responsive and avoid generating an unbounded download in one request.
if ($studentIds === [] || count($studentIds) > 250) {
    http_response_code(400);
    exit($studentIds === [] ? 'Select at least one student to export.' : 'You can export up to 250 students at a time.');
}

try {
    $connection = Database::getConnection();
    $repository = new StudentRepository($connection);
    $students = $repository->findByIds($studentIds);
    if ($students === []) {
        http_response_code(404);
        exit('No selected students were found.');
    }

    foreach ($students as &$student) {
        if (trim((string) ($student['student_number'] ?? '')) === '') {
            $student['student_number'] = $repository->generateStudentNumber(
                (int) $student['id'],
                (string) ($student['first_name'] ?? ''),
                (string) ($student['last_name'] ?? '')
            );
        }
        $student['full_name'] = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
    }
    unset($student);

    $templateService = new TemplateDesignerService();
    $template = $templateId !== ''
        ? $templateService->getTemplate($templateId)
        : $templateService->getDefaultTemplate();
    if (!$template) {
        throw new RuntimeException($templateId !== ''
            ? 'The selected template could not be loaded.'
            : 'No default template configured. Please set a default template in the Template Designer.');
    }

    $appSettings = (new SettingsRepository($connection))->getAll();
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
    $verificationService = new CardVerificationService(new CardRepository($connection));
    foreach ($students as &$student) {
        $student = $verificationService->issueForStudent($student, (string) ($appSettings['verification_endpoint'] ?? ''));
    }
    unset($student);
    $theme = SettingsRepository::themeFromSettings($appSettings);

    $renderedCards = [];
    foreach ($students as $index => $student) {
        $studentNumber = trim((string) ($student['student_number'] ?? ''));
        $safeNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentNumber) ?: 'student_' . (int) ($student['id'] ?? $index + 1);
        foreach (['front', 'back'] as $side) {
            $renderedCards[] = [
                'filename' => sprintf('%03d_card_%s_%s.png', $index + 1, $safeNumber, $side),
                'html' => $templateService->renderTemplate($template, $student, $organization, $theme, $side),
            ];
        }
    }
} catch (RuntimeException $exception) {
    http_response_code(500);
    echo 'Bulk export failed: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
} catch (Throwable $exception) {
    error_log('Bulk card export error: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine() . PHP_EOL . $exception->getTraceAsString());
    http_response_code(500);
    echo 'An unexpected error occurred. Please try again.';
    exit;
}

$downloadName = 'student_ids_' . date('Ymd_His') . ($format === 'pdf' ? '.pdf' : '.zip');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preparing Bulk ID Export</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f6f8fb; }
        #bulkCards { position: absolute; left: -10000px; top: 0; width: 856px; }
        .bulk-export-card { width: 856px; height: 540px; overflow: hidden; background: #fff; }
    </style>
</head>
<body>
    <main class="container py-5">
        <div class="card shadow-sm mx-auto" style="max-width: 560px;">
            <div class="card-body p-4 text-center">
                <div class="spinner-border text-primary mb-3" role="status"><span class="visually-hidden">Preparing export</span></div>
                <h1 class="h4">Preparing bulk <?= $format === 'pdf' ? 'PDF' : 'PNG ZIP' ?></h1>
                <p id="exportStatus" class="text-muted mb-0">Loading <?= count($renderedCards) ?> card sides with the selected template...</p>
                <div id="exportError" class="alert alert-danger text-start mt-3 d-none"></div>
                <a href="students.php" class="btn btn-outline-secondary mt-4">Back to students</a>
            </div>
        </div>
    </main>

    <div id="bulkCards" aria-hidden="true">
        <?php foreach ($renderedCards as $card): ?>
            <div class="bulk-export-card" data-filename="<?= htmlspecialchars((string) $card['filename'], ENT_QUOTES, 'UTF-8') ?>"><?= $card['html'] ?></div>
        <?php endforeach; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <?php if ($format === 'png_zip'): ?>
        <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
    <?php endif; ?>
    <script>
        (() => {
            const format = <?= json_encode($format, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const downloadName = <?= json_encode($downloadName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const cards = Array.from(document.querySelectorAll('.bulk-export-card'));
            const status = document.getElementById('exportStatus');
            const errorBox = document.getElementById('exportError');
            const cardWidth = 856;
            const cardHeight = 540;

            const waitForImages = async card => {
                const images = Array.from(card.querySelectorAll('img'));
                await Promise.race([
                    Promise.all(images.map(image => image.complete ? Promise.resolve() : new Promise(resolve => {
                        image.addEventListener('load', resolve, { once: true });
                        image.addEventListener('error', resolve, { once: true });
                    }))),
                    new Promise(resolve => window.setTimeout(resolve, 6000)),
                ]);
            };

            const captureCard = async card => {
                await waitForImages(card);
                return window.html2canvas(card, {
                    backgroundColor: '#ffffff',
                    scale: 1,
                    width: cardWidth,
                    height: cardHeight,
                    logging: false,
                    useCORS: true,
                });
            };

            const canvasBlob = canvas => new Promise((resolve, reject) => {
                canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('Unable to create a card PNG.')), 'image/png');
            });

            const download = (blob, filename) => {
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.setTimeout(() => URL.revokeObjectURL(url), 1000);
            };

            const updateStatus = (index, label) => {
                status.textContent = label + ' ' + (index + 1) + ' of ' + cards.length + ' card sides...';
            };

            const createPdf = async () => {
                if (!window.jspdf || typeof window.jspdf.jsPDF !== 'function') {
                    throw new Error('The PDF export library could not be loaded. Check your internet connection and try again.');
                }
                const pdf = new window.jspdf.jsPDF({ orientation: 'landscape', unit: 'mm', format: [85.6, 53.98], compress: true });
                for (let index = 0; index < cards.length; index += 1) {
                    updateStatus(index, 'Rendering');
                    const canvas = await captureCard(cards[index]);
                    if (index > 0) {
                        pdf.addPage([85.6, 53.98], 'landscape');
                    }
                    pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, 85.6, 53.98);
                    canvas.width = 1;
                    canvas.height = 1;
                }
                status.textContent = 'Downloading PDF...';
                pdf.save(downloadName);
            };

            const createPngZip = async () => {
                if (typeof window.JSZip !== 'function') {
                    throw new Error('The ZIP export library could not be loaded. Check your internet connection and try again.');
                }
                const zip = new window.JSZip();
                for (let index = 0; index < cards.length; index += 1) {
                    updateStatus(index, 'Rendering');
                    const canvas = await captureCard(cards[index]);
                    zip.file(cards[index].dataset.filename || ('card_' + (index + 1) + '.png'), await canvasBlob(canvas));
                    canvas.width = 1;
                    canvas.height = 1;
                }
                status.textContent = 'Building ZIP download...';
                download(await zip.generateAsync({ type: 'blob', compression: 'DEFLATE', compressionOptions: { level: 6 } }), downloadName);
            };

            window.addEventListener('load', async () => {
                try {
                    if (cards.length === 0) {
                        throw new Error('No ID cards were available for export.');
                    }
                    if (typeof window.html2canvas !== 'function') {
                        throw new Error('The card rendering library could not be loaded. Check your internet connection and try again.');
                    }
                    if (format === 'pdf') {
                        await createPdf();
                    } else {
                        await createPngZip();
                    }
                    status.textContent = 'Your download has started.';
                } catch (error) {
                    const message = error instanceof Error ? error.message : 'Unable to create the bulk export.';
                    status.textContent = 'The export could not be completed.';
                    errorBox.textContent = message;
                    errorBox.classList.remove('d-none');
                }
            });
        })();
    </script>
</body>
</html>
