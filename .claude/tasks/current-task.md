# Task: Admin and instructor learner profile deletion

## Overview

Staff can unassign a learner from an instructor but cannot remove the
profile. Duplicate learners (e.g. XXXXXXXXAbhinay James) stay on the
Students list with only Assign Instructor available. This work adds a
safe delete for owners and for instructors on their own pupils.

Hard-deleting `students` would cascade-delete `orders` (and therefore
lessons, invoices, and payouts). Staff delete is therefore a soft delete
plus login lock, so listings hide the profile while historical records
keep their `student_id`.

## Phase 1: Planning ✅

### Current state
- `DELETE /students/{student}/remove` and API `DELETE /api/v1/students/{id}`
  only set `instructor_id = null`.
- `DeleteStudentAction` hard-deletes the row and is unused.
- `ProcessAccountDeletionAction` already anonymises rather than hard-deletes
  because user/student cascades would wipe lesson/payment history.
- Unassigned duplicates are managed from the Pupils Assign Instructor sheet.
- Instructors cannot open `/pupils` but can manage their pupils on
  `/instructors/{id}` (Actions tab).

### Approach
1. Soft-delete `students` (`deleted_at`). Default queries hide deleted rows.
2. Lock the linked user (revoke tokens, randomise password, unique deleted
   email) so the duplicate cannot log in or reset a password.
3. Keep student PII on the soft-deleted row; `belongsTo` student relations
   use `withTrashed()` so invoices/lessons still resolve.
4. Web `DELETE /students/{student}` for owners (any pupil) and instructors
   (assigned pupils only). Do not change the mobile API unassign endpoint.
5. UI: delete control on the Assign Instructor sheet and on the student
   Actions tab, each with a confirmation dialog.

### Tasks
- [x] Confirm data-model risk (orders cascade) and choose soft delete
- [x] Plan auth (owner + assigned instructor) and UI surfaces

### Reflection
Soft delete is the only safe general delete. Hard delete would wipe
financial history even for a "duplicate" if that row later had an order.
Unassigned duplicates are owner-only because instructors cannot open
`/pupils` and unassigned rows have `instructor_id = null`.

**Last Updated:** 2026-09-03.

## Phase 2: Implementation ✅

### Currently working on
Complete.

### Tasks
- [x] Migration: `students.deleted_at`
- [x] Student SoftDeletes + withTrashed on historical relations
- [x] Rewrite DeleteStudentAction (soft delete + lock user)
- [x] StudentService, policy, form request, PupilController, route
- [x] Assign Instructor sheet + Actions tab delete UI
- [x] Update database-schema.md

### Reflection
Mobile `DELETE /api/v1/students/{id}` is still unassign-only — this ticket
is admin CRM. `DeleteStudentAction` is now the shared web path.

## Phase 3: Reflection ✅

Staff can delete duplicate and unwanted learner profiles from the Assign
Instructor sheet (owners, including unassigned rows) and from the student
Actions tab (owners and the assigned instructor). Profiles leave listings
without cascading away lessons or invoices. Linked logins are locked.

### Tasks
- [x] Document decisions and leftover risks

### Reflection
Leftover: no restore UI; GDPR anonymise and staff soft-delete remain two
paths. Instructors still cannot delete an unassigned profile they created
and then removed — owners handle that from Students.
