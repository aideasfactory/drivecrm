# Task: Alternative payment method for pay-in-full (upfront) bookings — exploration

**Created:** 2026-10-22
**Last Updated:** 2026-10-22 13:55 UTC
**Status:** Planning complete — waiting on product decisions before implementation

---

## 📋 Overview

### Goal
The alternative payment method (PR #153, `feature/alternative-payment-method`) lets an instructor
confirm that a pupil pays them directly (e.g. cash) instead of through Stripe. It only works for
**weekly** (`payment_mode = weekly`) orders. This task explores what the same feature looks like for
**pay-in-full** (`payment_mode = upfront`) orders: what breaks, what has to change and which decisions
are needed.

### Success Criteria (for the eventual implementation)
- [ ] An instructor can confirm a pending pay-in-full booking of a pupil with alternative payments on
- [ ] Stripe Checkout is closed before anything is recorded (no double payment)
- [ ] Each lesson is recorded as paid by alternative method at sign-off, with **no payout**
- [ ] Nothing in the cancellation/refund, revenue, or reporting flows treats the block as Stripe-paid
- [ ] Turning the pupil's switch off can't leave lessons stuck or chased for an unpayable amount

### Context
- Rules: `app/Support/AlternativePayments.php` (`allowedForOrder()` requires `isWeekly()`).
- Service: `app/Services/AlternativePaymentService.php` (`confirmBooking()`, `prepareSignOff()`).
- Since commit `3fa5775`, **single-lesson packages can only be paid in full**
  (`Package::allowsWeeklyPayment()`). So today a cash-paying pupil booking a *single* lesson cannot
  use the alternative method at all unless a full-access owner books it as a `cash` order. This is
  the main practical gap.
- Separate, older feature: `payment_mode = cash` orders (`CashBookings`, owner-only, chosen at
  booking time, never touch Stripe). Not the same thing as the alternative method, which is a card
  booking the instructor later confirms as paid another way.

---

## 🎯 PHASE 1: PLANNING (exploration)

**Status:** ✅ Complete

### How the two modes differ today

| | Weekly | Pay in full (upfront) |
|---|---|---|
| Pending order has lesson payment rows | Yes, one DUE row per lesson | **No** — rows are created only when Stripe confirms |
| Confirmation creates | Order ACTIVE, first row stays DUE | Order ACTIVE + a **PAID** row per lesson (`ConfirmUpfrontPaymentAction`) |
| Payout source at sign-off | Lesson's own invoice charge | Order's `stripe_charge_id`, else **plain transfer from platform balance** |
| Sign-off payment guard | Lesson payment must be PAID | Order must be ACTIVE |
| Invoices / reminders | Weekly invoice sender (weekly-only) + reminders | None needed |
| Hold expiry | Alternative-payment staff bookings are never released | Released like any other booking |

### What breaks if we just drop the `isWeekly()` check

1. **Instructors could be paid twice (critical).** If an upfront order goes ACTIVE with no Stripe
   charge, `SignOffLessonAction` sees an active upfront order, and `CreateLessonPayoutAction` can't find
   a source charge, so it falls back to a **plain Stripe transfer from the platform balance**. The
   instructor gets the pupil's cash *and* a Stripe payout the platform never received. Today payouts
   are only skipped when `lessonPayment->isPaidByAlternativeMethod()`, so every lesson needs a correctly
   flagged payment row.
2. **`ConfirmAlternativePaymentBookingAction`** refuses non-weekly orders and only flags the "first
   lesson payment", which upfront orders don't have.
3. **"Paid" shown everywhere.** `Order::isPrepaid()` is true for any active upfront order, so the
   lesson resources (`LessonDetailResource`, `InstructorDayLessonResource`, `GetStudentLessonsAction`)
   show `payment_status = paid` for lessons with no payment row.
4. **Cancellation refunds.** `CancelBookingAction::wasPaid()` treats an active upfront lesson with no
   payment row as paid, so cancelling sends a Head Office **refund review** for money the platform
   never held.
5. **Revenue.** `Student::totalRevenuePence` counts the whole package total for active/completed
   upfront orders.
6. **Hold expiry.** `OrderService::releaseExpiredHolds()` only keeps *weekly* alternative-payment
   bookings, so upfront ones get released before the instructor can confirm.
7. **Emails and the guarantee.** The normal upfront confirmation (`confirmUpfrontPayment()`) sends
   "paid in full" emails and grants the free Pass Your Test Guarantee. Neither should happen here,
   because the weekly alternative flow waives fees and the guarantee.
8. **Switch-off hazard.** `SetStudentCashPaymentsAction` clears the confirmation on DUE rows when the
   switch goes off. For weekly that sends them back into invoicing. Upfront rows would never be
   invoiced (the invoice sender is weekly-only), but `GetPaymentRemindersDueAction` and
   `GetInvoiceDueWithin48HoursAction` are **not** weekly-only, so the pupil would be chased for a
   payment they have no way to make.

### Options considered

**A. Confirm the block, record each lesson at sign-off (recommended).**
On confirm: close Checkout, set the order ACTIVE, and create a **DUE** lesson payment row per lesson at
the lesson price (fees and guarantee waived, like weekly) with `alternative_payment_confirmed_at/by`
set. Each lesson then goes through the existing `prepareSignOff()` (no invoice to void) and
`RecordAlternativeLessonPaymentAction`, so it becomes PAID + ALTERNATIVE and no payout is made.
- Pros: reuses almost everything from the weekly flow. The rows stop the `isPrepaid()` "paid"
  display and the refund review, because `wasPaid()` checks the row first and DUE means unpaid.
  Sign-off skips the payout. The cash report already includes ALTERNATIVE rows.
- Cons: an upfront order with DUE rows is a new state. Every query that assumes "active upfront =
  paid" has to be checked (listed above).

**B. Record the whole block as PAID + ALTERNATIVE at confirmation.**
Simpler, but every lesson becomes "paid" before any money changes hands. Cancelling then goes to
Head Office refund review for money the platform never had, and nothing at sign-off asks the
instructor to confirm they were paid. Not recommended.

**C. Convert the order to `payment_mode = cash` on confirm.**
Reuses the cash flow (no payouts, recorded at sign-off), but it rewrites the order's history, skips
the owner-only rule in `CashBookings`, keeps the fee-inclusive totals from the card booking, and
mixes up two features the business keeps separate. Not recommended.

### Decisions needed from product
1. **Single lessons only, or any pay-in-full package?** Pay in full is mainly used for single
   lessons now, so starting with single lessons would close the main gap with less risk.
2. **Confirm the whole block, or each lesson?** Under option A the instructor confirms once at
   booking, then ticks the risk box at each sign-off (same as weekly). Is that the right UX for a
   block?
3. **Price recorded.** Weekly records the lesson price (fees waived). Same for upfront?
   (Recommended: yes.)
4. **Pupil booked and paid themselves (website / learner app).** Keep them card-only, like weekly?
   (Recommended: yes.)
5. **Hold expiry.** Should pending upfront staff/instructor bookings of these pupils also never be
   released automatically, like weekly?
6. **Switch turned off after confirmation.** Weekly falls back to invoicing. Upfront has no
   per-lesson invoicing. Options: block turning the switch off while a confirmed upfront order has
   unsigned lessons, or leave those rows confirmed and flag them for Head Office. (Recommended:
   block, with a clear message.)
7. **Pupil pays by card after confirmation.** Checkout is closed on confirm, so they can't. Is that
   acceptable for upfront? (Same as weekly week one.)

### Proposed implementation outline (option A, once decisions are made)
- `AlternativePayments::allowedForOrder()` → allow `isWeekly() || isUpfront()` (optionally only single-lesson).
- `ConfirmAlternativePaymentBookingAction` → upfront branch: create DUE rows per lesson at
  `lesson.amount_pence`, `test_pass_guarantee_pence = 0`, confirmation fields set, `payment_method` null.
- `AlternativePayments::canSignOffWithAlternativePayment()` → already works once `allowedForOrder()`
  allows upfront (needs active order + DUE row).
- `SignOffLessonAction` → for upfront, also require the lesson's row (if any) to be PAID, so an
  upfront lesson with a DUE row can never reach `CreateLessonPayoutAction`. This is the critical guard.
- `CreateLessonPayoutAction` → defence in depth: refuse a payout for an upfront order with no
  `stripe_charge_id`/`stripe_payment_intent_id`.
- `OrderService::releaseExpiredHolds()` → extend the "keep" rule to upfront if decision 5 is yes.
- `GetConfirmableAlternativePaymentBookingsAction` → include upfront; `amount_pence` = sum of lesson prices.
- `Student::totalRevenuePence` → for upfront orders that have alternative rows, sum PAID rows instead of package total.
- `SetStudentCashPaymentsAction` → handle upfront confirmed rows per decision 6.
- `bookingRefusalMessage()` copy, `.claude/api.md`, web `LessonsSubTab.vue` copy ("first lesson" →
  "booking"), and Pest tests that mirror the weekly alternative tests.
- No migration needed: the existing `lesson_payments` columns are enough.

### Complexity Assessment
- [ ] Low
- [x] Medium: backend changes to about 8 actions/services plus copy changes in the web UI. Risk is
  concentrated in sign-off and payouts, so the new guards there must have tests.

### Reflection
**What went well:**
- The weekly design (rows flagged as confirmed, then recorded at sign-off) carries over to upfront
  with one structural change: creating rows at confirmation.

**What could be improved:**
- `.claude/payment-pipeline.md` is referenced by `CLAUDE.md` but does not exist in the repo (checked
  `origin/main` too), so the critical-file list couldn't be cross-checked.

**Risks identified:**
- Payout via the plain-transfer fallback (point 1 above) is the main risk. It must be guarded in
  two places.
- "Active upfront = paid" is assumed in several queries. Any missed spot shows a wrong paid status
  or triggers a wrong refund review.

---

## 🔨 PHASE 2: IMPLEMENTATION

**Status:** ⏸️ Not Started — waiting on the product decisions above.

---

## 💭 PHASE 3: FINAL REFLECTION & DOCUMENTATION

**Status:** ⏸️ Not Started

---

💳 Payment impact: NONE in this change (documentation only). The proposed implementation would TOUCH
the payment pipeline: `ConfirmAlternativePaymentBookingAction`, `SignOffLessonAction`,
`CreateLessonPayoutAction`, `OrderService::releaseExpiredHolds()`, `SetStudentCashPaymentsAction`.
Before release, run the alternative-payment, sign-off, payout, hold-release and cancellation tests,
and check by hand that a confirmed upfront lesson creates no `payouts` row.
