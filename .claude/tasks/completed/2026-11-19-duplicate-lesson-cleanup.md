# Task: Find and remove duplicate lessons after the GoRoadie import

**Created:** 2026-11-19
**Last Updated:** 2026-11-19
**Status:** Complete
**Ticket:** 01a1f3f3-855d-7293-b1e5-0137466fdb2d (urgent, reporter GB; affected: Mark Collinson and likely others)

---

## 📋 Overview

### Goal
Sunday's GoRoadie import duplicated lessons in instructor diaries: exact copies in the same slot, and GoRoadie
originals brought back at their old date/time after the instructor had moved the lesson in Drive Plus.
Find them all (CSV for review), then remove the duplicates on the admin side, keeping the Drive Plus version and
leaving payments, invoices and Stripe records with the kept lesson.

### Sam's decisions (binding)
- Build the CSV and a separate delete command that only runs after review.
- Scan all instructors.

### Success Criteria
- [x] `lessons:find-duplicates` scans every instructor, makes no data changes, writes one CSV row per lesson with KEEP/REMOVE, reason and manual-review flag
- [x] `lessons:remove-duplicates --csv=…` is a dry run unless `--confirm`, acts only on REMOVE rows, skips review-flagged groups, one transaction per group, audit of every change
- [x] Payments / invoices / Stripe records never moved or changed; amounts never changed
- [x] Importer no longer re-creates the "moved in Drive Plus" duplicate

---

## 🎯 PHASE 1: PLANNING ✅

### Findings
- GoRoadie booking ID = `diary_ref`, stored in `import_mappings` (`diary` → calendar_items.id, `lesson` → lessons.id), unique on `(entity, source_ref)`. Every import path skips a mapped ref, and each instructor's diary runs in one transaction, so the same ref can't be imported twice (a racing worker hits the unique index and rolls back). Sunday's duplicates therefore came from bookings arriving under a different `diary_ref`, or from lessons the exact-time guard (`studentHasLessonAt`) can't see — a lesson moved in Drive Plus is no longer at the GoRoadie time.
- A Drive Plus move updates the lesson in place (`UpdateCalendarItemAction`) and writes a pupil `activity_logs` row with `lesson_id` (or `anchor_lesson_id` for bulk moves) + `old_date` / `old_start_time`. `updated_at` is unreliable (the importer's renumbering touches every open lesson).
- Linked records: `lesson_payments` and `payouts` (CASCADE on lesson delete; Stripe invoice metadata and the `invoice.paid` webhook find payments by lesson ID), `reflective_logs` (unique, cascade), `lesson_resource`, `lesson_reminders` (cascade), `instructor_finances.lesson_id` (GoRoadie bookkeeping, null on delete), import mappings (no FK), order-level Stripe IDs. `Lesson` has no SoftDeletes.

### Decisions Made
- Remove = quiet cancel (status `cancelled`, reason `other`, "Duplicate of lesson #…"), diary item deleted — the precedent set by `import:repair` (`ResolveImportedLessonClashesAction`). Not a hard delete (would cascade-delete payments/payouts); not a new SoftDeletes migration (global scope would change every lesson query in the payment pipeline).
- Only imported (£0) lessons with no lesson payment / payout are removed automatically; anything with Stripe money is flagged for manual review rather than re-pointed.
- Third group type `moved_in_drive_plus` added (the ticket's hard case) using the reschedule activity log.

---

## 🎯 PHASE 2: IMPLEMENTATION ✅

- [x] `app/Actions/Lesson/FindDuplicateLessonsAction.php` — read-only scan, grouping, keep/remove + review rules
- [x] `app/Actions/Lesson/RemoveDuplicateLessonAction.php` — live re-checks, re-points bookkeeping links, cancels duplicate
- [x] `app/Services/DuplicateLessonService.php` — CSV write/read, per-group transactions, JSONL audit
- [x] `app/Console/Commands/FindDuplicateLessons.php`, `app/Console/Commands/RemoveDuplicateLessons.php`
- [x] `app/Actions/Import/ImportDiaryItemAction.php` — `studentHasLessonAt` also true when the pupil's open lesson was moved from that slot
- [x] `.claude/database-schema.md` — import_mappings note

### Reflection
- Verified against a scratch local MySQL database only (seeded scenarios), never real data. No tests written or run (ticket rule).

💳 Payment impact: TOUCHED (read + guarded write path, no payment code changed) — `lessons:remove-duplicates` cancels imported £0 lessons only and refuses any lesson with a `lesson_payments` row or `payouts` row; it never edits lesson_payments, payouts, orders' Stripe IDs or any amount. Re-points only `instructor_finances.lesson_id` (bookkeeping), reflective logs, resources and import mappings. `syncImportedOrder` runs only on imported orders. Before release: DB backup, run the scan, review the CSV, dry run, then `--confirm`.
