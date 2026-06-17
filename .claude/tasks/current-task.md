# Task: Google tracking integration on the booking page

## Overview

Add Google Ads conversion tracking (Tag ID `AW-10884289539`) and GA4 measurement
(Stream ID `G-NBYWT0EZF6`) to the public `/booking` page. The page is rendered
from a Vue/Inertia component (`Booking/Step1.vue`) served via the
`booking.start` route, and the root Blade template (`resources/views/app.blade.php`)
already loads Google Tag Manager + Google Consent Mode on all `booking.*`
routes. The new gtag.js loader must sit alongside the existing GTM snippet,
share the same dataLayer, and respect the same Consent Mode defaults so users
who reject analytics cookies are not tracked.

## Phase 1: Planning ✅

### Approach
- Follow the existing GTM pattern in `resources/views/app.blade.php`:
  config-driven IDs, only emit on `booking.*` routes, use Google Consent Mode.
- Add two new config entries under `services.google_tag` — `ads_id` and
  `ga4_id` — with sensible env-driven defaults pointing at the provided IDs.
- Inject one gtag.js loader (sourced from the Google Ads ID since one loader
  can drive multiple destinations) and call `gtag('config', ...)` for each ID.
- Place the snippet AFTER the existing GTM block so the consent default is
  already pushed to `dataLayer` before gtag inits.
- The IDs are public values that ship in HTML, so storing the defaults in code
  is fine; env override is still wired up for parity with the GTM setup.

### Files to touch
- `config/services.php` — add `google_tag.ads_id` and `google_tag.ga4_id`.
- `resources/views/app.blade.php` — inject gtag.js when on `booking.*`.
- `.env.example` — document the new env vars.

### Out of scope
- Conversion event firing (already handled in `Booking/Step2.vue` via GTM).
- Touching the cookie-consent UI; gtag respects the existing Consent Mode.

## Phase 2: Implementation ✅

### Tasks
- [x] Added `google_tag.ads_id` and `google_tag.ga4_id` to `config/services.php`,
      defaulting to the provided IDs but env-overridable.
- [x] Split the existing GTM block in `app.blade.php` so the Google Consent
      Mode `default` push happens once for either GTM or gtag, then injected
      the gtag.js loader (one async script tag) plus `gtag('config', ...)` for
      both `AW-10884289539` and `G-NBYWT0EZF6`.
- [x] Added commented `GOOGLE_ADS_ID` / `GOOGLE_GA4_ID` entries to `.env.example`
      next to a documentation note pointing at `config/services.php`.

### Notes
- The new gtag block lives inside `request()->routeIs('booking.*')`, matching
  the existing GTM gate. The `/booking` entry point is itself a redirect to
  `/booking/{uuid}/step/1`, so tracking only fires on the pages the user
  actually interacts with — which is the intended behaviour.
- Both gtag config calls inherit the same `dataLayer` and Consent Mode defaults
  set above, so a visitor who rejects analytics cookies is still not tracked.

## Phase 3: Reflection ✅

### What went well
- Existing GTM scaffold (route gate, Consent Mode block, env-driven config)
  made the gtag addition a strict additive change — no behaviour for non-booking
  routes changed.
- Sharing the consent-default script between GTM and gtag avoided duplicating
  the Consent Mode push (which would have been harmless but noisy).

### Trade-offs / future work
- The IDs are checked in as defaults. They are public values that ship in every
  rendered booking page anyway, so this is fine, but if the brand ever spins up
  a separate Google Ads account per environment the env overrides are ready.
- We intentionally did NOT route the new IDs through GTM (which is also on the
  page). That keeps the data path explicit: GTM continues to fire the
  conversion event from `Booking/Step2.vue`, while gtag.js drives both Google
  Ads + GA4 direct destinations. Worth revisiting if analytics ops wants
  everything funneled through GTM later.

Last Updated: 2026-06-17
