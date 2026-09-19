<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/AcademicProgrammeRepository.php';
require_once __DIR__ . '/../app/AcademicChecksheetService.php';
require_once __DIR__ . '/../app/AcademicEligibilityService.php';
require_once __DIR__ . '/../app/AcademicCertificateRepository.php';

use App\Auth;

$user = Auth::requireRole('Administrator');
$errors = [];
$success = '';

try {
    $connection = Database::getConnection();
    $students = (new StudentRepository($connection))->search('');
    $programmes = (new AcademicProgrammeRepository($connection))->active();
    $eligibilityService = new AcademicEligibilityService(new AcademicChecksheetService($connection));
    $repository = new AcademicCertificateRepository($connection, $eligibilityService);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Auth::requireCsrf();
        $action = (string) ($_POST['action'] ?? 'create');
        $certificateId = (int) ($_POST['certificate_id'] ?? 0);

        if ($action === 'create') {
            $id = $repository->createDraft(
                (int) ($_POST['student_id'] ?? 0),
                (int) ($_POST['programme_id'] ?? 0),
                (int) ($user['id'] ?? 0)
            );
            $success = 'Certificate draft ' . (string) ($repository->find($id)['certificate_number'] ?? '') . ' created.';
        } elseif ($action === 'submit') {
            if ($repository->submitForApproval($certificateId)) {
                $success = 'Certificate submitted for approval.';
            } else {
                $errors[] = 'Only a draft certificate can be submitted for approval.';
            }
        } elseif ($action === 'issue') {
            if ($repository->issue($certificateId, (int) ($user['id'] ?? 0))) {
                $success = 'Certificate issued. Its credential number and verification token are now fixed.';
            } else {
                $errors[] = 'Only a certificate awaiting approval can be issued.';
            }
        } elseif ($action === 'revoke') {
            if ($repository->revoke($certificateId, (int) ($user['id'] ?? 0), (string) ($_POST['revocation_reason'] ?? ''))) {
                $success = 'Certificate revoked.';
            } else {
                $errors[] = 'Only an issued certificate can be revoked.';
            }
        }
    }

    $certificates = $repository->all();
} catch (Throwable $exception) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $errors[] = $exception->getMessage();
        try {
            $certificates = isset($repository) ? $repository->all() : [];
        } catch (Throwable $ignored) {
            $certificates = [];
        }
    } else {
        $students = $programmes = $certificates = [];
        $errors[] = 'Certificates are unavailable. Apply database/migrations/20260919_create_academic_certificates.sql. ' . $exception->getMessage();
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function certificateBadgeClass(string $status): string
{
    return match ($status) {
        'issued' => 'success',
        'revoked' => 'danger',
        'pending_approval' => 'warning',
        default => 'secondary',
    };
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Certificates</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/header.php'; ?>
<div class="container py-4">
    <div class="ndc-page-heading"><div><div class="ndc-eyebrow">Credentials</div><h1 class="h3 mb-1">Academic Certificates</h1><p class="text-muted mb-0">Create certificates only for academically eligible students, then submit, issue, or revoke them.</p></div></div>
    <?php require __DIR__ . '/partials/academic-nav.php'; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($errors !== []): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e((string) $error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4"><form method="post" class="card shadow-sm"><div class="card-body"><h2 class="h5 mb-2">Create certificate draft</h2><p class="small text-muted">Only eligible students can receive a certificate draft.</p><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="action" value="create"><div class="mb-3"><label class="form-label">Student</label><select name="student_id" class="form-select" required><option value="">Select student</option><?php foreach ($students as $student): ?><?php $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')); ?><option value="<?= (int) $student['id'] ?>"><?= e((string) ($student['student_number'] ?? '') . ' - ' . $name) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Programme</label><select name="programme_id" class="form-select" required><option value="">Select programme</option><?php foreach ($programmes as $programme): ?><option value="<?= (int) $programme['id'] ?>"><?= e((string) $programme['code'] . ' - ' . (string) $programme['name']) ?></option><?php endforeach; ?></select></div></div><div class="card-footer bg-white text-end"><button class="btn btn-primary">Create eligible draft</button></div></form></div>
        <div class="col-lg-8"><div class="card shadow-sm"><div class="card-body"><h2 class="h5 mb-3">Certificate register</h2><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Certificate</th><th>Student</th><th>Programme</th><th>Status</th><th>Issued</th><th></th></tr></thead><tbody><?php foreach ($certificates as $certificate): ?><?php $name = trim((string) ($certificate['first_name'] ?? '') . ' ' . (string) ($certificate['last_name'] ?? '')); ?><tr><td><div class="fw-semibold"><?= e((string) $certificate['certificate_number']) ?></div><div class="small text-muted font-monospace"><?= e(substr((string) $certificate['verification_token'], 0, 16)) ?>...</div></td><td><?= e((string) $certificate['student_number'] . ' - ' . $name) ?></td><td><?= e((string) $certificate['programme_code'] . ' - ' . (string) $certificate['programme_name']) ?></td><td><span class="badge text-bg-<?= e(certificateBadgeClass((string) $certificate['status'])) ?>"><?= e(str_replace('_', ' ', (string) $certificate['status'])) ?></span></td><td><?= e((string) ($certificate['issued_at'] ?? '-')) ?></td><td class="text-end text-nowrap"><?php if ($certificate['status'] === 'draft'): ?><form method="post" class="d-inline"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="certificate_id" value="<?= (int) $certificate['id'] ?>"><input type="hidden" name="action" value="submit"><button class="btn btn-sm btn-outline-primary">Submit</button></form><?php elseif ($certificate['status'] === 'pending_approval'): ?><form method="post" class="d-inline"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="certificate_id" value="<?= (int) $certificate['id'] ?>"><input type="hidden" name="action" value="issue"><button class="btn btn-sm btn-success">Issue</button></form><?php elseif ($certificate['status'] === 'issued'): ?><button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#revoke-<?= (int) $certificate['id'] ?>">Revoke</button><div class="modal fade text-start" id="revoke-<?= (int) $certificate['id'] ?>" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><div class="modal-header"><h3 class="modal-title fs-5">Revoke certificate</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="certificate_id" value="<?= (int) $certificate['id'] ?>"><input type="hidden" name="action" value="revoke"><label class="form-label">Reason</label><input name="revocation_reason" class="form-control" maxlength="255" required></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Revoke certificate</button></div></form></div></div><?php endif; ?></td></tr><?php endforeach; ?><?php if ($certificates === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No certificates have been created.</td></tr><?php endif; ?></tbody></table></div></div></div></div>
    </div>
    <?php $issuedCertificates = array_filter($certificates, static fn (array $certificate): bool => ($certificate['status'] ?? '') === 'issued'); ?>
    <?php if ($issuedCertificates !== []): ?>
        <div class="card shadow-sm mt-4"><div class="card-body"><h2 class="h6 mb-3">Issued certificate PDFs</h2><div class="d-flex flex-wrap gap-2"><?php foreach ($issuedCertificates as $certificate): ?><a class="btn btn-outline-primary btn-sm" href="export-certificate.php?id=<?= (int) $certificate['id'] ?>"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><?= e((string) $certificate['certificate_number']) ?></a><?php endforeach; ?></div></div></div>
    <?php endif; ?>
</div>
</body>
</html>
