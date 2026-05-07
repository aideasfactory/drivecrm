# Runbook — Instructor says their submitted figures are wrong

**Symptom:** Instructor contacts support after a quarterly update / final declaration / VAT return saying the figures they filed don't match their records, or that they made a typo.

**SLA:** Same business day. **Always** treat as urgent if within the 7-day correction window of a final declaration.

---

## 1. First — what kind of submission?

The correction path is completely different per type.

### A. ITSA quarterly update — fully correctable

HMRC allows quarterly updates to be amended right up until the Final Declaration is submitted for that tax year.

- Direct the user to `/hmrc/itsa` → click the period → "Amend".
- The new submission writes a new revision row in `hmrc_itsa_quarterly_update_revisions` with `kind=amendment`, preserving the history.
- The parent row in `hmrc_itsa_quarterly_updates` is updated in place.
- HMRC issues a new `submission_id` and `correlation_id`; both are recorded.

If the period is already closed beyond what the UI allows, see [submission-failed.md](submission-failed.md) for a tinker-based replay.

### B. ITSA Final Declaration — VERY LIMITED

Once filed, a Final Declaration can only be amended within HMRC's 12-month-from-statutory-deadline window AND requires a new calculation. The flow:

1. The instructor opens `/hmrc/itsa/final-declaration/{taxYear}`.
2. They edit any of the 5 supplementary types (savings / dividends / reliefs / disclosures / personal details). Each type's PUT is upsert — overwrites the existing row in `hmrc_itsa_supplementary_data`.
3. They re-trigger calculation (a new `calculation_id` is minted).
4. They submit a new Final Declaration. **A new row is written in `hmrc_itsa_final_declarations` with the same `(user_id, tax_year)` — the unique constraint will block re-submission.**

**Important:** The unique constraint on `(user_id, tax_year)` means the existing row blocks a fresh submission. Two correct paths:

- Within HMRC's amendment window: HMRC accepts a re-submission via the same endpoint and the old declaration is superseded. We need to remove the unique constraint or treat it as upsert. **Today this case has no UI** — engineering escalation.
- Outside HMRC's amendment window: the only correction is via written contact with HMRC. Direct the instructor to [https://www.gov.uk/self-assessment-tax-returns/corrections](https://www.gov.uk/self-assessment-tax-returns/corrections).

### C. VAT 9-box return — NOT amendable in software

VAT returns have **no amendment endpoint** in MTD. Once filed, the only correction is a **future-period adjustment** under the £10k threshold (or a written disclosure to HMRC over that).

The instructor must adjust the offending box in their **next** VAT period:

- Under-declared output VAT (Box 1): add to next period's Box 1.
- Over-claimed input VAT (Box 4): subtract from next period's Box 4.
- Errors >£10k net or >1% of Box 6 turnover: must be reported to HMRC separately (form VAT652).

The UI surfaces this on the confirmation overlay before submission, but if they missed it, walk them through it.

---

## 2. Pull the audit row

```sql
-- ITSA quarterly: latest revision per period
SELECT r.kind, r.revision_number, r.submission_id, r.correlation_id, r.submitted_at
FROM hmrc_itsa_quarterly_update_revisions r
WHERE r.user_id = ?
ORDER BY r.submitted_at DESC
LIMIT 10;

-- ITSA final declaration
SELECT * FROM hmrc_itsa_final_declarations WHERE user_id = ? ORDER BY submitted_at DESC;

-- VAT
SELECT id, vrn, period_key, submitted_at, form_bundle_number, correlation_id
FROM hmrc_vat_returns
WHERE user_id = ?
ORDER BY submitted_at DESC;
```

Confirm the figures the instructor disputes match (or don't match) the `request_payload` JSON. If the user typed wrong figures, the payload reflects what they typed — that's a user-error correction. If the payload differs from what they remember entering, that's a bug — file it.

---

## 3. Attestation context

Every submission carries `digital_records_attested_at` + `digital_records_attested_by_user_id`. The instructor (or the staff member acting on their behalf) attested at submission time that figures came from digital records. Reference the timestamp when explaining why we can't simply "edit" the filing — the attestation is part of the legal record.

---

## 4. Communicate the correction path

- Quarterly: "We can amend that for you in DRIVE — please open the period and click Amend."
- Final declaration: "Final Declarations can only be amended in limited circumstances — let me check eligibility and come back to you." Loop in engineering for the unique-constraint case.
- VAT: "VAT returns cannot be amended in software once filed — corrections go in your next VAT period. Here's how:" then walk them through the future-period adjustment.

---

## 5. After resolution

- Note the correction path used (amend / future-period / written disclosure to HMRC).
- If the user's confusion came from UI ambiguity (e.g. they didn't realise VAT couldn't be amended), file a UI-copy improvement.
- For final-declaration amendments outside the window, add a knowledge-base article so the next user gets the answer instantly.
