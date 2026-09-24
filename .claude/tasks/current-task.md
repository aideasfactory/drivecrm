# Task: Staff bookings via the onboarding form

**Created:** 2026-09-24
**Last Updated:** 2026-09-24
**Status:** Complete

Admin/bookings team can open the public onboarding form in "staff booking"
mode and book lessons for a student without being sent to Stripe.

## Phase 1: Planning ✅
- ✓ Traced Step 6: upfront redirects to Stripe Checkout; weekly activates the
  order, emails the first Stripe invoice and a confirmation to the student.
- ✓ Found existing payment-link email pipeline (OrderService /
  SendPaymentLinkEmailAction / PaymentLinkCheckoutController).

## Phase 2: Implementation ✅
- ✓ `/onboarding?staff_booking=1` flags the enquiry (`data.staff_booking`),
  only for signed-in owner users.
- ✓ Step 6 upfront + staff booking → `OrderService::sendPaymentLink()` emails
  the Stripe Checkout link; staff browser goes to the completion page.
- ✓ Weekly staff bookings use the existing flow unchanged.
- ✓ Staff-specific copy on Step 6, Complete page and payment-link email.
- ✓ "Book lessons for a student" button on Students page; "Purchase lessons"
  link on a student's Lessons tab passes the staff flag.
- ✓ Feature tests in `tests/Feature/Onboarding/StaffBookingTest.php`.

## Phase 3: Reflection ✅
- Unpaid upfront orders keep draft calendar items only until the nightly
  `calendar:cleanup-drafts` run (same as instructor payment links).
- No migrations or API endpoints changed.
