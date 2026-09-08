<?php

namespace App\Services;

use Mpdf\QrCode\Output\Png as QrPngOutput;
use Mpdf\QrCode\Output\Svg as QrSvgOutput;
use Mpdf\QrCode\QrCode;
use RuntimeException;

/**
 * Renders the physical card with fixed SVG/GD coordinates.
 * The HTML template remains the browser preview format; exports do not execute it.
 */
final class CardRenderer
{
    public const CARD_WIDTH = 856;
    public const CARD_HEIGHT = 540;
    public const CARD_WIDTH_MM = 85.6;
    public const CARD_HEIGHT_MM = 53.98;
    public const CARD_DPI = 254;

    private const NAVY = '#06233d';
    private const YELLOW = '#ffd24a';
    private const INK = '#172033';
    private const MUTED = '#6b7280';
    private const LIGHT_PANEL = '#f5f7fa';

    private string $projectRoot;
    /** @var array<string, array<string, mixed>> */
    private array $cardDataCache = [];

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 2);
    }

    public static function temporaryDirectory(): string
    {
        $directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'exports';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        if (is_dir($directory) && is_writable($directory)) {
            return $directory;
        }

        $systemDirectory = sys_get_temp_dir();
        if (is_dir($systemDirectory) && is_writable($systemDirectory)) {
            return $systemDirectory;
        }

        throw new RuntimeException('The export temporary directory is not writable.');
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, mixed> $student
     * @param array<string, mixed> $organization
     * @param array<string, mixed> $theme
     */
    public function renderFront(array $template, array $student, array $organization = [], array $theme = []): string
    {
        return $this->renderSvg($this->cardData($template, $student, $organization, $theme), 'front');
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, mixed> $student
     * @param array<string, mixed> $organization
     * @param array<string, mixed> $theme
     */
    public function renderBack(array $template, array $student, array $organization = [], array $theme = []): string
    {
        return $this->renderSvg($this->cardData($template, $student, $organization, $theme), 'back');
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, mixed> $student
     * @param array<string, mixed> $organization
     * @param array<string, mixed> $theme
     */
    public function renderPng(
        array $template,
        array $student,
        array $organization,
        array $theme,
        string $side
    ): string {
        if (!in_array($side, ['front', 'back'], true)) {
            throw new RuntimeException('Invalid card side.');
        }

        if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng') || !function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Card PNG export requires the PHP GD extension.');
        }

        $data = $this->cardData($template, $student, $organization, $theme);
        $canvas = imagecreatetruecolor(self::CARD_WIDTH, self::CARD_HEIGHT);
        if ($canvas === false) {
            throw new RuntimeException('Unable to create the card PNG canvas.');
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);
        $this->fill($canvas, '#ffffff');
        $this->drawPngCard($canvas, $data, $side);

        $path = self::temporaryDirectory() . DIRECTORY_SEPARATOR . 'card_' . bin2hex(random_bytes(10)) . '_' . $side . '.png';
        if (!@imagepng($canvas, $path, 9)) {
            imagedestroy($canvas);
            throw new RuntimeException('Unable to write the card PNG export. Check that storage/exports is writable.');
        }

        imagedestroy($canvas);
        $dimensions = @getimagesize($path);
        if ($dimensions === false || $dimensions[0] !== self::CARD_WIDTH || $dimensions[1] !== self::CARD_HEIGHT) {
            @unlink($path);
            throw new RuntimeException('Card PNG export did not produce the required 856x540 dimensions.');
        }

        return $path;
    }

    public function imageToDataUri(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (preg_match('#^data:image/[a-z0-9.+-]+(?:;[^,]+)?,#i', $path) === 1) {
            return $path;
        }

        $resolvedPath = $this->resolveLocalImagePath($path);
        if ($resolvedPath === null) {
            return null;
        }

        $contents = file_get_contents($resolvedPath);
        if ($contents === false) {
            return null;
        }

        // mPDF can flatten transparent WebP images to black when they are
        // embedded directly in an SVG. Normalize card artwork to PNG first.
        if (function_exists('imagecreatefromstring') && function_exists('imagepng')) {
            $image = @imagecreatefromstring($contents);
            if ($image !== false) {
                $width = imagesx($image);
                $height = imagesy($image);
                $canvas = imagecreatetruecolor($width, $height);
                if ($canvas !== false) {
                    $white = imagecolorallocate($canvas, 255, 255, 255);
                    imagefill($canvas, 0, 0, $white);
                    imagealphablending($canvas, true);
                    imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
                    ob_start();
                    $written = imagepng($canvas, null, 6);
                    $pngContents = ob_get_clean();
                    imagedestroy($canvas);
                } else {
                    $written = false;
                    $pngContents = false;
                }
                imagedestroy($image);

                if ($written && is_string($pngContents) && $pngContents !== '') {
                    return 'data:image/png;base64,' . base64_encode($pngContents);
                }
            }
        }

        $mimeType = function_exists('mime_content_type') ? mime_content_type($resolvedPath) : false;
        if (!is_string($mimeType) || !str_starts_with($mimeType, 'image/')) {
            $mimeType = 'image/png';
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, mixed> $student
     * @param array<string, mixed> $organization
     * @param array<string, mixed> $theme
     * @return array<string, mixed>
     */
    private function cardData(array $template, array $student, array $organization, array $theme): array
    {
        $cacheKey = hash('sha256', serialize([$template, $student, $organization, $theme]));
        if (isset($this->cardDataCache[$cacheKey])) {
            return $this->cardDataCache[$cacheKey];
        }

        $fullName = trim((string) ($student['full_name'] ?? ''));
        if ($fullName === '') {
            $fullName = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
        }

        $studentNumber = trim((string) ($student['student_number'] ?? '')) ?: 'N/A';
        $fullName = $fullName !== '' ? $fullName : 'Student Name';
        $organizationName = trim((string) ($organization['name'] ?? '')) ?: 'NDC';
        $verificationCode = $this->verificationCode($studentNumber, $fullName, (string) ($student['expiry_date'] ?? ''), $organizationName);

        $qrPayload = json_encode([
            'verification_code' => $verificationCode,
            'student_number' => $studentNumber,
            'student_name' => $fullName,
            'organization' => $organizationName,
            'expiry_date' => (string) ($student['expiry_date'] ?? ''),
        ], JSON_UNESCAPED_SLASHES);

        if ($qrPayload === false) {
            $qrPayload = $verificationCode;
        }

        $qrCode = new QrCode($qrPayload, QrCode::ERROR_CORRECTION_MEDIUM);
        $qrSvg = (new QrSvgOutput())->output($qrCode, 240, 'white', 'black');
        $qrPng = (new QrPngOutput())->output($qrCode, 240, [255, 255, 255], [0, 0, 0], 9);

        $data = [
            'template' => $template,
            'palette' => $this->palette($template, $theme),
            'student_number' => $studentNumber,
            'full_name' => $fullName,
            'program' => trim((string) ($student['program'] ?? 'N/A')) ?: 'N/A',
            'department' => trim((string) ($student['department'] ?? 'N/A')) ?: 'N/A',
            'expiry_date' => trim((string) ($student['expiry_date'] ?? 'N/A')) ?: 'N/A',
            'website' => trim((string) ($organization['website'] ?? '')) ?: 'https://ndc.edu',
            'organization_name' => $organizationName,
            'address' => trim((string) ($organization['address'] ?? '')) ?: 'Ntcheu',
            'phone' => trim((string) ($organization['phone'] ?? '')) ?: '+265 999 000 000',
            'email' => trim((string) ($organization['email'] ?? '')) ?: 'info@ndc.edu',
            'authorized_name' => trim((string) ($organization['authorized_name'] ?? '')) ?: 'Authorized Officer',
            'verification_code' => $verificationCode,
            'photo' => $this->imageToDataUri((string) ($student['photo_path'] ?? '')),
            'logo' => $this->imageToDataUri((string) ($organization['logo_path'] ?? '')),
            'signature' => $this->imageToDataUri((string) ($organization['authorized_signature_path'] ?? '')),
            'qr_svg' => $qrSvg,
            'qr_png' => $qrPng,
        ];

        $this->cardDataCache[$cacheKey] = $data;
        return $data;
    }

    /**
     * The default Minimal Ribbon card is reproduced with fixed coordinates. Other
     * templates retain their selection and palette while using this deterministic
     * physical-card export profile instead of executing arbitrary HTML/CSS.
     *
     * @param array<string, mixed> $template
     * @param array<string, mixed> $theme
     * @return array<string, string>
     */
    private function palette(array $template, array $theme): array
    {
        $html = (string) ($template['front_html'] ?? '') . (string) ($template['back_html'] ?? '');
        preg_match_all('/#[0-9a-f]{6}/i', $html, $matches);
        $colors = array_values(array_unique(array_map('strtolower', $matches[0] ?? [])));
        $dark = self::NAVY;
        $accent = (string) ($theme['accent_color'] ?? self::YELLOW);

        foreach ($colors as $color) {
            [$red, $green, $blue] = $this->rgb($color);
            $luminance = (0.299 * $red) + (0.587 * $green) + (0.114 * $blue);
            if ($luminance < 95) {
                $dark = $color;
                break;
            }
        }

        foreach ($colors as $color) {
            [$red, $green, $blue] = $this->rgb($color);
            if ($red > 160 && $green > 120 && $blue < 140) {
                $accent = $color;
                break;
            }
        }

        return [
            'navy' => $dark,
            'yellow' => $accent,
            'ink' => self::INK,
            'muted' => self::MUTED,
        ];
    }

    /** @param array<string, mixed> $data */
    private function renderSvg(array $data, string $side): string
    {
        $palette = $data['palette'];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="' . self::CARD_WIDTH_MM . 'mm" height="' . self::CARD_HEIGHT_MM . 'mm" viewBox="0 0 ' . self::CARD_WIDTH . ' ' . self::CARD_HEIGHT . '">';
        $svg .= '<rect width="' . self::CARD_WIDTH . '" height="' . self::CARD_HEIGHT . '" fill="#fff"/>';
        $svg .= $side === 'front' ? $this->frontSvg($data, $palette) : $this->backSvg($data, $palette);
        return $svg . '</svg>';
    }

    /** @param array<string, mixed> $data @param array<string, string> $palette */
    private function frontSvg(array $data, array $palette): string
    {
        $svg = '<rect width="856" height="155" fill="' . $this->escapeXml($palette['navy']) . '"/>';
        $svg .= '<rect y="155" width="856" height="9" fill="' . $this->escapeXml($palette['yellow']) . '"/>';
        $svg .= '<circle cx="816" cy="30" r="136" fill="none" stroke="' . $this->escapeXml($palette['yellow']) . '" stroke-opacity=".18" stroke-width="28"/>';

        $svg .= $this->svgImage($data['photo'], 54, 147, 200, 200, 'xMidYMid slice', 'Student photo', true);
        $svg .= '<rect x="45" y="138" width="218" height="218" rx="16" fill="none" stroke="#fff" stroke-width="9"/>';
        $svg .= '<rect x="64" y="372" width="180" height="30" rx="15" fill="' . $this->escapeXml($palette['navy']) . '"/>';
        $svg .= $this->svgText(154, 393, $this->fitText((string) $data['student_number'], 150, 13), 13, $palette['yellow'], true, 1, 'middle');
        $svg .= $this->svgText(307, 52, 'STUDENT IDENTITY', 13, $palette['yellow'], true, 1.8);
        $svg .= $this->svgText(307, 86, $this->fitText((string) $data['organization_name'], 330, 18), 18, '#fff', true);
        $svg .= '<rect x="736" y="32" width="88" height="88" rx="12" fill="#fff"/>';
        $svg .= $this->svgImage($data['logo'], 744, 40, 72, 72, 'xMidYMid meet', 'Organization logo');

        $nameSize = $this->fitFontSize((string) $data['full_name'], 510, 44, 27);
        $svg .= $this->svgText(307, 245, $this->fitText((string) $data['full_name'], 510, $nameSize), $nameSize, $palette['ink'], true);
        $svg .= $this->svgText(307, 289, $this->fitText((string) $data['program'], 510, 19), 19, $palette['navy'], true);
        $svg .= $this->svgText(307, 320, $this->fitText((string) $data['department'], 510, 14), 14, $palette['muted']);

        $svg .= '<rect x="307" y="350" width="252" height="60" fill="' . self::LIGHT_PANEL . '"/><rect x="307" y="350" width="5" height="60" fill="' . $this->escapeXml($palette['yellow']) . '"/>';
        $svg .= '<rect x="571" y="350" width="253" height="60" fill="' . self::LIGHT_PANEL . '"/><rect x="571" y="350" width="5" height="60" fill="' . $this->escapeXml($palette['navy']) . '"/>';
        $svg .= $this->svgText(325, 372, 'DEPARTMENT', 10, $palette['muted'], true, 1);
        $svg .= $this->svgText(325, 396, $this->fitText((string) $data['department'], 220, 14), 14, $palette['ink'], true);
        $svg .= $this->svgText(589, 372, 'VALID UNTIL', 10, $palette['muted'], true, 1);
        $svg .= $this->svgText(589, 396, $this->fitText((string) $data['expiry_date'], 220, 14), 14, $palette['ink'], true);

        $svg .= '<line x1="307" y1="427" x2="824" y2="427" stroke="#e5e7eb" stroke-width="2"/>';
        $svg .= $this->svgText(307, 467, $this->fitText((string) $data['website'], 390, 11), 11, $palette['muted'], true);
        $svg .= '<rect x="742" y="430" width="82" height="82" rx="8" fill="#fff" stroke="' . $this->escapeXml($palette['navy']) . '" stroke-width="2"/>';
        $svg .= $this->svgImage($this->dataUri('image/png', (string) $data['qr_png']), 749, 437, 68, 68, 'none', 'Verification QR code');

        return $svg;
    }

    /** @param array<string, mixed> $data @param array<string, string> $palette */
    private function backSvg(array $data, array $palette): string
    {
        $svg = '<rect width="856" height="540" fill="' . $this->escapeXml($palette['navy']) . '"/>';
        $svg .= '<path d="M856 0H564C503 0 454 49 454 110V430C454 491 503 540 564 540H856Z" fill="#fff"/>';
        $svg .= '<rect y="526" width="454" height="14" fill="' . $this->escapeXml($palette['yellow']) . '"/>';
        $svg .= '<circle cx="50" cy="530" r="158" fill="none" stroke="' . $this->escapeXml($palette['yellow']) . '" stroke-opacity=".13" stroke-width="32"/>';

        $svg .= $this->svgText(38, 112, $this->fitText((string) $data['organization_name'], 390, 28), 28, $palette['yellow'], true);
        $svg .= $this->svgText(38, 161, $this->fitText((string) $data['address'], 390, 16), 16, '#dbeafe');
        $svg .= $this->svgText(38, 187, $this->fitText((string) $data['phone'], 390, 16), 16, '#dbeafe');
        $svg .= $this->svgText(38, 213, $this->fitText((string) $data['email'], 390, 16), 16, '#dbeafe');
        $svg .= '<rect x="38" y="241" width="100" height="5" rx="3" fill="' . $this->escapeXml($palette['yellow']) . '"/>';
        $svg .= $this->svgText(38, 276, 'This card is the property of', 15, '#fff', true);
        $svg .= $this->svgText(38, 298, 'the institution.', 15, '#fff', true);
        $svg .= $this->svgText(38, 320, 'Return this card if found.', 15, '#fff', true);
        $svg .= $this->svgText(38, 353, 'Authorized by', 14, '#9fc3df', true);
        $svg .= '<rect x="38" y="372" width="220" height="72" rx="8" fill="#fff" stroke="' . $this->escapeXml($palette['yellow']) . '" stroke-width="2"/>';
        if ($data['signature'] !== null) {
            $svg .= $this->svgImage($data['signature'], 51, 379, 194, 58, 'xMidYMid meet', 'Authorized signature');
        }
        $svg .= $this->svgText(38, 464, $this->fitText((string) $data['authorized_name'], 220, 15), 15, '#fff', true);
        $svg .= $this->svgText(38, 485, 'AUTHORIZED SIGNATURE', 9, '#9fc3df', false, 1.2);

        $svg .= $this->svgText(696, 165, 'VERIFY STUDENT', 16, $palette['navy'], true, 1.7, 'middle');
        $svg .= '<rect x="615" y="190" width="160" height="160" rx="14" fill="#fff" stroke="' . $this->escapeXml($palette['navy']) . '" stroke-width="5"/>';
        $svg .= $this->svgImage($this->dataUri('image/png', (string) $data['qr_png']), 628, 203, 134, 134, 'none', 'Verification QR code');
        $svg .= $this->svgText(696, 382, 'Scan the QR code to verify', 12, '#475569', true, 0, 'middle');
        $svg .= $this->svgText(696, 399, 'this student identity card.', 12, '#475569', true, 0, 'middle');

        return $svg;
    }

    /** @param array<string, mixed> $data @param resource $canvas */
    private function drawPngCard($canvas, array $data, string $side): void
    {
        $palette = $data['palette'];
        if ($side === 'front') {
            $this->fillRect($canvas, 0, 0, 856, 155, $palette['navy']);
            $this->fillRect($canvas, 0, 155, 856, 164, $palette['yellow']);
            $this->drawCircle($canvas, 816, 30, 150, $palette['yellow'], 28, 18);
            $this->drawImageCover($canvas, $data['photo'], 54, 147, 200, 200);
            $this->fillRoundedRect($canvas, 64, 372, 244, 402, 15, $palette['navy']);
            $this->drawText($canvas, 154, 393, $this->fitText((string) $data['student_number'], 150, 13), 13, $palette['yellow'], true, 1, 'center');
            $this->drawText($canvas, 307, 52, 'STUDENT IDENTITY', 13, $palette['yellow'], true, 1.8);
            $this->drawText($canvas, 307, 86, $this->fitText((string) $data['organization_name'], 330, 18), 18, '#fff', true);
            $this->fillRoundedRect($canvas, 736, 32, 824, 120, 12, '#fff');
            $this->drawImageContain($canvas, $data['logo'], 744, 40, 72, 72);
            $nameSize = $this->fitFontSize((string) $data['full_name'], 510, 44, 27);
            $this->drawText($canvas, 307, 245, $this->fitText((string) $data['full_name'], 510, $nameSize), $nameSize, $palette['ink'], true);
            $this->drawText($canvas, 307, 289, $this->fitText((string) $data['program'], 510, 19), 19, $palette['navy'], true);
            $this->drawText($canvas, 307, 320, $this->fitText((string) $data['department'], 510, 14), 14, $palette['muted']);
            $this->fillRect($canvas, 307, 350, 559, 410, self::LIGHT_PANEL);
            $this->fillRect($canvas, 307, 350, 312, 410, $palette['yellow']);
            $this->fillRect($canvas, 571, 350, 824, 410, self::LIGHT_PANEL);
            $this->fillRect($canvas, 571, 350, 576, 410, $palette['navy']);
            $this->drawText($canvas, 325, 372, 'DEPARTMENT', 10, $palette['muted'], true, 1);
            $this->drawText($canvas, 325, 396, $this->fitText((string) $data['department'], 220, 14), 14, $palette['ink'], true);
            $this->drawText($canvas, 589, 372, 'VALID UNTIL', 10, $palette['muted'], true, 1);
            $this->drawText($canvas, 589, 396, $this->fitText((string) $data['expiry_date'], 220, 14), 14, $palette['ink'], true);
            $this->line($canvas, 307, 427, 824, 427, '#e5e7eb', 2);
            $this->drawText($canvas, 307, 467, $this->fitText((string) $data['website'], 390, 11), 11, $palette['muted'], true);
            $this->fillRoundedRect($canvas, 742, 430, 824, 512, 8, '#fff');
            $this->strokeRoundedRect($canvas, 742, 430, 824, 512, 8, $palette['navy'], 2);
            $this->drawQrPng($canvas, $data['qr_png'], 749, 437, 68, 68);
            $this->strokeRoundedRect($canvas, 45, 138, 263, 356, 16, '#fff', 9);
            return;
        }

        $this->fill($canvas, $palette['navy']);
        $this->fillRightPanel($canvas);
        $this->fillRect($canvas, 0, 526, 454, 540, $palette['yellow']);
        $this->drawCircle($canvas, 50, 530, 190, $palette['yellow'], 32, 13);
        $this->drawText($canvas, 38, 112, $this->fitText((string) $data['organization_name'], 390, 28), 28, $palette['yellow'], true);
        $this->drawText($canvas, 38, 161, $this->fitText((string) $data['address'], 390, 16), 16, '#dbeafe');
        $this->drawText($canvas, 38, 187, $this->fitText((string) $data['phone'], 390, 16), 16, '#dbeafe');
        $this->drawText($canvas, 38, 213, $this->fitText((string) $data['email'], 390, 16), 16, '#dbeafe');
        $this->fillRoundedRect($canvas, 38, 241, 138, 246, 3, $palette['yellow']);
        $this->drawText($canvas, 38, 276, 'This card is the property of', 15, '#fff', true);
        $this->drawText($canvas, 38, 298, 'the institution.', 15, '#fff', true);
        $this->drawText($canvas, 38, 320, 'Return this card if found.', 15, '#fff', true);
        $this->drawText($canvas, 38, 353, 'Authorized by', 14, '#9fc3df', true);
        $this->fillRoundedRect($canvas, 38, 372, 258, 444, 8, '#fff');
        $this->strokeRoundedRect($canvas, 38, 372, 258, 444, 8, $palette['yellow'], 2);
        $this->drawImageContain($canvas, $data['signature'], 51, 379, 194, 58);
        $this->drawText($canvas, 38, 464, $this->fitText((string) $data['authorized_name'], 220, 15), 15, '#fff', true);
        $this->drawText($canvas, 38, 485, 'AUTHORIZED SIGNATURE', 9, '#9fc3df', false, 1.2);
        $this->drawText($canvas, 696, 165, 'VERIFY STUDENT', 16, $palette['navy'], true, 1.7, 'center');
        $this->fillRoundedRect($canvas, 615, 190, 775, 350, 14, '#fff');
        $this->strokeRoundedRect($canvas, 615, 190, 775, 350, 14, $palette['navy'], 5);
        $this->drawQrPng($canvas, $data['qr_png'], 628, 203, 134, 134);
        $this->drawText($canvas, 696, 382, 'Scan the QR code to verify', 12, '#475569', true, 0, 'center');
        $this->drawText($canvas, 696, 399, 'this student identity card.', 12, '#475569', true, 0, 'center');
    }

    /** @param resource $canvas */
    private function fillRightPanel($canvas): void
    {
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 564, 0, 856, 540, $white);
        imagefilledellipse($canvas, 564, 110, 220, 220, $white);
        imagefilledellipse($canvas, 564, 430, 220, 220, $white);
        imagefilledrectangle($canvas, 454, 110, 856, 430, $white);
    }

    /** @param resource $canvas */
    private function drawQrPng($canvas, string $png, int $x, int $y, int $width): void
    {
        $qr = @imagecreatefromstring($png);
        if ($qr === false) {
            return;
        }
        imagecopyresampled($canvas, $qr, $x, $y, 0, 0, $width, $width, imagesx($qr), imagesy($qr));
        imagedestroy($qr);
    }

    /** @param resource $canvas */
    private function drawImageCover($canvas, ?string $dataUri, int $x, int $y, int $width, int $height): void
    {
        $source = $this->imageFromDataUri($dataUri);
        if ($source === false) {
            $this->fillRect($canvas, $x, $y, $x + $width, $y + $height, '#f3f4f6');
            return;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = max($width / max(1, $sourceWidth), $height / max(1, $sourceHeight));
        $cropWidth = (int) round($width / $scale);
        $cropHeight = (int) round($height / $scale);
        $sourceX = (int) max(0, floor(($sourceWidth - $cropWidth) / 2));
        $sourceY = (int) max(0, floor(($sourceHeight - $cropHeight) / 2));
        imagecopyresampled($canvas, $source, $x, $y, $sourceX, $sourceY, $width, $height, $cropWidth, $cropHeight);
        imagedestroy($source);
    }

    /** @param resource $canvas */
    private function drawImageContain($canvas, ?string $dataUri, int $x, int $y, int $width, int $height): void
    {
        $source = $this->imageFromDataUri($dataUri);
        if ($source === false) {
            return;
        }

        $scale = min($width / max(1, imagesx($source)), $height / max(1, imagesy($source)));
        $targetWidth = (int) max(1, round(imagesx($source) * $scale));
        $targetHeight = (int) max(1, round(imagesy($source) * $scale));
        $targetX = $x + (int) floor(($width - $targetWidth) / 2);
        $targetY = $y + (int) floor(($height - $targetHeight) / 2);
        imagecopyresampled($canvas, $source, $targetX, $targetY, 0, 0, $targetWidth, $targetHeight, imagesx($source), imagesy($source));
        imagedestroy($source);
    }

    /** @return resource|false */
    private function imageFromDataUri(?string $dataUri)
    {
        if ($dataUri === null || $dataUri === '') {
            return false;
        }

        if (preg_match('#^data:[^;]+;base64,(.*)$#s', $dataUri, $matches) !== 1) {
            return false;
        }

        $bytes = base64_decode($matches[1], true);
        return $bytes === false ? false : @imagecreatefromstring($bytes);
    }

    /** @param resource $canvas */
    private function fill($canvas, string $color): void
    {
        imagefill($canvas, 0, 0, $this->color($canvas, $color));
    }

    /** @param resource $canvas */
    private function fillRect($canvas, int $left, int $top, int $right, int $bottom, string $color): void
    {
        imagefilledrectangle($canvas, $left, $top, $right, $bottom, $this->color($canvas, $color));
    }

    /** @param resource $canvas */
    private function fillRoundedRect($canvas, int $left, int $top, int $right, int $bottom, int $radius, string $color): void
    {
        $fill = $this->color($canvas, $color);
        $diameter = $radius * 2;
        imagefilledrectangle($canvas, $left + $radius, $top, $right - $radius, $bottom, $fill);
        imagefilledrectangle($canvas, $left, $top + $radius, $right, $bottom - $radius, $fill);
        imagefilledellipse($canvas, $left + $radius, $top + $radius, $diameter, $diameter, $fill);
        imagefilledellipse($canvas, $right - $radius, $top + $radius, $diameter, $diameter, $fill);
        imagefilledellipse($canvas, $left + $radius, $bottom - $radius, $diameter, $diameter, $fill);
        imagefilledellipse($canvas, $right - $radius, $bottom - $radius, $diameter, $diameter, $fill);
    }

    /** @param resource $canvas */
    private function strokeRoundedRect($canvas, int $left, int $top, int $right, int $bottom, int $radius, string $color, int $width): void
    {
        $lineColor = $this->color($canvas, $color);
        for ($offset = 0; $offset < $width; $offset++) {
            imagerectangle($canvas, $left + $offset, $top + $offset, $right - $offset, $bottom - $offset, $lineColor);
        }
    }

    /** @param resource $canvas */
    private function drawCircle($canvas, int $centerX, int $centerY, int $diameter, string $color, int $width, int $opacity): void
    {
        $lineColor = imagecolorallocatealpha($canvas, ...array_merge($this->rgb($color), [127 - (int) round(127 * ($opacity / 100))]));
        for ($offset = 0; $offset < $width; $offset++) {
            imagearc($canvas, $centerX, $centerY, $diameter - $offset, $diameter - $offset, 0, 360, $lineColor);
        }
    }

    /** @param resource $canvas */
    private function line($canvas, int $x1, int $y1, int $x2, int $y2, string $color, int $width): void
    {
        imagesetthickness($canvas, $width);
        imageline($canvas, $x1, $y1, $x2, $y2, $this->color($canvas, $color));
        imagesetthickness($canvas, 1);
    }

    /** @param resource $canvas */
    private function drawText($canvas, int $x, int $baseline, string $text, int $size, string $color, bool $bold = false, float $letterSpacing = 0, string $align = 'left'): void
    {
        $font = $this->fontPath($bold);
        $textWidth = $this->textWidth($text, $size, $font, $letterSpacing);
        if ($align === 'center') {
            $x -= (int) round($textWidth / 2);
        }
        imagettftext($canvas, $size, 0, $x, $baseline, $this->color($canvas, $color), $font, $text);
    }

    private function fontPath(bool $bold): string
    {
        $path = $this->projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'mpdf' . DIRECTORY_SEPARATOR . 'mpdf' . DIRECTORY_SEPARATOR . 'ttfonts' . DIRECTORY_SEPARATOR . ($bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf');
        if (!is_file($path)) {
            throw new RuntimeException('Card PNG export requires the bundled DejaVu Sans font.');
        }
        return $path;
    }

    private function textWidth(string $text, int $size, string $font, float $letterSpacing = 0): float
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $width = abs($box[2] - $box[0]);
        return $width + max(0, mb_strlen($text, 'UTF-8') - 1) * $letterSpacing;
    }

    private function fitFontSize(string $text, int $maxWidth, int $size, int $minimum): int
    {
        $font = $this->fontPath(true);
        while ($size > $minimum && $this->textWidth($text, $size, $font) > $maxWidth) {
            $size--;
        }
        return $size;
    }

    private function fitText(string $text, int $maxWidth, int $size): string
    {
        $font = $this->fontPath(true);
        if ($this->textWidth($text, $size, $font) <= $maxWidth) {
            return $text;
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        while (count($characters) > 1) {
            array_pop($characters);
            $candidate = rtrim(implode('', $characters)) . '...';
            if ($this->textWidth($candidate, $size, $font) <= $maxWidth) {
                return $candidate;
            }
        }

        return '...';
    }

    private function svgText(int $x, int $baseline, string $text, int $size, string $color, bool $bold = false, float $letterSpacing = 0, string $anchor = 'start'): string
    {
        $weight = $bold ? '700' : '400';
        $spacing = $letterSpacing !== 0.0 ? ' letter-spacing="' . $letterSpacing . 'px"' : '';
        return '<text x="' . $x . '" y="' . $baseline . '" text-anchor="' . $anchor . '" font-family="DejaVu Sans,Arial,sans-serif" font-size="' . $size . 'px" font-weight="' . $weight . '" fill="' . $this->escapeXml($color) . '"' . $spacing . '>' . $this->escapeXml($text) . '</text>';
    }

    private function svgImage(?string $dataUri, int $x, int $y, int $width, int $height, string $preserveAspectRatio, string $label, bool $placeholder = false): string
    {
        if ($dataUri === null || $dataUri === '') {
            return $placeholder
                ? '<rect x="' . $x . '" y="' . $y . '" width="' . $width . '" height="' . $height . '" fill="#f3f4f6"/><text x="' . ($x + $width / 2) . '" y="' . ($y + $height / 2) . '" text-anchor="middle" font-family="DejaVu Sans,Arial,sans-serif" font-size="12px" fill="#9ca3af">PHOTO</text>'
                : '';
        }

        $encodedUri = $this->escapeXml($dataUri);
        return '<image x="' . $x . '" y="' . $y . '" width="' . $width . '" height="' . $height . '" preserveAspectRatio="' . $preserveAspectRatio . '" href="' . $encodedUri . '" xlink:href="' . $encodedUri . '" aria-label="' . $this->escapeXml($label) . '"/>';
    }

    private function dataUri(string $mimeType, string $contents): string
    {
        return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** @return array{0:int,1:int,2:int} */
    private function rgb(string $color): array
    {
        $color = ltrim($color, '#');
        if (strlen($color) === 3) {
            $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
        }
        if (strlen($color) !== 6 || !ctype_xdigit($color)) {
            return [0, 0, 0];
        }
        return [hexdec(substr($color, 0, 2)), hexdec(substr($color, 2, 2)), hexdec(substr($color, 4, 2))];
    }

    /** @param resource $canvas */
    private function color($canvas, string $color): int
    {
        [$red, $green, $blue] = $this->rgb($color);
        return imagecolorallocate($canvas, $red, $green, $blue);
    }

    private function resolveLocalImagePath(string $path): ?string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if (preg_match('~^[A-Za-z]:\\\\~', $normalized) === 1 || str_starts_with($normalized, DIRECTORY_SEPARATOR)) {
            return is_file($normalized) ? $normalized : null;
        }

        $candidates = [
            $this->projectRoot . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR),
            $this->projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function verificationCode(string $studentNumber, string $fullName, string $expiryDate, string $organizationName): string
    {
        $org = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', $organizationName));
        $org = $org !== '' ? substr($org, 0, 4) : 'NDC';
        $hash = strtoupper(substr(hash('crc32b', $studentNumber . '|' . $fullName . '|' . $expiryDate), 0, 8));
        return $org . '-' . (preg_replace('/[^A-Za-z0-9]/', '', $studentNumber) ?: 'NA') . '-' . $hash;
    }
}
