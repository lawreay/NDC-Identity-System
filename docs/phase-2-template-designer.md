# Phase 2 — Template Designer Documentation

This document captures the completed Phase 2 work for the Template Designer.

## Goal

Phase 2 builds on Phase 1 by adding template management, portability, and default template selection. It turns the designer into a reusable, shareable module rather than a single one-off authoring page.

## Completed Features

### 1. Template management dashboard
- Added a template list view for all stored templates.
- Each item shows name, description, status, and default state.
- Actions available per template:
  - Preview
  - Edit
  - Duplicate
  - Export
  - Set as Default
  - Delete

### 2. Duplicate template support
- Added `duplicateTemplate()` in `app/TemplateDesigner/TemplateDesignerService.php`.
- Duplicate action creates a new template copy with a new ID and renamed title.
- Supports duplicate of front/back HTML, background assets, and thumbnail.

### 3. Export / import package support
- Added `exportTemplate()` to build a `.ndctemplate` ZIP package.
- Package includes `template.json`, `front.html`, `back.html`, and asset images.
- Added `importTemplate()` to import uploaded packages and create new templates.
- Import validates `template.json` contents and creates a new template directory.

### 4. Default template selection
- Added default template persistence via `default_template.json`.
- Added `setDefaultTemplate()` and `getDefaultTemplateId()`.
- Default selection is visible in the template list and preserved across page views.

### 5. Improved form & preview integration
- Continued using CodeMirror for template editing.
- Live preview remains active for front/back cards.
- Hidden textareas synchronize with the editor values for form submission.
- Background replace / remove support is kept in the template edit form.

### 6. Rendering and placeholder engine
- `renderTemplate()` now supports a broad set of placeholders:
  - student fields
  - organization fields
  - card assets
  - theme colors
  - template backgrounds
- Background images render as inline data URIs for preview and package testing.

## Files Changed

### `public/template-designer.php`
- Added template list and preview modes.
- Added template import form and package action handling.
- Added duplicate, export, set default, and delete actions.
- Added logic for edit mode with background replace/remove controls.
- Added CodeMirror integration and preview toggle buttons.

### `app/TemplateDesigner/TemplateDesignerService.php`
- Added template duplication, export, import, and default template methods.
- Added template list, load, save, and delete persistence logic.
- Added helper methods for image storage, thumbnails, and relative path normalization.
- Added background rendering and rendered template output generation.

## Architecture and Behavior

### Template persistence
- Templates are stored in `storage/templates/template_{n}` directories.
- Each template directory contains `template.json`, `front-background.png`, `back-background.png`, and `thumbnail.png`.
- Default template selection is stored in `storage/default_template.json`.

### Package portability
- Exported packages are ZIP archives with a `.ndctemplate` extension.
- Packages contain metadata plus all template payload files required to recreate the design.
- Imported packages are assigned a new template ID and saved as a fresh template.

### Default template usage
- The list view highlights the selected default template.
- The default action is disabled for the currently selected default.
- The loaded default template ID is used by the page and can be expanded to application-wide template selection in later phases.

## Phase 2 Status

This phase is complete and stable for the following scenarios:
- authoring card design templates
- managing multiple templates
- exporting and importing template packages
- duplicating existing templates
- setting and preserving a default template

## Next Phase and Phase 3 Planning

Phase 3 should focus on:
- integrating the template designer with the ID generation workflow
- adding template selection inside the student ID card generation pipeline
- template version history and rollback
- template preview data selector for real student records
- role-based access control for template management
- improved HTML/CSS validation and sanitization
- reusable design components and layout blocks
- thumbnail generation for list previews
- template settings for print layout, paper size, and card ratio
