# Results — Booking form: remove "Both" transmission option

## What was changed

The public booking form at **/booking** previously offered three transmission
choices: **Manual**, **Automatic**, and **Both**. The "Both" option has now
been removed. Prospects can only choose Manual or Automatic.

The change was made in two places so the form is locked down from both ends:

1. **The dropdown itself** (`resources/js/pages/Booking/Step1.vue`)
   The `<option value="both">Both</option>` line was removed from the
   transmission `<select>`. A new visitor sees only Manual and Automatic.

2. **The server-side validator** (`app/Http/Requests/Booking/StepOneRequest.php`)
   The `transmission` field's `in:manual,automatic,both` rule was tightened to
   `in:manual,automatic`. Even if someone tampered with the page or scripted a
   POST to the endpoint, a `transmission=both` submission is now rejected with
   the standard "Please choose a transmission preference" message.

## What was deliberately left alone

To keep the change clean and avoid side-effects elsewhere in the system:

- **Existing enquiries** in the database that already hold
  `transmission=both` from before this change are untouched. They continue to
  render in the admin email as "Either / no preference" and in the Enquiries
  index as "Either", so historical data isn't broken.
- **The instructor management form** (used internally to add instructors)
  still has a "Both" option, because that describes which gearboxes an
  instructor can teach — a different concept from a learner's preference.
  This was out of scope for the brief.
- **Configuration mapping** in `config/booking.php` that pairs `both` with an
  instructor ID is unchanged. It is no longer reachable from the public form,
  but removing it would risk other internal tooling and is outside this
  ticket's scope.

## Files modified

- `resources/js/pages/Booking/Step1.vue`
- `app/Http/Requests/Booking/StepOneRequest.php`

## How to verify in the browser

1. Open `/booking`.
2. Fill the form to the transmission question.
3. The dropdown should show **Manual** and **Automatic** only — no "Both".
4. Submit normally — booking flow continues as expected.

## Confidence score

**9 / 10**

Reasoning: this is a small, well-bounded UI + validation change with both
front-end and server-side coverage. The two edits are localised, the
behaviour is easy to reason about, and the change has been kept narrowly
within the public booking form so historical data and unrelated internal
features keep working. The one point withheld is because the change has
not been visually loaded in a running browser as part of this task —
per project rules tests/linters/dev-server commands are run by the user,
not the agent.
