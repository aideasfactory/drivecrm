# Task: Admin refunds management

## Overview

Admins need oversight of refunds requested when staff cancel paid lessons
from the instructor diary. Learners cannot self-cancel. Fit this into the
existing Stripe + CancelBookingAction machinery:

- Persist a `refunds` row when a paid lesson is cancelled (pending request)
- Owner-only Refunds page: list newest first, running totals, initiate via
  Stripe or mark complete after a manual Stripe refund
- Cancel Booking modal offers a refund option (request / Stripe now / none)
- Paper trail: which staff member completed the refund and when

## Phase 1: Planning ✅

### Existing machinery (do not invent a parallel payments system)

- `CancelBookingAction` already identifies paid lessons (`wasPaid`: weekly
  LessonPayment paid, or confirmed upfront order) and emails Head Office via
  `RefundRequiredNotification`. No Stripe refund, no persisted request.
- `LessonPayment.status` already includes `refunded`.
- Stripe charges live on `lesson_payments.stripe_charge_id` (weekly) and
  `orders.stripe_charge_id` / `stripe_payment_intent_id` (upfront).
- `StripeService` can resolve charge IDs from invoices and payment intents.
  Needs a `createRefund()` wrapper around Stripe Refunds.

### Approach

1. New `refunds` table + `Refund` model (one per cancelled paid lesson).
2. `CancelBookingAction` creates pending refunds unless staff chose "none".
   Optional `refund_action=stripe` processes them immediately via Stripe.
3. `RefundService` + actions for list/process/mark-complete.
4. Owner-only `/refunds` Inertia page in the sidebar.
5. Cancel Booking modal: refund radios when the lesson is paid.

### Reflection

A dedicated refunds table is the right fit: the cancel action already knows
which lessons were paid, but emails are not an audit trail. Reusing Stripe
charge IDs already stored on orders/lesson_payments avoids a second payments
system. Instructor API cancel always queues a pending request so admin still
has oversight; only owners can initiate Stripe or mark complete.

**Status:** Phase 1 complete.
**Last Updated:** 2026-08-27.

## Phase 2: Implementation ✅

### Files created

- `app/Enums/RefundStatus.php`, `RefundMethod.php`, `RefundAction.php`
- `database/migrations/2026_08_27_210000_create_refunds_table.php`
- `app/Models/Refund.php`, `database/factories/RefundFactory.php`
- `app/Actions/Refund/CreateRefundsForCancelledLessonsAction.php`
- `app/Actions/Refund/ProcessStripeRefundAction.php`
- `app/Actions/Refund/MarkRefundCompleteAction.php`
- `app/Services/RefundService.php`
- `app/Http/Controllers/RefundController.php`
- `resources/js/pages/Refunds/Index.vue`
- `tests/Feature/Refunds/RefundManagementTest.php`

### Files edited

- `CancelBookingAction`, `InstructorService`, `InstructorController`, API calendar destroy
- `StripeService::createRefund`
- `Lesson`, `LessonPayment`, `Order`, `User` relationships
- `AppSidebar.vue`, `ScheduleTab.vue`
- `routes/web.php`
- `.claude/database-schema.md`, `.claude/api.md`
- `RefundRequiredNotification` copy points at the Refunds dashboard

### Key decisions

- One `refunds` row per lesson (unique `lesson_id`). Amount from weekly
  `LessonPayment` or the lesson's `amount_pence` for upfront.
- Diary cancel: `refund_action` = `request` (default) | `stripe` | `none`.
  Instructor API always queues `request` so owners still see it.
- Completing a refund (Stripe or mark-complete) sets `LessonPayment` to
  `refunded` and writes "{Name} made refund on {d/m/Y H:i}".

## Phase 3: Reflection ✅

**Why this shape is right for the brief:**
- Oversight lives in a real table, not an email inbox.
- Stripe refunds reuse the charges the app already stores.
- Staff can refund from the diary or review case-by-case on `/refunds`.
- Paper trail is the exact sentence the ticket asked for.

**Out of scope / follow-up:**
- No new mobile API to initiate Stripe refunds (owner web only).
- Partial upfront refunds refund that lesson's `amount_pence` against the
  order charge; Stripe will reject if it would exceed the remaining charge.

**Status:** All phases complete.
**Last Updated:** 2026-08-27.

## 💭 PHASE 3: REFLECTION ✅

### Why this shape is right for the brief
The cancel flow already knew which lessons were paid. Persisting that as
`refunds` gives the running total and case-by-case review the ticket asked
for, without a second payments system.

### Operational notes
- Default diary choice for a paid lesson is "Request a refund".
- Stripe initiation needs a charge ID on the lesson payment or order.
  If missing, the row stays pending and staff can mark complete after
  refunding in Stripe by hand.

### Follow-ups not done (out of scope)
- No Pint / Prettier run (project rule: user handles code style).
- No test run in this environment if PHP is unavailable (tests are written).
