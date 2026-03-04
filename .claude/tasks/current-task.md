# Task: Calendar Item Notes and Unavailability Reason

**Created:** 2026-03-04  
**Last Updated:** 2026-03-04  
**Status:** 🔄 In Progress — Phase 1: Planning

---

## Overview

### Goal
Update the instructor scheduling feature in Drive CRM to support:
1. **Notes field** — Add ability to include notes when creating scheduling dates for each instructor
2. **Unavailability reason** — When an instructor selects unavailable, require them to provide a reason via a free text field
3. **Database updates** — Update the `calendar_item` table migration to include new fields (`notes` and `unavailability_reason`)
4. **Model updates** — Update the `CalendarItem` model to support these new attributes
5. **UI updates** — Ensure the UI captures and displays these fields appropriately

### Repository
`aideasfactory/drivecrm` (local: `/Users/claw/Herd/drivecrm`)

### Branch
`feature/calendar-item-notes-unavailability`

---

## What Already Exists

**Calendar System (from previous Phase 1 Onboarding work):**
- `Calendar` model — Parent calendar for instructors
- `CalendarItem` model — Time slots within a calendar date
- Existing fields on `calendar_items`: `id`, `calendar_id`, `start_time`, `end_time`, `is_available`, `status`, `created_at`, `updated_at`
- Status enum: `draft`, `reserved`, `booked`, `completed`
- `is_available` boolean for blocking slots

**Frontend:**
- Calendar UI components for instructors to manage their availability
- Scheduling interface for creating/managing calendar items

---

## What Needs to Be Built

### Database Changes
1. **Migration: Add `notes` to `calendar_items` table** — text, nullable
2. **Migration: Add `unavailability_reason` to `calendar_items` table** — text, nullable (only relevant when `is_available = false`)

### Backend
1. **Update `CalendarItem` model** — Add `notes` and `unavailability_reason` to `$fillable`
2. **Update Calendar/Scheduling Controller** — Accept and store the new fields
3. **Validation rules** — `notes` optional string, `unavailability_reason` required when marking unavailable

### Frontend
1. **Calendar item creation/edit form** — Add notes textarea
2. **Unavailability dialog** — When marking a slot unavailable, show modal requiring reason input
3. **Calendar display** — Show notes indicator and unavailability reason on calendar items

---

## Phased Plan

**Phase 1: Planning** ✅ — Break down requirements, review existing code  
**Phase 2: Database Migration** ✅ — Create migrations, update model and docs  
**Phase 3: Backend Updates** ✅ — Update controller, validation, actions, service  
**Phase 4: Frontend Updates** ✅ — Update forms and UI components  
**Phase 5: Testing & Review** 🔄 — Test all scenarios, edge cases (IN PROGRESS)  
**Phase 6: Reflection** — Document decisions, complete task

---

## Phase 1: Planning ✅

### Tasks
- [x] Read .claude/instructions.md
- [x] Read .claude/database-schema.md (calendar_items section)
- [x] Explore existing Calendar and CalendarItem models
- [x] Identify existing controller and frontend components
- [x] Plan database changes
- [x] Plan validation rules
- [x] Get approval from Sam

### Reflection
- Reviewed existing calendar system architecture
- Confirmed calendar_items table structure from database-schema.md
- Planned simple migration approach: two separate ALTER TABLE statements
- Validation rules defined: notes optional, unavailability_reason required when unavailable
- Frontend flow mapped: conditional fields based on availability toggle
- Phase 1 complete — ready for database migration

#### Migration 1: Add `notes` to `calendar_items`
```sql
ALTER TABLE calendar_items 
ADD COLUMN notes TEXT NULLABLE AFTER status;
```

#### Migration 2: Add `unavailability_reason` to `calendar_items`
```sql
ALTER TABLE calendar_items 
ADD COLUMN unavailability_reason TEXT NULLABLE AFTER notes;
```

### Validation Rules
- `notes` — nullable, string, max 1000 characters
- `unavailability_reason` — required only when `is_available` is set to `false`, string, max 500 characters

### Frontend Flow
1. **Creating/Editing Calendar Item:**
   - Show notes textarea (optional)
   - Show "Available" toggle
   - If toggle switched to "Unavailable", show unavailability_reason textarea (required)

2. **Calendar Display:**
   - Show notes indicator (icon) on items with notes
   - Show unavailability badge with reason preview on unavailable slots

---

## Files to Create/Modify

### Create
| File | Purpose |
|------|---------|
| `database/migrations/YYYY_MM_DD_HHMMSS_add_notes_to_calendar_items_table.php` | Add notes column |
| `database/migrations/YYYY_MM_DD_HHMMSS_add_unavailability_reason_to_calendar_items_table.php` | Add unavailability_reason column |

### Modify
| File | Change |
|------|--------|
| `app/Models/CalendarItem.php` | Add `notes`, `unavailability_reason` to `$fillable` |
| `app/Http/Controllers/CalendarController.php` (or similar) | Accept and validate new fields |
| `resources/js/components/.../CalendarItemForm.vue` (or similar) | Add notes and unavailability fields |
| `.claude/database-schema.md` | Document new columns |

---

## Decisions Log
- Notes field is optional — instructors can add general notes to any calendar item
- Unavailability reason is required when marking unavailable — ensures visibility into why slots are blocked
- Both fields are text (not limited string) to allow flexibility
- Unavailability reason only shown in UI when is_available = false

---

## Phase 2: Database Migration ✅

### Tasks
- [x] Create migration for `notes` field
- [x] Create migration for `unavailability_reason` field
- [x] Update CalendarItem model
- [x] Update database-schema.md
- [ ] Run migrations locally

### Reflection
- Created two separate migrations following Laravel conventions: `add_notes_to_calendar_items_table` and `add_unavailability_reason_to_calendar_items_table`
- Added `notes` and `unavailability_reason` to CalendarItem model `$fillable` array
- Updated database-schema.md with new column documentation
- Both fields are nullable text columns as planned
- Migrations use `->after()` to position columns logically after `status`
- Ready for user to run `php artisan migrate`

---

## Phase 3: Backend Updates ✅

### Tasks
- [x] Find and examine Calendar/Scheduling controller
- [x] Update StoreCalendarItemRequest validation rules
- [x] Update UpdateCalendarItemRequest validation rules
- [x] Add conditional validation for unavailability_reason (required when is_available=false)
- [x] Update CreateCalendarItemAction to accept and store new fields
- [x] Update UpdateCalendarItemAction to accept and store new fields
- [x] Update InstructorService to pass new fields to actions
- [x] Update InstructorController to accept and return new fields

### Reflection
- Located calendar item handling in InstructorController with Form Request validation
- Added `notes` (nullable, max 1000) and `unavailability_reason` (nullable, max 500) to both Form Requests
- Implemented conditional validation in `withValidator()` - unavailability_reason required when is_available=false
- Updated CreateCalendarItemAction and UpdateCalendarItemAction to accept and store the new fields
- Updated InstructorService method signatures to include optional $notes and $unavailabilityReason parameters
- Updated InstructorController to pass fields from requests to service and include them in JSON responses
- All backend components now support the new fields end-to-end

---

## Phase 4: Frontend Updates ✅

### Tasks
- [x] Find and examine frontend calendar components
- [x] Update TypeScript types (CalendarItem, CalendarItemFormData, CalendarItemResponse)
- [x] Update ScheduleTab.vue create/edit forms with notes field
- [x] Add unavailability reason dialog/field when marking unavailable
- [x] Update CalendarEventBlock.vue to display notes indicator
- [x] Update CalendarEventBlock.vue to display unavailability reason
- [x] Add form validation for unavailability reason
- [x] Update delete dialog to show notes and unavailability reason

### Reflection
- Updated TypeScript types in `types/instructor.ts` to include `notes` and `unavailability_reason` fields
- Modified `ScheduleTab.vue` to include notes textarea (always visible) and unavailability_reason textarea (conditional, required when unavailable)
- Added character count indicators for both fields (1000 for notes, 500 for unavailability reason)
- Updated form submission logic to validate unavailability_reason when marking unavailable
- Modified `CalendarEventBlock.vue` to display notes icon and text, and unavailability reason with warning icon
- Updated delete confirmation dialog to show notes and unavailability reason
- All frontend components now properly handle the new fields end-to-end

---

**Last Updated:** 2026-03-04 14:00 GMT  
**Current Phase:** Phase 4 Complete — Ready for Phase 5 (Testing & Review)

---

**Last Updated:** 2026-03-04 13:50 GMT  
**Current Phase:** Phase 4 — Frontend Updates (IN PROGRESS)
