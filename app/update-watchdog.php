<?php

use App\AppUpdateService;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once __DIR__ . '/AppUpdateService.php';

$updateId = (string) ($argv[1] ?? '');
if ($updateId === '') {
    exit(1);
}

$service = new AppUpdateService(dirname(__DIR__));
$pending = $service->pendingUpdate();
if ($pending === null || (string) ($pending['id'] ?? '') !== $updateId) {
    exit(0);
}

$expiresAt = (int) ($pending['expires_at'] ?? 0);
$sleepFor = max(0, $expiresAt - time());
if ($sleepFor > 0) {
    sleep($sleepFor);
}

$pending = $service->pendingUpdate();
if ($pending !== null && (string) ($pending['id'] ?? '') === $updateId && time() >= (int) ($pending['expires_at'] ?? 0)) {
    $service->rollbackPendingUpdate('Update was not confirmed within 10 minutes.');
}
