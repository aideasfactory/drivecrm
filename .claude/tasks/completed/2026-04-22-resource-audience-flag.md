# Task: Resource Audience Flag (Student vs Instructor)

## Overview

Add an `audience` flag (`student` | `instructor`) to `resources`. Admins choose it on create, edit, and in CSV import. Mobile API `/api/v1/resources` accepts `?audience=student|instructor` (omit for all) and returns `audience` on every resource.

## Phase 1: Planning ✅

- Column: `audience` varchar(20), default `student`, indexed, cast via `ResourceAudience` enum.
- Admin: required on create/edit/CSV.
- API: optional filter on index.
- Student-specific mobile endpoints (`/api/v1/student/...`) left alone — flagged in docs as non-filtered so callers know.

## Phase 2: Implementation ✅

**Flag**
- Migration `2026_04_22_143738_add_audience_to_resources_table` — `audience` string(20), default `student`, indexed.
- `app/Enums/ResourceAudience.php` — STUDENT / INSTRUCTOR.
- `Resource` model — `audience` fillable + enum cast.

**Admin create**
- `StoreResourceRequest` — required, in:student,instructor.
- `UploadResourceAction` + `StoreVideoLinkResourceAction` — accept enum.
- `ResourceService::uploadResource` / `storeVideoLinkResource` — pass-through.
- `ResourceController::storeResource` — hydrate enum from request.
- `UploadResourceSheet.vue` — toggle buttons (Student / Instructor), defaults to student.

**Admin edit**
- `UpdateResourceRequest` — required.
- `UpdateResourceAction` — optional param (only writes when provided, keeps action backward-compatible).
- `ResourceService::updateResource` — optional param.
- `ResourceController::updateResource` — always hydrates enum (validation makes it required).
- `EditResourceSheet.vue` — toggle, pre-fills from current resource.

**Admin visibility**
- `ResourceCard.vue` — badge (default variant for instructor, secondary for student).
- `Resources/Index.vue` — `ResourceItem` type gains `audience`.

**CSV import**
- `BulkImportResourcesAction` — new required `audience` column with validation.
- `ResourceController::downloadCsvTemplate` — adds `audience` header + example row.

**Mobile API**
- `GetPublishedResourcesAction` — optional `?ResourceAudience` filter inline (`where('audience', ...)`).
- `ResourceApiService::getPublishedResources` — filter pass-through; only cache the null-audience case.
- `Api/V1/ResourceController::index` — validates `?audience=` and passes enum down.
- `ResourceResource` — exposes `audience` (needed when client calls without filter).

**Docs**
- `.claude/database-schema.md` — audience column + index + one-line business logic note.
- `.claude/api.md` — documents the query param and response field, plus a caveat that `/api/v1/student/*` endpoints are NOT audience-filtered (intentional — out of scope).

## Phase 3: Reflection ✅

**What I kept minimal after first-pass review:**
- Dropped `scopeForAudience` on the model — used in only one place; an inline `where` is cleaner.
- Reverted hard-filtering of `/api/v1/student/resources`, `StudentResourceController`, `getResourceFolderTree`, `getMyResources`, `getPublishedResource`, `getRandomPublishedResources` to `audience=student`. Documented the gap in `api.md` so callers know to either self-filter or use `/api/v1/resources?audience=student`.
- Dropped per-audience cache key variants — only the null-audience `getPublishedResources()` call is cached now. Filter-by-audience goes straight to the DB.

**Technical debt / follow-up (not doing now):**
- Pre-existing `tests/Feature/Resources/ResourceUploadTest.php` POSTs to `/resources/files` without `audience` — will 422 after this change. User handles test updates manually.
- If/when instructor resources start appearing in shared folders and leaking into the student app's `/api/v1/student/resources` tree, add `->where('audience', 'student')` in `GetResourceFolderTreeAction` (one line).

---

**Status:** All phases complete.
**Last Updated:** 2026-04-22
