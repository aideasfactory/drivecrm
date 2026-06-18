# Results — Lesson Package Pricing Label Fix

## What was wrong
On the onboarding "Pick a package" step, each package card showed its lessons
count (e.g. *10 lessons*) and the total price (e.g. *£350*), and underneath had
a smaller breakdown of the unit price labelled **"per hour"**. The value itself
was correct — it was the price per lesson — but the wording mixed up lessons
and hours and could mislead a student into thinking each lesson was an hour
shorter or longer than they expected, or worse, that the school was suggesting
an inflated hourly rate.

## What was changed
Both the discounted and full-price variants of the price label on Step 3 of the
onboarding flow now read **"per lesson"** instead of **"per hour"**.

For the example in the brief — a 10-lesson package totalling £350 — the small
print under the headline price now reads "£35.00 per lesson" (or the
discounted equivalent if a promotion applies), matching the wording already
used on the order summary step, the instructor booking dialog, and the
instructor's package management screen.

## Files touched
- `resources/js/pages/Onboarding/Step3.vue` — two label strings updated.

## Scope check
- Confirmed the underlying value (`formatted_lesson_price`) is calculated in the
  `Package` model as total price divided by lesson count, so it has always been
  a per-lesson figure. Only the label was wrong; no pricing logic, schema, or
  totals were affected.
- Verified the rest of the codebase already uses "per lesson" / "/lesson"
  wording on every other surface that shows a unit price — this change brings
  Step 3 into line with the existing pattern.
- No backend, database, or API changes were needed.

## Confidence score
**9 / 10**

A purely textual change on a single Vue template, in two adjacent lines, with no
side effects and the rest of the app already using the new wording. The one
point withheld reflects that the fix wasn't visually confirmed in a running
browser (per project rules, automated tests and the dev server were not
invoked); a quick eyeball on staging before release is recommended.
