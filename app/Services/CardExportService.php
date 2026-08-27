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

        $chromePath = $this->findChromePath();
        if ($chromePath !== null) {
            try {
                return $this->exportWithChrome($htmlFront, $htmlBack, $studentNumber, $chromePath);
            } catch (Throwable $exception) {
                error_log('Chrome card export failed; using mPDF fallback: ' . $exception->getMessage());
            }
        }

        ini_set('pcre.backtrack_limit', '10000000');

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
            'mode' => 'utf-8',
            'dpi' => 260,
            'tempDir' => sys_get_temp_dir(),
        ]);
        $mpdf->SetAutoPageBreak(false, 0);

        // Set metadata
        $mpdf->SetTitle('Student ID Card - ' . $studentNumber);
        $mpdf->SetAuthor('NDC Identity System');
        $mpdf->SetSubject('Student Identification Card');
        $mpdf->SetKeywords('student, id, card, identity');

        // Add front side
        $this->writeHtmlInChunks($mpdf, $this->wrapCardHtml($htmlFront));

        // Add page break for back side
        $mpdf->AddPage();

        // Add back side
        $this->writeHtmlInChunks($mpdf, $this->wrapCardHtml($htmlBack));

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

    private function findChromePath(): ?string
    {
        $paths = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function exportWithChrome(string $htmlFront, string $htmlBack, string $studentNumber, string $chromePath): string
    {
        $token = bin2hex(random_bytes(8));
        $htmlPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'card_' . $token . '.html';
        $pdfPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'card_' . $token . '.pdf';
        $profilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chrome_card_' . $token;

        $document = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            . '@page{size:85.6mm 53.98mm;margin:0}'
            . '*{box-sizing:border-box}'
            . 'html,body{margin:0;padding:0;width:323px;height:204px}'
            . '.page{width:323px;height:204px;overflow:hidden;page-break-after:always;position:relative}'
            . '.page:last-child{page-break-after:auto}'
            . '.card{width:856px;height:540px;transform:scale(0.3773);transform-origin:top left}'
            . '</style></head><body>'
            . '<div class="page"><div class="card">' . $htmlFront . '</div></div>'
            . '<div class="page"><div class="card">' . $htmlBack . '</div></div>'
            . '</body></html>';

        if (file_put_contents($htmlPath, $document) === false) {
            throw new RuntimeException('Could not create the temporary browser export document.');
        }

        $command = escapeshellarg($chromePath)
            . ' --headless=new --disable-gpu --no-sandbox --allow-file-access-from-files'
            . ' --no-pdf-header-footer --user-data-dir=' . escapeshellarg($profilePath)
            . ' --print-to-pdf=' . escapeshellarg($pdfPath)
            . ' ' . escapeshellarg($htmlPath);

        exec($command, $output, $exitCode);
        $this->removeTemporaryPath($htmlPath);
        $this->removeTemporaryPath($profilePath);

        if ($exitCode !== 0 || !is_file($pdfPath)) {
            $this->removeTemporaryPath($pdfPath);
            throw new RuntimeException('Chrome could not generate the PDF.');
        }

        return $pdfPath;
    }

    private function removeTemporaryPath(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
            return;
        }

        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->removeTemporaryPath($path . DIRECTORY_SEPARATOR . $entry);
                }
            }
            @rmdir($path);
        }
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
        html, body {
            width: 85mm;
            height: 53mm;
            overflow: hidden;
            page-break-after: avoid;
            page-break-before: avoid;
        }
        .card-container {
            width: 850px;
            height: 534px;
            overflow: hidden;
            position: relative;
            page-break-inside: avoid;
        }
        .ndc-id-card-wrapper {
            width: 850px !important;
            height: 534px !important;
            max-width: 850px !important;
            aspect-ratio: auto !important;
            page-break-inside: avoid;
            page-break-after: avoid;
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

    private function writeHtmlInChunks(Mpdf $mpdf, string $html): void
    {
        $parts = explode('>', $html);
        $chunk = '';

        foreach ($parts as $part) {
            $chunk .= $part . '>';
            if (strlen($chunk) >= 200000) {
                $mpdf->WriteHTML($chunk);
                $chunk = '';
            }
        }

        if ($chunk !== '') {
            $mpdf->WriteHTML($chunk);
        }
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

        ini_set('pcre.backtrack_limit', '10000000');

        $mpdf = new Mpdf([
            'format' => [85.6, 53.98],
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
            'mode' => 'utf-8',
            'dpi' => 260,
            'tempDir' => sys_get_temp_dir(),
        ]);
        $mpdf->SetAutoPageBreak(false, 0);

        $this->writeHtmlInChunks($mpdf, $this->wrapCardHtml($html));

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
