# Task: Enquiry list CSV export with date filters

## Overview

Admin Enquiries list (`/enquiries`) has status and area filters but no
date/month filter and no CSV export. Staff need to export the currently
filtered enquiry list (by month/date range and the existing filter types).

## Phase 1: Planning ✅

### Current state
- `GET /enquiries` (owner/admin, `RestrictInstructor`) lists paginated
  enquiries via `EnquiryController` → `EnquiryService` →
  `GetFilteredEnquiriesAction`.
- Server filters: `status` (all/completed/full_onboarding/in_progress),
  `area` (all/in_area/out_of_area/unknown).
- Search was client-side on the current page only.
- No date filter. No export. Reports already stream CSV with
  `response()->streamDownload` + `fputcsv`.

### Approach
1. Extend the shared filter query with `date_from` / `date_to` (created_at)
   and server-side `q` search so list and export stay in sync.
2. `GET /enquiries/export` streams a CSV of every matching row (not just
   the current page), using the same filters.
3. UI: month picker (sets month bounds) + from/to date inputs + Download
   CSV button, matching the cancelled-lessons report pattern.
4. CSV columns = table columns plus consent, instructor id, and tracking.

### Tasks
- [x] Trace enquiries list, filters, and existing CSV export patterns
- [x] Choose UI placement and CSV columns (no new library)

### Reflection
Reuse the reports CSV stream pattern and the existing Controller →
Service → Action chain. No migration. Web admin only — not a mobile API.

**Last Updated:** 2026-09-08.

## Phase 2: Implementation ✅

### Currently working on
Complete.

### Tasks
- [x] FilterEnquiriesRequest + date/search on GetFilteredEnquiriesAction
- [x] EnquiryService paginate vs export collection
- [x] EnquiryController@exportCsv + named route
- [x] Enquiries/Index.vue month + date pickers and Download CSV
- [x] Wayfinder import via EnquiryController.exportCsv (generated at build)

### Reflection
Export is a GET with the same query string as the list. Month picker is a
convenience that writes `date_from`/`date_to`; custom ranges still work.
Search is now server-side so CSV matches the visible filter set, not the
current page.

No database-schema.md update — no migration.
No api.md update — web admin download, not a mobile API endpoint.

## Phase 3: Reflection ✅

Staff can pick a month or a from/to range, keep status/area/search, and
download every matching enquiry as CSV. Unfiltered export only happens
when no filters are set.

### Tasks
- [x] Document decisions and leftover risks

### Reflection
Leftover: this environment has no PHP binary, so Wayfinder was not
generated here (Vite plugin will generate on `npm run dev` / build).
Very large exports stream via cursor but may still hit web timeouts.
JSON name search uses MySQL `JSON_EXTRACT` / `CONCAT_WS`.
