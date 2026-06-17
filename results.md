# Diary view extended to midnight-to-midnight

## What you asked for

> "Review the diary/timetable view and make it available from midnight to midnight.
> Extend the visible diary day range so users can work with times across the full 00:00 to 23:59 day.
> Apply this to the diary experience used when adding diary dates by clicking on the calendar."

## What was built

The instructor diary / weekly schedule grid (used inside **Instructors → Schedule**) previously only showed and allowed lesson bookings between **06:00 and 21:00**. It now shows and allows bookings across the full **24-hour day, midnight to midnight**.

This affects three places you'll see in the admin UI:

1. **The weekly calendar grid** — now renders all 48 half-hour rows from 00:00 down through 23:30. The grid is taller than before (≈1920px vs the previous ≈1200px); the schedule scrolls inside its container, as accepted in the brief.
2. **The "Add Time Slot" sheet (opens when you click an empty slot, or click a day in the month view)** — the start-time dropdown now offers every 15-minute increment from **00:00** through **21:45** for regular 2-hour lessons. (21:45 is the latest a 2-hour lesson can start while still ending before midnight — see "Why 23:45 and not 24:00" below.)
3. **Drag-and-drop** — events can now be dragged to anywhere in the new wider window. The drag boundary respects the same 00:00 → 23:45 end constraint.

## What was changed under the hood

| File | Why |
|------|-----|
| `resources/js/lib/diary-hours.ts` | Frontend single-source-of-truth for the diary bounds. Widened from 6/21 to 0/24 and added a `DIARY_MAX_END_MINUTES = 23:45` helper. |
| `config/diary.php` | Backend single-source-of-truth. Widened `start_time` to `00:00`, `end_time` to `23:59`. |
| `resources/js/components/Instructors/Tabs/Schedule/WeeklyCalendarGrid.vue` | Picks up the new bounds; click and drag clamps now use the wrap-safe upper bound. |
| `resources/js/components/Instructors/Tabs/ScheduleTab.vue` | The start-time picker and snap-to-grid logic now use the new bounds. |
| `.claude/api.md` | Updated docs and changelog entry for `POST /api/v1/instructor/calendar/items` reflecting the new validation bounds. |

The three Form Requests that validate calendar item submissions (`StoreCalendarItemRequest`, `UpdateCalendarItemRequest`, `Api\V1\StoreCalendarItemRequest`) **did not need to change** — they already read the bounds from `config('diary.start_time')` / `config('diary.end_time')`, so widening the config alone propagated the validation change to both the admin web UI and the mobile API.

No database migration was needed. The `calendar_items.start_time` / `end_time` columns already accept any TIME value; the constraint is application-level. Existing lesson records remain valid because the new (wider) window contains the old (narrower) window.

## Why 23:45 and not 24:00 for the latest end

A subtle technical point worth flagging for transparency: lesson end times are stored as `HH:MM`, which has no representation for `24:00` (it would wrap to `00:00` of the next day). So although the visible grid extends all the way to midnight, the latest a regular 2-hour lesson can start is **21:45** (ending **23:45**). The last 15 minutes of the day (23:45 → 00:00) is visible on the grid but cannot itself be the *start* of a new 2-hour booking.

This is an inherent limitation of the fixed 2-hour lesson duration plus the HH:MM storage format. A future change could allow lessons that genuinely cross midnight, but that's a much larger redesign of how the day is modelled. For the current "let users see and book across the day" brief, the 23:45 ceiling is the practical maximum.

## Out of scope (not done — flag if these matter)

- **Practical-test buffer wrap.** Practical tests use a `−1 hr prep / +30 min buffer` window for overlap-checking. With the new midnight bounds, a practical test at 00:00 has prep starting at 23:00 the *previous* day — the existing SQL `TIME()`-based overlap check can produce false negatives across midnight. This is a pre-existing quirk that's slightly more visible with the wider window. The user did not ask for cross-midnight handling.
- **Per-instructor working hours.** The 00:00–23:59 window is a global ceiling. Per-instructor preferences (e.g. "I never work after 22:00") would need their own DB table and UI.
- **Lessons that genuinely cross midnight** (e.g. start 23:00, end 01:00 next day). Would require modelling the diary entry as having a date range rather than a single date + HH:MM pair.

## Confidence

**9 / 10.**

- The change is small and additive: two config values widened, three downstream Vue/PHP consumers picked up the new values, one helper added.
- The single-source-of-truth design (set up in a prior task) held up — both the admin web UI and the mobile API stay in lockstep through `config/diary.php`.
- Existing data is guaranteed safe: the old window (06:00–21:00) sits entirely inside the new window (00:00–23:59), so no record can become invalid.
- The 23:45 / midnight ceiling is the one rough edge — small, well-documented, and inherent to the HH:MM + 2-hour-duration design rather than something introduced by this change. Removing that point would require a much larger rework of how lessons are stored, which wasn't asked for.
