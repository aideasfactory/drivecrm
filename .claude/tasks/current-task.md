# Task: Auto-add lesson fee to instructor profile on payment

## Overview

When a pupil pays for a lesson, the **lesson fee only** (not booking fee or
digital charge) must appear automatically on the instructor profile Finances
tab (`instructor_finances`). Stripe Connect transfers / `payouts` stay on
lesson sign-off and are out of scope.

Ticket: 01a06bcc-d752-7094-beb8-0984be625cf3
Hard rule: no tests.

## Phase 1: Planning ✅

### Current state
- Weekly pay: `invoice.paid` marks `lesson_payments` PAID. Amount includes
  lesson + booking + digital (split via `LessonPayment::weeklyBreakdown`).
- Upfront pay: `checkout.session.completed` creates PAID `lesson_payments`
  at `lesson.amount_pence` (lesson price only).
- Instructor profile Finances tab lists `instructor_finances` (payments /
  expenses). Staff and the mobile app already read this ledger.
- `payouts` are Stripe transfers created at sign-off from
  `$lesson->amount_pence`. Separate concern; backlog may cover transfers.

### Approach
1. New payment category `lesson_fee`.
2. On paid lesson, create an idempotent `instructor_finances` payment
   (`type=payment`, `category=lesson_fee`) for the lesson-fee pence only.
3. Link via unique nullable `lesson_payment_id` so webhooks cannot double-post.
4. Weekly: use `weeklyBreakdown()['lesson']`. Upfront: use
   `lesson.amount_pence` (already excludes platform fees).

### Tasks
- [x] Confirm landing surface (instructor_finances, not payouts)
- [x] Confirm weekly vs upfront amount rules

### Reflection
The Finances tab is the instructor-profile money ledger. Recording there at
payment time matches “added to the instructor's profile” without touching
Stripe transfers.

**Last Updated:** 2026-09-04.

## Phase 2: Implementation ✅

### Currently working on
Complete.

### Tasks
- [x] Migration: `lesson_payment_id` + `lesson_fee` tax mapping
- [x] Config + models + RecordLessonFee action + InstructorService
- [x] Call from weekly `invoice.paid` and upfront checkout
- [x] Update database-schema.md and api.md

### Reflection
Existing Finances UI and mobile list pick up the new category from config.
No Stripe transfer changes. Webhook finance failures are logged so payment
confirmation still completes.

## Phase 3: Reflection ✅

### Tasks
- [x] Document decisions

### Reflection
Landing surface is `instructor_finances` (instructor profile Finances tab),
not `payouts`. Amount is lesson fee only. Idempotent via unique
`lesson_payment_id`. Historical paid lessons are not backfilled except when
`GetStudentPaymentsAction` creates a missing PAID upfront payment row.
