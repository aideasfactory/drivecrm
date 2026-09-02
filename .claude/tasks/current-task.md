# Task: Instructor Diary — Admin Area + API

## Overview

Admin diary interactions for empty and booked slots, plus shared Service → Action APIs for the mobile app.

Empty slot: alert (Edit / Delete / Add Booking / Offer Slot / Close).
Booked slot: alert (Move / Delete / Close).
Add Booking reuses the existing student booking flow without asking for a date.
Offer Slot creates a short-notice offer (package or one-off price), pushes students, and race-safe booking.

## Phase 1: Planning ✅

### Approach

- Clicking an **existing available** diary item (open slot, no lesson) opens an action alert instead of the edit sheet.
- Clicking empty grid cells still opens the create sheet.
- Clicking a booked/reserved/draft item opens a booked-slot alert (Move / Delete / Close).
- Travel, practical test, unavailable, and completed items keep the existing edit sheet.
- Admin and API share `InstructorService`, `OrderService`, `SlotOfferService`, and existing calendar/booking/cancel actions.
- Existing update/delete/move/cancel API endpoints are reused; new endpoints only for Offer Slot and booking against a specific calendar item.

### Reflection

Existing calendar update/delete/move/cancel APIs already match admin behaviour. The new work is UI routing plus Offer Slot and booking-from-slot reuse of `OrderService::bookLessons`.

**Status:** Complete
**Last Updated:** 2026-09-02

## Phase 2: Empty slot alert + API ✅

### Tasks

- [x] Empty-slot action dialog in ScheduleTab
- [x] Edit opens existing edit sheet unchanged
- [x] Delete uses existing destroy endpoint
- [x] Review PUT/DELETE calendar item API

### Reflection

Empty-slot clicks now open a Dialog (Edit / Delete / Add Booking / Offer Slot / Close). Grid-cell clicks still create. PUT/DELETE `/api/v1/instructor/calendar/items/{id}` already covered update and delete of empty slots via `InstructorService`.

**Status:** Complete
**Last Updated:** 2026-09-02

## Phase 3: Add Booking from empty slot ✅

### Tasks

- [x] Add Booking sheet: student → package → payment; date/time from slot
- [x] Reuse `OrderService::bookLessons`
- [x] Accept optional `calendar_item_id` on order create API
- [x] Allow first lesson on today (`after_or_equal:today`)

### Reflection

`AddBookingFromSlotSheet` posts to the existing student orders endpoint with `calendar_item_id`. `OrderService::bookLessonsFromCalendarItem` and `CreateDraftCalendarItemsAction` claim the clicked slot under `lockForUpdate`. Admin and API share this path.

**Status:** Complete
**Last Updated:** 2026-09-02

## Phase 4: Offer Slot ✅

### Tasks

- [x] `slot_offers` table + `packages.is_one_off`
- [x] Admin Offer Slot sheet
- [x] Create/reuse One-Off Package
- [x] Push to instructor's students
- [x] Student list + accept APIs with `lockForUpdate`

### Reflection

`SlotOfferService` wraps create/list/accept/cancel actions. Accepting books immediately via `bookLessonsFromCalendarItem` (not on payment). Concurrent accepts lock the diary slot first, then the offer row.

**Status:** Complete
**Last Updated:** 2026-09-02

## Phase 5: Booked slot UI + move API ✅

### Tasks

- [x] Booked-slot action dialog (Move / Delete / Close)
- [x] Remove Delete from booked-slot edit/move sheet
- [x] Drag-move behaviour unchanged
- [x] Review move API (`apply_to_future_in_order`, 422 clashes)

### Reflection

Move still uses `InstructorService::updateCalendarItem` / `MoveLessonAndFutureSiblingsAction`. PUT API already supported single vs future siblings and 422 clashes. Empty availability under a move is still consumed, not treated as a clash.

**Status:** Complete
**Last Updated:** 2026-09-02

## Phase 6: Cancellation + docs ✅

### Tasks

- [x] Delete from booked alert opens existing cancellation sheet
- [x] Review cancel API (`CancelBookingAction`)
- [x] Update `api.md` and `database-schema.md`

### Reflection

Cancellation still requires a reason and supports this-lesson vs future lessons. Student always gets `BookingCancelledNotification`; Head Office gets `RefundRequiredNotification` only when paid lessons are cancelled; instructor is not emailed. `api.md` documents offer endpoints, `calendar_item_id` on orders, `has_open_offer`, and `is_one_off`.

**Status:** Complete
**Last Updated:** 2026-09-02

**Currently working on:** All phases complete
**Last Updated:** 2026-09-02
