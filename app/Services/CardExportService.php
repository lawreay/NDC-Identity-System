<?php

namespace App\Services;

use RuntimeException;

require_once dirname(__DIR__, 2) . '/app/TemplateDesigner/TemplateDesignerService.php';

final class CardExportService
{
    /** @param array<string, mixed> $template @param array<string, mixed> $student @param array<string, mixed> $organization @param array<string, mixed> $theme */
    public function exportCardPng(array $template, array $student, array $organization, array $theme, string $studentNumber, string $side): string
    {
        if (!in_array($side, ['front', 'back'], true)) {
            throw new RuntimeException('Invalid PNG export side.');
        }

        $designer = new \TemplateDesignerService();
        try {
            $html = $designer->renderTemplateForExport($template, $student, $organization, $theme, $side);
        } catch (\Throwable $exception) {
            throw new RuntimeException('PNG template preparation failed: ' . $exception->getMessage(), 0, $exception);
        }
        $safeNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentNumber) ?: 'export';
        $outputPath = CardRenderer::temporaryDirectory() . DIRECTORY_SEPARATOR . 'card_' . $safeNumber . '_' . $side . '_' . bin2hex(random_bytes(6)) . '.png';
        $this->renderInBrowser($html, $outputPath, 'png');

        $dimensions = @getimagesize($outputPath);
        if ($dimensions === false || $dimensions[0] !== 856 || $dimensions[1] !== 540) {
            self::cleanupFile($outputPath);
            throw new RuntimeException('PNG export did not produce the required 856x540 dimensions.');
        }
        return $outputPath;
    }

    /** @param array<string, mixed> $template @param array<string, mixed> $student @param array<string, mixed> $organization @param array<string, mixed> $theme */
    public function exportCardPdf(array $template, array $student, array $organization, array $theme, string $studentNumber): string
    {
        $designer = new \TemplateDesignerService();
        $frontHtml = $designer->renderTemplateForExport($template, $student, $organization, $theme, 'front');
        $backHtml = $designer->renderTemplateForExport($template, $student, $organization, $theme, 'back');
        $safeNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentNumber) ?: 'export';
        $outputPath = CardRenderer::temporaryDirectory() . DIRECTORY_SEPARATOR . 'card_' . $safeNumber . '_' . bin2hex(random_bytes(6)) . '.pdf';
        $this->renderInBrowser($this->printDocument([$frontHtml, $backHtml]), $outputPath, 'pdf');
        return $outputPath;
    }

    /** @param array<string, mixed> $template @param array<string, mixed> $student @param array<string, mixed> $organization @param array<string, mixed> $theme */
    public function exportCardSidePdf(array $template, array $student, array $organization, array $theme, string $studentNumber, string $side = 'front'): string
    {
        if (!in_array($side, ['front', 'back'], true)) {
            throw new RuntimeException('Invalid PDF export side.');
        }
        $designer = new \TemplateDesignerService();
        $html = $designer->renderTemplateForExport($template, $student, $organization, $theme, $side);
        $safeNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentNumber) ?: 'export';
        $outputPath = CardRenderer::temporaryDirectory() . DIRECTORY_SEPARATOR . 'card_' . $safeNumber . '_' . $side . '_' . bin2hex(random_bytes(6)) . '.pdf';
        $this->renderInBrowser($this->printDocument([$html]), $outputPath, 'pdf');
        return $outputPath;
    }

    /** @param array<int, string> $cards */
    private function printDocument(array $cards): string
    {
        $pages = '';
        foreach ($cards as $card) {
            $pages .= '<section class="export-page"><div class="export-card">' . $card . '</div></section>';
        }
        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            . 'html,body{margin:0;padding:0;background:#fff;} .export-page{width:856px;height:540px;overflow:hidden;page-break-after:always;background:#fff;} .export-card{width:856px;height:540px;overflow:hidden;}'
            . '@media print{ @page{size:85.6mm 53.98mm;margin:0;} body{width:85.6mm;} .export-page{width:85.6mm;height:53.98mm;} .export-card{transform:scale(0.377952,0.37763);transform-origin:top left;} }'
            . '</style></head><body>' . $pages . '</body></html>';
    }

    private function renderInBrowser(string $html, string $outputPath, string $format): void
    {
        $browser = $this->browserBinary();
        $directory = CardRenderer::temporaryDirectory();
        $htmlPath = $directory . DIRECTORY_SEPARATOR . 'card-export-' . bin2hex(random_bytes(8)) . '.html';
        $profilePath = $directory . DIRECTORY_SEPARATOR . 'chrome-profile-' . bin2hex(random_bytes(8));
        $document = $format === 'pdf' ? $html : '<!doctype html><html><head><meta charset="utf-8"><style>html,body{margin:0;padding:0;width:856px;height:540px;overflow:hidden;background:#fff;}.export-card{width:856px;height:540px;overflow:hidden;}</style></head><body><div class="export-card">' . $html . '</div></body></html>';
        if (file_put_contents($htmlPath, $document) === false) {
            throw new RuntimeException('The export document could not be prepared.');
        }

        // escapeshellarg() protects spaces on Windows; encoding them here would
        // be stripped by the Windows implementation and break the file URL.
        $url = 'file:///' . str_replace('\\', '/', $htmlPath);
        $target = $format === 'pdf' ? '--print-to-pdf=' : '--screenshot=';
        $command = escapeshellarg($browser) . ' --headless --disable-gpu --no-sandbox --allow-file-access-from-files --disable-background-networking --disable-component-update --no-first-run --no-default-browser-check --hide-scrollbars --run-all-compositor-stages-before-draw --no-pdf-header-footer --force-device-scale-factor=1 --window-size=856,540 --user-data-dir=' . escapeshellarg($profilePath) . ' ' . $target . escapeshellarg($outputPath) . ' ' . escapeshellarg($url);
        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $process = proc_open($command, [1 => ['file', $nullDevice, 'w'], 2 => ['file', $nullDevice, 'w']], $pipes);
        if (!is_resource($process)) {
            self::cleanupFile($htmlPath);
            throw new RuntimeException('The browser export process could not be started.');
        }

        $startedAt = microtime(true);
        do {
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if (microtime(true) - $startedAt > 30) {
                proc_terminate($process);
                proc_close($process);
                self::cleanupFile($htmlPath);
                $this->removeDirectory($profilePath);
                throw new RuntimeException('The browser export timed out.');
            }
            usleep(100000);
        } while (true);

        $exitCode = proc_close($process);
        self::cleanupFile($htmlPath);
        $this->removeDirectory($profilePath);
        if (!is_file($outputPath) || filesize($outputPath) === 0) {
            self::cleanupFile($outputPath);
            throw new RuntimeException('The browser could not render the card export.');
        }
    }

    private function browserBinary(): string
    {
        $configured = getenv('BROWSER_BIN');
        $candidates = array_filter([
            is_string($configured) && $configured !== '' ? $configured : null,
            PHP_OS_FAMILY === 'Windows' ? 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe' : null,
            PHP_OS_FAMILY === 'Windows' ? 'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe' : null,
            'google-chrome', 'chromium', 'chromium-browser', 'msedge',
        ]);
        foreach ($candidates as $candidate) {
            if (is_file($candidate) || (PHP_OS_FAMILY !== 'Windows' && trim((string) shell_exec('command -v ' . escapeshellarg($candidate)))) !== '') {
                return $candidate;
            }
        }
        throw new RuntimeException('Card export requires a Chromium browser. Set BROWSER_BIN to Chrome or Chromium.');
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) { return; }
        for ($attempt = 0; $attempt < 5 && is_dir($path); $attempt++) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
            @rmdir($path);
            if (is_dir($path)) { usleep(100000); }
        }
    }

    public static function cleanupFile(string $filePath): bool
    {
        return is_file($filePath) && @unlink($filePath);
    }
}
