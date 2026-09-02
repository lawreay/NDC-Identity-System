# NDC Identity System

## ID Card Template Rendering Analysis

**Document:** Template Rendering Analysis  
**Project:** NDC Identity System  
**Purpose:** Explain why ID card content appeared smaller during export than it did in Card Review.

---

## 1. Executive Summary

The original ID card template was not fundamentally broken.

The main problem was that the template used a highly percentage-based responsive layout combined with fixed pixel typography, flexbox, aspect-ratio sizing, and nested percentage dimensions.

This made the card look correct in the browser during Card Review, but it also made the same template sensitive to differences in the rendering environment used during export.

The important distinction is:

> The physical card boundary was approximately correct, but the content inside the card was rendered smaller.

This affected elements such as:

- Text
- Student name
- Logo
- Student photograph
- QR code
- Metadata
- Internal spacing
- Other nested elements

The template therefore amplified differences between the browser preview renderer and the export renderer.

---

## 2. The Original Template

The original front template used a structure similar to:

```html
<div style="
    width: 100%;
    height: 100%;
    ...
">
```

Inside the card, several dimensions were percentage-based:

```css
padding: 3% 4% 0 4%;
gap: 2%;
width: 10%;
height: 16%;
flex: 0 0 20%;
gap: 4%;
gap: 1.5%;
margin-top: 2%;
```

At the same time, typography was defined using fixed pixel values:

```css
font-size: 22.1px;
font-size: 12.35px;
font-size: 29.9px;
font-size: 14.3px;
font-size: 13px;
```

The QR and photograph also depended on:

```css
aspect-ratio: 1 / 1;
```

This combination made the layout sensitive to the rendering context.

---

## 3. The Main Problem

### 3.1 Percentage-Based Dimensions Depend on the Containing Box

The template contained many rules such as:

```css
width: 20%;
padding: 5%;
gap: 4%;
height: 16%;
```

Percentages are not absolute measurements. They are calculated relative to another dimension.

For example:

```css
width: 20%;
```

means:

> 20% of the available width of the parent.

Likewise:

```css
padding: 5%;
gap: 4%;
```

depend on the layout context.

If the export renderer establishes a slightly different layout coordinate system, the resulting internal dimensions can change even though the outer card still appears correct.

---

## 4. The Second Problem: Fixed Pixel Fonts Inside a Responsive Layout

The original template mixed percentage-based geometry with fixed pixel typography.

For example:

```css
font-size: 29.9px;
```

while the surrounding layout used:

```css
padding: 5%;
gap: 4%;
flex: 0 0 20%;
```

This creates a hybrid layout. The physical size of the card can remain correct while the relationship between text and surrounding elements changes.

The result can look like:

```text
CARD BOUNDARY
+-------------------------------+
|                               |
|       smaller content         |
|                               |
|       smaller QR              |
|                               |
+-------------------------------+
```

rather than:

```text
CARD BOUNDARY
+-------------------------------+
|                               |
|     correctly sized content   |
|                               |
|       correctly sized QR      |
|                               |
+-------------------------------+
```

---

## 5. The Third Problem: Flexbox Percentage Sizing

The original template relied heavily on flexbox:

```css
display: flex;
```

with children such as:

```css
flex: 0 0 20%;
```

and:

```css
flex: 1;
```

The available width is therefore calculated dynamically.

For example:

```html
<div style="display: flex; gap: 4%;">
```

combined with:

```html
<div style="flex: 0 0 20%;">
```

and:

```html
<div style="flex: 1;">
```

means the final width of the content column depends on the available space after padding, gaps, and the specified flex basis have been calculated.

This is not inherently wrong. It becomes problematic when the same template is rendered in different environments.

---

## 6. The Fourth Problem: Aspect-Ratio Dependencies

The original template used:

```css
aspect-ratio: 1 / 1;
```

for both the student photo and QR-related elements.

For example:

```html
<div style="
    width: 100%;
    aspect-ratio: 1 / 1;
">
```

This means the browser determines the height from the calculated width.

The QR area also depended on:

```html
<span style="
    width: 100%;
    aspect-ratio: 1 / 1;
">
```

This creates another chain of dependent calculations:

```text
Card width
   |
   v
Parent width
   |
   v
Column width
   |
   v
QR container width
   |
   v
QR span width
   |
   v
QR dimensions
```

If any earlier dimension changes, the QR changes too.

This is why the QR can become smaller even though nobody explicitly told the QR to become smaller.

---

## 7. The Fifth Problem: The Export Pipeline Used a Different Rendering Context

This is the most important architectural issue.

Card Review renders the card directly in the browser.

Conceptually:

```text
Template
   |
   v
TemplateDesignerService
   |
   v
Browser DOM
   |
   v
Card Review
```

The export process, however, used a separate rendering process.

The previous architecture was effectively:

```text
Template
   |
   v
TemplateDesignerService
   |
   v
Export HTML
   |
   v
Chromium screenshot
   |
   v
PNG
   |
   v
Another HTML document
   |
   v
PDF rendering
```

This means Card Review and export were not necessarily rendering the exact same document in the exact same coordinate system.

That is the key reason the export could look different.

---

## 8. The 856 x 540 vs 850 x 534 Difference

Card Review uses approximately:

```text
856 x 540
```

while the export implementation had another card container around:

```text
850 x 534
```

The physical card was then represented using:

```text
85.6mm x 53.98mm
```

These numbers are very close, but they are not identical.

The difference alone is not large enough to explain a dramatic reduction in font or QR size. However, it adds another coordinate conversion to an already complicated rendering pipeline, so it was unnecessary and undesirable.

---

## 9. Why Simply Increasing Font Sizes Was Not the Correct Original Fix

One possible reaction would have been:

```css
font-size: 40px;
```

instead of:

```css
font-size: 30px;
```

This would make the exported version appear larger, but it would be the wrong architectural solution.

It would mean the template is being designed around a rendering bug rather than being designed correctly.

The same problem would then appear differently in:

- Card Review
- PNG export
- PDF export
- Printing
- Another browser
- Another template

The correct approach is:

> Make the template layout predictable first, then make the renderer preserve that layout.

---

## 10. What Changed

The revised template keeps the card itself responsive:

```css
width: 100%;
height: 100%;
```

but makes important internal dimensions more predictable.

For example, instead of:

```css
flex: 0 0 20%;
```

the revised template uses:

```css
flex: 0 0 23%;
```

for the photograph area.

The QR section uses an explicit width:

```css
flex: 0 0 185px;
width: 185px;
```

The QR container uses:

```css
width: 165px;
height: 165px;
```

and the QR itself:

```css
width: 139px;
height: 139px;
```

This removes several levels of percentage-based calculation.

---

## 11. Typography Was Increased

The original student name size:

```css
font-size: 29.9px;
```

became:

```css
font-size: 32px;
```

The organization name:

```css
font-size: 22.1px;
```

became:

```css
font-size: 24px;
```

The programme:

```css
font-size: 14.3px;
```

became:

```css
font-size: 15px;
```

Metadata:

```css
font-size: 13px;
```

became:

```css
font-size: 14px;
```

These are intentional visual adjustments, not compensation for an export bug.

---

## 12. Why the Revised Template Is Better

The new template has fewer chained calculations.

Instead of:

```text
percentage
   |
   v
percentage
   |
   v
flex calculation
   |
   v
aspect ratio
   |
   v
percentage
   |
   v
final size
```

important elements now behave more like:

```text
856 x 540 card
       |
       v
known content area
       |
       v
known photo area
       |
       v
known QR area
       |
       v
known typography
```

This makes the result more predictable.

---

## 13. Important Finding From the Test

The revised template is already producing a better result.

This is significant because it means the original template's responsive sizing contributed to the visual difference.

However, this does not prove that the template was the only problem. The export renderer still needs to be tested independently.

If the revised template produces:

```text
Card Review
     |
     v
correct content size
```

and:

```text
PNG export
     |
     v
still noticeably smaller content
```

then the remaining problem is in the export pipeline.

---

## 14. Correct Rendering Principle

The system should treat Card Review as the visual source of truth.

The intended architecture should eventually be:

```text
                    +---------------+
                    |   Template    |
                    +-------+-------+
                            |
                            v
                 TemplateDesignerService
                            |
                            v
                  Same rendered card HTML
                     +------+------+
                     |             |
                     v             v
                Card Review    PNG Export
                     |             |
                     |             |
              Browser display   Chromium
                     |             |
                     +------+------+
                            |
                            v
                    Same visual result
```

The exporter should not redesign the card. It should capture it.

---

## 15. Recommended PNG Export Coordinate System

The card should use this CSS canvas:

```text
856 x 540
```

For high-resolution PNG export, use this device scale:

```text
2x
```

Therefore, the final PNG should be:

```text
1712 x 1080
```

The important distinction is:

```text
856 x 540 CSS pixels
```

should remain the layout coordinate system.

The 2x scale should increase raster resolution, not redesign the layout.

---

## 16. What the Exporter Should Not Do

The PNG exporter should not:

### 16.1 Do Not Change the Card to 850 x 534

```css
width: 850px;
height: 534px;
```

### 16.2 Do Not Apply a Transform

```css
transform: scale(...);
```

### 16.3 Do Not Change Template Font Sizes

```css
font-size
```

The exporter should never modify template font sizes.

### 16.4 Do Not Use mPDF for PNG Generation

mPDF is useful for PDF generation, but it should not be involved in the direct PNG path.

### 16.5 Do Not Render a PNG and Put It Into Another HTML Document

That creates another rendering stage.

### 16.6 Do Not Recalculate the Card Layout Unnecessarily

The exporter should preserve the same layout used by Card Review.

---

## 17. Correct Strategy

The preferred PNG workflow is:

```text
Student
   |
   v
Selected Template
   |
   v
TemplateDesignerService
   |
   v
Rendered Card HTML
   |
   v
856 x 540 CSS viewport
   |
   v
Chromium
   |
   v
2x device scale
   |
   v
1712 x 1080 PNG
```

This produces a high-resolution PNG without changing the card's internal geometry.

---

## 18. Testing Procedure

Every template should be tested using the following process.

### Test 1: Card Review

Check:

- Logo size
- Student photo
- Student name
- Programme
- Student ID
- Department
- Dates
- QR code
- Borders
- Spacing

### Test 2: PNG Export

Compare the PNG directly against Card Review. Check the same elements.

### Test 3: Pixel Dimensions

The expected PNG should be approximately:

```text
1712 x 1080
```

when using 2x raster scaling.

### Test 4: Different Students

Test with:

- Short name
- Long name
- Long programme
- Missing photo
- Different QR codes

### Test 5: Different Templates

Test:

- Default template
- Custom template
- Front side
- Back side

---

## 19. Final Diagnosis

The original problem was caused by two interacting issues.

### Issue A: Template Layout Sensitivity

The template relied heavily on:

- Percentage dimensions
- Percentage padding
- Percentage gaps
- Flexbox calculations
- Aspect-ratio calculations
- Fixed pixel typography

This made it sensitive to changes in the rendering environment.

### Issue B: Export Rendering Differences

The exporter did not simply capture the same Card Review rendering. It created another rendering context and used different dimensions and conversion stages.

Therefore:

```text
Card Review != export rendering context
```

The card boundary could remain approximately correct while internal content became visually smaller.

---

## 20. Conclusion

The correct lesson is not:

> Make all the fonts bigger.

The correct lesson is:

> Use predictable template geometry and make the export renderer preserve the same coordinate system as Card Review.

The revised template addresses the first problem.

The next technical step is to ensure the PNG exporter addresses the second problem.

The desired final behavior is:

```text
Card Review
     |
     | 856 x 540 CSS layout
     v
Chromium
     |
     | 2x rasterization
     v
1712 x 1080 PNG
```

with no additional scaling, resizing, PDF conversion, or alternate card layout.

---

## Status

### Template

Improved.

### Card Review

Expected to remain visually consistent.

### PNG Export

Still requires renderer verification.

### PDF Export

Should remain unchanged until PNG export is confirmed.

### Core Principle

> The template defines the design. The exporter must reproduce the design, not redesign it.
