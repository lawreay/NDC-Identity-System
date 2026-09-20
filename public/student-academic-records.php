<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/CardRepository.php';
require_once __DIR__ . '/../app/SettingsRepository.php';
require_once __DIR__ . '/../app/AcademicChecksheetService.php';
require_once __DIR__ . '/../app/PublicAcademicRecordRepository.php';
require_once __DIR__ . '/../app/Services/CardVerificationService.php';

use App\Services\CardVerificationService;

$guid = strtolower(trim((string) ($_GET['guid'] ?? '')));
$programmeId = (int) ($_GET['programme_id'] ?? 0);
$organizationName = 'NDC Identity System';
$error = '';
$card = null;
$checksheet = null;
$documents = [];
$documentsError = '';
$programmes = [];

try {
    $connection = Database::getConnection();
    try {
        $settings = (new SettingsRepository($connection))->getAll();
        $organizationName = trim((string) ($settings['organization_name'] ?? '')) ?: $organizationName;
    } catch (Throwable $ignored) {
    }

    $verification = (new CardVerificationService(new CardRepository($connection)))->verify($guid);
    if (($verification['state'] ?? '') !== 'VALID' || !is_array($verification['card'] ?? null)) {
        http_response_code(404);
        $error = 'A valid active student ID is required to view academic records.';
    } else {
        $card = $verification['card'];
        $studentId = (int) $card['student_id'];
        $checksheetService = new AcademicChecksheetService($connection);
        $programmes = $checksheetService->programmesForStudent($studentId);
        $checksheet = $checksheetService->build($studentId, $programmeId);
        if ($checksheet !== null && $programmeId === 0) {
            $programmeId = (int) $checksheet['programme']['id'];
        }
        try {
            $documents = (new PublicAcademicRecordRepository($connection))->issuedDocumentsForStudent($studentId);
        } catch (Throwable $exception) {
            error_log('Public academic document list failed: ' . $exception->getMessage());
            $documentsError = 'Issued documents are temporarily unavailable.';
        }
    }
} catch (Throwable $exception) {
    http_response_code(503);
    error_log('Public academic record lookup failed: ' . $exception->getMessage());
    $error = 'Academic records are temporarily unavailable.';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Records | <?= e($organizationName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
    <style>body{min-height:100vh;background:linear-gradient(145deg,#eef4fb,#f8fafc)}.record-wrap{max-width:1050px}.record-head{border-radius:18px;background:#153a70;color:#fff}.label{color:#64748b;font-size:.76rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase}</style>
</head>
<body class="py-4"><main class="container record-wrap">
    <a class="btn btn-outline-secondary btn-sm mb-3" href="verify.php?guid=<?= e($guid) ?>"><i class="bi bi-arrow-left me-1"></i>Back to ID verification</a>
    <?php if ($error !== ''): ?><div class="alert alert-warning"><?= e($error) ?></div><?php elseif ($checksheet !== null): ?>
        <?php $student = $checksheet['student']; $programme = $checksheet['programme']; $summary = $checksheet['summary']; ?>
        <section class="record-head shadow-sm p-4 p-md-5 mb-4"><div class="d-flex flex-wrap justify-content-between gap-3"><div><div class="text-uppercase small opacity-75">Verified student academic records</div><h1 class="h2 mb-1"><?= e(trim((string) $student['first_name'] . ' ' . (string) $student['last_name'])) ?></h1><div class="opacity-75"><?= e((string) $student['student_number']) ?> | <?= e((string) $programme['code'] . ' - ' . (string) $programme['name']) ?></div></div><div class="text-md-end"><div class="small opacity-75">Academic completion</div><div class="h3 mb-0"><?= $summary['is_complete'] ? 'COMPLETE' : 'IN PROGRESS' ?></div></div></div></section>
        <?php if ($programmes !== []): ?><form method="get" class="card shadow-sm mb-4"><div class="card-body d-flex flex-wrap gap-2 align-items-end"><input type="hidden" name="guid" value="<?= e($guid) ?>"><div class="flex-grow-1"><label class="form-label">Programme</label><select class="form-select" name="programme_id"><?php foreach ($programmes as $item): ?><option value="<?= (int) $item['programme_id'] ?>" <?= $programmeId === (int) $item['programme_id'] ? 'selected' : '' ?>><?= e((string) $item['code'] . ' - ' . (string) $item['name']) ?></option><?php endforeach; ?></select></div><button class="btn btn-primary">View checksheet</button></div></form><?php endif; ?>
        <div class="row g-3 mb-4"><div class="col-md-4"><div class="card h-100 shadow-sm"><div class="card-body"><div class="label">Required courses</div><strong class="fs-4"><?= (int) $summary['completed_required_courses'] ?> / <?= (int) $summary['required_courses'] ?></strong></div></div></div><div class="col-md-4"><div class="card h-100 shadow-sm"><div class="card-body"><div class="label">Completed credits</div><strong class="fs-4"><?= e(number_format((float) $summary['completed_credits'], 2)) ?></strong></div></div></div><div class="col-md-4"><div class="card h-100 shadow-sm"><div class="card-body"><div class="label">Outstanding required</div><strong class="fs-4"><?= (int) $summary['outstanding_required_courses'] ?></strong></div></div></div></div>
        <?php $checksheetPdfUrl = 'public-academic-checksheet.php?guid=' . rawurlencode($guid) . '&programme_id=' . $programmeId; ?>
        <section class="card shadow-sm mb-4"><div class="card-body"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h2 class="h5 mb-0">Academic checksheet</h2><div class="btn-group"><a class="btn btn-sm btn-primary" target="_blank" rel="noopener" href="<?= e($checksheetPdfUrl) ?>"><i class="bi bi-eye me-1"></i>View PDF</a><a class="btn btn-sm btn-outline-primary" href="<?= e($checksheetPdfUrl . '&download=1') ?>"><i class="bi bi-download me-1"></i>Download</a></div></div><div class="table-responsive"><table class="table table-striped align-middle mb-0"><thead><tr><th>Course</th><th>Credits</th><th>Approved result</th><th>Completion</th></tr></thead><tbody><?php foreach ($checksheet['courses'] as $course): ?><tr><td><strong><?= e((string) $course['code']) ?></strong><div class="small text-muted"><?= e((string) $course['name']) ?></div></td><td><?= e(number_format((float) $course['credits'], 2)) ?></td><td><?= $course['result_mark'] !== null ? e(number_format((float) $course['result_mark'], 2) . ' (' . (string) $course['result_grade'] . ')') : '<span class="text-muted">Not available</span>' ?></td><td><span class="badge text-bg-<?= $course['is_completed'] ? 'success' : 'secondary' ?>"><?= e((string) $course['requirement_status']) ?></span></td></tr><?php endforeach; ?></tbody></table></div></div></section>
        <section class="card shadow-sm"><div class="card-body"><h2 class="h5 mb-3">Issued documents</h2><?php if ($documentsError !== ''): ?><p class="text-warning mb-0"><?= e($documentsError) ?></p><?php elseif ($documents === []): ?><p class="text-muted mb-0">No issued certificates or transcripts are available.</p><?php else: ?><div class="vstack gap-3"><?php foreach ($documents as $document): ?><?php $type = (string) $document['document_type']; $documentUrl = 'public-academic-document.php?guid=' . rawurlencode($guid) . '&type=' . rawurlencode($type) . '&id=' . (int) $document['id']; ?><div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border rounded p-3"><div><strong><?= e(ucfirst($type)) ?></strong><div class="small text-muted"><?= e((string) $document['document_number']) ?> · Issued <?= e(date('d M Y', strtotime((string) $document['issued_at']))) ?></div></div><div class="btn-group"><a class="btn btn-primary" target="_blank" rel="noopener" href="<?= e($documentUrl) ?>"><i class="bi bi-eye me-1"></i>View</a><a class="btn btn-outline-primary" href="<?= e($documentUrl . '&download=1') ?>"><i class="bi bi-download me-1"></i>Download</a></div></div><?php endforeach; ?></div><?php endif; ?></div></section>
    <?php endif; ?>
</main></body></html>
