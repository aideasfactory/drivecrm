# Head Office bookings are reschedule-only for instructors

**Status:** ✅ Complete · **Date:** 2026-11-18

## Overview
Lessons booked by Head Office can no longer be cancelled by the instructor. They get a **Reschedule** option that shows
"This lesson can only be rearranged, to cancel please contact support." and then opens the existing move flow.
Owners can still cancel. Instructor-booked and learner-booked lessons are unchanged.

## Who counts as Head Office
No new column. Uses what orders already record:
- `orders.source = bookings_team` (set by `CreateOrderFromEnquiryAction` for staff "Book lessons for a student" / onboarding), or
- `orders.booked_by_user_id` not null (only ever set for staff bookings; backfilled from enquiries).

`Order::wasBookedByHeadOffice()` and `Order::canBeCancelledBy(?User)` hold the rule; the message is
`Order::HEAD_OFFICE_RESCHEDULE_ONLY_MESSAGE`.

## Changes
- ✓ Server guards (403 + message) for instructors: web `DELETE /instructors/{instructor}/calendar/items/{item}` (booking slot),
  web `POST /students/{student}/lessons/{lesson}/cancel-banked`, API `DELETE /api/v1/instructor/calendar/items/{item}`,
  API `POST /api/v1/instructor/lessons/{lesson}/cancel-banked`.
- ✓ `booked_by_head_office` + `can_cancel` on API calendar items, instructor day lessons, banked lessons, student lessons list + detail.
  `booked_by_head_office` on the web calendar items and web pupil lessons list.
- ✓ ScheduleTab: for instructors, Head Office bookings show Reschedule (notice dialog, then move sheet) and no Cancel lesson /
  "Cancel it instead". LessonsSubTab: banked Head Office lessons show the notice instead of "Cancel lesson instead".
- ✓ `.claude/api.md` documented (fields, 403s, changelog).

## Reflection
- Owner bookings made from the CRM (`source = crm`) don't record who booked them, so they are not treated as Head Office.
- Banking ("Rebook later") is still allowed for Head Office bookings; it keeps the lesson in the booking.

💳 Payment impact: TOUCHED (guard only) — the cancel entry points that lead to `CancelBookingAction` now refuse instructors on
Head Office bookings before anything runs. No change to `CancelBookingAction`, Stripe, invoices, holds, refunds, payouts or pricing.
Risk: an instructor can no longer trigger the refund-review flow for these lessons; support/owners must cancel. Check before release:
owner cancel of a bookings-team lesson still works; instructor cancel of their own CRM/app booking still works.
