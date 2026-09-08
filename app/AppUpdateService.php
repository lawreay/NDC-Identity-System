<?php

namespace App;

use RecursiveDirectoryIterator;
use RecursiveCallbackFilterIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

final class AppUpdateService
{
    private const CONFIRM_WINDOW_SECONDS = 600;
    private const PENDING_FILE = 'pending-update.json';

    private string $rootPath;
    private string $updatesPath;
    private string $incomingPath;
    private string $backupsPath;
    private string $stagingPath;

    public function __construct(?string $rootPath = null)
    {
        $this->rootPath = rtrim($rootPath ?? dirname(__DIR__), DIRECTORY_SEPARATOR);
        $this->updatesPath = $this->rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'updates';
        $this->incomingPath = $this->updatesPath . DIRECTORY_SEPARATOR . 'incoming';
        $this->backupsPath = $this->updatesPath . DIRECTORY_SEPARATOR . 'backups';
        $this->stagingPath = $this->updatesPath . DIRECTORY_SEPARATOR . 'staging';

        foreach ([$this->updatesPath, $this->incomingPath, $this->backupsPath, $this->stagingPath] as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }
    }

    public static function rollbackExpiredPendingUpdate(): void
    {
        try {
            $service = new self();
            $pending = $service->pendingUpdate();
            if ($pending !== null && time() >= (int) ($pending['expires_at'] ?? 0)) {
                $service->rollbackPendingUpdate('Update was not confirmed within 10 minutes.');
            }
        } catch (\Throwable $exception) {
            error_log('Automatic update rollback failed: ' . $exception->getMessage());
        }
    }

    public function pendingUpdate(): ?array
    {
        $path = $this->pendingFilePath();
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<int, array{name: string, path: string, size: int, modified_at: int}>
     */
    public function listIncomingPackages(): array
    {
        $packages = [];
        foreach (glob($this->incomingPath . DIRECTORY_SEPARATOR . '*.zip') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $packages[] = [
                'name' => basename($path),
                'path' => $path,
                'size' => filesize($path) ?: 0,
                'modified_at' => filemtime($path) ?: 0,
            ];
        }

        usort($packages, static fn (array $left, array $right): int => $right['modified_at'] <=> $left['modified_at']);

        return $packages;
    }

    public function storeUploadedPackage(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The update package upload failed.');
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension !== 'zip') {
            throw new RuntimeException('Update package must be a ZIP file.');
        }

        if ((int) ($file['size'] ?? 0) > 200 * 1024 * 1024) {
            throw new RuntimeException('Update package must not exceed 200 MB.');
        }

        $filename = 'update_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.zip';
        $destination = $this->incomingPath . DIRECTORY_SEPARATOR . $filename;
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $saved = is_uploaded_file($tmpPath)
            ? move_uploaded_file($tmpPath, $destination)
            : copy($tmpPath, $destination);

        if (!$saved) {
            throw new RuntimeException('The update package could not be saved.');
        }

        return $destination;
    }

    public function deleteIncomingPackage(string $packageName): void
    {
        $packagePath = $this->resolvePackagePath($packageName);
        if (!@unlink($packagePath)) {
            throw new RuntimeException('Update package could not be deleted.');
        }
    }

    public function applyPackage(string $packagePath): array
    {
        if ($this->pendingUpdate() !== null) {
            throw new RuntimeException('A pending update already exists. Confirm or roll it back before applying another update.');
        }

        $packagePath = $this->resolvePackagePath($packagePath);
        $this->assertZipPackage($packagePath);

        $token = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $backupPath = $this->backupsPath . DIRECTORY_SEPARATOR . 'backup_' . $token;
        $extractPath = $this->stagingPath . DIRECTORY_SEPARATOR . 'extract_' . $token;

        mkdir($backupPath, 0777, true);
        mkdir($extractPath, 0777, true);

        try {
            $this->backupCurrentApp($backupPath);
            $this->extractPackage($packagePath, $extractPath);
            $sourcePath = $this->detectPackageRoot($extractPath);
            $fileChanges = $this->copyUpdateFiles($sourcePath);

            $pending = [
                'id' => $token,
                'package' => basename($packagePath),
                'backup_path' => $backupPath,
                'applied_at' => time(),
                'expires_at' => time() + self::CONFIRM_WINDOW_SECONDS,
                'changed_files' => $fileChanges['changed'],
                'added_files' => $fileChanges['added'],
            ];
            file_put_contents($this->pendingFilePath(), json_encode($pending, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->startRollbackWatcher($token);

            $this->removeDirectory($extractPath);

            return $pending;
        } catch (\Throwable $exception) {
            $this->removeDirectory($extractPath);
            @unlink($this->pendingFilePath());

            try {
                $this->restoreBackup($backupPath);
            } catch (\Throwable $restoreException) {
                error_log('Update restore after failed apply also failed: ' . $restoreException->getMessage());
            }

            throw new RuntimeException('Update failed and the previous files were restored: ' . $exception->getMessage(), 0, $exception);
        }
    }

    public function confirmPendingUpdate(): void
    {
        $pending = $this->pendingUpdate();
        if ($pending === null) {
            throw new RuntimeException('There is no pending update to confirm.');
        }

        @unlink($this->pendingFilePath());
    }

    public function rollbackPendingUpdate(string $reason = 'Update rolled back.'): void
    {
        $pending = $this->pendingUpdate();
        if ($pending === null) {
            throw new RuntimeException('There is no pending update to roll back.');
        }

        $backupPath = (string) ($pending['backup_path'] ?? '');
        if ($backupPath === '' || !is_dir($backupPath)) {
            throw new RuntimeException('The update backup could not be found.');
        }

        $this->restoreBackup($backupPath);
        foreach (($pending['added_files'] ?? []) as $relative) {
            if (!is_string($relative) || $this->isProtectedRelativePath($relative)) {
                continue;
            }
            $target = $this->rootPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
            if (is_file($target)) {
                @unlink($target);
            }
        }
        @unlink($this->pendingFilePath());
        error_log($reason);
    }

    private function pendingFilePath(): string
    {
        return $this->updatesPath . DIRECTORY_SEPARATOR . self::PENDING_FILE;
    }

    private function startRollbackWatcher(string $updateId): void
    {
        $script = __DIR__ . DIRECTORY_SEPARATOR . 'update-watchdog.php';
        if (!is_file($script)) {
            return;
        }

        try {
            $php = PHP_BINARY ?: 'php';
            $command = \escapeshellarg($php) . ' ' . \escapeshellarg($script) . ' ' . \escapeshellarg($updateId);

            if (PHP_OS_FAMILY === 'Windows') {
                if (!\function_exists('popen') || !\function_exists('pclose')) {
                    return;
                }

                $process = @\popen('start "" /B ' . $command . ' >NUL 2>NUL', 'r');
                if (\is_resource($process)) {
                    @\pclose($process);
                }
                return;
            }

            if (\function_exists('exec')) {
                @\exec($command . ' > /dev/null 2>&1 &');
            }
        } catch (\Throwable $exception) {
            error_log('Update rollback watcher could not be started: ' . $exception->getMessage());
        }
    }

    private function resolvePackagePath(string $packagePath): string
    {
        $packagePath = trim($packagePath);
        if ($packagePath === '') {
            throw new RuntimeException('No update package was selected.');
        }

        $path = $this->incomingPath . DIRECTORY_SEPARATOR . basename($packagePath);
        $realIncoming = realpath($this->incomingPath);
        $realPath = realpath($path);
        if ($realIncoming === false || $realPath === false || !str_starts_with($realPath, $realIncoming . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Invalid update package path.');
        }

        return $realPath;
    }

    private function assertZipPackage(string $packagePath): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive is required to apply app updates.');
        }
        if (!is_file($packagePath)) {
            throw new RuntimeException('Update package was not found.');
        }

        $zip = new ZipArchive();
        if ($zip->open($packagePath) !== true) {
            throw new RuntimeException('Update package could not be opened.');
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $name)) {
                $zip->close();
                throw new RuntimeException('Update package contains an unsafe file path.');
            }
        }

        $zip->close();
    }

    private function extractPackage(string $packagePath, string $extractPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($packagePath) !== true) {
            throw new RuntimeException('Update package could not be opened.');
        }

        if (!$zip->extractTo($extractPath)) {
            $zip->close();
            throw new RuntimeException('Update package could not be extracted.');
        }

        $zip->close();
    }

    private function detectPackageRoot(string $extractPath): string
    {
        $entries = array_values(array_filter(scandir($extractPath) ?: [], static fn (string $entry): bool => $entry !== '.' && $entry !== '..'));
        if (count($entries) === 1 && is_dir($extractPath . DIRECTORY_SEPARATOR . $entries[0])) {
            $candidate = $extractPath . DIRECTORY_SEPARATOR . $entries[0];
            if (is_dir($candidate . DIRECTORY_SEPARATOR . 'app') || is_dir($candidate . DIRECTORY_SEPARATOR . 'public')) {
                return $candidate;
            }
        }

        return $extractPath;
    }

    private function backupCurrentApp(string $backupPath): void
    {
        $this->copyDirectory($this->rootPath, $backupPath, true);
    }

    private function restoreBackup(string $backupPath): void
    {
        $this->copyDirectory($backupPath, $this->rootPath, false);
    }

    /**
     * @return array{changed: array<int, string>, added: array<int, string>}
     */
    private function copyUpdateFiles(string $sourcePath): array
    {
        $changedFiles = [];
        $addedFiles = [];
        $iterator = new RecursiveIteratorIterator(
            $this->filteredDirectoryIterator($sourcePath, false),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $source = $item->getPathname();
            $relative = $this->relativePath($sourcePath, $source);
            if ($this->isProtectedRelativePath($relative)) {
                continue;
            }

            $target = $this->rootPath . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0777, true);
                }
                continue;
            }

            $targetDir = dirname($target);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $wasNewFile = !is_file($target);
            if (!copy($source, $target)) {
                throw new RuntimeException('Could not update file: ' . $relative);
            }
            $changedFiles[] = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            if ($wasNewFile) {
                $addedFiles[] = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            }
        }

        return [
            'changed' => $changedFiles,
            'added' => $addedFiles,
        ];
    }

    private function copyDirectory(string $sourcePath, string $targetPath, bool $forBackup): void
    {
        $iterator = new RecursiveIteratorIterator(
            $this->filteredDirectoryIterator($sourcePath, $forBackup),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $source = $item->getPathname();
            $relative = $this->relativePath($sourcePath, $source);
            if ($forBackup && $this->isSkippedBackupPath($relative)) {
                continue;
            }
            if (!$forBackup && $this->isProtectedRelativePath($relative)) {
                continue;
            }

            $target = $targetPath . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0777, true);
                }
                continue;
            }

            $targetDir = dirname($target);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            if (!copy($source, $target)) {
                throw new RuntimeException('Could not copy file: ' . $relative);
            }
        }
    }

    private function isSkippedBackupPath(string $relative): bool
    {
        $normalized = str_replace('\\', '/', $relative);

        return $normalized === '.git'
            || str_starts_with($normalized, '.git/')
            || $normalized === 'storage/updates'
            || str_starts_with($normalized, 'storage/updates/')
            || $normalized === 'composer.zip'
            || $normalized === 'vendor.zip';
    }

    private function isProtectedRelativePath(string $relative): bool
    {
        $normalized = str_replace('\\', '/', trim($relative, '/\\'));

        return $normalized === ''
            || $normalized === '.env'
            || $normalized === '.git'
            || str_starts_with($normalized, '.git/')
            || $normalized === 'storage/updates'
            || str_starts_with($normalized, 'storage/updates/')
            || $normalized === 'storage/logs'
            || str_starts_with($normalized, 'storage/logs/')
            || $normalized === 'storage/templates'
            || str_starts_with($normalized, 'storage/templates/')
            || $normalized === 'public/uploads'
            || str_starts_with($normalized, 'public/uploads/')
            || $normalized === 'composer.zip'
            || $normalized === 'vendor.zip';
    }

    private function relativePath(string $basePath, string $path): string
    {
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return ltrim(str_replace($basePath, '', $path), DIRECTORY_SEPARATOR);
    }

    private function filteredDirectoryIterator(string $sourcePath, bool $forBackup): RecursiveCallbackFilterIterator
    {
        $directoryIterator = new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS);

        return new RecursiveCallbackFilterIterator(
            $directoryIterator,
            function (\SplFileInfo $current) use ($sourcePath, $forBackup): bool {
                if (!$current->isDir()) {
                    return true;
                }

                $relative = $this->relativePath($sourcePath, $current->getPathname());
                if ($forBackup && $this->isSkippedBackupPath($relative)) {
                    return false;
                }
                if (!$forBackup && $this->isProtectedRelativePath($relative)) {
                    return false;
                }

                return true;
            }
        );
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }
}
