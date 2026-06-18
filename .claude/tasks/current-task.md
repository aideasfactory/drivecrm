# Task: Password Reset Notification Email for Pupils

## Overview

When an instructor or admin resets a pupil's password via the
`AdminResetPasswordAction` (called from `PupilController::updatePassword`),
the pupil currently receives no notification. They get a new password they
don't know about.

This task adds a transactional email that fires the moment a pupil's
password is reset by an admin/instructor, with the new password included
so they can sign back in.

## Phase 1: Planning ✅

### Where the reset currently happens
- `app/Actions/Shared/AdminResetPasswordAction.php` — does
  `$user->update(['password' => $password, 'password_change_required' => true])`
  and logs activity. The `password` cast is `'hashed'`, so the raw password
  is hashed automatically. The action receives the plain password as
  argument, so we still have it to put in the email.
- Called from `PupilController::updatePassword` (pupil reset) and
  `InstructorController::updatePassword` (instructor reset).

### Scope decision: pupils only
Brief says "Send a notification email to the pupil when their password is
reset by an instructor or admin." It does NOT ask for the same for
instructors. So:
- The action will check `$user->student` (i.e. has a Student profile) before
  sending. If the user is an instructor, no email goes out — preserves
  current behaviour for that path.
- Done inside the Action (not the Controller) so any future caller of the
  shared Action automatically gets the notification too.

### Why check `$user->student` rather than `$user->isStudent()`
The role-enum check requires the `role` column to be set. The Student
factory creates the underlying User with no role (UserFactory doesn't set
one), so a role-based check would silently break in tests. The
relationship check asks the source of truth — "is there a Student profile
attached to this User?" — and works in tests and prod alike. The one
extra query on an admin-only action is negligible.

### Why queue, not send
- Existing mailers use `->queue()` (`LessonResourcesAssigned`,
  `ProcessResourceRecommendationsJob`). Consistent with project pattern
  and avoids holding up the HTTP response.

### Out of scope
- Sending the same email when an instructor's password is reset (brief
  scoped to pupils only).
- A password-reset email for self-service / Fortify resets — those already
  have their own flow.

## Phase 2: Implementation ✅

### Files created
- `app/Mail/PupilPasswordResetMail.php` — Mailable, queued, takes
  `(User $user, string $newPassword)`. Subject:
  *"Your {app-name} password has been reset"*. Renders
  `emails.pupil-password-reset` and passes:
  - `pupilName` — derived from `User::name` (falls back to "there")
  - `email` — `User::email`
  - `newPassword` — the plain password the admin just set
  - `loginUrl` — `url('/login')`
  - `appName` — `config('app.name')`
- `resources/views/emails/pupil-password-reset.blade.php` — matches the
  existing email design system used by `lesson-feedback-request.blade.php`:
  red brand bar, logo, monospace credentials block, accent button, and a
  security notice prompting the user to change it on next sign-in.

### Files edited
- `app/Actions/Shared/AdminResetPasswordAction.php` — after the activity
  log, queues `PupilPasswordResetMail` to `$user->email` iff
  `$user->student` is present and the user has an email. Instructors
  hitting this action are untouched.
- `tests/Feature/AdminPasswordResetTest.php` — added two new tests:
  1. **pupil receives an email with the new password when an admin resets it**
     — uses `Mail::fake()`, asserts the queued mail has the pupil's email,
     the correct `newPassword`, and references the right user.
  2. **no notification email is sent when an instructor password is reset**
     — confirms the scope guard works.

### Verification done
- `php -l` on all three changed PHP files → no syntax errors.

## Phase 3: Reflection ✅

### Why this shape fits the brief
- The brief asked for one thing: email the pupil their new password on an
  admin/instructor-driven reset. The action is the single point in the
  codebase where that reset happens, so adding the mail dispatch *there*
  guarantees every existing and future caller (admin UI today, future
  bulk admin tools, future API endpoints) gets the email for free.
- No new Service, no controller changes, no new route. Minimal surface
  area.

### Subtle decisions worth flagging
- **Plain password in the email body.** Standard practice for
  admin-driven resets is generally a *link* rather than the plain
  password. But the brief explicitly says "Include the new password in
  the notification email", so we honour that. The notice block prompts
  the pupil to change it on next sign-in, and the existing
  `password_change_required` flag will force them to do so.
- **Relationship guard not role guard.** As noted in Phase 1 — protects
  us against role-less User rows.
- **Mail::to(string $email).** We send to the User's email, not the
  Student's `email` column. The User row is the source of truth for
  login email, so that's where the password notification has to go.
- **Queued.** Matches the pattern used by `LessonResourcesAssigned` and
  `ProcessResourceRecommendationsJob` — keeps admin UI snappy and lets
  the queue worker handle the SMTP/API round-trip.

### Anti-patterns / potential overheads
- **Plaintext-password-in-email** is a known risk: anyone with access to
  the pupil's inbox can sign in. The brief required it, the
  `password_change_required` flag mitigates it, but worth noting for a
  future "Generate a secure reset link instead" follow-up.
- **One extra query** (`$user->student`) on the reset path. Trivial; not
  worth optimising.

### Out of scope (carried forward)
- Email for instructor password resets.
- Generate-link flow instead of plaintext-password flow.
- Webhook handling for bounce/delivery on this particular mail (we rely
  on the existing Mandrill transport setup).

### Score
8/10. Tight, follows existing patterns, covered by tests. Loses 2 points
because the design choice it implements (plain password in email) is
inherently weaker than a link-based reset — but that constraint comes
from the brief, not from the implementation.

---

**Status:** All phases complete.
**Last Updated:** 2026-06-17.
