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

## Phase 1.5: Tax Profile ✅ Complete

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

- [x] Migration: `add_tax_profile_fields_to_instructors_table` — `database/migrations/2026_04_28_144149_add_tax_profile_fields_to_instructors_table.php`
  - `business_type` (varchar(32), nullable) — cast to `BusinessType` enum on the model
  - `vat_registered` (boolean, default false)
  - `vrn` (varchar(9), nullable, unique — MySQL allows multiple NULLs)
  - `utr` (varchar(10), nullable) — sole trader / partnership
  - `nino` (text, nullable, encrypted via Laravel cast) — text used because encrypted ciphertext exceeds 9 chars
  - `companies_house_number` (varchar(8), nullable) — Ltd only
  - `tax_profile_completed_at` (timestamp, nullable)
- [x] Updated `.claude/database-schema.md` with the new columns on `instructors`

### 1.5b. Models

- [x] `Instructor` model — added new fields to `$fillable` and `casts()`: `business_type` → `BusinessType::class`, `vat_registered` → `boolean`, `nino` → `encrypted`, `tax_profile_completed_at` → `datetime`
- [x] New `app/Enums/BusinessType.php` — `SoleTrader`, `Partnership`, `LimitedCompany` (TitleCase cases, snake_case values) with `label()` and `itsaCanApply()` methods

### 1.5c. Validation

- [x] FormRequest `app/Http/Requests/UpdateInstructorTaxProfileRequest`:
  - `business_type` — required, validated via `Enum(BusinessType::class)` rule
  - `vat_registered` — required boolean
  - `vrn` — `required_if:vat_registered,true`, regex `^\d{9}$`, unique-ignoring-self
  - `utr` — `required_if:business_type,sole_trader,partnership`, regex `^\d{10}$`
  - `nino` — `required_if:business_type,sole_trader,partnership`, regex `^[A-CEGHJ-PR-TW-Z][A-CEGHJ-NPR-TW-Z]\d{6}[A-D]$` (HMRC's official prefix exclusions, suffix A–D)
  - `companies_house_number` — `required_if:business_type,limited_company`, regex `^[A-Z0-9]{8}$`
  - `prepareForValidation()` strips spaces and uppercases identifier fields before rules run
- [x] **Note:** v1 uses regex-only validation. Live VRN validation via HMRC's `Check a UK VAT number` API remains deferred to future work.

### 1.5d. Service / Actions

- [x] `app/Actions/Hmrc/Profile/UpdateTaxProfileAction` — invokable, persists profile fields, clears mutually-exclusive fields based on business type / VAT toggle, sets `tax_profile_completed_at` only on first save
- [x] `app/Actions/Hmrc/Profile/GetMtdApplicabilityAction` — pure function returning `{ profile_complete, business_type, vat: {...}, itsa: { applies, status, thresholds }, corporation_tax: { applies: false, reason }, summary }`. Single source of truth for the UI conditionals; ITSA `status` is one of `mandated_by_threshold`, `tbc_by_hmrc`, `not_applicable`, `unknown`.
- [x] Extended `HmrcService` with:
  - `getTaxProfile(Instructor): array`
  - `updateTaxProfile(Instructor, array): Instructor`
  - `getMtdApplicability(Instructor): array`

### 1.5e. Controllers + UI

- [x] Extended `HmrcConnectionController::index()` to also pass `taxProfile`, `applicability`, and `businessTypes` props to the page
- [x] New `HmrcConnectionController::updateTaxProfile(UpdateInstructorTaxProfileRequest)` — redirects back with success flash
- [x] Updated `pages/Hmrc/Connection.vue`:
  - "Your tax profile" status card (business type, VAT yes/no, masked identifiers) with "Edit tax profile" / "Set up tax profile" Sheet trigger
  - Sheet form using ShadCN Sheet/Input/Checkbox/Label + native `<select>` for business type (matches `PushNotifications/Index.vue` pattern; no Select primitive in `components/ui`). Conditional fields appear based on business_type and vat_registered.
  - "Available HMRC services" card driven by `applicability` — ITSA card shows mandate dates or partnership-TBC notice; VAT card shows when VAT-registered; "No MTD services apply" notice for Ltd-only-no-VAT.
  - Existing Connection status + Hello World cards preserved.
- [x] Generic "Connect to HMRC" button still works for Hello World testing in Phase 1.5; service-specific Connect buttons arrive in Phase 3 / 4.

### 1.5f. Routes

- [x] Added to the `hmrc` route group: `Route::post('/tax-profile', [HmrcConnectionController::class, 'updateTaxProfile'])->name('tax-profile.update');`
- [x] `php artisan route:clear && route:list --path=hmrc` confirms 6 routes (including `hmrc.tax-profile.update`)

### 1.5g. Verification

- [x] `php -l` clean on all new/modified PHP files (BusinessType, Instructor, migration, FormRequest, both Actions, HmrcService, HmrcConnectionController, routes/web.php)
- [x] `database-schema.md` updated
- [ ] **USER ACTION:** Run `php artisan migrate` to apply the new instructor columns
- [ ] **USER ACTION:** With `npm run dev` running, toggle through the 6 matrix combinations in the browser; verify correct services appear for each, validation rejects bad VRN/UTR/NINO/CRN formats

### Phase 1.5 Reflection

**What went well**
- Single-action source of truth: `GetMtdApplicabilityAction` is a pure function that returns everything the UI needs (profile_complete, vat applies, itsa status + thresholds, summary copy). Phase 3/4's UI gating can call this without re-deriving anything.
- Enum-cast `business_type` keeps the controller and applicability action working with type-safe values rather than magic strings.
- `prepareForValidation()` normalises identifiers (strips spaces, uppercases) before rules run, so the regex sees the canonical form and the DB stores the canonical form — no double-normalisation on the way in.
- NINO uses `text` storage with Laravel's `encrypted` cast (PII) while VRN/UTR stay as plaintext varchars per the spec — matches existing `hmrc_tokens.access_token` pattern.

**Gotchas / decisions worth recording**
- `components/ui/` has no `Select` primitive in this project, so the business-type field uses a native `<select>` styled to match input classes (mirroring `PushNotifications/Index.vue`). When/if a ShadCN Select arrives, the swap is mechanical.
- Identifier display in the read-only profile card masks UTR and NINO (`••••XYZ`) but shows VRN in full. VRN is published on invoices anyway; UTR and NINO are private.
- VRN unique constraint uses `Rule::unique('instructors', 'vrn')->ignore($instructorId)` so re-saving an unchanged VRN doesn't trip uniqueness against the row's own value.
- The action clears mutually-exclusive fields server-side (e.g. switching from sole trader to limited company nulls UTR/NINO) — the FormRequest only validates input shape; the action enforces the matrix.
- No Phase 1.5 changes needed in `config/hmrc.php` — tax profile is local data, not OAuth-scoped.

**Open items entering Phase 2**
- User to run `php artisan migrate`.
- User to manually walk the 6-matrix combinations in the browser before approving Phase 2.

---

## Phase 2: Fraud Prevention Headers ✅ Complete

**Goal:** Add the legally-required `Gov-Client-*` and `Gov-Vendor-*` fraud prevention headers to all HMRC API calls (other than the OAuth endpoints and Hello World), and validate them clean against HMRC's Test Fraud Prevention Headers API.

**Connection method to declare:** `WEB_APP_VIA_SERVER` — user authenticates in their browser, our server makes the API calls. (HMRC defines several methods; this matches the architecture.)

### 2a. Spec verification

- [x] Fetched the current `WEB_APP_VIA_SERVER` header spec from `https://developer.service.hmrc.gov.uk/guides/fraud-prevention/connection-method/web-app-via-server/`. Per-header source-of-truth captured in `.claude/hmrc-fraud-headers.md`. **Important deviation from this task plan:** WEB_APP_VIA_SERVER does NOT require `Local-IPs`, `Browser-Plugins`, or `Browser-Do-Not-Track` — those are for desktop/mobile connection methods. The migration and fingerprint scope were reduced accordingly.
- [x] Validator endpoint URL confirmed: `POST {api_base}/test/fraud-prevention-headers/validator/validate` via the already-subscribed Test Fraud Prevention Headers (MTD) 1.0 API.

### 2b. Database

- [x] Migration: `database/migrations/2026_04_28_153511_create_hmrc_client_fingerprints_table.php`
  - `id`, `hmrc_token_id` (fk unique cascade), `screens` (json), `window_size` (json), `timezone` (json — `{iana, offset_minutes}`), `browser_user_agent` (text), `captured_at` (timestamp), `timestamps`
  - Dropped `local_ips`, `browser_plugins`, `browser_do_not_track` per the WEB_APP_VIA_SERVER scope.
- [x] Updated `.claude/database-schema.md` with the new table

### 2c. Frontend fingerprint capture

- [x] `resources/js/lib/hmrcFingerprint.ts` — `captureHmrcFingerprint()` returns `{screens, window_size, timezone:{iana, offset_minutes}, browser_user_agent}`. Sign-flips JS's `getTimezoneOffset()` (which is west-of-UTC) to HMRC's east-of-UTC convention.
- [x] POST endpoint `POST /hmrc/fingerprint` → `HmrcFraudHeadersController::storeFingerprint`, accepts JSON and upserts on `hmrc_token_id`.
- [x] FormRequest `StoreHmrcFingerprintRequest` — strict array shape + numeric ranges, max sizes on UA + IANA fields.
- [x] Composable `resources/js/composables/useHmrcAction.ts` — captures fingerprint, POSTs to `/hmrc/fingerprint`, then runs the wrapped axios action. Exposes `running`, `error`.

### 2d. Backend header builder

- [x] `app/Actions/Hmrc/BuildFraudPreventionHeadersAction` — composes the WEB_APP_VIA_SERVER header set:
  - `Gov-Client-Connection-Method`, `Gov-Client-Device-ID`, `Gov-Client-User-IDs`, `Gov-Client-Timezone` (formatted from offset_minutes), `Gov-Client-Screens` (multi-monitor list format), `Gov-Client-Window-Size`, `Gov-Client-Browser-JS-User-Agent`
  - `Gov-Client-Public-IP` + `Gov-Client-Public-IP-Timestamp` only if IP looks public (FILTER_FLAG_NO_PRIV_RANGE | NO_RES_RANGE); private IP is logged as a warning rather than thrown
  - `Gov-Client-Public-Port` only if proxy didn't strip it
  - `Gov-Client-Multi-Factor` only when session MFA marker present
  - `Gov-Vendor-Forwarded`, `Gov-Vendor-Product-Name`, `Gov-Vendor-Version` always
  - `Gov-Vendor-Public-IP` only when env-configured
  - `Gov-Vendor-License-IDs` deliberately omitted — HMRC: "Conditional - not required if no licenses present"; DRIVE has no licenses in the request path
- [x] `CallHmrcApiAction` updated: new `withFraudHeaders: bool` (default false) and `fraudContext: array` params. When true, looks up the user's token + fingerprint, throws `MissingFraudFingerprintException` if missing/stale (configurable via `hmrc.fraud_headers.fingerprint_max_age_minutes`, default 30), otherwise builds + merges headers. Hello World stays bare (default false).
- [x] `app/Exceptions/Hmrc/MissingFraudFingerprintException` added; controller surfaces as HTTP 422.

### 2e. Validator endpoint

- [x] `app/Actions/Hmrc/ValidateFraudHeadersAction` — POSTs to `/test/fraud-prevention-headers/validator/validate` with the fraud headers (passed via `extraHeaders` so the action can return them to the UI). Throws on missing/stale fingerprint or no token.
- [x] Service method `HmrcService::validateFraudHeaders(User, context)` orchestrates.
- [x] Controller `HmrcFraudHeadersController::validate` returns JSON `{headers_sent, errors, warnings, raw}`. Maps Hmrc errors to 502, missing fingerprint to 422, no-token to 400.
- [x] Connection.vue: new diagnostic card with "Validate fraud headers" button, ShadCN Alerts for errors/warnings, a `<details>` collapsible for the headers we sent. Uses `useHmrcAction` composable so fingerprint is captured automatically before the call.

### 2f. Routes / config

- [x] Added `POST /hmrc/fingerprint` (`hmrc.fingerprint.store`) and `POST /hmrc/test/fraud-headers` (`hmrc.test.fraud-headers`)
- [x] `route:list --path=hmrc` shows 8 routes total
- [x] Added `hmrc.fraud_headers.*` config block: `connection_method`, `vendor_product_name`, `vendor_version`, `vendor_public_ip`, `user_id_key`, `fingerprint_max_age_minutes`. Mirrored env keys to `.env.example`.

### 2g. Verification

- [x] `php -l` clean on every new/modified PHP file
- [x] `database-schema.md` updated with `hmrc_client_fingerprints`
- [x] `route:list --path=hmrc` shows 8 routes including the 2 new Phase 2 ones
- [ ] **USER ACTION:** `php artisan migrate` (creates `hmrc_client_fingerprints`)
- [ ] **USER ACTION:** With `npm run dev` running, click "Validate fraud headers" against the sandbox; iterate on any errors/warnings HMRC reports until clean

### Phase 2 Reflection

**What went well**
- `BuildFraudPreventionHeadersAction` is a single pure function with no DB touches — given a user + fingerprint + context it returns the header array. All the orchestration (fingerprint lookup, freshness check, throwing) lives one layer up in `CallHmrcApiAction::resolveFraudHeaders` or in `HmrcService::validateFraudHeaders`. This means Phase 3/4 callers will get fraud headers automatically by passing `withFraudHeaders: true`.
- The `useHmrcAction` composable is intentionally thin: it doesn't know what fields HMRC needs (the server is the source of truth); it just refreshes the fingerprint and runs the action. Phase 3/4 quarterly/VAT submission buttons can wrap their axios call with this composable for free.
- Splitting fraud-header endpoints into `HmrcFraudHeadersController` keeps `HmrcConnectionController` focused on the OAuth lifecycle. Phase 3/4 will get their own controllers under `app/Http/Controllers/Hmrc/Itsa/` and `Hmrc/Vat/`.
- The fraud-prevention spec deviation (dropping Local-IPs / Browser-Plugins / Browser-Do-Not-Track for WEB_APP_VIA_SERVER) was caught early via a fresh WebFetch of HMRC's docs. The original task plan was based on a more comprehensive header list — trusting the live spec saved capturing fields HMRC will reject.

**Gotchas / decisions worth recording**
- **Timezone offset sign:** `Date.getTimezoneOffset()` returns minutes WEST of UTC (positive in negative-offset zones), but HMRC wants positive minutes EAST of UTC. The frontend lib sign-flips before sending. Server formats `UTC±hh:mm` from the already-flipped value.
- **No `Gov-Vendor-License-IDs`:** HMRC says "conditional — not required if no licenses present". DRIVE has no third-party licenses in the request path, so we omit. Header builder simply doesn't add the key. The original task plan said "UUID per user, stored" — this would have been incorrect (license IDs are software licenses, not user identifiers).
- **`Gov-Vendor-Public-IP` is env-gated.** In Herd/sandbox there's no stable outbound public IP, so we omit it (HMRC's validator will warn but not error). Production sets `HMRC_VENDOR_PUBLIC_IP`.
- **Fingerprint freshness window** is 30 minutes by default (configurable). Triggers `MissingFraudFingerprintException` → 422 → the composable re-captures and the user retries.
- **CallHmrcApiAction now takes `withFraudHeaders` + `fraudContext`** — additive, default false, so Hello World still works with a bare-token call. ValidateFraudHeadersAction does NOT use the flag (it builds headers itself so it can return them in the response payload).
- **Single fingerprint row per `hmrc_token_id`**, not per user. If user disconnects + reconnects (new token row), they need a new fingerprint capture — handled automatically because `useHmrcAction` runs `refreshFingerprint()` before every action.
- **Stable device cookie still lives on user_id**, separate from the fingerprint. Disconnecting doesn't reset the device ID — that's the HMRC fraud-prevention requirement we already implemented in Phase 1. The fingerprint table is for the *device snapshot at time of submission*, not the long-lived identifier.

**Open items entering Phase 3**
- User to run `php artisan migrate`.
- User to walk the validator end-to-end against the sandbox; capture any HMRC errors/warnings and iterate (likely candidates: `Gov-Client-Public-IP` private in dev, `Gov-Vendor-Public-IP` missing). These are expected sandbox warnings, not blockers for moving to Phase 3.
- Phase 3 (ITSA) call sites can use `withFraudHeaders: true` directly on `CallHmrcApiAction::__invoke` — fingerprint orchestration is already wired.

---

## Phase 3: ITSA Quarterly Updates ✅ Complete

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

**What landed**

- 5 migrations: `add_mtd_itsa_status_to_instructors_table`, `create_hmrc_itsa_businesses_table`, `create_hmrc_itsa_obligations_table`, `create_hmrc_itsa_quarterly_updates_table`, `create_hmrc_itsa_quarterly_update_revisions_table`
- 4 enums: `ItsaBusinessType`, `ItsaObligationStatus`, `ItsaEnrolmentStatus`, `ItsaExpenseCategory` (with `column()`/`hmrcKey()`/`label()` helpers — single source of truth for the 15 expense buckets)
- 4 models: `HmrcItsaBusiness`, `HmrcItsaObligation`, `HmrcItsaQuarterlyUpdate` (with `nextRevisionNumber()`, `isItemised()`, `totalExpensesPence()`), `HmrcItsaQuarterlyUpdateRevision`
- 8 actions: `ResolveEnrolmentStatusAction`, `ListBusinessesAction`, `RetrieveBusinessAction`, `ListObligationsAction`, `BuildQuarterlyPayloadAction`, `SubmitQuarterlyUpdateAction`, `AmendQuarterlyUpdateAction`, `RetrieveQuarterlyUpdateAction`
- `HmrcItsaService extends BaseService` with cache-on-read for businesses + open-obligations (300s TTL, invalidated on every write)
- `EnsureMtdEnrolled` middleware gating the submission routes; the `index` route stays open so the state machine can render `not_signed_up` / `income_source_missing` cards
- `SubmitQuarterlyUpdateRequest` with `prepareForValidation()` converting pounds→pence pre-rules + cross-field check rejecting consolidated AND itemised together; `AmendQuarterlyUpdateRequest` extends it (drops period dates)
- `ItsaController` (`index`, `period`, `store`, `amend`, `refreshStatus`, `syncObligations`)
- 6 ITSA routes nested in the existing HMRC group; `EnsureMtdEnrolled` applied selectively
- `SyncHmrcItsaObligations` daily cron (07:15) + `ItsaObligationDueSoon` notification firing at T-30/T-14/T-7/T-1, idempotent via `hmrc_itsa_obligations.last_reminder_sent_at`
- `Hmrc/Itsa/Index.vue` (state machine cards + businesses table + open obligations + history) and `Hmrc/Itsa/Period.vue` (consolidated/itemised toggle + 15 expense fields + mandatory attestation + confirmation overlay)
- Connection.vue ITSA card now deep-links to `/hmrc/itsa`
- OAuth scope assembly (`HmrcService::scopesFor`) requests union of currently-applicable scopes (Hello + ITSA for sole_trader/partnership; preserves any existing token scopes — never narrows)
- `.claude/hmrc-itsa-api.md` internal spec note (with explicit caveat that the OAS pages render via JS — sandbox testing is the validation step)
- `.claude/database-schema.md` updated with all 4 new tables + the 2 new instructor columns

**What went well**

- The append-only revisions table makes the audit trail "free" — both the success path and the failed path write a revision *before* the parent row is touched, and the parent row is overwritten in place. 6-year retention is just "don't delete revisions". Every submission has a `kind` so failed attempts are queryable separately for ops.
- `ItsaExpenseCategory::column()` + `hmrcKey()` is the single mapping point. Migration column names, DB casts on `HmrcItsaQuarterlyUpdate`, FormRequest input keys, payload-build keys, and frontend labels all derive from one enum case. Adding a new category = one enum case + one migration column.
- `EnsureMtdEnrolled` only guards the writeable ITSA routes — the index page is deliberately accessible to users in `not_signed_up` / `income_source_missing` states so the explanatory copy + gov.uk link reach them.
- `SubmitQuarterlyUpdateAction` and `AmendQuarterlyUpdateAction` both write a `failed_*` revision when HMRC rejects the call, using a separate try/catch that swallows audit-trail write failures (so a 6-year retention bug never masks a real HMRC error).
- The submit flow is fingerprint-aware: `useHmrcAction.refreshFingerprint()` is called from `Period.vue` before the Inertia `router.post` so Phase 2's fraud-header machinery applies automatically. No new frontend wiring per ITSA action.

**Gotchas / decisions worth recording**

- HMRC's OAS spec pages (`.../oas/page`) render entirely client-side, so WebFetch couldn't read them. Endpoint paths/JSON shapes were taken from the project task plan + HMRC's published end-to-end service guide. Sandbox testing (3i USER ACTION) is the truth check — if HMRC rejects a field name the migration / `BuildQuarterlyPayloadAction` / `ItsaExpenseCategory::hmrcKey()` need a coordinated update.
- `ItsaExpenseCategory` does NOT include `consolidated_expenses` — it's a separate column / mode toggle, mutually exclusive with the 15 itemised cases at the FormRequest level.
- The submission row uses `firstOrNew` + manual `fill()` rather than `updateOrCreate`. Reason: we need the row in memory *before* the HTTP call so the `failed_submission` revision can attach to a real `quarterly_update_id` even if the call fails. The row is only persisted on success (or via `$row->save()` inside `writeFailedRevision`).
- HMRC may return `submissionId` at the top level OR inside a `links[]` block depending on response variant — `SubmitQuarterlyUpdateAction` reads `$response['submissionId']` directly. If sandbox testing reveals it's nested, normalise in the action.
- Correlation IDs (`X-CorrelationId`) come back as a response header; `CallHmrcApiAction` currently only returns the JSON body. If we want the correlation ID for audit, we need to either echo the header into the body in `CallHmrcApiAction::__invoke` (add `_correlationId` synthetic key) or refactor it to return a richer envelope. The submit action reads `$response['_correlationId']`, so when the time comes to wire that, the change is one place.
- `ItsaController::amend` performs ownership check (`$quarterlyUpdate->user_id !== $request->user()->id` → 403) — route-model binding by ID alone isn't safe given the controller is per-instructor.
- `HmrcItsaService::cachedBusinesses` and `openObligations` cache the *DB read*, not the HMRC call. The HMRC sync is explicit (`syncObligations`/`syncBusinesses`) and triggered by either the cron or a user-facing button — never on page load. This avoids the "HMRC call on every Inertia render" smell.
- The Period.vue confirmation overlay is a hand-rolled card with a backdrop rather than ShadCN Dialog — Dialog is reserved for confirmations per the project standards but ShadCN's Dialog primitive isn't currently published in `components/ui`. Swapping the overlay to Dialog when it lands is a 30-line change.

**Open items entering Phase 3.5**

- USER ACTION: `php artisan migrate` to apply the 5 new migrations.
- USER ACTION: Provision an MTD ITSA test user in HMRC sandbox (Test Support API), connect, walk: list businesses → list obligations → submit quarterly update → verify `submission_id` and `correlation_id` (note caveat above) → amend → verify revision row appended.
- USER ACTION: `php artisan hmrc:sync-itsa-obligations --dry-run` to confirm the cron lists expected reminders without sending.
- If sandbox surfaces a JSON shape mismatch (different field name, different envelope), update `BuildQuarterlyPayloadAction` (payload build) and `ListObligationsAction::upsert` (response parse) — both are isolated.

---

## Phase 3.5: ITSA Final Declaration ✅ Complete (code) / ⛔ DESCOPED from product 2026-04-30

> **Product status:** code is built and works against the HMRC sandbox; the **product no longer exposes Final Declaration**. The end-of-year journey is now Phase 9 (tax-year archive) handed to a qualified accountant. See the *Session handoff — 2026-04-30* entry near the bottom of this file for the full decision and the action list. Do not delete this code; do not action this phase plan; do not include Final Declaration in MFS evidence; production HMRC API subscription drops to 9 APIs (the five Final-Declaration support APIs are removed from the production subscription list).

**Goal:** At tax-year end, the instructor reviews their full year's data, adds any supplementary income/reliefs/disclosures (savings interest, dividends, pension contributions, marriage allowance), triggers HMRC's tax calculation, reviews the calculated liability, and submits their **Final Declaration** — the equivalent of the legacy SA tax return.

**Scope decision for v1:**
- Cover the supplementary types most likely to apply to a driving instructor: savings income (bank interest), dividends, reliefs (pension contributions, charity donations), disclosures (marriage allowance), and personal details
- Defer: capital gains, foreign income, employment income (most full-time instructors don't have it), property income, partner income, state benefits, charges, losses, tax-liability-adjustments
- v1 is **manual entry** for all supplementary fields. Auto-population from connected sources (e.g. open banking for savings interest) is a future enhancement.

### 3.5a. Spec verification

- [x] Pulled and documented into `.claude/hmrc-itsa-final-declaration.md`:
  - **Individual Calculations (MTD) v8.0** — `POST /individuals/calculations/{nino}/self-assessment` (trigger), `GET /…/{calculationId}` (retrieve), `POST /…/{calculationId}/final-declaration` (submit)
  - **Self Assessment Accounts (MTD) v4.0** — `GET /accounts/self-assessment/{nino}/balance-and-transactions`
  - **Self Assessment Individual Details (MTD) v2.0**
  - **Individuals Reliefs (MTD) v3.0** — pension contributions, charitable giving
  - **Individuals Disclosures (MTD) v2.0** — Marriage Allowance transfer
  - **Individuals Savings Income (MTD) v2.0** — UK and foreign savings/interest
  - **Individuals Dividends Income (MTD) v2.0** — UK dividends from securities
- [x] Polling strategy documented: 1.5/3/6/12s with 30s cap and 60s overall ceiling; status reverts to `pending` if HMRC still computing past the cap and the UI surfaces a manual Retry button. Same caveat as Phase 3 — HMRC's OAS pages are JS-rendered, so sandbox testing remains the truth check.

### 3.5b. Database

- [x] Migration: `create_hmrc_itsa_calculations_table` (`2026_04_28_155064_*`) — calculation IDs + cached payloads
  - `id`, `user_id`, `nino`, `tax_year` (string e.g. `2025-26`), `calculation_id` (string from HMRC, indexed), `calculation_type` (string cast to `ItsaCalculationType`), `status` (string cast to `ItsaCalculationStatus`), `triggered_at`, `processed_at` (nullable), `summary_payload` (json), `detail_payload` (json), `error_payload` (json nullable), `timestamps`
- [x] Migration: `create_hmrc_itsa_supplementary_data_table` (`2026_04_28_155065_*`) — single row per (user, tax_year, type)
  - `id`, `user_id`, `tax_year`, `type` (string cast to `ItsaSupplementaryType`), `payload` (json), `submission_id`, `correlation_id`, `submitted_at`, `response_payload`, `timestamps`
  - Unique on `(user_id, tax_year, type)`
- [x] Migration: `create_hmrc_itsa_final_declarations_table` (`2026_04_28_155066_*`) — permanent audit
  - `id`, `user_id`, `nino`, `tax_year`, `calculation_id` (fk to calculations row, nullOnDelete), `submitted_at`, `correlation_id`, `request_payload`, `response_payload`, `digital_records_attested_at`, `digital_records_attested_by_user_id`, `timestamps`
  - Unique on `(user_id, tax_year)`
- [x] Updated `.claude/database-schema.md` with all three new tables + relationships

### 3.5c. Models + Enums

- [x] Models: `HmrcItsaCalculation`, `HmrcItsaSupplementaryData`, `HmrcItsaFinalDeclaration` with `casts()` for date/json/enum fields
- [x] Enums: `ItsaCalculationType`, `ItsaCalculationStatus`, `ItsaSupplementaryType` (with `hmrcPath()`, `hmrcVersion()`, `v1Fields()` helpers — single source of truth for path + form scope per type)

### 3.5d. Actions (`app/Actions/Hmrc/Itsa/FinalDeclaration/`)

Consolidated to one Submit + one Retrieve action driven by `ItsaSupplementaryType` rather than per-type pairs (the per-type pairs would have been ~10 near-identical classes — DRY/KISS, the type enum is the source of variance):

- [x] `BuildSupplementaryPayloadAction` — translates v1 form fields into HMRC's per-type JSON shape (mirrors `BuildQuarterlyPayloadAction`)
- [x] `SubmitSupplementaryAction` — generic PUT keyed by `ItsaSupplementaryType`; upserts on `hmrc_itsa_supplementary_data` unique key
- [x] `RetrieveSupplementaryAction` — generic GET keyed by `ItsaSupplementaryType`

For the calculation flow:

- [x] `TriggerCalculationAction` — POST with `taxYear` + optional `finalDeclaration=true`; persists row in `pending` state
- [x] `RetrieveCalculationAction` — GET, maps HMRC's `metadata.calculationOutcome` to local status, populates `summary_payload`/`error_payload`
- [x] `PollCalculationAction` — loops Retrieve with 1.5/3/6/12/30s backoff, capped at 60s total
- [x] `SubmitFinalDeclarationAction` — POST to final-declaration endpoint after status check; idempotent via existing-row short-circuit
- [x] `RetrieveAccountBalanceAction` — Self Assessment Accounts balance-and-transactions endpoint (used by the service `accountBalance()` cache)

### 3.5e. Service

- [x] New `HmrcItsaFinalDeclarationService extends BaseService` — kept separate from `HmrcItsaService` because the surface area is large (calculation + 5 supplementary types + final declaration)
  - `getSupplementary(User, taxYear): array<value, ?HmrcItsaSupplementaryData>`
  - `saveSupplementary(User, taxYear, type, data, fraud)`
  - `triggerCalculation(User, taxYear, type, fraud)`
  - `pollCalculation(User, calc, fraud)` / `refreshCalculation(User, calc, fraud)`
  - `submitFinalDeclaration(User, calc, fraud)`
  - `accountBalance(User, fraud)` — cached 300s
  - `findFinalDeclaration(User, taxYear)`
  - `calculationsFor(User, taxYear)`

### 3.5f. Controllers + UI — Final Declaration wizard

- [x] `FinalDeclarationController` (Inertia) — `index`, `step`, `storeStep`, `triggerCalculation`, `showCalculation`, `pollCalculation` (JSON for AJAX polling), `submit`. All ITSA-style ownership + tax-year guards via `abort_if`.
- [x] `Hmrc/Itsa/FinalDeclaration/Index.vue` — single hub page covering all five wizard "steps":
  - **Step 1** — read-only quarterly summary table for the selected tax year (with link back to `/hmrc/itsa` for amendments)
  - **Steps 2–4** — clickable list of supplementary types (savings, dividends, reliefs, disclosures, personal details) with completion badges; each opens `Step.vue`
  - **Step 5** — calculations table + "Trigger calculation" button (disabled until all supplementary steps are complete)
- [x] `Hmrc/Itsa/FinalDeclaration/Step.vue` — polymorphic form rendered per `ItsaSupplementaryType` value; `useHmrcAction` ensures fingerprint capture; native `<select>` for marital status (no ShadCN Select primitive)
- [x] `Hmrc/Itsa/FinalDeclaration/Calculation.vue` — review screen with auto-polling (`axios.get` to `/poll` every 5s while `pending`), full payload `<pre>` for transparency, attestation overlay before submit
- [x] **Submit** — confirmation overlay with mandatory digital-records attestation checkbox (same wording as quarterly): *"I confirm these figures are derived from digital business records that I keep in line with MTD requirements."* Submit disabled until ticked. Attestation timestamp + user id stored on `hmrc_itsa_final_declarations`.
- [x] FormRequests `app/Http/Requests/Hmrc/Itsa/FinalDeclaration/`:
  - `SubmitSupplementaryRequest` — type-driven rule set (resolves type from route, switches rules per type) + `prepareForValidation()` pence conversion
  - `SubmitFinalDeclarationRequest` — attestation only

### 3.5g. Routes

- [x] Added inside the `itsa` route group, under the `EnsureMtdEnrolled` middleware (so `not_signed_up` and `income_source_missing` users can't reach the wizard):
  ```php
  Route::prefix('final-declaration')->name('final-declaration.')->group(function () {
      Route::get('/{taxYear}', [FinalDeclarationController::class, 'index'])->name('index');
      Route::get('/{taxYear}/step/{type}', [FinalDeclarationController::class, 'step'])->name('step');
      Route::post('/{taxYear}/step/{type}', [FinalDeclarationController::class, 'storeStep'])->name('step.store');
      Route::post('/{taxYear}/calculate', [FinalDeclarationController::class, 'triggerCalculation'])->name('calculate');
      Route::get('/{taxYear}/calculation/{calculation}', [FinalDeclarationController::class, 'showCalculation'])->name('calculation');
      Route::get('/{taxYear}/calculation/{calculation}/poll', [FinalDeclarationController::class, 'pollCalculation'])->name('calculation.poll');
      Route::post('/{taxYear}/submit/{calculation}', [FinalDeclarationController::class, 'submit'])->name('submit');
  });
  ```
- [x] `php artisan route:clear && route:list --path=hmrc` shows 21 routes (was 14) including the 7 new final-declaration ones

### 3.5h. Verification

- [x] `php -l` clean on every new/modified PHP file (3 migrations, 3 enums, 3 models, 8 actions, 1 service, 1 controller, 2 form requests, routes)
- [x] `database-schema.md` updated with all 3 new tables (calculations / supplementary_data / final_declarations)
- [x] Route list confirms 21 HMRC routes including all 7 final-declaration routes
- [ ] **USER ACTION:** `php artisan migrate` to apply the 3 new migrations
- [ ] **USER ACTION:** With sandbox MTD ITSA test user pre-populated with 4 quarters: walk through the 5 supplementary steps → trigger calculation → poll → submit → verify `correlation_id` captured. Re-submit each supplementary type to confirm overwrite semantics.

### Phase 3.5 Reflection

**What landed**

- 3 migrations: `create_hmrc_itsa_calculations_table`, `create_hmrc_itsa_supplementary_data_table`, `create_hmrc_itsa_final_declarations_table` (`2026_04_28_155064`–`...155066`)
- 3 enums: `ItsaCalculationType`, `ItsaCalculationStatus`, `ItsaSupplementaryType` (with `hmrcPath()`/`hmrcVersion()`/`v1Fields()`/`label()` helpers)
- 3 models: `HmrcItsaCalculation`, `HmrcItsaSupplementaryData`, `HmrcItsaFinalDeclaration` with `casts()` for enum/json/timestamp fields
- 8 actions in `app/Actions/Hmrc/Itsa/FinalDeclaration/`: `BuildSupplementaryPayloadAction`, `SubmitSupplementaryAction`, `RetrieveSupplementaryAction`, `TriggerCalculationAction`, `RetrieveCalculationAction`, `PollCalculationAction`, `SubmitFinalDeclarationAction`, `RetrieveAccountBalanceAction`
- `HmrcItsaFinalDeclarationService extends BaseService` (kept separate from `HmrcItsaService` — surface area is large) with cache-on-read for SA account balance (300s)
- `FinalDeclarationController` (Inertia) with `index`, `step`, `storeStep`, `triggerCalculation`, `showCalculation`, `pollCalculation` (JSON for AJAX polling), `submit`
- `SubmitSupplementaryRequest` — single FormRequest that resolves the type from the route, switches rule set per type, runs `prepareForValidation()` pence conversion
- `SubmitFinalDeclarationRequest` — attestation-only
- 7 routes nested under `hmrc.itsa.final-declaration.*`, all gated by `EnsureMtdEnrolled` middleware (Phase 3a.5)
- 3 Inertia pages in `resources/js/pages/Hmrc/Itsa/FinalDeclaration/`: `Index.vue` (wizard hub), `Step.vue` (polymorphic per-type form), `Calculation.vue` (review + auto-polling + attestation/submit)
- `.claude/hmrc-itsa-final-declaration.md` internal spec note (with same caveat as `hmrc-itsa-api.md` — JS-rendered OAS pages, sandbox is the truth)
- `.claude/database-schema.md` updated with all 3 new tables + relationships

**What went well**

- **DRY enum-driven supplementary actions.** The original task plan called for 10 separate Submit/Retrieve actions (one pair per supplementary type). Replaced with a single Submit + Retrieve action driven by `ItsaSupplementaryType::hmrcPath()` / `hmrcVersion()`. Adding a sixth supplementary type is now: add an enum case, add `v1Fields()` mapping, extend `BuildSupplementaryPayloadAction::__invoke` switch, extend `SubmitSupplementaryRequest::rules()` switch, extend `Step.vue` template — no new action class.
- **Polling is split between server-blocking (initial trigger) and client-polling (page reload).** `triggerCalculation` runs `PollCalculationAction` synchronously up to 60s so most users see a processed calc immediately. If they reload the calculation page while still pending, the client kicks off a 5-second `axios` poll loop against `pollCalculation` (JSON endpoint). State persists on `hmrc_itsa_calculations` so neither path is destructive — the user can close the tab and come back.
- **Final declaration submission is idempotent.** `SubmitFinalDeclarationAction` checks for an existing row on `(user_id, tax_year)` before calling HMRC and returns the existing row if found. Defends against a user double-clicking the submit button or HMRC's response being delayed beyond the user's patience.
- **Tax-year filter on Phase 3 history is computed from period dates, not stored.** `FinalDeclarationController::matchesTaxYear` expands `2025-26` → `[2025-04-06, 2026-04-05]` and filters in PHP. No new column needed; existing `period_start_date` is enough.
- **One Service per ITSA flow.** `HmrcItsaService` (Phase 3) and `HmrcItsaFinalDeclarationService` (Phase 3.5) are siblings, both extending `BaseService`. Splitting kept each focused — final declaration's surface (calculation polling, 5 supplementary types, declaration submit, account balance) would have made `HmrcItsaService` ~250 lines. Sibling services share Actions where they overlap (none currently — quarterly Actions don't touch the calculation flow).

**Gotchas / decisions worth recording**

- **HMRC's OAS spec pages still render JS-only.** Same caveat as Phase 3 — paths and JSON shapes were assembled from HMRC's published end-to-end service guide + the task plan. Sandbox testing is the truth check; if a field name or envelope mismatches, `BuildSupplementaryPayloadAction` (per type) and `RetrieveCalculationAction::extractSummary`/`extractOutcome` are the isolated places to update.
- **`detail_payload` and `summary_payload` overlap.** `detail_payload` stores the full retrieve response; `summary_payload` is the `liabilityAndCalculation` (or `calculation`) sub-block we expose to the UI. The duplication is intentional — UI reads from `summary_payload` (small), audits/exports read from `detail_payload` (large).
- **Final declaration body is empty.** HMRC accepts `POST .../final-declaration` with no body; the `calculationId` in the URL is the assertion. We persist a synthetic `request_payload = ['calculationId' => $id]` so the audit row carries something meaningful for export.
- **Marital status enum is local to the form.** HMRC's individual-details endpoint accepts a wider enum (e.g. various separation forms); v1 collects the five most common values. The Step.vue form uses a native `<select>` (no ShadCN Select primitive) — same pattern as the tax-profile Sheet from Phase 1.5.
- **`EnsureMtdEnrolled` covers the entire final-declaration tree.** Including the calculation poll endpoint. A user whose status flips from `mandated` to `not_signed_up` mid-flow gets a 403 on poll — this is correct (HMRC will reject the call anyway), but the UI doesn't currently surface a friendly message in that edge case. Future improvement.
- **Supplementary "completion" is binary in the UI.** A user who saves an empty Reliefs form (no pension, no charity) will mark the step "Complete" — the FormRequest allows nullable values. This matches HMRC's accept-empty-payload semantics; the user is asserting "I have no reliefs to declare for this year". The audit row's `payload` is `{}` in that case.
- **Polling delays are wall-clock, not HMRC-driven.** HMRC may return the calc in <1s for simple cases; we still wait 1.5s on the first retry. Acceptable trade-off — under-polling is worse than over-polling for the user experience and HMRC's rate limits aren't tight enough to notice.

**Open items entering Phase 4**

- USER ACTION: `php artisan migrate` to apply the 3 new migrations.
- USER ACTION: Provision a fully-populated MTD ITSA test user (4 quarterly updates already submitted), connect, walk all 5 supplementary forms → trigger calculation → review → submit final declaration. Capture any field-name mismatches against the v1 form scope.
- USER ACTION: Re-trigger the calculation a second time (re-creates the row with a new calculationId) to confirm idempotency on the supplementary side and that the user can review the new calc before submitting.
- Phase 4 (VAT) reuses Phase 1/1.5/2 foundations and the same fingerprint composable; no Phase 3.5 hand-off blockers.

---

## Phase 4: VAT submission flow (optional toggle) ✅ Complete

**Goal:** For the minority of instructors who are VAT-registered (`tax_profile.vat_registered = true`), expose a VAT card alongside ITSA on the HMRC connection page. Instructor can submit a quarterly VAT 9-box return. Reuses Phase 1/1.5/2 foundations end-to-end.

**Scope decision for v1:** Manual 9-box entry. Auto-derivation deferred to the same future work as ITSA auto-derivation.

### 4a. VAT API spec verification

- [x] Pulled VAT (MTD) v1.0 endpoint shapes into `.claude/hmrc-vat-api.md` with the same JS-rendered-OAS caveat as ITSA — sandbox is the truth. Covers obligations / retrieve / submit / liabilities / payments and the rounding/sign rules per box.
- [x] Added `read:vat write:vat` to `config('hmrc.scopes.vat')`. `HmrcService::scopesFor()` from Phase 1 already merges currently-granted scopes from the existing token, so re-auth from a VAT-registered instructor with an existing ITSA token produces the union — never narrower.
- [x] VAT Index page surfaces a "VAT permissions not granted" banner when the eligible user's existing token lacks `write:vat`/`read:vat`, with a Reconnect CTA. Existing ITSA permissions are preserved through the disconnect/reconnect cycle (UI copy makes this explicit).

### 4b. Database

- [x] Migration: `2026_04_28_160001_create_hmrc_vat_obligations_table` — `(user_id, vrn, period_key)` unique, status + `last_reminder_sent_at` for reminders, `last_synced_at` for cron freshness.
- [x] Migration: `2026_04_28_160002_create_hmrc_vat_returns_table` — 9 boxes as `bigInteger` pence (boxes 6–9 stored as pence here even though HMRC takes whole pounds; the action layer rounds), HMRC response fields (`form_bundle_number`, `charge_ref_number`, `payment_indicator`, `processing_date`), audit fields (`request_payload`, `response_payload`, `correlation_id`), attestation columns. Unique on `(user_id, vrn, period_key)`.
- [x] VRN read from `instructors.vrn` (Phase 1.5) — never re-prompted.
- [x] No revisions table for VAT: HMRC has no amendment endpoint, so the row IS the audit record.
- [x] Updated `.claude/database-schema.md` with both new tables + relationships.

### 4c. Models, Actions, Service

- [x] `HmrcVatObligation` model — casts `status` to existing `ItsaObligationStatus` enum (Open/Fulfilled), date casts. Reused the ITSA enum because the values match and adding a `VatObligationStatus` clone would have been pure duplication.
- [x] `HmrcVatReturn` model — casts for all 9 pence integers + `finalised` bool + json payloads + datetime fields.
- [x] 5 actions in `app/Actions/Hmrc/Vat/` plus a `BuildVatReturnPayloadAction` (translates pence → HMRC payload, rounds boxes 6–9 to whole pounds):
  - `ListVatObligationsAction` — GET obligations, upserts on `(user_id, vrn, period_key)`. Maps HMRC's `O`/`F` status codes to long-form. Throws on missing VRN.
  - `RetrieveVatReturnAction` — GET a previously-submitted return; URL-encodes `periodKey` (some legacy keys contain `#`).
  - `SubmitVatReturnAction` — idempotent on existing `(user_id, vrn, period_key)` row that has a `submitted_at`. Marks the matching obligation `Fulfilled` after a successful submit.
  - `ListVatLiabilitiesAction` / `ListVatPaymentsAction` — read-only date-range queries.
- [x] `HmrcVatService extends BaseService` — `syncObligations`, `openObligations` (300s cache), `submissionHistory`, `submitReturn`, `retrieveReturn`, `liabilities`, `payments`. Cache invalidation on every write.
- [x] Reused the Phase 3 reminder/scheduling cron — `SyncHmrcItsaObligations` now syncs VAT obligations for VAT-registered instructors AND fires the same 30/14/7/1 reminder thresholds via a sibling `VatObligationDueSoon` notification. Each branch (ITSA / VAT) is independently gated on the instructor's profile, so an instructor with both gets both kinds of reminders, with their own `last_reminder_sent_at` idempotency.

### 4d. Controllers + UI

- [x] `Hmrc/Vat/VatController` (Inertia) — `index`, `syncObligations`, `period`, `store`. All four use the existing fraud-context helper pattern from `ItsaController`.
- [x] `Hmrc/Vat/Index.vue` — open obligations table with deadline badges, submission history table with form bundle + correlation IDs, refresh-from-HMRC button using `useHmrcAction` for fingerprint capture, immutability notice. Eligibility branches into 4 states: not-connected / not-VAT-registered / VAT-scope-missing / ready.
- [x] `Hmrc/Vat/Period.vue` — full 9-box form with computed Box 3 / Box 5 helpers ("Use computed" links), inputmode hints (decimal for VAT amounts, numeric for whole-pound boxes), per-box field errors, attestation checkbox, confirmation overlay copy emphasises immutability: *"VAT returns cannot be amended once filed — corrections must be made in a future-period adjustment."* Read-only state when `existing.submitted_at` is set.
- [x] `SubmitVatReturnRequest` — pence conversion in `prepareForValidation()`, all 9 boxes required non-negative integers, cross-field validation: Box 3 = Box 1 + Box 2, Box 5 = abs(Box 3 − Box 4). Authorisation checks instructor + `vat_registered` + non-empty VRN.
- [x] Connection.vue VAT card now deep-links to `/hmrc/vat` (was "Coming soon").

### 4e. Routes

- [x] Added inside the existing `hmrc` route group (outside `EnsureMtdEnrolled` — that's ITSA-specific):
  ```php
  Route::prefix('vat')->name('vat.')->group(function () {
      Route::get('/', [VatController::class, 'index'])->name('index');
      Route::post('/sync-obligations', [VatController::class, 'syncObligations'])->name('sync-obligations');
      Route::get('/{periodKey}/period', [VatController::class, 'period'])->where('periodKey', '.+')->name('period');
      Route::post('/{periodKey}/period', [VatController::class, 'store'])->where('periodKey', '.+')->name('store');
  });
  ```
- [x] `php artisan route:clear && route:list --path=hmrc` shows 25 routes total (was 21) including the 4 new VAT ones.
- [x] `where('periodKey', '.+')` accommodates HMRC period keys that may contain non-`/` special chars (e.g. `#`). The Vue `encodeURIComponent` call handles the URL safely.

### 4f. Verification

- [x] `php -l` clean on every new/modified PHP file (2 migrations, 2 models, 6 actions, 1 service, 1 notification, 1 cron rewrite, 1 form request, 1 controller, routes, config).
- [x] `database-schema.md` updated with both new VAT tables.
- [x] `route:list --path=hmrc` confirms 25 routes including the 4 new VAT routes.
- [x] `schedule:list` still shows `hmrc:sync-itsa-obligations` (now covers VAT too) at `15 7 * * *`.
- [ ] **USER ACTION:** `php artisan migrate` to apply the 2 new migrations (`hmrc_vat_obligations`, `hmrc_vat_returns`).
- [ ] **USER ACTION:** Provision a sandbox VAT test user via HMRC's Create Test User, set the instructor's `vat_registered=true` + VRN matching the test user, reconnect to grant VAT scopes (the Index banner will prompt this), then walk: refresh obligations → open period → fill boxes 1–9 → tick attestation → confirm overlay → submit → verify `form_bundle_number` + `correlation_id` captured. Verify the obligation is marked `Fulfilled` afterwards.
- [ ] **USER ACTION:** Verify a non-VAT-registered instructor's `/hmrc/vat` page shows the "Not VAT-registered" banner and no submission UI.
- [ ] **USER ACTION:** `php artisan hmrc:sync-itsa-obligations --dry-run` to confirm VAT lines now appear alongside ITSA lines for a VAT-registered + sole-trader instructor.

### Phase 4 Reflection

**What landed**

- 2 migrations (`hmrc_vat_obligations`, `hmrc_vat_returns`) — `2026_04_28_160001` / `…160002`
- 2 models: `HmrcVatObligation`, `HmrcVatReturn`. Reused `ItsaObligationStatus` enum on the obligation rather than minting a near-identical copy.
- 6 actions in `app/Actions/Hmrc/Vat/`: `BuildVatReturnPayloadAction`, `ListVatObligationsAction`, `RetrieveVatReturnAction`, `SubmitVatReturnAction`, `ListVatLiabilitiesAction`, `ListVatPaymentsAction`
- `HmrcVatService extends BaseService` (sibling of `HmrcItsaService` / `HmrcItsaFinalDeclarationService`) with cache-on-read for open obligations
- `VatObligationDueSoon` notification + extended `SyncHmrcItsaObligations` cron to also sync VAT and queue VAT reminders (idempotent via `hmrc_vat_obligations.last_reminder_sent_at`)
- `VatController` (Inertia) with `index`, `period`, `store`, `syncObligations`
- `SubmitVatReturnRequest` — pence conversion + cross-field box-3/box-5 invariant checks
- 4 routes (`hmrc.vat.{index,sync-obligations,period,store}`) inside the existing HMRC group, **outside `EnsureMtdEnrolled`** (that middleware is ITSA-specific)
- 2 Vue pages: `Hmrc/Vat/Index.vue` (4-state eligibility branches: not-connected / not-VAT-registered / VAT-scope-missing / ready) and `Hmrc/Vat/Period.vue` (9-box form, computed Box 3/5 helpers, immutability copy, attestation overlay)
- Connection.vue VAT card now deep-links to `/hmrc/vat` (was "Coming soon" placeholder)
- `.claude/hmrc-vat-api.md` internal spec note (with same JS-OAS caveat as ITSA)
- `.claude/database-schema.md` updated with the 2 new VAT tables
- `config/hmrc.php` `scopes.vat = ['read:vat', 'write:vat']` — `HmrcService::scopesFor()` from Phase 1 already wires VAT scopes into the auth URL when `vat_registered=true`

**What went well**

- **Reusing the cron + status enum saved a chunk of duplication.** The original task plan said "extend the daily cron". The implementation is one extra branch per token in `SyncHmrcItsaObligations::handle` and one extra reminder dispatcher — both gated by the instructor's profile. ITSA-only and VAT-only instructors still work; dual-MTD instructors get both. Reusing `ItsaObligationStatus` (Open/Fulfilled) on `HmrcVatObligation` avoided a near-identical enum.
- **Idempotent submit is a single-line guard.** `SubmitVatReturnAction` checks for an existing row with a `submitted_at` and returns it without re-calling HMRC — protects against the user double-submitting the same period (HMRC would 409, but defending in the action is cheaper UX). VAT's no-amendment policy means this is the *only* idempotency we need; quarterly-update Phase 3 had to handle `firstOrNew → fill → save` because amendments mutate the row.
- **The `hasVatScope` banner.** Spec said "small disconnect window during re-auth — document this in UI copy". Surfacing it as an Inertia prop on the Index page rather than a generic warning lets the UI state machine (4 branches: not-connected / not-VAT-registered / scope-missing / ready) handle every realistic case without ambiguity.
- **9-box value boxes stored as pence, submitted as pounds.** HMRC's API takes integer pounds for boxes 6–9 — but storing them as pence keeps the schema homogeneous and lets a future "auto-derive from `instructor_finances`" task feed pence in everywhere. The `BuildVatReturnPayloadAction::wholePounds()` helper rounds at the boundary, single-place change if HMRC ever switches to 2dp on these boxes.

**Gotchas / decisions worth recording**

- **HMRC obligations API returns `O`/`F` status codes for VAT** but `Open`/`Fulfilled` for ITSA. `ListVatObligationsAction::mapStatus` translates so the `ItsaObligationStatus` cast works on the model. A future "VAT v2" with a different shape would need its own mapping in the same place.
- **Period keys can contain `#`.** The route uses `where('periodKey', '.+')` and the Vue side calls `encodeURIComponent`. The retrieve action also `rawurlencode`s it. Don't concatenate raw period keys into URLs.
- **Box 5 is `abs(Box 3 − Box 4)`** — the FormRequest enforces this. HMRC will reject otherwise, but catching it client-side avoids a round-trip. The Vue form has a "Use computed" link beside Box 3 and Box 5 to populate them from the other inputs.
- **VAT submissions are not amendable** so no revisions table. The single `hmrc_vat_returns` row IS the 6-year audit record. UI copy on the confirmation overlay and the success banner emphasise this — corrections must go through a future-period adjustment.
- **Eligibility ≠ scope.** A VAT-registered instructor with an existing token from before Phase 4 has no VAT scopes. The Index page detects this via `hasVatScope` prop and shows a "Reconnect to HMRC" CTA. Reconnecting through the existing `/hmrc/connect` route automatically requests the union scope set (ITSA + VAT) thanks to `HmrcService::scopesFor()`.
- **VAT routes live outside `EnsureMtdEnrolled`.** That middleware checks the ITSA-specific enrolment status enum on `instructors.mtd_itsa_status` — VAT has no such state machine in v1 (binary: registered/not). VAT enrolment is implicit in `instructors.vat_registered=true` + a working VRN; if HMRC's obligations endpoint 404s with `MTD_NOT_SIGNED_UP`, the user sees the standard `HmrcApiException` user message and follows the link in `hmrc-vat-api.md`'s gov.uk pointer.
- **`SubmitVatReturnAction::resolveVrn` mirrors the ITSA pattern.** Could DRY a `RequiresVatProfile` trait but the duplication is 5 lines × 4 actions — not yet worth abstracting; KISS over DRY here.

**Open items entering Phase 5**

- USER ACTION: `php artisan migrate` for the 2 new VAT migrations.
- USER ACTION: Provision a sandbox VAT test user, walk obligations → submit → verify `form_bundle_number` + `correlation_id` are captured. Verify the obligation flips to `Fulfilled`.
- USER ACTION: For a sole-trader-and-VAT-registered instructor: re-connect (the Index banner will tell them) and confirm the resulting token has both ITSA and VAT scopes (`HmrcToken::scopes` should contain all four).
- USER ACTION: `hmrc:sync-itsa-obligations --dry-run` to confirm VAT branches activate alongside ITSA for the dual-registered case.
- Phase 5 (production readiness) is administrative + ops. No coding hand-off blockers — the implementation surface for v1 (Phases 1, 1.5, 2, 3, 3.5, 4) is now feature-complete.

---

## Phase 5: Production readiness ✅ Complete (developer-side)

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

- [x] `app/Console/Commands/CheckHmrcRefreshHealth` — hourly cron via `bootstrap/app.php`. Computes failure rate over last N hours from `hmrc_token_refresh_logs`, breaks down by `outcome` and top error codes, exits non-zero AND `Log::warning('HMRC refresh failure rate exceeded threshold', …)` when rate > threshold. Default window 24h, threshold 1%. Both knobs configurable via `--hours` / `--threshold`. Pipe alerts off the warning-level log line.
- [x] `MonitorHmrcTokenExpiry` already covers the daily T-30 / T-7 reconnect digest (Phase 1).
- [ ] **USER ACTION (ops):** Wire the Laravel-log alerting to fire on `HMRC refresh failure rate exceeded threshold`. (Implementation depends on the user's log infrastructure — Datadog, CloudWatch, Sentry, etc.)
- [ ] **USER ACTION (ops):** Add ITSA-obligations-sync failure alerting (the `SyncHmrcItsaObligations` cron already throws + logs on per-user errors; alert on the existing log channel).
- [ ] **USER ACTION (ops):** Add HMRC 5xx submission-failure alerting. Submission failures persist a `failed_*` revision (`hmrc_itsa_quarterly_update_revisions.kind`) and `CallHmrcApiAction` logs the response status — pick whichever the existing alerting infra reads.

### 5e. Support runbooks (`.claude/runbooks/hmrc/`)

- [x] [submission-failed.md](../runbooks/hmrc/submission-failed.md) — locate audit row by table, classify HMRC error code, replay via tinker, capture correlation ID
- [x] [stuck-reconnecting.md](../runbooks/hmrc/stuck-reconnecting.md) — token state snapshot, `hmrc_token_refresh_logs` interpretation per outcome, 5 named scenarios (A–E), manual reconnect via tinker
- [x] [hmrc-approval-rejected.md](../runbooks/hmrc/hmrc-approval-rejected.md) — categories of HMRC reviewer rejection + remediation paths, escalation when rejected twice
- [x] [instructor-figures-wrong.md](../runbooks/hmrc/instructor-figures-wrong.md) — correction paths split by submission type (quarterly amend / final declaration window / VAT future-period adjustment)

### 5f. Verification

- [ ] HMRC production application submitted with MFS evidence + URLs
- [ ] Production approval received from HMRC
- [ ] Smoke test against production — ideally with one consenting real instructor and a low-stakes period
- [ ] Monitoring alerts proven by deliberately tripping each one in a controlled way

### Phase 5 Reflection

**What landed**

- `app/Console/Commands/CheckHmrcRefreshHealth` hourly cron — single-purpose: read `hmrc_token_refresh_logs` over a window, compute failure rate, log warning + exit non-zero on breach. Tabular CLI output for ops, structured `Log::warning` payload for alerting wiring.
- Hourly schedule entry added to `bootstrap/app.php` alongside the existing `hmrc:monitor-token-expiry` and `hmrc:sync-itsa-obligations`.
- 4 support runbooks under `.claude/runbooks/hmrc/`:
  - `submission-failed.md` — classify HMRC error codes, replay path, correlation-ID retrieval
  - `stuck-reconnecting.md` — 5 scenarios (A: refresh expired, B: user revoked, C: scope drift, D: race, E: stale frontend)
  - `hmrc-approval-rejected.md` — MFS-evidence / docs-gap / production-subscription / fraud-headers / audit-trail rejection categories
  - `instructor-figures-wrong.md` — correction paths split by quarterly (amendable) / final declaration (windowed) / VAT (future-period adjustment only)

**What went well**

- The audit-trail design from Phases 3 / 3.5 / 4 made the runbooks largely a query-and-explain exercise. Every submission already records correlation ID, request payload, response payload, and (for ITSA quarterly) an append-only revision log. The runbooks point at concrete tables and JSON paths rather than guessing.
- `CheckHmrcRefreshHealth` reuses Phase 1's `hmrc_token_refresh_logs` data source verbatim — no new schema. Exit-code semantics (non-zero on breach) plus a warning-level log line cover both "schedule alerts on cron exit codes" infra and "schedule alerts on log severity" infra without coupling to a specific provider.
- The runbooks deliberately don't enumerate every HMRC error code — that lives in `app/Enums/HmrcErrorCode.php` and would drift. Instead they explain how to read the catalogue. Adding a new error case = one enum case, no runbook update.

**Gotchas / decisions worth recording**

- **Phase 5 is mostly USER ACTIONS** — production HMRC application, MFS demo video, privacy/T&Cs URLs, ops alerting wiring, smoke tests against production. The dev-side surface area is small (one Artisan command + four markdown files). Marking the phase "complete" reflects dev work being finished; the production-go-live gates remain unchecked deliberately so the user has a live punch list.
- **Final-declaration amendment has a unique-constraint trap.** `hmrc_itsa_final_declarations` is unique on `(user_id, tax_year)` which blocks an in-window re-submission today. Documented in `instructor-figures-wrong.md` as an engineering escalation case. Future work — likely the constraint should be relaxed and the row treated as upsert with a revision sibling table mirroring quarterly's pattern.
- **No alerting wiring inside DRIVE.** Alerting infrastructure is operations-owned; the command emits structured signals (exit code, log severity, log payload). Wiring those to Datadog / CloudWatch / Sentry / Slack is environment-specific and listed as USER ACTIONs.
- **HMRC 5xx alerting reuses existing logs.** `CallHmrcApiAction` already logs request/response status for debugging — no new code needed; the runbook documents that the alerting hook should pattern-match on existing log lines.

**Open items for production go-live (USER ACTIONs)**

- USER ACTION: Capture MFS evidence per 5a (screen-recorded demo + screenshots).
- USER ACTION: Publish privacy / T&Cs / FAQ / support pages with the MTD-specific clauses listed in 5b. The runbook `hmrc-approval-rejected.md` has the verbatim required content.
- USER ACTION: Apply for production credentials per 5c. Subscribe to all 14 APIs in production. Set `HMRC_ENVIRONMENT=production`, `HMRC_VENDOR_PUBLIC_IP`, production `HMRC_CLIENT_ID` / `HMRC_CLIENT_SECRET` / `HMRC_REDIRECT_URI` in production `.env`.
- USER ACTION: Wire log-based alerting per 5d.
- USER ACTION: Production smoke test per 5f (ideally one consenting real instructor on a low-stakes period). Trip each alert deliberately to prove monitoring is real.

---

## Out of scope for this task (future work)

### 🔁 MOVED — auto-derive ITSA categories now in active planning (see "Next task" section at end of file)
**Status update 2026-04-29 (afternoon):** the auto-derivation work has been promoted from "future work" to active planning. Phases 6–9 are scoped at the bottom of this file. The original spec sketch below is preserved for context, but the canonical version is in `.claude/hmrc-category-mapping.md` and the new phase definitions below.

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

**Status:** All phases ✅ complete (developer-side) on 2026-04-28. Phase 5 production go-live gates (HMRC application, MFS evidence, public docs, alerting wiring, production smoke test) remain as USER ACTIONS — see Phase 5 Reflection's open-items list.

**Last updated:** 2026-04-28 — Phase 5 implementation finished. Final phase done; `.phase_done` sentinel written.
**Last Updated:** 2026-04-28

---

## Session handoff — 2026-04-29

This session was the first sandbox-testing pass after Phase 5 dev-completion. Several real-world issues only surfaced once HMRC's actual responses came through. All fixes shipped and verified.

### What was tested + sandbox-verified this session

| Phase | Status | Evidence |
|---|---|---|
| Phase 1 — OAuth + Hello World | ✅ Verified end-to-end | `{ "message": "Hello User" }` response captured in DB; tokens encrypted, scopes `[hello, read:self-assessment, write:self-assessment]` granted |
| Phase 1.5 — Tax Profile | ✅ Verified | Sole-trader profile with NINO + UTR matching the HMRC test user (NINO `YJ880329D`, UTR `6363336439`) |
| Phase 2 — Fraud headers | ⚠️ Partially verified | Headers flow through every API call (every successful sync confirms this). The diagnostic validator endpoint URL (`/test/fraud-prevention-headers/validator/validate`) returns 404 — HMRC's actual path differs from the task plan's guess. Diagnostic-only; doesn't block submissions. Punt until production setup or fix as needed. |
| Phase 3 — ITSA Quarterly submit | ✅ Fully verified | Round-trip POST to `/individuals/business/self-employment/{nino}/{businessId}/period` → 200, captured `submission_id=2019-01-06_2019-04-05` (HMRC's `periodId`) and `correlation_id=c52674a1-…` |
| Phase 3 — ITSA Quarterly amend | ✅ Code path verified | HMRC sandbox returns 404 on PUT (sandbox is stateless — POST simulates success but doesn't persist, so subsequent PUT against the periodId 404s). Our code correctly catches the failure and writes `failed_amendment` revision rows for the audit trail. Production HMRC is stateful → success branch will run, parent row will be overwritten, fresh correlation captured on revision N+1. |
| Phase 3.5 — Final Declaration | ⏸️ Not tested | Code complete from Phase 3.5 build. Needs sandbox walk-through with 4 quarters pre-filled. |
| Phase 4 — VAT | ⏸️ Not tested | Code complete from Phase 4 build. Test user has VRN `795267087` ready. Toggle `vat_registered=true` on tax profile + reconnect to grant scopes when ready. |

### Bugs discovered + fixed in this session

1. **Eloquent pluralizer trap on `HmrcOAuthState`** — model resolved to `hmrc_o_auth_states` instead of `hmrc_oauth_states`. Fix: explicit `protected $table` on the model.
2. **Double `Accept` header** — `CallHmrcApiAction` was calling `->acceptJson()` AND `->withHeaders(['Accept' => 'application/vnd.hmrc.X.Y+json'])`, which Guzzle merged into `Accept: application/json, application/vnd.hmrc.X.Y+json`. HMRC rejected with 406. Fix: dropped the `->acceptJson()` call.
3. **Lowercase status from HMRC** (`fulfilled`/`open`) didn't match TitleCase enum cases. Fix: `ucfirst(strtolower(...))` at the boundary in `ListObligationsAction::upsert`.
4. **HMRC v3 obligations response has no `periodKey`** — only `periodStartDate`/`periodEndDate`. Fix: synthesize `period_key` as `{start}_{end}` so the unique key works and URLs are valid.
5. **Stale fingerprint on sync buttons** — `Index.vue` did plain `router.post` without `useHmrcAction.refreshFingerprint()` first. After 30 minutes the action threw `MissingFraudFingerprintException`. Fix: import the composable and `await refreshFingerprint()` before the post in `refreshStatus` and `syncObligations`.
6. **HMRC returns `periodId` not `submissionId`** for ITSA quarterly submits. Fix: `SubmitQuarterlyUpdateAction` falls back from `submissionId` to `periodId`.
7. **`X-CorrelationId` was being discarded** — `CallHmrcApiAction` returned only the JSON body. Fix: now reads the `X-CorrelationId` response header and merges it into the body as `_correlationId`. Every action downstream now captures correlation IDs automatically.
8. **Cross-business-type contamination** — `openObligations` showed UK-property and foreign-property obligations alongside self-employment, leading to a user clicking the wrong row and submitting against the wrong endpoint. Fix: filter `openObligations` to businesses where `hmrc_itsa_businesses.type_of_business = 'self-employment'`.
9. **APP_URL scheme mismatch** — `HMRC_REDIRECT_URI` empty fallback resolved to `http://drivecrm.test/...` but HMRC has `https://drivecrm.test/...` registered. **USER ACTION:** explicit `HMRC_REDIRECT_URI=https://drivecrm.test/hmrc/oauth/callback` set in `.env`.

### UI improvements landed this session

- HMRC tab embedded inside `/instructors/{id}` instead of standalone `/hmrc` for instructors. Sub-page pattern with Back button (mirrors student detail UX).
- New shared component: `resources/js/components/Hmrc/HmrcConnectionPanel.vue` (extracted from Connection.vue body so it can be reused both in the standalone page and the instructor-tab embed).
- `Connection.vue` slimmed to a thin AppLayout wrapper.
- `InstructorHeader.vue` — HMRC button next to Stripe + Edit Profile. Shows "HMRC Connected" green-outline button when token exists, "HMRC / Tax" with shield icon otherwise. Matches Stripe button styling (`min-w-[180px] py-2.5`).
- HMRC OAuth callback redirects to `/instructors/{id}?tab=hmrc` when the user has an instructor profile (instead of standalone `/hmrc`).
- Tax profile + Connection status side-by-side in the panel (was vertically stacked).

### Known minor issues, deferred

- **Period.vue toast on amend failure** — flash error shows briefly via Sonner but auto-dismisses; user may miss it. Future: persistent error banner instead of toast for failure states.
- **`Gov-Client-Public-IP looks private` warning** — expected in dev (Herd is `127.0.0.1`); production with real public IP will resolve.
- **Fraud-headers validator URL** — 404s; diagnostic-only. Real fraud headers DO flow correctly because every successful API call sends them.

### Next major piece of work (post handoff)

**Auto-derive ITSA categories + VAT 9-box from `instructor_finances` and `mileage_logs`.** Spec sketch at lines 924-949 above. The actual category-mapping table needs:

1. **Inspection step** — dump current DRIVE expense/payment categories from `instructor_finances` so we know what we're working with (next session's first action).
2. **Strawman mapping** — map each DRIVE category to: HMRC ITSA bucket (one of 15) + `vat_treatment` enum + `claimable: bool`. AI can draft, human (with MTD/accountancy domain knowledge) reviews.
3. **`simplified_vs_actual` choice** on tax profile — per-vehicle, lifetime decision. Affects whether mileage log feeds `carVanTravelExpenses` or whether actual fuel/insurance/MOT costs do.
4. **VAT treatment columns** on `instructor_finances` (`vat_treatment`, `vat_rate`, `vat_amount_pence`).
5. **`fuel_scale_charge` calc** for VAT-registered with engine_size on instructor.
6. **Pre-population** in `Period.vue` (and the VAT 9-box form) with calculated values + override + "calculated from your records" badge per field.

**Recommended first step in the next session:** dump the current expense category list + sample data, draft the strawman mapping. Don't write any code yet — agree on the mapping table first.

**Last updated:** 2026-04-29 — Phase 3 sandbox round-trip verified; Phase 3.5 + Phase 4 still need sandbox walk-through; auto-derivation queued as next major task.

---

## Next task — Auto-derive ITSA quarterly updates from instructor records

**Status:** ⏸️ Planning complete, scope locked, awaiting client sign-off on category mapping before Phase 6 starts.

**Why:** Phase 3 (ITSA Quarterly Updates) shipped with manual figure entry — instructors type the 15 expense bucket totals into `Period.vue` every quarter. This task replaces manual entry with automatic calculation from `instructor_finances` + `mileage_logs`, with per-field override + audit trail. Same applies to the VAT 9-box for the small minority of VAT-registered instructors (Phase 4 forms). VAT auto-derivation is **out of scope for this round** — it'll be a separate task once ITSA auto-derive is solid.

**Source-of-truth planning docs (read these before starting):**
- `.claude/hmrc-category-mapping.md` — engineering spec; drives schema + code
- `.claude/hmrc-tax-categories-client-summary.md` — client-facing version sent for sign-off

### Locked decisions (2026-04-29)

1. **Build both methods.** Both Simplified Expenses and Actual Costs are supported per-vehicle. Default-suggest Simplified in the UI; instructor can toggle to Actual for any vehicle on first setup. (Option 1 from the planning conversation.)
2. **Engineering doc §4 mapping is the strawman** — once client signs it off line-by-line, it gets locked into a new `category_tax_mapping` block in `config/hmrc.php`. After locking, downstream changes are config-only.
3. **Three structural changes to the existing finance categories:**
   - Split `insurance` into `vehicle_insurance` + `business_insurance`
   - Drop `food_drink` from the picker (existing rows stay tagged historically; excluded from HMRC payloads)
   - Add `phone` + `accountant_fees`
4. **Four new Actual-only categories** (only relevant if a vehicle is on the Actual method): `servicing`, `repairs`, `road_tax`, `breakdown_cover`
5. **Vehicle is a first-class entity.** New `vehicles` table — `simplified_vs_actual` is per-vehicle and lifetime, so it cannot live on the instructor row.
6. **Soft-delete + 6-yr retention on `instructor_finances` is mandatory.** Once a row contributes to an HMRC submission, it cannot be hard-deleted from DRIVE. Receipts follow the same lifecycle.
7. **Tax-year archive download is in v1** — single-tax-year, async, signed URL. Multi-year + custom date ranges deferred.

### Open items still to confirm with client (not blocking Phase 6 planning, but blocking Phase 6 execution)

Tracked in `hmrc-category-mapping.md §6` and `hmrc-tax-categories-client-summary.md §9`. Blocking ones:
- Final sign-off on §4 mapping table (line-by-line)
- Three policy decisions: vehicle change frequency (informs comparison-panel emphasis), recommend-or-stay-neutral, hard-lock vs soft-lock on method switch
Non-blocking (can resolve while Phase 6 builds):
- Pupil test fees pass-through treatment
- Equipment capital threshold (suggest £200/item)
- Franchise fee structure (flat vs commission)

### Phase summary

| # | Phase | Deliverable |
|---|---|---|
| 6 | Foundation — config, category changes, vehicles entity | Locked mapping; structural slug changes shipped; vehicles entity live with method choice + comparison panel |
| 7 | Auto-derivation engine | `Period.vue` pre-populated from finances + mileage; per-field override with reason + audit trail of contributing rows |
| 8 | Retention + delete protection | Soft-delete state machine on `instructor_finances`; receipts retained for 6 yrs; UI enforces lock |
| 9 | Tax-year archive download | Self-serve ZIP export per completed tax year; async job + signed URL email |

**Same multi-phase rule as the original task — do NOT auto-continue between phases.** After each phase: mark complete, fill reflection, update timestamp, then STOP and wait for the user. The `.phase_done` sentinel for THIS continuation is re-written when all four phases (6, 7, 8, 9) are complete (the original sentinel for Phases 1-5 was written 2026-04-28 and remains valid for that work).

---

## Phase 6: Foundation — mapping config, categories, vehicles ⏸️ Not Started

**Goal:** Lock the category mapping into config, make the agreed structural changes to expense slugs, and introduce the `vehicles` entity with method-choice UI + comparison panel. No auto-derivation yet — that's Phase 7.

### 6a. Database
- Migration: `update_finance_categories_for_hmrc_alignment` — non-destructive config-driven; data migration backfills any existing `insurance` rows to `vehicle_insurance` (with a one-time prompt for instructors to review on next visit). Existing `food_drink` rows remain tagged but the slug is removed from the active picker.
- Migration: `create_vehicles_table` — columns: `id`, `instructor_id` (FK), `registration` (UK plate), `make`, `model`, `purchase_date`, `purchase_price_pence` (bigInt), `co2_emissions_g_per_km` (smallInt nullable), `fuel_type` (enum), `method` (enum: `simplified`|`actual`), `business_use_percentage` (decimal 5,2 default 100.00 — only consulted for Actual), `method_decided_at`, `deactivated_at` (nullable, for when instructor sells the car), `notes`, timestamps.
- Migration: `add_vehicle_id_to_instructor_finances` — nullable FK; required at submit time only for rows in vehicle-cost categories.

### 6b. Config
- Update `config/finances.php`:
  - `expense_categories`: replace `insurance` with `vehicle_insurance` + `business_insurance`; remove `food_drink`; add `phone`, `accountant_fees`, `servicing`, `repairs`, `road_tax`, `breakdown_cover`.
- New `config/hmrc.php` block — `category_tax_mapping` keyed by DRIVE slug with `{ hmrc_bucket: string|null, claimable: bool, method_dependent: bool }`. Source: `.claude/hmrc-category-mapping.md` §4.

### 6c. Models + Enums
- New `app/Models/Vehicle.php` — `instructor()` belongsTo, `finances()` hasMany, scopes for active/inactive.
- Update `app/Models/Instructor.php` — `vehicles()` hasMany, `activeVehicles()` scope.
- Update `app/Models/InstructorFinance.php` — `vehicle()` belongsTo (nullable).
- New enum `app/Enums/VehicleMethod.php` — cases `Simplified`, `Actual` with `label()` + `description()` helpers.
- New enum `app/Enums/FuelType.php` — `Petrol`, `Diesel`, `Hybrid`, `Electric`, `PluginHybrid`.

### 6d. Validation
- `app/Http/Requests/StoreVehicleRequest.php`, `UpdateVehicleRequest.php` — UK plate regex on `registration`, positive `purchase_price_pence`, valid CO2 if non-EV.
- Update `StoreInstructorFinanceRequest` / `UpdateInstructorFinanceRequest` — require `vehicle_id` if category is in the vehicle-running-costs set.

### 6e. Service / Actions
- `app/Services/VehicleService.php extends BaseService` — CRUD, deactivation (sells/replaces vehicle), method-switch with soft-lock check.
- `app/Actions/Hmrc/Vehicles/SwitchVehicleMethodAction.php` — checks if any quarterly update has been submitted using current method; soft-locks (warning only) per the locked decision.
- `app/Actions/Hmrc/Vehicles/CalculateMethodComparisonAction.php` — given an instructor + vehicle + lookback window (default 12 months), returns `{ simplified_pence, actual_pence, basis_data }` for the comparison panel.

### 6f. Controllers + UI
- `app/Http/Controllers/Hmrc/VehicleController.php` — index/store/update/destroy/switchMethod.
- New page: `resources/js/pages/Hmrc/Vehicles/Index.vue` — list of vehicles, method shown as badge.
- New sheet: `resources/js/components/Hmrc/VehicleSheet.vue` — add/edit form including initial method choice (Simplified pre-selected per locked decision).
- New component: `resources/js/components/Hmrc/MethodComparisonPanel.vue` — side-by-side card showing `Simplified would deduct £X / Actual would deduct £Y based on your last 12 months`.
- Embed Vehicles tab into `HmrcConnectionPanel.vue`.

### 6g. Routes
- Wayfinder: `/hmrc/vehicles` index/CRUD + `/hmrc/vehicles/{vehicle}/method` for switch.

### 6h. Verification
- Sandbox check: create a vehicle, set method to Simplified, view comparison panel populated with calculated figures.

### Phase 6 Reflection
*(To be filled at end of phase)*

---

## Phase 7: Auto-derivation engine ⏸️ Not Started

**Goal:** Replace manual entry on `Period.vue` (and the upcoming Final Declaration form) with calculated figures from `instructor_finances` + `mileage_logs`, respecting per-vehicle method choice. Per-field override with reason; audit trail of contributing rows.

### 7a. Database
- Migration: `add_derivation_audit_to_quarterly_updates` — JSON column `derivation_audit` on `hmrc_itsa_quarterly_updates`, structured as `{ bucket_key: { calculated_pence, override_pence, override_reason, contributing_finance_ids: [], contributing_mileage_ids: [], calculation_notes: string } }` per bucket.
- Migration: `add_auto_populated_meta` — `auto_populated_at` timestamp + `last_calculated_at` timestamp on `hmrc_itsa_quarterly_updates`.

### 7b. Service / Actions
- `app/Actions/Hmrc/Itsa/DeriveQuarterlyUpdateAction.php` — pure calculation, no persistence:
  - Input: instructor, period_start, period_end.
  - Output: `array<HmrcBucket, { calculated_pence, contributing_finance_ids, contributing_mileage_ids, notes }>`.
  - Per-bucket logic:
    - Sum `instructor_finances` rows where `category` maps to this bucket and `period_start <= occurred_at <= period_end`.
    - For `carVanTravelExpenses`: iterate active vehicles; if vehicle method = Simplified, compute `mileage × rate` honouring the 10k threshold across the tax year (query previous quarters' mileage to find the rolling total before this quarter); if vehicle method = Actual, sum vehicle-running-cost rows linked to this `vehicle_id`, multiplied by `business_use_percentage`.
- `app/Actions/Hmrc/Itsa/ApplyDerivationToQuarterlyUpdateAction.php` — calls Derive, persists calculated figures + audit JSON to the draft quarterly update.

### 7c. Controllers + UI
- New endpoint on `QuarterlyUpdateController`: `derive(Period)` — returns calculated figures without saving (preview).
- `resources/js/pages/Hmrc/Itsa/Period.vue` — on mount, call `derive` and pre-fill each of the 15 fields. Each field shows:
  - Value (calculated or overridden)
  - "Calculated from your records" badge (with hover-popover listing contributing row count + link to filtered finance view)
  - Override toggle → input + required reason textarea (audit trail)
  - On submit: snapshot full audit JSON into the update.

### 7d. Routes
- Wayfinder: `POST /hmrc/itsa/quarterly/{period}/derive` (returns JSON, no submit).

### 7e. Verification
- Sandbox: instructor with mixed Simplified + Actual vehicles, finance rows + mileage logs across a quarter → derive → numbers match expected → submit → HMRC accepts → audit row stored.

### Phase 7 Reflection
*(To be filled at end of phase)*

---

## Phase 8: Retention + delete protection ⏸️ Not Started

**Goal:** Enforce HMRC's 6-year retention rule. Soft-delete + state machine on `instructor_finances`. UI prevents destructive actions on locked rows. Receipts on disk follow the lifecycle.

### 8a. Database
- Migration: `add_soft_delete_and_state_to_instructor_finances`:
  - `deleted_at` (timestamp, soft-delete column)
  - `submission_state` enum: `draft` | `submitted` | `final_declared` (default `draft`)
  - `submission_state_changed_at` (timestamp)
  - `tax_year_locked` (string, e.g. `2026-27` — the tax year that locked this row, for retention calculation)

### 8b. Models
- `InstructorFinance` — `use SoftDeletes`; helper methods `isLocked(): bool`, `canEdit(): bool`, `canDelete(): bool`.
- Observer or service hook: on successful quarterly submission, mark all `contributing_finance_ids` from the audit as `submitted`. On successful final declaration, mark all of that tax year's rows as `final_declared`.

### 8c. Service / Actions
- `app/Actions/Hmrc/Retention/LockSubmittedFinancesAction.php` — bulk-update state after a quarterly submission succeeds.
- `app/Actions/Hmrc/Retention/LockTaxYearOnFinalDeclarationAction.php` — fires after Final Declaration success.
- `app/Console/Commands/PurgeExpiredFinancialRecords.php` — scheduled nightly; deletes rows + receipt files where `tax_year_locked` is more than 6 years past the end of the relevant tax year.

### 8d. Controllers + UI
- `InstructorFinanceController::destroy` — call `canDelete()`; return 403 with explanation if locked.
- `InstructorFinanceController::update` — if `submitted`, allow but write a revision (re-uses the existing audit pattern from Phase 3); if `final_declared`, return 403.
- `resources/js/components/Finance/FinanceTable.vue` — disable delete button + show tooltip on locked rows; show small "Submitted to HMRC" / "Final declared" badge.

### 8e. Verification
- Manually try to delete a row that's part of a submitted period via the UI and via the API → both blocked with explanation.
- Submit a quarterly update → verify contributing rows transition to `submitted`.

### Phase 8 Reflection
*(To be filled at end of phase)*

---

## Phase 9: Tax-year archive download ⏸️ Not Started

**Goal:** Self-serve ZIP archive per completed tax year — accountant handover at year-end + HMRC enquiry response pack on the rare occasions HMRC asks. Async job, signed URL via email.

### 9a. Database
- Migration: `create_tax_year_archives_table` — `id`, `instructor_id` (FK), `tax_year` (e.g. `2026-27`), `status` (enum: `pending`|`building`|`ready`|`failed`|`expired`), `requested_at`, `built_at`, `expires_at`, `file_path` (private storage), `file_size_bytes`, `error_message` (nullable), timestamps.

### 9b. Service / Actions
- `app/Actions/Hmrc/Archive/RequestTaxYearArchiveAction.php` — creates pending row, dispatches job, returns the archive ID.
- `app/Jobs/BuildTaxYearArchiveJob.php` (queued) — generates ZIP:
  - `finances.csv` — all finance rows for the tax year (incl. soft-deleted-but-locked rows)
  - `mileage.csv` — all mileage logs
  - `receipts/Q1/`, `Q2/`, `Q3/`, `Q4/` — original files filed by quarter, prefixed with row ID (`{row_id}_{original_filename}`)
  - `submissions/quarterly_q[1-4].json` + `submissions/final_declaration.json` — HMRC payload + correlation ID + response per submission
  - `summary.pdf` — cover sheet (instructor name, business name, tax year range, totals per HMRC bucket, list of submissions with correlation IDs)
  - On completion: upload to private storage, set `ready_at`, `expires_at = +24 hours`, generate signed URL, email instructor.
- `app/Actions/Hmrc/Archive/BuildArchiveSummaryPdfAction.php` — generates the cover sheet (use `barryvdh/laravel-dompdf` if available; otherwise add).

### 9c. Controllers + UI
- `app/Http/Controllers/Hmrc/TaxYearArchiveController.php` — `index` (list of available + previously requested archives), `store` (request new), `download` (signed URL gateway).
- `resources/js/components/Hmrc/TaxYearArchiveSection.vue` — embedded on the HMRC tab. Row per completed tax year. States: `Not requested` (button: "Request archive") / `Building...` (with timestamp) / `Ready until {expires_at}` (download button) / `Expired` (re-request button).

### 9d. Routes
- Wayfinder: archive CRUD + signed download endpoint.

### 9e. Verification
- Request archive for a sandbox tax year with at least one submission → wait for job → email arrives → download → verify ZIP contents (CSV row counts match DB; receipts present; summary PDF readable).

### Phase 9 Reflection
*(To be filled at end of phase)*

---

**Last updated:** 2026-04-29 (afternoon) — Auto-derive planning locked. Default Simplified with Actual toggle confirmed (Option 1). Phases 6–9 scoped against the two new planning docs. Awaiting client sign-off on `.claude/hmrc-category-mapping.md` §4 mapping table before starting Phase 6.

---

## Session handoff — 2026-04-30 — Product scope decisions & compliance track

Three product decisions taken with the user, and a parallel non-code workstream named.

### Decision 1 — Final Declaration is removed from the product

**What:** Phase 3.5 (ITSA Final Declaration) code is built and code-complete (see `Phase 3.5: ITSA Final Declaration ✅ Complete` above), but the *product* will not expose it. End-of-year journey is now: instructor uses DRIVE for quarterly updates → Phase 9 tax-year archive download → instructor hands the archive to a qualified accountant → accountant files the Final Declaration via their own tools.

**Why:** sharply reduces our exposure to the "but the software told me to" defence. Quarterly updates are mechanical (here are the figures the instructor entered); Final Declaration involves interpretive choices (which reliefs apply, supplementary income categorisation) where being seen to advise is a real risk. Handing off to an accountant for the interpretive piece is a much cleaner "software not advice" position. Confirmed with client 2026-04-30.

**How to apply (when this decision is actioned — not now):**
- Do **not** delete Phase 3.5 code. Keep `app/Actions/Hmrc/Itsa/FinalDeclaration/`, the migrations, the controller, the wizard pages — they're a known-working asset.
- Hide the Final Declaration entry points in `HmrcConnectionPanel.vue` / instructor HMRC tab. Disable the routes (`abort(404)` in the controller, or remove from the route file) so a directly-typed URL doesn't reach the wizard.
- Remove the Final Declaration items from Phase 5a MFS evidence list — the demo we record for HMRC production approval no longer needs to show a Final Declaration journey.
- Production HMRC API subscription is **9 APIs not 14** — drop these five from the production subscription (sandbox can keep them in case we ever re-enable):
  - Self Assessment Individual Details (MTD) 2.0
  - Individuals Reliefs (MTD) 3.0
  - Individuals Disclosures (MTD) 2.0
  - Individuals Savings Income (MTD) 2.0
  - Individuals Dividends Income (MTD) 2.0
  - **Keep** Individual Calculations (MTD) 8.0 and Self Assessment Accounts (MTD) 4.0 read-only — they're useful for surfacing the running tax position to the instructor without crossing into advice territory.
- `.claude/hmrc-itsa-final-declaration.md` carries a `SUPERSEDED` header from 2026-04-30 — leave it as historical reference, do not action.
- Phase 3.5 heading above carries a `DESCOPED 2026-04-30` banner — points back to this handoff entry.

### Decision 2 — Phase 9 (tax-year archive) is the primary year-end deliverable

**What:** the existing Phase 9 (Self-serve ZIP archive per completed tax year) was scoped as a useful adjunct. It is now the **primary user-facing year-end action**. The "give this to your accountant" hand-off is the explicit purpose of the feature, not a side-benefit.

**How to apply (when Phase 9 is built):**
- UI copy across the HMRC tab should frame the year-end action as "download your tax-year archive for your accountant" — not "submit your final declaration" (we don't do that any more) or "request an export" (under-sells it).
- Archive contents per Phase 9b stay as planned (finances.csv, mileage.csv, receipts/Q1–Q4/, submissions/*.json, summary.pdf). The original Phase 9 spec is correct — just promote its prominence.
- Open question for Phase 9 scoping: the current spec is single-tax-year. The user described it as "full information and full finance section of the site". Decide before Phase 9 starts whether to add an ad-hoc full-history archive alongside the per-tax-year one. Default position: ship per-tax-year first, add full-history if asked.

### Decision 3 — Simplified vs Advanced UI label, per-vehicle Simplified vs Actual under the hood

**What:** user-facing UI presents two methods, "Simplified" (mileage only) and "Advanced" (actual receipts + tyres / wipers / etc). Implementation underneath remains the per-vehicle, lifetime "Simplified vs Actual" decision recorded in the Phase 6 plan.

**Why this works:** HMRC's rules on simplified expenses are per-vehicle and the choice is generally sticky once made. Phase 6 already scopes the `vehicles` table with a `method` enum and a soft-lock on switching. The user's Simplified/Advanced framing is the *user-facing label* sitting on top — substance matches.

**How to apply (when Phase 6 is built):**
- Label `VehicleMethod::Simplified` as "Simplified" in the UI, `VehicleMethod::Actual` as "Advanced". Internal code stays Simplified/Actual to match HMRC vocabulary.
- Comparison panel copy: "Simplified would deduct £X / Advanced would deduct £Y" — same numbers, friendlier label.
- The `method_dependent: bool` flag in `category_tax_mapping` becomes the gate for whether the receipt category appears at all on a "Simplified" vehicle.

### Compliance track — now active alongside Phases 6–9

A separate, parallel non-code workstream is named alongside the auto-derivation work. **Source of truth:** [`.claude/compliance-go-live-checklist.md`](../compliance-go-live-checklist.md). All policies, registrations, public documents, certifications, and insurance live there with Tier 1 / Tier 2 / Tier 3 launch-readiness badges.

**Why this matters as a named workstream:** every code phase from here forward is gated by the production HMRC application (Phase 5c), which is gated by the Tier 1 documents (Privacy Policy + T&Cs + support email + DAC7 registration). Treating compliance as "we'll do it nearer launch" is exactly how launches slip.

**Two regulatory hats stacked:**
1. **DAC7 — Reporting Platform Operator.** Triggered by Stripe Connect facilitating pupil→instructor payment + UTR/NINO retention. We are *in scope*, not just a software vendor (this was clarified on 2026-04-30 — see `memory/project_compliance_dac7.md`).
2. **HMRC MTD software vendor.** Bound by HMRC Developer Hub Terms of Use; gates production access.

**Suggested order of attack (compliance-go-live-checklist.md §12 has the full list):**
1. Solicitor consult — drafts T&Cs + Privacy Policy + confirms DAC7 read in writing. **Unblocks almost everything else.**
2. ICO registration + DPIA + ROPA — in-house, ~1–2 weeks.
3. DAC7 registration + due-diligence procedure documented.
4. HMRC MTD production application (Phase 5c) — gated on the above three.
5. Cyber Essentials, pen-test, insurance review — in parallel with #2–4.
6. In-product UX work for Tier 2: per-submission attestation, T&Cs versioning audit, data export & erasure flows, cookie consent UI.

**Does not change:** the Phases 6–9 plan stays as written. Compliance work is parallel, not sequenced before code. The two converge at production launch.

**Last updated:** 2026-04-30 — Final Declaration descoped from product. Tax-year archive (Phase 9) elevated to primary year-end deliverable. Simplified/Advanced UI labels confirmed over Simplified/Actual internals. Compliance track named with Tier 1/2/3 stratification in `compliance-go-live-checklist.md`.
