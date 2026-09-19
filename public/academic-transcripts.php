<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/AcademicProgrammeRepository.php';
require_once __DIR__ . '/../app/AcademicTranscriptRepository.php';

use App\Auth;

$user = Auth::requireRole('Administrator');
$errors = [];
$success = '';

try {
    $connection = Database::getConnection();
    $students = (new StudentRepository($connection))->search('');
    $programmes = (new AcademicProgrammeRepository($connection))->active();
    $repository = new AcademicTranscriptRepository($connection);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Auth::requireCsrf();
        $action = (string) ($_POST['action'] ?? 'create');
        $transcriptId = (int) ($_POST['transcript_id'] ?? 0);
        if ($action === 'create') {
            $id = $repository->createDraft((int) ($_POST['student_id'] ?? 0), (int) ($_POST['programme_id'] ?? 0), (int) ($user['id'] ?? 0));
            $success = 'Transcript draft ' . (string) ($repository->find($id)['transcript_number'] ?? '') . ' created.';
        } elseif ($action === 'issue') {
            if ($repository->issue($transcriptId, (int) ($user['id'] ?? 0))) {
                $success = 'Transcript issued. Its academic snapshot and verification token are now fixed.';
            } else {
                $errors[] = 'Only a draft transcript can be issued.';
            }
        } elseif ($action === 'revoke') {
            if ($repository->revoke($transcriptId, (int) ($user['id'] ?? 0), (string) ($_POST['revocation_reason'] ?? ''))) {
                $success = 'Transcript revoked.';
            } else {
                $errors[] = 'Only an issued transcript can be revoked.';
            }
        }
    }
    $transcripts = $repository->all();
} catch (Throwable $exception) {
    $students = $programmes = $transcripts = [];
    $errors[] = 'Transcripts are unavailable. Run database/migrations/20260919_create_academic_transcripts.sql. ' . $exception->getMessage();
}

function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function transcriptBadge(string $status): string { return match ($status) { 'issued' => 'success', 'revoked' => 'danger', default => 'secondary' }; }
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Academic Transcripts</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link href="assets/app.css" rel="stylesheet"></head>
<body class="bg-light"><?php require_once __DIR__ . '/partials/header.php'; ?><div class="container py-4">
    <div class="ndc-page-heading"><div><div class="ndc-eyebrow">Credentials</div><h1 class="h3 mb-1">Academic Transcripts</h1><p class="text-muted mb-0">Issue a permanent snapshot of a student's approved academic results.</p></div></div>
    <?php require __DIR__ . '/partials/academic-nav.php'; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($errors !== []): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e((string) $error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="row g-4"><div class="col-lg-4"><form method="post" class="card shadow-sm"><div class="card-body"><h2 class="h5 mb-2">Create transcript draft</h2><p class="small text-muted">The student must have a programme enrolment and at least one approved result.</p><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="action" value="create"><div class="mb-3"><label class="form-label">Student</label><select name="student_id" class="form-select" required><option value="">Select student</option><?php foreach ($students as $student): ?><?php $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')); ?><option value="<?= (int) $student['id'] ?>"><?= e((string) $student['student_number'] . ' - ' . $name) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Programme</label><select name="programme_id" class="form-select" required><option value="">Select programme</option><?php foreach ($programmes as $programme): ?><option value="<?= (int) $programme['id'] ?>"><?= e((string) $programme['code'] . ' - ' . (string) $programme['name']) ?></option><?php endforeach; ?></select></div></div><div class="card-footer bg-white text-end"><button class="btn btn-primary">Create transcript draft</button></div></form></div>
    <div class="col-lg-8"><div class="card shadow-sm"><div class="card-body"><h2 class="h5 mb-3">Transcript register</h2><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Transcript</th><th>Student</th><th>Programme</th><th>Status</th><th>Issued</th><th></th></tr></thead><tbody><?php foreach ($transcripts as $transcript): ?><?php $name = trim((string) $transcript['first_name'] . ' ' . (string) $transcript['last_name']); ?><tr><td><div class="fw-semibold"><?= e((string) $transcript['transcript_number']) ?></div><div class="small text-muted font-monospace"><?= e(substr((string) $transcript['verification_token'], 0, 16)) ?>...</div></td><td><?= e((string) $transcript['student_number'] . ' - ' . $name) ?></td><td><?= e((string) $transcript['programme_code'] . ' - ' . (string) $transcript['programme_name']) ?></td><td><span class="badge text-bg-<?= e(transcriptBadge((string) $transcript['status'])) ?>"><?= e((string) $transcript['status']) ?></span></td><td><?= e((string) ($transcript['issued_at'] ?? '-')) ?></td><td class="text-end text-nowrap"><?php if ($transcript['status'] === 'draft'): ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="transcript_id" value="<?= (int) $transcript['id'] ?>"><input type="hidden" name="action" value="issue"><button class="btn btn-sm btn-success">Issue</button></form><?php elseif ($transcript['status'] === 'issued'): ?><button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#revoke-<?= (int) $transcript['id'] ?>">Revoke</button><div class="modal fade text-start" id="revoke-<?= (int) $transcript['id'] ?>" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><div class="modal-header"><h3 class="modal-title fs-5">Revoke transcript</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="transcript_id" value="<?= (int) $transcript['id'] ?>"><input type="hidden" name="action" value="revoke"><label class="form-label">Reason</label><input name="revocation_reason" class="form-control" maxlength="255" required></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Revoke transcript</button></div></form></div></div><?php endif; ?></td></tr><?php endforeach; ?><?php if ($transcripts === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No transcripts have been created.</td></tr><?php endif; ?></tbody></table></div></div></div></div></div>
</div></body></html>
