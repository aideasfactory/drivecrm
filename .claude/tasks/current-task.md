# Task: Extend Diary View to Midnight-to-Midnight

**Created:** 2026-06-17
**Last Updated:** 2026-06-17
**Status:** Planning
**Branch:** feature/019ed525-6a18-727c-bdc7-0d282ae0febb-extend-diary-view-to-midnight-to-midnight

---

## Overview
Update the refund policy text in the onboarding flow sidebar from "24 hours before" to "48 hours before" so the wording matches the latest policy.

### Goal
Widen the instructor diary / weekly-calendar grid from the current **06:00–21:00** window to a full **midnight-to-midnight (00:00–24:00)** day. Users will be able to view, click into, and book lessons across the full 24-hour day. The grid will be ~60% taller; scrolling is the accepted trade-off.

### Success criteria
- [ ] Weekly calendar grid renders 48 half-hour rows covering 00:00–24:00.
- [ ] Clicking any empty slot opens the "Add Time Slot" sheet pre-filled with that time.
- [ ] Start-time dropdown offers every 15-min increment from 00:00 up to the latest valid start for a 2-hour lesson.
- [ ] Drag-and-drop clamps to the new bounds.
- [ ] Backend form requests (web Store, web Update, mobile API Store) validate start ≥ 00:00 and end ≤ 23:59.
- [ ] Existing rows remain valid (old window is a subset of the new window — guaranteed).
- [ ] No schema migration needed.

### Context
- Two-place single-source-of-truth (set up in 2026-04-27 task): `resources/js/lib/diary-hours.ts` (frontend) + `config/diary.php` (backend). Both must stay in sync.
- The grid math relies on `minutesToTime` wrapping at 24:00 via `% 24`, which means a HH:MM end_time of "24:00" cannot be stored. Latest practical end for a 2-hour lesson is therefore **23:45** (start 21:45). The full 00:00–24:00 row range still renders; the last 15 minutes of the day are visible but un-bookable as the *start* of a new 2-hour slot.

---

## PHASE 1: PLANNING ✅

### Where the constraint lives (inventory)

| File | What constrains the hours |
|------|---------------------------|
| `resources/js/lib/diary-hours.ts` | `DIARY_START_HOUR`, `DIARY_END_HOUR`, `DIARY_START_MINUTES`, `DIARY_END_MINUTES` — frontend single source |
| `resources/js/components/Instructors/Tabs/Schedule/WeeklyCalendarGrid.vue` | `DAY_START_HOUR` / `DAY_END_HOUR` (rebound from import), `SLOT_COUNT`, `timeLabels`, `handleSlotClick` clamp, drag boundary clamp in `handlePointerUp` |
| `resources/js/components/Instructors/Tabs/ScheduleTab.vue` | `startTimeOptions`, `snapToStartOption`, `MAX_START_HOUR` |
| `config/diary.php` | `start_hour`, `end_hour`, `start_time`, `end_time` — backend single source |
| `app/Http/Requests/StoreCalendarItemRequest.php` | `after_or_equal` / `before_or_equal` time rules |
| `app/Http/Requests/UpdateCalendarItemRequest.php` | Same |
| `app/Http/Requests/Api/V1/StoreCalendarItemRequest.php` | Same |

### Decisions

- **Visible range = 00:00–24:00**, but the *bookable* upper bound for end_time is **23:59** (HH:MM has no representation for 24:00, so a 2-hour lesson can't naturally end at midnight). Add a derived helper `DIARY_MAX_END_MINUTES` (= 1425 = 23:45) for the bookable clamp; keep `DIARY_END_MINUTES` (= 1440) for the visible-grid math.
- **Backend `config('diary.end_time') = '23:59'`** — this is the strictest representable upper bound. `'24:00'` cannot be parsed by Carbon for `before_or_equal`.
- **Monthly view's `handleDayClick` default** remains `'08:00'` — a sensible "office hours" default when the user clicks a day in the month view without specifying a time. Could be revisited but is out of scope.
- **Practical tests** continue to share the regular-lesson start picker. Their effective blocked window (prep − 60min, buffer + 30min) can now legitimately straddle midnight from either side. The existing `Carbon::parse(...)->subMinutes(60)` overlap math wraps to the previous day, but the SQL overlap check uses `TIME(...)` which is time-only — this can produce false negatives for cross-midnight practical-test buffers. **Out of scope** for this task; the user has not asked for cross-midnight test handling.
- **No DB schema change.** `calendar_items.start_time` / `end_time` are TIME columns with no CHECK constraints. Application-level validation continues to be the source of truth.
- **No documentation update** for `database-schema.md` (no schema change).
- **`api.md`** updated for `POST /api/v1/instructor/calendar/items` to reflect new bounds (00:00–23:59) + changelog entry.

### Risks

- **Grid is ~60% taller.** Previously 30 rows × 40px = 1200px. Now 48 rows × 40px = 1920px. Scrolling already exists; users will scroll more. User has explicitly accepted this in the brief.
- **The last 15 minutes of the day (23:45 → 00:00) cannot be selected as a *start* of a regular 2-hour slot.** This is a soft constraint imposed by HH:MM format + 2-hour fixed duration; the visible grid still shows that strip and lets users see lessons that end at 23:45.
- **`%24` wrap in `minutesToTime`.** If we accidentally allow a start that produces end ≥ 24:00, the auto-calc end_time wraps to 00:00 and validation fails. Defended against by `DIARY_MAX_END_MINUTES` clamp in both the picker and the slot-click handler.

---

## PHASE 2: IMPLEMENTATION ✅

### Steps

- [x] Update `resources/js/lib/diary-hours.ts` to `DIARY_START_HOUR=0`, `DIARY_END_HOUR=24`, add `DIARY_MAX_END_MINUTES` helper for the wrap-safe bookable upper bound.
- [x] Update `resources/js/components/Instructors/Tabs/Schedule/WeeklyCalendarGrid.vue`:
  - Import `DIARY_MAX_END_MINUTES`.
  - Use it in `handleSlotClick` clamp (instead of `(DAY_END_HOUR - SLOT_DURATION_HOURS) * 60`).
  - Use it as the drag boundary upper bound (instead of `DAY_END_HOUR * 60`).
- [x] Update `resources/js/components/Instructors/Tabs/ScheduleTab.vue`:
  - Import `DIARY_MAX_END_MINUTES`.
  - Rewrite `startTimeOptions` to iterate by minutes from `DIARY_START_MINUTES` to `DIARY_MAX_END_MINUTES − SLOT_DURATION_HOURS*60` in 15-min increments.
  - Update `snapToStartOption` to clamp using `DIARY_MAX_END_MINUTES − SLOT_DURATION_HOURS*60`.
- [x] Update `config/diary.php` to `start_hour=0`, `end_hour=24`, `start_time='00:00'`, `end_time='23:59'`.
- [x] No changes needed to the three Form Requests — they already read from `config('diary.*')`.
- [x] Update `.claude/api.md` with new bounds + changelog entry.

### Files changed

| File | Change |
|------|--------|
| `resources/js/lib/diary-hours.ts` | Widen bounds to 00:00–24:00; add `DIARY_MAX_END_MINUTES` helper |
| `resources/js/components/Instructors/Tabs/Schedule/WeeklyCalendarGrid.vue` | Use new helper for click + drag clamps |
| `resources/js/components/Instructors/Tabs/ScheduleTab.vue` | Use new helper for picker + snap |
| `config/diary.php` | Widen bounds |
| `.claude/api.md` | Reflect new validation bounds + changelog entry |

### Out of scope

- Cross-midnight practical test buffer handling (existing edge case).
- Per-instructor working hours.
- DB CHECK constraints.
- Changing the 2-hour lesson / 1-hour practical-test fixed durations.
- Allowing end_time = exactly midnight (would need wider rework of the HH:MM convention).

---

## PHASE 3: REFLECTION ✅

### What went well

- **Single source of truth held up.** The 2026-04-27 task put hours in `diary-hours.ts` and `config/diary.php`; widening the window touched the bounds in those files plus three downstream consumers (two Vue files use the new `DIARY_MAX_END_MINUTES` helper, three PHP form requests need no change at all because they read from config). No new scattered hardcodes.
- **Backend form requests untouched.** Because they already read from `config('diary.start_time')` / `config('diary.end_time')`, simply changing `config/diary.php` propagates to Store, Update, and API v1 Store request validation.
- **Existing data guaranteed safe.** Old window (06:00–21:00) is a strict subset of the new window (00:00–24:00) — no existing row can become invalid.
- **Visible-vs-bookable split.** Introducing `DIARY_MAX_END_MINUTES = 1425` (23:45) as a wrap-safe bookable upper bound lets the grid render the full 24 hours while keeping the 2-hour-lesson math from wrapping at 24:00.

### Anti-pattern check

- No tests touched (project rule).
- No `pint`, no `php artisan test` (project rules).
- No `database-schema.md` change (no schema change).
- `api.md` updated atomically with the bound widening.

### Technical debt / future considerations

- **Cross-midnight practical-test buffer.** `checkForOverlap` in the three Form Requests parses `Carbon::parse($startTime)->subMinutes(60)` for practical-test prep, which wraps to the previous day's time and is then compared with SQL `TIME(...)` — a same-day-only comparison. With 24-hour bookings, a 00:00 practical test will not correctly detect overlap with items at 23:00–23:30 the same day (a false negative). Pre-existing logic; surfaced more by the wider window. Out of scope for this brief.
- **23:45 → midnight strip is visible but un-bookable as a regular-lesson start.** Acceptable inherent limitation of HH:MM + 2-hour lessons. Could be solved by representing end_time as the next-day midnight in a richer format, but that's a much larger change.
- **Grid is 1920px tall.** Scroll-heavy on smaller monitors. Could add a "collapse empty hours" toggle later, but the user explicitly accepted scrolling.
- **TS↔PHP sync remains manual.** Both files comment that they mirror each other. Codegen would be safer but unjustified for two integers that change every six months.

### Score

**9 / 10.** Loses one point for the inherent 23:45 / midnight ceiling (a 2-hour lesson cannot end exactly at midnight). Otherwise: minimal, additive change, single-source-of-truth holds, three form requests stay synchronised through config, existing data guaranteed safe, no schema migration, mobile API and admin web stay in lockstep.

---

**Status:** All phases complete.
**Last Updated:** 2026-06-17.
