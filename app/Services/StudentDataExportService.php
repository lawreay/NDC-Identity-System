<?php

namespace App\Services;

use Mpdf\Mpdf;
use RuntimeException;

final class StudentDataExportService
{
    /** @return array<string, string> */
    public static function availableFields(): array
    {
        return [
            'student_number' => 'Student ID',
            'full_name' => 'Full name',
            'first_name' => 'First name',
            'last_name' => 'Surname',
            'gender' => 'Gender',
            'date_of_birth' => 'Date of birth',
            'phone_number' => 'Student phone',
            'qualification' => 'Qualification',
            'mode_of_study' => 'Mode of study',
            'program' => 'Program',
            'class_level' => 'Class level',
            'billing_category' => 'Billing category',
            'status' => 'Status',
            'district' => 'District',
            'traditional_authority' => 'Traditional authority',
            'village' => 'Village',
            'guardian_name' => 'Parent/guardian name',
            'guardian_relationship' => 'Relationship',
            'guardian_phone' => 'Guardian phone',
            'guardian_alt_phone' => 'Guardian alt phone',
            'guardian_email' => 'Guardian email',
            'guardian_address' => 'Guardian address',
        ];
    }

    /** @return array<int, string> */
    public static function defaultFields(): array
    {
        return [
            'student_number',
            'full_name',
            'gender',
            'date_of_birth',
            'program',
            'class_level',
            'qualification',
            'mode_of_study',
            'guardian_name',
            'guardian_phone',
        ];
    }

    /**
     * @param array<int, string> $fields
     * @return array<int, string>
     */
    public static function normalizeFields(array $fields): array
    {
        $available = self::availableFields();
        $normalized = [];
        foreach ($fields as $field) {
            $field = trim((string) $field);
            if (isset($available[$field]) && !in_array($field, $normalized, true)) {
                $normalized[] = $field;
            }
        }

        return $normalized !== [] ? $normalized : self::defaultFields();
    }

    /**
     * @param array<int, array<string, mixed>> $students
     * @param array<int, string> $fields
     */
    public function csv(array $students, array $fields): string
    {
        $fields = self::normalizeFields($fields);
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('Unable to prepare the Excel export.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $this->headers($fields));
        foreach ($students as $student) {
            fputcsv($handle, $this->row($student, $fields));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return is_string($csv) ? $csv : '';
    }

    /**
     * @param array<int, array<string, mixed>> $students
     * @param array<int, string> $fields
     */
    public function pdf(array $students, array $fields, string $title = 'Student Data Export'): string
    {
        if (!class_exists(Mpdf::class)) {
            throw new RuntimeException('PDF export requires the mPDF library. Run composer install.');
        }

        $fields = self::normalizeFields($fields);
        $tempDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'exports';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'tempDir' => $tempDir,
        ]);
        $mpdf->SetTitle($title);
        $mpdf->SetAuthor('NDC Identity System');

        $html = '<style>
            body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#172033}
            h1{font-size:16px;margin:0 0 4px}
            .meta{color:#64748b;margin-bottom:10px}
            table{width:100%;border-collapse:collapse}
            th,td{border:1px solid #d9e2ef;padding:5px;vertical-align:top}
            th{background:#eff6ff;color:#1e3a8a;text-align:left;font-weight:bold}
            tr:nth-child(even) td{background:#f8fafc}
        </style>';
        $html .= '<h1>' . $this->e($title) . '</h1>';
        $html .= '<div class="meta">Generated ' . $this->e(date('d M Y H:i')) . ' | ' . count($students) . ' student' . (count($students) === 1 ? '' : 's') . '</div>';
        $html .= '<table><thead><tr>';
        foreach ($this->headers($fields) as $header) {
            $html .= '<th>' . $this->e($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($students as $student) {
            $html .= '<tr>';
            foreach ($this->row($student, $fields) as $value) {
                $html .= '<td>' . nl2br($this->e($value)) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /** @param array<int, string> $fields @return array<int, string> */
    private function headers(array $fields): array
    {
        $available = self::availableFields();
        return array_map(static fn (string $field): string => $available[$field] ?? $field, $fields);
    }

    /** @param array<string, mixed> $student @param array<int, string> $fields @return array<int, string> */
    private function row(array $student, array $fields): array
    {
        $row = [];
        foreach ($fields as $field) {
            $row[] = $this->value($student, $field);
        }

        return $row;
    }

    /** @param array<string, mixed> $student */
    private function value(array $student, string $field): string
    {
        if ($field === 'full_name') {
            return trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
        }

        if ($field === 'mode_of_study') {
            return match (strtoupper(trim((string) ($student['qualification'] ?? '')))) {
                'MSCE' => 'Formal',
                'JCE' => 'Informal',
                default => '',
            };
        }

        return (string) ($student[$field] ?? '');
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
