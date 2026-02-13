# Task: Snapshot Package Costs on Orders

**Created:** 2026-02-13
**Last Updated:** 2026-02-13
**Status:** ✅ All Phases Complete - Awaiting Review

---

## Overview

### Goal
Add package cost snapshot columns to the `orders` table so that each order retains a permanent record of the pricing at the time of purchase. This ensures that if a package's price changes later, historical orders still reflect the correct amounts.

### Problem
Currently `orders` only has a `package_id` FK. All pricing/naming data is read live from `packages`. If a package price is updated, every historical order retroactively shows the new price — incorrect for reporting, emails, and audit purposes.

### Solution
Add snapshot columns to `orders`:
- `package_name` — name of the package at time of purchase
- `package_total_price_pence` — total package price snapshot
- `package_lesson_price_pence` — per-lesson price snapshot
- `package_lessons_count` — number of lessons snapshot

Then update all code that reads pricing from `$order->package->...` to use the snapshotted values instead.

---

## Phase 1: Planning ✅

### Tasks
- [x] Read instructions.md, backend-coding-standards.md, database-schema.md
- [x] Identify all files that reference `$order->package` for pricing data
- [x] Map out the full impact of the change
- [x] Create task breakdown

---

## Phase 2: Migration + Model Changes ✅

### Tasks
- [x] Create migration to add snapshot columns to `orders`
- [x] Backfill existing orders in migration (from current package data)
- [x] Update `Order` model: add to `$fillable`, add `casts()`
- [x] Update `.claude/database-schema.md`

---

## Phase 3: Snapshot at Creation ✅

### Tasks
- [x] Update `CreateOrderFromEnquiryAction` to populate snapshot fields when creating an order

---

## Phase 4: Read from Snapshot ✅

### Tasks
- [x] Update `GetStudentDetailAction` — use `$order->package_total_price_pence`
- [x] Update `GetInstructorPupilsAction` — use `$order->package_total_price_pence`
- [x] Update `OrderConfirmationNotification` — use snapshotted fields
- [x] Update `WebhookController` — use `$order->package_lessons_count`
- [x] Search for any other `$order->package->` pricing references — none remaining

---

## Phase 5: Review & Reflection ✅

### Verification
- [x] No remaining `$order->package->` references for pricing data (grep confirmed)
- [x] No frontend references to `order.package.` for pricing (grep confirmed)
- [x] `package_id` FK still intact for non-pricing queries
- [x] `database-schema.md` updated with snapshot columns + business logic note

### Reflection
- Clean, minimal change — 4 columns added, 5 files updated to read from snapshot
- Migration includes backfill so existing orders get snapshot data immediately
- No breaking changes — `package_id` FK preserved for template reference queries
- `orders.package` relationship still available for non-pricing use cases

---

## Decisions Log
- **Keep `package_id` FK** — still needed for querying "which orders used this package template", and for non-pricing metadata
- **Nullable columns** — existing orders will be backfilled in migration, but nullable provides safety
- **No `description` snapshot** — package description is not used in any order context currently; can be added later if needed

## Files Changed
| File | Change |
|------|--------|
| `database/migrations/2026_02_13_200000_add_package_snapshot_to_orders_table.php` | NEW — adds 4 snapshot columns + backfills existing orders |
| `app/Models/Order.php` | Added snapshot fields to `$fillable` and `casts()` |
| `app/Actions/Onboarding/CreateOrderFromEnquiryAction.php` | Snapshots package data at order creation |
| `app/Actions/Student/GetStudentDetailAction.php` | Reads revenue from `$order->package_total_price_pence`, removed `orders.package` eager load |
| `app/Actions/Instructor/GetInstructorPupilsAction.php` | Same — snapshot for revenue, removed `orders.package` eager load |
| `app/Notifications/OrderConfirmationNotification.php` | All pricing/name reads from snapshot columns |
| `app/Http/Controllers/WebhookController.php` | Log line uses `$order->package_lessons_count` |
| `.claude/database-schema.md` | Updated orders table with snapshot columns + business logic note |
