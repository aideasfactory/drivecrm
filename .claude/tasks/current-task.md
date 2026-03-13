# Task: Update lesson scheduling to support flexible start times and travel-time calendar blocking

**Created:** 2026-03-13
**Last Updated:** 2026-03-13T17:00:00Z
**Status:** In Progress

---

## Overview

### Goal
Build the next iteration of Drive lesson scheduling around fixed 2-hour lesson slots with travel-time blocking.

### Requirements
- Keep each lesson slot fixed at 2 hours long.
- Replace the current predefined start time dropdown (08:00, 10:00, 12:00, 14:00, 16:00) with flexible 30-minute increments (08:00, 08:30, 09:00, ..., 16:00).
- Add a travel time option with selectable values of 15, 30, or 45 minutes (default: 30).
- Travel time creates a separate calendar item after the lesson, flagged as type 'travel'.
- Travel-time entries are unbookable and visually distinct (purple/indigo styling).
- Overlap checking accounts for travel time windows.
- Calendar grid snaps to 30-minute increments instead of 2-hour blocks.

### Context
- Tile ID: 019ce7ff-df09-733b-be1b-294fe1175ea1
- Repository: drivecrm
- Branch: feature/019ce7ff-df09-733b-be1b-294fe1175ea1-update-lesson-scheduling-to-support-flexible-start-times-and
- Priority: MEDIUM

---

## PHASE 1: PLANNING
**Status:** ✅ Complete

### Tasks
- [x] Review existing codebase (ScheduleTab.vue, CalendarEventBlock.vue, WeeklyCalendarGrid.vue, CalendarItem model, enums, actions, services)
- [x] Identify all files that need modification
- [x] Plan database schema changes
- [x] Plan backend changes (enum, model, actions, controller, form request)
- [x] Plan frontend changes (types, components, snap logic)

### Key Files to Modify
**Backend:**
- `app/Enums/CalendarItemType.php` (NEW)
- `database/migrations/xxxx_add_item_type_to_calendar_items_table.php` (NEW)
- `app/Models/CalendarItem.php`
- `app/Actions/Instructor/CreateCalendarItemAction.php`
- `app/Actions/Instructor/GetInstructorCalendarAction.php`
- `app/Http/Controllers/InstructorController.php`
- `app/Http/Requests/StoreCalendarItemRequest.php`
- `app/Services/InstructorService.php`
- `app/Services/CalendarService.php`

**Frontend:**
- `resources/js/types/instructor.ts`
- `resources/js/components/Instructors/Tabs/ScheduleTab.vue`
- `resources/js/components/Instructors/Tabs/Schedule/CalendarEventBlock.vue`
- `resources/js/components/Instructors/Tabs/Schedule/WeeklyCalendarGrid.vue`

### Approach
1. Add `item_type` column to `calendar_items` (enum: 'slot', 'travel'; default: 'slot')
2. When creating a slot with travel time, backend creates BOTH the lesson slot AND a travel-time calendar item immediately after it
3. Travel items: `is_available = false`, `item_type = 'travel'`, `unavailability_reason = 'Travel time'`
4. Overlap checking considers travel items as blocking
5. Frontend: flexible 30-min start times, travel time selector, purple/indigo visual for travel blocks

### Reflection
Planning complete. Clear understanding of all changes needed. The approach leverages existing `is_available = false` pattern while adding a new `item_type` discriminator for visual distinction and semantic meaning.

---

## PHASE 2: IMPLEMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Create CalendarItemType enum
- [x] Create migration to add item_type to calendar_items
- [x] Update CalendarItem model with item_type
- [x] Update CreateCalendarItemAction to support travel time creation
- [x] Update InstructorController & StoreCalendarItemRequest
- [x] Update GetInstructorCalendarAction to include item_type
- [x] Update InstructorService
- [x] Update frontend TypeScript types
- [x] Update CalendarEventBlock with travel time styling
- [x] Update ScheduleTab with flexible start times and travel time selector
- [x] Update WeeklyCalendarGrid snap logic for 30-min increments
- [x] Update CalendarService to exclude travel items from availability
- [x] Write Pest tests

### Reflection
All backend and frontend changes implemented successfully. The travel-time system uses a parent-child relationship between slot and travel CalendarItems. 13 Pest tests cover creation, deletion, update cascading, API validation, overlap checking, and flexible start times.

---

## PHASE 3: FINAL REFLECTION & DOCUMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Update .claude/database-schema.md with new columns
- [x] Write reflection on implementation
- [x] Write .phase_done sentinel file

### Reflection
The implementation cleanly extends the existing calendar system by adding a `item_type` discriminator column and parent-child relationship for travel blocks. Key design decisions:
1. Travel blocks are full CalendarItem rows (not virtual) — this makes overlap checking, deletion cascading, and calendar rendering straightforward.
2. The `parent_item_id` FK with `nullOnDelete` ensures orphan travel blocks are cleaned up.
3. Frontend distinguishes travel blocks with purple/indigo styling and prevents drag/edit operations on them.
4. Start times now support 30-minute increments (08:00–16:00) instead of the previous 2-hour fixed options.
5. Overlap validation accounts for the travel time window by computing an effective end time.
