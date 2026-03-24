# Task: Calendar Management API for Mobile App

## Overview
Expose the existing admin calendar management functionality through the API so the mobile app can create, delete, and query calendar items. Reuse the same Actions and Services used by the admin area — no logic duplication.

**Existing State:**
- Admin area supports full calendar CRUD via `InstructorController` (web)
- Actions exist in `app/Actions/Instructor/`: `CreateCalendarItemAction`, `DeleteCalendarItemAction`, `CreateRecurringCalendarItemsAction`, `DeleteRecurringCalendarItemsAction`
- Services: `InstructorService` (orchestration), `InstructorCalendarService` (caching)
- API already has a GET endpoint (`/api/v1/instructor/calendar`) for available items on a date — but only returns basic fields (id, start_time, end_time, is_available, status)
- `CalendarItemResource` exists but is minimal — needs enriching

**What's Needed:**
1. **POST** `/api/v1/instructor/calendar/items` — Create calendar item (with all options: travel, recurrence, practical test, notes)
2. **DELETE** `/api/v1/instructor/calendar/items/{calendarItem}` — Delete calendar item (single or future recurring)
3. **GET** `/api/v1/instructor/calendar/items` — Get calendar items for a specific day with filters (e.g., only available slots)

---

## Phase 1: Planning ✅ Complete
**Status:** ✅ Complete
**Last Updated:** 2026-03-24

### Tasks
- [x] Review existing web controller flow for create and delete
- [x] Identify exact Service/Action methods to reuse
- [x] Plan API controller methods, routes, form requests, and resources
- [x] Document field mapping and response structure
- [x] Create task breakdown for Phase 2

### Decisions & Notes
**Reuse plan:**
- `InstructorService::addCalendarItem()` → single item creation
- `InstructorService::addRecurringCalendarItems()` → recurring creation
- `InstructorService::removeCalendarItem()` → single deletion
- `InstructorService::removeRecurringCalendarItems()` → future recurring deletion
- `InstructorCalendarService::getCalendarItems()` → GET with filtering (needs action update for filters)
- Existing `InstructorCalendarController` has index() only — add store() and destroy()
- `CalendarItemResource` needs enriching (currently only 5 fields)
- Web `StoreCalendarItemRequest` overlap check uses `$this->route('instructor')` — API version must use `$request->user()->instructor`
- The existing GET route for calendar was never registered in api.php — will register all 3 routes together
- `GetInstructorCalendarItemsAction` currently hard-filters to available-only — needs optional filter params for the "get a day's items" requirement

### Reflection
Clean architecture — all logic exists in Actions/Services already. API layer is purely HTTP concerns.

---

## Phase 2: Implementation ✅ Complete
**Status:** ✅ Complete
**Last Updated:** 2026-03-24

### Tasks
- [x] Create `StoreCalendarItemRequest` for API (`app/Http/Requests/Api/V1/`)
- [x] Enrich `CalendarItemResource` to include all fields (item_type, travel_time_minutes, notes, unavailability_reason, recurrence fields, parent_item_id)
- [x] Add `store` method to `InstructorCalendarController` (create calendar item via existing Service)
- [x] Add `destroy` method to `InstructorCalendarController` (delete calendar item via existing Service)
- [x] Update `index` method on `InstructorCalendarController` to support day-based filtering (available_only param)
- [x] Add routes to `routes/api.php`
- [x] Update `.claude/api.md` with all new/modified endpoints

### Reflection
All logic reused from existing InstructorService — zero new Actions or Services created. API FormRequest mirrors web version but resolves instructor from token. CalendarItemResource enriched with all fields using `whenLoaded` for the date relation. Changelog and quick route reference updated.

---

## Phase 3: Documentation & Finalization ✅ Complete
**Status:** ✅ Complete
**Last Updated:** 2026-03-24

### Tasks
- [x] Final review of all endpoints for consistency with admin behaviour
- [x] Verify CalendarItemResource response matches documented structure
- [x] Ensure api.md is complete with request params, response examples, and auth requirements
- [x] Write `.phase_done` sentinel

### Reflection
All three endpoints match admin behaviour exactly — same Service methods, same Actions, same validation (overlap detection, unavailability reason check). The CalendarItemResource `date` field uses `whenLoaded('calendar')` so it appears on store responses (where we explicitly load it) and is omitted on index responses (where the caller already knows the date). No new Services or Actions were created — full reuse.
