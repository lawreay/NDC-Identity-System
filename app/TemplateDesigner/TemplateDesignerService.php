<?php

final class TemplateDesignerService
{
    private string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'templates';

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0777, true);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTemplates(): array
    {
        if (!is_dir($this->storagePath)) {
            return [];
        }

        $directories = array_filter(scandir($this->storagePath) ?: [], static function (string $entry): bool {
            return $entry !== '.' && $entry !== '..' && is_dir(
                dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $entry
            );
        });

        $templates = [];
        foreach ($directories as $directory) {
            $metadataPath = $this->storagePath . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . 'template.json';
            if (!is_file($metadataPath)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($metadataPath), true);
            if (!is_array($decoded)) {
                continue;
            }

            $decoded['id'] = (string) ($decoded['id'] ?? $directory);
            $decoded['directory'] = $directory;
            $templates[] = $decoded;
        }

        usort($templates, static function (array $left, array $right): int {
            return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return $templates;
    }

    public function getTemplate(string $templateId): ?array
    {
        $templatePath = $this->templateDirectory($templateId);
        if (!is_dir($templatePath)) {
            return null;
        }

        $metadataPath = $templatePath . DIRECTORY_SEPARATOR . 'template.json';
        if (!is_file($metadataPath)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($metadataPath), true);
        if (!is_array($decoded)) {
            return null;
        }

        $decoded['id'] = (string) ($decoded['id'] ?? $templateId);
        $decoded['directory'] = $templateId;

        return $decoded;
    }

    public function createTemplate(array $input, array $files = []): array
    {
        $templateId = $this->nextTemplateId();
        return $this->saveTemplate($templateId, $input, $files);
    }

    public function updateTemplate(string $templateId, array $input, array $files = []): array
    {
        return $this->saveTemplate($templateId, $input, $files);
    }

    public function deleteTemplate(string $templateId): bool
    {
        $directory = $this->templateDirectory($templateId);
        if (!is_dir($directory)) {
            return false;
        }

        $this->removeDirectory($directory);
        return !is_dir($directory);
    }

    public function duplicateTemplate(string $templateId): ?array
    {
        $source = $this->templateDirectory($templateId);
        if (!is_dir($source)) {
            return null;
        }

        $newTemplateId = $this->nextTemplateId();
        $destination = $this->templateDirectory($newTemplateId);
        $this->copyDirectory($source, $destination);

        $metadata = $this->getTemplate($newTemplateId);
        if ($metadata === null) {
            return null;
        }

        $metadata['id'] = $newTemplateId;
        $metadata['name'] = trim((string) ($metadata['name'] ?? '') . ' Copy');
        $metadata['created_at'] = date('Y-m-d H:i:s');
        $metadata['updated_at'] = date('Y-m-d H:i:s');

        file_put_contents($destination . DIRECTORY_SEPARATOR . 'template.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $metadata;
    }

    public function exportTemplate(string $templateId): ?string
    {
        $template = $this->getTemplate($templateId);
        if ($template === null || !class_exists('ZipArchive')) {
            return null;
        }

        $exportPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'template_export_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $templateId) . '.ndctemplate';
        $zip = new ZipArchive();
        if ($zip->open($exportPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $zip->addFromString('template.json', json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('front.html', (string) ($template['front_html'] ?? ''));
        $zip->addFromString('back.html', (string) ($template['back_html'] ?? ''));

        foreach (['front_background', 'back_background', 'thumbnail'] as $assetKey) {
            $fieldKey = $assetKey . '_path';
            if (!empty($template[$fieldKey])) {
                $assetPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim((string) $template[$fieldKey], DIRECTORY_SEPARATOR);
                if (is_file($assetPath)) {
                    $zip->addFile($assetPath, basename($assetPath));
                }
            }
        }

        $zip->close();
        return $exportPath;
    }

    public function importTemplate(array $file): array
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['errors' => ['Missing import package.']];
        }

        if (!class_exists('ZipArchive')) {
            return ['errors' => ['ZipArchive is required to import templates.']];
        }

        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            return ['errors' => ['Unable to open template package.']];
        }

        $jsonIndex = $zip->locateName('template.json');
        if ($jsonIndex === false) {
            $zip->close();
            return ['errors' => ['Template package must include template.json.']];
        }

        $metadata = json_decode($zip->getFromIndex($jsonIndex), true);
        if (!is_array($metadata)) {
            $zip->close();
            return ['errors' => ['Invalid template metadata.']];
        }

        $newTemplateId = $this->nextTemplateId();
        $directory = $this->templateDirectory($newTemplateId);
        mkdir($directory, 0777, true);

        foreach (['front.html', 'back.html'] as $htmlFile) {
            $contents = $zip->getFromName($htmlFile);
            if ($contents !== false) {
                file_put_contents($directory . DIRECTORY_SEPARATOR . $htmlFile, $contents);
            }
        }

        foreach (['front-background.png', 'back-background.png', 'thumbnail.png'] as $assetFile) {
            $contents = $zip->getFromName($assetFile);
            if ($contents !== false) {
                file_put_contents($directory . DIRECTORY_SEPARATOR . $assetFile, $contents);
            }
        }

        $zip->close();

        $metadata['id'] = $newTemplateId;
        $metadata['created_at'] = date('Y-m-d H:i:s');
        $metadata['updated_at'] = date('Y-m-d H:i:s');
        $metadata['front_background_path'] = file_exists($directory . DIRECTORY_SEPARATOR . 'front-background.png') ? $this->relativePath($directory . DIRECTORY_SEPARATOR . 'front-background.png') : '';
        $metadata['back_background_path'] = file_exists($directory . DIRECTORY_SEPARATOR . 'back-background.png') ? $this->relativePath($directory . DIRECTORY_SEPARATOR . 'back-background.png') : '';
        $metadata['thumbnail_path'] = file_exists($directory . DIRECTORY_SEPARATOR . 'thumbnail.png') ? $this->relativePath($directory . DIRECTORY_SEPARATOR . 'thumbnail.png') : '';
        $metadata['name'] = trim((string) ($metadata['name'] ?? 'Imported Template'));

        file_put_contents($directory . DIRECTORY_SEPARATOR . 'template.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ['template' => $metadata];
    }

    public function setDefaultTemplate(string $templateId): void
    {
        $defaultFile = $this->defaultTemplatePath();
        file_put_contents($defaultFile, json_encode(['default' => $templateId], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function getDefaultTemplateId(): ?string
    {
        $defaultFile = $this->defaultTemplatePath();
        if (!is_file($defaultFile)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($defaultFile), true);
        if (!is_array($decoded) || empty($decoded['default'])) {
            return null;
        }

        return (string) $decoded['default'];
    }

    public function getDefaultTemplate(): ?array
    {
        $templateId = $this->getDefaultTemplateId();
        if ($templateId === null) {
            return null;
        }

        return $this->getTemplate($templateId);
    }

    private function defaultTemplatePath(): string
    {
        return dirname($this->storagePath) . DIRECTORY_SEPARATOR . 'default_template.json';
    }

    /**
     * @param string $source
     * @param string $destination
     */
    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }

        $items = scandir($source) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $src = $source . DIRECTORY_SEPARATOR . $item;
            $dest = $destination . DIRECTORY_SEPARATOR . $item;
            if (is_dir($src)) {
                $this->copyDirectory($src, $dest);
            } else {
                copy($src, $dest);
            }
        }
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, mixed> $student
     * @param array<string, mixed> $organization
     * @param array<string, mixed> $theme
     * @return string
     */
    public function renderTemplate(array $template, array $student, array $organization = [], array $theme = [], string $side = 'front'): string
    {
        $payload = $template['front_html'] ?? '';
        if ($side === 'back') {
            $payload = $template['back_html'] ?? '';
        }

        $replacements = [
            'student.photo' => $this->renderImageTag($this->imagePath($student['photo_path'] ?? ''), 'Student photo'),
            'student.full_name' => htmlspecialchars((string) ($student['full_name'] ?? $student['first_name'] . ' ' . $student['last_name'] ?? 'Student Name'), ENT_QUOTES, 'UTF-8'),
            'student.student_id' => htmlspecialchars((string) ($student['student_number'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
            'student.gender' => htmlspecialchars((string) ($student['gender'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
            'student.department' => htmlspecialchars((string) ($student['department'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
            'student.program' => htmlspecialchars((string) ($student['program'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
            'student.class_level' => htmlspecialchars((string) ($student['class_level'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
            'student.qualification' => htmlspecialchars((string) ($student['qualification'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
            'student.issue_date' => htmlspecialchars((string) ($student['issue_date'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8'),
            'student.expiry_date' => htmlspecialchars((string) ($student['expiry_date'] ?? date('Y-m-d', strtotime('+1 year'))), ENT_QUOTES, 'UTF-8'),
            'student.status' => htmlspecialchars((string) ($student['status'] ?? 'Active'), ENT_QUOTES, 'UTF-8'),
            'student.signature' => '<div style="display:inline-block;width:140px;height:44px;border-bottom:2px solid #111;padding-top:24px;text-align:center;font-size:12px;color:#111;">Signed</div>',
            'organization.logo' => $this->renderImageTag($this->imagePath($organization['logo_path'] ?? ''), 'Organization logo'),
            'organization.name' => htmlspecialchars((string) ($organization['name'] ?? 'NDC'), ENT_QUOTES, 'UTF-8'),
            'organization.address' => htmlspecialchars((string) ($organization['address'] ?? 'Ntcheu'), ENT_QUOTES, 'UTF-8'),
            'organization.phone' => htmlspecialchars((string) ($organization['phone'] ?? '+265 999 000 000'), ENT_QUOTES, 'UTF-8'),
            'organization.email' => htmlspecialchars((string) ($organization['email'] ?? 'info@ndc.edu'), ENT_QUOTES, 'UTF-8'),
            'organization.website' => htmlspecialchars((string) ($organization['website'] ?? 'https://ndc.edu'), ENT_QUOTES, 'UTF-8'),
            'card.qr_code' => '<div style="display:inline-flex;align-items:center;justify-content:center;width:90px;height:90px;border:2px dashed #999;font-size:11px;color:#666;">QR</div>',
            'card.barcode' => '<div style="display:inline-flex;align-items:center;justify-content:center;width:140px;height:44px;border:2px dashed #999;font-size:11px;color:#666;">Barcode</div>',
            'card.serial_number' => htmlspecialchars((string) ($student['student_number'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
            'card.verification_code' => htmlspecialchars((string) ($student['student_number'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
            'authorized.signature' => '<div style="display:inline-block;width:140px;height:44px;border-bottom:2px solid #111;padding-top:24px;text-align:center;font-size:12px;color:#111;">Authorized</div>',
            'authorized.name' => htmlspecialchars((string) ($organization['authorized_name'] ?? 'Authorized Officer'), ENT_QUOTES, 'UTF-8'),
            'theme.primary_color' => htmlspecialchars((string) ($theme['primary_color'] ?? '#0b5ed7'), ENT_QUOTES, 'UTF-8'),
            'theme.secondary_color' => htmlspecialchars((string) ($theme['secondary_color'] ?? '#0a7e8c'), ENT_QUOTES, 'UTF-8'),
            'theme.accent_color' => htmlspecialchars((string) ($theme['accent_color'] ?? '#f4b400'), ENT_QUOTES, 'UTF-8'),
            'template.front_background' => $this->renderBackgroundImageTag($template['front_background_path'] ?? null),
            'template.back_background' => $this->renderBackgroundImageTag($template['back_background_path'] ?? null),
        ];

        foreach ($replacements as $tag => $value) {
            $payload = str_replace('{{' . $tag . '}}', $value, $payload);
        }

        return $payload;
    }

    /**
     * @return array<int, string>
     */
    public function validateTemplate(string $frontHtml, string $backHtml): array
    {
        $errors = [];
        $errors = array_merge($errors, $this->validateHtml($frontHtml, 'front'));
        $errors = array_merge($errors, $this->validateHtml($backHtml, 'back'));
        return $errors;
    }

    public function defaultFrontHtml(): string
    {
        return <<<'HTML'
<div style="width:100%;min-height:100%;padding:24px;background-image:url('{{template.front_background}}');background-size:cover;background-position:center;background-repeat:no-repeat;color:#111;font-family:Arial,sans-serif;">
  <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 18px;border-bottom:2px solid {{theme.accent_color}};background:rgba(255,255,255,0.92);">
    <div>
      <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:{{theme.primary_color}};">{{organization.name}}</div>
      <div style="font-size:14px;color:#333;">{{organization.address}}</div>
    </div>
    <img src="{{organization.logo}}" alt="Organization logo" style="max-height:54px;max-width:110px;">
  </div>
  <div style="display:flex;gap:18px;padding:20px;align-items:center;">
    <div style="width:122px;height:146px;border:3px solid {{theme.primary_color}};overflow:hidden;background:#fff;display:flex;align-items:center;justify-content:center;">
      <img src="{{student.photo}}" alt="Student photo" style="width:100%;height:100%;object-fit:cover;">
    </div>
    <div style="flex:1;">
      <h2 style="margin:0 0 8px;font-size:24px;color:{{theme.primary_color}};">{{student.full_name}}</h2>
      <p style="margin:4px 0;font-size:14px;"><strong>Student ID:</strong> {{student.student_id}}</p>
      <p style="margin:4px 0;font-size:14px;"><strong>Program:</strong> {{student.program}}</p>
      <p style="margin:4px 0;font-size:14px;"><strong>Department:</strong> {{student.department}}</p>
      <p style="margin:4px 0;font-size:14px;"><strong>Status:</strong> {{student.status}}</p>
    </div>
  </div>
  <div style="display:flex;justify-content:space-between;align-items:center;padding:0 20px 20px;">
    <div style="text-align:center;">
      <div style="font-size:12px;color:#666;">Verification</div>
      <div style="margin-top:6px;">{{card.qr_code}}</div>
    </div>
    <div style="text-align:center;">
      <div style="font-size:12px;color:#666;">Authorized</div>
      <div style="margin-top:6px;">{{authorized.signature}}</div>
    </div>
  </div>
</div>
HTML;
    }

    public function defaultBackHtml(): string
    {
        return <<<'HTML'
<div style="width:100%;min-height:100%;padding:24px;background-image:url('{{template.back_background}}');background-size:cover;background-position:center;background-repeat:no-repeat;color:#111;font-family:Arial,sans-serif;">
  <div style="padding:18px;border:2px solid {{theme.secondary_color}};background:rgba(255,255,255,0.95);">
    <h3 style="margin:0 0 12px;font-size:20px;color:{{theme.secondary_color}};">Institution Details</h3>
    <p style="margin:4px 0;font-size:14px;"><strong>Name:</strong> {{organization.name}}</p>
    <p style="margin:4px 0;font-size:14px;"><strong>Address:</strong> {{organization.address}}</p>
    <p style="margin:4px 0;font-size:14px;"><strong>Phone:</strong> {{organization.phone}}</p>
    <p style="margin:4px 0;font-size:14px;"><strong>Email:</strong> {{organization.email}}</p>
    <p style="margin:4px 0;font-size:14px;"><strong>Website:</strong> {{organization.website}}</p>
  </div>
  <div style="display:flex;justify-content:space-between;align-items:flex-end;padding-top:18px;">
    <div>
      <div style="font-size:12px;color:#666;">Student Signature</div>
      <div style="margin-top:6px;">{{student.signature}}</div>
    </div>
    <div style="text-align:center;">
      <div style="font-size:12px;color:#666;">Barcode</div>
      <div style="margin-top:6px;">{{card.barcode}}</div>
    </div>
  </div>
  <div style="margin-top:20px;padding:14px;background:rgba(255,255,255,0.9);border-top:2px solid {{theme.accent_color}};">
    <p style="margin:0;font-size:13px;">Issued: {{student.issue_date}} &nbsp;|&nbsp; Expires: {{student.expiry_date}}</p>
    <p style="margin:6px 0 0;font-size:13px;">Authorized by {{authorized.name}}</p>
  </div>
</div>
HTML;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $files
     * @return array<string, mixed>
     */
    private function saveTemplate(string $templateId, array $input, array $files = []): array
    {
        $directory = $this->templateDirectory($templateId);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $name = trim((string) ($input['name'] ?? 'Untitled Template'));
        $description = trim((string) ($input['description'] ?? ''));
        $status = trim((string) ($input['status'] ?? 'draft'));
        $frontHtml = (string) ($input['front_html'] ?? $this->defaultFrontHtml());
        $backHtml = (string) ($input['back_html'] ?? $this->defaultBackHtml());

        $frontBackgroundPath = null;
        $backBackgroundPath = null;
        $currentTemplate = $this->getTemplate($templateId) ?? [];

        if (!empty($files['front_background'] ?? null)) {
            $frontBackgroundPath = $this->storeImageUpload($files['front_background'], $directory, 'front-background');
        }
        if (!empty($files['back_background'] ?? null)) {
            $backBackgroundPath = $this->storeImageUpload($files['back_background'], $directory, 'back-background');
        }

        if (!empty($input['remove_front_background'])) {
            $this->removeFile($directory . DIRECTORY_SEPARATOR . 'front-background.png');
            $currentTemplate['front_background_path'] = '';
        }
        if (!empty($input['remove_back_background'])) {
            $this->removeFile($directory . DIRECTORY_SEPARATOR . 'back-background.png');
            $currentTemplate['back_background_path'] = '';
        }

        $frontBackgroundPath = $frontBackgroundPath ?? ((string) ($input['front_background_path'] ?? ($currentTemplate['front_background_path'] ?? '')));
        $backBackgroundPath = $backBackgroundPath ?? ((string) ($input['back_background_path'] ?? ($currentTemplate['back_background_path'] ?? '')));

        $metadata = [
            'id' => $templateId,
            'name' => $name !== '' ? $name : 'Untitled Template',
            'description' => $description,
            'front_html' => $frontHtml,
            'back_html' => $backHtml,
            'front_background_path' => $frontBackgroundPath,
            'back_background_path' => $backBackgroundPath,
            'thumbnail_path' => $this->createThumbnail($directory, $frontBackgroundPath),
            'status' => $status,
            'created_by' => (string) ($input['created_by'] ?? 'Administrator'),
            'created_at' => (string) ($input['created_at'] ?? date('Y-m-d H:i:s')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        file_put_contents($directory . DIRECTORY_SEPARATOR . 'template.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $metadata;
    }

    private function templateDirectory(string $templateId): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . $templateId;
    }

    private function nextTemplateId(): string
    {
        $templates = $this->listTemplates();
        return 'template_' . ((count($templates) + 1));
    }

    private function storeImageUpload(?array $file, string $directory, string $baseName): ?string
    {
        if (!is_array($file) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return null;
        }

        $extension = $this->guessExtension($file);
        if ($extension === null) {
            return null;
        }

        $destinationPath = $directory . DIRECTORY_SEPARATOR . $baseName . '.png';
        $source = file_get_contents($file['tmp_name']);
        if ($source === false) {
            return null;
        }

        if (function_exists('imagecreatefromstring') && function_exists('imagepng')) {
            $image = @imagecreatefromstring($source);
            if ($image !== false) {
                imagepng($image, $destinationPath);
                imagedestroy($image);
                return $this->relativePath($destinationPath);
            }
        }

        file_put_contents($destinationPath, $source);
        return $this->relativePath($destinationPath);
    }

    private function createThumbnail(string $directory, string $backgroundPath): string
    {
        $sourcePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim($backgroundPath, DIRECTORY_SEPARATOR);
        if (!is_file($sourcePath)) {
            $sourcePath = $directory . DIRECTORY_SEPARATOR . 'front-background.png';
        }

        if (!is_file($sourcePath)) {
            return '';
        }

        $destinationPath = $directory . DIRECTORY_SEPARATOR . 'thumbnail.png';
        if (function_exists('imagecreatefromstring') && function_exists('imagecopyresampled') && function_exists('imagepng')) {
            $image = @imagecreatefromstring((string) file_get_contents($sourcePath));
            if ($image !== false) {
                $width = imagesx($image);
                $height = imagesy($image);
                $thumb = imagecreatetruecolor(220, 140);
                imagecopyresampled($thumb, $image, 0, 0, 0, 0, 220, 140, $width, $height);
                imagepng($thumb, $destinationPath);
                imagedestroy($image);
                imagedestroy($thumb);
                return $this->relativePath($destinationPath);
            }
        }

        copy($sourcePath, $destinationPath);
        return $this->relativePath($destinationPath);
    }

    private function guessExtension(array $file): ?string
    {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $name = strtolower((string) ($file['name'] ?? ''));
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        if (in_array($extension, $allowed, true)) {
            return $extension;
        }

        if (!empty($file['type'])) {
            $typeMap = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            return $typeMap[$file['type']] ?? null;
        }

        return null;
    }

    private function renderImageTag(?string $path, string $alt): string
    {
        if ($path === null || $path === '') {
            return '<span style="display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;border:1px dashed #999;color:#666;font-size:12px;">' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
        if (!is_file($absolutePath)) {
            return '<span style="display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;border:1px dashed #999;color:#666;font-size:12px;">' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        $data = base64_encode((string) file_get_contents($absolutePath));
        return 'data:image/png;base64,' . $data;
    }

    private function renderBackgroundImageTag(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }

        $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
        if (!is_file($absolutePath)) {
            return '';
        }

        $data = base64_encode((string) file_get_contents($absolutePath));
        return 'data:image/png;base64,' . $data;
    }

    private function relativePath(string $absolutePath): string
    {
        $relative = str_replace(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR, '', $absolutePath);
        return str_replace('\\', '/', $relative);
    }

    private function removeFile(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function imagePath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
        return is_file($absolutePath) ? str_replace('\\', '/', $this->relativePath($absolutePath)) : null;
    }

    private function validateHtml(string $html, string $side): array
    {
        $errors = [];
        if (trim($html) === '') {
            $errors[] = ucfirst($side) . ' HTML is required.';
            return $errors;
        }

        $sanitized = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;
        $sanitized = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $sanitized) ?? $sanitized;

        if (preg_match('/<script\b|<link\b|<iframe\b|<object\b|<embed\b|<form\b|<input\b|<button\b/i', $sanitized)) {
            $errors[] = 'Only HTML, inline CSS and internal style blocks are allowed in the template. Scripts, forms and linked assets are not supported.';
        }

        if (preg_match('/\bclass\s*=\s*["\']/i', $sanitized)) {
            $errors[] = 'Bootstrap classes are not allowed. Use inline styles instead.';
        }

        $tagPattern = '/<\/?([a-zA-Z0-9]+)(?:\s[^>]*)?>/';
        preg_match_all($tagPattern, $sanitized, $matches);
        $stack = [];
        $selfClosing = ['area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr'];
        $allowed = ['a','article','aside','b','blockquote','br','div','em','footer','figure','figcaption','h1','h2','h3','h4','h5','h6','header','hr','i','img','li','main','ol','p','section','small','span','strong','style','table','tbody','td','th','thead','tr','u','ul'];

        foreach ($matches[1] as $tagName) {
            $tag = strtolower($tagName);
            if ($tag === '!--') {
                continue;
            }

            if (!in_array($tag, $allowed, true)) {
                $errors[] = 'Unsupported tag <' . $tag . '> found in the ' . $side . ' template.';
                continue;
            }

            if (str_starts_with($matches[0][array_search($tagName, $matches[1], true)] ?? '', '</')) {
                // handled below
            }
        }

        $tokens = [];
        preg_match_all($tagPattern, $sanitized, $tokenMatches, PREG_OFFSET_CAPTURE);
        foreach ($tokenMatches[0] as $index => $tokenData) {
            $token = $tokenData[0];
            $tagName = strtolower((string) ($tokenMatches[1][$index][0] ?? ''));
            if ($tagName === 'style') {
                continue;
            }
            if ($tagName === '!--') {
                continue;
            }

            if (str_starts_with($token, '</')) {
                $closingTag = $tagName;
                if (count($stack) === 0 || end($stack) !== $closingTag) {
                    $errors[] = 'Mismatched closing tag </' . $tagName . '> in the ' . $side . ' template.';
                    continue;
                }
                array_pop($stack);
                continue;
            }

            if (in_array($tagName, $selfClosing, true)) {
                continue;
            }

            $stack[] = $tagName;
        }

        if ($stack !== []) {
            $errors[] = 'Missing closing tag(s) for: ' . implode(', ', array_unique($stack)) . ' in the ' . $side . ' template.';
        }

        return array_unique($errors);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                $this->removeDirectory($fullPath);
            } else {
                unlink($fullPath);
            }
        }

        rmdir($path);
    }
}
