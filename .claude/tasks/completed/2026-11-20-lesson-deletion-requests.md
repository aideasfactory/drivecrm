# Task: Lesson Deletion Requests (instructor request → Head Office approval)

**Created:** 2026-11-20
**Last Updated:** 2026-11-20
**Status:** Complete

---

## 📋 Overview

### Goal
Instructors can ask Head Office to permanently delete a past lesson logged by mistake (duplicate, or never happened). Owners and the bookings team approve (lesson hard-deleted) or reject (lesson kept) on a new CRM page. The instructor gets a push either way. CRM half of a two-repo ticket; the app (drivecrm-app) builds the UI from the API contract in `.claude/api.md`.

### Success Criteria
- [x] Instructor API: request (required reason) and cancel, own lessons only
- [x] Lesson payloads show `deletion_pending`, `deletion_request`, `can_request_deletion`, `deletion_blocked_reason`
- [x] Only past, unpaid, not signed-off lessons with no payout qualify; checked at request **and** approval
- [x] CRM list for owners + restricted owners (bookings team) with Approve (confirm) / Reject and a pending count badge
- [x] Approve permanently deletes the lesson and safe dependents; the request row survives as the audit record
- [x] Push to the instructor on approve/reject; no pupil emails; approver logged

### Context
- Sam's decisions: approve = permanent delete; only past, unpaid, not signed off; block approval when payment or payout exists; owners and bookings team decide.
- Existing owner "delete signed-off lesson" (`CancelBookingAction::deleteSignedOffLesson`) cancels rather than deletes; it is unchanged.

---

## 🎯 PHASE 1: PLANNING

**Status:** ✅ Complete

### Decisions Made
- New `lesson_deletion_requests` table with name/date snapshots and `pending_lesson_id` UNIQUE (one pending per lesson at DB level).
- Eligibility lives in one action (`GetLessonDeletionBlockerAction`) used by the API flags, the request, the admin list and approval.
- "Unpaid" means no money moved at all: no payout, no paid/refunded payment, no open Stripe invoice, not upfront-paid, not imported-as-paid, no instructor finance row. Cash lessons qualify (cash is recorded at sign-off only).
- Page lives under Instructors (`/instructors/lesson-deletion-requests`) so restricted owners reach it through the existing `instructors/*` allow-list.

### API Endpoints
- `POST /api/v1/instructor/lessons/{lesson}/deletion-request`
- `DELETE /api/v1/instructor/lessons/{lesson}/deletion-request`
- Web: `GET /instructors/lesson-deletion-requests`, `POST .../{id}/approve`, `POST .../{id}/reject`

### Reflection
**Risks identified:**
- Hard delete must never remove money records → re-check eligibility under a row lock at approval.

---

## 🔨 PHASE 2: IMPLEMENTATION

**Status:** ✅ Complete

### Code Locations
**Files created:**
- `database/migrations/2026_11_20_100000_create_lesson_deletion_requests_table.php`
- `app/Enums/LessonDeletionRequestStatus.php`, `app/Models/LessonDeletionRequest.php`
- `app/Actions/Lesson/{GetLessonDeletionBlocker,RequestLessonDeletion,CancelLessonDeletionRequest,ApproveLessonDeletionRequest,RejectLessonDeletionRequest,PermanentlyDeleteLesson,NotifyInstructorOfLessonDeletionDecision,GetLessonDeletionRequests}Action.php`
- `app/Services/LessonDeletionRequestService.php`, `app/Support/LessonDeletionFlags.php`
- `app/Http/Controllers/LessonDeletionRequestController.php`
- `app/Http/Requests/Api/V1/StoreLessonDeletionRequest.php`, `app/Http/Resources/V1/LessonDeletionRequestResource.php`
- `resources/js/pages/LessonDeletionRequests/Index.vue`

**Files modified:**
- `app/Models/Lesson.php` (relations), `routes/api.php`, `routes/web.php`
- `app/Http/Controllers/Api/V1/InstructorLessonController.php`, `InstructorCalendarController.php`
- Resources: `InstructorDayLessonResource`, `LessonResource`, `LessonDetailResource`, `CalendarItemResource`
- Actions: `GetInstructorLessonsInRangeAction`, `GetStudentLessonsAction`, `GetStudentLessonDetailAction`, `MergeInstructorAccountsAction`
- `app/Http/Middleware/HandleInertiaRequests.php` (pending count), `app/Support/PushNotificationCatalog.php`
- `resources/js/components/AppSidebar.vue`, `resources/js/types/index.ts`

### Reflection
**What went well:**
- One blocker action keeps app flags, admin list and approval consistent.

**Technical debt created:**
- None known. The CRM's own lesson screens don't show the pending tag (not required for V1).

---

## 💭 PHASE 3: FINAL REFLECTION & DOCUMENTATION

**Status:** ✅ Complete

### Documentation Updates
- `.claude/api.md` — two endpoints, payload fields, push payloads, changelog
- `.claude/database-schema.md` — `lesson_deletion_requests` table, lesson relations, ERD line

### Known Issues
- **Issue:** An approved request's push names the lesson by its old id, which no longer exists.
  - **Workaround:** App refreshes the diary / lesson list on `lesson_deletion_approved`.

---

## ✅ TASK COMPLETE

**Completed:** 2026-11-20

### Final Summary
Instructors can request deletion of past mistaken lessons via the API; owners and the bookings team approve (hard delete, audited) or reject on a new CRM page with a pending badge; the instructor is pushed the outcome.

### Review cycle 1 fix (invoicing race)
- `SendLessonInvoiceAction` now locks the `lesson_payments` row, re-checks it (exists, `due`, uninvoiced, no pending deletion request) and holds the lock across the Stripe call until `stripe_invoice_id` is saved; the reminder email is sent after commit. Skips return `skipped: true` without calling Stripe.
- `lessons:send-invoices` and `OrderService::sendNextDueInvoice()` exclude lessons with a pending request; `sendNextDueInvoice()` moves on to the next candidate when one is skipped.
- Request creation and approval lock the payment row before the eligibility check.
- Steps after the approval commit (order close, renumbering, cache, activity logs) are guarded so a failure there no longer returns a 500.

💳 Payment impact: TOUCHED — invoicing (`SendLessonInvoiceAction`, `lessons:send-invoices`, `OrderService::sendNextDueInvoice`) now skips lessons with a pending deletion request and locks the payment row for the duration of the Stripe invoice call; Stripe call arguments, amounts and webhooks unchanged. Approval deletes the lesson's `lesson_payments` row, but only when it is `due` with no open Stripe invoice (unpaid, never invoiced or invoice cancelled); eligibility re-checked under lock at approval. No Stripe calls, no amounts, payouts, invoices, holds or webhooks changed. Read-only: lesson payloads now also select `stripe_invoice_id` / `invoice_cancelled_at`. An active order left with no open lessons is closed (`completed`/`cancelled`). Before release: approve an unpaid weekly lesson and confirm no Stripe invoice/payout exists for it; confirm paid, upfront, invoiced and paid-out lessons are refused.
