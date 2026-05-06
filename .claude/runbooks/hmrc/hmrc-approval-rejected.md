# Runbook — HMRC production approval rejected

**Symptom:** HMRC's MTD application reviewer has rejected DRIVE's production application. Email typically lists which Minimum Functionality Standards (MFS) we missed or which docs aren't satisfactory.

**SLA:** This isn't time-pressure — production approval is a one-time milestone. Plan for one full reapply cycle (~1–2 weeks).

---

## 1. Don't panic — first-pass rejections are common

HMRC reviewers are thorough. Common rejection reasons fall into categories below. Log the rejection email content into the production-readiness ticket; quote the reviewer's exact wording.

---

## 2. Rejection reasons + what to fix

### MFS evidence insufficient (`5a` of the task plan)

Reviewer says: "we couldn't see the user submitting an amendment / triggering a calculation / etc."

**Fix:** Re-record the demo video. Demo must show, end-to-end with NO cuts:
- OAuth from a clean browser session
- Open obligations
- Submit a quarterly update — show the **correlation ID** in the success state
- Amend the same period — show the new correlation ID
- Trigger a tax calculation — show the breakdown
- Submit a Final Declaration — show the receipt
- (If applicable) Submit a 9-box VAT return — show the form bundle number
- Trigger an HMRC error path (use a deliberately-invalid sandbox payload) and show the user-facing error copy
- Show the audit-trail page with correlation IDs visible

If they ask for a screen-recorded "happy path with one error case", that's the minimum bar.

### Public docs miss MTD-specific commitments (`5b`)

Privacy policy must explicitly cover:
- HMRC fraud-prevention header collection (device ID cookie, screen size, timezone, UA — see `app/Actions/Hmrc/BuildFraudPreventionHeadersAction.php` for the full list)
- Storage of NINO / UTR / VRN / Companies House number
- 6-year retention of submission records
- That the instructor is responsible for the figures, not the software vendor

T&Cs must cover:
- Software is a conduit; instructor remains liable
- Digital-records attestation is binding
- No-amendment policy on VAT (corrections via future-period adjustment)

If reviewers reject, paste the missing commitments verbatim into the policy and re-publish at the same URL.

### Production credentials / subscription gap (`5c`)

Reviewer says: "we can't see API X subscribed in production."

**Fix:** Production subscriptions don't carry over from sandbox. Subscribe to all 14 APIs in the production developer hub (Phase 1 lists them). Re-trigger the application after.

### Fraud headers don't validate cleanly in production

Reviewer reports specific header errors from their validator.

**Fix:** Run our validator (`/hmrc/test/fraud-headers`) against production with a real connected user. The likely culprits:
- `Gov-Vendor-Public-IP` not set (env var `HMRC_VENDOR_PUBLIC_IP` must be set in production with the egress IP from our hosting provider)
- `Gov-Client-Public-IP` reading a private range — fix by configuring trusted proxies in `bootstrap/app.php` so `request()->ip()` returns the real client IP
- Missing `Gov-Client-Multi-Factor` for users who DID complete MFA — wire up the session marker per HMRC's spec

The header builder (`BuildFraudPreventionHeadersAction`) is the single place to adjust.

### Audit trail not durable

Reviewer says: "we couldn't see how a 6-year-old submission would be retrieved."

**Fix:** Demonstrate the audit tables remain intact. The append-only `hmrc_itsa_quarterly_update_revisions` records every attempt (success and failure) — show this in the demo. For VAT, the row in `hmrc_vat_returns` IS the record (no amendments).

If retention is the concern, add a **don't-delete** safeguard: a database constraint or a backup policy proven by ops. There is no auto-purge in DRIVE today, so the constraint is operational, not technical.

---

## 3. After rectifying

- Re-submit application with: cover note listing what changed, links to refreshed docs, a fresh demo video.
- If the reviewer named a specific person, address the cover note to them by name.
- Don't re-submit until you've actually made every change — partial fixes generate further rejection cycles.

---

## 4. If rejected twice for the same item

Escalate inside HMRC: ask for a 30-minute call with the reviewer through `sdsteam@hmrc.gov.uk`. Reviewers are usually willing to walk through their concerns directly when written feedback isn't landing.
