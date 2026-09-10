# Task: Admin resource/folder re-ordering

## Overview

Owners need to control the display order of videos/resources (and
folders) in the admin Resources library. The mobile app currently
shows items in title order because every row has `sort_order = 0`.
Admin drag-and-drop should persist `sort_order`; the mobile API
must return items in that order and expose the field.

Ticket: 01a08a7d-05af-7287-80d0-df7bb3bed854 (urgent bug).

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

## Phase 2: Implementation ✅

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

## Phase 3: Reflection ✅

Staff can drag folders (any level, including root) and resources
inside a folder. The mobile API returns `sort_order` and arrays
already in that order. Sam can consume the field later without a
mobile-repo change here.

### Tasks
- [x] Document decisions and leftover risks

### Reflection
Leftover: existing rows stay at `sort_order = 0` (title order) until
someone reorders that folder in admin. Tree eager-load is still two
levels of children (pre-existing). This environment has no PHP binary
so Wayfinder was not regenerated (admin page uses axios URLs, not
Wayfinder). No tests per ticket HARD RULE.
