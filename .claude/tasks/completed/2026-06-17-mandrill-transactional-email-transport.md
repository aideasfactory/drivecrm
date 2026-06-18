# Task: Mandrill Transactional Email Transport

## Overview

Wire up Mandrill (Mailchimp Transactional) as a Laravel mail transport so the
existing Mailables (e.g. `BookingEnquirySubmittedMail`) deliver through Mandrill
instead of the `log` driver. Also add a thin Service for sending
**Mandrill-hosted templates** (e.g. the `magiclink` template reused from the
smartdriving project) which Laravel's transport layer can't address directly.

Production sending domain: `just-drive.co.uk` (or a subdomain like
`mail.just-drive.co.uk`). During testing the team will send from
`noreply@mail.drivedrivingschool.co.uk`, which is already DKIM/SPF-verified in
the shared Mandrill account from the smartdriving project.

## Phase 1: Planning ✅

### Why Mandrill and not Bird
- Existing Mandrill account already in use on smartdriving (api key, verified
  domain, the `magiclink` template).
- Avoids spinning up a second Bird Programmable Email channel and a second DNS
  setup just for transactional sends during the testing phase.
- Trade-off accepted: two providers in play (Bird for inbox conversations,
  Mandrill for transactional). Acceptable while volume is low; revisit before
  go-live if consolidation matters.

### Why a Service for templates but NOT for Mailables
- Blade-rendered Mailables already use the `Mail` facade — `Mail::extend()`
  swaps the transport transparently. No wrapper Service needed.
- Mandrill-hosted templates (authored in the Mandrill dashboard, not in this
  repo) require a direct API call to `messages/send-template` with merge
  variables. That's the only thing that needs new code.

## Phase 2: Implementation ✅

### Files created
- `app/Services/MandrillTemplateService.php` — sends a Mandrill-hosted template
  to a single recipient with merge vars. Extends `BaseService`. Reads API key
  from `config('services.mandrill.key')`. Throws on HTTP failure or
  Mandrill-side reject/invalid status.

### Files edited
- `config/mail.php` — added `mandrill` mailer config block (`transport` =>
  `mandrill`, `key` => `env('MANDRILL_API_KEY')`).
- `config/services.php` — added `mandrill.key` entry (the conventional spot for
  third-party credentials, kept separate from the mailer config).
- `app/Providers/AppServiceProvider.php` — added `registerMandrillTransport()`
  which calls `Mail::extend('mandrill', ...)` returning a
  `Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillApiTransport`.
- `.env.example` — added `MANDRILL_API_KEY=` placeholder under a Mandrill
  comment block.

### Composer
- `composer require symfony/mailchimp-mailer` (v8) — provides
  `MandrillApiTransport`. The Symfony bridge for Mandrill is named after
  Mailchimp because Mandrill is the Mailchimp Transactional product.

### Key decisions
- **No `MandrillMailService` for standard sends.** Laravel's `Mail` facade is
  already the abstraction. A wrapper would be ceremony with no value.
- **`Mail::extend` rather than a custom service provider class.** One method on
  the existing `AppServiceProvider` is enough; no new file just to register a
  transport.
- **API key in `services.mandrill.key`, not `mail.mailers.mandrill.key`.**
  Following the Laravel convention — `config/services.php` is the canonical
  home for third-party credentials. The mailer config also reads it, but the
  Service uses the services-namespaced key.
- **Service throws `RuntimeException` on failure.** Lets callers decide whether
  to log, queue-retry, or surface to the user. Reject/invalid statuses are
  treated as failures because Mandrill returns HTTP 200 with `"status":
  "rejected"` for things like a recipient on the suppression list — silently
  letting that through would be worse than throwing.

### Verification done
- `php -l` on all four changed PHP files → no syntax errors.
- `php artisan config:clear` → cache flushed.
- `php artisan config:show mail.mailers.mandrill` → shows transport + key.
- `php artisan config:show services.mandrill` → shows key.

### Out of scope (not built)
- New Mailables (existing `BookingEnquirySubmittedMail` etc. already cover
  current flows).
- Webhook handler for Mandrill bounce/spam events.
- Suppression-list management UI.
- Migration to `just-drive.co.uk` sending domain — happens before go-live, not
  now.

## Phase 3: Reflection ✅

**Why this shape is right for the brief:**
- The user wanted to reuse the existing Mandrill setup from smartdriving for
  testing. The smallest possible footprint to do that: add the transport
  bridge, register it, set the env var. Total new code: one Service class
  (~110 lines) for the one thing the transport can't do (template sends).
- Existing Mailables stay untouched. Existing `Mail::send()` calls anywhere in
  the codebase now route through Mandrill the moment `MAIL_MAILER=mandrill` is
  set in `.env`.

**Subtle decisions worth flagging:**
- The Symfony package name (`symfony/mailchimp-mailer`) is non-obvious because
  Mandrill rebranded to "Mailchimp Transactional Email" in 2020 but most
  developers still call it Mandrill. The transport class itself is
  `MandrillApiTransport`, which is why the config key stays as `mandrill`.
- `Mail::extend()` is called in `boot()`, which runs after all providers are
  registered. This is the correct lifecycle hook — the `MailManager` resolves
  transports lazily on first `Mail::mailer('mandrill')` call, so the closure
  doesn't fire until something actually tries to send.
- `MandrillTemplateService::send()` returns the first recipient entry from
  Mandrill's response array. Mandrill always returns an array (one entry per
  recipient), but the Service is single-recipient by design — multi-recipient
  blasts are a marketing concern and should use Mandrill's dashboard or a
  proper campaign tool.
- The Service uses `Http::acceptJson()->post(...)`. No retries configured —
  callers that need retries should dispatch via a queued job
  (`ShouldQueue` Mailable pattern handles this for transport sends already;
  template-API sends would need their own job class if retry semantics matter).

**Operational notes for the user:**
- **`.env` must be set:** `MANDRILL_API_KEY=` needs the actual key from the
  smartdriving Mandrill account. (User has already done this.)
- **`MAIL_MAILER=mandrill`** must be flipped from `log` for actual sends to
  happen. Leave on `log` for local dev to avoid burning API calls.
- **`MAIL_FROM_ADDRESS`** should be set to
  `noreply@mail.drivedrivingschool.co.uk` for testing, then changed to the
  `just-drive.co.uk` (or subdomain) sending address before go-live.
- **Before go-live on `just-drive.co.uk`:** add the new domain to Mandrill,
  publish DKIM + SPF + Return-Path DNS records, verify in the Mandrill
  dashboard, then update `MAIL_FROM_ADDRESS`.
- **Calling Mandrill templates:** inject `MandrillTemplateService` into a
  Controller/Service constructor and call
  `$this->mandrill->send('template-slug', $email, ['VAR' => $value])`.

**Out of scope (carried forward from Phase 1, NOT done):**
- Webhook ingestion for delivery events / bounces / unsubscribes.
- Suppression-list sync between Mandrill and the local users table.
- Decision on consolidating Bird + Mandrill (deferred until pre-launch review).

**Technical debt / follow-up not done:**
- No tests added (project rule: user maintains tests manually).
- No Pint formatting run (project rule: user handles code style).

---

**Status:** All phases complete.
**Last Updated:** 2026-05-15.
