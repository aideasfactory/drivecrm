# HMRC MTD ITSA — API surface DRIVE talks to

Internal reference assembled from HMRC's MTD ITSA documentation and the project task plan (Phase 3) on 2026-04-28.

> **⚠️ Important caveat:** HMRC's OpenAPI spec pages (`/oas/page`) render client-side via JavaScript and cannot be fetched as static markup. The endpoint shapes below come from the task plan + HMRC's published end-to-end service guide. If a sandbox round-trip surfaces a mismatch (e.g. a renamed field), update this doc and the affected migration/action — don't paper over it.

## OAuth scopes

- `read:self-assessment` — list businesses, obligations, retrieve submissions
- `write:self-assessment` — submit / amend quarterly updates, trigger calculations

DRIVE requests these scopes when an instructor's tax profile (`business_type` is `sole_trader` or `partnership`).
For VAT-registered instructors, ITSA scopes are still requested if their business type qualifies — scopes are union, never narrowed.

## Authorisation contract — what OAuth gets you ≠ MTD enrolment

A successful OAuth flow does **not** mean the instructor is signed up for MTD ITSA at HMRC. They must separately:

1. Be registered for Self Assessment (have a UTR).
2. Have submitted at least one SA return in the past two years (typical HMRC eligibility condition).
3. Have signed up each income source (e.g. self-employment business) for MTD via gov.uk.

If they haven't, calls to Business Details return either an empty `[]` list (no businesses signed up) or `404` with `RULE_NOT_SIGNED_UP_TO_MTD`. The Phase 3a.5 enrolment-status state machine surfaces this in the UI before any submission UI is shown.

## Endpoints used in Phase 3

### Business Details (MTD) v2.0

- `GET /individuals/business/details/{nino}/list`
  - Accept: `application/vnd.hmrc.2.0+json`
  - Scope: `read:self-assessment`
  - Response (per business): `businessId`, `typeOfBusiness` (`self-employment` | `uk-property` | `foreign-property`), `tradingName`, `accountingType` (`CASH` | `ACCRUALS`), `commencementDate`, `cessationDate`, `latencyDetails`
  - Empty list ⇒ user has no MTD-signed-up businesses
- `GET /individuals/business/details/{nino}/{businessId}`
  - Same Accept/scope; returns the single business detail.

**Errors signalling enrolment problems:**
- `RULE_NOT_SIGNED_UP_TO_MTD` (404) — user not enrolled
- `MATCHING_RESOURCE_NOT_FOUND` (404) — businessId unknown
- `INVALID_NINO` (400) — NINO format wrong (we should never hit this if Phase 1.5 validation works)

### Obligations (MTD) v3.0

- `GET /obligations/details/{nino}/income-and-expenditure`
  - Accept: `application/vnd.hmrc.3.0+json`
  - Scope: `read:self-assessment`
  - Query params: optional `fromDate`, `toDate` (YYYY-MM-DD), optional `status` (`Open` | `Fulfilled`), optional `typeOfBusiness`, optional `businessId`
  - Response (per obligation): `businessId`, `typeOfBusiness`, `obligations[]` each with `periodStartDate`, `periodEndDate`, `dueDate`, `receivedDate` (when fulfilled), `status` (`Open` | `Fulfilled`), `periodKey`

### Self Employment Business (MTD) v5.0

- `POST /individuals/business/self-employment/{nino}/{businessId}/period`
  - Accept: `application/vnd.hmrc.5.0+json`
  - Scope: `write:self-assessment`
  - Request body shape (decimal pounds with 2dp; pence × 100 in DB):
    ```json
    {
      "periodDates": {
        "periodStartDate": "2025-04-06",
        "periodEndDate": "2025-07-05"
      },
      "periodIncome": {
        "turnover": 12345.67,
        "other": 0.00
      },
      "periodExpenses": {
        // EITHER consolidated:
        "consolidatedExpenses": 1234.56,
        // OR itemised — every field optional, decimal pounds:
        "costOfGoods": 0,
        "paymentsToSubcontractors": 0,
        "wagesAndStaffCosts": 0,
        "carVanTravelExpenses": 0,
        "premisesRunningCosts": 0,
        "maintenanceCosts": 0,
        "adminCosts": 0,
        "businessEntertainmentCosts": 0,
        "advertisingCosts": 0,
        "interestOnBankOtherLoans": 0,
        "financeCharges": 0,
        "irrecoverableDebts": 0,
        "professionalFees": 0,
        "depreciation": 0,
        "otherExpenses": 0
      }
    }
    ```
  - Mutually exclusive: send `consolidatedExpenses` OR the itemised set, never both.
  - Success: `200 OK`, body contains `submissionId`. HMRC sends `X-CorrelationId` response header (capture for audit trail).
- `PUT /individuals/business/self-employment/{nino}/{businessId}/period/{periodId}`
  - Same Accept/scope; body shape mirrors POST (you're replacing the figures for that period).
  - Allowed only **before** the Final Declaration for the tax year.
- `GET /individuals/business/self-employment/{nino}/{businessId}/period/{periodId}`
  - Read-back of a previously-submitted period.

## Money handling (cross-cutting)

Source of truth: `app/Support/HmrcMoney.php` (Phase 1).
- Browser/UI: pounds `"1234.56"` or `1234.56`
- Database: `bigInteger` pence — `123456`
- HMRC payload: decimal pounds with 2dp — `1234.56` as a JSON number

`HmrcMoney::toHmrcPayload()` enforces 2dp rounding and forbids negatives where HMRC does (most income/expense fields are non-negative). Apply per-field at the action's payload-build step.

## Error envelope

Standard MTD error response on failure:

```json
{
  "code": "RULE_DUPLICATE_SUBMISSION",
  "message": "...",
  "errors": [
    {"code": "FORMAT_VALUE", "path": "/periodIncome/turnover"}
  ]
}
```

Surface via `HmrcApiException`. Specific codes mapped to user-friendly copy in `HmrcErrorCode` enum.
Retryable codes are rare for ITSA — rule violations require user action, not a retry.

## Reminder cadence

The Phase 3f cron (`SyncHmrcItsaObligations`) refreshes obligations daily and fires `ItsaObligationDueSoon` at **30 / 14 / 7 / 1** days before due date. Idempotent via `last_reminder_sent_at` on `hmrc_itsa_obligations`.
