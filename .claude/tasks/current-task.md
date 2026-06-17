# Task: Instructor Package Pricing UI in Pounds

## Overview

Display the Total Price for instructor packages in **pounds** (£) instead of
pence in the admin area, while continuing to **store** the price as pence in
the database. This affects the package create/edit form input shared by:

- Admin packages list (`/packages` create + edit sheets)
- Instructor bespoke packages (`/instructors/{id}` Details tab → Packages sheet)

The Index table column already shows the formatted pound value via the model
accessor `formatted_total_price`. The work is on the **form input**: today it
asks for pence (e.g., `50000` for £500.00) which is unfriendly for admins.

## Phase 1: Planning ✅

### What changes
- **Frontend only.** Backend schema (`total_price_pence` integer), FormRequest
  rules, Resources, Service, and Action stay untouched.
- `PackageForm.vue` gains an internal pounds-denominated input bound to a local
  `total_price_pounds` field. On submit it multiplies by 100 and rounds to
  produce the integer `total_price_pence` the backend already expects. On
  load (edit mode) it divides the existing pence value by 100 to populate the
  input.
- Label changes from "Total Price (in pence)" to "Total Price (£)".
- The helper line under the input keeps showing "£X.XX total (£Y.YY per
  lesson)" which is already in pounds — its computed source switches from
  `total_price_pence` to the new pounds field.

### Why this shape
- The backend is the source of truth and the database column is named
  `total_price_pence` for a reason: integer arithmetic for money is correct.
  Touching the backend just to rename a field would ripple into Stripe code,
  resources, API consumers, and migrations for no benefit.
- The Inertia DTO emitted by the form keeps the `total_price_pence` key so the
  HTTP layer is unchanged — only the input the human sees is in pounds.
- Rounding on submit (`Math.round(pounds * 100)`) guards against
  floating-point drift if the admin types `12.345`.

### Files to edit
1. `resources/js/components/Instructors/PackageForm.vue` — only file requiring
   real changes. Switch input binding to a pounds field, convert in/out, relabel.

### Files NOT to edit (and why)
- `Package.php` model — accessors already format pence → "£X.XX" for read.
- `PackageController.php`, `PackageService.php`, related Actions — they
  receive `total_price_pence` from the form payload unchanged.
- `StorePackageRequest.php`, `UpdatePackageRequest.php`,
  `StoreInstructorPackageRequest.php` — validation rules still apply to the
  same pence integer the form emits.
- `Packages/Index.vue` — already shows `formatted_total_price` (pounds).
- `migrations/*` and `database-schema.md` — column unchanged.
- `api.md` — endpoints unchanged (still take `total_price_pence`).

### Risks / edge cases
- Existing packages have integer `total_price_pence` (e.g., 50000). Dividing
  by 100 gives `500` which renders as `500` in a `type="number"` input —
  acceptable. Admin can type `500.50` and the form converts to `50050`.
- `min:0` validation is preserved (pounds `0` → pence `0`).
- `step="0.01"` on the input keeps the browser's spinner sensible.

### Reflection
The planning surfaced the right level of change: a form-only conversion at
the input boundary, with the emitted DTO unchanged. No backend file needed
touching, and no docs (database-schema, api.md) needed updating because the
data contract is untouched.

## Phase 2: Implementation ✅

### Changes
- `resources/js/components/Instructors/PackageForm.vue`:
  - New internal `PackageFormState` interface with `total_price_pounds`.
    Original exported `PackageFormData` (the emit/save DTO) **unchanged** so
    `CreatePackageSheet.vue`, `EditPackageSheet.vue` and
    `Instructors/Tabs/Details/EditDetailsSubTab.vue` (the three consumers of
    this form) continue to receive `{ total_price_pence }` exactly as before.
  - `formData` ref now holds pounds. The `watch` on `props.package` converts
    `pkg.total_price_pence / 100` on load.
  - `handleSubmit` builds the emit payload with
    `Math.round(total_price_pounds * 100)` so the integer pence value is
    safe against floating-point drift.
  - `formattedPrice` and `pricePerLesson` computeds rewritten to consume
    pounds directly (no `/100` step needed).
  - Input field: `id`/`for`/`v-model` switched to `total_price_pounds`,
    `step="1"` → `step="0.01"`, label "Total Price (in pence)" → "Total
    Price (£)", placeholder updated to "Enter price in pounds (e.g.,
    500.00)".

### Verification
- Read final file end-to-end: 175 lines, clean, no leftover pence references
  except the legitimate `total_price_pence` in the export type (DTO) and
  inside `handleSubmit` where pounds → pence conversion lives.
- Three consumers of `PackageForm.vue` confirmed:
  - `components/Packages/CreatePackageSheet.vue` — `POST /packages` with
    `data` from `save` event. Still receives `total_price_pence`. ✓
  - `components/Packages/EditPackageSheet.vue` — `PUT /packages/{id}` with
    the same shape. ✓
  - `components/Instructors/Tabs/Details/EditDetailsSubTab.vue` — `POST
    /instructors/{id}/packages` and `PUT /packages/{id}`. ✓
- Backend FormRequests (`StorePackageRequest`, `UpdatePackageRequest`,
  `StoreInstructorPackageRequest`) still validate `total_price_pence` as
  integer ≥ 0. Unchanged.
- Index page (`Packages/Index.vue`) already renders
  `pkg.formatted_total_price` from the model accessor (`'£' .
  number_format($this->total_price_pence / 100, 2)`). No change needed.

### Out of scope (deliberately not done)
- Renaming the database column or the FormRequest fields.
- Touching API resources or `.claude/api.md` (the API still accepts pence).
- Modifying the Instructor "create bespoke package" path beyond what
  `PackageForm.vue` shares — that path already uses the same form component.
- Adding currency-input masking (e.g., always-two-decimals display while
  typing). Browsers handle `step="0.01"` adequately for this admin tool.

## Phase 3: Reflection ✅

### What worked
- Keeping `PackageFormData` (the emitted DTO) byte-identical meant zero
  ripple into three different sheet consumers and three backend FormRequests.
  The form-component became the only file that knows about the unit
  conversion.
- The model accessors (`formatted_total_price`, `formatted_lesson_price`)
  meant the listing table already displayed in pounds — no display work.
- The `Math.round(pounds * 100)` pattern is the standard guard for the
  classic JS float problem (`5.55 * 100 === 554.9999...`). Worth keeping
  even if the form mostly sees whole pounds.

### Subtle decisions worth flagging
- The internal state type `PackageFormState` is **not** exported. Only the
  DTO type `PackageFormData` is exported — that's deliberate. Outside
  callers must not see the pounds field; their interface is the integer
  pence DTO.
- The `|| 0` fallback in computeds guards against `undefined`/empty input
  during typing — without it the user sees "£NaN" briefly while clearing
  the field.
- `step="0.01"` rather than `step="any"` so the browser spinner increments
  by a penny, which matches the user's mental model.

### Technical debt / follow-ups (NOT done)
- No tests added (project rule: user maintains tests manually).
- No Pint / Prettier run (project rule: user handles code style).
- The lesson-price helper line is **derived** in the UI (pounds ÷ lessons)
  while the database also stores a computed `lesson_price_pence` via the
  model's `saving` hook. These can diverge transiently while editing, but
  the DB value is recalculated on save. Acceptable — pointing it out.

### Score
**Solution quality: 9/10.** Minimal, surgical change. Single file edited.
No new abstractions, no parallel actions, no docs to update. One point off
because a longer-term improvement would be a dedicated `<CurrencyInput>`
component if more pence-vs-pounds form fields appear elsewhere in the app —
but for one form, inlining the conversion is the right call.

---

**Status:** All phases complete.
**Last Updated:** 2026-06-17.
