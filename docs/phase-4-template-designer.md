# Phase 4 — Template Designer UI/UX Consolidation

This document captures Version 2 Phase 4 for the Template Designer.

## Goal

Consolidate the designer into a single authoring page that combines template editing, preview, asset settings, and tag guidance.

## What Phase 4 Covers

- Unified authoring screen with front/back HTML editor tabs.
- Live preview panels for front and back card output.
- A tag toolbox for fast placeholder insertion.
- Background asset browser with preview, replace, and remove controls.
- Template settings and metadata entry on the same screen.
- No separate editor/preview pages for the authoring workflow.

## Completed Phase 4 Features

### Single-page authoring layout
- The edit form includes:
  - template name, description, and status
  - front and back background selectors
  - inline CodeMirror editors for front/back HTML
  - hidden form fields to persist editor values on save
- The right-side panel includes:
  - preview toggle buttons
  - live rendered front/back preview
  - tag toolbox grouped by category
  - usage guide and example snippets

### Live preview integration
- `renderLivePreview()` updates both preview panels from the current editor content.
- Preview values are rendered from dummy student and organization data.
- Preview updates are debounced for performant typing.
- Front/back preview visibility is controlled by preview toolbar buttons.

### Editor and tag UX
- Editor tabs allow switching between front and back HTML.
- Tags insert directly into the active editor cursor position.
- Tag toolbox includes:
  - student fields
  - organization fields
  - card assets
  - template backgrounds
  - theme colors

### Asset and template management in one flow
- Background previews are shown inline.
- Replace/remove actions are available without leaving the editor.
- Template metadata and form submission remain on the same page.

## Implementation Files

- `public/template-designer.php`
  - current single-page editor UI
  - preview rendering
  - template list, import, export, duplicate, delete, and default actions
- `app/TemplateDesigner/TemplateDesignerService.php`
  - stores templates
  - renders templates with substitutions
  - manages default template selection and import/export

## Next Steps in Version 2

Phase 4 completed here. Next improvements in the Version 2 roadmap are:
- line-level validation feedback
- conditional template blocks
- test-data preview selector
- template version history
- richer metadata fields

## Notes

This phase is intentionally focused on authoring usability. The codebase now supports a designer experience that is both functional and consolidated, with the authoring and preview UX living together on one page.
