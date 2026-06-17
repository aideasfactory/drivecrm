# Booking Hours summary — four-week view

## What changed

The **Booking Hours** card on the instructor admin
*Details → Summary* tab now shows a rolling four-week view instead of just
two weeks. From any day, the card displays the instructor's booked hours
for:

1. **Current Week** — the week you're in right now (Monday → Sunday)
2. **Week of [date]** — the following week
3. **Week of [date]** — two weeks out
4. **Week of [date]** — three weeks out

Each block shows the week's label, the date range (e.g. *15 Jun – 21 Jun*),
and the total booked hours rounded to one decimal place.

## Where to find it

`Admin → Instructors → [select instructor] → Details tab → Summary sub-tab`

The Booking Hours card is the same card that used to show *Current Week* and
*Next Week*.

## How hours are counted

A lesson contributes its full duration (start time → end time) to whichever
week it falls in. Lessons that are **cancelled** or still in **draft** state
are excluded — the figure reflects bookings that should actually run.

The card is calculated server-side each time the page is loaded, so it is
always live.

## Display behaviour

- **Wide screens (lg+):** all four weeks shown side-by-side in a single row.
- **Tablets (md):** two columns, two rows.
- **Phones:** stacked vertically.

This matches the responsive layout used elsewhere in the instructor admin.

## What was *not* changed

- The rest of the Summary tab (current pupils, passed pupils, archived,
  waiting list cards) is untouched.
- No new admin actions, no new API endpoints, no schema changes.
- The week boundary remains Monday → Sunday, the project's existing standard.

## Files touched

- `app/Http/Controllers/InstructorController.php` — replaced the two-week
  hours calculation with a four-week loop using a single lessons query.
- `resources/js/types/instructor.ts` — new `BookingHoursWeek` shape.
- `resources/js/components/Instructors/Tabs/Details/SummarySubTab.vue` —
  responsive four-card layout with date-range labels.
- `tests/Feature/Instructors/InstructorBookingHoursTest.php` — Pest coverage
  for the new payload (four-week shape, hour bucketing, exclusions).

## Confidence score

**9 / 10**

Why not 10:
- The Pest tests were written following the existing project patterns and
  inertia-laravel's `assertInertia` helpers, but per the project's standing
  rule the developer agent does not run tests locally — the test suite has
  not been executed in this environment.
- Production has no automated visual regression tests, so the responsive
  layout (1/2/4 columns) is verified by inspection of the Tailwind classes
  against the rest of the codebase, not by a live render.

Why 9:
- The change is localised to a single card on a single tab.
- The data path was previously already in this controller (two-week
  variant), so there are no new dependencies or surface area.
- The original two-week field shape was searched for across the codebase
  before being replaced — no other consumer uses `current_week` /
  `next_week`.
- The new payload is a generic weeks array, so future range adjustments
  (e.g. extending to 6 weeks) are a one-line backend change with no
  frontend follow-up.
