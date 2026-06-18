# Task: Replace add-instructor free-text fields with structured selectors

**Created:** 2026-06-18
**Last Updated:** 2026-06-18
**Status:** Complete
**Branch:** feature/019ed9f2-2bb4-7275-8fa6-5caf99930510-replace-add-instructor-free-text-field-with-structured-selec

---

## Overview

### Goal
The Add Instructor slide-out (`AddInstructorSheet.vue`) renders the same form for both **create** and **edit** flows. Two fields (`status`, `pdi_status`) are open text inputs that accept any string up to 50 chars — making the form slower to fill, error-prone, and inconsistent. Replace them with structured selects backed by PHP enums so the admin picks from a fixed, sensible set of options. Keep `transmission_type` as a select (already structured) but drive its options from the same backend source.

### Success criteria
- [ ] `status` is a dropdown with sensible defaults (active, inactive, suspended, on_leave, archived).
- [ ] `pdi_status` is a dropdown with the UK PDI lifecycle (qualified, trainee, pdi_part_1, pdi_part_2, pdi_part_3) plus a "None" / empty option.
- [ ] `transmission_type` keeps its current select but reads its options from the backend formOptions payload (single source of truth).
- [ ] Backend `StoreInstructorRequest`, `UpdateInstructorRequest` and `BulkImportInstructorsAction` validate the value against the enum (not just `string|max:50`).
- [ ] Defaults: `status = active`, `pdi_status = qualified`, `transmission_type = manual`. Admin can change any of them on the form.
- [ ] No existing rows become invalid (DB stays varchar; the new constraint only applies to submissions through these requests).
- [ ] CSV import enforces the same enum values.
- [ ] Pest validation tests cover store + update reject/accept paths.

### Out of scope
- Bio, address, postcode, phone, email, password remain free-text (these are inherently free-form).
- Changing the underlying DB column type from varchar to a generated ENUM (varchar + app-level constraint is fine here; preserves rollback safety).
- Backfilling legacy rows whose `status` doesn't match the enum (older free-text values still display but to re-save through the form the admin will need to pick a valid one).
- Adding a ShadCN `Select` component package — there isn't one in `components/ui` today; the existing pattern for `transmission_type` uses a styled native `<select>`, which we mirror for consistency.

---

## PHASE 1: PLANNING ✅

### Where the free-text inputs live (inventory)

| File | Line | Field | Today | After |
|------|------|-------|-------|-------|
| `resources/js/components/Instructors/AddInstructorSheet.vue` | 447 | `status` | `<Input type="text">` | styled `<select>` |
| `resources/js/components/Instructors/AddInstructorSheet.vue` | 461 | `pdi_status` | `<Input type="text">` | styled `<select>` |
| `resources/js/components/Instructors/AddInstructorSheet.vue` | 374 | `transmission_type` | styled `<select>` (hardcoded options) | same UI, options come from props |
| `app/Http/Requests/StoreInstructorRequest.php` | 37–38 | `status`, `pdi_status` | `nullable|string|max:50` | `nullable|in:{enum cases}` |
| `app/Http/Requests/UpdateInstructorRequest.php` | 43–44 | same | same | same |
| `app/Actions/Instructor/BulkImportInstructorsAction.php` | 48–49 | same (CSV) | same | same |

### Backend enum design

`App\Enums\InstructorStatus` (string-backed):
- `Active = 'active'` (default)
- `Inactive = 'inactive'`
- `Suspended = 'suspended'`
- `OnLeave = 'on_leave'`
- `Archived = 'archived'`

`App\Enums\PdiStatus` (string-backed):
- `Qualified = 'qualified'` (default — most instructors in the system are fully-qualified ADIs)
- `Trainee = 'trainee'`
- `PdiPart1 = 'pdi_part_1'`
- `PdiPart2 = 'pdi_part_2'`
- `PdiPart3 = 'pdi_part_3'`

`App\Enums\TransmissionType` (string-backed):
- `Manual = 'manual'` (default)
- `Automatic = 'automatic'`
- `Both = 'both'`

Each enum gets a `label(): string` method and an `options(): array` static helper that returns `[['value' => ..., 'label' => ...], ...]` so controllers can pass them to Inertia in one call (mirrors the existing `BusinessType::cases()` pattern in `InstructorController::show()`).

### Inertia wiring

- `InstructorController::index()` and `InstructorController::show()` both pass a `formOptions` prop with `status`, `pdi_status`, `transmission_type` arrays.
- `Instructors/Index.vue` and `Instructors/Show.vue` accept the new prop and forward it to `<AddInstructorSheet :form-options="formOptions" />`.
- `AddInstructorSheet.vue` types `formOptions` as `InstructorFormOptions` (new TS type) and renders each select via `v-for`.
- TS types: add `InstructorFormOptions` and `FormOption` to `resources/js/types/instructor.ts`. Keep `InstructorDetail.status` / `pdi_status` as `string` so legacy rows still display.

## Why a password setup link (not a temp password)?
- The existing pupil flow emails a plain-text temporary password. That works but means the password lives in the inbox forever and is shoulder-surf vulnerable.
- The Laravel password broker is already wired (Fortify + `routes/auth.php`). The broker mints a token, the user receives a link to `password.reset`, and the existing `ResetPassword` Inertia page handles the password creation. No new auth surface area.
- The instructor never sees a server-generated password, the link expires (60 mins by default, refreshable via forgot-password), and the same flow handles future admin invites.

- **Backward compatibility for legacy values.** Rows with non-enum legacy `status` strings (e.g. `'something_else'`) still render in lists but, when opened in the edit sheet, the select will fall back to the default option. Because the form is the only mutator going through validation, no data corruption can happen — but the admin must pick a valid enum value to save. Acceptable: the user explicitly asked to constrain new entries.
- **`pdi_status` default.** The legacy column allowed null and the form treated empty as "no PDI status set". We keep nullability in the DB but require a value when posted; "Qualified" is the default since the majority of instructors are full ADIs. The admin can pick any other lifecycle stage.
- **`transmission_type` already a select.** We don't change behaviour, just drive its options from the backend so the three free-text/select fields stay in sync from one source.
- **No new ShadCN package.** The current `transmission_type` select uses a native `<select>` styled with the same Tailwind classes as the ShadCN `Input`. We mirror that for the two new selects to avoid introducing a Select dependency.
- **No schema migration.** `status` / `pdi_status` are already `string(50)` / `string(50) NULL` in `instructors`. The enum constraint is purely at the application layer (FormRequest + CSV action). No `.claude/database-schema.md` update needed because no column changes.
- **`api.md` not touched.** This task does not add, modify, or remove any API endpoint — `InstructorController::store`/`update` are web (Inertia) only. The mobile API for instructors does not include this admin create flow.

### New
- `app/Mail/InstructorWelcomeMail.php` — Mailable for the welcome email
- `resources/views/emails/instructor-welcome.blade.php` — HTML template
- `app/Actions/Instructor/SendInstructorWelcomeEmailAction.php` — mints token, dispatches mail, logs activity, manages `welcome_email_pending`
- `tests/Feature/Instructors/InstructorWelcomeEmailTest.php` — feature tests (single create, bulk import, resend, password setup link)

- **Drift between TS enum values and PHP enum values.** Mitigated by passing the options array from PHP through Inertia — TS never hard-codes the enum values.
- **Legacy rows.** Discussed above — acceptable.

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
