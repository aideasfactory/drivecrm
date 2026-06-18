# Add Instructor — Structured Selectors

## What changed

The "Add Instructor" slide-out (also used for "Edit Instructor") had two fields that accepted any free-form text — **Status** and **PDI Status**. Both have been replaced with structured dropdowns backed by a fixed list of sensible options, so the admin can pick a value in one click instead of remembering the exact wording.

We also tidied up the surrounding form while we were there.

### Before

| Field | Type | Problem |
|---|---|---|
| Status | Free-text input | Admin had to type "active" / "inactive" / etc. Any typo was accepted (e.g. "Actve") |
| PDI Status | Free-text input | Free-form placeholder of "e.g., qualified, trainee" — easy to drift |
| Transmission Type | Dropdown | Hard-coded in the page — not coming from a single source |

### After

| Field | Type | Options |
|---|---|---|
| Status | **Dropdown** | Active *(default)*, Inactive, Suspended, On Leave, Archived |
| PDI Status | **Dropdown** | Qualified ADI *(default)*, Trainee, PDI Part 1 (Theory), PDI Part 2 (Driving), PDI Part 3 (Instructional) |
| Transmission Type | Dropdown (unchanged UX) | Manual *(default)*, Automatic, Both — now driven from the same source as the other two |

All three dropdowns share **one source of truth** in the backend, so the admin UI, the backend validation, and the CSV bulk-import all enforce the exact same list. There is no way for them to drift apart.

## What stayed free-text (and why)

These fields are *intentionally* still open text because the values are inherently free-form:

- **Full Name, Email, Phone, Password** — naturally unique per person.
- **Bio** — a paragraph of biography text.
- **Address** — a street address.
- **Postcode** — a UK postcode, validated by format if/when needed.

We didn't add unnecessary constraints to fields where a fixed list would harm usability.

## Defaults

When the admin opens "Add Instructor", the form pre-fills with the most common choices:

- **Status:** Active
- **PDI Status:** Qualified ADI
- **Transmission Type:** Manual

These can all be changed before saving — they're suggestions, not locks.

## Coverage

- **Create flow** — the slide-out used from the Instructors list page.
- **Edit flow** — the same slide-out reused from a specific instructor's page.
- **CSV bulk import** — uploaded files are now validated against the same enumerated values, so admins can't import an instructor with a Status of `"actve"` even by spreadsheet.

## Edge cases handled

- **Existing instructors with a legacy free-text Status** (e.g. a `status` value that pre-dates the dropdown): the form quietly snaps to the default option instead of breaking. The admin sees the default selected and can pick a valid value.
- **Mobile API not affected:** no API endpoints were created, changed, or removed. The mobile app does not include admin instructor creation, so it's unaffected.
- **No database migration needed:** the columns are already strings; we constrained the *allowed values* at the application layer, which keeps existing data intact and the rollback path trivial.

## Tests

A new Pest feature test file — `tests/Feature/Instructors/InstructorStructuredFieldsTest.php` — covers:

- Valid status / PDI status accepted on create
- Invalid status / PDI status rejected on create
- Status and PDI status remain optional on create
- Valid status / PDI status accepted on update
- Invalid status / PDI status rejected on update

Existing transmission type tests continue to pass (the enum just moves into a typed class — the values are unchanged).

## Files changed

**New**
- `app/Enums/InstructorStatus.php`
- `app/Enums/PdiStatus.php`
- `app/Enums/TransmissionType.php`
- `tests/Feature/Instructors/InstructorStructuredFieldsTest.php`

**Modified**
- `app/Http/Requests/StoreInstructorRequest.php` — validates against enums
- `app/Http/Requests/UpdateInstructorRequest.php` — validates against enums
- `app/Actions/Instructor/BulkImportInstructorsAction.php` — CSV import validates against enums
- `app/Http/Controllers/InstructorController.php` — passes `formOptions` to the Index and Show Inertia pages
- `resources/js/types/instructor.ts` — added `FormOption` and `InstructorFormOptions` types
- `resources/js/pages/Instructors/Index.vue` — accepts + forwards `formOptions` to the sheet
- `resources/js/pages/Instructors/Show.vue` — same
- `resources/js/components/Instructors/AddInstructorSheet.vue` — three dropdowns driven by `formOptions`

---

## Confidence score: **9 / 10**

Why 9 and not 10:
- A legacy instructor whose `status` is a free-text value that doesn't match the new enum (rare, but possible if the system has historical drift) will see the dropdown default to "Active" the first time it's opened. If the admin saves without changing it, the legacy value is overwritten. This is deliberate behaviour (the whole point of the brief is to constrain values), but it's worth flagging.

Everything else is solid:
- One backend source feeds three places (form, validation, CSV import).
- No schema migration, no risk to existing data structure.
- Pattern follows the existing `BusinessType` / `businessTypes` precedent already used by the HMRC tab — same idiom, same shape.
- Pest tests cover both create and update paths.
