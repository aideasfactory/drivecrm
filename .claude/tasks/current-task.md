# Task: Reflective log + lesson sign-off persistence

**Created:** 2026-09-18
**Last Updated:** 2026-09-18
**Status:** Complete

---

## Overview

Mission Control ticket `01a0b0da-8f65-71da-8770-c06db6cd370b` (urgent bug).
Instructor Kieran Grant completed the reflective log, summary and topic
stats for pupil Jack Mine Lesson #1, but Complete Sign Off never confirms
and the app still shows "Reflective log not completed" / Needs Sign Off.

Root cause (API, not the mobile app):
1. Reflective logs were **read-only** — table, model, and GET serializers
   existed, but there was no write endpoint. Completing the log could not persist.
2. `POST .../sign-off` only accepted `summary` and ignored any log payload.
3. Sign-off returned 200 "being processed" and completed in a queued job
   whose DB transaction included the Stripe payout. Payment/onboarding/
   Stripe failures rolled back `completed_at`, so Needs Sign Off never cleared.

Topic stats already persist via the progress-tracker API — that part works.

### Success Criteria
- [x] Instructor can persist a complete reflective log via the mobile API
- [x] Sign-off accepts and saves the log + summary before completion
- [x] Successful sign-off persists `completed_at` even if payout fails
- [x] Response returns the lesson so the client can clear Needs Sign Off
- [x] Incomplete log / unpaid / not-ready return 422 (not silent job fail)
- [x] `.claude/api.md` updated
- [x] No tests added (HARD RULE)

---

## PHASE 1: PLANNING

**Status:** ✅ Complete

### Tasks
- [x] Trace sign-off, reflective log, progress, and card-status APIs
- [x] Identify missing write path and silent async/transaction failure
- [x] Choose persist + confirm contract

### Decisions Made
- Upsert `PUT`/`POST /api/v1/students/{student}/lessons/{lesson}/reflective-log`
  (plural alias too). Instructor only. Three prompt fields required.
- Sign-off also accepts nested `reflective_log` (or flattened aliases).
- Persist log + summary **synchronously**. Mark lesson completed in a
  transaction that does **not** include Stripe. Payout/emails stay in the
  existing job (now idempotent if the lesson is already completed).
- Return `LessonDetailResource` on sign-off and on log save so
  `has_reflective_log`, `reflective_log`, `status`, and `card_status`
  update immediately.
- `has_reflective_log` means the log is **complete** (all three prompts
  filled), not merely that a row exists.
- Stripe Connect onboarding no longer blocks marking the lesson complete.

### Reflection
The screenshot's "Reflective log not completed" maps 1:1 to
`has_reflective_log === false` with no write API. Topic stats were a
red herring (progress-tracker already saves).

**Last Updated:** 2026-09-18.

---

## PHASE 2: IMPLEMENTATION

**Status:** ✅ Complete

### Currently working on
Complete.

### Tasks
- [x] SaveReflectiveLogAction + ReflectiveLog completeness helpers
- [x] Form requests + LessonReflectiveLogController + routes
- [x] Persist log/summary on sign-off; complete lesson sync; 422 guards
- [x] Split payout out of the completion transaction; job idempotent
- [x] Update api.md, lesson-sign-off-process.md, database-schema note

### Reflection
Reuse Controller → Service → Action. No migration — `reflective_logs`
already existed. Admin web sign-off still queues the job (now payout-safe).

I've updated api.md to reflect the new/changed endpoint.

**Last Updated:** 2026-09-18.

---

## PHASE 3: REFLECTION

**Status:** ✅ Complete

### Tasks
- [x] Document decisions and leftovers

### Reflection
Product follow-up: the four-prompt reflective log is leftover. Mobile
sign-off now mirrors admin — `{ "summary": "..." }` only; the log is not
required. The leftover upsert endpoint remains so old app builds do not
404. The app must stop gating on `has_reflective_log`. Failed Stripe
payouts leave `payouts.status = failed` and retry instead of rolling
back the lesson.

No tests added, per HARD RULE. I understand I must not run tests or
linting commands.

**Last Updated:** 2026-09-18.
