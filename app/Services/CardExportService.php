<?php

namespace App\Services;

use Mpdf\Mpdf;
use RuntimeException;

final class CardExportService
{
    /**
     * Export a student card as PDF with both front and back
     * 
     * @param string $htmlFront The front side HTML
     * @param string $htmlBack The back side HTML
     * @param string $studentNumber For filename reference
     * @return string Path to the generated PDF file
     * @throws RuntimeException If PDF generation fails
     */
    public function exportCardPdf(
        string $htmlFront,
        string $htmlBack,
        string $studentNumber
    ): string {
        if (!class_exists('Mpdf\Mpdf')) {
            throw new RuntimeException(
                'mPDF library not installed. Run: composer require mpdf/mpdf'
            );
        }

        // Standard ID card size: 85.6mm x 53.98mm (3.37" x 2.125")
        // Landscape orientation for better presentation
        $mpdf = new Mpdf([
            'format' => [85.6, 53.98],
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
            'orientation' => 'L',
            'mode' => 'utf-8',
            'tempDir' => sys_get_temp_dir(),
        ]);

        // Set metadata
        $mpdf->SetTitle('Student ID Card - ' . $studentNumber);
        $mpdf->SetAuthor('NDC Identity System');
        $mpdf->SetSubject('Student Identification Card');
        $mpdf->SetKeywords('student, id, card, identity');

        // Add front side
        $mpdf->WriteHTML($this->wrapCardHtml($htmlFront));

        // Add page break for back side
        $mpdf->AddPage();

        // Add back side
        $mpdf->WriteHTML($this->wrapCardHtml($htmlBack));

        // Generate output filename
        $sanitizedNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentNumber);
        $filename = 'card_' . ($sanitizedNumber ?: 'export') . '_' . time() . '.pdf';

        // Save to temporary directory
        $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

        try {
            $mpdf->Output($outputPath, 'F');
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Failed to generate PDF: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        if (!file_exists($outputPath)) {
            throw new RuntimeException('PDF file was not created successfully');
        }

        return $outputPath;
    }

    /**
     * Wrap card HTML with proper styling for PDF rendering
     *
     * @param string $html The card HTML
     * @return string The wrapped HTML
     */
    private function wrapCardHtml(string $html): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            margin: 0;
            padding: 0;
            background: none;
            font-family: Arial, sans-serif;
        }
        .card-container {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        .ndc-id-card-wrapper {
            width: 100% !important;
            height: 100% !important;
            max-width: none !important;
            aspect-ratio: auto !important;
        }
        @media print {
            body, html {
                margin: 0 !important;
                padding: 0 !important;
                page-break-after: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="card-container">
        $html
    </div>
</body>
</html>
HTML;
    }

    /**
     * Export a single side card as PDF (front only or back only)
     *
     * @param string $html The card HTML
     * @param string $studentNumber For filename reference
     * @param string $side 'front' or 'back' for file naming
     * @return string Path to the generated PDF file
     */
    public function exportCardSidePdf(
        string $html,
        string $studentNumber,
        string $side = 'front'
    ): string {
        if (!class_exists('Mpdf\Mpdf')) {
            throw new RuntimeException(
                'mPDF library not installed. Run: composer require mpdf/mpdf'
            );
        }

        $mpdf = new Mpdf([
            'format' => [85.6, 53.98],
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
            'orientation' => 'L',
            'mode' => 'utf-8',
            'tempDir' => sys_get_temp_dir(),
        ]);

        $mpdf->WriteHTML($this->wrapCardHtml($html));

        $sanitizedNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentNumber);
        $filename = 'card_' . ($sanitizedNumber ?: 'export') . '_' . $side . '_' . time() . '.pdf';

        $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

        try {
            $mpdf->Output($outputPath, 'F');
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Failed to generate PDF: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        if (!file_exists($outputPath)) {
            throw new RuntimeException('PDF file was not created successfully');
        }

        return $outputPath;
    }

    /**
     * Clean up temporary PDF files (call after download)
     *
     * @param string $filePath Path to the file to delete
     * @return bool True if deleted successfully
     */
    public static function cleanupFile(string $filePath): bool
    {
        if (is_file($filePath)) {
            return @unlink($filePath);
        }
        return false;
    }
}
