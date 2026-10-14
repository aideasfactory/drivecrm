# Task: Manual "mark as refunded" tick in admin

**Created:** 2026-10-14
**Last Updated:** 2026-10-14
**Status:** Complete

Mission Control ticket `01a13a48-a75a-71ee-a2dd-581afcbdb0ae`.

---

## 📋 Overview

### Goal
Let admin staff (owners) tick a booking or payment as refunded when the refund
was made outside the system (directly in Stripe or by bank transfer). Record who
ticked it and when, show it wherever the booking/payment appears in admin, allow
unticking, and keep an audit of every change. No Stripe refund is triggered.

### Success Criteria
- [x] Migration adds `manually_refunded_at` + `manually_refunded_by_user_id` to `lesson_payments` and `orders`
- [x] Owner-only tick/untick for a paid lesson payment (status → `refunded`, untick → `paid`)
- [x] Owner-only tick/untick for a whole booking (covers released bookings with no payment rows)
- [x] Staff member name + date shown on Payments tab, Lessons tab, Cancelled Lessons report (+ CSV) and Lessons booked page
- [x] Every tick/untick written to the student's activity log with the admin user
- [x] No Stripe calls

---

## 🎯 PHASE 1: PLANNING

**Status:** ✅ Complete

### Decisions Made
- Two levels: per payment (`lesson_payments`) and per booking (`orders`). Expired bookings lose their payment rows, so a booking-level tick is needed for those.
- Ticking a payment reuses the existing `refunded` status so existing badges, totals and the Cancelled Lessons "Refunded" filter work unchanged. Only `paid` payments can be ticked; only staff-ticked payments can be unticked (back to `paid`).
- Booking tick does not change order status or cascade to payments — it is a record only.
- Audit trail = `activity_logs` (existing mechanism), category `payment`, with `action`, `admin_user_id` and previous marker in metadata. The columns hold the current state only.
- Owner-only (`EnsureOwner` + FormRequest `authorize`). Restricted owners can use it on student pages and Lessons booked.

### Reflection
**What went well:** the manual payout "mark as paid" flow was a close template.
**Risks identified:** a `invoice.paid` webhook arriving after a tick would set the payment back to `paid` (unlikely for a refunded payment).

---

## 🎯 PHASE 2: IMPLEMENTATION

**Status:** ✅ Complete

### Tasks
- [x] Migration `2026_10_14_140000_add_manual_refund_fields_to_lesson_payments_and_orders_tables`
- [x] Models: `LessonPayment` / `Order` — fillable, casts, `manuallyRefundedBy()`, `wasManuallyRefunded()`
- [x] Actions: `Payment/SetLessonPaymentManualRefundAction`, `Payment/SetOrderManualRefundAction`
- [x] `OrderService::setLessonPaymentManualRefund()` / `setOrderManualRefund()` with activity logging
- [x] `UpdateManualRefundRequest`
- [x] Routes: `PATCH /students/{student}/payments/{lessonPayment}/refund`, `PATCH /lessons-booked/{order}/refund`
- [x] Read side: `GetStudentPaymentsAction`, `GetStudentLessonsAction`, `GetCancelledLessonsAction` (+ CSV), `ListStaffBookedLessonsAction`
- [x] Frontend: `Shared/ManualRefundToggle.vue` (checkbox + confirm dialog), wired into Payments tab, Cancelled Lessons report, Lessons booked; read-only line on Lessons tab
- [x] Updated `.claude/database-schema.md`

### Reflection
**What went well:** one shared toggle component keeps the three screens consistent.
**What could be improved:** no optional refund reference (e.g. Stripe `re_...` ID) is captured — easy follow-up if wanted.

---

## 🎯 PHASE 3: REFLECTION

**Status:** ✅ Complete

- Technical debt: none significant. Booking and payment ticks are independent, so a booking can be ticked while its payments still read "paid" — the Payments and Lessons tabs show the booking-level line to make that visible.
- Not run (per project rules): tests, Pint, ESLint/Prettier, migrations.
