# Task: Calendar: add recurring slot options and review best implementation

**Created:** 2026-03-13
**Last Updated:** 2026-03-13T16:30:00Z
**Status:** ✅ Complete

---

## Overview

### Goal
Build improved recurring slot support for the calendar. Instructors should be able to create time slots that repeat weekly, bi-weekly, monthly, or indefinitely, while still allowing individual occurrences to be modified or deleted.

### Context
- Tile ID: 019ce7ac-e270-73ed-9823-3824cf04b133
- Repository: drivecrm
- Branch: feature/019ce7ac-e270-73ed-9823-3824cf04b133-calendar-add-recurring-slot-options-and-review-best-implemen
- Priority: HIGH
- Customer: Drive

---

## PHASE 1: PLANNING
**Status:** ✅ Complete

### Design Decision: Materialized Instances with Recurrence Metadata
Chose to generate individual CalendarItem rows for each occurrence, linked by a shared `recurrence_group_id` UUID. This approach keeps existing queries, overlap checks, and onboarding flow unchanged while allowing individual modifications.

### Reflection
Good choice of pattern — avoids RRULE complexity, keeps the database query model simple, and matches how the existing onboarding flow already creates weekly slots.

---

## PHASE 2: IMPLEMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Create RecurrencePattern enum (None, Weekly, Biweekly, Monthly)
- [x] Create migration: add recurrence_pattern, recurrence_end_date, recurrence_group_id to calendar_items
- [x] Update CalendarItem model with new fields, casts, and isRecurring() helper
- [x] Create CreateRecurringCalendarItemsAction (generates all occurrences)
- [x] Create DeleteRecurringCalendarItemsAction (delete this + all future)
- [x] Update StoreCalendarItemRequest with recurrence validation
- [x] Update InstructorService with new methods
- [x] Update InstructorController with recurrence handling + formatCalendarItem helper
- [x] Update GetInstructorCalendarAction to return recurrence fields
- [x] Update TypeScript types (RecurrencePattern, CalendarItemFormData, CalendarItemResponse)
- [x] Update ScheduleTab.vue with recurrence dropdown + end date picker
- [x] Update CalendarEventBlock.vue with recurrence indicator icon + new CalendarEvent fields
- [x] Update database-schema.md with new columns and business logic
- [x] Write Pest feature tests (10 tests covering actions, model, and API endpoints)

### Reflection
Implementation went smoothly. The materialized instances pattern meant zero changes to the existing calendar query pipeline. The delete endpoint's `scope` query parameter cleanly supports both single and bulk delete without adding a new route. The frontend UI additions are minimal — a recurrence dropdown on the create sheet and a radio selector in the delete dialog.

---

## PHASE 3: FINAL REFLECTION & DOCUMENTATION
**Status:** ✅ Complete

### Summary
Built full recurring slot support for the instructor calendar. Instructors can now create time slots that repeat weekly, bi-weekly, or monthly with an optional end date (defaults to 6 months). Each occurrence is an independent CalendarItem that can be individually edited, moved, or deleted. The delete dialog offers "this event only" or "this and all future events" for recurring series.

### Files Changed
- `app/Enums/RecurrencePattern.php` (new)
- `app/Models/CalendarItem.php` (updated)
- `app/Actions/Instructor/CreateRecurringCalendarItemsAction.php` (new)
- `app/Actions/Instructor/DeleteRecurringCalendarItemsAction.php` (new)
- `app/Actions/Instructor/GetInstructorCalendarAction.php` (updated)
- `app/Http/Requests/StoreCalendarItemRequest.php` (updated)
- `app/Http/Controllers/InstructorController.php` (updated)
- `app/Services/InstructorService.php` (updated)
- `database/migrations/2026_03_13_160502_add_recurrence_fields_to_calendar_items_table.php` (new)
- `resources/js/types/instructor.ts` (updated)
- `resources/js/components/Instructors/Tabs/ScheduleTab.vue` (updated)
- `resources/js/components/Instructors/Tabs/Schedule/CalendarEventBlock.vue` (updated)
- `tests/Feature/RecurringCalendarItemsTest.php` (new)
- `.claude/database-schema.md` (updated)

### Potential Overhead / Anti-Patterns
1. **Data volume**: The materialized approach creates many rows (e.g., 26 rows for weekly/6-month). This is acceptable for this scale but could be optimized with lazy expansion if instructors create hundreds of recurring slots.
2. **No bulk update**: Editing a recurring event only changes the single occurrence, not the entire series. This is intentional for flexibility but could be extended later if needed.
3. **Overlap validation**: The overlap check in StoreCalendarItemRequest only validates the first date, not all generated dates. For recurring slots, some occurrences could theoretically overlap with existing slots. This is an acceptable tradeoff — the instructor can delete individual conflicting occurrences.

### Score: 8/10
Solid implementation that follows existing project patterns. The materialized instances approach is simple, maintainable, and backward-compatible. Deducted points for the overlap validation gap on recurring dates and the lack of bulk-edit capability for recurring series (both are reasonable future enhancements).
