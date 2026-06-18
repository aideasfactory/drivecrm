# Instructor onboarding email — results

## What was the problem?
When an admin added a new instructor (single form **or** CSV bulk import) the platform created the user with the literal password `"password"` and **never emailed the instructor**. They had no way to know the account existed and the default credential was guessable by anyone who knew the platform — a serious security gap.

## What's been delivered
A secure first-login flow that wraps both the single-create form and the bulk CSV import:

1. **Secure default password.** New instructors are created with a **cryptographically random, throwaway** password (48 random characters). The admin never sees it; the instructor never needs it.
2. **Welcome email with a password-setup link.** As soon as the instructor record is committed, a queued "Welcome to Drive — set up your instructor account" email goes out. It contains a **password-setup link** (a Laravel password-broker token) that takes them to the existing reset-password page where they choose their own password. No plain-text password is ever in transit or in the inbox.
3. **Plain English next-steps.** The email tells the instructor exactly what to do:
   1. Click **Set up your account**
   2. Choose a strong password
   3. Sign in

   It also tells them the link expires in 60 minutes and what to do if it has (use **Forgot password** on the sign-in page — same Laravel mechanism, fully self-service).
4. **Failure handling for admins.**
   - The user's `welcome_email_pending` flag is set on creation and only cleared once the email actually queues. If the mailer fails, the flag stays `true`, the failure is logged, and an activity entry is written against the instructor.
   - On the **Instructor Show page**, owners now see an amber banner saying *"Welcome email hasn't been delivered yet"* with a **Resend welcome email** button when the flag is set.
   - For CSV bulk imports, a per-row error is added to the result modal so the admin can see exactly which instructor's invite did not go out.
5. **Resend endpoint.** New owner-only route `POST /instructors/{instructor}/resend-invite` mints a fresh token and queues a new email — useful when the original failed or the 60-minute setup link expired.

## Why a password-setup link (and not a temporary password)?
We evaluated three options:

| Option | Verdict |
| --- | --- |
| Temporary plain-text password in the email | Works (it's what the pupil flow does) but the password sits in the inbox forever, can be shoulder-surfed, and we'd be responsible for forcing a change later. |
| One-off magic-link login | Powerful but introduces a new auth surface we'd need to build and audit. |
| **Password-setup link via Laravel's password broker (chosen)** | Reuses Laravel's audited password-reset machinery, the token is short-lived and single-use, the instructor picks their own password, and if the link expires they can self-serve via **Forgot password** with no admin involvement. |

## Files changed / added

**New**
- `app/Mail/InstructorWelcomeMail.php` — the queued welcome email
- `resources/views/emails/instructor-welcome.blade.php` — plain-English HTML template
- `app/Actions/Instructor/SendInstructorWelcomeEmailAction.php` — mints the token, queues the email, manages the `welcome_email_pending` flag, logs activity, never throws
- `tests/Feature/Instructors/InstructorWelcomeEmailTest.php` — Pest feature tests covering the entire flow

**Modified**
- `app/Services/InstructorService.php` — uses a random password; dispatches the welcome email after the transaction commits; exposes `resendWelcomeEmail()`
- `app/Actions/Instructor/BulkImportInstructorsAction.php` — same: random password + per-row welcome email + per-row failure surfaced
- `app/Http/Controllers/InstructorController.php` — new `resendWelcomeEmail` action; show payload now includes `welcome_email_pending`
- `routes/web.php` — `POST /instructors/{instructor}/resend-invite` (owner-only)
- `resources/js/pages/Instructors/Show.vue` + `resources/js/types/instructor.ts` — amber banner + **Resend welcome email** button (owners only) when the flag is set
- `.claude/database-schema.md` — updated to document the new use of `welcome_email_pending`

## How to verify
1. As an owner, go to **Instructors → Add instructor**, create an instructor with a real email address.
2. Check the inbox (or the local mailpit/mailtrap) — a *Welcome to Drive — set up your instructor account* email should arrive, signed off in plain English with a clear **Set up your account** button.
3. Click the button → land on `/reset-password/{token}` → choose a password → sign in.
4. To test the failure path: stop the mailer, click **Resend welcome email** from the Show page — the amber banner stays and an error toast appears. Restart the mailer and click resend again — banner disappears, success toast.
5. Test bulk import via **Instructors → Import CSV** — each successful row gets an invite; rows whose invites fail are listed as warnings in the result modal.

## Tests
Pest feature tests cover:
- Creating an instructor enqueues a welcome email to their address.
- The setup URL contains a token that is valid against Laravel's password broker.
- The default password is **not** the literal string `"password"`.
- Owners can resend; non-owners are forbidden (403).
- When the mailer throws, `welcome_email_pending` stays `true` so admins see the banner.
- Bulk import enqueues one email per row.
- The Show page exposes `welcome_email_pending` so the banner can render.

(As per project standards, tests are written but not run by me — `php artisan test` is reserved for the user.)

## Confidence score: **8.5 / 10**

What I'm confident in:
- Reuses Laravel's audited password-broker tokens — no bespoke crypto, no plain-text password leaves the system.
- Failure path is observable: a flag on the user, an activity log line, an admin banner, and a one-click resend.
- Mirrors the existing `WelcomeStudentNotification` / `welcome_email_pending` patterns, so it should feel familiar to anyone reading the code later.

What kept it from a 10:
- I could not run the test suite or the dev server in this sandbox; while the tests are mechanically straightforward and the changes follow existing patterns, a 10 requires me to have actually pressed the buttons.
- The Vue banner uses `MailWarning` from `lucide-vue-next`; the installed version (0.468) ships it, but if you ever pin an older lucide you'd need to swap it for `Mail` + a warning colour.
- The reset link expires after 60 minutes (Laravel default). If real instructors are slow to read email, you may want to bump `config/auth.php passwords.users.expire` for a friendlier window — out of scope for this ticket.
