# Task: Instructor admin package hide/remove

## Overview

Instructors need to hide or remove packages they create, but only from
the CRM admin (instructor Details → Packages). Hard delete is unsafe:
`orders.package_id` and `slot_offers.package_id` both `ON DELETE CASCADE`,
which would wipe orders, lessons, and payments. Lessons and
`lesson_payments` do not store `package_id` (they hang off orders).

Decision: mark packages `active = false` (column already exists). Keep
the row so payment/order history still resolves. Mobile API already
returns only active packages — no mobile API change.

## Phase 1: Planning ✅

### Current state
- Admin list: `GET /instructors/{id}/packages` via InstructorController
  → InstructorService → GetInstructorPackagesAction (`onlyActive=true`).
- Create: `POST /instructors/{id}/packages`. Update: `PUT /packages/{id}`.
- UI: `EditDetailsSubTab.vue` has create/edit and an Active/Inactive
  badge, but no hide/remove action. Inactive rows never appear because
  the list filters to active.
- Mobile: `GET /api/v1/instructor/packages` and student packages already
  filter `active = true`. Booking/offer-slot UIs also filter client-side.
- `DeletePackageAction` hard-deletes (owner `/packages` only). Unused by
  instructors (`RestrictInstructor` blocks `/packages`).

### Approach
1. Deactivate (`active = false`) instead of SoftDeletes or hard delete.
   SoftDeletes would hide `$order->package` on payment screens.
2. Admin GET returns all instructor packages so hidden ones stay visible.
3. `DELETE /instructors/{instructor}/packages/{package}` deactivates.
   `PATCH .../restore` reactivates. Ownership check on instructor_id.
4. UI: Remove + confirm Dialog; Restore on inactive rows. Admin only.
5. Invalidate instructor package cache on write. No mobile API delete.

### Tasks
- [x] Trace package FKs on orders, lessons, payments, slot offers
- [x] Choose deactivate over hard delete / SoftDeletes

### Reflection
Reuse `packages.active` and existing Controller → Service → Action
chain. No migration. Web admin only — not a mobile API.

**Last Updated:** 2026-09-10.

## Phase 2: Implementation ✅

### Currently working on
Complete.

### Tasks
- [x] SetInstructorPackageActiveAction + InstructorService methods
- [x] InstructorController deactivate/restore + admin list includes inactive
- [x] Routes under instructor-scoped admin paths
- [x] EditDetailsSubTab Remove/Restore + confirm Dialog
- [x] Update database-schema.md (no migration; document hide behaviour)
- [x] No api.md change (mobile API shape unchanged)

### Reflection
Deactivate keeps `package_id` on orders and slot offers, so Stripe
payment records and lesson history stay queryable. Admin list now
passes `onlyActive: false` so hidden packages remain visible and
restorable. Booking sheets still filter `active` client-side; mobile
list endpoints are unchanged.

I've updated database-schema.md to reflect the hide-not-delete
behaviour and the CASCADE risk of hard delete.

## Phase 3: Reflection ✅

Staff and instructors can Remove a bespoke package from Details →
Packages. That only sets `active = false`. Restore brings it back.
Payments, lessons, and orders are untouched.

### Tasks
- [x] Document decisions and leftover risks

### Reflection
Leftover: owner `/packages` still hard-deletes via
`DeletePackageAction` — that would CASCADE-delete orders if used on a
package that has been purchased. Instructor package *edit* still
`PUT /packages/{id}`, which `RestrictInstructor` redirects away from
for instructor-role users (pre-existing). No PHP in this environment
to regenerate Wayfinder; admin UI uses the same axios paths as the
rest of the Details tab.

No api.md update — web admin JSON only, mobile `/api/v1` unchanged.
