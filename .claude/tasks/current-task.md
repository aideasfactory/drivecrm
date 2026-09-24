# Task: Instructor diary weekly range API

**Created:** 2026-09-24
**Last Updated:** 2026-09-24
**Status:** Complete

## Overview

Add inclusive `from` / `to` reads beside the existing instructor day diary routes so the week view can load calendar items and lessons in two requests instead of fourteen.

## Phase 1: Planning

**Status:** Complete

- [x] Keep single-day routes
- [x] Calendar index accepts `from` + `to` (max 31 days), ignored when `date` is present
- [x] New `GET /api/v1/instructor/lessons?from=&to=` registered before `{date}`
- [x] Same JSON resources, instructor scoped from the token

**Reflection:** Reused the day queries and resources so the week payload matches what the app already decodes.

## Phase 2: Implementation

**Status:** Complete

- [x] `GetInstructorCalendarItemsRequest` and `GetInstructorLessonsRangeRequest`
- [x] Range methods on the calendar and lesson actions
- [x] Feature tests for ordering, scoping, filters, and 422s
- [x] `.claude/api.md` updated

**Reflection:** Range results are not cached, because calendar cache invalidation is per day.

## Phase 3: Reflection

**Status:** Complete

- [x] Day routes unchanged
- [x] Week view can switch to two GETs once this is deployed

**Reflection:** SQLite test migrations needed small driver guards so the new feature tests can migrate. MySQL enum alters are unchanged.
