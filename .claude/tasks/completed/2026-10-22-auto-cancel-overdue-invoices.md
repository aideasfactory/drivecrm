# Auto-cancel weekly lesson invoices left unpaid 7+ days after they fell due

**Status:** ✅ Complete — 2026-10-22
**Ticket:** Mission Control (Gav, urgent, due 23 Oct 2026) — "an automated cancellation process for unpaid invoices"

## Overview
Invoices were still going out for long-past lessons (e.g. sent 22 Oct for a 12 Oct lesson). Backstop: a weekly
lesson payment still unpaid more than 7 days after it fell due is cancelled automatically: Stripe invoice voided,
no more invoices, reminders or resends. Instructors still cancel lessons properly; the lesson itself is not touched.

## Phase 1: Investigation ✅
- ✓ Weekly lesson invoices: `SendLessonInvoiceAction` (creates + finalises the Stripe invoice, emails `LessonPaymentReminderNotification`, queues a push). Callers: `OrderService::sendNextDueInvoice` (on sign-off / activation) and the hourly `lessons:send-invoices` catch-up
- ✓ Reminders: `reminders:send` → `GetPaymentRemindersDueAction` / `SendPaymentReminderAction` (48h before the lesson). Manual resend: `ResendLessonInvoiceAction` via `PupilController::resendLessonInvoice`
- ✓ The only Stripe "payment request" for a weekly lesson is its Stripe invoice (`lesson_payments.stripe_invoice_id`). `StripeService::voidInvoice` already voids open / deletes draft / reports paid
- ✓ Why 22 Oct for a 12 Oct lesson: both catch-ups pick any `due` payment with no invoice and `due_date <= now + 48h`, with **no lower bound**, so a payment that was blocked for weeks is invoiced the moment the block lifts. `5dc623c` ("booking fix", 21 Oct 20:50) removed the `stripe_customer_id` requirement from `lessons:send-invoices`; pupils without a Stripe customer (never opened a card checkout, e.g. confirmed by alternative payment / instructor or staff booking) had been skipped every hour since their due date, and the first hourly run after deploy swept up the whole backlog. Other ways the same gap is hit: `SetStudentCashPaymentsAction` turning alternative payments off clears confirmations on old `due` rows; `sendNextDueInvoice` invoices the *oldest* uninvoiced lesson on sign-off

## Phase 2: Implementation ✅
- ✓ Migration `2026_10_22_120000_add_auto_cancellation_to_lesson_payments_table`: `auto_cancelled_at`, `auto_cancellation_reason`
- ✓ `config/booking_payments.php` `auto_cancel_overdue_invoice_days` (env `BOOKING_AUTO_CANCEL_OVERDUE_INVOICE_DAYS`, default 7); `BookingPayments::overdueInvoiceCutoffDate()`
- ✓ `GetOverdueLessonPaymentsToAutoCancelAction`: `due`, not auto-cancelled, no alternative confirmation, `due_date` before today (UK) − 7 days, lesson not completed/cancelled and no payout, weekly order not still pending
- ✓ `AutoCancelOverdueLessonPaymentAction`: re-checks the row, voids the Stripe invoice first (paid → left to webhook; failure → retried next run), then a conditional update, log + student activity
- ✓ `lessons:auto-cancel-overdue-invoices`, hourly, `withoutOverlapping`, scheduled before `lessons:send-invoices`
- ✓ Guards: `SendLessonInvoiceAction`, `SendLessonInvoices`, `OrderService::sendNextDueInvoice` (also never invoice past the cutoff), `GetPaymentRemindersDueAction`, `ResendLessonInvoiceAction`, `PupilController::resendLessonInvoice`
- ✓ Moving the lesson (`UpdateCalendarItemAction`) revives an auto-cancelled payment so it is invoiced afresh
- ✓ Lessons tab: "Invoice auto-cancelled" badge, Resend hidden. Mobile API unchanged (`LessonResource` whitelists fields)
- ✓ `.claude/database-schema.md` updated

## Reflection
Additive and reversible: no status enum change, no amount change, no webhook change. The cause of the late invoice
is inferred from code history; confirm on production with the lesson payment's `created_at`, the student's
`stripe_customer_id` creation time and the activity log ("Payment reminder email sent ... for lesson on 12 Oct 2026").
Follow-ups: show the auto-cancel state in the pupil/instructor mobile apps; consider whether Stripe's own overdue
reminder emails are switched on in the Stripe dashboard (Drive can't stop those except by voiding, which this does).

💳 Payment impact: TOUCHED — new `lessons:auto-cancel-overdue-invoices` voids Stripe invoices (via the existing `StripeService::voidInvoice`) for weekly lesson payments unpaid 7+ days past due and sets `auto_cancelled_at`/`auto_cancellation_reason`. Invoice senders (`SendLessonInvoiceAction`, `SendLessonInvoices`, `OrderService::sendNextDueInvoice`), the 48h reminder and resend skip those rows, and the two catch-ups no longer invoice anything past the cutoff. Unchanged: amounts, invoice creation, checkout, webhooks, payouts, refunds, statuses (paid/refunded rows are never selected or updated). Risks: a lesson that did happen but wasn't signed off within 6 days of its date (due 2 days before; cancelled on the 8th day after the due date) loses its open invoice (revived if the lesson is moved; otherwise collect manually); the migration must run before deploy. Before release: run the migration, run `php artisan lessons:auto-cancel-overdue-invoices` once by hand and check the log/summary, and confirm in Stripe that the listed invoices show as void.
