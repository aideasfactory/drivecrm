# Task: Progress Tracker Framework

## Overview

Per-instructor driving-progress frameworks (two-level: category → subcategory). On instructor creation, seed a default framework from config. Admins (via the instructor's Details → Tracker subtab) can CRUD and reorder their own categories/subcategories. Instructors mark student progress (score 1–5) against subcategories via mobile API. Students can GET their own scores; instructors can GET and POST scores for their students.

### Decisions locked in Phase 1

- **Framework edit vs. history:** soft-delete categories/subcategories so historical scores stay visible; POSTs against soft-deleted subcats rejected.
- **Score storage:** overwrite (one row per `(student_id, progress_subcategory_id)`, upserted on save).
- **Seeding:** config file `config/progress_tracker.php` + `SeedInstructorProgressTrackerAction`. Backfill command for existing instructors.
- **Save granularity:** bulk POST of all changed scores.
- **Reorder + custom adds:** `sort_order` on both tables, drag-reorder in admin.
- **API audience:** student `GET` own; instructor `GET`+`POST` for their students. Mirror `StudentPolicy` ownership checks.
- **Admin UI:** framework editor only (categories/subcats). Scoring UI is app-side — not our job.
- **Model names:** `ProgressCategory`, `ProgressSubcategory`, `StudentProgress`.

## Phase 1: Planning ✅

- Schema mapped (3 tables, soft-deletes on two), API shape agreed, patterns ripped from `InstructorFinanceController` / `ActivitySubTab` / `CreateInstructorPackageAction` / `StudentPolicy`.
- Config template drawn from the 3 screenshots (Preparation / Traffic / Junctions / Traffic Management / Manoeuvres / Situations). Gaps in the middle screenshot — user will tweak config later.

## Phase 2: Implementation ✅

**Database** — 3 migrations applied (`progress_categories`, `progress_subcategories`, `student_progress`). Soft-deletes on the framework tables, unique `(student_id, progress_subcategory_id)` on the progress table.

**Config & seeding** — `config/progress_tracker.php` holds default framework + score labels. `SeedInstructorProgressTrackerAction` is idempotent (skips if the instructor already has any categories, trashed or live). Hooked into `InstructorService::createInstructor` after `DB::commit()`. `php artisan progress-tracker:backfill` ran against 19 existing instructors — all seeded.

**Models** — `ProgressCategory`, `ProgressSubcategory`, `StudentProgress` plus `progressCategories()` relation on `Instructor`.

**Service** — `ProgressTrackerService extends BaseService` with admin CRUD + reorder methods, plus delegates for get/save student progress actions.

**Admin** — `ProgressTrackerController` exposes axios-fed framework CRUD + reorder under `/instructors/{instructor}/progress-tracker/*`. `TrackerSubTab.vue` added with up/down reorder (no drag lib installed, and dependencies can't be added without approval — up/down buttons are functional and avoid that risk). Wired into `DetailsTab.vue`.

**Mobile API** — `GET /api/v1/student/progress`, `GET /api/v1/instructor/students/{student}/progress`, `POST /api/v1/instructor/students/{student}/progress`. All share `StudentProgressResource` (grouped category → subcategory → score + `archived` flag). POST validates 1–5 + subcategory existence, silently drops entries for soft-deleted or foreign subcats.

**Docs** — `.claude/database-schema.md` gains Progress Tracker Tables section. `.claude/api.md` gains Progress Tracker endpoint section + TOC entry + changelog line.

**Quick sanity checks (tinker):** seed + save + read-back round trip confirmed; archived subcats with existing scores remain visible with `archived: true` and preserved score; POSTs against archived subcats are silently ignored.

`vendor/bin/pint --dirty --format agent` — `{"result":"pass"}`.

## Phase 3: Reflection ✅

**Went well**
- Soft-delete strategy + "silently drop writes to archived subcats" cleanly resolves the framework-edits-vs-history trade-off without surfacing bespoke error states to the mobile app. GET returns archived items; POST quietly ignores them.
- Reusing `StudentProgressResource` across all 3 endpoints kept the response shape identical — one client-side parser can handle every case.
- Backfill idempotency (`withTrashed()->exists()` guard) means the command is safe to re-run and can be wired into future deploys without worry.

**Trade-offs / follow-ups**
- **No drag reorder.** Up/down arrows only. To add drag, we'd need `vuedraggable`/`sortablejs` — blocked by "do not change the application's dependencies without approval". Happy to add if the user OKs.
- **Admin routes sit under `RestrictInstructor` middleware.** Per the user's original brief ("each instructor needs to be able to log into the admin area in their details tab"), instructors should eventually access the framework editor for themselves — currently only admins can. That's a scope-wider middleware change (would affect every sibling route on the instructor details page), so I kept it identical to the other subtabs. Flag for the user if they want instructors to self-serve.
- **`score_labels` is duplicated across 3 API responses.** Fine for now (static, tiny), but if a 4th endpoint joins, factor into a trait or a `JsonResource::withScoreLabels()` helper.
- **No tests** per project rules (user maintains tests manually). The new endpoints, command, and Vue subtab are therefore unprotected against regressions — user's call if they want test coverage added.

---

**Status:** All phases complete.
**Last Updated:** 2026-04-22
