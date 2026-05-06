# Runbook — HMRC submission failed

**Symptom:** Instructor reports a quarterly update, final declaration, or VAT 9-box submission errored. Or ops sees a spike in HMRC 5xx responses.

**SLA:** Best-effort same business day. Tax-deadline windows (week before quarterly due dates) escalate to 2h.

---

## 1. Identify the submission

Ask the instructor for any of: the period (e.g. `2025-26 Q2`), the time of attempt, or any reference number on screen. Then locate the audit row.

| Submission type | Table | Audit-trail table |
|---|---|---|
| ITSA quarterly update | `hmrc_itsa_quarterly_updates` | `hmrc_itsa_quarterly_update_revisions` (append-only) |
| ITSA final declaration | `hmrc_itsa_final_declarations` | row IS the audit record |
| VAT 9-box | `hmrc_vat_returns` | row IS the audit record (no amendment endpoint) |

Find the row:

```sql
-- Quarterly: every attempt (success and failure) writes a revision
SELECT id, kind, submission_id, correlation_id, submitted_at, response_payload
FROM hmrc_itsa_quarterly_update_revisions
WHERE user_id = ?
ORDER BY submitted_at DESC
LIMIT 20;

-- VAT: in-place row, single audit record per period
SELECT id, vrn, period_key, submitted_at, correlation_id, response_payload
FROM hmrc_vat_returns
WHERE user_id = ?
ORDER BY submitted_at DESC;
```

The `response_payload` JSON contains HMRC's full error envelope when the call failed.

---

## 2. Classify the failure

Open `response_payload` and look at HMRC's `code`. The catalogue lives in `app/Enums/HmrcErrorCode.php` — anything not enumerated there falls back to `default()`. The user-facing copy comes from `HmrcErrorCode::userMessage()`.

Common categories:

- **`RULE_NOT_SIGNED_UP_TO_MTD`, `MATCHING_RESOURCE_NOT_FOUND`** — the instructor isn't enrolled or the income source isn't registered. Check `instructors.mtd_itsa_status`. If it's stale, run:
  ```bash
  php artisan tinker
  >>> app(\App\Actions\Hmrc\Itsa\ResolveEnrolmentStatusAction::class)($user, $fraudContext);
  ```
  See [stuck-reconnecting.md](stuck-reconnecting.md) if the underlying issue is OAuth.

- **`INVALID_PAYLOAD`, `RULE_*` (validation)** — figures don't satisfy HMRC's rules. The instructor needs to amend their input. The `errors[]` array in `response_payload` lists the offending fields. Walk them through the corrections in the UI (or for VAT, a future-period adjustment).

- **`BUSINESS_ERROR_*`, `SERVER_ERROR`, HTTP 5xx** — HMRC outage. Check [HMRC status page](https://api-platform-status.production.tax.service.gov.uk/). If HMRC is degraded, the instructor should retry later. Open an incident if widespread.

- **`MissingFraudFingerprintException`** — fingerprint stale (>30 min). The user should refresh the page; the composable re-captures automatically. If it recurs, check `hmrc_client_fingerprints.captured_at` for the user's token.

---

## 3. Confirm correlation ID is captured

Every HMRC call returns an `X-CorrelationId` header. We persist it on the audit row (`correlation_id`). If it's null, the failure happened before HMRC saw the request — look at Laravel's log (`storage/logs/laravel.log`) for the upstream exception (DB error, missing token, etc.).

If it's present, you can quote it to HMRC dev support: `sdsteam@hmrc.gov.uk`.

---

## 4. Replay if needed

For ITSA, amend through the existing UI (`/hmrc/itsa/{businessId}/period/{periodKey}` → Amend). The action writes a new revision row.

For VAT, **there is no amendment endpoint** — corrections must be made in a future-period adjustment. Direct the instructor to HMRC's guidance and document the contact in your support ticket.

For replaying a quarterly via tinker (only when UI fails for some reason):

```bash
php artisan tinker
>>> $user = \App\Models\User::find(...);
>>> $business = \App\Models\HmrcItsaBusiness::find(...);
>>> $payload = [...];        // the same shape SubmitQuarterlyUpdateRequest produces
>>> $fraudContext = ['ip' => null, 'user_agent' => null];
>>> app(\App\Services\HmrcItsaService::class)->submitQuarterly($user, $business, $payload, request());
```

---

## 5. After resolution

- Add a note to the support ticket with: HMRC `code`, `correlation_id`, action taken.
- If a previously-unmapped `code` appeared, add a case to `HmrcErrorCode` so the next user sees friendly copy.
- If the failure suggests our payload is wrong (not the user's data), open a bug — the source of variance is `BuildQuarterlyPayloadAction`, `BuildSupplementaryPayloadAction`, or `BuildVatReturnPayloadAction`.
