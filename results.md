# Results — Timetable Y-Axis Slot Alignment Fix

## What was the problem?

On the **Instructor → Schedule** weekly view, the time labels on the
left-hand side of the calendar (09:00, 10:00, 11:00, etc.) were drawn
**on the dividing line between two 30-minute cells** instead of inside
the cell they belonged to.

That made it look as if the cell **above** the "10:00" label was the
10:00 cell — but clicking it actually created a slot starting at
**09:30**. This is the kind of mis-click that quietly leads to
mis-booked lessons and wasted instructor time chasing them up.

To compound the visual confusion, the alternating border styling
("solid for hours, dashed for half-hours" — the standard calendar
convention) was inverted. Hour marks were rendering dashed and
half-hour marks were rendering solid, so the eye had no consistent cue
about where one hour ended and the next began.

## What changed?

A single Vue component was edited:

- `resources/js/components/Instructors/Tabs/Schedule/WeeklyCalendarGrid.vue`

Two visual changes inside that component:

1. **Hour labels now sit inside the correct cell.**
   The "10:00" label is now drawn at the top of the 10:00–10:30 cell,
   not floating on its top border. The cell you see the label inside is
   the cell that maps to 10:00–12:00 when you click it.

2. **Hour vs. half-hour gridlines are flipped to match calendar
   convention.**
   Hour boundaries (the line at the top of every "00" cell) now render
   as solid lines. Half-hour boundaries (the line at the top of every
   "30" cell) render as dashed lines. This works the same in both the
   left-hand time gutter and the seven day columns, so the whole grid
   reads consistently top-to-bottom.

The click handler that converts a clicked cell into a start time was
**not** touched. The maths there was already correct — the only bug
was visual. Leaving the click handler alone means lessons, availability
slots, and practical-test blocks created before today still display at
exactly the right Y-position.

## What does it look like now?

For the 10:00 hour mark, for example:

```
─────────────  ← solid 10:00 hour line
 10:00         ← label sits INSIDE the 10:00–10:30 cell
              ← clicking here = 10:00 start time (correct)
─ ─ ─ ─ ─ ─    ← dashed 10:30 half-hour line
              ← clicking here = 10:30 start time
─────────────  ← solid 11:00 hour line
 11:00
```

Before, the "10:00" label was floating on the boundary line itself,
making the 09:30 cell look like the 10:00 cell.

## What is unaffected?

- Existing calendar events (lessons, slots, travel-time blocks,
  practical tests) keep their stored times and continue to render at
  the same vertical pixel offset — no data migration, no behavioural
  change.
- The monthly view, the booking flow, and the start-time dropdown in
  the create/edit sheets were untouched. Only the weekly grid changes.
- Drag-and-drop and the click-to-create flow still snap to 15-minute
  increments exactly as before.

## Files changed

- `resources/js/components/Instructors/Tabs/Schedule/WeeklyCalendarGrid.vue`
- `.claude/tasks/current-task.md` (process file — task tracking)

## Confidence

**Confidence score: 9 / 10**

Why a 9 and not a 10:

- The change is small, surgical, and contained to the visual layer of
  one component. The click-to-time maths is untouched, so no risk of
  rescheduling existing entries.
- The fix has been read end-to-end against the click handler, the
  event-positioning component (`CalendarEventBlock.vue`), and the
  monthly grid to confirm no cross-component fallout.
- The half-point is deducted because the change is purely visual and
  has not been pixel-checked in a browser in this environment — the
  exact `top-1` (4px) inset for the label inside the cell is a
  reasonable default, but you may want to nudge it (e.g. `top-0.5` or
  `-top-2` to overlap the hour line) once you see it on a real screen.
  The behavioural correctness — clicking the cell with the "10:00"
  label gives 10:00 — is independent of that tweak.
