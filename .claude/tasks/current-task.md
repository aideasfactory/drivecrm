# Task: Restrict instructor-role admin users to the instructor screen and hide the full admin side menu

**Created:** 2026-03-23
**Last Updated:** 2026-03-23T11:30:00Z
**Status:** ✅ Complete

---

## Overview

### Goal
Build a role-specific admin experience for instructors. When an instructor logs in, they should only see their own instructor screen, with the full admin side menu hidden.

### Context
- Tile ID: 019d1a52-1dbf-71c3-bb74-211c9e8a35a9
- Branch: feature/019d1a52-1dbf-71c3-bb74-211c9e8a35a9-restrict-instructor-role-admin-users-to-the-instructor-scree

---

## PHASE 1: PLANNING — ✅ Complete

### Reflection
Clean patterns already exist (EnsureOwner middleware, useRole composable). Followed the same approach.

---

## PHASE 2: IMPLEMENTATION — ✅ Complete

### Tasks
- ✓ Create RestrictInstructor middleware
- ✓ Share instructor_id in HandleInertiaRequests
- ✓ Apply middleware in routes/web.php
- ✓ Modify AppSidebarLayout.vue to hide sidebar for instructors
- ✓ Update Auth type to include instructor_id
- ✓ Write tests

### Reflection
All changes follow existing patterns. The middleware mirrors EnsureOwner's structure. Frontend uses the existing useRole composable. No new dependencies introduced.

---

## PHASE 3: FINAL REFLECTION — ✅ Complete

### What was built
- Backend middleware (RestrictInstructor) that redirects instructor-role users to their own instructor page for any non-allowed route
- Instructor-allowed routes: their own /instructors/{id}/*, /students/*, /settings/*
- Frontend layout modification that completely hides the sidebar for instructor users
- Shared instructor_id via Inertia for frontend use
- Comprehensive test suite covering all redirect scenarios

### Score: 8/10
Solid implementation that reuses existing patterns. Clean separation between backend enforcement (middleware) and frontend presentation (layout). No over-engineering. Minor consideration: if more allowed routes are needed in future, the middleware allowlist will need updating.
