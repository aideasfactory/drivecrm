# HMRC Fraud Prevention Headers — `WEB_APP_VIA_SERVER`

Internal reference captured from HMRC's developer hub on 2026-04-28.
Source: <https://developer.service.hmrc.gov.uk/guides/fraud-prevention/connection-method/web-app-via-server/>

## Connection method

DRIVE declares `Gov-Client-Connection-Method: WEB_APP_VIA_SERVER`.
The instructor authenticates in their browser; DRIVE's server makes the API calls.

## Required headers

### Gov-Client-* (originating device)

| Header | Source in DRIVE | Notes |
|---|---|---|
| `Gov-Client-Connection-Method` | constant `WEB_APP_VIA_SERVER` | always |
| `Gov-Client-Browser-JS-User-Agent` | `navigator.userAgent` from fingerprint | as-reported |
| `Gov-Client-Device-ID` | `HmrcDeviceIdentifier::forUser()` | UUID, stable across token churn (cookie-mirrored) |
| `Gov-Client-Multi-Factor` | session MFA marker (conditional) | omit if user didn't 2FA in this session; format `type=TOTP&timestamp=…&unique-reference=…` |
| `Gov-Client-Public-IP` | `$request->ip()` | conditional — omit if obviously private (TrustProxies misconfig) |
| `Gov-Client-Public-IP-Timestamp` | `now()->format('Y-m-d\TH:i:s.v\Z')` | UTC, millisecond precision |
| `Gov-Client-Public-Port` | `$request->server('REMOTE_PORT')` | conditional — omit if proxy stripped it (log warning) |
| `Gov-Client-Screens` | fingerprint | `width=…&height=…&scaling-factor=…&colour-depth=…` (list format if multiple monitors) |
| `Gov-Client-Timezone` | fingerprint offset minutes | format `UTC±hh:mm` |
| `Gov-Client-User-IDs` | `drivecrm=<user_id>` | percent-encoded |
| `Gov-Client-Window-Size` | fingerprint | `width=…&height=…` |

### Gov-Vendor-* (DRIVE itself)

| Header | Source in DRIVE | Notes |
|---|---|---|
| `Gov-Vendor-Forwarded` | `by=<server-public-ip>&for=<client-public-ip>` | document each TLS-terminating hop |
| `Gov-Vendor-Product-Name` | `Drive%20CRM` (percent-encoded) | constant |
| `Gov-Vendor-Public-IP` | env `HMRC_VENDOR_PUBLIC_IP` (production) | conditional — omit if not configured |
| `Gov-Vendor-Version` | `drivecrm=v<APP_VERSION>` | from env / composer.json |
| `Gov-Vendor-License-IDs` | — | DRIVE has no third-party licences inside the request path; omit |

### Headers explicitly NOT required for `WEB_APP_VIA_SERVER`

- `Gov-Client-Browser-Plugins` — desktop/mobile only
- `Gov-Client-Browser-Do-Not-Track` — desktop/mobile only
- `Gov-Client-Local-IPs` / `…-Timestamp` — desktop/mobile only
- `Gov-Client-MAC-Addresses` — desktop/mobile only

We do not capture or send these. The fingerprint table only stores fields HMRC currently requires for our connection method.

## Test validator endpoint

`POST {api_base}/test/fraud-prevention-headers/validator/validate`

- Send the fraud-prevention header set; HMRC echoes back JSON with `errors[]` and `warnings[]`.
- Sandbox base: `https://test-api.service.hmrc.gov.uk`
- Subscribed via the "Test Fraud Prevention Headers (MTD) 1.0" API (already on the DRIVE app subscription list).
- This endpoint is itself a real HMRC API call, so it requires the fraud headers it is validating.

## Operational notes

- `Gov-Client-Public-Port` is often missing behind cloud proxies — accept its absence rather than forcing a 422.
- `Gov-Vendor-Public-IP` for prod must be a real outbound public IP. In Herd/sandbox we omit and accept the warning.
- `Gov-Client-Multi-Factor` is included only when the user actually 2FA'd in the current session.
