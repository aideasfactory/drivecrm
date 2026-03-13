# Task: Update lesson start-time selection to support 15-minute increments

**Created:** 2026-03-13
**Last Updated:** 2026-03-13T17:40:00Z
**Status:** ✅ Complete

---

## Overview

### Goal
Update the lesson start-time selection from 30-minute to 15-minute increments across the scheduling UI.

### Context
- Tile ID: 019ce836-2973-7273-92e7-d6290f0fadec
- Repository: drivecrm
- Branch: feature/019ce836-2973-7273-92e7-d6290f0fadec-update-lesson-start-time-selection-to-support-15-minute-incr
- Priority: MEDIUM
- Customer: Drive

---

## PHASE 1: PLANNING
**Status:** ✅ Complete

### Tasks
- [x] Review current start-time implementation in ScheduleTab.vue
- [x] Review WeeklyCalendarGrid.vue snap/click logic
- [x] Review CalendarEventBlock.vue positioning (no changes needed)
- [x] Review backend validation (no changes needed)
- [x] Identify all files requiring changes

### Reflection
The backend already supports arbitrary H:i format times. The change is entirely frontend-focused: updating the dropdown step from 30→15 and updating the calendar grid drag snapping for consistency. CalendarEventBlock positioning math uses `(minutes / 30) * rowHeight` which naturally handles 15-min positions correctly.

---

## PHASE 2: IMPLEMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Update `startTimeOptions` in ScheduleTab.vue to use 15-min increments
- [x] Update `snapToStartOption` in ScheduleTab.vue to snap to 15-min
- [x] Update `SNAP_MINUTES` and `SNAP_PX` in WeeklyCalendarGrid.vue
- [x] Update comments referencing 30-min increments
- [x] Write Pest test for start-time validation with 15-min times

### Reflection
Changes were minimal and focused. The dropdown now generates 33 options (08:00–16:00 in 15-min steps) instead of 17 (30-min steps). Both create and edit forms benefit since they share the same `startTimeOptions` computed property. The drag-and-drop snap was halved from 40px to 20px to match 15-min granularity. Tests cover both Action-level and HTTP endpoint-level creation with 15-min start times.

---

## PHASE 3: FINAL REFLECTION & DOCUMENTATION
**Status:** ✅ Complete

### Files Changed
1. `resources/js/components/Instructors/Tabs/ScheduleTab.vue` — Dropdown 30→15 min, snap function 30→15 min
2. `resources/js/components/Instructors/Tabs/Schedule/WeeklyCalendarGrid.vue` — SNAP_MINUTES 30→15, SNAP_PX 40→20
3. `tests/Feature/CalendarItem15MinuteStartTimeTest.php` — New test file (5 tests)

### No changes needed
- Backend validation (already accepts H:i format)
- CalendarEventBlock.vue (positioning math handles any minute value)
- Models/migrations (time columns are flexible)

### Summary
Updated the lesson scheduling start-time dropdown from 30-minute to 15-minute increments, providing more flexible time selection. Updated the calendar grid drag-and-drop to also snap at 15-minute intervals for consistency. Added 5 Pest tests covering 15-minute start times via both the Action layer and the HTTP endpoint.

### Score: 8/10
Simple, focused changes with no unnecessary complexity. No anti-patterns introduced. The only potential consideration is that the visual calendar grid still renders 30-min rows — events at :15/:45 will position correctly between grid lines but the grid lines themselves remain at :00/:30 boundaries. This is a reasonable UX tradeoff since doubling grid lines would make the calendar overly busy.
