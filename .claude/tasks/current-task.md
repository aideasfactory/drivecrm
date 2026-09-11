# Task: Single reusable password-reset token

**Created:** 2026-09-11
**Last Updated:** 2026-09-11
**Status:** Complete

---

## Overview

Drive CRM admin (Fortify + Laravel password broker). Users who request
password reset more than once get a new hashed token; the first email
link then fails with an invalid-token error. Fix: one outstanding token
that is resent on later requests and stays valid until the password is
actually changed. Instructor welcome/invite uses the same broker — do
not break it. No tests (HARD RULE).

Ticket: 01a08fa4-f131-70a3-972d-391c277c7ced (urgent bug).

### Success Criteria
- [x] Requesting reset again does not invalidate an unused first link
- [x] Same outstanding token is resent (Laravel token repository)
- [x] Token is deleted when the password is successfully changed
- [x] Instructor welcome / password-setup links still work
- [x] Expiry/throttle trade-offs documented
- [x] No tests added (HARD RULE)

---

## PHASE 1: PLANNING

**Status:** ✅ Complete

### Current state
- Fortify `Features::resetPasswords()` with Inertia Forgot/Reset views.
- `routes/auth.php` Breeze controllers are commented out of `web.php`.
- Laravel `DatabaseTokenRepository::create()` always `deleteExisting()`
  then inserts a new bcrypt hash. Table PK is `email` (one row).
- Tokens are hashed, so the plaintext cannot be read back to resend.
- `config/auth.php` expire was 60 minutes, throttle 60 seconds.
- Instructor welcome mints `Password::broker()->createToken($user)` and
  links to `password.reset` — same invalidation bug on resend.
- Student invite uses a temporary password, not the broker.
- Admin / settings / API password changes did not delete reset tokens.

### Approach
1. Custom `ReusableDatabaseTokenRepository` extending Laravel’s
   `DatabaseTokenRepository`.
2. Store an APP_KEY-encrypted copy of the plaintext token so the same
   value can be resent. Keep the bcrypt `token` column for `exists()`.
3. Custom `PasswordBrokerManager` so Fortify/Password::broker() uses it.
4. Reuse the outstanding unexpired token; refresh `created_at` so the
   idle window follows the latest request. Mint a new token only when
   none exists, it expired, or the encrypted copy is missing.
5. Default expire 1440 minutes (24h). `0` means never expire (custom
   `tokenExpired()`). Keep throttle at 60s. Document why not infinite
   by default.
6. Delete the broker token on admin reset, settings/API password change,
   and student re-invite (password replaced). Broker reset already deletes.
7. Instructor welcome copy: human-readable expiry (`expires_in`).

### Tasks
- [x] Trace Fortify, broker, instructor welcome, student invite
- [x] Choose reusable DatabaseTokenRepository + encrypted sidecar

### Reflection
Laravel hashes reset tokens, so “resend the same token” needs a
retrievable copy. Encrypting with APP_KEY keeps `exists()` on bcrypt
and avoids inventing a custom HMAC scheme. Student invites stay on
temporary passwords.

**Last Updated:** 2026-09-11.

---

## PHASE 2: IMPLEMENTATION

**Status:** ✅ Complete

### Currently working on
Complete.

### Tasks
- [x] ReusableDatabaseTokenRepository + PasswordBrokerManager
- [x] Bind custom broker manager from FortifyServiceProvider
- [x] encrypted_token migration
- [x] expire 24h / throttle unchanged; expire=0 = never
- [x] Delete token on admin reset, settings/API change, student re-invite
- [x] Instructor welcome copy uses human-readable validity
- [x] Update database-schema.md
- [x] No tests (HARD RULE)

### Reflection
Fortify, instructor welcome, and forgot-password all go through
`Password::broker()`, so one repository fixes every unused-link
invalidation. Literal never-expire is available via
`AUTH_PASSWORD_RESET_EXPIRE=0` but is not the default — stolen
mailbox + immortal link.

I've updated database-schema.md to reflect the migration changes.

**Last Updated:** 2026-09-11.

---

## PHASE 3: REFLECTION

**Status:** ✅ Complete

### Tasks
- [x] Document decisions and trade-offs

### Reflection
Reuse + 24h idle window is the Laravel-shaped fix. True never-expire
conflicts with Laravel’s short-lived token default; expire=0 is
supported and documented. Throttle is unchanged. No mobile API change
— no api.md update. No tests per HARD RULE. This environment has no
PHP binary so artisan/pint were not run.

I understand I must not run tests or linting commands.

**Last Updated:** 2026-09-11.
