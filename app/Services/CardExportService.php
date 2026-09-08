<?php

namespace App\Services;

use Mpdf\Mpdf;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/app/TemplateDesigner/TemplateDesignerService.php';

final class CardExportService
{
    public function __construct(private ?CardRenderer $renderer = null)
    {
        $this->renderer ??= new CardRenderer();
    }

    /** @param array<string, mixed> $template @param array<string, mixed> $student @param array<string, mixed> $organization @param array<string, mixed> $theme */
    public function exportCardPng(array $template, array $student, array $organization, array $theme, string $studentNumber, string $side): string
    {
        $path = $this->renderer->renderPng($template, $student, $organization, $theme, $side);
        $safeNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentNumber) ?: 'export';
        $downloadPath = CardRenderer::temporaryDirectory() . DIRECTORY_SEPARATOR . 'card_' . $safeNumber . '_' . $side . '_' . bin2hex(random_bytes(6)) . '.png';

        if (!copy($path, $downloadPath)) {
            self::cleanupFile($path);
            throw new RuntimeException('Could not prepare the PNG download.');
        }

        self::cleanupFile($path);
        return $downloadPath;
    }

    /** @param array<string, mixed> $template @param array<string, mixed> $student @param array<string, mixed> $organization @param array<string, mixed> $theme */
    public function exportCardPdf(array $template, array $student, array $organization, array $theme, string $studentNumber): string
    {
        if (!class_exists(Mpdf::class)) {
            throw new RuntimeException('PDF export requires the mPDF library. Run: composer install');
        }

        $designer = new \TemplateDesignerService();
        $frontHtml = $designer->renderTemplateForExport($template, $student, $organization, $theme, 'front');
        $backHtml = $designer->renderTemplateForExport($template, $student, $organization, $theme, 'back');
        $mpdf = $this->newMpdf($studentNumber);
        ini_set('pcre.backtrack_limit', '10000000');

        try {
            $mpdf->WriteHTML($frontHtml);
            $mpdf->AddPage();
            $mpdf->WriteHTML($backHtml);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to generate PDF: ' . $exception->getMessage(), 0, $exception);
        }

        $safeNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentNumber) ?: 'export';
        $outputPath = CardRenderer::temporaryDirectory() . DIRECTORY_SEPARATOR . 'card_' . $safeNumber . '_' . bin2hex(random_bytes(6)) . '.pdf';

        try {
            $mpdf->Output($outputPath, 'F');
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to generate PDF: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_file($outputPath)) {
            throw new RuntimeException('PDF file was not created successfully.');
        }

        return $outputPath;
    }

    /** @param array<string, mixed> $template @param array<string, mixed> $student @param array<string, mixed> $organization @param array<string, mixed> $theme */
    public function exportCardSidePdf(array $template, array $student, array $organization, array $theme, string $studentNumber, string $side = 'front'): string
    {
        if (!in_array($side, ['front', 'back'], true)) {
            throw new RuntimeException('Invalid card side.');
        }
        if (!class_exists(Mpdf::class)) {
            throw new RuntimeException('PDF export requires the mPDF library. Run: composer install');
        }

        $svg = $side === 'front'
            ? $this->renderer->renderFront($template, $student, $organization, $theme)
            : $this->renderer->renderBack($template, $student, $organization, $theme);
        $mpdf = $this->newMpdf($studentNumber);
        ini_set('pcre.backtrack_limit', '10000000');

        try {
            $mpdf->WriteHTML($svg);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to generate PDF: ' . $exception->getMessage(), 0, $exception);
        }

        $safeNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentNumber) ?: 'export';
        $outputPath = CardRenderer::temporaryDirectory() . DIRECTORY_SEPARATOR . 'card_' . $safeNumber . '_' . $side . '_' . bin2hex(random_bytes(6)) . '.pdf';
        $mpdf->Output($outputPath, 'F');

        if (!is_file($outputPath)) {
            throw new RuntimeException('PDF file was not created successfully.');
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
        $mpdf->SetKeywords('student, id, card, identity');
        return $mpdf;
    }

    public static function cleanupFile(string $filePath): bool
    {
        return is_file($filePath) && @unlink($filePath);
    }
}
