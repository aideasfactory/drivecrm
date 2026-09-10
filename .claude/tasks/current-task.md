# Task: Pupil 48-hour reschedule notice copy

## Overview

Mission Control ticket `01a08ab7-4436-7047-843c-aed5e43170ab`:
confirmation to pupils must say they have to give 48 hours' notice to
reschedule. Copy lives in admin-editable `email_templates` (defaults in
`EmailTemplateCatalog`) and two web confirmation pages that still said
24 hours. Mobile app is out of scope. No tests.

## Phase 1: Planning ✅

### Current state
- Pupil reschedule emails (`learner.lesson_rescheduled`,
  `learner.lessons_bulk_rescheduled`) only said contact the instructor.
- Booking confirmation (`learner.order_confirmation`) had no
  reschedule policy.
- Payment confirmed (`learner.lesson_payment_received`) said "contact
  us as soon as possible".
- Stored `email_templates` rows are not overwritten by sync; restore
  uses catalog defaults.
- Onboarding Complete and Payment Link Success said 24 hours.

### Approach
1. Update catalog defaults for those four learner templates.
2. Data-migrate stored rows that still match the old default body.
3. Fix the two pupil web confirmation pages (not the mobile app).

### Tasks
- [x] Trace mailables, catalog keys, and confirmation pages
- [x] Choose copy that states the 48-hour notice requirement

### Reflection
Catalog + exact-match data migration preserves staff edits. Vue
confirmation pages are in scope because they previously told pupils
the wrong notice period.

**Last Updated:** 2026-09-10.

## Phase 2: Implementation ✅

### Currently working on
Complete.

### Tasks
- [x] Update EmailTemplateCatalog learner copy
- [x] Data-migrate unmodified stored templates
- [x] Update database-schema.md
- [x] Fix Onboarding Complete and Payment Success copy

### Reflection
Four learner catalog bodies now include "Please give at least 48
hours' notice if you need to reschedule a lesson." The data migration
only replaces exact previous defaults. Instructor emails and the
mobile app were left unchanged.

I've updated database-schema.md to reflect the migration changes.

## Phase 3: Reflection ✅

Staff-edited email templates are not overwritten. Marketing bullets
on booking/onboarding step 1 still say "reschedule anytime" — that
was left as a follow-up, not a confirmation message.

### Tasks
- [x] Document decisions and leftover risks

### Reflection
Leftover: if an owner already customized one of the four templates,
they still need to add the 48-hour line (or restore defaults) in
`/email-templates`. No API change. No mobile app change.
