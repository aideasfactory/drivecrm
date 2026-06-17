# Onboarding Refund Policy Copy Update — Results

## What changed
The refund policy line in the onboarding form's left sidebar has been updated so the cancellation window matches the current policy.

**Before:** "Full refund policy - cancel up to 24 hours before"
**After:**  "Full refund policy - cancel up to 48 hours before"

## Where it lives
The copy appeared in two places in the onboarding journey, and both have been updated so the wording stays consistent throughout the flow:

1. `resources/js/components/Onboarding/OnboardingLeftSidebar.vue` — the shared sidebar used across the onboarding steps.
2. `resources/js/pages/Onboarding/Step1.vue` — an inline sidebar copy on Step 1 of the onboarding form.

## What was checked
- Searched the codebase for every occurrence of "Full refund policy" and "24 hours" to make sure nothing referencing the refund window was missed.
- Confirmed remaining "24 hours" mentions elsewhere (e.g., `Step6.vue`) refer to invoice-email timing — unrelated to the refund policy and intentionally left alone.
- Verified the post-change copy renders consistently in both updated files.

## Risk & impact
- Pure copy change — no business logic, validation, or booking behaviour touched.
- No database, API, or migration impact.
- No tests required for a static-string copy change.

## Confidence score
**9 / 10**

Why not 10: the wording change is straightforward and verified in both files, but the actual refund-policy enforcement (e.g., how cancellations are processed downstream) is policy/operational and is outside the scope of this UI text change — please make sure the operations/policy side reflects 48 hours too if that wasn't already aligned.
