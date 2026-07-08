# Task: Environment variable to override the MTD digital button on the instructor layout

## Overview

The instructor layout (`InstructorHeader.vue`) currently exposes two HMRC-related
buttons — "HMRC Connected" (when linked) and "HMRC / Tax" (when unlinked) — that
give instructors access to Making Tax Digital (MTD) features (ITSA + VAT).

Requirement: hide these MTD digital buttons by default and add an environment
variable to override that so a site owner can bring them back without a code
change.

## Locations identified

1. **`resources/js/components/Instructors/InstructorHeader.vue`**
   - Renders two "MTD digital" buttons: HMRC Connected (line ~217) and
     HMRC / Tax (line ~227) — both are the surface the user calls the
     "MTD digital button".

2. **`config/hmrc.php`**
   - Right place for a new `show_mtd_button` config value backed by an env var.

3. **`app/Http/Middleware/HandleInertiaRequests.php`**
   - Shares props globally with Inertia. Best surface to expose the flag to Vue
     without wiring it through every controller.

## Phase 1: Planning ✅

### What needs to change

**Backend:**
- `config/hmrc.php`
  - Add `'show_mtd_button' => (bool) env('SHOW_MTD_BUTTON', false)` — defaults
    to `false` so the button is hidden unless the site operator opts in.
- `app/Http/Middleware/HandleInertiaRequests.php`
  - Share the flag as `hmrc.show_mtd_button` so every Inertia page can read it.
- `.env.example`
  - Document the new `SHOW_MTD_BUTTON` variable.

**Frontend:**
- `resources/js/types/globals.d.ts` (or the shared `PageProps` type)
  - Extend the shared Inertia page props type with `hmrc.show_mtd_button`.
- `resources/js/components/Instructors/InstructorHeader.vue`
  - Read `hmrc.show_mtd_button` from `usePage().props`.
  - Wrap both HMRC buttons in a `v-if` so they only render when the flag is
    truthy AND the user is an instructor (existing role check).

### Why this scope

- The requirement is UI-only ("hide the button"). No routes need blocking — the
  HMRC tab remains reachable by direct URL for admins/owners. This is a display
  toggle, not a feature disable.
- Using a config-backed env var (not a raw `env()` call in code) is the Laravel
  convention and keeps behavior predictable when config is cached.
- Sharing via `HandleInertiaRequests` keeps the flag globally available without
  having to touch each controller that renders an instructor page.

### Out of scope

- Blocking access to `/hmrc/*` routes (this is a UI hide, not a permission).
- Changing HMRC connection logic, tokens, or middleware.
- Hiding the "HMRC" tab pill inside the instructor page — the visible button
  in the header is the specific surface called out by the requirement.

## Phase 2: Implementation ✅

### Files edited

- `config/hmrc.php`
  - Added `show_mtd_button` config key backed by `SHOW_MTD_BUTTON` env var,
    defaulting to `false`.
- `app/Http/Middleware/HandleInertiaRequests.php`
  - Shared `hmrc.show_mtd_button` as a global Inertia prop.
- `resources/js/components/Instructors/InstructorHeader.vue`
  - Read the flag from `usePage().props.hmrc?.show_mtd_button` and wrapped both
    the "HMRC Connected" and "HMRC / Tax" buttons behind it.
- `resources/js/types/index.d.ts`
  - Extended `SharedData` with `hmrc: { show_mtd_button: boolean }`.
- `.env.example`
  - Added `SHOW_MTD_BUTTON=false` with a short comment.

### Key decisions

- **Default hidden.** The user asked to "hide the MTD digital button" first,
  then have an env var override — so the safe default is `false`.
- **Boolean cast in config.** `env()` returns strings for values like "true"
  in some setups; a `(bool)` cast normalises the flag so the frontend can
  trust `v-if` semantics.
- **Shared prop, not per-page.** Sharing through the Inertia middleware means
  any future instructor page that wants to consult the flag has it for free.
- **Guarded on both existing conditions.** The buttons still require the
  `isInstructor` role check that was there before — the new flag is an
  *additional* gate, not a replacement.

## Phase 3: Reflection ✅

**Why this shape is right for the brief:**
- Single env var toggles the UI. No behavior change beyond visibility.
- Config-first pattern keeps it cache-safe and testable.
- No changes to route registration or authorisation.

**Operational notes:**
- To show the button in an environment, set `SHOW_MTD_BUTTON=true` in `.env`
  and run `php artisan config:clear` (or `config:cache`) so the new value takes
  effect.
- The HMRC tab is still directly linkable by URL — this is a header display
  toggle only.

**Out of scope, carried forward:**
- If the product later wants a full "hide all MTD features" mode, the same env
  var could gate route registration in `routes/web.php`. That's a bigger blast
  radius and not part of this brief.

**Technical debt / follow-up not done:**
- No tests added (project rule: user maintains tests manually).
- No Pint / Prettier run (project rule: user handles code style).

---

**Status:** All phases complete.
**Last Updated:** 2026-07-08.
