<?php

namespace App\Services;

use Mpdf\Mpdf;
use RuntimeException;

final class TranscriptExportService
{
    /** @param array<string, mixed> $transcript @param array<string, string> $settings */
    public function renderPdf(array $transcript, array $settings, string $verificationUrl): string
    {
        if (!class_exists(Mpdf::class)) {
            throw new RuntimeException('Transcript PDF export requires the mPDF library. Run composer install.');
        }
        if (($transcript['status'] ?? '') !== 'issued') {
            throw new RuntimeException('Only issued transcripts can be exported.');
        }

        try {
            $snapshot = json_decode((string) ($transcript['transcript_snapshot'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new RuntimeException('The stored transcript snapshot is invalid.', 0, $exception);
        }
        if (!is_array($snapshot) || !is_array($snapshot['results'] ?? null)) {
            throw new RuntimeException('The stored transcript snapshot is incomplete.');
        }

        $organization = trim((string) ($settings['organization_name'] ?? '')) ?: 'NDC Identity System';
        $student = (array) ($snapshot['student'] ?? []);
        $programme = (array) ($snapshot['programme'] ?? []);
        $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('The stored transcript student name is incomplete.');
        }

        try {
            $mpdf = new Mpdf([
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 15,
                'tempDir' => sys_get_temp_dir(),
            ]);
            $mpdf->SetTitle('Transcript - ' . (string) $transcript['transcript_number']);
            $mpdf->SetAuthor($organization);
            $mpdf->SetSubject('Academic Transcript');
            $mpdf->WriteHTML($this->html($organization, $name, $student, $programme, $snapshot['results'], (string) $transcript['transcript_number'], (string) $transcript['issued_at'], $verificationUrl));
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to generate the transcript PDF: ' . $exception->getMessage(), 0, $exception);
        }

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'transcript_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $transcript['transcript_number']) . '_' . bin2hex(random_bytes(6)) . '.pdf';
        $mpdf->Output($path, 'F');
        if (!is_file($path) || filesize($path) === 0) {
            @unlink($path);
            throw new RuntimeException('Transcript PDF generation did not produce a file.');
        }

        return $path;
    }

    /** @param array<string, mixed> $student @param array<string, mixed> $programme @param array<int, array<string, mixed>> $results */
    private function html(string $organization, string $name, array $student, array $programme, array $results, string $number, string $issuedAt, string $verificationUrl): string
    {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $rows = '';
        foreach ($results as $result) {
            $rows .= '<tr><td>' . $e((string) ($result['academic_year'] ?? '')) . ' ' . $e((string) ($result['term_name'] ?? '')) . '</td><td>' . $e((string) ($result['course_code'] ?? '')) . '</td><td>' . $e((string) ($result['course_name'] ?? '')) . '</td><td class="num">' . $e(number_format((float) ($result['credits'] ?? 0), 2)) . '</td><td class="num">' . $e(number_format((float) ($result['mark'] ?? 0), 2)) . '</td><td class="center">' . $e((string) ($result['grade'] ?? '')) . '</td></tr>';
        }
        $issued = date('d F Y', strtotime($issuedAt ?: 'now'));

        return '<style>
            body { font-family: dejavusans, sans-serif; color: #172b4d; font-size: 10pt; }
            .header { border-bottom: 3px solid #153a70; padding-bottom: 8mm; }
            .organization { color: #153a70; font-size: 18pt; font-weight: bold; }
            .title { color: #b48216; font-size: 23pt; font-family: dejavuserif, serif; font-weight: bold; letter-spacing: 1px; margin-top: 4mm; }
            .meta { color: #53657f; font-size: 9pt; line-height: 1.55; }
            .details { margin: 8mm 0; padding: 5mm; background: #f4f7fb; border-left: 4px solid #153a70; }
            table { border-collapse: collapse; width: 100%; }
            th { background: #153a70; color: #fff; text-align: left; padding: 3mm 2mm; font-size: 8.5pt; }
            td { border-bottom: 1px solid #d8e0ec; padding: 3mm 2mm; vertical-align: top; }
            .num { text-align: right; } .center { text-align: center; }
            .footer { margin-top: 9mm; border-top: 1px solid #aab7c9; padding-top: 4mm; }
            .small { font-size: 7.5pt; color: #53657f; }
        </style>
        <div class="header"><div class="organization">' . $e($organization) . '</div><div class="title">ACADEMIC TRANSCRIPT</div><div class="meta">Official record of approved academic results</div></div>
        <table class="details"><tr><td width="60%"><strong>Student:</strong> ' . $e($name) . '<br><strong>Student Number:</strong> ' . $e((string) ($student['student_number'] ?? '')) . '<br><strong>Programme:</strong> ' . $e((string) ($programme['code'] ?? '') . ' - ' . (string) ($programme['name'] ?? '')) . '</td><td width="40%"><strong>Transcript No:</strong> ' . $e($number) . '<br><strong>Issued:</strong> ' . $e($issued) . '<br><strong>Status:</strong> ISSUED</td></tr></table>
        <table><thead><tr><th width="18%">Term</th><th width="14%">Course</th><th width="40%">Course name</th><th width="10%" class="num">Credits</th><th width="10%" class="num">Mark</th><th width="8%" class="center">Grade</th></tr></thead><tbody>' . $rows . '</tbody></table>
        <table class="footer"><tr><td width="70%" class="small">This document is an immutable snapshot of approved results at the date of issue.<br>Scan the QR code to verify this transcript.</td><td width="30%" class="center"><barcode code="' . $e($verificationUrl) . '" type="QR" size="0.9" error="M" disableborder="1" /></td></tr></table>';
    }
}
