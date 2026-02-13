# Task: Fix Calendar Bugs + 2-Hour Time Slot Changes

**Created:** 2026-02-13
**Last Updated:** 2026-02-13
**Status:** 🔄 Phase 2 Complete - Awaiting Review

---

## Overview

### Goal
1. Fix two bugs (events stuck at bottom, drag validation errors) caused by model datetime cast
2. Change calendar to 8am–6pm range
3. Use 2-hour fixed time slots with dropdown selection
4. Auto-calculate end time from start time

---

## Phase 1: Planning ✅

### Tasks
- [x] Diagnose root cause of both bugs (datetime cast in CalendarItem model)
- [x] Plan 2-hour slot changes
- [x] Create task file

---

## Phase 2: Implementation ✅

### Tasks
- [x] Remove `datetime:H:i` cast from `CalendarItem` model (bug fix)
- [x] Change `DAY_START_HOUR` 6→8, `DAY_END_HOUR` 22→18
- [x] Add 2-hour drag snap (`SNAP_PX`) and slot click snap to even hours
- [x] Replace time `<Input>` with `<select>` dropdown (08:00, 10:00, 12:00, 14:00, 16:00)
- [x] Add auto end time calculation (start + 2 hours) with watchers
- [x] Show end time as read-only display in both create/edit forms
- [x] Update drag-and-drop to use fixed 2-hour duration

### Files Changed
- `app/Models/CalendarItem.php` — Removed `datetime:H:i` cast for `start_time`/`end_time`
- `resources/js/components/Instructors/Tabs/Schedule/WeeklyCalendarGrid.vue` — Hours 8–18, 2-hour snap
- `resources/js/components/Instructors/Tabs/ScheduleTab.vue` — Dropdown start time, auto end time

---

## Phase 3: Testing & Review ⏸️

### Test Scenarios
- [ ] Events display at correct time positions (not stuck at bottom)
- [ ] Events show proper "HH:MM - HH:MM" time labels (not "2026-")
- [ ] Calendar shows 8am–6pm range
- [ ] Click empty slot → sheet opens with start time dropdown pre-selected
- [ ] Changing start time dropdown → end time auto-updates (+2 hours)
- [ ] Drag-and-drop snaps to 2-hour boundaries
- [ ] Drag-and-drop succeeds without validation errors
- [ ] Create/edit/delete time slots all work correctly
- [ ] Week navigation loads events correctly
