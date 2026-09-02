<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/TemplateDesigner/TemplateDesignerService.php';

$designer = new TemplateDesignerService();
$exporter = new App\Services\CardExportService();

$templates = ['template_19', 'template_20', 'template_21', 'template_22'];
$theme = [
    'primary_color' => '#0b5ed7',
    'secondary_color' => '#0a7e8c',
    'accent_color' => '#f4b400',
];

$cases = [
    'normal' => [
        'student' => [
            'student_number' => 'NDC001',
            'full_name' => 'Amina Banda',
            'first_name' => 'Amina',
            'last_name' => 'Banda',
            'date_of_birth' => '2004-04-12',
            'program' => 'Information Technology',
            'department' => 'ICT',
            'class_level' => 'Level 1',
            'qualification' => 'Diploma',
            'issue_date' => '2026-09-02',
            'expiry_date' => '2027-09-02',
            'status' => 'Active',
            'photo_path' => '',
        ],
        'organization' => [
            'name' => 'NDC',
            'school_name' => 'NDC',
            'campus_name' => 'Main Campus',
            'address' => 'Ntcheu',
            'phone' => '+265 999 000 000',
            'email' => 'info@ndc.edu',
            'website' => 'https://ndc.edu',
            'logo_path' => '',
            'authorized_name' => 'Authorized Officer',
            'authorized_signature_path' => '',
        ],
    ],
    'long' => [
        'student' => [
            'student_number' => 'NDC-LONG-0000000001',
            'full_name' => 'Chikondi Memory Temwani Phiri-Nyirenda The Third',
            'first_name' => 'Chikondi Memory Temwani',
            'last_name' => 'Phiri-Nyirenda The Third',
            'date_of_birth' => '2003-11-29',
            'program' => 'Advanced Applied Computer Science and Institutional Information Systems',
            'department' => 'Department of Digital Transformation, Records, and Information Governance',
            'class_level' => 'Final Year Extended Cohort',
            'qualification' => 'Advanced Diploma',
            'issue_date' => '2026-09-02',
            'expiry_date' => '2028-12-31',
            'status' => 'Active',
            'photo_path' => '',
        ],
        'organization' => [
            'name' => 'National Development College of Applied Technology and Continuing Education',
            'school_name' => 'National Development College of Applied Technology',
            'campus_name' => 'Main Campus and Distance Learning Centre',
            'address' => 'Very Long Institutional Address, Administration Block, Ntcheu District, Malawi',
            'phone' => '+265 999 000 000',
            'email' => 'registrar.office.with.a.very.long.email.address@ndc.edu',
            'website' => 'https://www.ndc.edu/programmes/student-identity-verification/records',
            'logo_path' => '',
            'authorized_name' => 'Dr. Long Authorized Signatory Name',
            'authorized_signature_path' => '',
        ],
    ],
];

foreach ($templates as $templateId) {
    $template = $designer->getTemplate($templateId);
    if ($template === null) {
        throw new RuntimeException('Missing template: ' . $templateId);
    }

    foreach ($cases as $caseName => $case) {
        $frontHtml = $designer->renderTemplate($template, $case['student'], $case['organization'], $theme, 'front');
        $backHtml = $designer->renderTemplate($template, $case['student'], $case['organization'], $theme, 'back');

        foreach (['front' => $frontHtml, 'back' => $backHtml] as $side => $html) {
            if (preg_match('/{{[^}]+}}/', $html, $match)) {
                throw new RuntimeException($templateId . ' ' . $caseName . ' ' . $side . ' has unresolved placeholder ' . $match[0]);
            }

            $pngPath = $exporter->exportCardPng($html, (string) $case['student']['student_number'], $side);
            $size = getimagesize($pngPath);
            if ($size === false || $size[0] !== 1712 || $size[1] !== 1080) {
                throw new RuntimeException($templateId . ' ' . $caseName . ' ' . $side . ' PNG dimensions failed');
            }
            App\Services\CardExportService::cleanupFile($pngPath);
        }

        $pdfPath = $exporter->exportCardPdf($frontHtml, $backHtml, (string) $case['student']['student_number']);
        if (!is_file($pdfPath) || filesize($pdfPath) <= 0) {
            throw new RuntimeException($templateId . ' ' . $caseName . ' PDF export failed');
        }
        $pdfSize = filesize($pdfPath);
        App\Services\CardExportService::cleanupFile($pdfPath);

        echo $templateId . ' ' . $caseName . ' PNG front/back 1712x1080; PDF ' . $pdfSize . " bytes\n";
    }
}
