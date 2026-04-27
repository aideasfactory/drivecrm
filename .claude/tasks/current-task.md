# Task: HMRC Making Tax Digital (MTD) Integration

## Overview

Allow instructors to connect their HMRC account via OAuth 2.0 and file their **Income Tax (MTD ITSA)** through the CRM, with **VAT** as an optional secondary feature for the small minority who are VAT-registered.

**Audience reality (confirmed with client):** ~99% of instructors are sole traders. Income Tax is the priority — VAT is a toggle for the few who are also VAT-registered. ITSA mandate dates: 6 Apr 2026 (>£50k), 6 Apr 2027 (>£30k), 6 Apr 2028 (>£20k).

**Cost:** HMRC charges nothing for API access or submissions. Cost to DRIVE is dev time, audit storage (6-year retention requirement), and the existing infra absorbing a small spike 4× per year at quarter-ends.

**HMRC Sandbox Application (Just DRIVE):**
- App ID: `252c25fe-9670-4e0a-a547-b587c488bdaf`
- Client ID: `wcl7WJn10t8AXupYzYtPU1vgKZXd` (in `.env` as `HMRC_CLIENT_ID`)
- Client Secret: in `.env` as `HMRC_CLIENT_SECRET` — never commit
- Redirect URI registered: `https://drivecrm.test/hmrc/oauth/callback`
- Grant length: 18 months (refresh token lifetime)
- **Subscribed APIs (14):**
  - Foundation: Hello World 1.0, Test Fraud Prevention Headers 1.0
  - ITSA core: Business Details (MTD) 2.0, Self Employment Business (MTD) 5.0, Obligations (MTD) 3.0, Individual Calculations (MTD) 8.0, Self Assessment Accounts (MTD) 4.0, Self Assessment Test Support (MTD) 1.0
  - ITSA Final Declaration support: Self Assessment Individual Details (MTD) 2.0, Individuals Reliefs (MTD) 3.0, Individuals Disclosures (MTD) 2.0, Individuals Savings Income (MTD) 2.0, Individuals Dividends Income (MTD) 2.0
  - VAT: VAT (MTD) 1.0

**Audience:** Instructor-facing web feature (not mobile API). Uses Inertia/Vue + ShadCN Sheet patterns. Browser-redirect OAuth flow — no need for `/api/v1/*` endpoints in v1. Confirmed by user: HMRC functionality is purely web-only inside the admin area.

**Connection persistence is a first-class concern.** User requirement: *"once they are oauthed they need to stay oauthed."* This means:
- All tax identifiers (VRN, UTR, NINO, business type) stored on `instructors` table in Phase 1.5 so they survive every session
- Refresh-token flow MUST be robust and silent — instructor should never see "please reconnect" while the 18-month refresh window is alive
- Atomic refresh under `lockForUpdate()` is mandatory (the new refresh token replaces the old one immediately on each refresh — losing it strands the user)
- Re-connect prompt only when refresh token is expired/revoked or scopes change
- Proactive monitoring: every refresh attempt is logged with outcome; ops alerted on elevated failure rate; instructor sees in-app warning at T-30 and T-7 days before `refresh_expires_at`. The contract is *"we'll warn you well before reconnect is needed,"* never *"you'll never reconnect."*

**OAuth scopes by phase:**
- Phase 1 (Hello World): `hello`
- Phase 3 (ITSA): `read:self-assessment write:self-assessment`
- Phase 4 (VAT): adds `read:vat write:vat` — instructor with VAT enabled re-authorises once to grant the additional scope

**Key environment endpoints (sandbox):**
- Authorize: `https://test-www.tax.service.gov.uk/oauth/authorize`
- Token: `https://test-api.service.hmrc.gov.uk/oauth/token`
- API base: `https://test-api.service.hmrc.gov.uk`

**Token semantics:**
- Access token: 4 hours
- Refresh token: 18 months — but **invalidated immediately on refresh**, so refresh must be atomic (DB-level lock per user)
- Auth code: 10 minutes
- PKCE (`S256`) used for defence in depth

---

## ⚠️ Multi-phase task — manual approval between phases

The user wants to walk through each phase one by one. **Do NOT auto-continue between phases.** After completing each phase: mark it complete, fill the reflection, update timestamp, then **STOP and wait for the user to say "continue"** before starting the next phase. The `.phase_done` sentinel is only written when ALL SEVEN phases are complete (1, 1.5, 2, 3, 3.5, 4, 5).

## Phase summary

| # | Phase | Deliverable |
|---|---|---|
| 1 | OAuth + Hello World | Prove auth round-trip end-to-end |
| 1.5 | Tax profile | Business type, identifiers, applicability matrix |
| 2 | Fraud prevention headers | Legally required, validated against HMRC's checker |
| **3** | **ITSA Quarterly Updates** | **Submit quarterly self-employment income/expenses** |
| **3.5** | **ITSA Final Declaration** | **Annual SA-equivalent submission with calculation review** |
| **4** | **VAT (optional)** | **9-box submission for VAT-registered instructors** |
| **5** | **Production readiness** | **HMRC production approval, MFS evidence, monitoring, support runbooks** |

---

## Phase 1: OAuth foundation + Hello World ✅ Complete

**Goal:** Prove the OAuth round-trip end-to-end against HMRC sandbox by successfully calling the user-restricted Hello World endpoint and showing the response on the connection page. No fraud headers yet (Hello World doesn't require them).

### 1a. Config

- [x] Create `config/hmrc.php` with:
  - `environment` (env: `HMRC_ENVIRONMENT`, default `sandbox`)
  - `client_id`, `client_secret`
  - `redirect_uri` (default `<APP_URL>/hmrc/oauth/callback`)
  - `urls.auth` and `urls.api` resolved per environment (sandbox vs production)
  - `scopes.hello_world` = `'hello'`
- [x] Add `HMRC_ENVIRONMENT`, `HMRC_CLIENT_ID`, `HMRC_CLIENT_SECRET`, `HMRC_REDIRECT_URI` to `.env.example` (blank values)
- [ ] **USER ACTION:** Add the real sandbox `HMRC_CLIENT_ID`/`HMRC_CLIENT_SECRET` to local `.env` (not committed by Claude)

### 1b. Database

Two new tables, both owned by user. Token storage is encrypted at rest (Laravel `encrypted` cast) on top of the application key.

- [x] Migration: `create_hmrc_oauth_states_table`
  - `id`, `user_id` (fk, cascade), `state` (string, unique), `code_verifier` (text), `scopes` (json), `redirect_uri` (string), `expires_at` (timestamp, ~10 min), `created_at`
  - Index on `state`, on `expires_at` (for sweep)
- [x] Migration: `create_hmrc_tokens_table`
  - `id`, `user_id` (fk, cascade, **unique**), `access_token` (text, encrypted), `refresh_token` (text, encrypted), `token_type` (string, default `bearer`), `scopes` (json), `expires_at` (timestamp), `refresh_expires_at` (timestamp), `last_refreshed_at` (timestamp, nullable), `last_expiry_warning_at` (timestamp, nullable — used to dedupe T-30/T-7 reconnect notifications), `connected_at` (timestamp), `timestamps`
- [x] Migration: `create_hmrc_device_identifiers_table` — stable per-user device ID for `Gov-Client-Device-ID`. **Must persist across token churn** per HMRC fraud-prevention spec; storing it on `hmrc_tokens` is wrong because disconnect/reconnect would mint a new ID
  - `id`, `user_id` (fk, cascade, **unique**), `device_id` (uuid), `first_seen_at` (timestamp), `last_seen_at` (timestamp), `timestamps`
  - Implementation: long-lived secure cookie (`hmrc_device_id`, HttpOnly, SameSite=Lax, ~10y) generated server-side on first OAuth visit; mirrored to client; never reset on disconnect/reconnect
- [x] Migration: `create_hmrc_token_refresh_logs_table` — every refresh attempt for ops monitoring
  - `id`, `user_id` (fk, cascade), `outcome` (enum: `success`, `failure_invalid_grant`, `failure_network`, `failure_other`), `error_code` (string nullable), `attempted_at` (timestamp), `timestamps`
  - Index on `(outcome, attempted_at)` for failure-rate dashboards
- [x] **Update `.claude/database-schema.md`** with all four tables + relationships before marking 1b complete

### 1c. Models

- [x] `app/Models/HmrcOAuthState.php` — `casts()`: `scopes` array, `expires_at` datetime; scope `notExpired()`
- [x] `app/Models/HmrcToken.php` — `casts()`: `access_token` encrypted, `refresh_token` encrypted, `scopes` array, `expires_at`/`refresh_expires_at`/`last_refreshed_at`/`last_expiry_warning_at`/`connected_at` datetime; helpers `isAccessTokenExpired()`, `isRefreshTokenExpired()`, `daysUntilRefreshExpiry()`, `belongsTo(User)`
- [x] `app/Models/HmrcDeviceIdentifier.php` — `belongsTo(User)`, casts timestamps; static `forUser(User, string $cookieValue): self` upserts the record (creates on first sight, touches `last_seen_at` thereafter)
- [x] `app/Models/HmrcTokenRefreshLog.php` — `belongsTo(User)`, `outcome` cast to `HmrcTokenRefreshOutcome` enum

### 1d. Actions (`app/Actions/Hmrc/`)

- [x] `BuildAuthorizationUrlAction` — generates `state`, PKCE `code_verifier` + `code_challenge`, persists an `HmrcOAuthState` row for the user, returns the full HMRC `/oauth/authorize` URL with all required query params
- [x] `ExchangeAuthorizationCodeAction` — POSTs to `/oauth/token` with `grant_type=authorization_code`, `code_verifier`, `client_id/secret`, validates state freshness, writes/upserts `HmrcToken`, deletes the `HmrcOAuthState` row, returns the token model
- [x] `RefreshAccessTokenAction` — atomic refresh under `DB::transaction` + `HmrcToken::lockForUpdate()`. POSTs `grant_type=refresh_token`, updates the token row in place. **Always** writes a `HmrcTokenRefreshLog` row (success or failure) so ops can see the failure rate. On terminal failure (e.g. user revoked at HMRC), throws `HmrcReconnectRequiredException` so the caller can prompt re-connect.
- [x] `GetValidAccessTokenAction` — returns a non-expired access token; if expired (with 60s buffer, configurable via `hmrc.access_token_refresh_buffer`), invokes the refresh action; throws if no token exists or refresh failed
- [x] `CallHmrcApiAction` — generic `Http::withToken()->acceptJson()->...` wrapper that:
  - Resolves the token via `GetValidAccessTokenAction`
  - Sets `Accept: application/vnd.hmrc.X.Y+json` (version pinned per call)
  - Returns the parsed response or throws a typed `HmrcApiException` with status + error code
  - Logs request/response status for debugging
- [x] `HelloWorldAction` — `CallHmrcApiAction` against `/hello/user` with `Accept: application/vnd.hmrc.1.0+json`

#### Foundational utilities (used by every later phase — define in Phase 1, not later)

- [x] `app/Support/HmrcMoney.php` — single source of truth for monetary conversion across the three formats DRIVE handles:
  - UI input: pounds with up to 2dp (`"1234.56"` or `1234.56`)
  - DB storage: `bigInteger` pence (`123456`)
  - HMRC payload: decimal pounds with exactly 2dp (`1234.56` as JSON number)
  - Methods: `fromInput(string|int|float): int`, `toDisplay(int): string`, `toHmrcPayload(int, bool $allowNegative, bool $allowZero): float`
- [x] `app/Enums/HmrcErrorCode.php` — enumerated catalogue of HMRC error codes we surface to instructors with user-friendly copy. Methods: `userMessage()`, `isRetryable()`, `tryFromString()`, `default()` fallback.
- [x] `app/Exceptions/Hmrc/HmrcApiException.php` — preserves HMRC's `code`, `message`, and `errors[]` array. Exposes `errorCode(): ?HmrcErrorCode` and `userMessage()`.
- [x] `app/Exceptions/Hmrc/HmrcReconnectRequiredException.php` — thrown when refresh fails terminally; surfaces in UI as "your HMRC connection needs renewing"
- [x] `app/Console/Commands/MonitorHmrcTokenExpiry` — daily cron (07:00, registered in `bootstrap/app.php`). For every connected user, computes days until `refresh_expires_at`. At T-30 and T-7, sends `HmrcReconnectSoonNotification` (mail) + queues a push via `PushNotificationService`. Idempotent via `last_expiry_warning_at` on `hmrc_tokens`.

### 1e. Service

- [x] `app/Services/HmrcService extends BaseService` — constructor injects all 6 actions. Public methods:
  - `connectionStatusFor(User): array` — returns `{ connected, connected_at, expires_at, refresh_expires_at, scopes, days_until_refresh_expiry }` for UI
  - `beginAuthorization(User): string` — returns the URL to redirect the browser to
  - `completeAuthorization(User, string $code, string $state): HmrcToken` — handles callback validation + exchange
  - `disconnect(User): void` — deletes the token row
  - `helloWorld(User): array` — proves the connection
- [x] No caching on token reads (volatile, security-sensitive). No caching on hello-world output.

### 1f. Controllers (web — `app/Http/Controllers/Hmrc/`)

- [x] `HmrcConnectionController` (Inertia)
  - `index()` → `Hmrc/Connection.vue` with `connection`, `environment`, and flashed `helloWorldResponse` props
  - `connect()` → ensures a stable `hmrc_device_id` cookie, redirects to authorization URL
  - `callback(Request)` → handles `code`/`state`/`error` query params, finalises, redirects to `index` with flash
  - `disconnect()` → deletes the token row, redirects with flash

### 1g. Routes (`routes/web.php`)

- [x] New `app/Http/Middleware/EnsureInstructor.php` (instructor-only gate; mirror of `EnsureOwner`)
- [x] Routes added to `routes/web.php` outside the `RestrictInstructor` group, gated by `['auth', 'verified', EnsureInstructor::class]`:
  ```php
  Route::middleware(['auth', 'verified', EnsureInstructor::class])
      ->prefix('hmrc')->name('hmrc.')->group(function () {
          Route::get('/', [HmrcConnectionController::class, 'index'])->name('index');
          Route::get('/connect', [HmrcConnectionController::class, 'connect'])->name('connect');
          Route::get('/oauth/callback', [HmrcConnectionController::class, 'callback'])->name('callback');
          Route::post('/disconnect', [HmrcConnectionController::class, 'disconnect'])->name('disconnect');
          Route::post('/test/hello-world', HmrcHelloWorldController::class)->name('test.hello-world');
      });
  ```
- [x] **Note on CSRF / state parameter:** OAuth `state` is what HMRC requires for CSRF on the callback. Laravel's session CSRF doesn't apply to GET callbacks. The `HmrcOAuthState` row is the trust anchor.

### 1h. Frontend (`resources/js/`)

- [x] `pages/Hmrc/Connection.vue` — Inertia page showing:
  - Status card: "Connected since…", access/refresh expiry, scopes granted, "Disconnect" button
  - "Not connected" state with environment-aware "Connect to HMRC" button
  - "Test Hello World" button (POST to `/hmrc/test/hello-world`) with loading state, displays response in a code block
  - Uses ShadCN Card, Button (`Loader2` icon), Badge, Sonner toast (driven from flash messages)
- [x] Sidebar nav item "HMRC / Tax" with `roles: ['instructor']` (uses string href `/hmrc` until Wayfinder regenerates)
- [ ] **USER ACTION:** Run `npm run dev` (or `composer run dev`) so Wayfinder generates `@/routes/hmrc/*` and Vite picks up the new Vue page

### 1i. Verification

- [x] `php -l` on every new PHP file (all clean)
- [x] `php artisan route:clear` then `php artisan route:list --path=hmrc` shows all 5 routes
- [x] `php artisan schedule:list` shows `hmrc:monitor-token-expiry` queued for `0 7 * * *`
- [ ] **USER ACTION:** Confirm `npm run dev` is running, run `php artisan migrate`, populate HMRC creds in `.env`, then test the round-trip in the browser

### Phase 1 Reflection

**What went well**
- The OAuth round-trip primitives (state + PKCE + token exchange + atomic refresh) are isolated as small `__invoke` actions, so Phase 3/4 can compose them without touching the OAuth code.
- `RefreshAccessTokenAction` is locked under `DB::transaction` + `lockForUpdate`, and *every* attempt writes a `HmrcTokenRefreshLog` row — the failure-rate dashboard the spec asks for in Phase 5 already has its data source.
- The stable `HmrcDeviceIdentifier` lives in its own table with a static `forUser()` upsert + cookie mirror, so the device ID survives disconnect/reconnect (which is what HMRC's fraud-prevention guidance requires).
- `HmrcMoney`, `HmrcErrorCode`, `HmrcApiException`, and `HmrcReconnectRequiredException` all landed in Phase 1 as planned, ready for Phase 3 to lean on without retrofitting.

**Gotchas / decisions worth recording**
- HMRC routes had to live *outside* the `RestrictInstructor` group (which only allows instructors at `/instructors/{theirId}/*` + `/students/*`). They are now in their own `auth + verified + EnsureInstructor` group, which is cleaner anyway.
- The sidebar nav uses a string href (`/hmrc`) instead of a Wayfinder import, because the Wayfinder TypeScript file for `@/routes/hmrc` only exists after `npm run dev` regenerates. Once it does, this can be swapped to `import { index as hmrcIndex } from '@/routes/hmrc'`.
- Real HMRC client_id/secret were intentionally NOT written to `.env` by Claude — flagged as a USER ACTION to keep secrets out of any tooling history.

**Open items entering Phase 1.5**
- User to populate `HMRC_CLIENT_ID` / `HMRC_CLIENT_SECRET` / `HMRC_REDIRECT_URI` in `.env`.
- User to run `php artisan migrate`.
- User to run `npm run dev` so Wayfinder regenerates and Vite picks up the page.
- User to do the end-to-end Hello World round-trip in the browser before approving Phase 1.5.

---

## Phase 1.5: Tax Profile ⏸️ Not Started

**Goal:** Capture the instructor's business structure and tax identifiers BEFORE they connect any specific MTD service. This profile drives which OAuth scopes we request, which Connect buttons appear, and which submission UIs are shown after.

**Why this phase exists:** MTD covers different things for different business types (see decision matrix below). Asking for VAT scopes from a non-VAT-registered instructor is wrong; showing ITSA UI to a Ltd company director is wrong. The profile is the gate.

### MTD applicability matrix (drives the UI)

| Business type | VAT-registered | MTD VAT shown | MTD ITSA shown | Notes |
|---|---|---|---|---|
| Sole trader | No | hidden | from threshold date | Currently no MTD until ITSA mandate hits their band |
| Sole trader | Yes | shown | from threshold date | Most common driving instructor profile |
| Partnership | No | hidden | "TBC by HMRC" notice | ITSA timeline unconfirmed |
| Partnership | Yes | shown | "TBC by HMRC" notice | |
| Limited company | No | hidden | n/a | No MTD relevance — show "no MTD services apply" notice |
| Limited company | Yes | shown (against company VRN) | n/a | Director's personal SA dividends are NOT MTD |

ITSA threshold dates (informational, used in UI copy):
- 6 Apr 2026 — qualifying income > £50k
- 6 Apr 2027 — qualifying income > £30k
- 6 Apr 2028 — qualifying income > £20k

### 1.5a. Database

- [ ] Migration: `add_tax_profile_fields_to_instructors_table`
  - `business_type` (enum: `sole_trader`, `partnership`, `limited_company`, nullable)
  - `vat_registered` (boolean, default false)
  - `vrn` (string 9 chars, nullable, unique-where-not-null)
  - `utr` (string 10 chars, nullable) — sole trader / partnership
  - `nino` (string 9 chars, nullable, encrypted) — sole trader / partnership; encrypted because PII
  - `companies_house_number` (string 8 chars, nullable) — Ltd only
  - `tax_profile_completed_at` (timestamp, nullable) — set when the form is first saved
- [ ] Update `.claude/database-schema.md` with the new columns on `instructors`

### 1.5b. Models

- [ ] `Instructor` model — add `casts()` entries: `tax_profile_completed_at` datetime, `vat_registered` bool, `nino` encrypted, `business_type` cast to a `BusinessType` enum
- [ ] New PHP enum `app/Enums/BusinessType.php` — `SoleTrader`, `Partnership`, `LimitedCompany` (TitleCase per project convention) with a `label()` method for UI

### 1.5c. Validation

- [ ] FormRequest `app/Http/Requests/UpdateInstructorTaxProfileRequest`:
  - `business_type` — required, in enum
  - `vat_registered` — required boolean
  - `vrn` — `required_if:vat_registered,true`, regex 9 digits
  - `utr` — `required_if:business_type,sole_trader,partnership`, regex 10 digits
  - `nino` — `required_if:business_type,sole_trader,partnership`, regex `/^[A-Z]{2}\d{6}[A-D]$/i` (server-side)
  - `companies_house_number` — `required_if:business_type,limited_company`, regex 8 alphanumeric
- [ ] **Note:** v1 uses regex-only validation. Live VRN validation via HMRC's `Check a UK VAT number` API is deferred (see future-work below) — it's a separate app-restricted API requiring server-token auth and adds scope creep to this task.

### 1.5d. Service / Actions

- [ ] `app/Actions/Hmrc/Profile/UpdateTaxProfileAction` — invokable, takes `Instructor` + validated array, persists fields, sets `tax_profile_completed_at`
- [ ] `app/Actions/Hmrc/Profile/GetMtdApplicabilityAction` — pure function: takes `Instructor`, returns a struct describing which MTD services apply: `{ vat: { applies: bool, vrn: ?string }, itsa: { applies: bool, mandatory_from: ?Carbon, threshold_band: ?string }, ct: { applies: false, reason: string } }`. Single source of truth for the UI conditionals.
- [ ] Extend `HmrcService` with:
  - `getTaxProfile(Instructor): array` — for Inertia page props
  - `updateTaxProfile(Instructor, array): Instructor` — wraps the action, invalidates any cached MTD applicability
  - `getMtdApplicability(Instructor): array` — wraps the applicability action

### 1.5e. Controllers + UI

- [ ] Extend `HmrcConnectionController::index()` to also pass tax-profile + applicability props to the page
- [ ] New `HmrcConnectionController::updateTaxProfile(UpdateInstructorTaxProfileRequest)` — POSTs to `/hmrc/tax-profile`, redirects back with toast
- [ ] Update `pages/Hmrc/Connection.vue`:
  - Top section: "Your tax profile" — radio for business type, switch for VAT-registered, conditional fields (VRN / UTR / NINO / CRN). Sheet form (per project convention) opened from an "Edit tax profile" button on a status card.
  - Below the profile: "Available HMRC services" — conditional cards based on applicability. VAT card always present if applicable; ITSA card stubbed with "Coming soon — mandatory for you from <date>"; Ltd-only-no-VAT shows "No MTD services currently apply" with explanatory copy.
  - Hello World test button stays — useful diagnostic regardless of profile.
- [ ] Conditional Connect button: the existing "Connect to HMRC" from Phase 1 becomes service-specific in Phase 3 (`Connect VAT`). For Phase 1.5 the generic Connect still works for Hello World testing.

### 1.5f. Routes

- [ ] Add to the `hmrc` route group:
  ```php
  Route::post('/tax-profile', [HmrcConnectionController::class, 'updateTaxProfile'])->name('tax-profile.update');
  ```

### 1.5g. Verification

- [ ] `php -l` on new files
- [ ] `database-schema.md` updated
- [ ] Toggle through all 6 matrix combinations in the UI; correct services shown for each
- [ ] Validation rejects bad VRN/UTR/NINO/CRN formats with helpful messages

### Phase 1.5 Reflection

_(Filled in at end of phase 1.5)_

---

## Phase 2: Fraud Prevention Headers ⏸️ Not Started

**Goal:** Add the legally-required `Gov-Client-*` and `Gov-Vendor-*` fraud prevention headers to all HMRC API calls (other than the OAuth endpoints and Hello World), and validate them clean against HMRC's Test Fraud Prevention Headers API.

**Connection method to declare:** `WEB_APP_VIA_SERVER` — user authenticates in their browser, our server makes the API calls. (HMRC defines several methods; this matches the architecture.)

### 2a. Spec verification (read-only research, no code yet)

- [ ] Fetch the current `WEB_APP_VIA_SERVER` header spec from `https://developer.service.hmrc.gov.uk/guides/fraud-prevention/` and capture the exact required header list + values into `.claude/hmrc-fraud-headers.md` (project-internal note, not a public doc)
- [ ] Confirm header expectations for the Test Fraud Prevention Headers Validator endpoint URL + scope

### 2b. Database

- [ ] Migration: `create_hmrc_client_fingerprints_table` (one row per `hmrc_token_id`, refreshed on each interactive submit)
  - `id`, `hmrc_token_id` (fk, unique), `screens` (json), `window_size` (json), `timezone` (string), `local_ips` (json), `browser_user_agent` (text), `browser_do_not_track` (boolean nullable), `browser_plugins` (json nullable), `captured_at` (timestamp), `timestamps`
  - Why a row, not session: instructor may submit from a queued job later, and we want a recent fingerprint. Refreshed on each submit-flow entry.
- [ ] Update `.claude/database-schema.md`

### 2c. Frontend fingerprint capture

- [ ] `resources/js/lib/hmrcFingerprint.ts` — function that captures: `screen.width`, `height`, `colorDepth`, `pixelRatio`, `window.innerWidth/innerHeight`, `Intl.DateTimeFormat().resolvedOptions().timeZone`, `navigator.userAgent`, `navigator.doNotTrack`, attempts WebRTC local IP (best effort, may be null)
- [ ] New POST endpoint `POST /hmrc/fingerprint` that accepts the JSON and stores it on the user's `HmrcClientFingerprint`. Called by the frontend before every interactive HMRC action (connect, hello world, VAT submit).
- [ ] FormRequest `StoreHmrcFingerprintRequest` — strict types, max sizes
- [ ] Composable `resources/js/composables/useHmrcAction.ts` that captures fingerprint, POSTs it, then performs the actual action

### 2d. Backend header builder

- [ ] `app/Actions/Hmrc/BuildFraudPreventionHeadersAction` — input: `User`, `Request`, `HmrcClientFingerprint`. Output: array of header name → value strings. Implements the full `WEB_APP_VIA_SERVER` set:
  - `Gov-Client-Connection-Method: WEB_APP_VIA_SERVER`
  - `Gov-Client-Public-IP` (from `$request->ip()` — verify `TrustProxies` is configured for the edge so this isn't the LB's IP)
  - `Gov-Client-Public-IP-Timestamp` (ISO-8601 with milliseconds, UTC)
  - `Gov-Client-Public-Port` (from `$request->server('REMOTE_PORT')`; HMRC's spec permits omission when the proxy strips it — log a warning rather than fail)
  - `Gov-Client-Device-ID` (from `HmrcDeviceIdentifier::forUser($user, $cookie)->device_id` — stable across token churn; cookie-mirrored)
  - `Gov-Client-User-IDs` (`os=<user_id>`)
  - `Gov-Client-Timezone` (e.g. `UTC+01:00` derived from fingerprint)
  - `Gov-Client-Local-IPs` (from fingerprint, comma-separated)
  - `Gov-Client-Local-IPs-Timestamp`
  - `Gov-Client-Screens` (e.g. `width=…&height=…&scaling-factor=…&colour-depth=…`)
  - `Gov-Client-Window-Size` (e.g. `width=…&height=…`)
  - `Gov-Client-Browser-Plugins`
  - `Gov-Client-Browser-JS-User-Agent`
  - `Gov-Client-Browser-Do-Not-Track`
  - `Gov-Client-Multi-Factor` (if user MFA'd in this session — capture from session)
  - `Gov-Vendor-Version` (from app version env)
  - `Gov-Vendor-License-IDs` (UUID generated per-user, stored)
  - `Gov-Vendor-Public-IP` (server's outbound IP — resolved per call with a 5-minute cache; cloud egress IPs aren't stable over long timeframes)
  - `Gov-Vendor-Forwarded`
  - `Gov-Vendor-Product-Name`
- [ ] Update `CallHmrcApiAction` to accept an optional `withFraudHeaders: bool` flag — when true, builds and merges fraud headers; default false (Hello World stays bare)
- [ ] Add `app/Exceptions/Hmrc/MissingFraudFingerprintException` and surface as a 422 with a clear message when an interactive submit is attempted without a fresh fingerprint

### 2e. Validator endpoint

- [ ] `app/Actions/Hmrc/ValidateFraudHeadersAction` — calls `POST /test/fraud-prevention-headers/validator/validate` (the Test Fraud Prevention Headers API). Echoes the header set to HMRC and parses the response (`errors` and `warnings` arrays).
- [ ] Add a "Validate fraud headers" button on the Connection page that posts to `POST /hmrc/test/fraud-headers` and renders any errors/warnings in a ShadCN Alert
- [ ] Iterate until clean (no errors). Warnings can be left documented but should be minimised.

### 2f. Verification

- [ ] Run validator from UI; expect zero errors, ideally zero warnings
- [ ] `php -l` + route list
- [ ] `database-schema.md` updated

### Phase 2 Reflection

_(Filled in at end of phase 2)_

---

## Phase 3: ITSA Quarterly Updates ⏸️ Not Started

**Goal:** Connected sole-trader instructor sees their open ITSA obligations, can submit a quarterly self-employment update for any open period (income totals + expense category totals), and the submission is stored permanently with HMRC's correlation ID. Reminder system pings them ahead of deadlines.

**Scope decision for v1:** Manual entry of income + categorised expenses. Auto-derivation from `instructor_finances` and `mileage_logs` is the next major task after this trio ships. v1 may show DRIVE's calculated totals alongside the form as a reference/sanity check, but the user types in the final figures.

**API source of truth:** [MTD ITSA end-to-end service guide](https://developer.service.hmrc.gov.uk/guides/income-tax-mtd-end-to-end-service-guide/). Last reviewed 23 March 2026. Ignore any earlier references to EOPS — that step was removed in July 2024.

### 3a. ITSA spec verification (research before coding)

- [ ] Pull current specs from these API reference pages and capture into `.claude/hmrc-itsa-api.md`:
  - **Business Details (MTD) v2.0** — `GET /individuals/business/details/{nino}/list`, `GET /individuals/business/details/{nino}/{businessId}`
  - **Obligations (MTD) v3.0** — `GET /obligations/details/{nino}/income-and-expenditure` (the quarterly obligations endpoint)
  - **Self Employment Business (MTD) v5.0** — `POST /individuals/business/self-employment/{nino}/{businessId}/period` (submit quarterly), `GET /…/period/{periodId}` (retrieve), `PUT /…/period/{periodId}` (amend before final declaration)
- [ ] Confirm scope strings — typically `read:self-assessment` and `write:self-assessment`
- [ ] Confirm whether the `Accept` header for these is `application/vnd.hmrc.X.Y+json` and whether the version differs per endpoint
- [ ] Capture the exact JSON shapes for the quarterly update body (income object + expenses object — see categories list below)
- [ ] Document the standard error envelope and the retry-able codes

### 3a.5. MTD ITSA enrolment status (gating check)

**Why this exists:** OAuth success ≠ instructor is signed up for MTD ITSA. They must also be registered for Self Assessment, have submitted a return in the last 2 years, and have signed up each income source for MTD. If they haven't, Business Details returns empty/404 with `RULE_NOT_SIGNED_UP_TO_MTD` and the rest of the flow falls over silently. We need an explicit state machine surfaced in the UI before any submission UI is shown.

- [ ] Migration: `add_mtd_itsa_status_to_instructors_table`
  - `mtd_itsa_status` (enum: `unknown`, `not_signed_up`, `signed_up_voluntary`, `mandated`, `income_source_missing`, default `unknown`)
  - `mtd_itsa_status_checked_at` (timestamp nullable)
  - Update `.claude/database-schema.md`
- [ ] `app/Actions/Hmrc/Itsa/ResolveEnrolmentStatusAction` — calls `ListBusinessesAction` and interprets the response:
  - HMRC error `RULE_NOT_SIGNED_UP_TO_MTD` → `not_signed_up`
  - 200 with empty list → `income_source_missing`
  - 200 with non-empty list, current tax year before instructor's mandate band → `signed_up_voluntary`
  - 200 with non-empty list, current tax year ≥ mandate band → `mandated`
  - Persists state + `checked_at` to instructor; refreshes daily via the Phase 3f cron
- [ ] UI on `Hmrc/Itsa/Index.vue`: explicit state cards per status:
  - `not_signed_up` — CTA linking to `https://www.gov.uk/guidance/sign-up-your-business-for-making-tax-digital-for-income-tax`, submission UI disabled with explanation
  - `income_source_missing` — explains the SA-business-registration prerequisite with link to gov.uk
  - `signed_up_voluntary` / `mandated` — full submission UI available; status badge visible
  - `unknown` — show "checking…" skeleton, trigger `ResolveEnrolmentStatusAction` synchronously
- [ ] Middleware `EnsureMtdEnrolled` guarding all ITSA submission routes (rejects unless status is `signed_up_voluntary` or `mandated`)

### 3b. Database

Four new tables, all keyed off `user_id` (and optionally `instructor_id` for the relationship):

- [ ] Migration: `create_hmrc_itsa_businesses_table` — cache of businesses returned from Business Details
  - `id`, `user_id` (fk, cascade), `instructor_id` (fk, nullable), `business_id` (string from HMRC, indexed), `type_of_business` (enum: `self-employment`, `uk-property`, `foreign-property`), `trading_name` (string nullable), `accounting_type` (enum: `cash`, `accruals`, nullable), `commencement_date` (date nullable), `cessation_date` (date nullable), `latency_details` (json nullable — HMRC's `latencyDetails` block), `last_synced_at` (timestamp), `timestamps`
  - Unique on `(user_id, business_id)`
- [ ] Migration: `create_hmrc_itsa_obligations_table` — cached open obligations, refreshed periodically
  - `id`, `user_id` (fk), `business_id` (string, indexed), `period_key` (string, indexed), `period_start_date` (date), `period_end_date` (date), `due_date` (date), `received_date` (date nullable), `status` (enum: `Open`, `Fulfilled`), `obligation_type` (string), `last_synced_at` (timestamp), `timestamps`
  - Unique on `(user_id, business_id, period_key, obligation_type)`
- [ ] Migration: `create_hmrc_itsa_quarterly_updates_table` — permanent audit record per quarterly submission
  - `id`, `user_id`, `instructor_id` (nullable), `business_id` (indexed), `period_key` (indexed), `period_start_date`, `period_end_date`
  - **Income** (bigInteger pence): `turnover`, `other_income`
  - **Expenses** (all bigInteger pence, all nullable — instructor may use consolidated or categorised):
    - `consolidated_expenses` (single field for sub-£90k threshold filers using the simplified bucket)
    - OR categorised: `cost_of_goods`, `payments_to_subcontractors`, `wages_and_staff_costs`, `car_van_travel_expenses`, `premises_running_costs`, `maintenance_costs`, `admin_costs`, `business_entertainment_costs`, `advertising_costs`, `interest_on_bank_other_loans`, `finance_charges`, `irrecoverable_debts`, `professional_fees`, `depreciation`, `other_expenses`
  - `submission_id` (string from HMRC), `correlation_id` (string), `submitted_at`, `request_payload` (json), `response_payload` (json), `digital_records_attested_at` (timestamp), `digital_records_attested_by_user_id` (fk users), `timestamps`
  - Unique on `(user_id, business_id, period_key)` — represents the *current* state per period; amendment history lives in `hmrc_itsa_quarterly_update_revisions` (next migration). This row is updated in place; revisions are append-only.
- [ ] Migration: `create_hmrc_itsa_quarterly_update_revisions_table` — immutable audit trail (append-only, 6-year retention)
  - `id`, `quarterly_update_id` (fk, cascade), `user_id`, `revision_number` (unsigned int, starts at 1), `kind` (enum: `submission`, `amendment`), `request_payload` (json), `response_payload` (json), `submission_id`, `correlation_id`, `submitted_at`, `submitted_by_user_id` (fk users — supports staff-assisted submissions), `digital_records_attested_at`, `timestamps`
  - Unique on `(quarterly_update_id, revision_number)`; index on `(user_id, submitted_at)` for export
  - **Never updated or deleted.** Every submission writes revision 1; every amendment writes revision N+1 *before* the parent row is updated. A failed submission also writes a revision with the error response so the audit trail is complete.
- [ ] Update `.claude/database-schema.md` with all four tables + relationships

### 3c. Models + Enums

- [ ] `app/Models/HmrcItsaBusiness` with relationships, `casts()` for dates + `latency_details` array
- [ ] `app/Models/HmrcItsaObligation` with `casts()` for dates + status enum
- [ ] `app/Models/HmrcItsaQuarterlyUpdate` with `casts()` for all integer pence fields and json payloads
- [ ] `app/Enums/ItsaBusinessType` (`SelfEmployment`, `UkProperty`, `ForeignProperty`)
- [ ] `app/Enums/ItsaObligationStatus` (`Open`, `Fulfilled`)
- [ ] `app/Enums/ItsaExpenseCategory` — full category list with `label()` for UI

### 3d. Actions (`app/Actions/Hmrc/Itsa/`)

- [ ] `ListBusinessesAction` — calls Business Details, upserts into `hmrc_itsa_businesses`. Fraud headers required.
- [ ] `RetrieveBusinessAction` — single-business detail
- [ ] `ListObligationsAction` — calls Obligations API for the quarterly obligations, upserts into `hmrc_itsa_obligations`
- [ ] `SubmitQuarterlyUpdateAction` — wraps the submit in a transaction. Writes a draft `hmrc_itsa_quarterly_updates` row, calls HMRC, updates the row with `submission_id` + `correlation_id` + response payload. On HMRC error, persists the error envelope and throws a typed exception.
- [ ] `RetrieveQuarterlyUpdateAction` — read-back of a previously-submitted period
- [ ] `AmendQuarterlyUpdateAction` — `PUT` for corrections (allowed before final declaration). Archives previous payload into the `request_payload` history field.

### 3e. Service

- [ ] `app/Services/HmrcItsaService extends BaseService` — orchestrates ITSA flows. Methods:
  - `syncBusinessesFor(User): Collection`
  - `getObligationsFor(User, ?string $businessId): Collection`
  - `submitQuarterly(User, HmrcItsaBusiness, array $payload, Request): HmrcItsaQuarterlyUpdate`
  - `amendQuarterly(User, HmrcItsaQuarterlyUpdate, array $payload, Request): HmrcItsaQuarterlyUpdate`
  - `historyFor(User): Collection`
- [ ] Cache obligations for a short TTL (5 min) using `BaseService::remember()` to avoid hitting HMRC on every page load. Invalidate after every submit/amend.

### 3f. Reminder & scheduling subsystem (NEW for ITSA, not needed for VAT)

ITSA's quarterly cadence is the big new piece — instructors will miss deadlines without prompts. Build this as part of Phase 3 because it's load-bearing for the product.

- [ ] Console command `app/Console/Commands/SyncHmrcItsaObligations` — daily cron via `routes/console.php`. For every connected instructor with `business_type=sole_trader` and ITSA scope granted: call `ListObligationsAction`, persist obligations, queue notifications for new deadlines.
- [ ] Notification class `app/Notifications/ItsaObligationDueSoon` — sent at 30 / 14 / 7 / 1 day(s) out via the existing `PushNotificationService` channel + email. Suppressed once the obligation is `Fulfilled`.
- [ ] DB column on `hmrc_itsa_obligations`: `last_reminder_sent_at` (or a small `obligation_reminders` table) to prevent duplicate sends
- [ ] Frontend banner on the dashboard when an obligation is <14 days from due date

### 3g. Controllers + UI (`app/Http/Controllers/Hmrc/Itsa/`)

- [ ] `ItsaController` (Inertia)
  - `index()` → `Hmrc/Itsa/Index.vue` — current period card + open obligations + submitted-history table
  - `period(string $businessId, string $periodKey)` → `Hmrc/Itsa/Period.vue` — quarterly update form
  - `store(SubmitQuarterlyUpdateRequest)` — performs submission, redirects with toast + reference
  - `amend(AmendQuarterlyUpdateRequest, HmrcItsaQuarterlyUpdate)` — corrections before final declaration
- [ ] `app/Http/Requests/SubmitQuarterlyUpdateRequest` — validates per HMRC's spec (decimal precision, range, mutually-exclusive consolidated vs categorised, period date sanity)
- [ ] `Hmrc/Itsa/Period.vue` form (Sheet, ShadCN per convention):
  - Toggle: **Consolidated expenses** (single field) vs **Categorised** (full breakdown). Pre-set based on instructor's turnover from the tax profile if known.
  - Income section: Turnover + Other income
  - Expense categories (when categorised): the 15 fields listed in 3b. Categories with zero typically pre-collapsed.
  - Side panel showing DRIVE's totals from `instructor_finances` + `mileage_logs` for the period as a sanity reference (read-only — auto-derivation is a later task)
  - Submit triggers AlertDialog confirmation (this is irreversible until amended) with **mandatory digital-records attestation checkbox**: *"I confirm these figures are derived from digital business records that I keep in line with MTD requirements."* Submit button disabled until ticked. Attestation timestamp + user id stored on both the parent row and the revision row. (Manual entry in v1 still requires this attestation — the legal obligation to keep digital records is on the instructor regardless of how figures reach DRIVE.)
- [ ] Update `pages/Hmrc/Connection.vue` to show ITSA service card with applicability driven by the Phase 1.5 `GetMtdApplicabilityAction`

### 3h. Routes

- [ ] Add inside the existing `hmrc` route group:
  ```php
  Route::prefix('itsa')->name('itsa.')->group(function () {
      Route::get('/', [ItsaController::class, 'index'])->name('index');
      Route::get('/{businessId}/period/{periodKey}', [ItsaController::class, 'period'])->name('period');
      Route::post('/{businessId}/period', [ItsaController::class, 'store'])->name('store');
      Route::put('/quarterly-updates/{quarterlyUpdate}', [ItsaController::class, 'amend'])->name('amend');
  });
  ```

### 3i. Verification

- [ ] Provision a sandbox ITSA test user via `Self Assessment Test Support (MTD)` (already subscribed)
- [ ] End-to-end: connect → list businesses → list obligations → submit a quarterly update → verify storage of `submission_id` and `correlation_id` → amend → verify amendment persisted
- [ ] Reminder cron: dry-run shows expected notifications would fire
- [ ] `php -l` + route list shows ITSA routes registered

### Phase 3 Reflection

_(Filled in at end of phase 3 — STOP. Do not proceed to Phase 3.5 without user approval.)_

---

## Phase 3.5: ITSA Final Declaration ⏸️ Not Started

**Goal:** At tax-year end, the instructor reviews their full year's data, adds any supplementary income/reliefs/disclosures (savings interest, dividends, pension contributions, marriage allowance), triggers HMRC's tax calculation, reviews the calculated liability, and submits their **Final Declaration** — the equivalent of the legacy SA tax return.

**Scope decision for v1:**
- Cover the supplementary types most likely to apply to a driving instructor: savings income (bank interest), dividends, reliefs (pension contributions, charity donations), disclosures (marriage allowance), and personal details
- Defer: capital gains, foreign income, employment income (most full-time instructors don't have it), property income, partner income, state benefits, charges, losses, tax-liability-adjustments
- v1 is **manual entry** for all supplementary fields. Auto-population from connected sources (e.g. open banking for savings interest) is a future enhancement.

### 3.5a. Spec verification

- [ ] Pull and document into `.claude/hmrc-itsa-final-declaration.md`:
  - **Individual Calculations (MTD) v8.0** — `POST /individuals/calculations/{nino}/self-assessment` (trigger), `GET /…/{calculationId}` (retrieve), `POST /…/{calculationId}/final-declaration` (submit)
  - **Self Assessment Accounts (MTD) v4.0** — `GET /accounts/self-assessment/{nino}/balance-and-transactions`, payment history
  - **Self Assessment Individual Details (MTD) v2.0**
  - **Individuals Reliefs (MTD) v3.0** — pension contributions, charitable giving
  - **Individuals Disclosures (MTD) v2.0** — Marriage Allowance transfer
  - **Individuals Savings Income (MTD) v2.0** — UK and foreign savings/interest
  - **Individuals Dividends Income (MTD) v2.0** — UK dividends from securities
- [ ] Document the calculation polling flow: trigger returns `calculationId`, status async — poll until `metadata.calculationOutcome` is `IS_PROCESSED`. Backoff strategy and timeout.

### 3.5b. Database

- [ ] Migration: `create_hmrc_itsa_calculations_table` — calculation IDs + cached payloads
  - `id`, `user_id`, `nino`, `tax_year` (string e.g. `2025-26`), `calculation_id` (string from HMRC, indexed), `calculation_type` (enum: `inYear`, `intentToCrystallise`, `crystallisation`, `finalDeclaration`), `status` (enum: `pending`, `processed`, `errored`), `triggered_at`, `processed_at` (nullable), `summary_payload` (json — calculation summary), `detail_payload` (json — full calc breakdown, lazy-loaded), `error_payload` (json nullable), `timestamps`
- [ ] Migration: `create_hmrc_itsa_supplementary_data_table` — single row per (user, tax_year, type) covering reliefs/disclosures/savings/dividends/personal-details
  - `id`, `user_id`, `tax_year`, `type` (enum: `reliefs`, `disclosures`, `savings`, `dividends`, `individual_details`), `payload` (json — the data we sent to HMRC), `submission_id` (from HMRC), `submitted_at`, `timestamps`
  - Unique on `(user_id, tax_year, type)` — most types are upsert-able submissions
- [ ] Migration: `create_hmrc_itsa_final_declarations_table` — permanent audit
  - `id`, `user_id`, `nino`, `tax_year`, `calculation_id` (fk to calculations row), `submitted_at`, `correlation_id`, `request_payload` (json), `response_payload` (json), `digital_records_attested_at` (timestamp), `digital_records_attested_by_user_id` (fk users), `timestamps`
  - Unique on `(user_id, tax_year)`
- [ ] Update `.claude/database-schema.md`

### 3.5c. Models + Enums

- [ ] Models for each new table with `casts()` for date/json/enum fields
- [ ] `app/Enums/ItsaCalculationType`, `ItsaCalculationStatus`, `ItsaSupplementaryType`

### 3.5d. Actions (`app/Actions/Hmrc/Itsa/FinalDeclaration/`)

For each supplementary data type, a Submit + Retrieve pair:

- [ ] `SubmitRelief`, `RetrieveRelief` (Individuals Reliefs)
- [ ] `SubmitDisclosure`, `RetrieveDisclosure` (Individuals Disclosures — marriage allowance)
- [ ] `SubmitSavingsIncome`, `RetrieveSavingsIncome`
- [ ] `SubmitDividendsIncome`, `RetrieveDividendsIncome`
- [ ] `UpdateIndividualDetails`, `RetrieveIndividualDetails`

For the calculation flow:

- [ ] `TriggerCalculationAction` — POST, returns `calculationId`
- [ ] `RetrieveCalculationAction` — GET, returns full calculation; handles pending status
- [ ] `PollCalculationAction` — internal helper that loops with backoff up to ~60s, throws on timeout
- [ ] `SubmitFinalDeclarationAction` — POST to the calculation's final-declaration endpoint. Atomic: writes draft row → calls HMRC → updates row.
- [ ] `RetrieveAccountBalanceAction` — Self Assessment Accounts liabilities/payments

### 3.5e. Service

- [ ] Extend `HmrcItsaService` with final-declaration methods (or new `HmrcItsaFinalDeclarationService extends BaseService` if it grows large):
  - `getSupplementaryFor(User, string $taxYear): array` — aggregates all supplementary data for the year
  - `saveSupplementary(User, string $taxYear, ItsaSupplementaryType, array, Request): HmrcItsaSupplementaryData`
  - `triggerCalculation(User, string $taxYear, ItsaCalculationType, Request): HmrcItsaCalculation` — async with poll
  - `submitFinalDeclaration(User, HmrcItsaCalculation, Request): HmrcItsaFinalDeclaration`
  - `accountBalanceFor(User): array`

### 3.5f. Controllers + UI — Final Declaration wizard

This is a multi-step flow (heavier than the quarterly form). Build as a wizard with named steps:

- [ ] `Hmrc/Itsa/FinalDeclaration/Index.vue` — landing page for the year, shows progress through the 5 steps with completion status
- [ ] **Step 1: Self-Employment review** — read-only summary of the 4 quarterly updates already submitted; option to amend any quarter (links back to Phase 3 amendment flow)
- [ ] **Step 2: Other Income** — Sheet form for Savings Income + Dividends Income (manually entered totals)
- [ ] **Step 3: Reliefs & Disclosures** — Sheet for pension contributions, charity, Marriage Allowance transfer
- [ ] **Step 4: Personal Details** — confirm/update name, address, marital status (Self Assessment Individual Details API)
- [ ] **Step 5: Calculation Review** — trigger calculation, poll, display HMRC's calculated liability breakdown with a clear "you owe / refund" summary
- [ ] **Step 6: Submit** — AlertDialog confirmation; *"This finalises your tax return for tax year XXXX. After submission you can no longer amend the quarterly updates for this year."* Plus mandatory digital-records attestation checkbox (same wording as quarterly). Attestation timestamp + user id stored on the `hmrc_itsa_final_declarations` row. → submit → receipt screen
- [ ] Each step is a route; user can come back and edit until step 6 is hit
- [ ] FormRequests per step in `app/Http/Requests/Hmrc/Itsa/FinalDeclaration/`

### 3.5g. Routes

- [ ] Add inside the `itsa` route group:
  ```php
  Route::prefix('final-declaration/{taxYear}')->name('final-declaration.')->group(function () {
      Route::get('/', [FinalDeclarationController::class, 'index'])->name('index');
      Route::get('/savings', [FinalDeclarationController::class, 'savingsForm'])->name('savings');
      Route::post('/savings', [FinalDeclarationController::class, 'storeSavings']);
      // …same pattern for dividends / reliefs / disclosures / details
      Route::post('/calculate', [FinalDeclarationController::class, 'triggerCalculation'])->name('calculate');
      Route::get('/calculation/{calculation}', [FinalDeclarationController::class, 'showCalculation'])->name('calculation');
      Route::post('/submit/{calculation}', [FinalDeclarationController::class, 'submit'])->name('submit');
  });
  ```

### 3.5h. Verification

- [ ] End-to-end against sandbox: pre-populate test user with 4 quarters → walk through steps 1–6 → final declaration submitted → response stored
- [ ] Re-trigger calculation in different ways (intentToCrystallise vs final) and verify polling
- [ ] Dashboard shows final-declaration status (submitted / not submitted) for the closing year
- [ ] `php -l` + route list

### Phase 3.5 Reflection

_(Filled in at end of phase 3.5 — STOP. Do not proceed to Phase 4 without user approval.)_

---

## Phase 4: VAT submission flow (optional toggle) ⏸️ Not Started

**Goal:** For the minority of instructors who are VAT-registered (`tax_profile.vat_registered = true`), expose a VAT card alongside ITSA on the HMRC connection page. Instructor can submit a quarterly VAT 9-box return. Reuses Phase 1/1.5/2 foundations end-to-end.

**Scope decision for v1:** Manual 9-box entry. Auto-derivation deferred to the same future work as ITSA auto-derivation.

### 4a. VAT API spec verification

- [ ] Pull current VAT (MTD) v1.0 spec into `.claude/hmrc-vat-api.md`:
  - `GET /organisations/vat/{vrn}/obligations`
  - `GET /organisations/vat/{vrn}/returns/{periodKey}`
  - `POST /organisations/vat/{vrn}/returns`
  - `GET /organisations/vat/{vrn}/liabilities`
  - `GET /organisations/vat/{vrn}/payments`
- [ ] Add `read:vat write:vat` to the OAuth scopes available. **Scope union policy** (explicit to avoid accidental downgrade):
  - Authorisation always requests the **union** of all currently-applicable scopes for the instructor (ITSA always, VAT iff `vat_registered=true`)
  - Existing token is *replaced* on successful re-auth, not merged — HMRC issues a fresh token bound to the new scope set; existing ITSA tokens keep working until the new one is issued
  - `BuildAuthorizationUrlAction` reads currently-granted scopes from `HmrcToken::scopes` and requests the union plus the additions; **never narrower than what's already granted**
  - Test: VAT-registered instructor with existing ITSA token re-auths for VAT → resulting token has both `read:self-assessment write:self-assessment` and `read:vat write:vat`. Never just VAT.
  - Note: there is a small disconnect window during re-auth (browser redirect to HMRC and back); document this in UI copy

### 4b. Database

- [ ] Migration: `create_hmrc_vat_returns_table` — permanent audit record
  - `id`, `user_id`, `instructor_id` (nullable), `vrn` (indexed), `period_key` (indexed)
  - 9 boxes as `bigInteger` pence: `vat_due_sales`, `vat_due_acquisitions`, `total_vat_due`, `vat_reclaimed_curr_period`, `net_vat_due`, `total_value_sales_ex_vat`, `total_value_purchases_ex_vat`, `total_value_goods_supplied_ex_vat`, `total_acquisitions_ex_vat`
  - `finalised` (bool), `submitted_at`, `processing_date`, `form_bundle_number`, `charge_ref_number` (nullable), `payment_indicator` (nullable), `correlation_id`, `request_payload` (json), `response_payload` (json), `digital_records_attested_at` (timestamp), `digital_records_attested_by_user_id` (fk users), `timestamps`
  - Unique on `(user_id, vrn, period_key)`
  - **VAT submissions are immutable on HMRC's side** — there is no amendment endpoint. Once submitted, this row is the audit record; corrections happen via a future-period adjustment, not by editing the row. So no separate revisions table is needed for VAT.
- [ ] VRN already on `instructors` from Phase 1.5 — read it from the tax profile, do not re-prompt.
- [ ] Update `.claude/database-schema.md`

### 4c. Models, Actions, Service

- [ ] `HmrcVatReturn` model with appropriate casts
- [ ] Actions in `app/Actions/Hmrc/Vat/`: `ListVatObligationsAction`, `RetrieveVatReturnAction`, `SubmitVatReturnAction`, `ListVatLiabilitiesAction`, `ListVatPaymentsAction`
- [ ] `app/Services/HmrcVatService extends BaseService` — same shape as `HmrcItsaService`
- [ ] Reuse the Phase 3 reminder/scheduling subsystem — extend the daily cron to also fetch VAT obligations for VAT-registered instructors and queue VAT-deadline notifications

### 4d. Controllers + UI

- [ ] `Hmrc/Vat/VatController` (Inertia)
- [ ] `Hmrc/Vat/Index.vue` — open obligations + history
- [ ] `Hmrc/Vat/Period.vue` — 9-box Sheet form with side panel showing DRIVE totals as reference; AlertDialog confirmation including mandatory digital-records attestation checkbox (same wording as ITSA). Dialog copy must emphasise the immutability: *"VAT submissions cannot be amended once filed — corrections must be made in a future period."*
- [ ] `SubmitVatReturnRequest` FormRequest

### 4e. Routes

- [ ] Add inside the `hmrc` route group:
  ```php
  Route::prefix('vat')->name('vat.')->group(function () {
      Route::get('/', [VatController::class, 'index'])->name('index');
      Route::get('/{periodKey}', [VatController::class, 'period'])->name('period');
      Route::post('/', [VatController::class, 'store'])->name('store');
  });
  ```

### 4f. Verification

- [ ] Provision sandbox VAT test user via Create Test User
- [ ] End-to-end: VAT-registered instructor connects → re-authorises with extra VAT scope → submits a 9-box return → receipt stored
- [ ] Non-VAT-registered instructor's UI does NOT show the VAT card
- [ ] `php -l` + route list

### Phase 4 Reflection

_(Filled in at end of phase 4 — STOP. Do not proceed to Phase 5 without user approval.)_

---

## Phase 5: Production readiness ⏸️ Not Started

**Goal:** Move from sandbox-proven to production-approved. HMRC's production approval is a manual administrative gate with specific minimum-functionality (MFS) and documentation requirements that DRIVE must meet before any real instructor can file. Sandbox proof is necessary but not sufficient — production filing requires a separate application with evidence of the end-to-end journey.

### 5a. HMRC Minimum Functionality Standards (MFS) evidence

HMRC requires evidence that the software supports the minimum filing journey end-to-end. Capture screenshots and a short demo video (2–5 min) of:

- [ ] Instructor signs in to MTD via the app (OAuth flow)
- [ ] View open obligations (quarterly + final declaration)
- [ ] Submit a quarterly update with confirmation/receipt showing correlation ID
- [ ] Amend a previously-submitted quarterly update
- [ ] Trigger a tax calculation and view the breakdown
- [ ] Submit a Final Declaration with receipt
- [ ] (If VAT-applicable) submit a 9-box VAT return with receipt
- [ ] Surfacing of HMRC error codes with user-friendly messages (use a deliberately-broken sandbox case to demonstrate)
- [ ] Digital-records attestation flow on every submission
- [ ] Audit-trail visibility — instructor can view their submission history with correlation IDs

### 5b. Public-facing documentation (required by HMRC application)

- [ ] Privacy policy URL — must specifically cover HMRC data handling, fraud-prevention header collection, NINO/UTR/VRN storage, and 6-year retention
- [ ] Terms & conditions URL — must cover MTD-specific responsibilities (instructor remains liable for figures, software is a conduit, attestation is binding)
- [ ] Support contact — published email + response-time SLA
- [ ] Help/FAQ page covering: connecting to HMRC, what data we collect, what to do if reconnect is needed, how to amend, where to view submissions, common error messages

### 5c. Production credentials & subscription

- [ ] Apply for production credentials on the HMRC developer hub (separate application from sandbox)
- [ ] Subscribe to all 14 APIs in production — subscriptions don't carry over from sandbox
- [ ] Confirm `HMRC_ENVIRONMENT=production` switching in `config/hmrc.php` resolves to the production URLs (already designed for in Phase 1a — verify it works)
- [ ] Production redirect URI registered with HMRC (real domain, not `.test`)
- [ ] Production client_id and client_secret added to production `.env` only — never committed, never copied into sandbox

### 5d. Operational monitoring

- [ ] Dashboard / log query for `hmrc_token_refresh_logs` — failure rate by user and overall
- [ ] Alert when token-refresh failure rate over the last 24h exceeds 1% (signals upstream HMRC outage or systemic config drift)
- [ ] Alert on `hmrc_itsa_obligations` sync failures (Phase 3f cron)
- [ ] Alert on submission failures with HMRC 5xx responses
- [ ] Daily digest of users whose `refresh_expires_at` is within 30 days — sanity check that the warning notifications from `MonitorHmrcTokenExpiry` are firing

### 5e. Support runbooks (`.claude/runbooks/hmrc/`)

- [ ] *Submission-failed* — how to look up correlation ID, replay request via tinker, contact HMRC dev support
- [ ] *Stuck-reconnecting* — token state inspection, manual disconnect/reconnect, `hmrc_token_refresh_logs` interpretation
- [ ] *HMRC-approval-rejected* — common reasons + remediation paths
- [ ] *Instructor-says-figures-are-wrong* — amendment flow for quarterly, "future-period adjustment" for VAT, no-amendment-after-final-declaration policy

### 5f. Verification

- [ ] HMRC production application submitted with MFS evidence + URLs
- [ ] Production approval received from HMRC
- [ ] Smoke test against production — ideally with one consenting real instructor and a low-stakes period
- [ ] Monitoring alerts proven by deliberately tripping each one in a controlled way

### Phase 5 Reflection

_(Filled in at end of phase 5, then write `.phase_done` sentinel)_

---

## Out of scope for this task (future work)

### 🔁 MUST RETURN TO — auto-derive ITSA categories + VAT 9-box from `instructor_finances` and `mileage_logs`
Manual entry is acceptable for v1 to ship, but is the single biggest UX gap. Driving instructors typing 12 expense category totals every quarter is a meaningfully worse experience than DRIVE doing it for them. **This is the next task after Phase 4 ships.** Scope:

**Common to both ITSA and VAT:**
- Add `vat_treatment` column to `instructor_finances` (`standard`, `reduced`, `zero_rated`, `exempt`, `outside_scope`, `reverse_charge`)
- Add `vat_rate` (decimal) and `vat_amount_pence` columns
- Build a `category_tax_mapping` config/table mapping each DRIVE expense category to `{ vat_treatment, itsa_bucket, claimable: bool }` so the existing `none`, `our_account`, `hmrc_tax`, `food_drink` etc. are filtered or routed correctly
- Add a `simplified_vs_actual` choice on the tax profile (per-vehicle, lifetime decision per HMRC rules)

**ITSA-specific:**
- Map each finance category to one of the 15 HMRC expense buckets (`carVanTravelExpenses`, `adminCosts`, `professionalFees`, etc.)
- For Simplified Expenses users: auto-compute business-mileage allowance (`business_miles × 45p` for first 10k, `× 25p` thereafter) → `carVanTravelExpenses`. Suppress fuel/insurance/MOT entries (otherwise it's double-claiming).
- For Actual Costs users: sum fuel/insurance/MOT/repairs apportioned by business-use percentage → `carVanTravelExpenses`.

**VAT-specific:**
- Sum input VAT (Box 4) from finance rows with `vat_treatment=standard` or `reduced`
- Sum output VAT (Box 1) from lesson income / payment_categories
- **Fuel scale charges** — HMRC's fixed-rate output VAT for private use of a fuel-claimed vehicle. Needs `vehicle_engine_size` on instructor profile and quarterly scale charge calculation
- VAT settings panel: registration date, scheme (Standard / Cash / Flat Rate), FRS rate if applicable

**Form behaviour:**
- Pre-populate Phase 3 / 3.5 / 4 submission forms with calculated values
- Always allow manual override
- Surface a clear "calculated from your DRIVE records" badge per field

### Other deferred items

- **Live VRN / VAT-number validation** — HMRC's `Check a UK VAT number` API confirms a VRN is real and returns the registered company name. Different auth model (app-restricted server-to-server, not user OAuth) — needs the API subscription added on the dev hub plus a server-token flow. v1 sticks to regex format check.
- **HMRC param-to-SA-box CSV reference** — HMRC publishes a CSV mapping of MTD API parameters to SA tax return box numbers at [github.com/hmrc/income-tax-mtd-changelog](https://github.com/hmrc/income-tax-mtd-changelog). Useful when implementing Phase 3.5; not deferred work itself. (Production approval has moved out of "future work" into Phase 5 above.)
- **MTD ITSA scopes outside our v1 surface:** capital gains, foreign income, employment income, property business, partner income, state benefits, charges, losses, tax-liability-adjustments, CIS deductions. Add as needed when client surfaces a real-world instructor case that hits one of these.
- **Property income alongside self-employment** — if any instructors also let property, they need `Property Business (MTD)` for the property quarterly updates. Driving instructor demographic suggests this is rare; revisit if customer evidence emerges.
- **Mobile API exposure** — confirmed not required v1; OAuth flow is browser-only by design and HMRC features live in the admin area only.
- **Open Banking pre-population for savings interest** — would let us auto-fill the Final Declaration savings income from connected bank accounts. Significant separate task (different regulator, different API ecosystem).

---

**Status:** Phase 1 ✅ complete (2026-04-27). Awaiting user verification (env values, migrations, `npm run dev`, browser round-trip) before Phase 1.5 begins.

**Last updated:** 2026-04-27 — Phase 1 implementation finished.
**Last Updated:** 2026-04-27
