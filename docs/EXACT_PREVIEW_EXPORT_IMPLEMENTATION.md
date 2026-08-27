# Exact Preview Card Export

## Current implementation

The preview and PDF export now use the same selected template:

- `public/student-id-card.php` sends `student_id` and `template_id` to the export endpoint.
- `public/export-card.php` loads that template instead of always loading the default template.
- The generated PDF keeps the card dimensions at 85.6 x 53.98 mm, with front and back on separate pages.
- The exporter scales the 856 x 540 preview canvas to the physical page and avoids mPDF's conflicting orientation override.
- Large rendered HTML is written in chunks and the PCRE limit is raised for embedded images, preventing mPDF's generic export failure.
- When Chrome or Edge is installed, headless Chromium is used first. Each side is captured at the native 856 x 540 preview size, then Chrome places those snapshots on the physical PDF pages. This preserves typography and prevents browser CSS from being split across pages.
- The snapshot PDF is image-based, so PDF text selection/search is not available; visual fidelity is prioritized for printed ID cards.
- Chrome captures at 2x device resolution (1712 x 1080) so small text and fine barcode/QR details remain sharper when printed.

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

On Windows, Chrome is detected at `C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe` and Edge is used as a fallback when Chrome is unavailable. mPDF remains the fallback renderer only when no browser renderer is available.

## PNG preview export

PNG export is separate from PDF export and does not use mPDF or PDF as an intermediate format.

- Endpoint: `public/export-card-png.php`
- Service method: `CardExportService::exportCardPng()`
- Controls: Front and Back `Export PNG` buttons on `public/student-id-card.php`
- Inputs: authenticated `student_id`, selected `template_id`, validated `side`, and CSRF token
- Output: `card_STUDENTNUMBER_front.png` or `card_STUDENTNUMBER_back.png`
- Target size: 1712 x 1080 pixels, representing an 856 x 540 CSS-pixel card at 2x device scale

The endpoint renders only the selected card side and uses the same template, student data, organization settings, and theme as Card Review. It rejects invalid sides, requires login and CSRF, sanitizes the filename, and removes temporary files after capture.