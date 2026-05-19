# Task: Vehicles entity + Simplified/Advanced method choice (Phase 6/7) + Year-end archive (Phase 9)

**Status:** Phase 6 + Phase 7 + Phase 9 shipped. The whole task is complete pending migration runs and a smoke test.
**Last updated:** 2026-05-19

---

## Phase 6 progress log (2026-05-19)

**Shipped:**
- Migrations 1–5: `vehicles`, `vehicle_id` on `instructor_finances` + `mileage_logs`, `category_tax_mapping` (table + 16 baseline seed rows), `update_finance_categories_for_method_aware_picker` (8 new categories + `food_drink.selectable_in_picker = false`).
- `database-schema.md` updated for all five migrations including the new `category_tax_mapping` table doc.
- `App\Enums\VehicleMethod` with `label()` (UI: Simplified/Advanced) and `hmrcLabel()` (HMRC docs: Simplified/Actual).
- `App\Models\Vehicle` with `instructor`, `finances`, `mileageLogs` relations; `methodLocked()` + `isDisposed()` + `scopeActive` helpers; cast for `method`/`business_use_percentage`/dates.
- `InstructorFinance` + `MileageLog` updated: `vehicle_id` fillable, `vehicle()` belongsTo. `Instructor::vehicles()` hasMany added.
- `config/hmrc.php`: `actual_default_business_use_pct` (default 95) + `mileage_allowance` (45p / 25p / 10k miles).
- `app/Actions/Vehicle/`: `CreateVehicleAction`, `SwitchVehicleMethodAction` (soft-lock dialog returns `requires_confirmation` payload), `CompareVehicleMethodsAction`, `BackfillPrimaryVehicleAction` (single-transaction tag), `ReviewInsuranceSplitAction`, `DisposeVehicleAction`.
- `App\Services\VehicleService extends BaseService` — wraps all six actions with cache reads (`vehiclesFor`) + invalidation on writes.
- `App\Http\Controllers\Hmrc\Vehicles\VehicleController` + FormRequests (`StoreVehicleRequest`, `UpdateVehicleRequest`, `SwitchVehicleMethodRequest`, `BackfillPrimaryVehicleRequest`, `ReviewInsuranceSplitRequest`) + 8 routes.
- Frontend: `Hmrc/Vehicles/Index.vue`, `Hmrc/Vehicles/Sheet.vue` (create/edit with embedded MethodComparison and soft-lock confirm flow), `Hmrc/Vehicles/MethodComparison.vue` (Scenario A fallback for thin data), `Hmrc/Vehicles/BackfillPrompt.vue` (one-time primary-vehicle prompt), `Hmrc/Vehicles/InsuranceReview.vue` (per-row + bulk re-tagging).
- HMRC connection panel: added a "Vehicles" card alongside ITSA/VAT linking to `/hmrc/vehicles` (no top-level sidebar entry — current NavMain is flat and the workshop locked Vehicles as "not standalone").
- Diagnostic cleanup: "Hello World" and "Fraud-prevention headers" cards now gate behind `showDiagnostics` (true in sandbox env or `app()->isLocal()`). Falls back to env-gate because the current `UserRole` enum (owner/instructor/student) has no developer/admin role.

**Deferred to Phase 7:**
- **Expense-form picker tweak** (FinancesTab.vue): vehicle dropdown when category is `method_dependent`, plus greying with tooltip when the active vehicle is on Simplified. This is a meaningful change to an existing component and pairs naturally with Phase 7's auto-derivation wiring (which also touches the same form). Workshop §3 covers the locked UX.
- **"Calculated from your records" badge on Period.vue** — depends on Phase 7's `DeriveItsaQuarterlyTotalsAction` producing the calculated values to badge.
- **BackfillPrompt + InsuranceReview composition into the HMRC index gate** (workshop §4): the components are built but not yet wired into a "first visit post-Phase-6" flow. They render correctly when manually pointed at; the gating banner / inline empty-state logic on ITSA index is Phase 7's territory because it depends on the same data signals as the picker.
- **`vehicle_method_changes` audit table** — workshop §2 calls for from/to/changed_at/reason logging; not in the 5-migration list. Add as a 6th migration when wiring the method-switch dialog into the real UX in Phase 7.

**Known not-yet-tested:** none of the new code has been run against the database. Migrations should be applied (the user runs migrations manually). Smoke-check after migration:
- `php artisan route:list --path=hmrc/vehicles` (already verified — 8 routes register).
- Visit `/hmrc/vehicles` as an instructor; add a vehicle; switch method on an unlocked vehicle; view comparison panel (will show Scenario A fallback with no data).

---

## Phase 7 progress log (2026-05-19, same session)

**Shipped:**
- 3 actions in [app/Actions/Hmrc/Itsa/Derive/](app/Actions/Hmrc/Itsa/Derive/):
  - `BusinessMilesToAllowanceAction` — applies 45p / 25p banding **per tax year** (resets each 6 April). Splits the period at the tax-year boundary; each segment has its own 10k cap. Handles vehicle disposed mid-period by clamping to `disposed_on`. Returns per-segment diagnostics.
  - `ActualVehicleCostsAction` — sums `instructor_finances` rows where `vehicle_id = X` and `category_tax_mapping.method_dependent = true` and `claimable = true`, multiplied by the vehicle's `business_use_percentage`.
  - `DeriveItsaQuarterlyTotalsAction` — orchestrator. Per-vehicle picks Simplified or Actual based on current `method` (mid-period switches use current method for the whole period, per locked edge case). Sums non-vehicle expenses grouped by `category_tax_mapping.itsa_bucket`. Returns full 15-bucket map + diagnostics including `missing_primary_vehicle` flag (true when there's no vehicle or any business mileage row has a null `vehicle_id`).
- `HmrcItsaService::prefillForPeriod` — looks up the obligation, parses dates, delegates to the orchestrator.
- New route `GET /hmrc/itsa/{businessId}/period/{periodKey}/prefill` → JSON. Registered behind `auth + verified + EnsureInstructor + EnsureMtdEnrolled` like the other ITSA submission endpoints.
- `Period.vue` — fetches prefill on mount via axios; populates the form fields when there's no existing submission; otherwise leaves the form untouched but compares per-field to flag overrides. Added per-field "Calculated from your records" badge and "Manually overridden — Reset to calculated" link for turnover, other_income, and each itemised expense. Added missing-vehicle warning banner that links to `/hmrc/vehicles`.
- `InstructorController` finance + mileage endpoints now accept/validate/serialise `vehicle_id` and the `finances` payload includes `category_meta` (method_dependent/claimable/selectable_in_picker/itsa_bucket per slug) plus an `active vehicles` list with method labels.
- `CreateInstructorFinanceAction`, `CreateMileageLogAction`, `UpdateMileageLogAction` updated to pass `vehicle_id` through.
- `FinancesTab.vue` (the deferred Phase 6 picker tweak): vehicle dropdown appears only when the selected category is `method_dependent`. Auto-selects the single active vehicle when only one exists; forces explicit choice when two+. Shows an amber double-claim warning when a Simplified vehicle is selected for a method-dependent category. Filters category options by `selectable_in_picker` (so `food_drink` disappears from the picker going forward while keeping historical rows tagged).

**Edge cases handled:**
- ✅ Vehicle disposed mid-period — both derivation actions clamp to `disposed_on`.
- ✅ Tax-year boundary inside the period — `BusinessMilesToAllowanceAction.splitByTaxYear` splits at 6 April and each segment gets its own 10k cap.
- ⚠️ Method switched mid-period — current method used for whole period (v1 locked behaviour). The "Method change history" surface (workshop §2 calls for from/to/changed_at/reason logging) is a Phase 9 stretch and would need a new `vehicle_method_changes` migration.
- ✅ Pre-Phase-6 mileage with no `vehicle_id` — surfaced as `diagnostics.missing_primary_vehicle = true` and a banner on Period.vue linking to `/hmrc/vehicles`.

**Known limitations / deferred to later phases:**
- Turnover wiring uses `Lesson.amount_pence` for `status = completed` in the date range. If your billing pipeline derives turnover differently (e.g. order-level rather than per-lesson), Period.vue's calculated turnover will diverge from your books; manual override is the v1 escape hatch.
- "Other income" defaults to 0 — no current DRIVE category maps to HMRC's optional misc-income field. Manual entry only.
- BackfillPrompt / InsuranceReview components built in Phase 6 are still not wired into the ITSA index gate (workshop §4). They render correctly when manually pointed at via a parent page, but the "first visit post-Phase-6" gating flow isn't composed yet. This is now a Phase 9 follow-up since the data-layer work is complete.
- `vehicle_method_changes` audit table — deferred again. Workshop §2 mentions it but the soft-lock dialog functions without it.

**Smoke check after running migrations:**
- `php artisan route:list --path=hmrc/itsa` shows `hmrc.itsa.prefill`.
- Visit `/hmrc/itsa/{businessId}/period/{periodKey}` — Period.vue should hit `/prefill`, populate fields, and badges should appear on populated rows.
- Open a finance form on the instructor admin tab, pick a method-dependent category like `fuel` — the vehicle dropdown should appear; selecting a Simplified vehicle should show the amber double-claim warning.

---

## Phase 9 progress log (2026-05-19, same session)

**Shipped:**
- `barryvdh/laravel-dompdf ^3.1` added via `composer require` (PDF generation for the archive cover sheet, no system deps).
- Migration `2026_05_19_100429_create_year_end_archives_table`: `year_end_archives` table (instructor_id, tax_year_start, status, file_path, file_size_bytes, counts JSON, error_message, queued_at, generated_at, expires_at, purged_at). UNIQUE (instructor_id, tax_year_start) prevents duplicates. INDEX on (status) and (expires_at) for the daily pruning sweep. `database-schema.md` updated.
- `App\Models\YearEndArchive` with status constants, casts, `isReady()`, `isExpired()`, `taxYearLabel()`, `taxYearStartDate()`, `taxYearEndDate()`. `Instructor::yearEndArchives()` hasMany added.
- `config/hmrc.php` `year_end_archive` block: disk, path template, signed-URL TTL (24h default), retention years (6 default), Mandrill template slug.
- 8 actions in `app/Actions/YearEndArchive/`:
  - `WriteFinancesCsvAction` — every payment + expense row for the tax year (includes the Simplified-excluded rows the accountant still wants).
  - `WriteMileageCsvAction` — mileage log dump.
  - `CopyReceiptsToArchiveAction` — pulls every receipt from S3 into `receipts/Q1..Q4/` bucketed by tax-year quarter. Per-receipt failures logged but don't abort the build.
  - `WriteSubmissionsJsonAction` — one JSON per ITSA quarterly (with revision history) and per VAT return.
  - `RenderArchiveCoverSheetPdfAction` — dompdf rendering [resources/views/pdf/year-end-archive-cover.blade.php](resources/views/pdf/year-end-archive-cover.blade.php). Headline figures, bucket totals (correctly excluding Simplified-vehicle rows from HMRC bucket sums), vehicles list, submissions table, ZIP contents summary.
  - `BuildArchiveZipAction` — recursive `ZipArchive` write.
  - `BuildYearEndArchiveAction` — orchestrator. Transitions row through `queued → building → ready` (or `failed`), creates a per-build temp dir under `sys_get_temp_dir()`, cleans up on success/failure.
  - `SendArchiveReadyEmailAction` — renders `App\Mail\YearEndArchiveReadyMail` via the Laravel Mail facade. Mandrill is just the transport (configured via `MAIL_MAILER=mandrill`). The Mailable uses the Blade view at `resources/views/emails/year-end-archive-ready.blade.php` and is passed the signed download URL, expiry time, and content counts. Logs and swallows transport failures — the archive itself is already built and downloadable from the UI.
- `App\Jobs\BuildYearEndArchiveJob` — queued, 30-min timeout, `tries = 1`. Calls the orchestrator then dispatches the Mandrill email on success.
- `App\Services\YearEndArchiveService extends BaseService` — `archivesFor()` cached, `availableTaxYearsFor()`, `summaryCountsFor()`, `queueBuild()` (reuses the existing row when re-queueing failed/expired so the unique constraint holds).
- `App\Http\Controllers\Hmrc\Archive\ArchiveController` with `index`, `summary` (JSON for the pre-generation modal), `store`, `download`, `regenerate`, `emailLink`. Download endpoint accepts a signed URL (the path from the Mandrill email) OR an in-session instructor — the route sits OUTSIDE the auth-gated group so unauthenticated signed-URL access works from a fresh browser.
- Routes registered under `/hmrc/archive` (6 routes; signed download is a sibling at `/hmrc/archive/{archive}/download` outside the auth group). `php artisan route:list --path=hmrc/archive` confirms.
- Frontend: [Hmrc/Archive/Index.vue](resources/js/pages/Hmrc/Archive/Index.vue) with table of generated archives (status badges, contents summary, expiry, download / email-link / regenerate actions) and a per-year generate-button grid. [Hmrc/Archive/SummaryDialog.vue](resources/js/components/Hmrc/Archive/SummaryDialog.vue) ShadCN Dialog shows row counts before queueing the build.
- HMRC connection panel: "Year-end archives" card linking to `/hmrc/archive` (sibling to ITSA / VAT / Vehicles).
- Console commands: `hmrc:build-year-end-archive {instructor} {tax_year_start}` for ops CLI, and `hmrc:prune-year-end-archives` daily-sweep that flips `ready → expired` and removes the file when `expires_at` has passed (DB row kept so UI can offer "Regenerate"). Scheduled at 02:00 daily in `bootstrap/app.php`.

**Email template:**
- Blade view at [resources/views/emails/year-end-archive-ready.blade.php](resources/views/emails/year-end-archive-ready.blade.php). Mailable at [app/Mail/YearEndArchiveReadyMail.php](app/Mail/YearEndArchiveReadyMail.php). No Mandrill-dashboard template needed.
- Mandrill remains the transport (set `MAIL_MAILER=mandrill`). The Symfony Mandrill transport sends the rendered HTML directly.

**Storage layout:**
- ZIPs land on the `local` disk (default `storage/app/private`) at `archives/{instructor_id}/{tax_year_start}.zip`. Switch to S3 by setting `HMRC_ARCHIVE_DISK=s3`.
- Receipts are read from the `s3` disk regardless (where `instructor_finances.receipt_path` points).
- Staging dir for the build is `sys_get_temp_dir()/drivecrm-archive-{id}-{uniqid}` — cleaned up in a `finally` block.

**What's NOT done / explicit deferrals:**
- Multi-year and custom-date archives — out of scope per workshop §6.
- "Regenerate" after expiry creates a fresh row of work — the unique constraint means the same row is overwritten (its `purged_at` is cleared on re-queue).
- BackfillPrompt / InsuranceReview composition into the ITSA index gate — still not wired into a "first visit post-Phase-6" flow. Components are functional; bolt-on later when product priorities allow.
- `vehicle_method_changes` audit table — still deferred. Soft-lock dialog functions without it.

**Smoke check after running migrations:**
- `php artisan route:list --path=hmrc/archive` should show 6 routes (the 5 in-session routes + the signed-URL download sibling).
- Set `HMRC_ARCHIVE_MANDRILL_TEMPLATE=...` if your slug differs from the default.
- Visit `/hmrc/archive` as an instructor with some history; pick a year; the summary dialog should show non-zero counts; queue a build; verify the job runs (queue worker required), the ZIP lands on disk, and the email is sent. Re-download via the table or the "Email link" button.
- `php artisan schedule:list` should show the 02:00 daily `hmrc:prune-year-end-archives` entry.

---

## Context

DRIVE's HMRC MTD ITSA quarterly submission currently uses manual entry — instructors type figures into the `Period.vue` form which posts to HMRC. This task adds the per-vehicle Simplified vs Advanced (Actual) method choice that drives auto-derivation of the `carVanTravelExpenses` HMRC bucket (and pulls along a broader category refactor).

This file is **independent of `.claude/tasks/current-task.md`** — current-task.md has moved on to other work since the original Phase 1–5 MTD plan was written. Read this file standalone; cross-reference current-task.md only if you need historical context on Phases 1–3 (already shipped).

## Phase numbering

- **Phases 1, 1.5, 2, 3** — HMRC OAuth + fraud headers + ITSA quarterlies. **Shipped.**
- **Phase 3.5** — Final Declaration. **Descoped from product** (see `project_mtd_final_declaration_descoped.md` memory note). End-of-year handover lives in Phase 9 archive instead.
- **Phase 4** — VAT 9-box submission. Status unknown to this file; check `current-task.md` if relevant.
- **Phase 5** — Production readiness. Status unknown to this file.
- **Phase 6** — Vehicles entity + method choice + category refactor. **This file.**
- **Phase 7** — Auto-derivation of HMRC totals from `instructor_finances` + `mileage_logs` per vehicle method. **This file.**
- **Phase 9** — Year-end archive (replaces Final Declaration). **This file.**

## Source documents

- `.claude/hmrc-tax-categories-client-summary.md` — client-facing summary dated 2026-04-29. Covers DRIVE-to-HMRC category mapping, Simplified vs Actual with 5 worked scenarios, retention rules, archive concept. Read first.
- `.claude/hmrc-category-mapping.md` — engineering spec for `category_tax_mapping`. **Not read by the author of this file** — read it before coding to confirm table schema + per-row mappings.
- `.claude/tasks/current-task.md` — historical reference for shipped Phases 1–5 work (OAuth, tax profile, fraud headers, ITSA quarterlies, possibly VAT).
- `.claude/database-schema.md` — current schema; will need updating for new tables/columns.

---

## Locked decisions

1. **Method scope:** per-vehicle, lifetime per HMRC's rule. Schema captures this; UI doesn't enforce hard-blocks. *(Locked 2026-04-29; reaffirmed 2026-04-30.)*
2. **Method switching:** soft-lock, not hard-lock. If a quarterly has already been submitted with the current method, switching shows a warning but isn't blocked. Implemented via `SwitchVehicleMethodAction`. *(Locked 2026-04-29.)*
3. **UI default:** Simplified selected by default. Toggle available to switch to Advanced. *(Locked 2026-04-30.)*
4. **Terminology:**
   - Internal code / HMRC docs: `Simplified` / `Actual`
   - User-facing UI label: `Simplified` / `Advanced`
   - `VehicleMethod` enum cases stay as `Simplified` / `Actual`; `label()` returns the UI string.
5. **"Simplified" narrowed to mileage only.** HMRC's broader regime includes use-of-home-as-office (£10/£18/£26 per month) and living-at-business-premises flat rates. **Out of scope for DRIVE v1.** Tooltip on the picker should clarify so instructors don't ask why.
6. **New categories added:** `servicing`, `repairs`, `road_tax`, `breakdown_cover` (Actual-method vehicle running costs), plus `phone` and `accountant_fees` (general).
7. **Insurance split:** `insurance` → `vehicle_insurance` + `business_insurance`. Different HMRC buckets (`carVanTravelExpenses` vs `otherExpenses`).
8. **`food_drink`:** kept on existing rows for historical integrity; `selectable_in_picker = false` going forward; excluded from HMRC payloads regardless.
9. **`method_dependent: bool`** flag on `category_tax_mapping` hides/shows vehicle-running-cost categories from the picker based on the selected vehicle's method (a Simplified vehicle's running-cost rows would be double-claiming).
10. **Business-use percentage:** per-vehicle `business_use_percentage` column on `vehicles`. Seeded from `config('hmrc.actual_default_business_use_pct', 95)` on vehicle creation. **Not exposed in the UI for v1.** Future override path is a one-line config or admin command. *(Confirmed 2026-04-30.)*
11. **Capital allowances:** **out of scope.** They're a Final Declaration item; Final Declaration is descoped. The accountant handles capital allowances at year-end using the archive (Phase 9) as input. *(Confirmed 2026-04-30.)*
12. **Existing-row backfill is part of Phase 6 scope:**
    - **Primary vehicle creation prompt** on first ITSA visit post-Phase-6; bulk-assign all existing vehicle-category finance rows + all existing mileage log rows to it.
    - **Insurance review screen** — one-time UI listing every existing `insurance` row and asking the instructor to re-tag each as `vehicle_insurance` or `business_insurance`. **No auto-default** — defaulting either way miscategorises some rows.

---

## Pre-implementation: UI design workshop ✅

**Completed 2026-05-15.** Outcomes locked in the section below (`## UI design — locked 2026-05-15`). The topics covered were:

### Phase 6/7 UI

- **Comparison panel layout** — Simplified vs Advanced last-12-months deduction figures. Empty-state copy when instructor has <1 month of data.
- **Method-switch dialog copy** — soft-lock warning wording when `lifetime_method_locked_at` is set.
- **"Calculated from your records" badge pattern** — per-field on `Period.vue`. Includes "manually overridden" state and "reset to calculated" link.
- **Picker behaviour** — how `method_dependent` categories appear/disappear when the active vehicle is Simplified vs Advanced.
- **Vehicle CRUD UX** — list page, Sheet form, dispose-vehicle flow.
- **Primary-vehicle backfill UX** — one-time prompt on first ITSA visit post-Phase-6.
- **Insurance review UX** — one-time screen for the existing `insurance` rows split.
- **Default-suggest copy** — exact wording when the comparison panel recommends Simplified.

### HMRC page cleanup (independent of Phase 6/7 but in scope for the workshop)

The current `/hmrc` page (Phase 1+2 work) has diagnostic cards that should not be visible to instructors in production:
- **"Diagnostic — Hello World"** card
- **"Diagnostic — Fraud-prevention headers"** card

These need to be either:
- (a) Gated behind a dev/admin role
- (b) Hidden in production (`config('app.env') !== 'local'`)
- (c) Removed entirely from instructor view (kept as `php artisan` commands for ops)

Decide which during the workshop.

### Phase 9 (year-end archive) UX

- **Tax year selector** — dropdown of completed tax years, or date range picker.
- **Trigger UX** — single "Download tax year archive" button or a form with options?
- **Async vs sync** — large archives may take >30s. Email-when-ready vs in-page progress?
- **ZIP contents preview** — show the instructor what they're about to download (count of finance rows, mileage logs, receipts, submissions).
- **Where lives on disk** — `storage/app/archives/{instructor_id}/{tax_year}.zip`? Pruning policy?

---

## UI design — locked 2026-05-15 (workshop outcomes)

The whole shape rests on one principle: **the vehicle is the spine.** Every fuel receipt, every insurance row, every mileage entry, every quarterly calculation either belongs to a vehicle or is explicitly non-vehicle (advertising, phone, accountant fees, business insurance). No vehicle → no ITSA filing.

### User journey (steady state)

1. Instructor opens the expense form → category picker first.
   - Non-vehicle category (advertising, phone, etc.) → no vehicle field shown.
   - Vehicle-bound category → vehicle field appears below.
2. Vehicle picker default behaviour:
   - **One active vehicle:** auto-selected.
   - **Two+ active vehicles:** no default, required field, deliberate choice.
   - Disposed vehicles (i.e. `disposed_on` set) are hidden from the picker but stay attached to their historical rows.
3. At quarter-end, [Period.vue](resources/js/pages/Hmrc/Itsa/Period.vue) auto-fetches the prefill, populates fields per-bucket, shows the "Calculated from your records" badge on each, instructor optionally overrides, submits.
4. After first successful quarterly submission for a vehicle, `lifetime_method_locked_at` is set → method changes go through the soft-lock dialog.

### 1. Comparison panel ([MethodComparison.vue](resources/js/components/Hmrc/Vehicles/MethodComparison.vue))

- **Always shown.** No data-thresholding.
- **Empty/thin-data state:** render Scenario A from `hmrc-tax-categories-client-summary.md` §6 as the industry baseline (28k miles, £6,135 running costs → Simplified £9,000 vs Advanced £5,828). Label clearly: *"Typical full-time instructor — your actual figures will replace this once you've logged 3 months of expenses."*
- **Two columns when `vehicle.acquired_on` is inside the current tax year:** "Last 12 months" + "Projected next 12 months (steady state)". The projection strips capital allowances. For older vehicles, single-column last-12-months only.
- **Default recommendation:** "Suggested" badge on Simplified card *unless* projected-year-2 numbers show Advanced still winning. Caveat copy: *"This is a tax decision worth checking with your accountant. We're suggesting Simplified because it usually wins for instructors who keep cars 5+ years and avoids 6 years of receipt-keeping."*
- **Where it lives:** embedded in the vehicle Sheet at create-time + reachable from the ITSA index via a per-vehicle "Compare methods" link. Not always-visible on the ITSA index.

### 2. Method-switch dialog

Triggered when an instructor changes the method radio on a vehicle whose `lifetime_method_locked_at` is set.

- **Modal with two-stage confirm:** list of impacts + checkbox *"I understand this is a permanent HMRC-tracked decision"* + Confirm button.
- **Optional reason field.** Captured if given. Persist switches in a `vehicle_method_changes` table (or JSONB column on `vehicles` — implementer's call during schema work) with `from_method`, `to_method`, `changed_at`, `reason`.
- **Inside the dialog:** a compact read-only `MethodComparison` panel showing what they'd be giving up, plus a bullet list of behavioural changes:
  - *"Vehicle running-cost categories (fuel, MOT, servicing, repairs, road tax, vehicle insurance, breakdown cover) will [appear in / disappear from] the expense picker for this vehicle."*
  - *"Your last-12-months figure would change from £X to £Y."*
  - *"Your current open quarterly will recalculate using the new method for the entire period — not split at today's date. Submit a correction afterwards if you've already entered figures."*
  - *"Past submitted quarterlies are not amended."*

### 3. Picker behaviour (expense form + [Period.vue](resources/js/pages/Hmrc/Itsa/Period.vue))

- **Smart hybrid flow:** category first, vehicle field appears only for `method_dependent = true` categories. Category list is **not** filtered by vehicle.
- **Simplified-vehicle running costs are greyed-out with tooltip**, not hidden. Tooltip copy: *"This vehicle is on Simplified. Mileage already covers fuel/insurance/MOT/servicing/repairs/road tax/breakdown cover — claiming them separately would double-claim."*
- **Historical rows on a now-Simplified vehicle stay editable** and tagged. They're excluded from HMRC payloads (Phase 7 derivation filters by current method). Index/list views show them with a subtle *"(not claimed)"* marker.
- **"Calculated from your records" badge pattern on [Period.vue](resources/js/pages/Hmrc/Itsa/Period.vue):**
  - Default: badge + value.
  - User types into the field: badge changes to *"Manually overridden"* + small *"Reset to calculated"* link.
  - No reason-for-override field in v1. Cheap to add later if HMRC audits demand it.

### 4. Backfill UX (existing instructors, first visit post-Phase-6)

Two sequential flows: **primary-vehicle prompt** then **insurance review**.

- **Primary-vehicle prompt:**
  - **ITSA index:** inline empty state replacing normal content. *"Before you can use this page, we need to know which vehicle these expenses belong to."* Hard block — cannot proceed to file without it.
  - **All other HMRC pages:** persistent top banner with "Set up your vehicle" CTA. Soft.
  - **Form fields:** minimal — `display_name` pre-filled "My tuition car", `registration` (optional), `method` radio with comparison panel below (using their real last-12-months data). `engine_size_cc`, `acquired_on`, `business_use_percentage` defaulted; editable later.
  - **Cannot be skipped.** On save, `BackfillPrimaryVehicleAction` runs in a single transaction: tags `vehicle_id` onto every existing vehicle-bound finance row and every mileage log.

- **Insurance review** (fires immediately after primary-vehicle creation):
  - Table of every existing `insurance` row with per-row toggle (vehicle / business).
  - **No auto-default.** Bulk-action buttons at the top: *"Mark all as vehicle insurance"* / *"Mark all as business insurance"* for the escape hatch.
  - *"Skip — I'll do this later"* link. **Skipped rows are excluded from HMRC payloads** until reviewed. A banner reappears on the ITSA index until the review is complete.

### 5. Diagnostic-card cleanup (existing `/hmrc` page)

- **"Hello World" + "Fraud-prevention headers" cards: role-gate behind admin/developer role.** Hidden from instructors. Kept accessible to sam/ops for production debugging when HMRC throws a fraud-headers rejection.
- **Fallback:** if no role infrastructure exists in the codebase at implementation time, fall back to env-gate (`config('app.env') === 'local'`). To be verified during coding.

### 6. Year-end archive UX (Phase 9)

- **`/hmrc/archive` page:**
  - Table of available archives: tax year, generated date, size, status badge (Generating / Ready / Expired), download button.
  - "Generate new archive" form at the top: tax year dropdown (only completed years; current year disabled with tooltip *"available after 5 April YYYY"*).
- **Pre-generation summary modal** before queuing: *"Your archive will include: X finance rows, Y mileage logs, Z receipts, 4 quarterly submissions for 2026/27. Generate?"* Counts are cheap queries, no ZIP yet.
- **Async generation:** queued job → email when ready → 24h signed-URL download. In-page status badge updates via page refresh (no realtime/websocket).
- **Storage:** local disk at `storage/app/archives/{instructor_id}/{tax_year}.zip`. Move to S3 only if Laravel Cloud's local disk becomes a constraint.
- **Pruning:** daily scheduled command. Archives retained 6 years from end of tax year (HMRC requirement). On expiry, file is deleted, DB row retained with `expired_at` set → UI shows "Regenerate" button.
- **Signed URL TTL:** 24 hours. Regeneratable on demand (re-emails the link).
- **Spreadsheet format inside the ZIP: CSV**, not XLSX. Universal, no library dependency.

### 7. Sidebar nav placement

- **"Vehicles" nests under HMRC** alongside ITSA / VAT / Archive entries — not a top-level sibling. Vehicles are an HMRC-domain concept, not standalone.

---

## Phase 6 scope: vehicles + category refactor + backfill

### Database

Five migrations:

1. **`create_vehicles_table`**
   - `id`, `instructor_id` (FK cascade), `display_name`, `registration` (nullable), `engine_size_cc` (nullable, future VAT fuel scale charges), `method` (string, default `'simplified'`), `business_use_percentage` (decimal 5,2, default from config), `acquired_on` (date), `disposed_on` (date nullable), `lifetime_method_locked_at` (timestamp nullable), timestamps
   - Soft delete? Probably not — disposal date is the soft-delete signal here.

2. **`add_vehicle_id_to_instructor_finances_table`**
   - `vehicle_id` (FK to vehicles, nullable, `nullOnDelete`)
   - Nullable because not all finance categories are vehicle-related (advertising, accountant fees, etc.).

3. **`add_vehicle_id_to_mileage_logs_table`**
   - `vehicle_id` (FK to vehicles, nullable on creation, populated by backfill).
   - After backfill, every existing row has a `vehicle_id`; new rows require it.

4. **`create_category_tax_mapping_table`**
   - `id`, `category` (string, unique), `vat_treatment` (string), `itsa_bucket` (string — one of the 15 HMRC buckets, or null for excluded), `claimable` (bool), `method_dependent` (bool), `selectable_in_picker` (bool, default true), timestamps
   - Seeded via a `database/seeders/CategoryTaxMappingSeeder.php` containing the table from `hmrc-tax-categories-client-summary.md` §4 plus the new categories from locked-decision §6.

5. **`update_finance_categories_for_method_aware_picker`**
   - Adds rows to `category_tax_mapping` for: `servicing`, `repairs`, `road_tax`, `breakdown_cover`, `phone`, `accountant_fees`, `vehicle_insurance`, `business_insurance`
   - Marks `food_drink.selectable_in_picker = false`
   - **Does NOT** auto-split existing `insurance` rows — that's the manual review screen's job.

### Enum

`app/Enums/VehicleMethod.php`:

```php
case Simplified = 'simplified';
case Actual = 'actual';

public function label(): string  // UI label
{
    return match ($this) {
        self::Simplified => 'Simplified',
        self::Actual => 'Advanced',
    };
}

public function hmrcLabel(): string  // For internal docs / audit
{
    return match ($this) {
        self::Simplified => 'Simplified',
        self::Actual => 'Actual',
    };
}
```

### Models

- `App\Models\Vehicle` — `belongsTo(Instructor)`, `hasMany(InstructorFinance)`, `hasMany(MileageLog)`. Cast `method` → `VehicleMethod::class`, `business_use_percentage` → `float`, dates appropriately. Helper `methodLocked(): bool`.
- Update `InstructorFinance` and `MileageLog` with `belongsTo(Vehicle)`.

### Actions (`app/Actions/Vehicle/`)

- `CreateVehicleAction` — defaults `method = Simplified`, seeds `business_use_percentage` from config.
- `SwitchVehicleMethodAction` — checks `lifetime_method_locked_at`; returns a `SoftLockWarning` DTO (or simple array `{ requires_confirmation: bool, message: string }`) when set; otherwise switches cleanly. Always called twice in the soft-lock path: once to fetch the warning, once with `confirmed=true` to commit.
- `CompareVehicleMethodsAction` — takes a vehicle + 12-month window, returns `{ simplified_pence, actual_pence }`. Shares math with Phase 7 derivations.
- `BackfillPrimaryVehicleAction` — invoked from the primary-vehicle prompt; creates a vehicle, bulk-assigns existing rows.
- `ReviewInsuranceSplitAction` — takes an array of `{ finance_row_id, target_category }` decisions from the review screen, updates each row's category.

### Service

`App\Services\VehicleService extends BaseService`. Cache `vehiclesFor(Instructor)` with invalidation on every write.

### Controller + routes

`App\Http\Controllers\Hmrc\Vehicles\VehicleController` with index, store, update, destroy (mark `disposed_on`), `switchMethod`, plus a `compareJson` endpoint feeding the comparison panel.

```
GET    /hmrc/vehicles             vehicles.index
POST   /hmrc/vehicles              vehicles.store
PUT    /hmrc/vehicles/{vehicle}   vehicles.update
DELETE /hmrc/vehicles/{vehicle}   vehicles.dispose  (sets disposed_on)
POST   /hmrc/vehicles/{vehicle}/switch-method  vehicles.switch-method
GET    /hmrc/vehicles/{vehicle}/compare         vehicles.compare  (JSON for the panel)
POST   /hmrc/vehicles/backfill-primary           vehicles.backfill
POST   /hmrc/vehicles/review-insurance           vehicles.review-insurance
```

### Frontend

- `resources/js/pages/Hmrc/Vehicles/Index.vue` — list view, method badges, add/edit/dispose actions
- `resources/js/components/Hmrc/Vehicles/Sheet.vue` — Sheet form for create/edit
- `resources/js/components/Hmrc/Vehicles/MethodComparison.vue` — embeddable comparison panel, used inside the Sheet *and* on the ITSA index
- `resources/js/components/Hmrc/Vehicles/BackfillPrompt.vue` — primary-vehicle one-time prompt
- `resources/js/components/Hmrc/Vehicles/InsuranceReview.vue` — one-time insurance re-tagging screen
- Sidebar nav: new "Vehicles" entry under HMRC / Tax (or as a sibling — workshop decision)
- Update existing `Period.vue` for the picker to respect `method_dependent` — but actual auto-derivation comes in Phase 7

### Config

Add to `config/hmrc.php`:
```php
'actual_default_business_use_pct' => env('HMRC_ACTUAL_DEFAULT_BUSINESS_USE_PCT', 95),
```

---

## Phase 7 scope: auto-derivation per method

### Actions (`app/Actions/Hmrc/Itsa/Derive/`)

- `BusinessMilesToAllowanceAction` — for Simplified vehicles. Sums `mileage_logs.business_miles` for the vehicle in the period. Applies **45p for the first 10,000 business miles and 25p thereafter, per tax year** (not per period — the 10k threshold resets each April 6, not each quarter boundary). Returns pence.
- `ActualVehicleCostsAction` — for Actual vehicles. Sums `instructor_finances` rows where `vehicle_id = X` and `category_tax_mapping.method_dependent = true` and `claimable = true`, in the period. Multiplies by `business_use_percentage`. Returns pence.
- `DeriveItsaQuarterlyTotalsAction` — orchestrates: per-vehicle math (whichever method each vehicle is on) plus non-vehicle expense category sums. Returns `{ turnover_pence, other_income_pence, expenses: { car_van_travel_expenses, admin_costs, … } }` matching `Period.vue`'s form shape.

### Wiring

- New route `GET /hmrc/itsa/{businessId}/period/{periodKey}/prefill` returns JSON.
- `Period.vue` on mount calls this via axios; form fields populate with calculated values; each field shows a "calculated from your DRIVE records" badge.
- Per-field "Reset to calculated" link when manually overridden.
- Manual override remains: typing replaces the calculated value, badge changes to "manually overridden".

### Edge cases (worth listing before sandbox testing surfaces them)

- **Vehicle disposed mid-period** → split the period at `disposed_on` for the math.
- **Method switched mid-period** → use the *current* method for the whole period in v1; surface a warning if `lifetime_method_locked_at` is inside the period.
- **Tax-year boundary inside a period** (period straddling 6 April) → split the 45p/25p banding at the boundary; banding resets each tax year.
- **Pre-Phase-6 mileage with no `vehicle_id`** → relies on backfill having run. If the instructor hasn't completed the primary-vehicle prompt, block the prefill call with a clear "complete vehicle setup first" message.

---

## Phase 9 scope: year-end archive (replaces Final Declaration)

### Goal

Single click → ZIP archive of an entire tax year for handover to the accountant. Covers what Final Declaration *would* have, except instead of submitting to HMRC, it produces a deliverable the accountant uses.

### Contents

- Spreadsheet of all finance rows for the tax year (XLSX or CSV — workshop decision)
- Spreadsheet of all mileage logs for the tax year
- All receipts (PDFs + photos) attached to finance rows, organised by quarter
- Each HMRC submission's request + response + correlation ID (one JSON per submission)
- A human-readable PDF cover sheet (turnover, total expenses by HMRC bucket, vehicle method used per vehicle, submission references)

### Implementation sketch

- `App\Console\Commands\BuildYearEndArchive {user_id} {tax_year}` — invokable from CLI or queued from the controller
- `App\Jobs\BuildYearEndArchiveJob` — queued; writes to `storage/app/archives/{instructor_id}/{tax_year}.zip`; emails the instructor when ready
- Controller endpoint `POST /hmrc/archive/{taxYear}` queues the job; index page lists available archives
- Pruning: keep archives for 6 years (HMRC retention), auto-delete after that

### Out of scope (locked)

- **Multi-year archives** — single tax year only for v1.
- **Custom date ranges** — full tax year only (HMRC tax year, 6 Apr–5 Apr).
- **Live editing of archive contents** — it's a snapshot at generation time.

---

## Out of scope (explicit)

- **Capital allowances** — accountant's problem; Final Declaration is descoped.
- **HMRC's wider Simplified Expenses regime** — home-as-office flat rate, business premises flat rate. Not relevant for driving instructors per the framing.
- **Auto-derived `business_use_percentage`** from business miles ÷ total miles. v1 uses the config default.
- **Cross-vehicle expense apportionment** — if a finance row applies to multiple vehicles (rare). v1 forces one vehicle per row.
- **Quarterly amendments after Phase 9 archive is generated** — schema allows it; UX flow not in v1 scope.

---

## Open items

Closed during the 2026-05-15 workshop:
1. ✅ Sidebar nav placement → nested under HMRC. See workshop §7.
2. ✅ Test API cards → role-gate behind admin/developer; env-gate fallback. See workshop §5.
3. ✅ Year-end archive sync/async → async, email-when-ready. See workshop §6.
4. ✅ Phase 9 archive storage + pruning → local disk + 6-year retention. See workshop §6.
5. ✅ Comparison panel timing → always shown, Scenario A baseline when data is thin. See workshop §1.

Still open (not UI — flag for separate decision):
- **Final-declaration descoping knock-on:** the production HMRC API subscription list drops to 9 APIs per `project_mtd_final_declaration_descoped.md` memory. Confirm which subscriptions stay before go-live. Out of scope for Phase 6/7/9 code; relevant when configuring the production HMRC sandbox → live transition.

---

## How a fresh session should pick this up

1. Read this file (`vehicles-and-method-choice.md`) end-to-end — workshop outcomes are locked in the "UI design — locked 2026-05-15" section.
2. Read `.claude/hmrc-tax-categories-client-summary.md` for category-mapping context.
3. Read `.claude/hmrc-category-mapping.md` for the engineering-side mapping table.
4. **Workshop is done — start coding.** Begin Phase 6 with migration #1 (`create_vehicles_table`). Build in this order:
   - Migrations #1 → #5
   - Enum + Models + Actions + Service + Controller
   - Frontend components (Index, Sheet, MethodComparison, BackfillPrompt, InsuranceReview)
   - Sidebar nav entry
   - Diagnostic-card role gate cleanup on `/hmrc`
5. After Phase 6 ships, start Phase 7 (auto-derivation). Phase 9 (year-end archive) ships last.

Phase 6 → Phase 7 → Phase 9 in that order. Phase 6 must ship complete before Phase 7 starts (Phase 7 depends on the data model, backfill, and category mapping landing first).
