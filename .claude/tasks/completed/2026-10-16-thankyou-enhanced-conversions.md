# Thank-you page Google Ads conversion + enhanced conversions

**Status:** ✅ Complete — 2026-10-16
**Ticket:** Mission Control 01a14510-d696-7395-acc5-33cc99b56ba1

## Overview
Google Ads conversion `app_booking_thankyou (web)` (AW-10884289539) stopped pinging after 13 Oct and Diagnostics asked for in-page enhanced conversions code.

## Phase 1: Investigation ✅
- ✓ Cause: `a1de4f7` (14 Oct) removed the hard-coded `gtag.js` / `gtag('config', 'AW-10884289539')`; tracking moved to GTM, which needs its own Ads conversion tag on the `purchase` event
- ✓ The thank-you page redesign kept its route; no route change

## Phase 2: Implementation ✅
- ✓ `ClaimPurchaseEventAction` adds `user_data` (email, E.164 phone, first/last name, postcode, country)
- ✓ `ContactDetails::toE164()`
- ✓ `lib/consent.ts` reads Google Consent Mode state from the dataLayer and watches for Usercentrics updates
- ✓ `usePurchaseTracking` waits for the consent default, only includes `user_data` once `ad_user_data` is granted, and pushes `purchase_consent_granted` after a later opt-in
- ✓ GTM / Ads / Usercentrics setup documented in `docs/gtm-enhanced-conversions.md`

## Reflection
GTM container configuration can't be verified from the repo; the doc lists the exact tags, triggers and variables required.

💳 Payment impact: TOUCHED — `app/Actions/Payment/ClaimPurchaseEventAction.php` only adds a `user_data` key to the analytics payload. Checkout verification, order status, Stripe and the once-per-order claim haven't changed. Risk: a null student or contact field leaves `user_data` empty, nothing else. Before release, do a test checkout on both success pages and confirm they render.
