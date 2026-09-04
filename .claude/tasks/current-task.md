# Task: MTD ITSA Connected + not-authorised contradiction

## Overview

Instructor HMRC tab can show a green "HMRC Connected" badge, a red
`CLIENT_OR_AGENT_NOT_AUTHORISED` flash ("Reconnect to grant the required
permissions"), and a grey "We haven't checked your MTD enrolment yet"
card at the same time.

Root cause: "Connected" is token-presence only. Enrolment check failures
are flashed and thrown, so `mtd_itsa_status` stays `unknown`. The error
copy treats HMRC identity/enrolment rejection as a missing OAuth scope.

## Phase 1: Planning ✅

### Current state
- Header badge = `hmrc_tokens` row exists.
- Refresh calls Business Details; `RULE_NOT_SIGNED_UP_TO_MTD` is mapped
  to `not_signed_up`, but `CLIENT_OR_AGENT_NOT_AUTHORISED` and
  `INVALID_SCOPE` are rethrown. Status stays `unknown`.
- VAT already surfaces missing scopes; ITSA does not.
- OAuth only adds ITSA scopes when `itsa.applies` is already true, so a
  connect-before-profile token can lack `read:self-assessment`.

### Approach
1. Persist `not_authorised` / `missing_scope` enrolment statuses.
2. Detect missing ITSA scopes on page load (same pattern as VAT).
3. Request ITSA scopes whenever the instructor is not a limited company.
4. Replace reconnect-for-permissions copy with NINO / MTD / sandbox
   guidance and real CTAs.
5. Hide the "haven't checked yet" card when a check has failed or
   scopes are missing.

### Tasks
- [x] Trace Connected vs enrolment vs HMRC error codes
- [x] Choose persist-status + clearer messaging (no new columns)

### Reflection
This is a product-state bug, not only a test-account limitation.
Sandbox test users often return `CLIENT_OR_AGENT_NOT_AUTHORISED` when
the NINO or MTD enrolment does not match; reconnecting the same login
does not fix that.

**Last Updated:** 2026-09-04.

## Phase 2: Implementation ✅

### Currently working on
Complete.

### Tasks
- [x] ItsaEnrolmentStatus + HmrcErrorCode copy
- [x] ResolveEnrolmentStatusAction maps auth/scope errors
- [x] scopesFor always requests ITSA scopes when they can apply
- [x] ItsaController passes hasItsaScope + environment
- [x] IndexPanel alerts and CTAs
- [x] Update database-schema.md

### Reflection
Persisting `not_authorised` removes the stale "haven't checked yet"
card. Reconnect is only offered as "different HMRC account" or missing
scopes — not as the fix for a matching-but-unenrolled test user.

I've updated database-schema.md to reflect the new enrolment statuses.

## Phase 3: Reflection ✅

Staff and instructors now see a persistent explanation when HMRC rejects
the enrolment check. Connected still means "OAuth token on file"; the
ITSA panel no longer pretends the check has not run.

### Tasks
- [x] Document decisions and leftover risks

### Reflection
Leftover: owner viewing an instructor ITSA tab still loads
`$request->user()` (the owner), not the instructor. Out of scope here.
Sandbox test-account setup remains an HMRC limitation — we now say so
instead of telling them to reconnect for permissions.
