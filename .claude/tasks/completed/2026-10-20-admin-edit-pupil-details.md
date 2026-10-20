# Admin: view and edit pupil details on the pupil profile

**Status:** ✅ Complete — 2026-10-20
**Ticket:** Mission Control (Jack) — "the ability to edit pupil details on the admin side"

## Overview
Staff couldn't edit a pupil's details from the admin side, before or after the pupil was booked with an instructor, and couldn't see the address or postcode on the pupil profile. The postcode only showed on the Lessons booked page.

## Phase 1: Investigation ✅
- ✓ No web route or UI to update a student existed. `PupilController` only had status, cash payments, password, remove and delete actions, and the profile header (`StudentTab.vue`) was read-only
- ✓ The only update endpoint is the mobile `PUT /api/v1/students/{id}`. `StudentPolicy::update` only lets the pupil or the instructor in `students.instructor_id` through, and never owners. Once a booking's first payment sets `instructor_id` (`AssignStudentOnPaidBookingAction`), only that instructor (in the app) or the pupil can change the record
- ✓ `students` had no address, postcode or DOB columns. Lessons booked rebuilds the postcode from the booking enquiry JSON (step 5 pickup postcode, then step 1 postcode) or the default pickup point
- ✓ Bird is only upserted from booking enquiries (`SyncBookingEnquiryToBirdJob`). Nothing syncs to Bird when a student record changes

## Phase 2: Implementation ✅
- ✓ Migration: `address_line_1`, `address_line_2`, `city`, `postcode`, `date_of_birth` on `students` (all nullable)
- ✓ `StudentPolicy::updateDetails` (owners always; instructors for their own pupils)
- ✓ `UpdatePupilDetailsRequest` (UK postcode normalised + validated, DOB not in the future, email required + unique against `users` when the pupil has a login)
- ✓ `PUT /students/{student}` → `PupilController::update` → `StudentService::update` → `UpdateStudentAction` (reused; login name/email sync unchanged). The activity log now records only changed fields
- ✓ `GetStudentBookingDetailsAction` + `StudentService::getBookingDetails`: address, postcode and DOB from the booking enquiry for pupils with nothing stored
- ✓ `GET /students/{student}` returns the new fields, booker fields and `booking_details`
- ✓ `PupilDetailsCard.vue` on the Overview sub-tab shows the details, with "From booking" badges for fallback values, and an Edit Details sheet. The header shows the postcode
- ✓ GDPR anonymisation nulls the new columns
- ✓ `database-schema.md` and the `api.md` changelog updated

## Reflection
Onboarding doesn't write the new columns, so checkout code stays untouched. New pupils show their booking address as a "From booking" fallback until staff save it. If the business wants it stored automatically, add the fields to `CreateUserAndStudentFromEnquiryAction::getStudentData` as a separate, payment-reviewed change.

💳 Payment impact: NONE — no changes to orders, checkout, Stripe, webhooks, holds, invoices, payouts, refunds or pricing. `GetStudentBookingDetailsAction` only reads `orders.id` and enquiry JSON.
