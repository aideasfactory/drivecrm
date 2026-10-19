# Google Ads conversions & enhanced conversions (GTM)

Conversion action: **app_booking_thankyou (web)** — Google Ads account **AW-10884289539**.
GTM container: **GTM-56KMNJR** (`services.gtm.container_id`), consent via Usercentrics (`services.usercentrics.settings_id`).

GTM and Usercentrics load only on `booking.*`, `onboarding.*` and `payment-link.*` routes (`resources/views/app.blade.php`).
Google Ads and GA4 are **not** in the code any more — they must be configured in GTM.

## Why the conversion stopped pinging (last ping 13 Oct 2026)

Until 14 Oct the layout loaded `gtag.js` directly and ran `gtag('config', 'AW-10884289539')` on booking/onboarding pages.
That hard-coded Google tag is what reported `app_booking_thankyou` (with Automatic enhanced conversions) when the learner landed on the thank-you page after Stripe.

Commit `a1de4f7` ("gtm marker update", 14 Oct 21:22) removed that `gtag.js` block and the `services.google_tag` config, moved all tracking into GTM behind Usercentrics, and added a `purchase` dataLayer event on the success pages.
From then on the conversion only fires if the GTM container has a Google Ads Conversion Tracking tag listening for that event. If the container has no such tag, nothing reports the conversion.

The thank-you page redesign (`3f16e24`, same day) kept the route (`onboarding.complete`) and URL, so it didn't cause this by itself.

Two smaller problems are fixed in code:

- The `purchase` push ran as soon as the page mounted. Usercentrics loads async, so GTM could evaluate the tags before the consent default was set.
- The server reports each order only once (`orders.purchase_tracked_at`). If the visitor hadn't accepted cookies yet, nothing reported the conversion again after they accepted.

## dataLayer contract (what the code pushes)

Pushed on `Onboarding/Complete` and `PaymentLink/Success`, once per order (server claim + localStorage):

```js
// 1. Always, once consent default is known (or after 2.5s)
{
  event: 'purchase',
  transaction_id: '123',          // order id — Ads dedupes on this
  value: 499.00,
  currency: 'GBP',
  items: [{ item_name, item_id, price, quantity: 1 }],
  user_data: {                    // ONLY when ad_user_data is granted
    email: 'jane@example.com',    // lower-cased
    phone_number: '+447700900123',// E.164
    address: { first_name: 'Jane', last_name: 'Doe', postal_code: 'SW1A1AA', country: 'GB' }
  }
}

// 2. Only if ad_storage was denied at purchase time and the visitor accepts while on the page
{ event: 'purchase_consent_granted', /* same fields as above, user_data if ad_user_data granted */ }
```

`user_data` is the person who paid (the booker when booking for someone else). The postcode is the learner's default pickup postcode, included only when the learner booked for themselves. Any field may be missing.

## GTM configuration (container GTM-56KMNJR)

1. **Variables** (Data Layer Variable, version 2):
   - `DLV - transaction_id` → `transaction_id`
   - `DLV - value` → `value`
   - `DLV - currency` → `currency`
   - `DLV - user_data` → `user_data`
   - **User-Provided Data** variable `UPD - purchase`: type *User-Provided Data* → *Code* → return `{{DLV - user_data}}`.
     (Alternatively *Manual configuration* mapping Email → `user_data.email`, Phone → `user_data.phone_number`, First name → `user_data.address.first_name`, Last name → `user_data.address.last_name`, Postal code → `user_data.address.postal_code`, Country → `user_data.address.country`.)
2. **Triggers** (Custom Event):
   - `CE - purchase` → event name `purchase`
   - `CE - purchase_consent_granted` → event name `purchase_consent_granted`
3. **Conversion Linker** tag → trigger *All Pages* (needed for gclid/wbraid cookies once consent is granted).
4. **Google tag** `AW-10884289539` → trigger *Initialization - All Pages*. This replaces the removed `gtag('config', 'AW-10884289539')`.
5. **Google Ads Conversion Tracking** tag `Ads - app_booking_thankyou`:
   - Conversion ID `10884289539`, Conversion Label from Google Ads (*Goals → Conversions → app_booking_thankyou → Tag setup → Use Google Tag Manager*)
   - Conversion value `{{DLV - value}}`, Transaction ID `{{DLV - transaction_id}}`, Currency code `{{DLV - currency}}`
   - Tick **Include user-provided data from your website** → `{{UPD - purchase}}`
   - Triggers: `CE - purchase` **and** `CE - purchase_consent_granted` (Ads dedupes the second fire by transaction ID)
   - Consent settings: rely on built-in consent checks (Google tags understand `ad_storage` / `ad_user_data`). Don't block it with an extra Usercentrics trigger exception, or the post-consent fire never sends.
6. **GA4** purchase event tag (G-NBYWT0EZF6) → trigger `CE - purchase` only. The payload is flat, not under `ecommerce`, so map `transaction_id`, `value`, `currency` and `items` as event parameters.
7. Remove or pause any old URL/page-view-based tag for `app_booking_thankyou`, so the same sale isn't counted twice.
8. Preview with Tag Assistant using a real test checkout: check that `purchase` has `user_data` after you accept cookies, that the Ads conversion tag fires, and that Tag Assistant shows *User-provided data*. Then **Submit/Publish** the container.

## Google Ads

- *Goals → Settings → Enhanced conversions for web*: on, method **Google Tag Manager**, and accept the customer data terms.
- Diagnostics (the "Implement in-page code" alert) usually update within 48–72 hours of the first conversions with user-provided data.

## Usercentrics

- Turn on **Google Consent Mode v2** in the Usercentrics admin. The code reads `gtag('consent', 'default'|'update', …)` entries from the dataLayer to decide when to send `user_data` and the post-consent fire. Without them, `user_data` is never sent and the conversion is never fired again after consent.
- Map the Google Ads services (Conversion Tracking / Remarketing) to marketing consent, so accepting grants `ad_storage` and `ad_user_data`.
