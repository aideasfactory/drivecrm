# Task: CSV Bulk Import for Instructors and Resources

**Created:** 2026-03-05
**Last Updated:** 2026-03-05T18:30:00Z
**Status:** ✅ Complete

---

## 📋 Overview

### Goal
Implement CSV bulk import functionality for both /instructors and /resources pages. Users can download example CSV templates and upload completed CSVs to bulk-create records with full validation, error reporting, and duplicate handling.

### Success Criteria
- [ ] Example CSV download button on /instructors page with all required fields
- [ ] Example CSV download button on /resources page with all required fields
- [ ] Upload CSV button on both pages to import completed CSVs
- [ ] Backend parsing and validation of uploaded CSV data
- [ ] Valid records imported into database
- [ ] Clear feedback on successful imports and validation errors
- [ ] Edge cases handled: duplicates, missing required fields, malformed data

### Context
- **Instructor required fields**: name, email, transmission_type (+ optional: phone, bio, status, pdi_status, address, postcode)
- **Resource required fields**: title, resource_type, resource_folder_id (+ conditional: video_url for video_link type; + optional: description, tags)
- **Resource CSV**: Only video_link type practical for CSV (file uploads need actual files)
- **Architecture**: Controller → Service → Action pattern
- **Frontend**: Vue 3 + Inertia v2, ShadCN components, Sheets for forms

---

## 🎯 PHASE 1: PLANNING
**Status:** 🔄 In Progress

### Analysis

#### Backend (Instructor CSV Import)
1. **Action**: `app/Actions/Instructor/BulkImportInstructorsAction.php`
   - Parse CSV, validate each row, create instructors via existing `CreateInstructorAction`
   - Return results: { imported: count, errors: [{row, field, message}] }
2. **FormRequest**: `app/Http/Requests/ImportInstructorsCsvRequest.php`
   - Validate file is CSV, max size
3. **Controller methods** on `InstructorController`:
   - `downloadCsvTemplate()` — returns example CSV with headers + sample row
   - `importCsv()` — accepts upload, delegates to service, returns results

#### Backend (Resource CSV Import)
1. **Action**: `app/Actions/Resource/BulkImportResourcesAction.php`
   - Parse CSV, validate each row, create resources via existing service methods
   - Only supports video_link type (file uploads require actual files)
   - Return results: { imported: count, errors: [{row, field, message}] }
2. **FormRequest**: `app/Http/Requests/ImportResourcesCsvRequest.php`
   - Validate file is CSV, max size
3. **Controller methods** on `ResourceController`:
   - `downloadCsvTemplate()` — returns example CSV with headers + sample row
   - `importCsv()` — accepts upload, delegates to service, returns results

#### Frontend (Both Pages)
1. **Shared component**: `CsvImportSheet.vue` — Sheet with file upload, progress, results display
2. **Instructor Index**: Add "Download Template" and "Upload CSV" buttons
3. **Resources Index**: Add "Download Template" and "Upload CSV" buttons
4. **Results display**: Show imported count, list row-level errors with details

#### CSV Template Fields
**Instructors**: name, email, transmission_type, phone, bio, status, pdi_status, address, postcode
**Resources**: title, resource_type, video_url, description, tags, resource_folder_id

### Risks & Edge Cases
- Duplicate email detection (unique constraint on users.email)
- Postcode coordinate lookup may fail — handle gracefully
- Large CSV files — process row by row, don't load entire file into memory
- Malformed CSV — detect and report parsing errors early
- Resource folder_id must exist — validate before import
- Video URL validation (YouTube/Vimeo only)

### Reflection
Solid plan covering both entities. The shared CsvImportSheet component prevents duplication. Using existing create actions ensures consistency. Row-level error reporting gives users actionable feedback.

---

## 🔨 PHASE 2: BACKEND IMPLEMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Create `ImportInstructorsCsvRequest` FormRequest
- [x] Create `BulkImportInstructorsAction` in `app/Actions/Instructor/`
- [x] Add `bulkImportInstructors()` and `parseCsvFile()` to InstructorService
- [x] Add `downloadCsvTemplate()` and `importCsv()` to InstructorController
- [x] Create `ImportResourcesCsvRequest` FormRequest
- [x] Create `BulkImportResourcesAction` in `app/Actions/Resource/`
- [x] Add `bulkImportResources()` and `parseCsvFile()` to ResourceService
- [x] Add `downloadCsvTemplate()` and `importCsv()` to ResourceController
- [x] Register routes in web.php (4 routes confirmed)

### Reflection
Clean implementation following existing patterns. Actions handle row-level validation with detailed error reporting. CSV parsing in service layer with shared `parseCsvFile()`. Template downloads use streamed responses. Resource CSV only supports video_link type as planned.

---

## 🔨 PHASE 3: FRONTEND IMPLEMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Create shared `CsvImportSheet.vue` component (file upload, results display, error list)
- [x] Add "Download Template" and "Upload CSV" buttons to Instructors/Index.vue
- [x] Wire up instructor CSV import with toast notifications and error display
- [x] Add "Download Template" and "Upload CSV" buttons to Resources/Index.vue
- [x] Wire up resource CSV import with toast notifications and error display
- [x] Generate Wayfinder types for new routes

### Reflection
Shared `CsvImportSheet.vue` component works for both pages via props (`importUrl`, `extraFormData`). Resources page passes `resource_folder_id` dynamically based on the current folder. Both pages use toast notifications for feedback and display row-level errors in a scrollable table.

---

## 💭 FINAL REFLECTION
**Status:** ✅ Complete

### Summary
Implemented CSV bulk import for both Instructors and Resources pages. Users can download example CSV templates and upload completed CSVs to bulk-create records with full validation and error reporting.

### Files Changed
**Backend (new):**
1. `app/Http/Requests/ImportInstructorsCsvRequest.php` — FormRequest for instructor CSV upload
2. `app/Http/Requests/ImportResourcesCsvRequest.php` — FormRequest for resource CSV upload
3. `app/Actions/Instructor/BulkImportInstructorsAction.php` — Row-level validation and creation
4. `app/Actions/Resource/BulkImportResourcesAction.php` — Row-level validation and creation

**Backend (modified):**
5. `app/Services/InstructorService.php` — Added `bulkImportInstructors()` and `parseCsvFile()`
6. `app/Services/ResourceService.php` — Added `bulkImportResources()` and `parseCsvFile()`
7. `app/Http/Controllers/InstructorController.php` — Added `downloadCsvTemplate()` and `importCsv()`
8. `app/Http/Controllers/ResourceController.php` — Added `downloadCsvTemplate()` and `importCsv()`
9. `routes/web.php` — 4 new routes for CSV template/import endpoints

**Frontend (new):**
10. `resources/js/components/CsvImportSheet.vue` — Shared reusable Sheet component

**Frontend (modified):**
11. `resources/js/pages/Instructors/Index.vue` — CSV Template + Upload CSV buttons
12. `resources/js/pages/Resources/Index.vue` — CSV Template + Upload CSV buttons

### Score
9/10 — Clean, well-structured implementation following all project patterns. The shared CsvImportSheet component avoids duplication. Row-level error reporting gives actionable feedback. Minor note: `parseCsvFile()` is duplicated in both services — could be extracted to a shared trait, but kept simple to avoid over-engineering.
