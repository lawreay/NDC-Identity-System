# Best Practices for ID Card Export - Implementation Guide

## Overview
The NDC Identity System currently supports template-based ID card generation. Here's the recommended approach to export actual student ID cards in multiple formats.

---

## 🏆 Recommended Export Methods (Ranked by Use Case)

### 1. **PDF Export** ⭐ BEST FOR PROFESSIONAL USE
**Use When**: Printing physical cards, official distribution, archival  
**Advantages**:
- ✅ Preserves exact layout and styling
- ✅ Print-ready (CMYK color support)
- ✅ Tamper-evident when encrypted
- ✅ Works across all devices
- ✅ Can include metadata/watermarks
- ✅ Professional appearance

**Implementation**:
```php
// Option A: Using DOMPDF (lightweight, no external dependencies)
composer require dompdf/dompdf

// Option B: Using Mpdf (better for complex layouts)
composer require mpdf/mpdf

// Option C: Using mPDF with TCPDF (enterprise)
// Best for batch processing and watermarks
```

**Recommended**: **mPDF** - Best balance of quality and performance  
**File Size**: ~200-300 KB per card  
**Export Time**: ~1-2 seconds per card

---

### 2. **PNG Image Export** ⭐⭐ BEST FOR DIGITAL SHARING
**Use When**: Email, digital wallets, web display, social media  
**Advantages**:
- ✅ Fast generation (~100-500ms per card)
- ✅ Small file size (~30-50 KB)
- ✅ Supports transparency
- ✅ Universal compatibility
- ✅ Good for batch processing

**Implementation**:
```php
// Using Imagick (wrapper around ImageMagick)
composer require imagick/imagick

// Or headless Chrome approach (better rendering)
composer require nesk/puphpeteer  // Puppeteer for PHP
```

**Recommended**: **headless Chrome/Puppeteer** - Best quality rendering  
**File Size**: ~30-50 KB per card  
**Export Time**: ~500ms-1s per card

---

### 3. **Batch Export (ZIP)** ⭐⭐⭐ BEST FOR BULK OPERATIONS
**Use When**: Bulk card generation, batch printing, year-end exports  
**What to include**:
- Individual PDFs (front/back separate or combined)
- Metadata CSV with student info
- Manifest file for verification
- Optionally, images for web use

**Implementation**:
```php
// Built-in PHP ZipArchive (no dependencies needed)
$zip = new ZipArchive();
$zip->open('cards_batch.zip', ZipArchive::CREATE);
$zip->addFile('card_001.pdf', 'cards/card_001.pdf');
$zip->addFile('manifest.csv', 'manifest.csv');
$zip->close();
```

---

### 4. **HTML Export** ⭐ BEST FOR DIGITAL WALLETS
**Use When**: Digital card storage, email distribution, mobile viewing  
**Advantages**:
- ✅ No file conversion needed
- ✅ Responsive design possible
- ✅ Can add interactive elements
- ✅ Smallest file size (~10 KB)

**Implementation**:
```html
<!-- Self-contained HTML with embedded images as base64 -->
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student ID Card</title>
  <style>
    /* Embedded print styles */
    @media print {
      body { margin: 0; }
      .card { page-break-after: avoid; }
    }
  </style>
</head>
<body>
  <!-- Embed card HTML and base64 images -->
</body>
</html>
```

---

### 5. **Print Layout** ⭐⭐ BEST FOR BATCH PRINTING
**Use When**: Printing multiple cards on single sheets, reducing paper waste  
**Features**:
- 4 cards per A4 page (or custom layout)
- Crop marks and guides
- Batch numbering
- Color management

---

## 📊 Comparison Matrix

| Export Type | Quality | Speed | File Size | Setup | Ideal For |
|------------|---------|-------|-----------|-------|-----------|
| PDF | Excellent | 1-2s | 200-300 KB | Medium | Printing, Archive |
| PNG | Very Good | 0.5-1s | 30-50 KB | Medium | Digital, Email |
| HTML | Good | <100ms | 10 KB | Easy | Web, Wallets |
| Print Layout | Excellent | 2-3s | 1-2 MB | Hard | Bulk Printing |
| ZIP Batch | Various | 5-60s | 1-100+ MB | Easy | Bulk Export |

---

## 🎯 Recommended Implementation Plan

### Phase 1: Single Card PDF Export (START HERE)
**Complexity**: Medium  
**Time to implement**: 2-3 hours  
**Libraries**: mPDF or DOMPDF

**Features**:
- [ ] Export single card as PDF
- [ ] Front and back combined
- [ ] Proper sizing for standard card stock (85.6 x 53.98 mm)
- [ ] Watermark with issue date
- [ ] Download trigger from student profile page

**New File**: `app/Services/CardExportService.php`
```php
class CardExportService
{
    public function exportCardPdf(array $card, array $template, array $organization): string
    {
        // Generate PDF with mPDF
        // Return PDF path for download
    }
    
    public function exportCardPng(array $card, array $template, array $organization): string
    {
        // Generate PNG with headless chrome
        // Return PNG path for download
    }
}
```

---

### Phase 2: Batch Export (AFTER PHASE 1)
**Complexity**: Medium  
**Time to implement**: 2-3 hours

**Features**:
- [ ] Export multiple selected cards
- [ ] ZIP file containing all PDFs
- [ ] CSV manifest with student data
- [ ] Progress tracking
- [ ] Bulk selection on students list

---

### Phase 3: Print Optimization (AFTER PHASE 2)
**Complexity**: High  
**Time to implement**: 4-5 hours

**Features**:
- [ ] Print layout (4 cards per page)
- [ ] Crop marks
- [ ] Color profiles (CMYK/RGB)
- [ ] Batch number injection
- [ ] Print preview

---

## 🔧 Recommended Tech Stack

### For PDF Export
```bash
# Best option: mPDF (professional, feature-rich)
composer require mpdf/mpdf

# Alternative: DOMPDF (simpler, smaller)
composer require dompdf/dompdf
```

### For Image Export (PNG)
```bash
# Option 1: Headless Chrome (best quality, requires Chrome/Chromium)
composer require nesk/puphpeteer

# Option 2: ImageMagick (faster, needs ImageMagick library)
# Already available on most servers
php -r "phpinfo();" | grep -i imagick
```

### For Batch Processing
```bash
# Built-in ZipArchive (no install needed)
# Use with job queue for large batches
composer require php-resque/php-resque  # Optional: for background jobs
```

---

## 🚀 Quick Start: Single Card PDF Export

### Step 1: Install mPDF
```bash
cd c:\wamp64\www\NDC\ Identity\ System
composer require mpdf/mpdf
```

### Step 2: Create CardExportService
```php
<?php
namespace App\Services;

use Mpdf\Mpdf;

class CardExportService
{
    public function exportCardPdf(string $html, string $filename): string
    {
        $mpdf = new Mpdf([
            'format' => [85.6, 53.98], // Credit card size in mm
            'margin_left' => 2,
            'margin_right' => 2,
            'margin_top' => 2,
            'margin_bottom' => 2,
            'orientation' => 'L', // Landscape
        ]);
        
        $mpdf->WriteHTML($html);
        
        $outputPath = sys_get_temp_dir() . '/' . $filename . '.pdf';
        $mpdf->Output($outputPath, 'F'); // Save to file
        
        return $outputPath;
    }
}
```

### Step 3: Add Export Button to Student Profile
```html
<div class="btn-group" role="group">
    <a href="student-id-card.php?id=<?= $id ?>" class="btn btn-outline-primary">Preview Card</a>
    <form method="post" action="export-card.php" style="display:inline;">
        <input type="hidden" name="student_id" value="<?= $id ?>">
        <input type="hidden" name="format" value="pdf">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
        <button type="submit" class="btn btn-primary">📥 Export as PDF</button>
    </form>
</div>
```

### Step 4: Create export-card.php Handler
```php
<?php
// public/export-card.php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/StudentRepository.php';
require_once __DIR__ . '/../app/SettingsRepository.php';
require_once __DIR__ . '/../app/TemplateDesigner/TemplateDesignerService.php';
require_once __DIR__ . '/../app/Services/CardExportService.php';

use App\Auth;

Auth::requireLogin();
Auth::requireCsrf();

$studentId = (int) ($_POST['student_id'] ?? 0);
$format = (string) ($_POST['format'] ?? 'pdf');

if ($studentId <= 0 || !in_array($format, ['pdf', 'png'], true)) {
    http_response_code(400);
    exit('Invalid request');
}

try {
    $repository = new StudentRepository(Database::getConnection());
    $student = $repository->findById($studentId);
    
    if (!$student) {
        throw new RuntimeException('Student not found');
    }
    
    // Generate HTML card
    $service = new TemplateDesignerService();
    $template = $service->getDefaultTemplate();
    // ... render card HTML ...
    
    // Export to file
    $exportService = new CardExportService();
    $filename = 'card_' . $student['student_number'];
    $path = $exportService->exportCardPdf($html, $filename);
    
    // Send file download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    unlink($path);
    exit;
    
} catch (Throwable $e) {
    http_response_code(500);
    exit('Export failed: ' . $e->getMessage());
}
```

---

## 📋 Security Considerations

### For Export Features
1. ✅ Authenticate all export requests (`Auth::requireLogin()`)
2. ✅ Use CSRF protection on export forms
3. ✅ Validate student ID ownership (prevent data leakage)
4. ✅ Log all export operations for audit trail
5. ✅ Rate limit exports to prevent abuse
6. ✅ Clean up temporary files after download
7. ✅ Use secure temporary directory (`sys_get_temp_dir()`)
8. ✅ Never expose file paths to users

### For Batch Exports
1. ✅ Implement file size limits
2. ✅ Add job queue for large batches (>100 cards)
3. ✅ Implement progress tracking
4. ✅ Store generated files securely
5. ✅ Implement expiration (delete after 24 hours)

---

## ⚡ Performance Optimization

### Single Card Export
```
PDF: ~1-2 seconds (acceptable)
PNG: ~0.5-1 second (good)
HTML: <100ms (excellent)
```

### Batch Export (100+ cards)
```
Sequential: 100s - 200s (too slow)
Parallel (4 workers): 25s - 50s (acceptable)
Queue-based (background): immediate (best UX)
```

**Recommendation**: Use **job queue** (php-resque, Redis) for batch > 10 cards

---

## 📦 File Structure After Implementation

```
app/
├── Services/
│   ├── CardExportService.php      (NEW)
│   └── BatchExportService.php     (FUTURE)
public/
├── export-card.php                (NEW)
├── export-batch.php               (FUTURE)
└── student-profile.php            (MODIFIED - add export button)
storage/
└── exports/                        (NEW - temp storage)
```

---

## 🎓 Summary & Recommendations

### Best Starting Point: **PDF Single Card Export**
- ✅ Most commonly requested
- ✅ Medium complexity
- ✅ Professional output
- ✅ Can expand to batch later

### Implementation Order:
1. **Phase 1**: PDF export (individual) → **START HERE**
2. **Phase 2**: Batch ZIP export
3. **Phase 3**: PNG image export
4. **Phase 4**: Print layout optimization
5. **Phase 5**: Digital wallet HTML export

### Quick Wins:
- [ ] Add "Export PDF" button to student profile
- [ ] Add "Export All" to students list
- [ ] Add progress bar for batch operations

---

## Questions to Consider

1. **Printing Requirements**: How many cards to print at once? Single or batch?
2. **Card Size**: Standard ID card (85.6 x 53.98 mm) or custom size?
3. **Front/Back**: Combine into one PDF or separate files?
4. **Watermarking**: Add issue date, expiration, watermark?
5. **Color Profile**: RGB (screen) or CMYK (professional printing)?
6. **Batch Operations**: How many cards exported at once? Up to 100? 1000?

Would you like me to implement **Phase 1 (PDF Single Card Export)** now?
