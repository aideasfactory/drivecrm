# Task: Add instructor onboarding email with login setup

## Overview
When an admin adds a new instructor (single form, CSV bulk import) no email is sent. The default password is the literal string `password`, which is a serious security issue and gives the instructor no way to know about the account.

This task wires up a secure first-login flow:

1. Replace the default `'password'` with a randomly-generated, unguessable password (so the account is never accessible without the email link).
2. Send the instructor a welcome email that contains a **password setup link** (Laravel password broker token) rather than a temporary password. The instructor clicks the link, sets their own password, and signs in.
3. Surface email send failures: mark the user `welcome_email_pending = true` until the notification is dispatched, log activity, and expose a "Resend welcome email" admin action so admins can recover from failures.
4. Cover the new code with Pest tests.

Branch: `feature/019ed9ee-bfc6-719c-a1c0-84af75e9bbb5-add-instructor-onboarding-email-with-login-setup`

## Why a password setup link (not a temp password)?
- The existing pupil flow emails a plain-text temporary password. That works but means the password lives in the inbox forever and is shoulder-surf vulnerable.
- The Laravel password broker is already wired (Fortify + `routes/auth.php`). The broker mints a token, the user receives a link to `password.reset`, and the existing `ResetPassword` Inertia page handles the password creation. No new auth surface area.
- The instructor never sees a server-generated password, the link expires (60 mins by default, refreshable via forgot-password), and the same flow handles future admin invites.

## Files to be touched / created

### New
- `app/Mail/InstructorWelcomeMail.php` — Mailable for the welcome email
- `resources/views/emails/instructor-welcome.blade.php` — HTML template
- `app/Actions/Instructor/SendInstructorWelcomeEmailAction.php` — mints token, dispatches mail, logs activity, manages `welcome_email_pending`
- `tests/Feature/Instructors/InstructorWelcomeEmailTest.php` — feature tests (single create, bulk import, resend, password setup link)

### Modified
- `app/Services/InstructorService.php` — call action after creating instructor; new `resendWelcomeEmail()` method
- `app/Actions/Instructor/BulkImportInstructorsAction.php` — generate random password, dispatch welcome email per row
- `app/Http/Controllers/InstructorController.php` — `resendWelcomeEmail` endpoint; expose `welcome_email_pending` on Show page
- `routes/web.php` — `POST /instructors/{instructor}/resend-invite`
- `resources/js/pages/Instructors/Show.vue` — surface invite-pending banner + resend button (lightweight — owner-only)

---

## Phase 1: Planning ✅
- [x] Audit current "create instructor" flow (`InstructorController::store`, `InstructorService::createInstructor`, `BulkImportInstructorsAction`)
- [x] Decide approach: password setup link via Laravel password broker (no plain-text password ever sent)
- [x] Confirm `welcome_email_pending` column exists on `users` (yes — migration 2026_04_27_193134)
- [x] Confirm Fortify routes (`password.reset`, `password.request`) are wired (yes, via `routes/auth.php`)
- [x] Confirm `WelcomeStudentNotification` pattern for analogous failure handling (use `welcome_email_pending`, log activity, allow resend)

### Reflection
- The `users` table already has `welcome_email_pending` and `password_change_required` columns from the pupil flow — we get the same defense-in-depth for instructors with no migration.
- Reusing the password broker keeps us on the audited Laravel reset path instead of inventing a new "magic link" surface area.
- The existing pattern in `SendOrderConfirmationEmailAction::sendWelcomeEmailIfPending` shows how to claim the pending flag atomically; we'll mirror it for the instructor variant.

## Phase 2: Implementation ✅
- [x] Create `InstructorWelcomeMail` Mailable
- [x] Create Blade view `emails/instructor-welcome.blade.php`
- [x] Create `SendInstructorWelcomeEmailAction` (mint token, mark pending, dispatch, log activity, on failure leave pending = true and log error)
- [x] Update `InstructorService::createInstructor` to use a cryptographic random password and dispatch the welcome email
- [x] Add `InstructorService::resendWelcomeEmail()` method
- [x] Update `BulkImportInstructorsAction` to use a random password and dispatch the welcome email
- [x] Add `InstructorController::resendWelcomeEmail` + route
- [x] Expose `welcome_email_pending` on the Show page payload and add the resend-button UI (owner-only)

### Reflection
- The store flow already runs inside a DB transaction. The email send is dispatched after the transaction commits so we never email someone who didn't get saved.
- Action returns a `bool` success and **never throws** to its callers — failures are logged and the pending flag is left at `true` so the admin UI can surface the issue.
- The bulk-import loop tolerates per-row send failures by logging them into the row-level `errors[]` array, so admins viewing the CSV result modal see exactly which instructor's invite did not go out.

## Phase 3: Tests, docs, sentinel ✅
- [x] Pest feature tests (notification dispatched; token created; pending flag toggles; resend works; failure leaves pending = true)
- [x] Update `api.md` if a new admin route counts — it's a web route, so api.md left alone; database-schema.md unchanged
- [x] Write `results.md`
- [x] Write `.phase_done` sentinel

### Reflection
- Branch unaffected by main; sentinel and results.md cover the deliverable for Gumbo.

---

**Status:** All phases complete.
**Last Updated:** 2026-06-17.
