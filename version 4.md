# NDC Identity System — Version 4 Roadmap

This document defines the Version 4 roadmap for the NDC Identity System, moving from isolated template authoring into a full student ID card generation workflow.

## Version 4 Objective

Bring the Template Designer into the student ID lifecycle by integrating templates with student record generation, preview, and output. Version 4 turns the designer from a standalone admin tool into a production-ready card engine.

## What is complete now

- Phase 1: Template authoring
  - CodeMirror template editor
  - Front and back HTML panes
  - Tag toolbox and live preview
  - Background image browser and replace/remove support

- Phase 2: Template management
  - Template list with preview/edit/delete
  - Duplicate templates
  - Export/import `.ndctemplate` packages
  - Default template selection
  - Saved templates persisted in `storage/templates`

- Phase 3: Student integration
  - Student ID preview page created
  - Student record preview route added
  - Student number auto-generation for preview when missing
  - Preview template selection and default fallback

## Version 4 Goals

1. Integrate templates into the student ID generation workflow.
2. Add real student preview and PDF/print output.
3. Add template engine flexibility and safeguard missing data.
4. Build template versioning and metadata.
5. Improve administration UI for selecting and applying templates.

## Version 4 Scope

### 1. Template-aware ID generation workflow
- Add a template selection option inside the student profile or ID generation flow.
- Use the default template automatically when no selection is made.
- Generate a preview and a card output for the selected template.
- Store the selected template ID alongside generated student ID card metadata.

### 2. Stable card output route
- Create a dedicated route/page for final card rendering:
  - `public/student-id-card.php`
  - support front/back preview
  - support generating printable card output
- Keep preview code and final output code aligned.

### 3. Template engine enhancements
- Add conditional rendering support:
  - `{{#if student.photo}}...{{/if}}`
  - `{{#if organization.logo}}...{{/if}}`
  - `{{#unless some.value}}...{{/unless}}`
- Add `{{#each ...}}` support for repeatable lists if needed.
- Add graceful fallback placeholders for missing fields.

### 4. Student data preview panel
- Add a test-data selector for student preview.
- Allow preview with:
  - sample student
  - random student
  - current student record
- Add a refresh button to re-render the preview.

### 5. Template metadata and version history
- Add template metadata fields:
  - author
  - version
  - category
  - orientation
  - card size
- Add version history on template save:
  - save snapshots of `template.json`
  - support restore/rollback

### 6. UX consolidation
- Combine editing, settings, preview, and metadata into a single designer screen.
- Keep the list view separate, but reduce page switching for editing.
- Add card thumbnail previews for students and templates.

## Version 4 Deliverables

- `public/student-id-card.php` preview and print page
- Template selection in student workflow
- Default and per-student template handling
- Conditional template engine support
- Template versioning + rollback UI
- Template metadata management
- ID generation output ready for production

## Implementation Plan

1. Connect the template designer to student card generation.
   - Add template selection to student record or card generation UI.
   - Persist selected template ID in configuration or card metadata.

2. Build a final card rendering endpoint.
   - Render the template with student, organization, and theme data.
   - Provide front/back display and printable layout.

3. Harden the engine.
   - Implement conditional blocks.
   - Support missing student/organization fields safely.
   - Validate template markup and provide line-level feedback.

4. Add management features.
   - Template metadata editing.
   - Save and restore historical template versions.
   - Display thumbnails in student and template views.

5. Polish UI.
   - Add a dedicated student template preview panel.
   - Add a unified editor layout.
   - Add breadcrumbs and descriptive help text.

## Version 4 Success Criteria

- Admins can choose a template when previewing or generating a student ID.
- Templates can be exported, imported, duplicated, and set as default.
- A student preview route displays both front and back card designs.
- Templates support optional data fields without breaking output.
- Template history is available for recovery.

---

### Notes

This version is a natural next step after the existing template designer work. It shifts the system from design-only to real-world ID card generation, while preserving the modular storage and import/export foundation already built.
