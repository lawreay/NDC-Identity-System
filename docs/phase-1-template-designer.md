# Phase 1 — Template Designer Documentation

This document captures everything completed for Phase 1 of the Template Designer.

## Goal

Phase 1 focuses on making template authoring professional and usable.
It does not cover template portability, version history, or advanced engine features.
Those are reserved for Phase 2+.

## Completed Features

### 1. Code editor replacement
- Replaced plain `<textarea>` template editors with CodeMirror.
- Implemented two editor panes:
  - `#frontEditor` for front HTML
  - `#backEditor` for back HTML
- CodeMirror configuration:
  - `mode: 'htmlmixed'`
  - `lineNumbers: true`
  - `autoCloseBrackets: true`
  - `matchBrackets: true`
- Uses CodeMirror 5 assets from CDN.
- Added `.CodeMirror` height styling for a stable editing area.

### 2. Live preview
- Added live preview panels for front and back templates.
- Live preview updates automatically when the user stops typing.
- Debounce interval set to 450ms.
- Rendered HTML is injected directly into the preview containers:
  - `#livePreviewFront`
  - `#livePreviewBack`
- Hidden textareas are synchronized before submit:
  - `#frontHtmlInput`
  - `#backHtmlInput`

### 3. Tag toolbox
- Added a tag toolbox UI in the right-hand panel with categories:
  - Student
  - Organization
  - Card
  - Template
  - Theme
- Each button inserts its tag at the active editor cursor position.
- Active editor detection is based on which CodeMirror pane is visible.

### 4. Background image browser UI
- Replaced simple file inputs with a richer image browser section.
- Each background panel includes:
  - preview area
  - Replace button
  - Remove button
- Remove buttons mark hidden flags:
  - `remove_front_background`
  - `remove_back_background`
- If the user removes a background, the preview area resets.

## Files Changed

### `public/template-designer.php`
- Added CodeMirror assets and initialization.
- Added live preview rendering JavaScript.
- Added editor tabs for front/back switching.
- Added tag toolbox markup and insertion behavior.
- Added improved background image browser UI.
- Preserved the existing save form and POST handling.

### `app/TemplateDesigner/TemplateDesignerService.php`
- Added support for removing saved background image files.
- Normalized stored paths to use web-friendly forward slashes.
- Added helper methods:
  - `removeFile()`
  - `relativePath()`

## Architecture and Behavior

### Editor flow
- `CodeMirror` editors replace visible textarea controls.
- Hidden textareas continue to hold field values for form submission.
- The preview updates via the `renderLivePreview()` function.
- The form still submits to the same PHP POST handler.

### Tag insertion
- Buttons are rendered with `data-tag` values.
- Clicking a tag inserts text into the currently visible editor.
- The active editor is determined by the front/back tab state.

### Preview behavior
- The page initially renders server-side preview values for the selected template.
- Client-side preview updates as the editor content changes.
- Front/back preview toggling is available in the right-hand panel.

## Remaining Phase 1 Improvements

These items were intentionally deferred until Phase 2 or later:
- true HTML syntax checking on the client
- template thumbnails generated on save
- duplicate template action
- template export/import
- version history
- advanced conditional template engine features
- test data selector panel

## Notes

- The current implementation focuses on authoring ergonomics.
- It is modular: phase 1 changes are isolated to `public/template-designer.php` and `app/TemplateDesigner/TemplateDesignerService.php`.
- Phase 2 should build on this foundation with management, portability, and engine enhancements.
