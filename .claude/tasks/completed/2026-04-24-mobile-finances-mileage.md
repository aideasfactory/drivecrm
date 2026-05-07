# Task: Mobile API — Finances + Mileage

## Overview

Expose the Finances + Mileage sections to the mobile app via `/api/v1/instructor/*` endpoints. The Sanctum-authed instructor is auto-resolved via `ResolveApiProfile` middleware; no `{instructor}` in the URL. Parity with the admin admin UI's capabilities (create/read/update/delete + receipt upload/delete + config dropdowns) plus a summary endpoint for the app's overview screen.

### Decisions locked in Phase 1

- **Default date range:** last 30 days when `from`/`to` not provided. Applied on `/summary`, `/finances`, `/mileage`.
- **Pagination:** `cursorPaginate()` on the list endpoints (`/finances`, `/mileage`). Cursor encoded on `(date, id)`, newest-first. `/summary` returns the full unpaginated dataset for the range (it's the app's overview, meant as one call; 30-day windows are small enough).
- **Stats always full-range:** `/summary` computes totals from the full date range, never from a single page.
- **List filters:** only `type` (payment/expense) on `/finances`. Date range + cursor on both. No category / mileage-type filter for v1.
- **Single-record GET:** yes, `/finances/{id}` and `/mileage/{id}` — app uses these for detail view / receipt-URL refresh.
- **Receipt URL TTL:** 20 min (unchanged from admin). App re-fetches when it renders.
- **Recurring:** stays as display-only badge (`is_recurring` + `recurrence_frequency`). No scheduler, no materialisation. Real recurrence is a separate future task.
- **Resources:** convert the admin controller's private `serializeFinance` / `serializeMileageLog` helpers into proper `InstructorFinanceResource` (update existing) + `MileageLogResource` (new). Reused by API + optionally later by the admin endpoints.
- **Auth model:** every endpoint runs inside the existing `/api/v1/instructor/*` group which already has `auth:sanctum` + `ResolveApiProfile` middleware. The controller pulls `$request->user()->instructor` and scopes queries via the relationship.
- **Ownership:** `abort(403)` on cross-instructor access, matching the existing `InstructorFinanceController` pattern.

### Endpoint shape

```
GET    /api/v1/instructor/finances/config               Dropdown options (cached client-side)
GET    /api/v1/instructor/finances/summary              Finances + mileage + stats for date range
GET    /api/v1/instructor/finances                      Cursor-paginated list (filters: type, date range)
POST   /api/v1/instructor/finances                      Create
GET    /api/v1/instructor/finances/{finance}            Single record
PUT    /api/v1/instructor/finances/{finance}            Update
DELETE /api/v1/instructor/finances/{finance}            Delete
POST   /api/v1/instructor/finances/{finance}/receipt    Upload (multipart)
DELETE /api/v1/instructor/finances/{finance}/receipt    Remove

GET    /api/v1/instructor/mileage                       Cursor-paginated list (date range)
POST   /api/v1/instructor/mileage                       Create
GET    /api/v1/instructor/mileage/{mileageLog}          Single record
PUT    /api/v1/instructor/mileage/{mileageLog}          Update
DELETE /api/v1/instructor/mileage/{mileageLog}          Delete
```

### `/summary` response shape

```json
{
    "date_range": { "from": "2026-03-25", "to": "2026-04-24", "default_applied": true },
    "finances": [ /* InstructorFinanceResource[] */ ],
    "mileage": [ /* MileageLogResource[] */ ],
    "stats": {
        "total_records": 42,
        "total_payments_pence": 123456,
        "total_payments_formatted": "£1,234.56",
        "total_expenses_pence": 65432,
        "total_expenses_formatted": "£654.32",
        "net_balance_pence": 58024,
        "net_balance_formatted": "£580.24",
        "total_trips": 15,
        "business_miles": 450,
        "personal_miles": 80,
        "total_miles": 530
    }
}
```

### List response shape (Laravel cursor paginator default)

```json
{
    "data": [ /* InstructorFinanceResource[] */ ],
    "path": "...",
    "per_page": 25,
    "next_cursor": "eyJpZCI6...",
    "next_page_url": "...",
    "prev_cursor": null,
    "prev_page_url": null
}
```

---

## Phase 1: Planning ✅

Decisions locked above. Scope excludes: real recurrence (separate task), advanced filters, reporting/analytics, activity logs. Tests not touched per project rules. api.md update is an atomic part of every endpoint that's created/modified.

## Phase 2: Implementation ✅

### 2a. Form Requests

- [ ] Update [StoreInstructorFinanceRequest.php](app/Http/Requests/Api/V1/StoreInstructorFinanceRequest.php) — add `category` (type-gated via closure that reads effective `type` from input) + `payment_method` rules.
- [ ] Update [UpdateInstructorFinanceRequest.php](app/Http/Requests/Api/V1/UpdateInstructorFinanceRequest.php) — same, `sometimes`.
- [ ] New `UploadFinanceReceiptRequest` — `receipt` file, mimes + max-size from config.
- [ ] New `StoreMileageLogRequest` — date, start_mileage, end_mileage (gte:start_mileage), type, notes.
- [ ] New `UpdateMileageLogRequest` — all sometimes, plus `prepareForValidation()` / `withValidator()` hook to enforce end ≥ effective-start when only one of the two fields is sent (mirrors admin controller logic).
- [ ] New `FinanceIndexRequest` (or inline validate) — from/to date, cursor, type filter.
- [ ] New `FinanceSummaryRequest` (or inline validate) — from/to date.

### 2b. Resources

- [ ] Update [InstructorFinanceResource.php](app/Http/Resources/V1/InstructorFinanceResource.php) — add `category`, `category_label`, `payment_method`, `payment_method_label`, `receipt` object (url/name/mime/size) or null.
- [ ] New `MileageLogResource` in `app/Http/Resources/V1/` — matches admin serializer shape.

### 2c. Service layer

- [ ] Add a `getFinancesInRange(Instructor, ?from, ?to, ?type, ?cursor): CursorPaginator` method to `InstructorService`. Defaults to last 30 days.
- [ ] Add `getMileageInRange(Instructor, ?from, ?to, ?cursor): CursorPaginator`.
- [ ] Add `getFinanceSummary(Instructor, ?from, ?to): array` — returns `{ finances, mileage, stats, date_range }`. Stats computed from the full range.
- [ ] Add `findFinance(Instructor, int $id): InstructorFinance` (404 on missing, 403 on foreign) — keeps controllers slim.
- [ ] Add `findMileageLog(Instructor, int $id): MileageLog` — same.

(Actions stay single-purpose; the Service orchestrates the range + pagination logic because it's a cross-action query concern.)

### 2d. Controllers

- [ ] Update [InstructorFinanceController.php](app/Http/Controllers/Api/V1/InstructorFinanceController.php):
  - `index()` — accept `type`, `from`, `to`, `cursor`, return cursor-paginated `InstructorFinanceResource::collection()`.
  - `show(Request, InstructorFinance)` — ownership check + Resource.
  - `store(StoreInstructorFinanceRequest)` — include new fields.
  - `update(UpdateInstructorFinanceRequest, InstructorFinance)` — include new fields + ownership check.
  - `destroy(InstructorFinance)` — unchanged apart from Resource-ifying the response.
  - `uploadReceipt(UploadFinanceReceiptRequest, InstructorFinance)` — ownership check → `InstructorService::uploadFinanceReceipt()` → Resource.
  - `destroyReceipt(InstructorFinance)` — ownership check → `InstructorService::deleteFinanceReceipt()` → Resource.
  - `config()` — returns the same shape as admin's `financeConfigPayload()`.
  - `summary(Request)` — date range resolution + `InstructorService::getFinanceSummary()` → JSON.
- [ ] New `InstructorMileageController` in `app/Http/Controllers/Api/V1/` with index/store/show/update/destroy, mirroring the finance controller.

### 2e. Routes

- [ ] Add to [routes/api.php](routes/api.php) under the existing `/api/v1/instructor/*` group:
  ```php
  Route::get('finances/config', [InstructorFinanceController::class, 'config']);
  Route::get('finances/summary', [InstructorFinanceController::class, 'summary']);
  Route::get('finances/{finance}', [InstructorFinanceController::class, 'show']);
  Route::post('finances/{finance}/receipt', [InstructorFinanceController::class, 'uploadReceipt']);
  Route::delete('finances/{finance}/receipt', [InstructorFinanceController::class, 'destroyReceipt']);
  // existing: GET/POST /finances, PUT/DELETE /finances/{finance}
  Route::get('mileage', [InstructorMileageController::class, 'index']);
  Route::post('mileage', [InstructorMileageController::class, 'store']);
  Route::get('mileage/{mileageLog}', [InstructorMileageController::class, 'show']);
  Route::put('mileage/{mileageLog}', [InstructorMileageController::class, 'update']);
  Route::delete('mileage/{mileageLog}', [InstructorMileageController::class, 'destroy']);
  ```
  Order matters: `config` + `summary` + single routes declared before `{finance}` wildcards (or use route-model-binding-tolerant ordering — Laravel handles it fine if specific strings are declared first).

### 2f. Docs

- [ ] Update [.claude/api.md](.claude/api.md):
  - Extend existing Instructor Finances section: document new fields on GET/POST/PUT, add the 5 new endpoints (`config`, `summary`, single-GET, receipt upload, receipt delete).
  - New Instructor Mileage section.
  - Update TOC.
  - Add changelog entry at the bottom.

### 2g. Lint

- [x] `vendor/bin/pint --dirty --format agent` → `{"result":"pass"}`
- [x] `php -l` on all 11 touched PHP files → clean
- [x] `php artisan route:clear` + route list shows all 14 new/updated routes registered under `/api/v1/instructor/*`

## Phase 3: Reflection ✅

**Went well**
- **Reusing the existing `InstructorService` for range + summary logic** kept the controllers thin (each method is ≤ 10 lines). Because the service already owned `createFinance`/`updateFinance`/etc. from the admin pass, the new API controllers are almost pure adapters.
- **`cursorPaginate()` first use in the project** dropped in cleanly — Laravel's built-in paginator serialises to the `data/next_cursor/next_page_url/prev_cursor/prev_page_url` envelope that `InstructorFinanceResource::collection($paginator)` wraps automatically. No manual envelope building, and no bespoke transformer class.
- **Type-gated category rule using `Rule::in(...)`** with a private `categoryKeys()` helper on the FormRequest reads the effective `type` from the request (on store) or falls back to the route model's `type` (on update). Clean and keeps validation at the request boundary rather than leaking into the controller.
- **`withValidator` after-hook** in `UpdateMileageLogRequest` enforces `end >= effective_start` correctly even when only one of the two fields is sent in a PATCH/PUT. This was the cleanest place to put it.
- **`/summary` returning full unpaginated range** + **`/finances` + `/mileage` cursor-paginating** split the workload sensibly — overview screen is one call, detailed history is scrolled.

**Trade-offs / follow-ups**
- **`/summary` could return a lot of data for long date ranges.** A 30-day window is tiny; a full tax-year window could return 500+ records unpaginated. App should use `/finances` + `/mileage` lists for deep scrolling and keep `/summary` for overview. If this turns out to be a problem, add a server-side cap or pagination on summary's `finances`/`mileage` fields (stats still computed from full range).
- **Signed URL per row on list responses.** Each finance with a receipt triggers a `Storage::disk('s3')->temporaryUrl()` call inside the Resource's `toArray()`. For a page of 25 finance rows with receipts, that's up to 25 URL-sign calls (all local, no S3 roundtrip — it's HMAC — so very cheap, but still overhead). Fine for now; if it becomes hot, make `receipt.url` an opt-in field via `?with_receipt_urls=1` query param.
- **No `destroy` on receipt-less records returns 200 silently** — `DeleteFinanceReceiptAction` is a no-op when there's no receipt. Considered returning 409/422 but kept the idempotent-success behaviour to match the admin-side DELETE pattern and simplify the mobile retry flow.
- **The category rule doesn't surface which slugs are valid** in the 422 error message (just "The selected category is invalid."). App should pre-validate against the `/finances/config` response it cached. If this turns out to be a UX problem, swap `Rule::in(...)` for a custom closure that returns `"Valid options for expense: fuel, insurance, ..."`.
- **No tests** per project rules. 5 FormRequests, 2 Resources, 2 controllers, plus 3 new service methods are unprotected against regressions.
- **Recurring materialisation not implemented** — `is_recurring` remains a display-only flag. Deferred to its own task per Phase 1 decision. The API shape won't need reshaping when we add real recurrence; we'd add a `recurrence_parent_id` column and an "upcoming" endpoint, nothing else changes.
- **Admin-side controller still has private `serializeFinance`/`serializeMileageLog` duplicated** — didn't refactor the admin to use the new Resources this pass (risk of breaking the admin UI, and the shapes diverge slightly: admin includes `formatted_amount` in the same casing as the frontend expects). Low-priority clean-up for later.

---

**Status:** All phases complete.
**Last Updated:** 2026-04-24
