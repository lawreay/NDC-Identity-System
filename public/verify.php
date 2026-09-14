<?php

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/CardRepository.php';
require_once __DIR__ . '/../app/SettingsRepository.php';
require_once __DIR__ . '/../app/Services/CardVerificationService.php';

use App\Services\CardVerificationService;

$guid = strtolower(trim((string) ($_GET['guid'] ?? '')));
$result = ['state' => 'INVALID', 'card' => null];
$organizationName = 'NDC Identity System';
$error = '';

try {
    $connection = Database::getConnection();
    $settings = (new SettingsRepository($connection))->getAll();
    $organizationName = trim((string) ($settings['organization_name'] ?? '')) ?: $organizationName;
    $result = (new CardVerificationService(new CardRepository($connection)))->verify($guid);
} catch (Throwable $exception) {
    http_response_code(503);
    $error = 'Verification is temporarily unavailable. Please contact the institution.';
}

$state = (string) $result['state'];
$card = $result['card'];
$isValid = $state === 'VALID';
$statusClass = match ($state) {
    'VALID' => 'success',
    'EXPIRED' => 'warning',
    'REVOKED' => 'danger',
    default => 'secondary',
};
$statusTitle = match ($state) {
    'VALID' => 'VALID CARD',
    'EXPIRED' => 'CARD EXPIRED',
    'REVOKED' => 'CARD REVOKED',
    default => 'INVALID CARD',
};
$statusMessage = match ($state) {
    'VALID' => 'This is an active, officially issued identity card.',
    'EXPIRED' => 'This card was issued but is no longer active because it has expired.',
    'REVOKED' => 'This card was issued but has been revoked and is not valid.',
    default => 'This identity card could not be verified.',
};

$photoUrl = '';
if (is_array($card)) {
    $photoPath = str_replace('\\', '/', ltrim((string) ($card['photo_path'] ?? ''), '/'));
    if (str_starts_with($photoPath, 'uploads/student_photos/') && is_file(__DIR__ . '/' . $photoPath)) {
        $photoUrl = $photoPath;
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function displayDate(string $date): string
{
    $timestamp = strtotime($date);
    return $timestamp === false ? 'Not available' : date('d M Y', $timestamp);
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card Verification | <?= e($organizationName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; background: linear-gradient(145deg, #eef4fb, #f8fafc); }
        .verification-card { max-width: 550px; border: 0; border-radius: 20px; overflow: hidden; }
        .status-band { padding: 2rem 1.5rem; color: #fff; text-align: center; }
        .status-band.success { background: #198754; }
        .status-band.warning { background: #b7791f; }
        .status-band.danger { background: #b42318; }
        .status-band.secondary { background: #475569; }
        .student-photo { width: 112px; height: 112px; object-fit: cover; border: 4px solid #fff; box-shadow: 0 4px 16px rgba(15, 23, 42, .18); }
        .detail-label { color: #64748b; font-size: .78rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .guid { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; overflow-wrap: anywhere; }
    </style>
</head>
<body class="d-flex align-items-center py-4">
    <main class="container">
        <section class="card verification-card shadow-lg mx-auto">
            <div class="status-band <?= e($statusClass) ?>">
                <div class="fs-1 mb-2" aria-hidden="true"><?= $isValid ? '✓' : '!' ?></div>
                <h1 class="h3 mb-2"><?= e($statusTitle) ?></h1>
                <p class="mb-0 opacity-75"><?= e($organizationName) ?></p>
            </div>
            <div class="card-body p-4 p-md-5 text-center">
                <?php if ($error !== ''): ?>
                    <p class="mb-0"><?= e($error) ?></p>
                <?php else: ?>
                    <p class="text-muted mb-4"><?= e($statusMessage) ?></p>
                    <?php if (is_array($card)): ?>
                        <?php if ($photoUrl !== ''): ?>
                            <img class="student-photo rounded-circle mb-3" src="<?= e($photoUrl) ?>" alt="Student photo">
                        <?php endif; ?>
                        <h2 class="h4 mb-1"><?= e(trim((string) ($card['first_name'] ?? '') . ' ' . (string) ($card['last_name'] ?? ''))) ?></h2>
                        <p class="text-muted mb-4">Student ID: <?= e((string) ($card['student_number'] ?? '')) ?></p>
                        <div class="row text-start g-3 border-top pt-4">
                            <div class="col-sm-6"><div class="detail-label">Programme</div><div><?= e((string) ($card['program'] ?? 'Not available')) ?></div></div>
                            <div class="col-sm-6"><div class="detail-label">Card status</div><div><?= e($state) ?></div></div>
                            <div class="col-sm-6"><div class="detail-label">Issued</div><div><?= e(displayDate((string) ($card['issued_at'] ?? ''))) ?></div></div>
                            <div class="col-sm-6"><div class="detail-label">Expires</div><div><?= e(displayDate((string) ($card['expires_at'] ?? ''))) ?></div></div>
                        </div>
                    <?php endif; ?>
                    <div class="border-top mt-4 pt-3 text-start">
                        <div class="detail-label">Verification ID</div>
                        <div class="guid small"><?= e($guid !== '' ? $guid : 'Not supplied') ?></div>
                    </div>
                    <p class="small text-muted mt-4 mb-0">For assistance, please contact <?= e($organizationName) ?>.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
