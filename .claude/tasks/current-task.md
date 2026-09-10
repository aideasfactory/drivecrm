# Task: Admin resource/folder re-ordering

## Overview

Owners need to control the display order of videos/resources (and
folders) in the admin Resources library. The mobile app currently
shows items in title order because every row has `sort_order = 0`.
Admin drag-and-drop should persist `sort_order`; the mobile API
must return items in that order and expose the field.

Ticket: 01a08a7d-05af-7287-80d0-df7bb3bed854 (urgent bug).
# Task: Folder visibility control for instructors and pupils

**Created:** 2026-09-10
**Last Updated:** 2026-09-10
**Status:** Complete

---

## Overview

Pupils could see instructor-only folders (e.g. VTS, Standards Check Success)
in the mobile library even when those folders contained no pupil resources.
Admin can now set folder visibility (instructor / pupil / both). The mobile
API hides folders the caller should not see and exposes `visibility` on
folder objects.

### Success Criteria
- [x] Admin can set folder visibility (instructor / pupil / both) on create and edit
- [x] Student resource tree and related student library endpoints omit hidden folders
- [x] Instructor resource tree omits folders hidden from instructors
- [x] Folder `visibility` is exposed on API folder objects
- [x] `.claude/api.md` and `.claude/database-schema.md` are updated
- [x] No tests added (HARD RULE)

---

## PHASE 1: PLANNING

**Status:** ✅ Complete

### Tasks
- [x] Trace folders, admin UI, and mobile resource APIs
- [x] Choose storage (`visibility` enum) and API shape

### Decisions Made
- Single `visibility` column (`student` | `instructor` | `both`), default `both`.
  API uses `student` (not `pupil`) to match existing resource `audience`.
- Server-side filter AND include `visibility` on folder objects for the app.
- Student tree also prunes empty folders after the audience filter.

### Reflection
Reusing the resource audience button-group and the instructor-tree prune
kept the change small. No mobile app work.
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
# Task: Admin Schedule Weekly calendar view

## Overview

Instructors in admin Schedule (`/instructors/{id}?tab=schedule`) can see
Today and Monthly calendar modes. They need a Weekly view so they can
see that week's diary layout. Web admin only — do not change the mobile
API or app.

## Phase 1: Planning ✅

### Current state
- `resources.sort_order` and `resource_folders.sort_order` already exist
  (integer, default 0). Models are fillable and cast.
- Folder/resource relations and tree actions already `orderBy('sort_order')`
  then name/title. All existing rows are `0`, so the tie-break is
  alphabetical — e.g. "Moving Off & Stopping Intro" sits near the bottom.
- Admin `/resources` has no reorder UI. Creates never set `sort_order`.
- API tree endpoints already query in `sort_order` order but do not
  include the field. Flat `GET /api/v1/resources` ordered by title only.
- `ResourceApiService` caches the published list and folder trees for
  10 minutes and was only invalidated on lesson-resource assignment.

### Approach
1. Reuse the Progress Tracker pattern: POST an ordered ID array, write
   `sort_order` as 0-based position, drag-and-drop via `vuedraggable`.
2. New items append (`max(sort_order) + 1`) so they do not jump to the top.
3. Expose `sort_order` on folder + resource API objects. Keep tree/list
   arrays in admin order. Invalidate the resource-library cache on writes.
4. No new tables. No mobile app changes. No tests.

### Tasks
- [x] Trace resources model, admin UI, API trees, and Progress Tracker reorder
- [x] Choose sort storage (existing column) and API shape (`sort_order` + ordered arrays)

### Reflection
No migration needed. Folder reorder is included for consistency — same
column and the same admin screen. Cache invalidation is required or the
app would keep the old order for up to 10 minutes.

**Last Updated:** 2026-09-10.
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
- `ScheduleTab.vue` loads calendar items via
  `GET /instructors/{id}/calendar?start_date&end_date`.
- `useCalendarNavigation` supports `week` | `month`.
- `WeeklyCalendarGrid` is a Mon–Sun time-grid diary.
- `MonthlyCalendarGrid` is a month overview.
- Nav has a Today jump button plus Week / Month toggles.
  The ticket describes Today + Monthly and asks for Weekly
  alongside them.

### Approach
1. Add a first-class `day` view (labeled Today) using the existing
   time-grid with a single column.
2. Relabel the view toggle to **Today | Weekly | Monthly**.
3. Keep prev / next / Today jump working for all three views.
4. Reuse the existing range loader — no backend or mobile API change.

### Tasks
- [x] Trace Schedule tab, navigation composable, and calendar grids
- [x] Confirm calendar range endpoint already supports a single day
- [x] Choose Today | Weekly | Monthly toggle matching existing patterns

### Reflection
Weekly already existed as "Week". The reporter asked for Weekly
alongside Today and Monthly, so expose three explicit views and
generalise the time-grid for 1 or 7 days. No migration. No mobile API.

**Last Updated:** 2026-09-10.

---

## PHASE 2: IMPLEMENTATION

**Status:** ✅ Complete

### Currently working on
Complete.

### Tasks
- [x] ReorderFoldersAction + ReorderResourcesAction
- [x] Assign next sort_order on folder/resource create and CSV import
- [x] ResourceService methods + cache invalidation
- [x] Form requests, controller endpoints, web routes
- [x] Expose sort_order on API resources; order flat published list
- [x] Admin Index.vue drag-and-drop for folders and resources
- [x] Update api.md and database-schema.md

### Reflection
Mirrored Progress Tracker: ordered ID arrays, vuedraggable handles,
toasts on save/failure. ResourceService now extends BaseService so
admin writes flush the same library cache keys the API reads.
No migration — columns already existed.
- [x] Migration + enum + model scopes
- [x] Admin create/edit folder visibility + FolderCard badge
- [x] Student and instructor trees filter + prune
- [x] Student summary / my_resources / badges / published list respect folder visibility
- [x] Invalidate cached folder trees on folder write
- [x] Update api.md and database-schema.md

### Reflection
Folder visibility is independent of per-resource `audience`. Existing
folders stay `both` until staff toggle VTS / Standards Check Success to
instructor-only.

I've updated database-schema.md to reflect the migration changes.
I've updated api.md to reflect the new/changed endpoint.

**Last Updated:** 2026-09-10.
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

---

## PHASE 3: REFLECTION

**Status:** ✅ Complete
Staff and instructors can Remove a bespoke package from Details →
Packages. That only sets `active = false`. Restore brings it back.
Payments, lessons, and orders are untouched.
- [x] Extend `useCalendarNavigation` with day view + setView
- [x] Generalise `WeeklyCalendarGrid` for a variable number of days
- [x] Update `ScheduleTab` toggle, labels, and prev/next/today
- [x] No tests (HARD RULE). No mobile app changes.

### Reflection
Today is a single-day time-grid, Weekly is the existing 7-day diary
(relabelled), Monthly is unchanged. Prev/next/Today jump work in all
three views. Calendar range API already accepted a single day.

No database-schema.md update — no migration.
No api.md update — web admin UI only, not a mobile API endpoint.

Staff can drag folders (any level, including root) and resources
inside a folder. The mobile API returns `sort_order` and arrays
already in that order. Sam can consume the field later without a
mobile-repo change here.
## Phase 3: Reflection ⏸️

### Tasks
- [ ] Document decisions

### Reflection
Leftover: existing rows stay at `sort_order = 0` (title order) until
someone reorders that folder in admin. Tree eager-load is still two
levels of children (pre-existing). This environment has no PHP binary
so Wayfinder was not regenerated (admin page uses axios URLs, not
Wayfinder). No tests per ticket HARD RULE.
Leftover: staff must set instructor-only on existing folders after
migrate — default `both` is conservative. Student tree now also prunes
empty folders, so even untoggled instructor libraries disappear from
pupils if they contain no student-audience files. App consumption is
Sam's follow-up.

No tests added, per HARD RULE. I understand I must not run tests or
linting commands.

**Last Updated:** 2026-09-10.
Leftover: owner `/packages` still hard-deletes via
`DeletePackageAction` — that would CASCADE-delete orders if used on a
package that has been purchased. Instructor package *edit* still
`PUT /packages/{id}`, which `RestrictInstructor` redirects away from
for instructor-role users (pre-existing). No PHP in this environment
to regenerate Wayfinder; admin UI uses the same axios paths as the
rest of the Details tab.

No api.md update — web admin JSON only, mobile `/api/v1` unchanged.
(pending)
