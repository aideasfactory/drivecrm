# Task: Fix instructor self-add sign-up flow

## Overview
Sam reported that when he tries to add himself as an instructor during sign-up, pressing "OK" (the Create Instructor button) does nothing — the instructor is not added and there is no error message. This task fixes the silent failure and adds clear UI feedback.

Branch: `feature/019ed9ed-4b56-726e-b611-8d5837366a60-fix-instructor-self-add-sign-up-flow`

---

## Investigation summary

Two distinct failure paths reproduce "nothing happens":

1. **Validation error displayed inline, no toast** — `StoreInstructorRequest` includes `email.unique:users,email`. If Sam types his own email (which already exists in `users`) he gets a 422 with `errors.email = "This email address is already in use."`. The sheet displays the error inline beneath the email input but shows **no toast**, so a user looking at the submit button thinks nothing happened.

2. **Silent backend failure on postcode lookup** — `'postcode'` is `nullable` in `StoreInstructorRequest`. If postcode is missing or fails the `postcodes.io` lookup, `InstructorService::createInstructor()` swallows the failure and returns `['success' => false, 'error' => '...']`. **`InstructorController::store()` discards that return value** and unconditionally `redirect()->route('instructors.index')`. From Inertia's perspective this is a successful POST — `onSuccess` fires, the sheet closes, and the user is left wondering why nothing happened.

There is **no max-instructor limit**. There is no business rule limiting the number of instructors. The only effective limits are:
- `users.email` unique constraint
- `instructors.user_id` unique constraint

---

## Phase 1: Planning ✅

- [x] Trace the "Add Instructor" sheet → `/instructors` POST → `InstructorController::store` → `InstructorService::createInstructor`
- [x] Confirm there is no max-instructor cap anywhere in the codebase
- [x] Identify the silent-failure root cause: controller ignores service's `success/false` array
- [x] Identify the secondary issue: postcode is nullable in validation but required by the service

### Reflection
- The legacy "return an array with `success` boolean" pattern in `createInstructor` is the structural cause. The controller never checks it, so any internal failure is invisible.
- The cleanest fix is to refactor `createInstructor` to throw `ValidationException` for known recoverable issues (postcode) and let the controller's validation pipeline surface the error to the form.

## Phase 2: Implementation ✅

- [x] Make `postcode` required in `StoreInstructorRequest` (the service needs it)
- [x] Refactor `InstructorService::createInstructor()` to throw `ValidationException` on postcode lookup failure and return an `Instructor` directly
- [x] Update `InstructorController::store()` to use the new return type and let validation exceptions surface to Inertia
- [x] Update `AddInstructorSheet.vue` to show success and error toasts (no more silent UI)
- [x] Show a general error toast when the server returns errors without specific field messages
- [x] Add a Pest feature test that proves the failure modes now surface as 422 errors with messages

### Reflection
- The fix is small (controller + service + form request + frontend toast) and targets the exact source of "nothing happens" rather than papering over symptoms.
- We deliberately did NOT add a max-instructor limit because none exists in the product; if one is needed later it would belong as a Policy/action gate, not a silent guard.

## Phase 3: Wrap-up ✅
- [x] Create `results.md` client-facing summary with confidence score
- [x] Write `.phase_done` sentinel

### Reflection
- Confidence is high: the controller now relies on Laravel's exception → 422 pipeline that the Inertia frontend already understands, and we added an explicit toast so generic errors can't go unnoticed.

---

Status: ✅ Complete
