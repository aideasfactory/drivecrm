# HMRC MTD VAT — API surface DRIVE talks to

Internal reference assembled from HMRC's MTD VAT documentation and the Phase 4 task plan on 2026-04-28.

> **⚠️ Important caveat:** HMRC's OpenAPI spec pages (`/api/service/vat-api/1.0/oas/page`) render client-side via JavaScript and cannot be fetched as static markup. The endpoint shapes below come from the task plan + HMRC's published end-to-end VAT service guide. Sandbox testing is the truth check — if a field name or envelope mismatches, update this doc and the affected migration/action.

## OAuth scopes

- `read:vat` — list obligations, retrieve returns/liabilities/payments
- `write:vat` — submit a 9-box VAT return

DRIVE requests these scopes when an instructor's tax profile has `vat_registered = true`. Scopes are unioned with ITSA scopes (where applicable) — `HmrcService::scopesFor()` already merges existing token scopes so re-auth for VAT never narrows ITSA scopes already granted.

## Scope union policy (v1)

- Authorisation always requests the **union** of all currently-applicable scopes for the instructor (Hello + ITSA where applicable + VAT iff `vat_registered=true`).
- HMRC issues a fresh token bound to the new scope set. The existing `HmrcToken` row is **upserted** on a successful exchange (`ExchangeAuthorizationCodeAction`) — there is a brief disconnect window during the browser redirect to HMRC and back. UI copy on the VAT card warns the instructor about this.
- `BuildAuthorizationUrlAction` reads currently-granted scopes from `HmrcToken::scopes` and requests the union plus the additions; **never narrower** than what's already granted.
- Test: VAT-registered instructor with existing ITSA token re-auths for VAT → resulting token has both `read:self-assessment write:self-assessment` and `read:vat write:vat`.

## Authorisation contract — what OAuth gets you ≠ MTD VAT enrolment

A successful OAuth flow does **not** mean the instructor is signed up for MTD VAT at HMRC. They must separately have signed up via gov.uk for MTD VAT (typically automatic for VAT registrations from April 2022 onwards; legacy registrations must opt in).

If the user has not signed up, calls to the obligations endpoint return either an empty list or a 403/404 with a code such as `MTD_NOT_SIGNED_UP` / `RULE_NOT_SIGNED_UP_TO_MTD`. v1 surfaces an explanatory state in the UI: "We couldn't find a MTD VAT registration — sign up at gov.uk and refresh."

(Unlike ITSA there is no separate enrolment status state machine in v1 — VAT is binary. An instructor either gets results from the obligations endpoint or doesn't.)

## Endpoints used in Phase 4

### Obligations

- `GET /organisations/vat/{vrn}/obligations`
  - Accept: `application/vnd.hmrc.1.0+json`
  - Scope: `read:vat`
  - Query params: optional `from` / `to` (YYYY-MM-DD), optional `status` (`O` for Open, `F` for Fulfilled)
  - Response: `obligations[]` each with `start`, `end`, `due`, `status`, `periodKey` (string used in submit/retrieve), `received` (when fulfilled)

### Retrieve a return

- `GET /organisations/vat/{vrn}/returns/{periodKey}`
  - Accept: `application/vnd.hmrc.1.0+json`
  - Scope: `read:vat`
  - Response: full 9-box body plus `processingDate` and metadata.
  - **Note on URL-encoding:** `periodKey` may contain a `#` symbol in some legacy formats. Always pass via Laravel's `Http` query helpers; never concatenate raw.

### Submit a return

- `POST /organisations/vat/{vrn}/returns`
  - Accept: `application/vnd.hmrc.1.0+json`
  - Scope: `write:vat`
  - Request body shape (decimal pounds with 2dp; pence × 100 in DB):
    ```json
    {
      "periodKey": "18A1",
      "vatDueSales": 1234.56,
      "vatDueAcquisitions": 0.00,
      "totalVatDue": 1234.56,
      "vatReclaimedCurrPeriod": 200.00,
      "netVatDue": 1034.56,
      "totalValueSalesExVAT": 6172,
      "totalValuePurchasesExVAT": 1000,
      "totalValueGoodsSuppliedExVAT": 0,
      "totalAcquisitionsExVAT": 0,
      "finalised": true
    }
    ```
  - **Notes on rounding/sign:**
    - Boxes 1, 2, 3, 4, 5 (the VAT amount fields) are decimal pounds with 2dp. `netVatDue` (Box 5) MUST be non-negative — HMRC computes absolute difference of Box 3 and Box 4.
    - Boxes 6, 7, 8, 9 (the value fields) are **whole pounds** — no decimals, no negatives accepted in v1 (driving instructor scenario).
    - `finalised: true` is the legal declaration that the return is final. Always set true for v1; "save as draft" is not in scope.
  - Success: `200 OK` with `processingDate`, `formBundleNumber`, `paymentIndicator`, `chargeRefNumber` (where applicable). HMRC sends `X-CorrelationId` response header (capture for audit).
  - **Immutability:** Once submitted, a VAT return CANNOT be amended. Corrections happen via a future-period adjustment.

### Liabilities

- `GET /organisations/vat/{vrn}/liabilities`
  - Accept: `application/vnd.hmrc.1.0+json`
  - Scope: `read:vat`
  - Query params: required `from` / `to` (YYYY-MM-DD)
  - Used by the VAT history page to show outstanding charges.

### Payments

- `GET /organisations/vat/{vrn}/payments`
  - Accept: `application/vnd.hmrc.1.0+json`
  - Scope: `read:vat`
  - Query params: required `from` / `to`
  - Companion of liabilities — shows what's been paid against past returns.

## Money handling (cross-cutting)

Source of truth: `app/Support/HmrcMoney.php` (Phase 1).

- Browser/UI: pounds `"1234.56"` or `1234.56`
- Database: `bigInteger` pence — `123456`
- HMRC payload: decimal pounds with 2dp for VAT amount boxes; whole pounds for value boxes. Apply per-field at the action's payload-build step.

## Error envelope

Standard MTD error response on failure:

```json
{
  "code": "INVALID_REQUEST",
  "message": "...",
  "errors": [
    {"code": "INVALID_NUMERIC_VALUE", "path": "/totalVatDue"}
  ]
}
```

Surface via `HmrcApiException`. Specific codes mapped to user-friendly copy in `HmrcErrorCode` enum.

## Reminder cadence

VAT obligations are folded into the existing `SyncHmrcItsaObligations` cron (Phase 3f). For VAT-registered instructors, the cron also fetches VAT obligations and persists them to `hmrc_vat_obligations`; the same `30 / 14 / 7 / 1` reminder thresholds apply via a sibling `VatObligationDueSoon` notification.

## Immutability note (no amendments)

VAT submissions are final on HMRC's side — there is no `PUT` amendment endpoint. The DRIVE submission row is the audit record. Corrections are made by adjusting the next period's figures, not by editing the row. UI copy on the submission confirmation must emphasise this irreversibility.
