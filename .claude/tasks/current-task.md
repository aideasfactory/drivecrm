# Task: Fix instructor self-add sign-up flow

## Overview
Sam reported that when he tries to add himself as an instructor during sign-up, pressing "OK" (the Create Instructor button) does nothing — the instructor is not added and there is no error message. This task fixes the silent failure and adds clear UI feedback.

Branch: `feature/019ed9ed-4b56-726e-b611-8d5837366a60-fix-instructor-self-add-sign-up-flow`

---

## Investigation summary

Two distinct failure paths reproduce "nothing happens":

1. **Validation error displayed inline, no toast** — `StoreInstructorRequest` includes `email.unique:users,email`. If Sam types his own email (which already exists in `users`) he gets a 422 with `errors.email = "This email address is already in use."`. The sheet displays the error inline beneath the email input but shows **no toast**, so a user looking at the submit button thinks nothing happened.

2. **Silent backend failure on postcode lookup** — `'postcode'` is `nullable` in `StoreInstructorRequest`. If postcode is missing or fails the `postcodes.io` lookup, `InstructorService::createInstructor()` swallows the failure and returns `['success' => false, 'error' => '...']`. **`InstructorController::store()` discards that return value** and unconditionally `redirect()->route('instructors.index')`. From Inertia's perspective this is a successful POST — `onSuccess` fires, the sheet closes, and the user is left wondering why nothing happened.

There is **no max-instructor limit**. There is no business rule limiting the number of instructors. The only effective limits are:
- `users.email` unique constraint
- `instructors.user_id` unique constraint

---

## Phase 1: Planning ✅

- [x] Trace the "Add Instructor" sheet → `/instructors` POST → `InstructorController::store` → `InstructorService::createInstructor`
- [x] Confirm there is no max-instructor cap anywhere in the codebase
- [x] Identify the silent-failure root cause: controller ignores service's `success/false` array
- [x] Identify the secondary issue: postcode is nullable in validation but required by the service

### Reflection
- The legacy "return an array with `success` boolean" pattern in `createInstructor` is the structural cause. The controller never checks it, so any internal failure is invisible.
- The cleanest fix is to refactor `createInstructor` to throw `ValidationException` for known recoverable issues (postcode) and let the controller's validation pipeline surface the error to the form.

## Phase 2: Implementation ✅

- [x] Make `postcode` required in `StoreInstructorRequest` (the service needs it)
- [x] Refactor `InstructorService::createInstructor()` to throw `ValidationException` on postcode lookup failure and return an `Instructor` directly
- [x] Update `InstructorController::store()` to use the new return type and let validation exceptions surface to Inertia
- [x] Update `AddInstructorSheet.vue` to show success and error toasts (no more silent UI)
- [x] Show a general error toast when the server returns errors without specific field messages
- [x] Add a Pest feature test that proves the failure modes now surface as 422 errors with messages

### Reflection
- The fix is small (controller + service + form request + frontend toast) and targets the exact source of "nothing happens" rather than papering over symptoms.
- We deliberately did NOT add a max-instructor limit because none exists in the product; if one is needed later it would belong as a Policy/action gate, not a silent guard.

### Inertia wiring

### Reflection
- Confidence is high: the controller now relies on Laravel's exception → 422 pipeline that the Inertia frontend already understands, and we added an explicit toast so generic errors can't go unnoticed.

---

## PHASE 2: IMPLEMENTATION ✅

### Steps
- [x] Create `app/Enums/InstructorStatus.php`
- [x] Create `app/Enums/PdiStatus.php`
- [x] Create `app/Enums/TransmissionType.php`
- [x] Update `StoreInstructorRequest::rules()` to validate `status` + `pdi_status` + `transmission_type` against enums
- [x] Update `UpdateInstructorRequest::rules()` likewise
- [x] Update `BulkImportInstructorsAction::__invoke()` row validator likewise
- [x] Update `InstructorController::index()` and `::show()` to attach `formOptions` Inertia prop (via a shared `private function instructorFormOptions(): array`)
- [x] Add `InstructorFormOptions` + `FormOption` types in `resources/js/types/instructor.ts`
- [x] Update `resources/js/pages/Instructors/Index.vue` to accept + forward `formOptions`
- [x] Update `resources/js/pages/Instructors/Show.vue` to accept + forward `formOptions`
- [x] Update `resources/js/components/Instructors/AddInstructorSheet.vue`:
  - Accept `formOptions` prop
  - Replace `status` `<Input>` with styled `<select>` driven by `formOptions.status`
  - Replace `pdi_status` `<Input>` with styled `<select>` driven by `formOptions.pdi_status`
  - Drive `transmission_type` options from `formOptions.transmission_type`
  - Add `snapToOption()` helper so legacy values are mapped to the default rather than silently selecting an out-of-list option
- [x] Write Pest feature test `tests/Feature/Instructors/InstructorStructuredFieldsTest.php` covering accept + reject for `status` and `pdi_status` on both store and update

### Files to change

| File | Change |
|------|--------|
| `app/Enums/InstructorStatus.php` | NEW |
| `app/Enums/PdiStatus.php` | NEW |
| `app/Enums/TransmissionType.php` | NEW |
| `app/Http/Requests/StoreInstructorRequest.php` | Use enum rules |
| `app/Http/Requests/UpdateInstructorRequest.php` | Use enum rules |
| `app/Actions/Instructor/BulkImportInstructorsAction.php` | Use enum rules in CSV row validator |
| `app/Http/Controllers/InstructorController.php` | Attach `formOptions` to `index` + `show` Inertia payloads |
| `resources/js/types/instructor.ts` | Add `FormOption` + `InstructorFormOptions` types |
| `resources/js/pages/Instructors/Index.vue` | Accept + forward `formOptions` |
| `resources/js/pages/Instructors/Show.vue` | Accept + forward `formOptions` |
| `resources/js/components/Instructors/AddInstructorSheet.vue` | Three structured selects |
| `tests/Feature/Instructors/AddInstructorValidationTest.php` | NEW Pest tests |

---

## PHASE 3: REFLECTION ✅

### What went well

- **Single source of truth (PHP enums → Inertia → Vue).** All three fields' option lists live in `App\Enums\*::options()`, are exposed by `InstructorController::instructorFormOptions()` to both Inertia pages, and the Vue sheet only renders what the backend gives it. There is no TS-side hard-coded list of values to drift.
- **Mirrored the existing `BusinessType` pattern.** The HMRC tab already shipped `businessTypes`/`methodOptions` to the frontend in the exact `[{value, label}]` shape, so the new `formOptions` payload is idiomatic and the frontend already knows how to consume that shape.
- **`transmission_type` came along for free.** It was already a select, but its options were hard-coded in the Vue template. Driving them from the same `formOptions` payload removed the last divergence point between the backend enum and the UI.
- **Backwards-compatible for legacy data.** `snapToOption()` in the sheet maps any pre-existing free-text value that isn't in the enum to the default option, so opening an old instructor with `status = 'something_else'` doesn't crash the select — the admin sees the default and can pick something valid.
- **CSV import stays consistent.** `BulkImportInstructorsAction` shares the same enum rules, so the constraint we introduced through the form can't be bypassed by uploading a CSV.

### Anti-pattern check

- No `pint`, no `php artisan test`, no lint runs (project rules).
- No migration / schema change → no `.claude/database-schema.md` update needed. Columns stay `varchar(50)`; the constraint is purely at the FormRequest layer where Laravel already enforces validation.
- No `api.md` update — no API endpoint added, modified or removed. Both `instructors.store` and `instructors.update` are web (Inertia) routes; the mobile API does not include admin instructor creation.

### Technical debt / future considerations

- **Legacy rows.** Any existing instructor whose `status` doesn't match the new enum still displays in lists but, on edit, the select falls back to the default ("Active"). The admin can keep saving without breaking, but the legacy value is lost on the first save. Acceptable given the brief, but worth a one-off backfill if there's drift in the DB.
- **No ShadCN `Select` component yet.** I followed the existing `transmission_type` pattern (native `<select>` with the same Tailwind classes as `Input`). When the project eventually adds a `components/ui/select` (reka-ui), the three selects can be migrated together — they all share the `selectClass` constant.
- **TypeScript still types `status`/`pdi_status` as `string` on `InstructorDetail`** rather than the enum literal union. That's deliberate so legacy values render without TS errors. If the DB is backfilled, tightening these types is a one-line change.

### Score

**9 / 10.** Loses one point for the legacy-row edge case (an old non-enum status is silently rewritten to "Active" on first edit) — a deliberate trade-off, not a bug, but worth flagging. Everything else is in lockstep: PHP enum is the single source, three consumers (web Store, web Update, CSV import) validate against the same list, the frontend renders only what the backend offers, and the existing `BusinessType`/`businessTypes` pattern is followed exactly.
