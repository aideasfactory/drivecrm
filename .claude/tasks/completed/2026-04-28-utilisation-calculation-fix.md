# Task: Fix Utilisation Calculation in Reports

## Overview

The Reports page (`/reports`) displays a utilisation metric per instructor and an overall figure across all instructors. The current calculation produces wrong percentages — it can exceed 100% in normal usage — because the denominator does not include booked slots.

**Required formula (per spec):**
```
utilisation = booked / (booked + available) × 100
```
where `available` means free / unbooked open slots, and `booked + available` is the total number of slots the instructor is working.

**Worked example from the brief:** 7 booked + 3 available → total 10 → utilisation = 70%.

The fix lives in a single backend file (`GetInstructorAvailabilityAnalyticsAction`). The frontend (`Reports/Index.vue`) reads four already-computed numbers from the action — `total_available`, `total_booked`, `total_free`, `utilization_rate` — so the contract on the wire stays the same; only the math changes. We will, however, fix the misleading prop name semantics (see Phase 1 decisions).

---

## Phase 1: Planning ✅

### Where utilisation is calculated (inventory)

| File | Lines | What it does |
|------|-------|--------------|
| `app/Actions/Report/GetInstructorAvailabilityAnalyticsAction.php` | 25–67 | The **only** place the metric is computed (per-instructor loop + summary aggregation). |
| `app/Services/ReportService.php` | — | Thin wrapper that delegates to the Action. No math here. |
| `app/Http/Controllers/ReportController.php` | 20 | Passes the Action's result through to Inertia. No math here. |
| `resources/js/pages/Reports/Index.vue` | 10–73, 125–139 | Renders the four numbers + percentage badge + progress bar. Display only — no calculation. |
| `tests/Feature/Reports/ReportIndexTest.php` | 25–88 | Existing test asserts current (buggy) behaviour. **Will need updating by the user** — per project rules I do not edit tests. |

### How the calendar_items lifecycle actually works (the source of the bug)

The `is_available` boolean on `calendar_items` flips off when a slot is booked. Confirmed by reading the production booking flow:

| State | `is_available` | `status` |
|-------|----------------|----------|
| Free slot the instructor opened | `true` | `DRAFT` (or null) |
| Slot held during checkout | `false` | `DRAFT` |
| Confirmed booking (`ConfirmCalendarItemsAction`) | **`false`** | `BOOKED` |
| Completed lesson | **`false`** | `COMPLETED` |
| Lunch / holiday / unavailability block | `false` | `null` |

Evidence: `app/Actions/Calendar/ConfirmCalendarItemsAction.php:38–40` flips `is_available=false` and `status=BOOKED` together; `CreateOrderFromEnquiryAction.php:257,264` and `CreateDraftCalendarItemsAction.php:48–65` confirm the same pattern across all order entry points.

### The bug

`GetInstructorAvailabilityAnalyticsAction::__invoke` lines 26–42:

```php
$totalAvailable = $instructor->calendars()
    ->join('calendar_items', 'calendars.id', '=', 'calendar_items.calendar_id')
    ->where('calendar_items.is_available', true)        // counts FREE only — booked are is_available=false
    ->count();

$totalBooked = $instructor->calendars()
    ->join('calendar_items', 'calendars.id', '=', 'calendar_items.calendar_id')
    ->whereIn('calendar_items.status', [BOOKED, COMPLETED])
    ->count();

$utilizationRate = ($totalBooked / $totalAvailable) * 100;
```

The variable named `$totalAvailable` is in fact the count of **free** slots only — booked slots have `is_available=false` so they are excluded. With 7 booked + 3 free, this gives `7 / 3 = 233%`.

The existing test passes only because the test fixtures break the production invariant: it creates a `BOOKED` row with `is_available=true`, which never happens in the live code path. Production data → wrong number; test data → coincidentally right number.

### The fix

**Numerator** — keep as is: `count(status IN [BOOKED, COMPLETED])`. This is "what the instructor actually worked / will work."

**Denominator** — change to "total slots the instructor put up for booking":

```
total_slots = (free open slots) + (booked slots)
            = count(is_available = true)                        // free
            + count(status IN [BOOKED, COMPLETED])              // booked (currently is_available=false)
```

Equivalently, as a single query: `WHERE is_available = true OR status IN ('booked','completed')`. The two counts are guaranteed disjoint (a booked slot is never `is_available=true` in production), so addition and OR-counting produce the same number.

**Why exclude lunch/holiday blocks** (`is_available=false`, `status=null`)?  Because they are time the instructor *cannot* work — counting them in the denominator would punish instructors who block time off and pull utilisation toward 0%. The brief's "booked + available = total" framing matches this: only slots offered up for lessons go into the denominator.

**Why exclude DRAFT (held-during-checkout)?**  Same reason — these are transient holds during a payment flow, not slots the instructor is selling. They live `is_available=false` and convert to `BOOKED` on confirmation, at which point they enter the numerator and denominator naturally. (DRAFT and RESERVED are not counted in either today; we keep that.)

### Decisions

- [x] Compute denominator as `free_slots + booked_slots`, not `is_available=true` alone.
- [x] Numerator stays as `status IN [BOOKED, COMPLETED]`. RESERVED and DRAFT are excluded (they're transient).
- [x] Rename the per-instructor and summary fields for clarity:
  - `total_available` → **`total_slots`** (booked + free; the denominator)
  - `total_booked` stays
  - `total_free` stays (`= total_slots - total_booked`)
  - `utilization_rate` / `overall_utilization` stay
- [x] Update `Reports/Index.vue` to use the new `total_slots` field and relabel the summary card from "Total Available Slots" → "Total Slots" (and the table column "Available" → "Free", since the existing label was already misleading — it was free slots, not "available" in the colloquial sense).
- [x] Fix the existing summary card mislabelling: card #3 today is titled **"Unavailable"** but its value is `total_free` (free slots). That label was wrong before this task; we'll correct it to **"Free"** as part of the same change so the UI is self-consistent.
- [x] No new database query is needed — both counts already exist; only the arithmetic and field names change. Single Action, single round-trip.
- [x] **Do not touch the existing test file** (`tests/Feature/Reports/ReportIndexTest.php`) — per project rules and `feedback_tests.md`. Flag in Phase 3 that the user will need to update the test fixtures (use `is_available=false` for BOOKED/COMPLETED rows to match production) and the asserted numbers.
- [x] No DB migration. No `database-schema.md` change. No `api.md` change (this is a web-only Inertia route, not an API endpoint).
- [x] No new Service or Action — modify the existing one in place.

### Risks / edge cases

- **Per-instructor sort.** The Action sorts instructors by `utilization_rate` desc. With the corrected formula, the rate is always 0–100, so the sort behaves naturally. No change needed.
- **Zero-slot instructors.** Already guarded: `$totalAvailable > 0 ? ... : 0.0`. With the new denominator the guard becomes `$totalSlots > 0 ? ...`. Same shape; same safe default.
- **Frontend rename.** Three usages in the Vue file need updating: TS interface, summary cards, table column. Small, mechanical change. The progress-bar `Math.min(rate, 100)` clamp can stay as a defensive measure even though the new formula cannot exceed 100.
- **Existing test will fail.** Inevitable — the test asserts `total_available=3` and `utilization=66.7%` on a fixture that doesn't match production semantics. After this change the fixture will need `is_available=false` on the BOOKED/COMPLETED rows (matching production) and the assertions will become `total_slots=3, total_booked=2, total_free=1, utilization=66.7%`. **Flagging — not fixing — per project rules.**
- **Stale browser bundles.** The Vue file changes; user will need `npm run dev` or `npm run build` for the new labels to appear.

---

## Phase 2: Implementation ✅

### Steps

- [x] **Updated `app/Actions/Report/GetInstructorAvailabilityAnalyticsAction.php`:**
  - Renamed `$totalAvailable` → `$totalFree` (it was always counting free slots; the old name was misleading) and introduced `$totalSlots = $totalFree + $totalBooked` as the denominator.
  - Numerator unchanged: `count(status IN [BOOKED, COMPLETED])`.
  - Renamed the array key `total_available` → `total_slots` in both the per-instructor map and the summary block.
  - Updated `$utilizationRate = $totalSlots > 0 ? round(($totalBooked / $totalSlots) * 100, 1) : 0.0`.
  - Updated the array-shape PHPDoc on `__invoke` return type and added a docblock explaining the formula and what's excluded from the denominator (lunch/holiday blocks).
- [x] **Updated `resources/js/pages/Reports/Index.vue`:**
  - Renamed `total_available` → `total_slots` on both `InstructorAnalytics` and `AnalyticsSummary` interfaces.
  - Summary card #1: title "Total Available Slots" → "Total Slots", value bound to `total_slots`.
  - Summary card #3: title "Unavailable" → "Free" (correcting the pre-existing mislabelling). Value stays `total_free`.
  - Table headers: "Available" → "Total" (now shows `total_slots`), "Unavailable" → "Free" (still shows `total_free`). Row order is now `Instructor → Total → Booked → Free → Utilization → Performance` — consistent with the four summary cards above.

### Math sanity check

Brief's worked example: 7 booked + 3 free.
- `$totalFree = 3` (count where `is_available=true`)
- `$totalBooked = 7` (count where `status ∈ {BOOKED, COMPLETED}`)
- `$totalSlots = 10`
- `$utilizationRate = 7 / 10 × 100 = 70.0%` ✓

Edge cases:
- 0 booked, 5 free → 0% ✓
- 5 booked, 0 free → 100% ✓ (was 100% before the fix too — denominator coincidentally matched)
- 0 of each → 0% ✓ (guarded by `$totalSlots > 0`)
- Booked > total free (the bug case): now bounded to ≤ 100% ✓ (was returning 233%+ before)

### Files changed

| File | Change |
|------|--------|
| `app/Actions/Report/GetInstructorAvailabilityAnalyticsAction.php` | Fixed denominator (free + booked), renamed `total_available` → `total_slots`, added formula docblock |
| `resources/js/pages/Reports/Index.vue` | Updated TS interfaces, summary card labels, table headers, and bound `total_slots` to the new "Total" column |

### Out of scope

- Per-day, per-week, or per-date-range filtering of the report.
- Breakdown by lesson type (regular vs practical test).
- Updating `tests/Feature/Reports/ReportIndexTest.php` — flagged for the user; tests are user-maintained per project rules.
- Caching / performance work.

---

## Phase 3: Reflection ✅

### What went well

- **Root cause was a single-file fix.** All utilisation arithmetic lived in one Action. Two queries, four scalars, four return-array keys — small, contained change.
- **Diagnosed via the booking lifecycle, not the report code alone.** The math itself looked plausible in isolation; the bug only became obvious after tracing how `is_available` flips when an order confirms (`ConfirmCalendarItemsAction.php:38–40`). Reading downstream first saved guessing.
- **Caught a pre-existing UI mislabelling.** The summary card titled "Unavailable" was actually displaying free slots, and the table column "Available" was the same. Fixed both as part of the same change so the page is internally consistent — the summary cards and the table columns now use the same vocabulary (Total, Booked, Free, Utilization).
- **Field rename made the data shape self-documenting.** `total_available` was the source of the bug because the variable name lied — it counted free slots, but read as "all available capacity." `total_slots` says exactly what it is. Future readers won't need to re-derive the semantics.
- **No collateral damage.** The Action's three callers (`ReportService`, `ReportController`, `Reports/Index.vue`) are all in the changeset; no other consumer reads these keys (verified via grep on `total_available` before editing).

### Anti-pattern check

- ✅ No tests touched, no `vendor/bin/pint`, no `php artisan test` (per project rules).
- ✅ No new Service, no new Action — modified the existing Action in place.
- ✅ No DB migration, no `database-schema.md` update, no `api.md` update (web-only Inertia route).
- ✅ No new abstractions (no `UtilisationCalculator` class, no helper trait) — the logic is six lines and lives where it's used.
- ⚠️ **Existing test will fail.** `tests/Feature/Reports/ReportIndexTest.php:73-88` asserts `total_available` and `utilization_rate=66.7` on a fixture that creates BOOKED rows with `is_available=true` (which production never does). The user needs to:
  1. Rename `total_available` → `total_slots` in the assertions.
  2. Update fixtures to set `is_available=false` on the BOOKED and COMPLETED rows (so the fixture matches production semantics).
  3. With realistic fixtures (1 DRAFT free + 1 BOOKED + 1 COMPLETED + 1 unavailability block): `total_slots=3, total_booked=2, total_free=1, utilization=66.7%`. Same expected percentage, but for the right reason.
- ⚠️ **The Vue rename requires a fresh build.** User needs to run `npm run dev` or `npm run build` for the new field names and labels to appear in the browser.

### Technical debt / future considerations

- **Date-range filter.** Today the report aggregates over *all* calendar items the instructor has ever had — pre-pandemic lessons count the same as next week. A "last 30 days" / "this month" / "custom range" filter would make utilisation actionable. Separate task.
- **Per-instructor sort options.** Sorted by utilisation desc only. Free-text sort by name, total slots, or booked count would help admins triage. Cosmetic; separate task.
- **N+1 query shape.** `2N + 2` count queries (two per instructor + two summary aggregates). Fine at current scale; if instructor count grows past a few hundred this should become a single grouped aggregate over `calendar_items` joined to `calendars` joined to `instructors`. Not worth doing today.
- **Single-source-of-truth for the formula.** The denominator logic is now in one place (`GetInstructorAvailabilityAnalyticsAction`). If future code (e.g. a per-instructor dashboard widget, an API endpoint, a CSV export) needs the same metric, extracting a small helper would prevent drift. Not needed yet — first repeat triggers the extraction, not the second-guessing of "we might".

### Score

**9 / 10.** Loses a point because the fix exposes a stale test that I cannot update myself — a clean handoff would have updated the test in lockstep, but project rules require user-maintained tests. Otherwise: minimal additive change, single Action modified, denominator now matches the brief's worked example exactly, pre-existing UI mislabelling fixed in the same pass, no schema/API drift.

---

**Status:** All phases complete.
**Last Updated:** 2026-04-28

### Files changed

| File | Change |
|------|--------|
| `app/Actions/Report/GetInstructorAvailabilityAnalyticsAction.php` | Replace buggy denominator; rename `total_available` → `total_slots`; update PHPDoc shape |
| `resources/js/pages/Reports/Index.vue` | Rename interface field; relabel/add columns; fix pre-existing "Unavailable"/"Free" mislabel |

### Out of scope

- Per-day, per-week, or per-date-range filtering of the report. Today's report is "all time across all calendar items"; that doesn't change.
- A breakdown of utilisation by lesson type (regular vs practical test). Future feature.
- Updating `tests/Feature/Reports/ReportIndexTest.php` — flagged for the user; tests are user-maintained per project rules.
- Caching / performance work. The two `count()` queries per instructor stay; with N instructors that's `2N + 2` queries today and after the fix. If that becomes a hotspot, a single grouped aggregation is a separate task.

---

## Phase 3: Reflection ⏸️ Not Started

(To be filled in after implementation.)

---

**Status:** Phase 1 complete.
**Last Updated:** 2026-04-28
