<?php

namespace App\Services;

use RuntimeException;

/** Normalizes uploaded photos and marks into small, predictable WebP assets. */
final class ImageUploadService
{
    private const MAX_BYTES = 5242880;

    /** @param array<string, mixed> $file */
    public function storeStudentPhoto(array $file, string $destinationDir, int $studentId): string
    {
        return $this->store($file, $destinationDir, 'student_' . $studentId . '_' . bin2hex(random_bytes(5)), 400, 500, false, 'uploads/student_photos');
    }

    /** @param array<string, mixed> $file */
    public function storeOrganizationLogo(array $file, string $destinationDir): string
    {
        return $this->store($file, $destinationDir, 'school_logo', 300, 300, true, 'uploads/settings');
    }

    /** @param array<string, mixed> $file */
    public function storeSignature(array $file, string $destinationDir): string
    {
        return $this->store($file, $destinationDir, 'authorized_signature', 500, 180, true, 'uploads/settings');
    }

    /** @param array<string, mixed> $file */
    private function store(
        array $file,
        string $destinationDir,
        string $baseName,
        int $canvasWidth,
        int $canvasHeight,
        bool $transparent,
        string $relativeDirectory
    ): string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The image upload failed. Please try again.');
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException('Image size must not exceed 5 MB.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            throw new RuntimeException('The uploaded image could not be read.');
        }

        $imageInfo = @getimagesize($tmpPath);
        $mimeType = is_array($imageInfo) ? strtolower((string) ($imageInfo['mime'] ?? '')) : '';
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Only PNG, JPG, JPEG, and WebP images are allowed.');
        }

        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            throw new RuntimeException('Image uploads require the PHP GD extension with WebP support.');
        }

        $source = @imagecreatefromstring((string) file_get_contents($tmpPath));
        if ($source === false) {
            throw new RuntimeException('The uploaded image is invalid or corrupted.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
        if ($canvas === false) {
            imagedestroy($source);
            throw new RuntimeException('Unable to prepare the uploaded image.');
        }

        if ($transparent) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $background = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        } else {
            imagealphablending($canvas, true);
            imagesavealpha($canvas, false);
            $background = imagecolorallocate($canvas, 255, 255, 255);
        }
        imagefill($canvas, 0, 0, $background);

        $scale = min($canvasWidth / max(1, $sourceWidth), $canvasHeight / max(1, $sourceHeight), 1);
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $offsetX = (int) floor(($canvasWidth - $width) / 2);
        $offsetY = (int) floor(($canvasHeight - $height) / 2);

        imagecopyresampled($canvas, $source, $offsetX, $offsetY, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0777, true) && !is_dir($destinationDir)) {
            imagedestroy($source);
            imagedestroy($canvas);
            throw new RuntimeException('The image upload directory could not be created.');
        }

        $filename = $baseName . '.webp';
        $destination = rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        $saved = imagewebp($canvas, $destination, 84);
        imagedestroy($source);
        imagedestroy($canvas);

        if (!$saved || !is_file($destination)) {
            throw new RuntimeException('The image could not be converted to WebP.');
        }

        return $relativeDirectory . '/' . $filename;
    }
}
