# Task: Drive onboarding & booking form — remove cookie banner + capture Google Ads gclid

## Overview

Two small marketing-flow changes to the public `/onboarding` and `/booking`
flows:

1. **Remove the cookie banner completely.** The vanilla-cookieconsent bar,
   the "Cookie preferences" links in the page footers, and every piece of
   consent-gating in the code (Google Maps, GTM Consent Mode default-deny)
   go away.
2. **Capture `gclid` from Google Ads landing URLs** and forward the value to
   third parties as a `source` parameter labelled `Google ads`.

The client has decided to run without a consent banner (their legal team's
call). GTM, GA4, and Google Ads tags will simply fire when the booking /
onboarding pages load, so we can drop the Consent Mode default-deny block
too.

## Locations identified

**Cookie banner:**
- `resources/js/app.ts` — `initCookieConsent()` call on app boot.
- `resources/js/lib/cookieConsent.ts` — the whole init + preferences module.
- `resources/js/lib/cookieConsent.css` — companion stylesheet.
- `resources/js/components/CookiePreferencesLink.vue` — the reusable link.
- `resources/js/pages/Booking/Step1.vue` — mounts `<CookiePreferencesLink />`.
- `resources/js/pages/Booking/Step2.vue` — mounts it in both branches.
- `resources/js/components/Onboarding/OnboardingFooter.vue` — mounts it in the footer.
- `resources/js/components/Onboarding/InstructorMap.vue` — gates map init behind functional consent.
- `resources/views/app.blade.php` — Google Consent Mode default-deny block.
- `package.json` — `vanilla-cookieconsent` dep.

**gclid capture & forward:**
- `app/Http/Controllers/Booking/BookingController.php` — `/booking` entry point.
- `app/Http/Controllers/Onboarding/OnboardingController.php` — `/onboarding` entry point.
- `app/Services/BirdContactService.php` — payload to Bird CRM (third-party sink).
- `app/Mail/BookingEnquirySubmittedMail.php` + `resources/views/emails/booking-enquiry-submitted.blade.php` — internal notification.
- `resources/js/pages/Booking/Step2.vue` — GTM `booking_enquiry_submitted` dataLayer push.

## Phase 1: Planning ✅

### Cookie banner removal

- Delete the vanilla-cookieconsent init and its two supporting files
  (`cookieConsent.ts`, `cookieConsent.css`, `CookiePreferencesLink.vue`).
- Strip `<CookiePreferencesLink />` from `Booking/Step1.vue`, `Booking/Step2.vue`,
  and `OnboardingFooter.vue`.
- Simplify `Onboarding/InstructorMap.vue`: drop the "awaiting consent" state,
  the `hasFunctionalConsent`/`openCookiePreferences` imports, and the
  `cc:onConsent`/`cc:onChange` listeners. The map now initialises unconditionally.
- Remove the Consent Mode default-deny block from `resources/views/app.blade.php`.
  GTM/GA/Ads tags will fire straight away once the visitor loads a
  booking/onboarding route.
- Drop `vanilla-cookieconsent` from `package.json`.

### gclid capture

- On `/booking` and `/onboarding` entry, read `?gclid=` from the query. If
  present, store `data.tracking = { gclid, source: 'Google ads' }` on the
  new enquiry.
- In `BirdContactService::buildPayload()`, read the enquiry's tracking data
  and, when present, add a `source` attribute (`Google ads`) plus a `gclid`
  attribute so the ads-source is available in Bird CRM.
- In the admin email view, add a "Source" row so operators see where the
  enquiry came from.
- On `Booking/Step2.vue`, enrich the `booking_enquiry_submitted` dataLayer
  event with `source` and `gclid` so GTM can forward them to Google Ads
  conversion / GA4.

### Out of scope

- Multi-source attribution (UTM, fbclid, etc.). Ticket is explicit: gclid only.
- New DB column for the gclid: the enquiry already stores an arbitrary `data`
  JSON blob; adding a nested `tracking` key is enough and keeps the schema
  unchanged.
- Bird CRM cookie policy / consent reconciliation. The client's decision to
  drop the banner is documented in the client-facing summary; policy sits
  outside this ticket.

## Phase 2: Implementation ✅

### Cookie banner removal

- `resources/js/app.ts` — removed the `initCookieConsent` import and boot call.
- `resources/js/pages/Booking/Step1.vue` — removed `<CookiePreferencesLink />`
  and its import.
- `resources/js/pages/Booking/Step2.vue` — removed both mounts and the import.
- `resources/js/components/Onboarding/OnboardingFooter.vue` — removed the
  link mount and its import.
- `resources/js/components/Onboarding/InstructorMap.vue` — removed the
  "awaiting consent" branch, the `hasFunctionalConsent`/`openCookiePreferences`
  imports, and the `cc:onConsent`/`cc:onChange` listeners. `onMounted` now
  calls `initializeMap()` directly.
- `resources/js/lib/cookieConsent.ts`, `resources/js/lib/cookieConsent.css`,
  and `resources/js/components/CookiePreferencesLink.vue` — deleted.
- `resources/views/app.blade.php` — removed the Google Consent Mode
  default-deny block. GTM / gtag now fire on load for booking/onboarding
  routes without any consent gate.
- `package.json` — dropped the `vanilla-cookieconsent` dependency.

### gclid capture + forwarding

- `app/Http/Controllers/Booking/BookingController.php` — on `/booking`, read
  `?gclid=`, and when present, store
  `data.tracking = { gclid, source: 'Google ads' }` on the new enquiry.
- `app/Http/Controllers/Onboarding/OnboardingController.php` — same handling.
- `app/Models/Enquiry.php` — added a `getTracking()` helper that returns the
  tracking blob if present.
- `app/Services/BirdContactService.php` — payload now includes `source`
  (`Google ads`) and `gclid` attributes when the enquiry has captured
  tracking data. Falls back to nothing when absent — no behavioural change
  for organic traffic.
- `app/Mail/BookingEnquirySubmittedMail.php` + `resources/views/emails/booking-enquiry-submitted.blade.php`
  — added a "Source" row (with the gclid alongside when it exists) so
  operators can see the origin at a glance.
- `resources/js/pages/Booking/Step2.vue` — the `booking_enquiry_submitted`
  dataLayer event now carries `source` and `gclid` (nullable) so GTM can
  route the conversion to Google Ads.

### Key decisions

- **Server-side gclid capture, not client-side.** The Google Ads landing URL
  hits `/booking` or `/onboarding`, both of which already create the enquiry
  server-side. Reading `?gclid=` there is a two-line change and survives the
  Inertia redirect. Client-side capture would require reading `window.location`
  after redirect (the query string is lost by then) and adding a hidden form
  field for round-tripping. Not worth it.
- **`data.tracking` JSON, not a new column.** `Enquiry.data` is already JSON
  and stores step data. Adding `data.tracking` is zero-schema and works with
  the existing setter helpers.
- **`source: 'Google ads'` matches the ticket wording** (not "google_ads",
  not "Google Ads Click"). Human-readable and matches how Bird's UI displays
  the value.
- **The GTM Consent Mode default-deny is gone**, not left as a courtesy — the
  client wants tags firing without a banner. Leaving it in would silently
  suppress Ads/GA4 tags and make attribution look broken.

## Phase 3: Reflection ✅

**Why this shape is right for the brief:**
- The cookie banner touches ~8 files, all deletions. Zero new abstractions.
- The gclid capture also uses the existing `data` JSON blob and the existing
  Bird sync pipeline. No new tables, no new jobs, no new endpoints.

**Operational notes:**
- No DB migration.
- No new environment variables.
- No new Composer / npm packages — we dropped `vanilla-cookieconsent`, added
  nothing.
- Existing enquiries without `data.tracking` continue working; Bird payload
  simply omits `source` for them, matching current behaviour.

**Client-facing impact:**
- Booking/onboarding pages no longer show a cookie bar or "Cookie preferences"
  link. GTM / GA4 / Google Ads scripts fire on load.
- Bird CRM contacts synced from Google-Ads-sourced enquiries will now show
  `source = "Google ads"` and carry the gclid attribute for downstream
  audience-building.
- Admin new-enquiry emails now display the origin source and gclid.

**Follow-ups intentionally not done:**
- No tests added (project rule: user maintains tests).
- No `npm install` run to prune `vanilla-cookieconsent` from the lockfile —
  the user will run install as part of their build step.

---

**Status:** All phases complete.
**Last Updated:** 2026-07-08.
