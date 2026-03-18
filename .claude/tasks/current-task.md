# Task: Create an authenticated instructor profile update endpoint with self-only access policy

**Created:** 2026-03-18
**Last Updated:** 2026-03-18T20:45:00Z
**Status:** Complete

---

## Overview

### Goal
Build an authenticated API endpoint for instructors to update their own profile in Drive.

### Context
- Tile ID: 019d01c0-ad41-71c6-81fb-b03c18b1f150
- Repository: drivecrm
- Branch: feature/019d01c0-ad41-71c6-81fb-b03c18b1f150-create-an-authenticated-instructor-profile-update-endpoint-w

---

## PHASE 1: PLANNING
**Status:** ✅ Complete

### Reflection
Explored existing patterns across controllers, services, actions, policies, resources, and routes. The codebase follows a clean Controller -> Service -> Action architecture with Sanctum auth and ResolveApiProfile middleware. No existing InstructorPolicy existed, so one was needed.

---

## PHASE 2: IMPLEMENTATION
**Status:** ✅ Complete

### Tasks
- ✓ Create InstructorPolicy with update method (self-only check)
- ✓ Create UpdateInstructorProfileAction
- ✓ Add updateProfile method to InstructorService
- ✓ Create UpdateInstructorProfileRequest FormRequest
- ✓ Create InstructorProfileController API controller
- ✓ Add PUT route to api.php
- ✓ Update api.md documentation
- ✓ Write Pest feature test (7 test cases)

### Reflection
Implementation followed existing patterns exactly. The endpoint derives the instructor from the Bearer token (never from URL/request), which aligns with the API Identity Resolution rules. The InstructorPolicy ensures self-only access. All fields are optional in the request to support partial updates.

---

## PHASE 3: FINAL REFLECTION & DOCUMENTATION
**Status:** ✅ Complete

### Summary
Built a complete PUT /api/v1/instructor/profile endpoint following the Controller -> Service -> Action pattern. The InstructorPolicy enforces that only the owning instructor can update their profile. The endpoint supports partial updates to bio, transmission_type, address, and postcode. Documented in api.md with changelog entry. Feature tests cover: full update, partial update, student rejection, auth required, validation errors, and response structure.

### Potential Considerations
- If postcode changes should trigger geocoding (lat/lng update), that logic would need to be added to the Action. Currently it only updates the postcode string.
- Additional fields can be added to the FormRequest rules as the instructor profile grows.

### Score: 8/10
Clean implementation following all existing patterns. Deducted for not handling postcode geocoding on update (which may or may not be desired).
