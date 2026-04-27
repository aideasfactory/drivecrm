# Task: Temporarily Block Student CRM Login

## Overview
Block student users from accessing the CRM (both web and API) until the student experience is properly built out. This is a temporary restriction.

## Phase 1: Planning ✅
- [x] Review current student login flow (web + API)
- [x] Identify all entry points where students can authenticate
- [x] Plan implementation approach

## Phase 2: Implementation ✅
- [x] Add student block check in LoginResponse.php (web login)
- [x] Add student block check in LoginAction.php (API login)
- [x] Block token issuance in AuthController::registerStudent()
- [x] Create RestrictStudent middleware for existing sessions
- [x] Register middleware in web.php, api.php, and settings.php routes
- [x] Update existing tests that expected student login to succeed
- [x] Write new Pest tests for student login block

## Phase 3: Reflection ✅
### What went well
- Clean separation: login block at action level, session block at middleware level
- Temporary nature is clear — easy to remove later by reverting these changes
- All entry points covered: web login, API login, API registration, existing sessions

### Decisions made
- Blocked at LoginResponse (post-auth) for web rather than custom Fortify pipeline — simpler
- Blocked at LoginAction for API — consistent error handling via ValidationException
- Blocked API registration at controller level to keep RegisterStudentAction reusable
- Created RestrictStudent middleware for defense in depth against existing sessions

### Future removal
- Remove RestrictStudent middleware and its route registrations
- Remove student check from LoginResponse.php
- Remove student check from LoginAction.php
- Restore registerStudent() in AuthController.php
- Revert test changes

**Last Updated:** 2026-04-27
