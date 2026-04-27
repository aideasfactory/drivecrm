# Task: Email Temporary Password to First-Time Pupils After Successful Payment

## Overview
When a pupil completes onboarding and makes their first successful payment (weekly or upfront), if they are a newly created user, email them a temporary password so they can log in to the mobile app.

## Phase 1: Planning ✅
- [x] Review onboarding flow (StepSixController, CreateUserAndStudentFromEnquiryAction)
- [x] Review webhook handlers (WebhookController)
- [x] Review existing WelcomeStudentNotification pattern (CreatePupilAction)
- [x] Identify all paths where payment succeeds (weekly immediate, upfront via webhook/redirect)
- [x] Plan implementation approach

## Phase 2: Implementation ✅
- [x] Create `OnboardingWelcomeNotification` notification class
- [x] Create `SendOnboardingWelcomeAction` in `app/Actions/Onboarding/`
- [x] Modify `CreateUserAndStudentFromEnquiryAction` to return readable temp password
- [x] Update `StepSixController::store()` to store is_new_user + encrypted temp password
- [x] Update `StepSixController::success()` to send temp password email after payment
- [x] Update `WebhookController` as fallback for upfront payment path
- [x] Write Pest tests

### Reflection
- Reused existing notification pattern from WelcomeStudentNotification
- Encrypted temp password storage in enquiry prevents plain-text exposure
- Double-send prevention via temp_password_sent flag
- Both weekly and upfront paths covered consistently

## Phase 3: Final Review ✅
- [x] All files follow coding standards (Actions in domain folder, proper namespacing)
- [x] No security concerns — temp password encrypted at rest, cleared after sending
- [x] Both payment paths covered (weekly in success(), upfront in success() + webhook fallback)
- [x] Edge cases handled (double-send prevention, existing users skipped, missing data handled)

**Last Updated:** 2026-04-27
