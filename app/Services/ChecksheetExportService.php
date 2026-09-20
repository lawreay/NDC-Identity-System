<?php

namespace App\Services;

use Mpdf\Mpdf;
use RuntimeException;

final class ChecksheetExportService
{
    /** @param array<string, mixed> $checksheet @param array<string, string> $settings */
    public function renderPdf(array $checksheet, array $settings): string
    {
        if (!class_exists(Mpdf::class)) {
            throw new RuntimeException('Checksheet PDF export requires the mPDF library. Run composer install.');
        }

        $student = (array) ($checksheet['student'] ?? []);
        $programme = (array) ($checksheet['programme'] ?? []);
        $summary = (array) ($checksheet['summary'] ?? []);
        $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
        if ($name === '' || (string) ($programme['code'] ?? '') === '') {
            throw new RuntimeException('The checksheet information is incomplete.');
        }

        $organization = trim((string) ($settings['organization_name'] ?? '')) ?: 'NDC Identity System';
        try {
            $mpdf = new Mpdf([
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 15,
                'tempDir' => sys_get_temp_dir(),
            ]);
            $mpdf->SetTitle('Checksheet - ' . (string) ($student['student_number'] ?? 'student'));
            $mpdf->SetAuthor($organization);
            $mpdf->SetSubject('Academic checksheet');
            $mpdf->WriteHTML($this->html($organization, $name, $student, $programme, $summary, (array) ($checksheet['courses'] ?? [])));
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to generate the checksheet PDF: ' . $exception->getMessage(), 0, $exception);
        }

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'checksheet_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $student['student_number']) . '_' . bin2hex(random_bytes(6)) . '.pdf';
        $mpdf->Output($path, 'F');
        if (!is_file($path) || filesize($path) === 0) {
            @unlink($path);
            throw new RuntimeException('Checksheet PDF generation did not produce a file.');
        }

        return $path;
    }

    /** @param array<string, mixed> $student @param array<string, mixed> $programme @param array<string, mixed> $summary @param array<int, array<string, mixed>> $courses */
    private function html(string $organization, string $name, array $student, array $programme, array $summary, array $courses): string
    {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $rows = '';
        foreach ($courses as $course) {
            $mark = $course['result_mark'] ?? null;
            $result = $mark === null ? 'Not available' : number_format((float) $mark, 2) . ' (' . (string) ($course['result_grade'] ?? '') . ')';
            $rows .= '<tr><td><strong>' . $e((string) ($course['code'] ?? '')) . '</strong><br><span class="muted">' . $e((string) ($course['name'] ?? '')) . '</span></td><td class="center">' . $e(number_format((float) ($course['credits'] ?? 0), 2)) . '</td><td>' . $e($result) . '</td><td class="center">' . $e(strtoupper((string) ($course['requirement_status'] ?? 'outstanding'))) . '</td></tr>';
        }
        $complete = !empty($summary['is_complete']) ? 'COMPLETE' : 'IN PROGRESS';

        return '<style>
            body { font-family: dejavusans, sans-serif; color: #172b4d; font-size: 10pt; }
            .header { border-bottom: 3px solid #153a70; padding-bottom: 8mm; }
            .organization { color: #153a70; font-size: 18pt; font-weight: bold; }
            .title { color: #b48216; font-size: 23pt; font-family: dejavuserif, serif; font-weight: bold; margin-top: 4mm; }
            .details { margin: 8mm 0; padding: 5mm; background: #f4f7fb; border-left: 4px solid #153a70; }
            table { border-collapse: collapse; width: 100%; } th { background: #153a70; color: #fff; text-align: left; padding: 3mm 2mm; font-size: 8.5pt; }
            td { border-bottom: 1px solid #d8e0ec; padding: 3mm 2mm; vertical-align: top; } .center { text-align: center; } .muted { color: #53657f; font-size: 8.5pt; }
            .summary { margin: 5mm 0 7mm; } .summary td { border: 0; padding: 3mm; background: #f4f7fb; }
        </style>
        <div class="header"><div class="organization">' . $e($organization) . '</div><div class="title">ACADEMIC CHECKSHEET</div></div>
        <table class="details"><tr><td width="60%"><strong>Student:</strong> ' . $e($name) . '<br><strong>Student Number:</strong> ' . $e((string) ($student['student_number'] ?? '')) . '</td><td width="40%"><strong>Programme:</strong> ' . $e((string) ($programme['code'] ?? '') . ' - ' . (string) ($programme['name'] ?? '')) . '<br><strong>Completion:</strong> ' . $complete . '</td></tr></table>
        <table class="summary"><tr><td><strong>Required courses</strong><br>' . (int) ($summary['completed_required_courses'] ?? 0) . ' / ' . (int) ($summary['required_courses'] ?? 0) . '</td><td><strong>Completed credits</strong><br>' . $e(number_format((float) ($summary['completed_credits'] ?? 0), 2)) . '</td><td><strong>Outstanding required</strong><br>' . (int) ($summary['outstanding_required_courses'] ?? 0) . '</td></tr></table>
        <table><thead><tr><th width="46%">Course</th><th width="14%" class="center">Credits</th><th width="22%">Approved result</th><th width="18%" class="center">Completion</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }
}
