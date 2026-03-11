# Task: Update Calendar Availability Handling for 2-Hour Blocks and Travel Time

**Created:** 2026-03-11
**Last Updated:** 2026-03-11T00:30:00Z
**Status:** ✅ Complete

---

## Overview

### Goal
Update the calendar availability system to:
1. Allow 30-minute interval selection in the UI
2. Enforce canonical 2-hour blocks when storing availability
3. Enforce 30-minute travel time between consecutive availability blocks
4. Prevent invalid partial, conflicting, or overlapping availability

### Design Decision: Travel Time
Travel time (30 minutes) sits **OUTSIDE** the 2-hour teaching block as an additional scheduling constraint. A 2-hour block represents teaching time. Between consecutive blocks on the same day, there must be a minimum 30-minute gap for travel.

Example: Block at 09:00-11:00 means the next block cannot start until 11:30.

### Success Criteria
- [x] UI allows start time selection in 30-minute intervals (08:00, 08:30, 09:00, ..., 16:00)
- [x] All availability is stored as exactly 2-hour blocks (end_time = start_time + 2 hours)
- [x] Backend validates and enforces 2-hour block duration
- [x] Backend validates 30-minute travel time gap between blocks
- [x] Frontend shows travel time conflicts before submission
- [x] Drag-and-drop snaps to 30-minute increments
- [x] Overlapping/conflicting availability cannot be saved
- [x] Tests cover the validation rules

---

## PHASE 1: PLANNING
**Status:** ✅ Complete

### Analysis

#### Current State
- 2-hour blocks enforced in UI via hardcoded even-hour start times (08:00, 10:00, 12:00, 14:00, 16:00)
- Backend validates overlap but does NOT enforce 2-hour duration or travel time
- No travel time implementation exists
- Drag snaps to 2-hour blocks (160px)

#### Changes Required

**Backend (Form Requests):**
1. `StoreCalendarItemRequest` - Add validation for exactly 2-hour duration + 30-min travel time gap
2. `UpdateCalendarItemRequest` - Same validations

**Frontend:**
1. `ScheduleTab.vue` - 30-min interval options, travel time conflict detection, inline warnings
2. `WeeklyCalendarGrid.vue` - 30-min drag snap

**Tests:**
- Pest feature tests for store/update validation rules

### Reflection
Straightforward extension of existing validation. No database changes needed.

---

## PHASE 2: IMPLEMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Update `StoreCalendarItemRequest` - enforce 2h duration + 30min travel gap
- [x] Update `UpdateCalendarItemRequest` - enforce 2h duration + 30min travel gap
- [x] Update `ScheduleTab.vue` - 30min start time options, travel time conflict detection, inline warnings
- [x] Update `WeeklyCalendarGrid.vue` - 30min drag snap
- [x] Write Pest tests for validation rules (15 test cases)

### Reflection
All changes applied cleanly. Backend validates both block duration and travel time using time arithmetic helpers. Frontend provides immediate visual feedback via computed conflict warnings that disable the submit button. Drag-and-drop now snaps to 30-minute increments while maintaining 2-hour block duration.

---

## PHASE 3: FINAL REFLECTION
**Status:** ✅ Complete

### Summary
Updated the calendar availability system with 30-minute interval selection, strict 2-hour block enforcement, and 30-minute travel time validation. Travel time sits outside the 2-hour teaching block as an additional scheduling constraint. Both frontend and backend enforce these rules consistently.

### Files Changed
1. `app/Http/Requests/StoreCalendarItemRequest.php` - Added checkBlockDuration(), checkForOverlapWithTravelTime(), time arithmetic helpers
2. `app/Http/Requests/UpdateCalendarItemRequest.php` - Same validations as store, excluding self from overlap check
3. `resources/js/components/Instructors/Tabs/ScheduleTab.vue` - 30-min start time options, hasConflict()/getConflictMessage() helpers, computed conflict warnings, disabled submit on conflict
4. `resources/js/components/Instructors/Tabs/Schedule/WeeklyCalendarGrid.vue` - Changed SNAP from 2-hour to 30-min, updated click-to-create and drag snap logic
5. `tests/Feature/CalendarAvailabilityTest.php` - 15 test cases covering block duration, travel time gaps, and edge cases
