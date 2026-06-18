# Task: Fix Timetable Y-Axis Slot Alignment

## Overview
Update the refund policy text in the onboarding flow sidebar from "24 hours before" to "48 hours before" so the wording matches the latest policy.

Branch: `feature/019ed532-0578-70ac-af88-70cc7ba3bf5f-fix-timetable-y-axis-slot-alignment`

The weekly calendar grid (Schedule tab on the Instructor page) currently
renders Y-axis hour labels (e.g. `10:00`) **on the boundary between two
30-min cells**. Because the label visually sits at the bottom edge of the
9:30–10:00 cell, instructors mis-click that cell believing it is the
10:00 cell — and end up with a 09:30 start time instead of 10:00.

Two underlying bugs in `WeeklyCalendarGrid.vue`:

1. Hour labels use `top-0 -translate-y-1/2`, which floats them on the row
   border instead of inside the cell that actually starts at that time.
2. The dashed/solid border alternation is **inverted** — the half-hour
   marks render solid and the hour marks render dashed, the opposite of
   the expected calendar convention.

Goal: each hour label visibly sits inside the cell it represents (e.g.
`10:00` inside the 10:00–10:30 cell), and the hour grid lines are solid
while half-hour lines are dashed. The click-handler maths is already
correct — the fix is purely visual alignment.

## Phase 1: Planning ✅

- [x] Reproduce: read `WeeklyCalendarGrid.vue`, trace label position and
      slot-click handler.
- [x] Confirm root cause:
      - `timeLabels[i]` is shown for even `i` (hour marks)
      - `<span class="absolute right-2 top-0 -translate-y-1/2 ...">`
        shifts the label up by half its height — onto the border between
        the previous half-hour cell and this one
      - `(slotIdx - 1) % 2 === 0 ? '' : 'border-dashed'` makes the FIRST
        cell (9:00–9:30) end in a solid border at 9:30 and the second
        cell (9:30–10:00) end in a dashed border at 10:00 — inverted
- [x] Confirm `handleSlotClick(...)` already maps slotIndex → correct
      start time, so no JS logic change is needed.
- [x] Decide on visual fix:
      - Move label INSIDE its hour cell at the top (drop the negative
        translate). `10:00` will sit at the top edge of the 10:00–10:30
        cell instead of floating between cells.
      - Flip the dashed/solid border so hour marks are solid and
        half-hour marks are dashed in BOTH the gutter and day columns.

## Phase 2: Implementation ✅

- [x] Update time-gutter label positioning in
      `resources/js/components/Instructors/Tabs/Schedule/WeeklyCalendarGrid.vue`
      to anchor the label inside the cell.
      Replaced `top-0 -translate-y-1/2 bg-background` with `top-1` so the
      hour label renders inside its own cell at the top, not floating on
      the border between two cells.
- [x] Apply alternating dashed/solid border to the gutter rows so the
      label sits below a clear hour line. Gutter rows now use
      `border-dashed` on odd indexes (half-hour rows) to match the day
      columns.
- [x] Flip the dashed/solid logic on day-column slot rows so hour
      boundaries render solid and half-hour boundaries render dashed.
      Was `(slotIdx - 1) % 2 === 0 ? '' : 'border-dashed'` → now
      `(slotIdx - 1) % 2 === 0 ? 'border-dashed' : ''`.
- [x] Verify no other component (e.g. MonthlyCalendarGrid) is affected.
      Monthly grid is a day-grid with no Y-axis time labels; weekly grid
      is the only affected component.

### Reflection — Phase 2

- The click-handler maths (`handleSlotClick`) was already correct — the
  bug was purely visual mis-alignment. Touching the maths would have
  re-broken legitimate clicks; flipping only the visual layout keeps the
  behaviour stable for events that load with existing times.
- Events drawn via `CalendarEventBlock` are positioned in pixels from
  the top of the day column. The grid row Y-coordinates are unchanged,
  so events still land at the right vertical offset; only the gutter
  label and the border style change.
- The `bg-background` class was dropped from the label span. It was
  there to mask the underlying row border that the label crossed when
  it was floating with `-translate-y-1/2`. Now the label sits inside
  the cell with no border behind it, so the mask is unnecessary.

## Phase 1: Planning ✅
- [x] Locate every occurrence of the refund policy copy in the onboarding flow
- [x] Confirm scope: only refund-policy sidebar copy (not unrelated "24 hours" usages, e.g., invoice timing on Step6)
- [x] Identify exact files and line numbers to change

- [x] Summarise what changed and why (in `results.md`).
- [x] Note the confidence score (9/10) and the caveat that the exact
      `top-1` inset is a sensible default but might want a small visual
      nudge once viewed on a real browser.

### Reflection — Phase 3

- The single biggest risk in this kind of UI fix is accidentally
  breaking the maths that converts a click into a start time, which
  would silently mis-book lessons. The fix is therefore intentionally
  layer-only — only the rendered position of the label and the
  border-style alternation change. The click handler, the events
  overlay, and the drag-and-drop snap behaviour are untouched.
- The dashed/solid border alternation was added to the gutter to keep
  it visually in step with the day columns. Without that, the gutter
  would have shown solid borders at every 30-min row while the day
  columns alternated, producing a visible mis-match across the row.
- `bg-background` was removed from the label span. It was a paint-over
  to hide the underlying border line when the label was floating with
  `-translate-y-1/2`. Now the label sits inside a cell with no border
  behind it, so no mask is needed.

## Status

- Last Updated: 2026-06-17
- Phase: 3 (Reflection) — complete. All phases done.


## Phase 3: Reflection ⏸️

- [ ] Summarise what changed and why.
- [ ] Note any subtle decisions (e.g. label `top` offset choice).
- [ ] Produce `results.md` for the client with a confidence score.

## Status (initial)

- Phase: 1 (Planning) — complete; moved directly into Phase 2.
