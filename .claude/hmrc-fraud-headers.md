# HMRC Fraud Prevention Headers — `WEB_APP_VIA_SERVER`

Source: <https://developer.service.hmrc.gov.uk/guides/fraud-prevention/connection-method/web-app-via-server/>
Validator: <https://developer.service.hmrc.gov.uk/api-documentation/docs/api/service/txm-fph-validator-api/1.0>

## Connection method

DRIVE declares `Gov-Client-Connection-Method: WEB_APP_VIA_SERVER`.
The instructor authenticates in their browser; DRIVE's server makes the API calls.

## Required headers

### Gov-Client-* (originating device)

| Header | Source in DRIVE | Notes |
|---|---|---|
| `Gov-Client-Connection-Method` | constant `WEB_APP_VIA_SERVER` | always |
| `Gov-Client-Browser-JS-User-Agent` | `navigator.userAgent` from fingerprint | as reported when US-ASCII; percent-encoded only if it contains other characters |
| `Gov-Client-Device-ID` | `HmrcDeviceIdentifier::forUser()` | UUID, stable across token churn (cookie-mirrored) |
| `Gov-Client-Multi-Factor` | session `hmrc.mfa`, set on a successful Fortify two-factor login | omit when this session did not complete MFA. Format `type=TOTP&timestamp=…&unique-reference=…` with the timestamp percent-encoded |
| `Gov-Client-Public-IP` | `$request->ip()` | omit if not a public address. Set `TRUSTED_PROXIES` behind a load balancer |
| `Gov-Client-Public-IP-Timestamp` | captured with the IP | UTC `yyyy-MM-ddThh:mm:ss.sssZ` |
| `Gov-Client-Public-Port` | client TCP source port | `REMOTE_PORT` only when the TCP peer is the client. Otherwise `HMRC_CLIENT_PUBLIC_PORT_HEADER`. Never 80 or 443 |
| `Gov-Client-Screens` | fingerprint | `width=…&height=…&scaling-factor=…&colour-depth=…`, comma-separated for multiple monitors |
| `Gov-Client-Timezone` | fingerprint offset minutes | `UTC±hh:mm` |
| `Gov-Client-User-IDs` | sign-in email plus internal id | `drivecrm=<email>&drivecrm-id=<id>`, keys and values percent-encoded |
| `Gov-Client-Window-Size` | fingerprint | `width=…&height=…` |

### Gov-Vendor-* (DRIVE itself)

| Header | Source in DRIVE | Notes |
|---|---|---|
| `Gov-Vendor-Forwarded` | `by=<vendor-public-ip>&for=<client-public-ip>` | sent only when both addresses are public. IPv6 values are percent-encoded |
| `Gov-Vendor-Product-Name` | `HMRC_VENDOR_PRODUCT_NAME` (default `Drive CRM`) | whole value percent-encoded |
| `Gov-Vendor-Public-IP` | `HMRC_VENDOR_PUBLIC_IP` | public IP of the TLS edge the browser connects to |
| `Gov-Vendor-Version` | `drivecrm=<HMRC_VENDOR_VERSION>` | key and value percent-encoded |
| `Gov-Vendor-License-IDs` | `HMRC_VENDOR_LICENSE_KEY` | omitted when unset. This web app has no license installed on the device. When a key is configured it is SHA-256 hashed per user |

Key-value headers use RFC 3986 percent-encoding (`%20`, never `+`). Separators `=`, `&` and `,` are not encoded.

### Headers not sent for `WEB_APP_VIA_SERVER`

- `Gov-Client-Browser-Plugins`
- `Gov-Client-Browser-Do-Not-Track`
- `Gov-Client-Local-IPs` / `Gov-Client-Local-IPs-Timestamp`
- `Gov-Client-MAC-Addresses`

## Test Fraud Prevention Headers API

Sandbox only (`HMRC_ENVIRONMENT=sandbox`).

`GET {sandbox_api}/test/fraud-prevention-headers/validate`

- Application-restricted. DRIVE obtains a client-credentials token; the instructor's user token is not accepted.
- The fraud-prevention headers are the request headers being validated.
- `Accept: application/vnd.hmrc.1.0+json`
- The sandbox application must be subscribed to Test Fraud Prevention Headers.
- UI: diagnostics card on the HMRC connection panel, `POST /hmrc/test/fraud-headers`.
- The same header builder is used for VAT and ITSA MTD calls.

Unattended scheduler pulls do not call HMRC. They have no originating browser, so they cannot populate this connection method. Instructors refresh obligations from a signed-in session.

## Environment

| Variable | Purpose |
|---|---|
| `HMRC_ENVIRONMENT` | `sandbox` to allow the validator |
| `HMRC_CLIENT_ID` / `HMRC_CLIENT_SECRET` | user OAuth and the validator's client-credentials token |
| `HMRC_VENDOR_PUBLIC_IP` | `Gov-Vendor-Public-IP` and the `by` hop |
| `HMRC_VENDOR_PRODUCT_NAME` | marketed product name |
| `HMRC_VENDOR_VERSION` / `HMRC_VENDOR_VERSION_KEY` | `Gov-Vendor-Version` |
| `HMRC_USER_ID_KEY` | key used in `Gov-Client-User-IDs` |
| `HMRC_VENDOR_LICENSE_KEY` | optional; hashed into `Gov-Vendor-License-IDs` |
| `HMRC_CLIENT_PUBLIC_PORT_HEADER` | proxy header carrying the client source port |
| `TRUSTED_PROXIES` | so `$request->ip()` is the instructor's public IP |
