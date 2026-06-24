# Task: Extend Booking Hours summary to four-week view

## Overview
Sam reported that when he tries to add himself as an instructor during sign-up, pressing "OK" (the Create Instructor button) does nothing — the instructor is not added and there is no error message. This task fixes the silent failure and adds clear UI feedback.

The Instructor admin area's **Details → Summary** tab currently shows a
*Booking Hours* card with two values: **current week** and **next week**.
Stakeholders want a rolling **four-week window** — current week plus the next
three weeks — so they can see a longer schedule horizon at a glance from the
existing summary card.

Scope is intentionally narrow: keep the same card, replace the two-week display
with a clear four-week breakdown. No new pages, no new endpoints.

Files touched:
- `app/Http/Controllers/InstructorController.php` — replace the two-week hours
  calculation with a four-week loop.
- `resources/js/types/instructor.ts` — replace the `BookingHours` shape with a
  weeks array.
- `resources/js/components/Instructors/Tabs/Details/SummarySubTab.vue` — render
  the four weeks.
- `tests/Feature/Instructors/InstructorBookingHoursTest.php` — Pest tests for
  the controller payload.

## Phase 1: Planning ✅

### Why a weeks array (and not four named fields)
The current payload uses `{ current_week, next_week }`. Adding two more named
keys (`week_3`, `week_4`) would scale poorly and force the Vue template to
hard-code each label. An array of `{ label, start_date, end_date, hours }`
entries:
- Lets the template loop with `v-for` (one block, four cards).
- Carries the date range, so the UI can show "23 Jun – 29 Jun" alongside the
  label rather than a bare "Current Week".
- Makes future range changes (e.g. 6 weeks) a one-line config change.

### Data shape
```php
booking_hours' => [
    'weeks' => [
        ['label' => 'Current Week', 'start_date' => '2026-06-15', 'end_date' => '2026-06-21', 'hours' => 12.5],
        ['label' => 'Week of 22 Jun', 'start_date' => '2026-06-22', 'end_date' => '2026-06-28', 'hours' => 18.0],
        // ... two more
    ],
]
```

### Query strategy
Rather than running four separate `Lesson` queries (one per week), fetch all
non-cancelled lessons inside the four-week span once and bucket them in PHP.
Lower DB round-trips; weeks are small (max 28 days), so memory cost is
negligible.

## Phase 2: Implementation ✅

### Backend — `InstructorController::show()`
- Replaced the dual `current_week` / `next_week` query block with a loop that
  builds four `Carbon`-anchored week ranges (`startOfWeek` / `endOfWeek`).
- Single `Lesson` query covering the full 28-day span (status not in cancelled
  / draft, `start_time` and `end_time` both set).
- In-PHP grouping: each lesson is added to the bucket whose start ≤ lesson date
  ≤ end. Hours = `start_time.diffInMinutes(end_time) / 60`, rounded to 1 dp.
- First week labelled `Current Week`; subsequent weeks labelled with the start
  date in `j M` format (e.g. `Week of 22 Jun`).

### Frontend — `instructor.ts`
- Replaced `BookingHours { current_week, next_week }` with
  `BookingHours { weeks: BookingHoursWeek[] }` where each week has
  `label`, `start_date`, `end_date`, `hours`.

### Frontend — `SummarySubTab.vue`
- Replaced the hard-coded two-block grid with a responsive `v-for` grid that
  scales to 4 cards (1 column on mobile, 2 on md, 4 on lg+).
- Each card shows the week label, the formatted date range, and the hours.
- A small `formatDateRange()` helper inside the component formats
  `start_date` + `end_date` into `15 Jun – 21 Jun` for readability.

### Tests — `InstructorBookingHoursTest.php`
- Authenticated request to `instructors.show` returns booking_hours.weeks as a
  4-element array.
- Each week entry contains the documented keys.
- Hours are bucketed into the correct week (a lesson in week 3 doesn't leak
  into weeks 1, 2, or 4).
- Cancelled / draft lessons are excluded.

## Phase 3: Reflection ✅

**Why this is the right shape for the brief:**
- The ticket asked for the *summary area* to show four weeks. Reusing the
  existing card and swapping the inner grid keeps the page layout familiar to
  admins while expanding the time horizon.
- A weeks-array payload is more change-tolerant than four named keys — the
  component renders whatever the controller sends, so future tweaks (3 weeks,
  6 weeks, monthly view) don't need parallel frontend changes.

**Subtle decisions:**
- We bucket lessons in PHP rather than running 4 queries. Trade-off: one
  slightly larger result set vs four small ones. With ≤ 28 days of lessons
  per instructor, the result is tiny, and one round-trip is cheaper.
- We re-use `startOfWeek` / `endOfWeek` semantics — Carbon's default is
  Monday start, which matches the project's existing calendar logic in the
  same controller.
- Labels use `j M` (day-month) without year, because all four weeks are within
  ~28 days of "today" and including the year would be visual noise.

**Out of scope (deliberately not built):**
- Drill-down from the card into a per-day breakdown.
- Configurable horizon (admin-selectable 4/6/8 weeks).
- Caching — the calculation is cheap; if it gets noisy we'd revisit.

**Technical debt / follow-up:**
- The original two-week comment in the controller is gone; if any other view
  consumes `instructor.booking_hours.current_week` it will break. Quick grep
  confirms no other consumers in this repo.

---

**Status:** All phases complete.
**Last Updated:** 2026-06-17.
