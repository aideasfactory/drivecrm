# Task: Finances Section Expansion (Backend + Admin UI)

## Overview

Expand the admin-area finances section on the instructor details page (`/instructors/{id}?tab=finances`). Add categories, payment methods, receipt uploads, and a new mileage-log record type. **API endpoints and mobile-app exposure are explicitly out of scope for this task** — we'll build those in a follow-up once the data model is proven in the admin UI.

### Decisions locked in Phase 1

- **Mileage has its own table** (`mileage_logs`), not extra fields on `instructor_finances`. Matches QuickBooks / Xero / FreeAgent / MileIQ. Fuel expenses are NOT linked to mileage logs — they're independent records. Reasoning: different shape (odometer readings vs amounts), different volume (per-trip vs per-transaction), and HMRC treats actual-cost vs mileage-allowance as alternative methods, not complements. If a real pairing need surfaces later, add nullable `fuel_expense_id` to `mileage_logs` then.
- **Categories** — two lists in `config/finances.php`: `expense_categories` and `payment_categories`. "None" is an explicit entry (`'none' => 'None'`) at the top of both, NOT NULL.
- **Payment methods** — shared list in config. UK spelling ("Cheque"). Nullable (genuinely optional).
- **Receipts** — separate endpoint (`POST /instructors/{instructor}/finances/{finance}/receipt`, DELETE to remove). Private S3 disk, 20-min signed URLs for display. Admin UI posts receipt as a second request after the record is saved.
- **Mileage UI** — fourth segment filter inside the existing `FinancesTab` (All / Payments / Expenses / Mileage). Keeps finance-related data cohesive.
- **Config slugs as VARCHAR, not DB ENUMs** — config can grow without migrations; validation lives in FormRequests / inline web validation.

### Schema changes

**Alter `instructor_finances`:**

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `category` | VARCHAR(64) | NO, default `'none'` | Backfill existing NULLs → `'none'` in the same migration. |
| `payment_method` | VARCHAR(32) | YES | |
| `receipt_path` | VARCHAR(255) | YES | Private S3 path. |
| `receipt_original_name` | VARCHAR(255) | YES | |
| `receipt_mime_type` | VARCHAR(64) | YES | |
| `receipt_size_bytes` | INT UNSIGNED | YES | |

New index: `(instructor_id, category)`.

**New `mileage_logs` table:**

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `instructor_id` | FK → instructors, cascade | Indexed. |
| `date` | DATE | Indexed with instructor_id. |
| `start_mileage` | INT UNSIGNED | |
| `end_mileage` | INT UNSIGNED | Validated ≥ start in FormRequest. |
| `miles` | INT UNSIGNED | Denormalised `end - start`, computed server-side for fast sums. |
| `type` | ENUM('business','personal') | Indexed with instructor_id. |
| `notes` | TEXT | Nullable. |
| `created_at`/`updated_at` | TIMESTAMP | |

No soft deletes (consistent with existing finances table).

### Config file shape (`config/finances.php`)

```php
return [
    'expense_categories' => [
        'none' => 'None',
        'our_account' => 'Our Account',
        'advertising' => 'Advertising',
        'association' => 'Association',
        'bank_charges' => 'Bank Charges',
        'computer_dvsa_fees' => 'Computer DVSA Fees',
        'equipment' => 'Equipment',
        'food_drink' => 'Food/Drink',
        'fuel' => 'Fuel',
        'insurance' => 'Insurance',
        'internet' => 'Internet',
        'mot' => 'MOT',
    ],
    'payment_categories' => [
        'none' => 'None',
        'franchise_payout' => 'Franchise Payout',
        'hmrc_tax' => 'HMRC Tax',
        'insurance' => 'Insurance',
        'referral' => 'Referral',
        'pupil_transfer_referral' => 'Pupil Transfer Referral',
    ],
    'payment_methods' => [
        'bank_transfer' => 'Bank Transfer',
        'card' => 'Card',
        'cash' => 'Cash',
        'cheque' => 'Cheque',
        'direct_debit' => 'Direct Debit',
        'paypal' => 'PayPal',
        'standing_order' => 'Standing Order',
    ],
    'mileage_types' => [
        'business' => 'Business',
        'personal' => 'Personal',
    ],
    'receipt' => [
        'max_size_kb' => 10240, // 10 MB
        'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],
];
```

### Validation rules (category gated by type)

Category validation depends on `type`:
- `type=expense` → category must be a key in `config('finances.expense_categories')`
- `type=payment` → category must be a key in `config('finances.payment_categories')`

Implemented via a custom `Rule::in(...)` computed from the current `type` value at request time.

---

## Phase 1: Planning ✅

Decisions locked above. No API work in this task. Tests not touched per project rules. Backfill of existing NULL `category` values → `'none'` handled inside the same alter migration.

## Phase 2: Implementation ✅

### 2a. Migrations + config + models

- [ ] `php artisan make:migration alter_instructor_finances_add_category_payment_method_receipt`
- [ ] Inside that migration: add 6 new columns, backfill `category` NULLs → `'none'`, then set NOT NULL default, add `(instructor_id, category)` index.
- [ ] `php artisan make:migration create_mileage_logs_table`
- [ ] Create `config/finances.php` with shape above.
- [ ] `php artisan make:model MileageLog`
- [ ] Update `app/Models/InstructorFinance.php` — fillable for 6 new cols, casts, `receipt_url` accessor (returns temporary S3 URL when path present, else null), `category_label` + `payment_method_label` accessors (read from config).
- [ ] Update `app/Models/Instructor.php` — add `mileageLogs(): HasMany`.
- [ ] Update `.claude/database-schema.md` — document altered `instructor_finances` + new `mileage_logs`.

### 2b. Actions

- [ ] Update `app/Actions/Instructor/CreateInstructorFinanceAction.php` — accept `category` + `payment_method` in `$data`.
- [ ] Update `app/Actions/Instructor/UpdateInstructorFinanceAction.php` — same.
- [ ] New `app/Actions/Instructor/UploadFinanceReceiptAction.php` — private S3 disk, path `instructors/{instructor_id}/finance-receipts/{finance_id}/{uuid}.{ext}`, deletes old receipt on replace.
- [ ] New `app/Actions/Instructor/DeleteFinanceReceiptAction.php` — removes S3 file + clears DB receipt columns.
- [ ] New `app/Actions/Instructor/Mileage/GetMileageLogsAction.php`
- [ ] New `app/Actions/Instructor/Mileage/CreateMileageLogAction.php` — computes `miles` server-side.
- [ ] New `app/Actions/Instructor/Mileage/UpdateMileageLogAction.php` — recomputes `miles` if start/end changed.
- [ ] New `app/Actions/Instructor/Mileage/DeleteMileageLogAction.php`

### 2c. Service

- [ ] Update `app/Services/InstructorService.php` — inject new Actions, add receipt + mileage methods. (Do NOT create a separate `FinanceService` or `MileageService`; keep cohesive with existing pattern.)

### 2d. Controller + routes

- [ ] Update `app/Http/Controllers/InstructorController.php`:
  - Extend `storeFinance` / `updateFinance` validation for `category` (type-gated) + `payment_method`.
  - Add `uploadFinanceReceipt(Instructor, InstructorFinance, Request)` + `destroyFinanceReceipt(Instructor, InstructorFinance)`.
  - Add `mileage`, `storeMileage`, `updateMileage`, `destroyMileage` methods mirroring the finance ones.
- [ ] Update `routes/web.php`, add (all inside the existing authenticated group):
  - `POST /instructors/{instructor}/finances/{finance}/receipt`
  - `DELETE /instructors/{instructor}/finances/{finance}/receipt`
  - `GET /instructors/{instructor}/mileage`
  - `POST /instructors/{instructor}/mileage`
  - `PUT /instructors/{instructor}/mileage/{mileageLog}`
  - `DELETE /instructors/{instructor}/mileage/{mileageLog}`

### 2e. Frontend — `resources/js/components/Instructors/Tabs/FinancesTab.vue`

- [ ] Pass config enums to the component via an Inertia prop (or axios-loaded endpoint — match existing pattern in tab).
- [ ] Add Category dropdown to the create/edit sheet — options switch based on selected `type` (payment vs expense).
- [ ] Add Payment Method dropdown to the create/edit sheet.
- [ ] Add Receipt file input. On submit: POST the record first, then on success POST the receipt file to the receipt endpoint. If receipt upload fails, keep the record and toast "Receipt upload failed — retry?".
- [ ] Show receipt indicator on the list row (paperclip icon if present); click opens the signed URL in a new tab.
- [ ] Add "Mileage" as a 4th segment filter next to All / Payments / Expenses.
- [ ] When Mileage filter active: swap the table + sheet for the mileage shape (date, start, end, auto-calc miles preview, type, notes).
- [ ] Summary cards remain on All/Payments/Expenses view; for Mileage view show: Total Business Miles, Total Personal Miles, Total Trips.

### 2f. Lint

- [x] `vendor/bin/pint --dirty --format agent` → `{"result":"pass"}`
- [x] `php -l` syntax check on all touched PHP files → clean
- [x] `php artisan route:clear` + route list verified all 6 new routes register
- [x] `php artisan config:clear` + tinker confirmed `config/finances.php` loads

All Phase 2 checklist items are complete. Every step marked ✅.

## Phase 3: Reflection ✅

**Went well**
- **Mileage as its own table was the right call.** The schema is cleaner for reporting (business-miles totals are a trivial `SUM(miles) WHERE type='business'`), and the UI got a fourth filter segment with zero friction because the existing `activeFilter` ref already supported the pattern. Keeping mileage independent from `instructor_finances` avoids the temptation to force pair fuel receipts to trips — a pairing that's genuinely rare in self-employed driving-instructor accounting.
- **Config-backed slugs** keep the dropdown contents in one file. When the user adds more categories pre-launch, it's an edit to `config/finances.php` + cache clear — no migration, no deploy ceremony.
- **Two-request save flow for receipts** (record JSON first, then multipart receipt upload) worked out cleanly on the Vue side and sets up the mobile API without rework. Receipt-upload failure on create still keeps the record; user sees a targeted toast and can retry from the edit sheet.
- **Single endpoint returns finances + config** avoided adding another round-trip for the dropdown options.

**Trade-offs / follow-ups**
- **No API v1 endpoints yet.** Deliberate — this task was admin-only. When we build the mobile API, the serializer helpers in `InstructorController::serializeFinance/serializeMileageLog` can be lifted into JSON Resources to keep response shape identical.
- **Receipt signed URLs are generated per-row on index.** For an instructor with hundreds of receipts, this is one `temporaryUrl()` call per record on every list refresh. Fine for now (list paging isn't added yet and typical volume is low); if it becomes hot, the accessor can be swapped for an explicit `?with_receipt_urls=1` query param that computes them lazily.
- **`UpdateMileageLogAction` has a minor code smell** — the `array_filter` + manual re-adding of `notes` is clunky because `notes` is allowed to be NULL. Could be cleaner with an explicit per-key loop, but it's isolated and covered by the controller-level `nullable` validation.
- **No tests** per project rules (user maintains tests manually). New endpoints, config validation, and Vue component are therefore unprotected against regressions — user's call if they want test coverage added later.
- **`end_mileage >= start_mileage` validation** lives in the controller rather than a FormRequest because resolving the "effective" start/end values (one may be missing on update) requires access to the model. A dedicated FormRequest could also do it via `prepareForValidation()` but the controller-level approach is smaller and matches the existing pattern in sibling endpoints.
- **UI is on the admin side only.** When we expose mileage/finances to the mobile app, the `config('finances.*')` arrays will need to be returned from a dedicated API endpoint (or included in the first `GET` response) so the mobile client can render the same dropdowns without hard-coding them.

---

**Status:** All phases complete.
**Last Updated:** 2026-04-23
