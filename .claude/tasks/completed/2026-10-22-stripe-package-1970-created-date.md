# Stripe packages showing "created 1 Jan 1970"

**Status:** ✅ Complete (investigation only, no code change) — 2026-10-22
**Ticket:** Mission Control, raised by Jack: "all of the created packages show on Stripe as being created 1st Jan 1970"

## Overview
Work out whether the app sends a bad timestamp when it creates a package's Stripe Product and Price.

## Phase 1: Investigation ✅
- ✓ Packages hit Stripe in one place, `StripeService::createProduct()` / `createPrice()`. They're called from `CreateAdminPackageAction`, `CreateInstructorPackageAction` and the `ResetStripeAccountData` command
- ✓ `products->create` sends only `name` and `description` (`"{lessons_count} driving lessons"`). `prices->create` sends only `product`, `unit_amount` and `currency`. No `created`, date or timestamp field and no `metadata`
- ✓ Git history: these payloads haven't changed since the initial commit, so no earlier version sent a date
- ✓ No API version is pinned (`stripe/stripe-php` v19.4.1 defaults) and there's no custom HTTP client, so nothing changes the request
- ✓ Checkout sessions that use inline `product_data` (booking fee, digital fee, Pass Your Test Guarantee, packages without a product id) also send no dates
- ✓ In the Stripe API, `created` on Product and Price is read-only. Stripe sets it to the request time (Unix seconds) and the create endpoints don't accept it

## Findings
The app can't set or affect the creation date Stripe shows. Whatever shows 1970 is Stripe's own value or the way it's displayed, not something we send.

1970-01-01 is what a Unix timestamp of `0` looks like. It's also what you get when a seconds timestamp is read as milliseconds: a 2026 timestamp of about `1.79e9` ms comes out as 21 Jan 1970.

To confirm, check one affected product directly:
`curl https://api.stripe.com/v1/products/prod_XXX -u sk_live_XXX:`
- If `created` is a sensible 2026 value, the Dashboard is displaying it wrongly. Raise it with Stripe support and include the product id
- If `created` really is `0`, the value is wrong on Stripe's side, because we never send it. Raise it with Stripe support

## Existing packages
They don't need fixing. Nothing in the app reads a Product or Price `created` value. Checkout uses `stripe_product_id` and `unit_amount`, and that works regardless of the date. `created` can't be changed after the object exists, so it can't be backfilled. The only way to get a new date would be new Products/Prices, which brings churn and risk for no functional gain.

## Reflection
No Stripe credentials were available in this environment, so the live objects weren't inspected. The `curl` check above settles which of the two cases applies.

💳 Payment impact: NONE — no code changed. `StripeService`, the package create actions and checkout are untouched.
