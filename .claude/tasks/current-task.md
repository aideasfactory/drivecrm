# Task: Fix Onboarding Flow to Reuse Existing Users Without Duplication

## Overview
When someone completes onboarding with an email that already exists in the system, the flow should reuse the existing user and student records instead of failing or creating duplicates. The `CreateUserAndStudentFromEnquiryAction` already checks for existing users, but there are gaps: student data isn't updated for returning users, and there's no safeguard for edge cases like instructor/admin emails.

---

## Phase 1: Planning ✅ Complete
**Status:** ✅ Complete
**Last Updated:** 2026-03-24

### Tasks
- [x] Review `CreateUserAndStudentFromEnquiryAction` for all edge cases
- [x] Review `CreateOrderFromEnquiryAction` for returning-user handling
- [x] Review `StepSixController` payment flow for existing user compatibility
- [x] Identify all code changes needed
- [x] Document the fix strategy

### Decisions & Notes
- Only one file needs changes: `CreateUserAndStudentFromEnquiryAction`
- Core fix: `Student::firstOrCreate()` → `Student::updateOrCreate()`
- User reuse already works correctly
- Stripe customer handling already works correctly
- `CreateOrderFromEnquiryAction` needs no changes (orders are per-enquiry)
- Non-student users (instructor/owner) can safely go through onboarding — their role isn't changed

### Reflection
Clean analysis — only one file needs modification. The existing architecture handles most edge cases already.

---

## Phase 2: Implementation ✅ Complete
**Status:** ✅ Complete
**Last Updated:** 2026-03-24

### Tasks
- [x] Change `Student::firstOrCreate()` to `Student::updateOrCreate()` in `CreateUserAndStudentFromEnquiryAction`
- [x] Remove redundant instructor assignment update block
- [x] Add logging for existing student updates
- [x] Verify the `getStudentData()` method produces correct data for both new and returning users
- [x] Remove unused `Instructor` import

### Reflection
Minimal, focused change. The `updateOrCreate()` call now handles both new and returning students in a single operation — no need for the separate instructor update block.

---

## Phase 3: Documentation & Finalization ✅ Complete
**Status:** ✅ Complete
**Last Updated:** 2026-03-24

### Tasks
- [x] Final review of all changes for consistency
- [x] Verify both new-user and returning-user paths work correctly
- [x] Write `.phase_done` sentinel

### Reflection
Single-file fix with no new dependencies, no new services, no migrations. Both new and returning user paths verified logically. The `updateOrCreate` approach is idiomatic Laravel and handles all edge cases: new users, returning students, and non-student users (instructor/owner) going through onboarding.
