# Exact Preview Card Export

## Current implementation

The preview and PDF export now use the same selected template:

- `public/student-id-card.php` sends `student_id` and `template_id` to the export endpoint.
- `public/export-card.php` loads that template instead of always loading the default template.
- The generated PDF keeps the card dimensions at 85.6 x 53.98 mm, with front and back on separate pages.
- The exporter scales the 856 x 540 preview canvas to the physical page and avoids mPDF's conflicting orientation override.
- Large rendered HTML is written in chunks and the PCRE limit is raised for embedded images, preventing mPDF's generic export failure.
- When Chrome or Edge is installed, headless Chromium is used first so browser CSS is rendered as a fixed preview canvas; mPDF remains the fallback renderer.

This prevents exporting a different template from the one visible in the preview.

## Requirements for true visual parity

mPDF is suitable for a print-oriented PDF, but it is not a browser renderer. It may differ from the preview when templates use CSS Grid, Flexbox, transparency, rounded corners, external fonts, or browser-specific CSS.

For an export that matches the browser preview exactly, the next renderer should be Chromium/Chrome:

1. Render the same front and back HTML used by `TemplateDesignerService`.
2. Load all photos, logos, signatures, backgrounds, and QR codes from local embedded data or absolute file URLs.
3. Capture each side with a fixed viewport of 856 x 540 CSS pixels.
4. Generate a PDF with zero margins, no scaling, and a page size of 85.6 x 53.98 mm.
5. Wait for images and fonts to finish loading before capture.

The QR code currently uses an external service URL. For reliable offline exports, replace it with a locally generated or embedded QR image.

## Verification checklist

- Select a non-default template in the preview.
- Click `Export Preview PDF`.
- Confirm the PDF contains that selected template on both sides.
- Confirm the PDF page size is 85.6 x 53.98 mm.
- Compare typography, spacing, backgrounds, photos, signatures, barcode, and QR code against the preview.
- Install the Composer dependency before using the current PDF path:

```powershell
composer install
```

On Windows, Chrome is detected at `C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe` and Edge is used as a fallback when Chrome is unavailable.