# NDC Identity System — Version 2 Roadmap

This document breaks the Template Designer improvements into phased app updates.

## Phase 1 — Core Authoring Experience

### 1. Replace `<textarea>` with a code editor
- Add Monaco Editor or CodeMirror 6 for template authoring.
- Required features:
  - HTML syntax highlighting
  - Auto closing tags
  - Line numbers
  - Search
  - Bracket matching
- Benefit: makes large templates manageable and professional.

### 2. Live preview
- Replace the manual preview button with a live split-view editor.
- Layout:
  - Left: HTML editor
  - Right: rendered preview
- Update preview automatically after the user pauses typing.
- Benefit: immediate feedback and modern template authoring flow.

### 3. Tag toolbox
- Add an insertable tag palette with categories:
  - Student
  - Organization
  - Card
  - Template
  - Theme
- Each tag should insert at cursor position.
- Benefit: removes memorization and speeds template creation.

### 4. Image browser UI
- Replace simple upload inputs with a rich image browser:
  - Front Background
  - Back Background
- Show preview tile and actions:
  - Preview
  - Replace
  - Remove
- Benefit: better asset management and clarity for users.

## Phase 2 — Productivity and polish

### 5. Template thumbnail generation
- Generate a thumbnail when a template is saved.
- Store and display the thumbnail in the template list.
- Benefit: better visual discovery and a polished admin UI.

### 6. Duplicate template
- Add a one-click duplicate action.
- Copy template name, HTML, backgrounds, and settings.
- Benefit: encourages reuse and prevents rebuilds from scratch.

### 7. Export / Import templates
- Add template export to a `.ndctemplate` file.
- Add template import support.
- Benefit: sharing templates, library reuse, and backup.

### 8. Version history
- Track template versions each time a template is saved.
- Allow restore to prior versions.
- Benefit: recovery from accidental changes and safer editing.

## Phase 3 — Validation and engine improvements

### 9. Validation enhancements
- Improve validation feedback with line-level details.
- Example: `Line 42 — Missing </div>`.
- Benefit: IDE-like feedback for template authors.

### 10. Template engine conditional blocks
- Support optional rendering like:
  - `{{#if student.photo}} ... {{/if}}`
  - `{{#if student.signature}} ... {{/if}}`
- Benefit: flexible templates that adapt to missing student data.

### 11. Test data panel
- Add a preview data selector:
  - Sample student
  - Random student
  - Specific student ID
- Include a refresh action.
- Benefit: validates tags against real data and avoids blind testing.

## Phase 4 — UI/UX consolidation

### 12. Single-page authoring layout
- Consolidate into one screen with sections for:
  - HTML editor
  - CSS editor (if supported later)
  - Preview
  - Available tags
  - Template settings
- Benefit: a Canva-like authoring experience with no page switching.

## Overall recommendation
- The backend architecture is strong and modular.
- The next major investment should be the authoring experience.
- A polished editor, live preview, and tag toolbox will lift the product from a functional admin form to a professional template designer.

---

### Priorities
1. Code editor + live preview
2. Tag toolbox
3. Image browser
4. Thumbnail + duplicate + import/export
5. Validation improvements + conditional rendering
6. Test data panel
7. One-screen authoring layout
NDC Identity System v2 Roadmap
Version 2.1 — Professional Template Designer (Highest Priority)

Goal: Make creating templates fast and intuitive.

Features
✅ Monaco Editor (or CodeMirror)
✅ Live Preview (auto refresh)
✅ Tag Toolbox (click to insert)
✅ Front & Back Background Image Manager
✅ Built-in Template Guide
✅ HTML Validation with line numbers

Deliverable

A user should be able to create a complete ID template in under 10 minutes.

Version 2.2 — Template Management

Goal: Make templates reusable.

Features
Duplicate Template
Template Thumbnails
Template Categories
Active / Inactive Status
Default Template
Search Templates

Deliverable

Managing 20+ templates should remain simple.

Version 2.3 — Template Portability

Goal: Allow templates to be shared.

Features
Export .ndctemplate
Import .ndctemplate
Package includes:
HTML
CSS
Backgrounds
Thumbnail
Metadata
Version

Example package:

Modern Blue.ndctemplate

├── template.json
├── front.html
├── front.css
├── back.html
├── back.css
├── front-background.png
├── back-background.png
└── thumbnail.png

This makes templates portable between installations.

Version 2.4 — Smart Template Engine

This is where the engine becomes much more capable.

Features
{{#if student.photo}}

{{/if}}

{{#if organization.logo}}

{{/if}}

{{#if student.signature}}

{{/if}}

Also support:

{{#unless student.photo}}

Default image

{{/unless}}

and

{{#each achievements}}

...

{{/each}}

Even if you don't need loops today, designing the parser with them in mind avoids a future rewrite.

Version 2.5 — Testing Tools
Features
Sample Student
Random Student
Search Student
Refresh Data
Highlight Missing Tags
Missing Images Report

This is for template developers, not end users.

Version 2.6 — Version Control
Features
Save Version
Restore Version
Compare Versions
View Changes
Author
Timestamp

Very useful once multiple admins edit templates.

Version 2.7 — Advanced Designer

Only after everything else.

Features
CSS Editor
Assets Panel
Layers
Variables
Theme Editor
Split Layout

This is the "nice to have" stage.

New Feature I'd Add
Template Metadata

Every template should have metadata.

{
  "name": "Modern Blue",
  "version": "1.2.0",
  "author": "Lawrence Phuka",
  "description": "Modern landscape student ID",
  "orientation": "Landscape",
  "card_size": "CR80",
  "created_at": "2026-08-04",
  "updated_at": "2026-08-04",
  "compatible_with": ">=2.0.0"
}

This becomes invaluable when sharing templates.

Another Feature
Template Assets

Instead of only background images, allow templates to own additional assets.

Template

Background Front

Background Back

Overlay

Icons

Fonts

Watermarks

Custom Images

That future-proofs the system.