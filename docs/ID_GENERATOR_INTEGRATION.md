# NDC Student ID Generator Integration Specification

## 1. Project Overview

The existing NDC Billing System is primarily a finance and student billing application. It includes student management, invoice generation, and a separate Student ID card feature.

The current system already provides:
- a `students` table containing student personal and academic data
- a `student_id_cards` table that stores generated ID card metadata
- a student ID number generation service
- QR code generation for student ID cards
- PDF rendering for ID cards using Dompdf
- student photo upload and storage
- organization logo and principal signature settings
- routes for generating, previewing, downloading, and verifying ID cards

This document describes the existing internals required to build a separate Student ID Generator application that can integrate with the current system.

---

## 2. Student Database Structure

### `students`
- **Purpose**: primary student record storage, including personal details, ID number, academic information, photo path, and status.
- **Primary key**: `id`
- **Foreign keys**: none directly in this table
- **Important fields**:
  - `student_number` - unique student identifier used for ID cards
  - `photo_path` - relative path to stored student photo
  - `first_name`, `last_name`
  - `gender`
  - `date_of_birth`
  - `qualification`, `program`, `class_level`
  - `status` - `Active` or `Inactive`
- **Relationships**:
  - referenced by `student_id_cards.student_id`
  - referenced by `invoices.student_id`

### `student_id_cards`
- **Purpose**: stores metadata for generated student ID cards, including issue/expiry dates and QR code path.
- **Primary key**: `id`
- **Foreign keys**:
  - `student_id` references `students.id`
- **Important fields**:
  - `student_id` - link to `students`
  - `issue_date`
  - `expiry_date`
  - `qr_code_path` - relative path to stored QR code image
  - `created_by` - user who generated the card
- **Relationships**:
  - one-to-one with `students` via `student_id`

### `settings`
- **Purpose**: generic settings store used for organization branding, ID card configuration, and other system settings.
- **Primary key**: `id`
- **Important fields**:
  - `setting_key`
  - `setting_value`
- **Relationships**:
  - none explicit, but used by application code for display and card generation.

---

## 3. Student Data Required

The following fields are required or used when generating an ID card.

| Field | Table | Column | Data Type | Nullable | Notes |
|---|---|---|---|---|---|
| Student ID | `students` | `student_number` | VARCHAR(50) | NO | Unique student identifier; used on card and verification URL.
| First Name | `students` | `first_name` | VARCHAR(100) | NO | Required for ID generation and display.
| Last Name | `students` | `last_name` | VARCHAR(100) | NO | Required for ID generation and display.
| Gender | `students` | `gender` | VARCHAR(20) | YES | Optional on ID card.
| Date of Birth | `students` | `date_of_birth` | DATE | YES | Used as birthday field on the card if present.
| Program | `students` | `program` | VARCHAR(150) | YES | Appears on generated card.
| Qualification / Department | `students` | `qualification` | VARCHAR(100) | YES | Shown as department or qualification.
| Class Level | `students` | `class_level` | VARCHAR(80) | YES | Optional card section.
| Status | `students` | `status` | ENUM('Active','Inactive') | NO | Used to determine active students and may display card status.
| Photo | `students` | `photo_path` | VARCHAR(255) | YES | Path to uploaded student photo.
| Issue Date | `student_id_cards` | `issue_date` | DATE | NO | Card issuance date.
| Expiry Date | `student_id_cards` | `expiry_date` | DATE | NO | Card expiration date.
| QR Code | `student_id_cards` | `qr_code_path` | VARCHAR(255) | YES | Stored QR code image file path.
| Card Exists | `student_id_cards` | `student_id` | INT | NO | Indicates ID card has been generated for the student.
| Created By | `student_id_cards` | `created_by` | INT | NO | User who generated the card.

Notes:
- `student_id_cards` records are created or updated when an ID card is generated.
- The QR code path is stored as relative storage under `public/uploads/student_id_qr`.

---

## 4. Existing Student ID Logic

### Algorithm
The existing student ID generation algorithm is implemented in `app/Services/StudentIdService.php`.

- Build a prefix from student names:
  - first 3 letters of the last name
  - first 2 letters of the first name
  - prefix is prefixed with `S`
  - non-letter characters are stripped before constructing the prefix
  - if the resulting prefix would be `S`, it falls back to `STU`
- Determine the next sequence number for the prefix by scanning existing `students.student_number` values.
- Append a zero-padded 2-digit sequence to the prefix.

### Uniqueness
- The generated `student_number` is ensured unique by checking existing values in `students.student_number`.
- Sequence calculation uses `Student::getHighestSequenceForPrefix()` to scan all existing student numbers matching the prefix and increment the highest numeric suffix.

### Format
- Example formats: `SABCXY01`, `S123AB02` depending on name content.
- Format is `S` + 3 letters from last name + 2 letters from first name + 2-digit sequence.
- If name parts are missing, the algorithm falls back to use the available name.

---

## 5. Existing QR Code Implementation

### Generation
- QR codes are created in `StudentIdController::generateQrCode()`.
- The external API `https://api.qrserver.com/v1/create-qr-code/` is used to generate PNG images.
- The payload is JSON-encoded and used as the `data` query parameter.

### Stored
- Generated QR images are stored under `public/uploads/student_id_qr`.
- File name format is `student_{student_number}.png`.

### Displayed
- When rendering the PDF card in `cardHtml()`, the QR code is loaded via `data_uri()` from the stored path.
- The student ID preview uses the same stored `qr_code_path` in the `student_id_cards` record.

### Verification URL
- Verification is done through the public route `verify.student_id`.
- The URL is built in `StudentIdController::verificationUrl()`:
  - `base_url/index.php?route=verify.student_id&student={student_number}`
- The public verification view `app/Views/student_ids/verify_public.php` checks the student number and displays card data.

### Libraries Used
- No dedicated QR library in the project.
- QR generation is handled via remote QRServer API.
- Existing PDF QR display uses standard HTML `<img src="...">` with a stored PNG or data URI.

---

## 6. Existing Image Handling

### Student Photo Upload
- Upload endpoint: `POST student_ids.uploadPhoto`
- Controller: `StudentIdController::uploadPhoto()`
- Accepted MIME types: `image/png`, `image/jpeg`, `image/jpg`, `image/webp`
- Destination folder: `public/uploads/student_photos`
- Naming convention: `student_{student_id}_{timestamp}.{ext}`
- Stored path on student record: `uploads/student_photos/{filename}`

### Photo Cropping
- No cropping logic found in current project.
- The photo is stored as-is after upload.

### Signature Upload
- Organization signature: `organization_signature_path` setting
- Principal signature: `principal_signature_path` setting
- Uploaded via `SettingsController` in the main application settings.
- Signature upload folder: `public/uploads/signatures`
- Naming convention: `signature-{YmdHis}-{random}.ext`
- Accepted formats: PNG, JPG, JPEG, WebP.

---

## 7. Existing PDF Generation

### Library
- `dompdf/dompdf` is used for PDF rendering.
- Version requirement from `composer.json`: `^3.1`.

### Service / Flow
- ID card PDF generation for the student ID card uses `StudentIdController::download()`.
- The controller constructs `Dompdf` with `Options` and `isRemoteEnabled` set to true.
- It loads HTML from `cardHtml()` and renders the PDF.
- The PDF is streamed for download with a generated filename.

### Templates
- Student ID card HTML is generated directly in `StudentIdController::cardHtml()`.
- `StudentController::studentCardHtml()` also generates another student card PDF style for student cards with QR content.
- The card markup and inline CSS are entirely generated in PHP string templates.

---

## 8. Existing Authentication

### Login Requirements
- Login is enforced by the application using session-based auth.
- Roles are stored in `users.role` and include at least `Administrator` and `Finance Officer`.

### Permissions
- Permission key for ID card management: `student_ids.manage`
- This permission is used by `StudentIdController::requireStudentIdAccess()`.
- The layout only shows Student ID navigation to users with the permission.

### Roles Required to Generate IDs
- Built-in roles seeded by migrations include `Administrator` and `Finance Officer`.
- Both roles are granted `student_ids.manage` by default.

---

## 9. Existing Services

| Service | Description |
|---|---|
| `StudentIdService` | Generates a unique student ID string from first and last name.
| `Student` model | Manages student records, photo path updates, and student ID uniqueness checks.
| `StudentIdCard` model | Reads and upserts card metadata, including issue/expiry dates and QR code path.
| `Setting` model | Generic settings access and upsert for ID card and branding values.
| `Dompdf\Dompdf` | PDF rendering library used for card PDF output.

Potential reuse or integration points:
- Student identity and metadata retrieval from `students`
- `student_id_cards` card metadata read/write
- settings for logo and signature
- QR path storage and display logic

---

## 10. Existing Routes

Relevant student and ID routes are defined in `public/index.php`.

### GET routes
- `student_ids.index` - list/manage Student ID cards
- `student_ids.create` - show generate/update ID card form
- `student_ids.preview` - preview a generated ID card
- `student_ids.download` - download ID card PDF
- `student_ids.verify` - internal verify route to require auth? (same as public verify if GET)
- `verify.student_id` - public verification endpoint by student number

### POST routes
- `student_ids.save` - save ID card display settings
- `student_ids.store` - generate or update student ID card metadata
- `student_ids.uploadPhoto` - upload student photo
- `student_ids.verify` - duplicate route for verification handling

---

## 11. Existing Models

| Model | Purpose |
|---|---|
| `App\Models\Student` | Primary student data access; photo path update, student ID uniqueness, and student lookup.
| `App\Models\StudentIdCard` | Card metadata access and persistence.
| `App\Models\Setting` | Application settings persistence.
| `App\Models\Permission` | Defines permission keys and role mappings including `student_ids.manage`.

---

## 12. Existing Settings

Settings relevant to ID generation and display include:

| Setting Key | Purpose |
|---|---|
| `organization_name` | Shown on ID card header/back and public verification page.
| `organization_signature_path` | Organization signature path used in invoice and card displays.
| `principal_signature_path` | Principal signature used on ID card back.
| `principal_signature_name` | Label displayed under signature.
| `organization_address` | Used as return address on ID card back.
| `organization_email` | Contact email shown on ID back.
| `organization_phone` | Contact phone shown on ID back.
| `organization_website` | Displayed on ID back if set.
| `student_id_card_show_logo` | Toggles logo on ID card.
| `student_id_card_show_photo` | Toggles student photo on ID card.
| `student_id_card_show_qr` | Toggles QR code on ID card.
| `student_id_card_show_student_number` | Toggles student number display.
| `student_id_card_show_student_name` | Toggles student name display.
| `student_id_card_show_program` | Toggles program display.
| `student_id_card_show_qualification` | Toggles qualification display.
| `student_id_card_show_class` | Toggles class level display.
| `student_id_card_show_status` | Toggles status display.
| `student_id_card_qr_label` | QR label text shown with the QR code.
| `student_id_card_qr_prefix` | Prefix added to QR payload for custom QR payload values.

---

## 13. Existing Storage Structure

### Upload folders
- `public/uploads/student_photos` - student photo uploads
- `public/uploads/student_id_qr` - generated QR code PNG files
- `public/uploads/signatures` - uploaded signatures

### Stored paths
- Student photos stored as `uploads/student_photos/student_{student_id}_{timestamp}.{ext}`
- QR codes stored as `uploads/student_id_qr/student_{student_number}.png`
- Signatures stored with `signature-{timestamp}-{random}.{ext}`

### Generated PDF
- PDFs are generated on demand via Dompdf and streamed; no permanent storage path is used by current code.

### Temporary files
- No explicit temporary file storage is used for PDF generation other than Dompdf internals.

---

## 14. Dependencies

Composer packages that can be reused by the new ID Generator project:

- `dompdf/dompdf` - PDF rendering
- `phpoffice/phpspreadsheet` - spreadsheet import/export (not directly required for ID generator but available)

Other relevant built-in or app utilities:
- PSR-4 autoloading of `App\` namespace
- custom helper functions in `app/Helpers/functions.php`

---

## 15. Integration Recommendations

### Shared MySQL database
- **Advantages**:
  - direct access to `students`, `student_id_cards`, and `settings`
  - no need to duplicate student data
  - consistent source of truth for student numbers and card metadata
- **Disadvantages**:
  - tighter coupling to the existing schema
  - more risk during schema changes
  - requires shared database credentials and security controls

### REST API
- **Advantages**:
  - decoupled integration layer
  - can enforce access control and auditing more cleanly
  - existing app can remain authoritative while new generator is separate
- **Disadvantages**:
  - no REST API currently exists; it would need to be built separately
  - added development overhead for a new API surface

### Shared authentication
- **Advantages**:
  - reuse existing user roles and permissions
  - keep authorization centralized
- **Disadvantages**:
  - can complicate a separate application if it uses different authentication mechanisms
  - may require session sharing or token-based SSO

### Shared storage
- **Advantages**:
  - reuse existing `public/uploads` folders for photos and QR codes
  - avoid duplication of image assets
- **Disadvantages**:
  - operational coupling between systems
  - permissions and file ownership must be managed carefully

Recommendation:
- For a separate Student ID Generator, the cleanest integration is via shared database access to `students`, `student_id_cards`, and `settings`, while keeping the new generator independent.
- A separate API layer would be ideal if the project must remain decoupled, but the current system does not yet expose such an API.
- Shared storage can be used for photo and QR code assets if both apps deploy to the same host or network filesystem.

---

## 16. Risks

Potential synchronization problems:
- `student_number` generation depends on scanning existing students, so concurrent ID generation can cause duplicate suffixes unless serialized.
- `students` and `student_id_cards` schema changes in the main app would break the integration if the new generator assumes the current schema.
- Shared file storage for photos / QR codes can diverge if both systems write to the same paths without coordination.
- Settings are stored as key/value pairs; a missing or renamed setting key could break rendering or QR behavior.
- Current verification endpoint is GET-based and exposes student numbers in query string.

---

## 17. Migration Checklist

- [x] Student table (`students`)
- [x] Student photo storage (`public/uploads/student_photos`)
- [x] Student ID card metadata (`student_id_cards`)
- [x] QR verification endpoint (`verify.student_id`)
- [x] Organization settings (`settings` table)
- [x] Student ID generation algorithm (`StudentIdService`)
- [x] PDF rendering library (`dompdf/dompdf`)
- [x] ID card display settings (`student_id_card_*` keys)
- [x] Student ID card issue/expiry support
- [x] Signature storage (`public/uploads/signatures`)
- [x] Permission key `student_ids.manage`

---

## Appendix: Key Existing Files

- `app/Controllers/StudentIdController.php`
- `app/Controllers/StudentController.php`
- `app/Services/StudentIdService.php`
- `app/Models/Student.php`
- `app/Models/StudentIdCard.php`
- `app/Models/Setting.php`
- `app/Helpers/functions.php`
- `public/index.php`
- `database/schema.sql`
- `composer.json`

---

### Notes
- If a required detail does not exist in the current project, it is marked as `Not found in current project`.
- This document is intended as a specification for a separate Student ID Generator application integrating with the current system.
