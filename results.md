# Fix — Instructor self-add sign-up flow

## What was wrong

When Sam tried to add himself (or any new person) as an instructor and pressed the **Create Instructor** button on the "Add New Instructor" sheet, nothing visible happened. The sheet closed, no instructor was created, and there was no error message anywhere on the page. The cause was **not** a maximum instructor limit — there is no such limit in the product.

Two issues combined to produce the silent failure:

1. **The backend was swallowing failures.** `InstructorController::store()` called `InstructorService::createInstructor()` and ignored its return value. The service used a legacy "return `success: false`" pattern, so whenever something went wrong inside (most commonly the postcode-to-coordinates lookup returning nothing), the controller never noticed and still redirected to the Instructors index page as though the create had succeeded. From the user's point of view, the form closed and the new instructor was simply missing from the list.
2. **The form's postcode was optional in validation but required by the service.** Because the request validator didn't require a postcode, a user could submit without one. The service would then fail the lookup and the controller would still redirect — the classic silent failure.

A secondary contributor: even when the backend *did* return a validation error (for example, the email already existed because Sam was trying to add himself with his own account email), the **Add Instructor** sheet only displayed the error inline beneath the email field. It showed no toast or banner, so a user focused on the submit button could easily miss it.

## What was changed

**Backend**
- `app/Http/Requests/StoreInstructorRequest.php` — `postcode` is now `required`, with a clear, user-facing validation message.
- `app/Services/InstructorService.php` — `createInstructor()` was refactored from "return an array with a `success` flag" to "return the `Instructor` model or throw a `ValidationException`". When the postcode lookup cannot find coordinates, it now throws a `ValidationException` keyed to the `postcode` field, so Laravel's existing pipeline turns that into a 422 response that Inertia surfaces straight back to the form.
- `app/Http/Controllers/InstructorController.php` — `store()` now relies on the service to throw on failure, and adds a `success` flash message on the happy path.

**Frontend**
- `resources/js/components/Instructors/AddInstructorSheet.vue` — both create and update paths now fire a toast on success and a destructive toast on error, with the first field-level message used as the toast body. The postcode field is marked with the `*` to match the new validation rule.

**Tests**
- `tests/Feature/Instructors/InstructorSelfAddTest.php` — new Pest tests that pin down every failure mode for the self-add flow: existing-email rejection, missing postcode, unresolvable postcode, and the happy path.
- `tests/Feature/Instructors/InstructorTransmissionTypeTest.php` — updated existing tests to include a postcode (now required) and to stub the postcodes.io HTTP call with `Http::fake()`, so they continue to verify transmission-type behaviour rather than fall over on the new validation rule.

## What the user will now see

- Submit the form with a missing or unresolvable postcode → a red toast appears explaining the issue, and the postcode field shows the inline error. No more silent close.
- Submit with an email that already exists (e.g. Sam's own login email) → a red toast appears with "This email address is already in use." and the email field highlights inline.
- Submit with a valid set of details → a confirmation toast appears, the sheet closes, and the new instructor shows in the list.

There is no maximum-instructor limit and we intentionally did **not** introduce one — that would be a product decision, not a bug fix.

## Files touched

- `app/Http/Controllers/InstructorController.php`
- `app/Http/Requests/StoreInstructorRequest.php`
- `app/Services/InstructorService.php`
- `resources/js/components/Instructors/AddInstructorSheet.vue`
- `tests/Feature/Instructors/InstructorSelfAddTest.php` (new)
- `tests/Feature/Instructors/InstructorTransmissionTypeTest.php`
- `.claude/tasks/current-task.md`

## Confidence: 9 / 10

Why high confidence:

- The root cause was a single, well-localised pattern (service returns `success: false`, controller ignores it). Replacing it with Laravel's native `ValidationException → 422 → Inertia onError` pipeline removes the silent-failure path entirely; it is the same path the rest of the codebase already uses successfully.
- Every failure mode is now backed by a new Pest test, including the exact "Sam adds himself" scenario (existing email + own login).
- The frontend change is additive — toasts plus the existing inline errors — so no existing happy-path behaviour is disturbed.

Why not a 10:

- Tests were not executed in this environment per project rules, so the assertion about the `errors.postcode` toast text is verified by reading the code rather than seeing it render. Manual smoke-testing on `npm run dev` is recommended before deploy to confirm the toast renders inside the Add Instructor sheet's mounted Toaster context.
