# HMRC MTD ITSA — Final Declaration API surface

> **⛔ SUPERSEDED — 2026-04-30.** Final Declaration has been descoped from
> the Drive CRM product. End-of-year journey is now: instructor uses
> Drive CRM for quarterly updates → tax-year archive download (Phase 9) →
> hand archive to a qualified accountant → accountant files the Final
> Declaration via their own tools. The five Final-Declaration support APIs
> (Individual Details, Reliefs, Disclosures, Savings Income, Dividends
> Income) are excluded from the production HMRC API subscription. This
> document remains as historical reference for the working Phase 3.5
> sandbox code — **do not action it**. See `.claude/tasks/current-task.md`
> *Session handoff — 2026-04-30* for the full decision rationale.

Internal reference assembled from HMRC's MTD ITSA documentation and the Phase 3.5 task plan on 2026-04-28.

> **⚠️ Important caveat:** HMRC's OpenAPI spec pages (`/oas/page`) render client-side via JavaScript and cannot be fetched as static markup. The endpoint shapes below come from HMRC's published end-to-end service guide + the Phase 3.5 task plan. Sandbox round-trips (3.5h USER ACTION) are the source of truth — if a field name or envelope differs, update this doc and the affected enum/migration/action together. Don't paper over a mismatch.

## OAuth scopes

Same scopes as quarterly updates:

- `read:self-assessment` — list calculations, retrieve calculation breakdowns, retrieve supplementary data, account balance
- `write:self-assessment` — submit supplementary data, trigger calculations, submit final declaration

No new scope additions for Phase 3.5 — the existing token from Phase 3 covers everything.

## Final Declaration concept

The legacy "Self Assessment tax return" for ITSA-mandated taxpayers is replaced by the **Final Declaration**. It is the once-a-year event after all four quarterly updates have been submitted. Sequence:

1. Self-employment quarterly updates (Phase 3) — already submitted for Q1–Q4.
2. Supplementary data — savings, dividends, reliefs, disclosures, individual details — submitted via dedicated endpoints.
3. Trigger a **calculation** (`finalDeclaration` type). HMRC processes the figures asynchronously.
4. Poll until the calculation status is `processed`.
5. Review the calculated liability in the UI.
6. Submit the **Final Declaration**, which crystallises the tax year. After this, quarterly updates are immutable for that year.

## Tax year format

HMRC uses the dash form: `2025-26` (April 2025–April 2026). The frontend sends this verbatim; persistence stores the same string.

## Calculation flow (Individual Calculations MTD v8.0)

### Trigger

- `POST /individuals/calculations/{nino}/self-assessment`
  - Accept: `application/vnd.hmrc.8.0+json`
  - Scope: `write:self-assessment`
  - Query params: `taxYear` (e.g. `2025-26`), `finalDeclaration` (boolean, true to mark intent for final declaration)
  - Body: empty
  - Response (`202 Accepted`): `{ calculationId }`

### Retrieve

- `GET /individuals/calculations/{nino}/self-assessment/{calculationId}`
  - Accept: `application/vnd.hmrc.8.0+json`
  - Scope: `read:self-assessment`
  - Response: full calculation detail. Top-level `metadata.calculationOutcome` is one of:
    - `IS_PROCESSED` — calc is ready, `liabilityAndCalculation` block populated
    - `IS_NOT_PROCESSED` — still computing; poll
    - `ERROR` — terminal failure; `errors[]` populated
  - The summary block we expose to the user in Step 5 (Calculation Review) is `liabilityAndCalculation.taxCalculation.totalIncomeTaxAndNicsDue` plus the breakdown rows.

### Submit Final Declaration

- `POST /individuals/calculations/{nino}/self-assessment/{calculationId}/final-declaration`
  - Accept: `application/vnd.hmrc.8.0+json`
  - Scope: `write:self-assessment`
  - Body: empty (the calculationId is the assertion of which figures the user is finalising)
  - Response: `204 No Content` on success; `X-CorrelationId` response header captures audit reference.

### Polling strategy

DRIVE polls the retrieve endpoint at 1.5s, 3s, 6s, 12s (capped at 30s with jitter), up to 60s total. If still `IS_NOT_PROCESSED` past the cap, the UI surfaces "still working" with a manual Retry button rather than blocking the user. State is persisted on `hmrc_itsa_calculations` so a reload picks up where polling left off.

## Supplementary data endpoints

Each supplementary type is a single-row submission per `(user, taxYear, type)`. HMRC accepts repeat submissions — they overwrite the previous figures for that year. We persist the latest payload + submission id.

| Type | Verb | Path (under `/individuals/`) | Version | Notes |
|---|---|---|---|---|
| `reliefs` | PUT | `reliefs/{nino}/{taxYear}` | `3.0` | Pension contributions, charitable giving |
| `disclosures` | PUT | `disclosures/{nino}/{taxYear}` | `2.0` | Marriage Allowance transfer, Class 2 NICs, taxAvoidance |
| `savings` | PUT | `savings-income/{nino}/{taxYear}` | `2.0` | UK + foreign savings/interest |
| `dividends` | PUT | `dividends-income/{nino}/{taxYear}` | `2.0` | UK dividends |
| `individual_details` | PUT | `self-assessment/individuals/details/{nino}/{taxYear}` | `2.0` | Name, address, marital status confirmation |

Retrieval uses the same path with `GET`. All require fraud headers.

### Common envelope shape (illustrative)

```json
{
  "submittedOn": "2026-04-28T11:32:01.123Z",
  // type-specific keys, e.g. for reliefs:
  "pensionReliefs": {
    "regularPensionContributions": 2400.00,
    "oneOffPensionContributionsPaid": 0.00
  },
  "charitableGivingTaxRelief": {
    "nonUkCharities": { "totalAmount": 0.00 }
  }
}
```

The Phase 3.5 v1 form scope captures the most common driving-instructor cases (pension contributions, charity, Marriage Allowance, savings interest, dividends). Less common fields are deferred — the JSON column on `hmrc_itsa_supplementary_data.payload` keeps the door open without re-migrating.

## Self Assessment Accounts (MTD) v4.0

- `GET /accounts/self-assessment/{nino}/balance-and-transactions`
  - Accept: `application/vnd.hmrc.4.0+json`
  - Scope: `read:self-assessment`
  - Surfaces: outstanding amount, payment status, list of transactions/charges. Used on the Final Declaration index page to show "what you currently owe HMRC" alongside the calculated liability.

## Money handling

Same as Phase 3:
- UI: pounds with up to 2dp
- DB: `bigInteger` pence (column suffix `_pence` on the supplementary table the *summary* totals; full payload remains JSON-shaped in pounds)
- HMRC payload: decimal pounds with 2dp

`HmrcMoney::toHmrcPayload()` (Phase 1) is the single conversion point.

## Error envelope

Standard MTD shape; surfaced via `HmrcApiException` with `HmrcErrorCode` mapping. Calculation-specific codes worth flagging:

- `RULE_NO_INCOME_SUBMISSIONS_EXIST` — Final Declaration triggered but no quarterly data: instructor missed Phase 3 step
- `RULE_FINAL_DECLARATION_RECEIVED` — already submitted; UI shows receipt instead of the submit button
- `RULE_INCOME_SOURCES_INVALID` — businesses signed up for MTD but quarterly updates incomplete

These are not retryable. They drive specific UI paths (link back to Phase 3 to fix the underlying data).

## Audit storage

`hmrc_itsa_final_declarations` is the immutable record. One row per `(user, taxYear)`, written only on successful `POST /final-declaration`. Failed attempts are NOT written here — they are inferred from the calculation's `error_payload` and the linked attempts log isn't required because Final Declaration is empty-body POST. The retention window is 6 years per HMRC's MTD record-keeping rules.

## What's NOT in v1 Phase 3.5

Deferred to a later iteration:
- Capital gains, foreign income, employment income, property income (most full-time instructors don't have these)
- Tax-liability adjustments
- Loss claims
- CIS deductions
- Open Banking pre-population for savings interest

The schema (JSON `payload` column on the supplementary table) accommodates additions without migrations; the FormRequest + UI are what would need to grow.
