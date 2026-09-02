# Task: Resend transactional mail

## Overview

Switch Drive CRM transactional email to Resend via Laravel's native Resend
mailer. The API key is supplied later via `.env` (`RESEND_API_KEY`); this
work wires the SDK, config, env template, and a diagnostic send command.

## Phase 1: Planning ✅

### Current state
- Laravel already had a `resend` mailer stub in `config/mail.php` and
  `config/services.php` (`RESEND_API_KEY`), but `resend/resend-php` was not
  installed (`composer.lock` only listed it as a Laravel suggest).
- Default mailer was `log`. Mandrill remains available as a custom transport.
- All Mailables and Notifications go through Laravel Mail, so changing
  `MAIL_MAILER` is enough to send them through Resend.

### Approach
1. Install `resend/resend-php` (Laravel 12 native driver).
2. Point `.env.example` at Resend (`MAIL_MAILER=resend`, empty `RESEND_API_KEY`).
3. Keep Mandrill code in place as a fallback mailer.
4. Add `mail:test-resend` so the key can be verified after it is added.
5. Update comments that still described Mandrill as the live transport.

### Reflection
Native Laravel Resend transport is the smallest change: no new service layer,
no template rewrite, no API contract. The user only needs to drop in
`RESEND_API_KEY` (and verify the from-domain in Resend).

## Phase 2: Implementation ✅

### Files edited
1. **`composer.json` / `composer.lock`** — `resend/resend-php` `^1.0` (v1.12.0).
2. **`.env.example`** — `MAIL_MAILER=resend`, empty `RESEND_API_KEY`, comments
   for verified from-domain and local `log` fallback.
3. **`config/mail.php`** — Resend mailer now carries `key` from `RESEND_API_KEY`.
4. **`config/services.php`** — comment documenting the Resend key.
5. **`app/Actions/YearEndArchive/SendArchiveReadyEmailAction.php`** — comment
   now names Resend as the transport.

### Files created
- **`app/Mail/ResendTestMail.php`** — diagnostic mailable used by the test command.
- **`app/Console/Commands/TestResendSend.php`** — `php artisan mail:test-resend {email}`.
- **`tests/Feature/Mail/ResendMailerTest.php`** — config, resolve, command, content.

### Key decisions
- **Native Laravel driver over `resend/resend-laravel`**: matches Laravel 12 docs.
- **Force `Mail::mailer('resend')` in the diagnostic command**: works even when
  local `MAIL_MAILER` is `log`.
- **Leave Mandrill in place**: unused unless `MAIL_MAILER=mandrill`.
- **No API key in the repo**: user supplies it in `.env` after merge.

## Phase 3: Reflection ✅

**Why this shape is right for the brief:**
- Transactional mail already flows through Laravel Mailables/Notifications.
  Installing the SDK and switching `MAIL_MAILER` is the whole production path.
- The diagnostic command is the only extra surface so the key can be verified
  without waiting for a real booking/invoice email.

**Operational notes:**
- After adding `RESEND_API_KEY`, run `php artisan config:clear` then
  `php artisan mail:test-resend you@example.com`.
- `MAIL_FROM_ADDRESS` must be on a domain verified in the Resend dashboard.
- Tests keep `MAIL_MAILER=array` via `phpunit.xml`.

**Follow-ups not done (out of scope):**
- Did not remove Mandrill or MailerSend packages.
- Did not migrate Mandrill-hosted templates (the template service is unused
  outside `mail:test-mandrill`).

**Status:** All phases complete.
**Last Updated:** 2026-09-02.
