# Runbook — HMRC connection stuck / reconnect loop

**Symptom:** Instructor reports they keep getting "your HMRC connection needs renewing" prompts, the OAuth dance loops back to the connect page, or a submission fails with `HmrcReconnectRequiredException`.

**SLA:** Same business day; before quarterly deadlines escalate to 2h.

---

## 1. Snapshot current token state

```sql
SELECT
    id,
    scopes,
    expires_at,
    refresh_expires_at,
    last_refreshed_at,
    last_expiry_warning_at,
    connected_at
FROM hmrc_tokens
WHERE user_id = ?;
```

Then look at recent refresh attempts:

```sql
SELECT outcome, error_code, attempted_at
FROM hmrc_token_refresh_logs
WHERE user_id = ?
ORDER BY attempted_at DESC
LIMIT 30;
```

Map outcomes (`app/Enums/HmrcTokenRefreshOutcome.php`):

| `outcome` | Meaning | Action |
|---|---|---|
| `success` | Refresh worked | Confirm the user's token row was updated; if not, talk to engineering |
| `failure_invalid_grant` | Refresh token is dead (revoked, expired, or — most commonly — already consumed by a concurrent refresh) | User must reconnect (see below). One-off occurrences during deploys/restarts are usually benign. |
| `failure_network` | Could not reach HMRC | Check HMRC status; if widespread escalate to incident |
| `failure_other` | Unexpected | Inspect Laravel log around `attempted_at` |

---

## 2. Common scenarios

### A. Refresh token expired (after 18 months)

HMRC refresh tokens are valid for 18 months. The `MonitorHmrcTokenExpiry` cron warns at T-30 and T-7 days, but if the user ignored both notifications they'll hit the expiry hard.

**Diagnosis:** `refresh_expires_at` is in the past, OR `failure_invalid_grant` consistently in the logs.

**Fix:** Direct the user to `/hmrc` → "Connect to HMRC". They will re-authenticate and the new token replaces the row in place.

### B. User revoked at HMRC

The user disconnected DRIVE from inside their HMRC account. We get `failure_invalid_grant` immediately on every refresh attempt with no preceding warnings.

**Diagnosis:** Refresh log shows a sudden cliff — successes followed by repeated `failure_invalid_grant`. `last_expiry_warning_at` may be null.

**Fix:** Same as above — they reconnect via `/hmrc`.

### C. Scope drift (VAT-eligible instructor with ITSA-only token)

A previously-ITSA instructor turned on VAT in the tax profile, but their existing token only carries `read:self-assessment write:self-assessment`. Submission to VAT routes will work in the UI gating layer (banner shown), but if scope-required calls happen the user sees `HmrcReconnectRequiredException`.

**Diagnosis:** `instructors.vat_registered = true`, `hmrc_tokens.scopes` array does not include `read:vat`/`write:vat`.

**Fix:** The `/hmrc/vat` Index page already shows a "VAT permissions not granted" banner with a Reconnect CTA. Reconnecting via `/hmrc/connect` requests the **union** of currently-applicable scopes (`HmrcService::scopesFor`) — never narrower. After reconnect, both ITSA and VAT scopes are present.

### D. Concurrent-refresh race (rare)

Two simultaneous requests both try to refresh. One wins, the other gets `invalid_grant` because HMRC invalidates the previous refresh token immediately. The Phase 1 `RefreshAccessTokenAction` uses `DB::transaction` + `lockForUpdate()` to serialise refreshes, so this should not happen — but if you see paired `success` + `failure_invalid_grant` log entries within milliseconds, that's the signature.

**Fix:** The atomic-refresh code is the fix. If you see this pattern, file a bug — there's a code path bypassing the lock.

### E. Refresh succeeds but user still sees the prompt

**Diagnosis:** Frontend cached the "not connected" prop. The user is reading stale Inertia state.

**Fix:** Ask them to hard-refresh `/hmrc`. If it persists, check `connectionStatusFor(User)` in `HmrcService` — that is the single source of truth for the prop.

---

## 3. Manual reconnect

If the user can't or won't reconnect through the UI, you can clear their token row and walk them through:

```bash
php artisan tinker
>>> $user = \App\Models\User::where('email', '...')->first();
>>> $user->hmrcToken?->delete();   // forces a fresh OAuth dance
```

Note: this **does not** clear the device identifier (`hmrc_device_identifiers`) or the fingerprint (`hmrc_client_fingerprints`). That's by design — HMRC fraud-prevention requires the device ID to persist across token churn.

---

## 4. Health monitoring

Run hourly via cron (`hmrc:check-refresh-health`). Manually:

```bash
php artisan hmrc:check-refresh-health --hours=24 --threshold=1.0
```

Exits non-zero (and logs `HMRC refresh failure rate exceeded threshold` at warning level) if more than 1% of refreshes failed in the last 24h. Pipe alerts off the warning.

---

## 5. After resolution

- Note the scenario (A–E above) on the ticket so the next responder sees patterns.
- If E recurs, the engineering team owns the bug.
