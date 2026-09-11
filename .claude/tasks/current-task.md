# Task: Instructor CSV import coverage column

**Created:** 2026-09-11
**Last Updated:** 2026-09-11
**Status:** Complete

---

## Overview

Admin Import Instructors from CSV should accept coverage in the same
upload as instructor details, so ~500 instructors can be created with
their postcode-sector coverage in one go.

Ticket: 01a08b29-5f2c-7142-8fb4-ff0e5932c1f0 (urgent bug).
Web admin only — do not change the mobile app. HARD RULE: no tests.

### Success Criteria
- [x] Downloadable instructor CSV template includes a coverage column
- [x] Importer parses coverage and writes `locations.postcode_sector`
- [x] Coverage format matches existing admin coverage (outcode sectors)
- [x] No mobile app / `/api/v1` changes
- [x] No tests added

---

## PHASE 1: PLANNING

**Status:** ✅ Complete

### Current state
- Bulk instructor CSV: `name, email, transmission_type, phone, bio, status, pdi_status, address, postcode`
- Coverage lives on `locations` (`instructor_id` + `postcode_sector`)
- Per-instructor coverage CSV uses one `postcode_sector` per row (TS7, WR14, M1)
- Sector regex: `/^[A-Z]{1,2}[0-9]{1,2}$/` (same as StoreLocationRequest)

### Approach
1. Add optional `coverage` column to the template: comma-separated sectors
2. Parse aliases (`coverage`, `coverage_areas`, `postcode_sectors`, `postcode_sector`)
3. After creating each instructor, insert valid sectors via
   `ReplaceInstructorLocationsAction`. Invalid sectors are warnings;
   the instructor still imports.
4. Update the import drawer copy. No migration. No mobile API.

### Tasks
- [x] Trace coverage storage and existing CSV importers
- [x] Choose one coverage column (comma-separated postcode sectors)

### Reflection
Reusing `ReplaceInstructorLocationsAction` keeps sector validation
identical to the per-instructor coverage CSV. One column (not one
sector per row) is required because the bulk file is one instructor
per row.

---

## PHASE 2: IMPLEMENTATION

**Status:** ✅ Complete

### Currently working on
Complete.

### Tasks
- [x] Add `coverage` to downloadable instructor CSV template
- [x] Parse coverage in `BulkImportInstructorsAction` and write locations
- [x] Update Import Instructors drawer copy
- [x] Update database-schema.md (no migration; document CSV coverage)
- [x] No api.md change (web admin CSV, not a mobile API)
- [x] No tests (HARD RULE)

### Reflection
Coverage is optional. Semicolon and pipe separators are accepted as
well as commas. Valid sectors still save when some tokens are invalid.

I've updated database-schema.md to reflect the bulk CSV coverage
behaviour. No migration.

---

## PHASE 3: REFLECTION

**Status:** ✅ Complete

### Tasks
- [x] Document decisions

### Reflection
Staff download the template, fill details plus a `coverage` cell of
sectors (e.g. `TS7, TS8, NE12`), and upload once. Per-instructor
coverage CSV on Details → Coverage is unchanged (one sector per row,
replace-all).

No mobile app change. No tests added, per HARD RULE. I understand I
must not run tests or linting commands.

**Last Updated:** 2026-09-11.
