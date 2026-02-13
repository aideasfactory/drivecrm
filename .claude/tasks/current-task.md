# Task: Interactive Calendar - Time Slot Management

**Created:** 2026-02-12
**Last Updated:** 2026-02-12
**Status:** 🔄 Phase 5 Complete - Custom Calendar - Awaiting Review

---

## Overview

### Goal
Build an interactive weekly calendar for the instructor schedule tab, where instructors can:
- View their weekly schedule on a calendar
- Navigate between weeks/dates
- Click on the calendar to add time slots with a status of **available** or **unavailable**
- Drag and drop time slots to move them
- Click existing time slots to edit or delete them
- Color-coded events (green = available, red = unavailable)
- Full dark mode support

### Requirements Summary
1. Custom weekly calendar grid with week view (06:00-22:00, Monday start)
2. Date navigation (forward/back through weeks, today button)
3. Click-to-create: click empty slot → Sheet opens to set status + confirm
4. Drag-and-drop: move existing time slots to new times/dates with 30-min snap
5. Event colors: green for available, red for unavailable (Tailwind dark: variants)
6. Event click: click existing slot → edit status or delete
7. Dynamic loading: fetch events from backend when navigating weeks
8. Backend: update endpoint for drag-and-drop moves + status changes

---

## Phase 1: Planning ✅

**Objective:** Design the full component architecture and identify all changes needed.

### Tasks
- [x] Review existing `ScheduleTab.vue` component
- [x] Review existing backend (controller, actions, models, routes)
- [x] Research Schedule X plugins
- [x] Plan component interaction flow

---

## Phase 2: Backend Updates ✅

**Objective:** Add update endpoint and improve calendar data fetching.

### Tasks
- [x] Create `UpdateCalendarItemRequest` form request
- [x] Create `UpdateCalendarItemAction`
- [x] Add `updateCalendarItem()` method to `InstructorController`
- [x] Add PUT route to `routes/web.php`
- [x] Wire through `InstructorService`
- [x] Add `is_available` param to store request, action, service, and controller

### Files Changed
- `app/Http/Requests/UpdateCalendarItemRequest.php` (NEW)
- `app/Actions/Instructor/UpdateCalendarItemAction.php` (NEW)
- `app/Http/Controllers/InstructorController.php` (UPDATE)
- `app/Services/InstructorService.php` (UPDATE)
- `app/Http/Requests/StoreCalendarItemRequest.php` (UPDATE)
- `app/Actions/Instructor/CreateCalendarItemAction.php` (UPDATE)
- `routes/web.php` (UPDATE)

---

## Phase 3: Schedule X Frontend ✅ → ❌ Replaced

**Objective:** Initial implementation using Schedule X library.

**Result:** Schedule X v4 had critical bugs:
1. `dateTime.split is not a function` - v4 passes Temporal objects, not strings
2. Calendar data never loads - same Temporal API issue in onRangeUpdate
3. No native Tailwind dark mode support

**Decision:** Replace with custom calendar component.

---

## Phase 4: Review of Schedule X ✅ → Led to Custom Component Decision

**Result:** User chose to build a custom calendar component after reviewing Schedule X bugs.

---

## Phase 5: Custom Calendar Component ✅

**Objective:** Replace Schedule X with a custom-built calendar using Tailwind/ShadCN.

### Architecture
- **4-file structure:** Composable + 2 sub-components + orchestrator rewrite
- **CSS Grid** layout with time gutter + 7 day columns
- **Pointer Events** for drag-and-drop with 30-min snap
- **Absolute positioning** for events within day columns
- **Tailwind dark: variants** for native dark mode
- **Zero external calendar dependencies**

### Tasks
- [x] Create `useCalendarNavigation.ts` composable (week nav, Monday start, formatDate)
- [x] Create `CalendarEventBlock.vue` (colored event block, click + drag emit)
- [x] Create `WeeklyCalendarGrid.vue` (CSS Grid, time labels, slot click, drag-drop)
- [x] Rewrite `ScheduleTab.vue` (orchestrator with navigation, API, sheets, dialogs)
- [x] Remove 7 Schedule X + temporal-polyfill packages

### Files Changed
- `resources/js/composables/useCalendarNavigation.ts` (NEW)
- `resources/js/components/Instructors/Tabs/Schedule/CalendarEventBlock.vue` (NEW)
- `resources/js/components/Instructors/Tabs/Schedule/WeeklyCalendarGrid.vue` (NEW)
- `resources/js/components/Instructors/Tabs/ScheduleTab.vue` (REWRITE)
- `package.json` (REMOVED 7 packages)

### Key Design Decisions
1. **Pointer Events** for drag (smoother than HTML5 Drag API, works on touch)
2. **CSS Grid** `grid-cols-[4rem_repeat(7,1fr)]` for aligned time rows
3. **30-min slot grid** with 40px row height, 06:00-22:00 range
4. **Optimistic drag updates** with revert on API error
5. **No date library needed** — native JS Date for simple week navigation

---

## Progress Summary

### Completion Status
- **Phase 1:** ✅ Complete (Planning)
- **Phase 2:** ✅ Complete (Backend)
- **Phase 3:** ❌ Replaced (Schedule X bugs)
- **Phase 4:** ✅ Complete (Decision: custom component)
- **Phase 5:** ✅ Complete (Custom Calendar)

### All Files (Current State)

**Backend (new):**
- `app/Http/Requests/UpdateCalendarItemRequest.php`
- `app/Actions/Instructor/UpdateCalendarItemAction.php`

**Backend (updated):**
- `app/Http/Controllers/InstructorController.php`
- `app/Services/InstructorService.php`
- `app/Http/Requests/StoreCalendarItemRequest.php`
- `app/Actions/Instructor/CreateCalendarItemAction.php`
- `routes/web.php`

**Frontend (new):**
- `resources/js/composables/useCalendarNavigation.ts`
- `resources/js/components/Instructors/Tabs/Schedule/CalendarEventBlock.vue`
- `resources/js/components/Instructors/Tabs/Schedule/WeeklyCalendarGrid.vue`

**Frontend (updated):**
- `resources/js/components/Instructors/Tabs/ScheduleTab.vue`
- `resources/js/types/instructor.ts`
- `package.json` (removed 7 Schedule X deps)
