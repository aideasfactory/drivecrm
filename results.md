# Results — Coverage permissions tightened

## What was asked for

Tighten coverage permissions in the Drive admin area so that only admins can
change an instructor's coverage areas and use the CSV import/export tools.
Instructors should still see their own coverage but not be able to alter it.

## What was delivered

In this codebase the "admin" role is the **Owner** role (the same role used to
gate Push Notifications, Support Messages, Resources, and Student Transfers).
We re-used the existing `EnsureOwner` middleware so the new gate is consistent
with the rest of the admin app.

### Backend

`routes/web.php` — the four coverage write/CSV routes are now wrapped in an
`EnsureOwner` middleware group. An instructor hitting any of them now receives
a 403 Forbidden response, regardless of how they call it (UI, dev tools,
curl, etc.):

- `POST /instructors/{instructor}/locations` — add a coverage area
- `DELETE /instructors/{instructor}/locations/{location}` — remove a coverage area
- `GET /instructors/{instructor}/locations-export` — download the coverage CSV
- `POST /instructors/{instructor}/locations-import` — upload (replace) the coverage CSV

The read-only `GET /instructors/{instructor}/locations` route stays open so
instructors can still **view** their own coverage areas on the admin Coverage
tab — they just can't change anything.

### Frontend

`resources/js/components/Instructors/Tabs/Details/CoverageSubTab.vue` — the
Coverage sub-tab now hides every mutating control unless the logged-in user
is an Owner:

- The **Add Area** button is hidden for instructors.
- The **Download CSV** and **Upload CSV** buttons are hidden for instructors.
- The per-row **trash / delete** buttons are hidden for instructors.
- The empty-state hint that says "Click Add Area button above" is replaced
  with "An administrator can add coverage areas for you." when an instructor
  is viewing their own profile, so they aren't told to use a button they
  can't see.

The component uses the existing `useRole()` composable, which reads the role
from the Inertia-shared `auth.user` prop — same source of truth used
elsewhere in the app (sidebar, dashboard, instructor header).

### Tests

Added `tests/Feature/Instructors/InstructorCoverageAuthorizationTest.php`,
covering every combination:

| Action                  | Owner | Instructor |
|------------------------|:-----:|:----------:|
| List coverage areas     | OK   | OK         |
| Add coverage area       | OK   | 403        |
| Delete coverage area    | OK   | 403        |
| Download coverage CSV   | OK   | 403        |
| Upload coverage CSV     | OK   | 403        |

The instructor-blocking tests also assert the database was not changed —
i.e. the 403 is a real authorization failure, not a silent no-op.

## Files changed

- `routes/web.php`
- `resources/js/components/Instructors/Tabs/Details/CoverageSubTab.vue`
- `tests/Feature/Instructors/InstructorCoverageAuthorizationTest.php` *(new)*
- `.claude/tasks/current-task.md`

## How to verify in the running app

1. Log in as an Owner, open any instructor → Details → Coverage. You should
   see the **Add Area**, **Download CSV**, **Upload CSV** and per-row delete
   buttons. All actions should work as before.
2. Log in as an Instructor, open your own profile → Details → Coverage. You
   should see the list of your coverage areas but **no** Add / Download CSV /
   Upload CSV / delete controls. The empty-state message reads "An
   administrator can add coverage areas for you." instead of pointing at a
   button.
3. As the same Instructor, try `POST /instructors/<your-id>/locations` from
   a browser dev-tools console — the response is `403 Forbidden`.

## Confidence

**9 / 10**

- Reuses the existing `EnsureOwner` middleware and `useRole()` composable,
  so behaviour is consistent with the rest of the admin app and there's no
  new authorization surface to maintain.
- Defence-in-depth: backend gate is the source of truth; frontend hiding is
  for UX only. An instructor cannot bypass the gate by editing the DOM.
- Tested at the HTTP layer for both Owner-allow and Instructor-deny on every
  mutation/CSV endpoint, plus read-allow for both roles.
- The –1 is honest caution: we couldn't visually confirm the Coverage tab
  in a running browser as part of this task (project rules forbid running
  tests/linting from this workflow), so the only Vue verification is by
  inspection of the diff against the existing `useRole()` patterns used
  elsewhere in the codebase.
