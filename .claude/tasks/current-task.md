# Task: Start mobile app authentication flows for login, student registration, and instructor registration

**Created:** 2026-03-15
**Last Updated:** 2026-03-15T13:15:00Z
**Status:** ✅ Complete

---

## Overview

### Goal
Build the mobile app authentication layer with login, student registration, and instructor registration flows using Sanctum tokens. Reuse existing services and actions where possible.

### Context
- Tile ID: 019cee56-a00b-72a7-a1c8-640538f298e1
- Repository: drivecrm
- Branch: feature/019cee56-a00b-72a7-a1c8-640538f298e1-start-mobile-app-authentication-flows-for-login-student-regi
- Priority: MEDIUM

### Key Decisions
- Created new `Auth` domain Actions rather than repurposing enquiry-specific actions
- Followed existing Controller → Service → Action pattern
- Used Eloquent Resources for API responses (UserResource)
- Sanctum token-based auth with device_name tracking
- Registration endpoints return token immediately for seamless UX

---

## PHASE 1: PLANNING
**Status:** ✅ Complete

### Tasks
- [x] Read all instruction files
- [x] Explore existing models (User, Student, Instructor)
- [x] Explore existing actions (CreatePupilAction, CreateNewUser, CreateUserAndStudentFromEnquiryAction)
- [x] Explore existing services (InstructorService, StudentService)
- [x] Review existing routes/api.php
- [x] Identify reusable components
- [x] Create implementation plan

### Reflection
The codebase has solid existing patterns. `CreatePupilAction` and `CreateUserAndStudentFromEnquiryAction` show how User+Student pairs are created. `InstructorService::createInstructor()` handles User+Instructor creation with geocoding. For the API auth flow, we created focused Actions that follow the same patterns but are tailored for self-registration (password set by user, no enquiry dependency, no Stripe integration at registration time).

---

## PHASE 2: IMPLEMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Create Auth Actions (Login, Logout, RegisterStudent, RegisterInstructor)
- [x] Create AuthService to orchestrate actions
- [x] Create API FormRequests (Login, RegisterStudent, RegisterInstructor)
- [x] Create UserResource for API responses
- [x] Create API AuthController
- [x] Update routes/api.php with versioned auth routes
- [x] Write 13 Pest feature tests
- [x] Update api.md with registration endpoint documentation

### Reflection
Implementation went smoothly following the existing architecture. The Controller → Service → Action pattern kept the code clean and testable. Each Action is single-responsibility and reusable. The AuthService orchestrates token creation after registration, keeping that concern out of the Actions themselves. FormRequests handle all validation including password confirmation and unique email checks.

---

## PHASE 3: FINAL REFLECTION & DOCUMENTATION
**Status:** ✅ Complete

### Reflection
All three auth flows (login, student registration, instructor registration) are implemented cleanly:

**What went well:**
- Followed existing codebase patterns exactly (Controller → Service → Action)
- Reused UserRole enum, password hashing via model casts, and Sanctum HasApiTokens
- Clean separation: Actions handle business logic, Service orchestrates + creates tokens, Controller handles HTTP
- Comprehensive test coverage with 13 tests covering success paths, validation, auth requirements, and edge cases

**Architecture notes:**
- Actions in `app/Actions/Auth/` are domain-organized per coding standards
- No duplicate logic — each Action is atomic and reusable by web controllers, jobs, or CLI
- Services remain transport-agnostic (no HTTP concerns)
- UserResource ensures consistent JSON structure across all auth endpoints

**Potential future enhancements (not implemented — out of scope):**
- Password reset flow for mobile
- Email verification flow for mobile
- Rate limiting on registration endpoints
- Instructor registration with geocoding (currently postcode stored without lat/lng — could integrate FetchPostcodeCoordinatesAction later)
