<?php

namespace App\Services;

use Mpdf\Mpdf;
use RuntimeException;

final class CertificateExportService
{
    /** @param array<string, mixed> $certificate @param array<string, string> $settings */
    public function renderPdf(array $certificate, array $settings, string $verificationUrl): string
    {
        if (!class_exists(Mpdf::class)) {
            throw new RuntimeException('Certificate PDF export requires the mPDF library. Run composer install.');
        }
        if (($certificate['status'] ?? '') !== 'issued') {
            throw new RuntimeException('Only issued certificates can be exported.');
        }

        $organization = trim((string) ($settings['organization_name'] ?? '')) ?: 'NDC Identity System';
        $schoolName = trim((string) ($settings['school_name'] ?? '')) ?: $organization;
        $studentName = trim((string) ($certificate['first_name'] ?? '') . ' ' . (string) ($certificate['last_name'] ?? ''));
        $qualification = trim((string) ($certificate['qualification'] ?? '')) ?: (string) ($certificate['programme_name'] ?? '');
        $issueDate = date('d F Y', strtotime((string) ($certificate['issued_at'] ?? 'now')));
        $token = (string) ($certificate['verification_token'] ?? '');

        if ($studentName === '' || $token === '') {
            throw new RuntimeException('Certificate information is incomplete.');
        }

        try {
            $mpdf = new Mpdf([
                'format' => 'A4-L',
                'margin_left' => 14,
                'margin_right' => 14,
                'margin_top' => 14,
                'margin_bottom' => 14,
                'tempDir' => sys_get_temp_dir(),
            ]);
            $mpdf->SetTitle('Certificate - ' . (string) $certificate['certificate_number']);
            $mpdf->SetAuthor($organization);
            $mpdf->SetSubject('Academic Certificate');
            $mpdf->WriteHTML($this->html(
                $organization,
                $schoolName,
                $studentName,
                $qualification,
                (string) ($certificate['programme_code'] ?? ''),
                (string) ($certificate['certificate_number'] ?? ''),
                $issueDate,
                $verificationUrl
            ));
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to generate the certificate PDF: ' . $exception->getMessage(), 0, $exception);
        }

        $directory = sys_get_temp_dir();
        $path = $directory . DIRECTORY_SEPARATOR . 'certificate_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $certificate['certificate_number']) . '_' . bin2hex(random_bytes(6)) . '.pdf';
        try {
            $mpdf->Output($path, 'F');
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to save the certificate PDF.', 0, $exception);
        }
        if (!is_file($path) || filesize($path) === 0) {
            @unlink($path);
            throw new RuntimeException('Certificate PDF generation did not produce a file.');
        }

        return $path;
    }

    private function html(string $organization, string $schoolName, string $studentName, string $qualification, string $programmeCode, string $number, string $issueDate, string $verificationUrl): string
    {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $qr = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');

        return '<style>
            body { font-family: dejavusans, sans-serif; color: #12233f; }
            .sheet { border: 5px solid #153a70; outline: 2px solid #d6a834; outline-offset: -12px; min-height: 173mm; padding: 20mm 18mm; text-align: center; }
            .organization { color: #153a70; font-size: 15pt; font-weight: bold; letter-spacing: 1px; }
            .school { font-size: 11pt; margin-top: 2mm; color: #42526b; }
            .title { margin: 12mm 0 4mm; color: #b48216; font-family: dejavuserif, serif; font-size: 31pt; font-weight: bold; letter-spacing: 2px; }
            .subtitle { font-size: 12pt; letter-spacing: 2px; text-transform: uppercase; }
            .name { margin: 11mm 0 4mm; font-family: dejavuserif, serif; font-size: 28pt; font-weight: bold; border-bottom: 1px solid #c9a24c; padding-bottom: 4mm; }
            .statement { margin: 5mm auto; width: 215mm; font-size: 13pt; line-height: 1.7; }
            .qualification { margin: 7mm 0; font-family: dejavuserif, serif; font-size: 19pt; font-weight: bold; color: #153a70; }
            .footer { margin-top: 14mm; width: 100%; }
            .meta { color: #53657f; font-size: 8.5pt; line-height: 1.55; text-align: left; }
            .signature { border-top: 1px solid #64748b; width: 65mm; margin: 10mm auto 0; padding-top: 2mm; font-size: 9pt; }
            .qr { text-align: right; }
            .qr-note { color: #53657f; font-size: 7.5pt; }
        </style>
        <div class="sheet">
            <div class="organization">' . $e($organization) . '</div>
            <div class="school">' . $e($schoolName) . '</div>
            <div class="title">CERTIFICATE</div>
            <div class="subtitle">Academic Achievement</div>
            <div class="statement">This is to certify that</div>
            <div class="name">' . $e($studentName) . '</div>
            <div class="statement">has satisfied the academic requirements for the qualification</div>
            <div class="qualification">' . $e($qualification) . '</div>
            <div class="statement">Programme: ' . $e($programmeCode) . '</div>
            <table class="footer"><tr><td width="70%" class="meta">Certificate No: ' . $e($number) . '<br>Issued: ' . $e($issueDate) . '<br>Scan the QR code to verify this credential.</td><td width="30%" class="qr"><barcode code="' . $qr . '" type="QR" size="1.05" error="M" disableborder="1" /><div class="qr-note">Credential verification</div></td></tr></table>
            <div class="signature">Authorized Officer</div>
        </div>';
    }
}
