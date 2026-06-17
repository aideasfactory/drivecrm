# Task: Restrict coverage changes and CSV actions to admin (Owner) users

## Overview

Tighten coverage-area permissions in the Instructor admin area so that only
admin-role users (mapped to `UserRole::OWNER` in this codebase) can:

- Add a new coverage area (postcode sector)
- Delete an existing coverage area
- Upload (replace) coverage areas from a CSV
- Download the coverage CSV

Logged-in instructors viewing their own Instructor → Details → Coverage tab
should still be able to **see** their coverage areas, but every mutation and
CSV action must be hidden from the UI and rejected by the backend.

In this codebase `admin === owner` (see `App\Enums\UserRole`). The existing
`App\Http\Middleware\EnsureOwner` middleware is the canonical gate for
admin-only routes.

## Phase 1: Planning ✅

### Backend surface to lock down

`routes/web.php` (lines 87–96) currently exposes:

| Method | Path | Action | Decision |
|--------|------|--------|----------|
| GET    | `/instructors/{instructor}/locations` | list | Stays open — instructors view their own |
| POST   | `/instructors/{instructor}/locations` | add | **Owner-only** |
| DELETE | `/instructors/{instructor}/locations/{location}` | delete | **Owner-only** |
| GET    | `/instructors/{instructor}/locations-export` | CSV download | **Owner-only** |
| POST   | `/instructors/{instructor}/locations-import` | CSV upload | **Owner-only** |

These four routes will be moved into a nested `Route::middleware([EnsureOwner::class])`
group inside the existing auth/verified/RestrictInstructor outer group.

### Frontend surface to gate

`resources/js/components/Instructors/Tabs/Details/CoverageSubTab.vue` exposes:

- "Add Area" button (top-right of the column header)
- "Download CSV" button
- "Upload CSV" button
- Per-row trash/delete button

All four will be wrapped in `v-if="isOwner"` using the existing
`@/composables/useRole` composable (`isOwner` reactive ref).

### Tests

A new `tests/Feature/Instructors/InstructorCoverageAuthorizationTest.php`
will:

1. Verify an Owner can `POST` / `DELETE` / export / import.
2. Verify an Instructor accessing their own routes is rejected with 403 for
   each of the four mutation endpoints.
3. Verify the read-only listing endpoint still works for the Instructor (so
   they can see their existing coverage areas in the UI).

## Phase 2: Implementation ✅

### Files edited

- `routes/web.php` — wrapped the four mutation/CSV routes in a nested
  `EnsureOwner` middleware group; left the read-only `locations` route
  outside so instructors can still view their own coverage.
- `resources/js/components/Instructors/Tabs/Details/CoverageSubTab.vue` —
  imported `useRole`, added `const { isOwner } = useRole()`, and gated
  "Add Area", "Download CSV", "Upload CSV", and per-row delete buttons,
  plus the empty-state copy that told users to click "Add Area" so an
  instructor seeing the empty state isn't told to use a button they can't
  see.

### Files created

- `tests/Feature/Instructors/InstructorCoverageAuthorizationTest.php` —
  Pest feature tests covering owner-allow / instructor-deny for every
  mutation and CSV endpoint, plus owner+instructor allow for read.
- `results.md` — client-facing summary of what was developed.

## Phase 3: Reflection ✅

### Why this shape is right for the brief

- The ticket says "if the logged-in user is an admin". This codebase has no
  literal `admin` role — the equivalent is `UserRole::OWNER`, the role used
  on every other gated admin feature (push notifications, support messages,
  resources, student transfers). Reusing `EnsureOwner` keeps the new gate
  consistent with the rest of the app rather than inventing a new abstraction.
- Defence-in-depth: even if a future UI change exposes a coverage mutation
  button, the route is already locked at the middleware layer, so an
  instructor cannot escalate privilege by crafting the request manually.
- The read-only `locations` GET endpoint stays open. Instructors can still
  load their own coverage tab and see what areas the admin has assigned them
  — they just can't mutate it. This matches the ticket: "Review the admin
  area coverage UI ... for logged-in instructors and admins".

### Subtle decisions worth flagging

- The route group keeps `RestrictInstructor` in the outer group. An
  instructor hitting `POST /instructors/{theirOwnId}/locations` still passes
  the outer "this is your own path" check but hits the inner `EnsureOwner`
  gate, returning a 403. That's the desired behaviour — instructors don't
  silently redirect, they get a clear authorization error.
- Frontend uses `v-if`, not `v-show`. Hiding the markup entirely means there's
  nothing for an instructor to interact with even via dev-tools tampering.
  The backend gate is the source of truth either way.
- The "Click Add Area button above" hint in the empty state is also gated —
  showing it to an instructor who can't see the button would be confusing.
- No changes to `InstructorController` — controllers stay HTTP-agnostic and
  authorization belongs in middleware per project standards.

### Out of scope

- No changes to the Booking/Step1 coverage usage — that's a customer-facing
  postcode lookup, not the admin coverage UI the ticket targets.
- No changes to API v1 endpoints (none exist for coverage management).
- No new "admin" role added — owner is the project's admin role.

---

**Status:** All phases complete.
**Last Updated:** 2026-06-17.
