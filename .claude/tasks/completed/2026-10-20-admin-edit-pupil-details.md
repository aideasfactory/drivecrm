# Admin: view and edit pupil details on the pupil profile

**Status:** ✅ Complete — 2026-10-20 (revised the same day per Sam: no new address fields)
**Ticket:** Mission Control (Jack) — "the ability to edit pupil details on the admin side"

## Overview
Staff couldn't edit a pupil's details from the admin side, before or after the pupil was booked with an instructor, and couldn't see the address or postcode on the pupil profile. The postcode only showed on the Lessons booked page.

## Phase 1: Investigation ✅
- ✓ No web route or UI to update a student existed. `PupilController` only had status, cash payments, password, remove and delete actions, and the profile header (`StudentTab.vue`) was read-only
- ✓ The only update endpoint is the mobile `PUT /api/v1/students/{id}`. `StudentPolicy::update` only lets the pupil or the instructor in `students.instructor_id` through, and never owners. Once a booking's first payment sets `instructor_id` (`AssignStudentOnPaidBookingAction`), only that instructor (in the app) or the pupil can change the record
- ✓ Neither `students` nor `users` had address, postcode or DOB columns. The only pupil address storage is `student_pickup_points`; the only DOB is `enquiries.data → steps.step5.learner_dob`
- ✓ Bird is only upserted from booking enquiries (`SyncBookingEnquiryToBirdJob`). Nothing syncs to Bird when a student record changes

## Phase 2: Implementation ✅
- ✓ Migration: `students.date_of_birth` only (nullable). No student address columns (Sam's decision)
- ✓ `StudentPolicy::updateDetails` (owners always; instructors for their own pupils)
- ✓ `UpdatePupilDetailsRequest`: name, email (required + unique against `users` when the pupil has a login), phone, DOB (not in the future), booker fields, and optional `pickup_point` {label, address, postcode} with the pickup point postcode rules
- ✓ `PUT /students/{student}` → `PupilController::update` → `StudentService::updateDetails` (transaction) → `UpdateStudentAction` (login name/email sync unchanged; activity log lists only changed fields) + create/update the default pickup point via `CreatePickupPointAction` / `UpdatePickupPointAction`
- ✓ `GetDefaultPickupPointAction`: `is_default` row, else the oldest (same rule as Lessons booked)
- ✓ `GetStudentBookingDetailsAction`: booking-form pickup address, postcode (step 5 pickup postcode, then step 1 postcode) and DOB, used only when there's no pickup point / stored DOB
- ✓ `GET /students/{student}` returns `date_of_birth`, booker fields, `default_pickup_point` and `booking_details`
- ✓ `PupilDetailsCard.vue`: shows the default pickup point (or "From booking" fallbacks). Its Edit Details sheet has a "You're editing this pupil's default pickup point" notice, a "Manage all pickup points" link (Actions sub-tab), and a "Use booking address" shortcut when the pupil has no pickup point
- ✓ Header postcode comes from the default pickup point, else the booking (marked "From booking")
- ✓ GDPR anonymisation nulls `date_of_birth`
- ✓ `database-schema.md` and the `api.md` changelog updated

## Reflection
Saving never creates a pickup point silently from booking data: staff have to enter an address or click "Use booking address". GDPR anonymisation doesn't delete `student_pickup_points` (that was already the case, and is outside this task).

💳 Payment impact: NONE — no changes to orders, checkout, Stripe, webhooks, holds, invoices, payouts, refunds or pricing. `GetStudentBookingDetailsAction` only reads `orders.id` and enquiry JSON.
