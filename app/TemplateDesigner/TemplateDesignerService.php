<?php

final class TemplateDesignerService
{
    private const CARD_WIDTH = 856;
    private const CARD_HEIGHT = 540;
    private const CARD_ASPECT_RATIO = '1.586';

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

        $fullName = trim((string) ($student['full_name'] ?? ''));
        if ($fullName === '') {
            $fullName = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
        }
        if ($fullName === '') {
            $fullName = 'Student Name';
        }

        $serialNumber = (string) ($student['student_number'] ?? 'N/A');
        $verificationCode = $this->verificationCode($student, $organization);

        $replacements = [
            'student.photo' => $this->renderImageTag($this->imagePath($student['photo_path'] ?? ''), 'Student photo'),
            'student.full_name' => htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'),
            'student.student_id' => htmlspecialchars((string) ($student['student_number'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
            'student.gender' => htmlspecialchars((string) ($student['gender'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
            'student.date_of_birth' => htmlspecialchars((string) ($student['date_of_birth'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
            'student.department' => htmlspecialchars((string) ($student['department'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
            'student.program' => htmlspecialchars((string) ($student['program'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
            'student.class_level' => htmlspecialchars((string) ($student['class_level'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
            'student.qualification' => htmlspecialchars((string) ($student['qualification'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
            'student.issue_date' => htmlspecialchars((string) ($student['issue_date'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8'),
            'student.expiry_date' => htmlspecialchars((string) ($student['expiry_date'] ?? date('Y-m-d', strtotime('+1 year'))), ENT_QUOTES, 'UTF-8'),
            'student.status' => htmlspecialchars((string) ($student['status'] ?? 'Active'), ENT_QUOTES, 'UTF-8'),
            'student.signature' => $this->signatureLineHtml('Student signature'),
            'organization.logo' => $this->renderImageTag($this->imagePath($organization['logo_path'] ?? ''), 'Organization logo'),
            'organization.name' => htmlspecialchars((string) ($organization['name'] ?? 'NDC'), ENT_QUOTES, 'UTF-8'),
            'organization.school_name' => htmlspecialchars((string) ($organization['school_name'] ?? $organization['name'] ?? 'NDC'), ENT_QUOTES, 'UTF-8'),
            'organization.campus_name' => htmlspecialchars((string) ($organization['campus_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'organization.academic_programs' => nl2br(htmlspecialchars((string) ($organization['academic_programs'] ?? ''), ENT_QUOTES, 'UTF-8')),
            'organization.address' => htmlspecialchars((string) ($organization['address'] ?? 'Ntcheu'), ENT_QUOTES, 'UTF-8'),
            'organization.phone' => htmlspecialchars((string) ($organization['phone'] ?? '+265 999 000 000'), ENT_QUOTES, 'UTF-8'),
            'organization.email' => htmlspecialchars((string) ($organization['email'] ?? 'info@ndc.edu'), ENT_QUOTES, 'UTF-8'),
            'organization.website' => htmlspecialchars((string) ($organization['website'] ?? 'https://ndc.edu'), ENT_QUOTES, 'UTF-8'),
            'card.qr_code' => $this->renderQrCodeHtml($verificationCode, $student, $organization),
            'card.barcode' => $this->renderBarcodeHtml($verificationCode),
            'authorized.signature' => $this->authorizedSignatureHtml($organization['authorized_signature_path'] ?? ''),
            'authorized.name' => htmlspecialchars((string) ($organization['authorized_name'] ?? 'Authorized Officer'), ENT_QUOTES, 'UTF-8'),
            'principal.signature' => $this->authorizedSignatureHtml($organization['authorized_signature_path'] ?? ''),
            'principal.name' => htmlspecialchars((string) ($organization['authorized_name'] ?? 'Authorized Officer'), ENT_QUOTES, 'UTF-8'),
            'organization.signature' => $this->authorizedSignatureHtml($organization['authorized_signature_path'] ?? ''),
            'card.serial_number' => htmlspecialchars($serialNumber, ENT_QUOTES, 'UTF-8'),
            'card.verification_code' => htmlspecialchars($verificationCode, ENT_QUOTES, 'UTF-8'),
            'theme.primary_color' => htmlspecialchars((string) ($theme['primary_color'] ?? '#0b5ed7'), ENT_QUOTES, 'UTF-8'),
            'theme.secondary_color' => htmlspecialchars((string) ($theme['secondary_color'] ?? '#0a7e8c'), ENT_QUOTES, 'UTF-8'),
            'theme.accent_color' => htmlspecialchars((string) ($theme['accent_color'] ?? '#f4b400'), ENT_QUOTES, 'UTF-8'),
            'template.front_background' => $this->renderBackgroundImageTag($template['front_background_path'] ?? null),
            'template.back_background' => $this->renderBackgroundImageTag($template['back_background_path'] ?? null),
        ];

        foreach ($replacements as $tag => $value) {
            $payload = str_replace('{{' . $tag . '}}', $value, $payload);
        }

        $payload = $this->normalizeViewportFontSizes($payload);

        return $this->wrapCardHtml($payload);
    }

    private function wrapCardHtml(string $html): string
    {
        $width = self::CARD_WIDTH;
        $height = self::CARD_HEIGHT;
        return '<div class="ndc-id-card-wrapper" style="width:' . $width . 'px;height:' . $height . 'px;min-width:' . $width . 'px;min-height:' . $height . 'px;max-width:' . $width . 'px;max-height:' . $height . 'px;aspect-ratio:' . $width . '/' . $height . ';box-sizing:border-box;overflow:hidden;position:relative;background:#fff;border:1px solid #d1d5db;border-radius:10px;display:block;">' . $html . '</div>';
    }

    private function normalizeViewportFontSizes(string $html): string
    {
        return preg_replace_callback('/font-size\s*:\s*([0-9]*\.?[0-9]+)vw/i', static function (array $matches): string {
            $fontSize = (float) $matches[1] * 13;
            return 'font-size:' . rtrim(rtrim(number_format($fontSize, 2, '.', ''), '0'), '.') . 'px';
        }, $html) ?? $html;
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
<div style="width:100%;height:100%;padding:10px;background-image:url('{{template.front_background}}');background-size:cover;background-position:center;color:#111;font-family:Arial,sans-serif;box-sizing:border-box;overflow:hidden;">
  <div style="width:100%;height:100%;background:rgba(255,255,255,0.96);border-radius:14px;display:grid;grid-template-rows:auto 1fr auto;gap:8px;padding:10px;box-sizing:border-box;overflow:hidden;">
    <div style="display:flex;align-items:center;gap:10px;padding-bottom:4px;border-bottom:1px solid #d9dce0;">
      <img src="{{organization.logo}}" alt="Logo" style="width:54px;height:54px;object-fit:contain;">
      <div>
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#555;">{{organization.name}}</div>
        <div style="font-size:12px;font-weight:700;color:#111;">Student Identity Card</div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:100px 1fr;gap:10px;align-items:start;">
      <div style="width:100px;height:130px;border:2px solid #d1d5db;border-radius:10px;overflow:hidden;background:#f7f7f7;display:flex;align-items:center;justify-content:center;">
        <img src="{{student.photo}}" alt="Student photo" style="width:100%;height:100%;object-fit:cover;">
      </div>
      <div style="display:grid;grid-template-rows:auto auto 1fr;gap:8px;">
        <div>
          <div style="font-size:8px;text-transform:uppercase;letter-spacing:1px;color:#666;">Name</div>
          <div style="font-size:18px;font-weight:700;line-height:1.1;color:#111;">{{student.full_name}}</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:10px;color:#333;">
          <div>
            <div style="font-size:8px;text-transform:uppercase;letter-spacing:1px;color:#666;">Student ID</div>
            <div>{{student.student_id}}</div>
          </div>
          <div>
            <div style="font-size:8px;text-transform:uppercase;letter-spacing:1px;color:#666;">Valid</div>
            <div style="font-weight:700;color:{{theme.primary_color}};">{{student.issue_date}} - {{student.expiry_date}}</div>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:10px;color:#333;">
          <div>
            <div style="font-size:8px;text-transform:uppercase;letter-spacing:1px;color:#666;">Program</div>
            <div>{{student.program}}</div>
          </div>
          <div>
            <div style="font-size:8px;text-transform:uppercase;letter-spacing:1px;color:#666;">Department</div>
            <div>{{student.department}}</div>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:10px;color:#333;">
          <div>
            <div style="font-size:8px;text-transform:uppercase;letter-spacing:1px;color:#666;">Gender</div>
            <div>{{student.gender}}</div>
          </div>
          <div>
            <div style="font-size:8px;text-transform:uppercase;letter-spacing:1px;color:#666;">Qualification</div>
            <div>{{student.qualification}}</div>
          </div>
        </div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr auto;align-items:center;gap:10px;padding-top:6px;border-top:1px solid #d9dce0;">
      <div style="display:flex;align-items:center;gap:10px;font-size:9px;color:#666;text-transform:uppercase;letter-spacing:1px;">
        <span>Verification</span>
        <span>{{card.qr_code}}</span>
      </div>
      <div style="text-align:right;">
        <div style="font-size:8px;text-transform:uppercase;letter-spacing:1px;color:#666;">Status</div>
        <div style="display:inline-block;padding:4px 10px;border-radius:999px;background:{{theme.primary_color}};color:#fff;font-size:10px;font-weight:700;">VALID</div>
      </div>
    </div>
  </div>
</div>
HTML;
    }

    public function defaultBackHtml(): string
    {
        return <<<'HTML'
<div style="width:100%;height:100%;padding:8px;background-image:url('{{template.back_background}}');background-size:cover;background-position:center;color:#111;font-family:Arial,sans-serif;box-sizing:border-box;overflow:hidden;">
  <div style="width:100%;height:100%;background:rgba(255,255,255,0.96);border-radius:14px;display:grid;grid-template-rows:auto 1fr auto;gap:10px;padding:10px;box-sizing:border-box;overflow:hidden;">
    <div style="text-align:center;">
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:{{theme.secondary_color}};font-weight:700;">Important</div>
      <div style="font-size:11px;font-weight:700;color:#111;">Return if found</div>
    </div>
    <div style="display:grid;gap:6px;font-size:10px;color:#333;line-height:1.4;">
      <p style="margin:0;">This card remains property of {{organization.name}}.</p>
      <p style="margin:0;">Return if found to:</p>
      <p style="margin:0;font-weight:700;">{{organization.name}}</p>
      <p style="margin:0;">{{organization.address}}</p>
      <p style="margin:0;">Email: {{organization.email}}</p>
      <p style="margin:0;">Phone: {{organization.phone}}</p>
    </div>
    <div style="display:grid;grid-template-columns:1fr auto;align-items:end;gap:10px;">
      <div style="display:grid;gap:8px;font-size:10px;color:#333;">
        <div style="font-size:9px;text-transform:uppercase;letter-spacing:1px;color:#666;">Authorized signature</div>
        <div>{{authorized.signature}}</div>
        <div style="font-size:11px;font-weight:700;">{{authorized.name}}</div>
      </div>
      <div style="display:grid;gap:8px;text-align:center;font-size:9px;color:#666;">
        <div>Verification QR</div>
        <div>{{card.qr_code}}</div>
        <div>Barcode</div>
        <div>{{card.barcode}}</div>
      </div>
    </div>
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

    public function renderImageTag(?string $path, string $alt): string
    {
        if ($path === null || $path === '') {
            return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
        }

        if (preg_match('#^(data:|https?://)#i', $path)) {
            return $path;
        }

        $resolvedPath = $this->imagePath($path);
        if ($resolvedPath === null || !is_file($resolvedPath)) {
            return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
        }

        $mimeType = mime_content_type($resolvedPath) ?: 'image/png';
        $data = base64_encode((string) file_get_contents($resolvedPath));
        return 'data:' . $mimeType . ';base64,' . $data;
    }

    public function renderBackgroundImageTag(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }

        if (preg_match('#^(data:|https?://)#i', $path)) {
            return $path;
        }

        $resolvedPath = $this->imagePath($path);
        if ($resolvedPath === null || !is_file($resolvedPath)) {
            return '';
        }

        $mimeType = mime_content_type($resolvedPath) ?: 'image/png';
        $data = base64_encode((string) file_get_contents($resolvedPath));
        return 'data:' . $mimeType . ';base64,' . $data;
    }

    /**
     * @param array<string, mixed> $student
     * @param array<string, mixed> $organization
     */
    private function verificationCode(array $student, array $organization): string
    {
        $base = trim((string) ($student['student_number'] ?? ''));
        if ($base === '') {
            $base = trim((string) ($student['full_name'] ?? 'student'));
        }

        $org = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($organization['name'] ?? 'NDC')) ?? 'NDC');
        $org = $org !== '' ? substr($org, 0, 4) : 'NDC';
        $hash = strtoupper(substr(hash('crc32b', $base . '|' . ($student['full_name'] ?? '') . '|' . ($student['expiry_date'] ?? '')), 0, 8));

        return $org . '-' . preg_replace('/[^A-Za-z0-9]/', '', $base) . '-' . $hash;
    }

    /**
     * @param array<string, mixed> $student
     * @param array<string, mixed> $organization
     */
    private function renderQrCodeHtml(string $verificationCode, array $student, array $organization): string
    {
        $payload = [
            'verification_code' => $verificationCode,
            'student_number' => (string) ($student['student_number'] ?? ''),
            'student_name' => (string) ($student['full_name'] ?? ''),
            'organization' => (string) ($organization['name'] ?? 'NDC'),
            'expiry_date' => (string) ($student['expiry_date'] ?? ''),
        ];

        $data = json_encode($payload, JSON_UNESCAPED_SLASHES) ?: $verificationCode;
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=' . rawurlencode($data);

        return '<img src="' . htmlspecialchars($qrUrl, ENT_QUOTES, 'UTF-8') . '" alt="QR code" style="width:100%;height:100%;max-width:100%;max-height:100%;object-fit:contain;display:inline-block;">';
    }

    private function renderBarcodeHtml(string $value): string
    {
        $encodedValue = strtoupper(preg_replace('/[^A-Z0-9.-]/', '', $value) ?? '');
        if ($encodedValue === '') {
            $encodedValue = 'NDC000000';
        }

        $hashBits = '';
        foreach (str_split(hash('sha256', $encodedValue)) as $hex) {
            $hashBits .= str_pad(base_convert($hex, 16, 2), 4, '0', STR_PAD_LEFT);
        }

        $bars = [];
        $x = 8;
        $width = 220;
        $height = 62;
        $barHeight = 42;

        foreach (str_split('1010' . $hashBits . '0101') as $index => $bit) {
            $barWidth = $bit === '1' ? (($index % 5 === 0) ? 3 : 2) : 1;
            if ($bit === '1') {
                $bars[] = '<rect x="' . $x . '" y="4" width="' . $barWidth . '" height="' . $barHeight . '" fill="#111"/>';
            }
            $x += $barWidth + 1;
            if ($x > $width - 8) {
                break;
            }
        }

        $label = htmlspecialchars($encodedValue, ENT_QUOTES, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '"><rect width="100%" height="100%" fill="#fff"/>' . implode('', $bars) . '<text x="110" y="58" font-family="Arial, Helvetica, sans-serif" font-size="9" fill="#111" text-anchor="middle">' . $label . '</text></svg>';

        return '<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" alt="Barcode" style="width:100%;height:100%;max-width:220px;max-height:62px;min-width:120px;min-height:34px;object-fit:contain;display:inline-block;">';
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
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (preg_match('#^(data:|https?://)#i', $path)) {
            return $path;
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if (preg_match('~^[A-Za-z]:\\\\~', $normalized) || str_starts_with($normalized, DIRECTORY_SEPARATOR)) {
            return is_file($normalized) ? $normalized : null;
        }

        $projectRootPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR);
        if (is_file($projectRootPath)) {
            return $projectRootPath;
        }

        $publicPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR);
        return is_file($publicPath) ? $publicPath : null;
    }

    public function authorizedSignatureHtml(?string $path): string
    {
        $imagePath = $this->imagePath($path ?? '');
        if ($imagePath === null) {
            return $this->signatureLineHtml('Authorized');
        }

        $imageTag = $this->renderImageTag($imagePath, 'Authorized signature');
        if (str_starts_with($imageTag, 'data:image/gif;base64')) {
            return $this->signatureLineHtml('Authorized');
        }

        return '<img src="' . htmlspecialchars($imageTag, ENT_QUOTES, 'UTF-8') . '" alt="Authorized signature" style="max-width:140px;max-height:44px;object-fit:contain;">';
    }

    private function signatureLineHtml(string $label): string
    {
        return '<div style="display:inline-block;width:140px;height:44px;border-bottom:2px solid #111;padding-top:24px;text-align:center;font-size:12px;color:#111;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>';
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
