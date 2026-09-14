<?php

namespace App\Services;

use Mpdf\Mpdf;
use RuntimeException;
use ZipArchive;

final class CardExportService
{
    public function __construct(private ?CardRenderer $renderer = null)
    {
        $this->renderer ??= new CardRenderer();
    }

    /** @param array<string, mixed> $template @param array<string, mixed> $student @param array<string, mixed> $organization @param array<string, mixed> $theme */
    public function exportCardPng(array $template, array $student, array $organization, array $theme, string $studentNumber, string $side): string
    {
        if (!in_array($side, ['front', 'back'], true)) {
            throw new RuntimeException('Invalid PNG export side.');
        }

        try {
            return $this->renderer->renderPng($template, $student, $organization, $theme, $side);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to generate PNG: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /** @param array<string, mixed> $template @param array<string, mixed> $student @param array<string, mixed> $organization @param array<string, mixed> $theme */
    public function exportCardPdf(array $template, array $student, array $organization, array $theme, string $studentNumber): string
    {
        if (!class_exists(Mpdf::class)) {
            throw new RuntimeException('PDF export requires the mPDF library. Run composer install.');
        }

        ini_set('pcre.backtrack_limit', '10000000');
        try {
            $mpdf = $this->newMpdf($studentNumber);
            $frontSvg = $this->renderer->renderFront($template, $student, $organization, $theme);
            $backSvg = $this->renderer->renderBack($template, $student, $organization, $theme);
            $mpdf->WriteHTML($frontSvg);
            $mpdf->AddPage();
            $mpdf->WriteHTML($backSvg);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to generate PDF: ' . $exception->getMessage(), 0, $exception);
        }

        $safeNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentNumber) ?: 'export';
        $outputPath = CardRenderer::temporaryDirectory() . DIRECTORY_SEPARATOR . 'card_' . $safeNumber . '_' . bin2hex(random_bytes(6)) . '.pdf';
        try {
            $mpdf->Output($outputPath, 'F');
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to write PDF export: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_file($outputPath) || filesize($outputPath) === 0) {
            self::cleanupFile($outputPath);
            throw new RuntimeException('PDF export did not produce a valid file.');
        }

        return $outputPath;
    }

    /**
     * Exports the front and back of each supplied student card into one PDF.
     *
     * @param array<string, mixed> $template
     * @param array<int, array<string, mixed>> $students
     * @param array<string, mixed> $organization
     * @param array<string, mixed> $theme
     */
    public function exportCardsPdf(array $template, array $students, array $organization, array $theme): string
    {
        if ($students === []) {
            throw new RuntimeException('Select at least one student to export.');
        }
        if (!class_exists(Mpdf::class)) {
            throw new RuntimeException('PDF export requires the mPDF library. Run composer install.');
        }

        ini_set('pcre.backtrack_limit', '10000000');
        try {
            $mpdf = $this->newMpdf('Bulk student ID cards');
            $isFirstPage = true;
            foreach ($students as $student) {
                foreach (['front', 'back'] as $side) {
                    if (!$isFirstPage) {
                        $mpdf->AddPage();
                    }
                    $mpdf->WriteHTML($side === 'front'
                        ? $this->renderer->renderFront($template, $student, $organization, $theme)
                        : $this->renderer->renderBack($template, $student, $organization, $theme));
                    $isFirstPage = false;
                }
            }
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to generate bulk PDF: ' . $exception->getMessage(), 0, $exception);
        }

        $outputPath = CardRenderer::temporaryDirectory() . DIRECTORY_SEPARATOR . 'student_ids_' . bin2hex(random_bytes(6)) . '.pdf';
        try {
            $mpdf->Output($outputPath, 'F');
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to write bulk PDF export: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_file($outputPath) || filesize($outputPath) === 0) {
            self::cleanupFile($outputPath);
            throw new RuntimeException('Bulk PDF export did not produce a valid file.');
        }

        return $outputPath;
    }

    /**
     * Exports front and back PNG files for each student into one ZIP archive.
     *
     * @param array<string, mixed> $template
     * @param array<int, array<string, mixed>> $students
     * @param array<string, mixed> $organization
     * @param array<string, mixed> $theme
     */
    public function exportCardsPngZip(array $template, array $students, array $organization, array $theme): string
    {
        if ($students === []) {
            throw new RuntimeException('Select at least one student to export.');
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('PNG ZIP export requires the PHP ZipArchive extension.');
        }

        $outputPath = CardRenderer::temporaryDirectory() . DIRECTORY_SEPARATOR . 'student_ids_' . bin2hex(random_bytes(6)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the PNG ZIP export.');
        }

        $pngPaths = [];
        $zipOpen = true;
        try {
            foreach ($students as $index => $student) {
                $studentNumber = trim((string) ($student['student_number'] ?? ''));
                $safeNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentNumber) ?: 'student_' . (int) ($student['id'] ?? $index + 1);
                foreach (['front', 'back'] as $side) {
                    $pngPath = $this->exportCardPng($template, $student, $organization, $theme, $studentNumber, $side);
                    $pngPaths[] = $pngPath;
                    $archiveName = sprintf('%03d_card_%s_%s.png', $index + 1, $safeNumber, $side);
                    if (!$zip->addFile($pngPath, $archiveName)) {
                        throw new RuntimeException('Unable to add a card PNG to the ZIP export.');
                    }
                    // ZipArchive reads the source file when close() is called.
                    // Keep it in place until then and remove all sources afterwards.
                }
            }

            if (!$zip->close()) {
                throw new RuntimeException('Unable to finalize the PNG ZIP export.');
            }
            $zipOpen = false;
        } catch (\Throwable $exception) {
            if ($zipOpen) {
                $zip->close();
            }
            self::cleanupFile($outputPath);
            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException('Failed to generate PNG ZIP: ' . $exception->getMessage(), 0, $exception);
        } finally {
            foreach ($pngPaths as $path) {
                self::cleanupFile($path);
            }
        }

        if (!is_file($outputPath) || filesize($outputPath) === 0) {
            self::cleanupFile($outputPath);
            throw new RuntimeException('PNG ZIP export did not produce a valid file.');
        }

        return $outputPath;
    }

    /** @param array<string, mixed> $template @param array<string, mixed> $student @param array<string, mixed> $organization @param array<string, mixed> $theme */
    public function exportCardSidePdf(array $template, array $student, array $organization, array $theme, string $studentNumber, string $side = 'front'): string
    {
        if (!in_array($side, ['front', 'back'], true)) {
            throw new RuntimeException('Invalid PDF export side.');
        }
        if (!class_exists(Mpdf::class)) {
            throw new RuntimeException('PDF export requires the mPDF library. Run composer install.');
        }

        try {
            $mpdf = $this->newMpdf($studentNumber);
            $svg = $side === 'front'
                ? $this->renderer->renderFront($template, $student, $organization, $theme)
                : $this->renderer->renderBack($template, $student, $organization, $theme);
            $mpdf->WriteHTML($svg);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to generate PDF: ' . $exception->getMessage(), 0, $exception);
        }

        $safeNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentNumber) ?: 'export';
        $outputPath = CardRenderer::temporaryDirectory() . DIRECTORY_SEPARATOR . 'card_' . $safeNumber . '_' . $side . '_' . bin2hex(random_bytes(6)) . '.pdf';
        try {
            $mpdf->Output($outputPath, 'F');
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to write PDF export: ' . $exception->getMessage(), 0, $exception);
        }
        if (!is_file($outputPath) || filesize($outputPath) === 0) {
            self::cleanupFile($outputPath);
            throw new RuntimeException('PDF export did not produce a valid file.');
        }
        return $outputPath;
    }

    private function newMpdf(string $studentNumber): Mpdf
    {
        $mpdf = new Mpdf([
            'format' => [CardRenderer::CARD_WIDTH_MM, CardRenderer::CARD_HEIGHT_MM],
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
            'mode' => 'utf-8',
            'dpi' => CardRenderer::CARD_DPI,
            'tempDir' => CardRenderer::temporaryDirectory(),
        ]);
        $mpdf->SetAutoPageBreak(false, 0);
        $mpdf->SetTitle('Student ID Card - ' . $studentNumber);
        $mpdf->SetAuthor('NDC Identity System');
        $mpdf->SetSubject('Student Identification Card');
        return $mpdf;
    }

    public static function cleanupFile(string $filePath): bool
    {
        return is_file($filePath) && @unlink($filePath);
    }
}
