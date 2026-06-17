# Show Lesson Cost in Signed-Off Lesson Summary — Results

## What you asked for

> When a student lesson has been signed off, the user can click "View summary".
> Update that summary view so it also shows the cost of the lesson, and make
> sure the lesson cost is visible in the signed-off lesson summary wherever
> "View summary" is shown.

## What you'll now see

After this change, every place we display a signed-off lesson summary now also
shows the **lesson cost** — formatted as British pounds (e.g. `£45.00`).

There are two surfaces in the app where an instructor can read the summary of
a lesson they've already signed off, and both now include the cost:

### 1. Student profile → Lessons tab → "View Summary" button

When you open a student, go to their **Lessons** tab and click **"View Summary"**
on a completed lesson, the dialog now shows:

- 📅 Date and time (existing)
- 💷 **Lesson cost** — new
- 📝 The summary you wrote when signing off (existing)

The cost appears in a clear, labelled row above the summary text, with a £
icon and the price formatted as GBP.

### 2. Schedule tab → Completed lesson side panel

When viewing your calendar and opening a lesson that has already been
completed, the side panel that shows the lesson details now also shows:

- 💷 **Lesson Cost** — new (right above the existing "Lesson Summary" block)

## What changed under the hood

Four small files, all minimal:

| File | Change |
|---|---|
| `resources/js/components/Instructors/Tabs/Student/LessonsSubTab.vue` | Added a cost row inside the "View Summary" dialog. |
| `resources/js/components/Instructors/Tabs/ScheduleTab.vue` | Added a "Lesson Cost" line in the completed-lesson side panel and a local GBP formatter. |
| `resources/js/types/instructor.ts` | Added `amount_pence` to the calendar item TypeScript type. |
| `app/Actions/Instructor/GetInstructorCalendarAction.php` | Surfaced the lesson cost in the calendar item payload so the Schedule tab has the value to render. |

### No DB changes
The lesson cost (`amount_pence` on the `lessons` table) was already stored —
this task only had to make it visible in the right places. No migration, no
schema change, no API contract break.

### No new endpoints
We added one field (`amount_pence`) to the existing calendar item response.
Existing API consumers continue to work; they simply gain a new field they
can ignore if they don't need it.

## Notes for QA

To verify on a local environment:
1. Find or create a student with a **completed/signed-off** lesson (the lesson
   must have `status = completed` and a non-empty `summary`).
2. Go to the student's **Lessons** sub-tab and click **"View Summary"** on the
   completed lesson — confirm the dialog now shows a "Lesson cost" row above
   the summary text, with a £ amount.
3. Go to the instructor's **Schedule** tab, find the same completed lesson on
   the calendar and click it — confirm the right-hand side panel now shows a
   "Lesson Cost" line above the "Lesson Summary" line.
4. The cost should always be in GBP (e.g. `£45.00`).

## Confidence score: **9 / 10**

**Why 9 and not 10:** The change itself is small, low-risk and uses existing
data that was already on the lesson record. The two display surfaces have
been updated and the data is wired end-to-end. I've held back one point only
because, per project workflow rules, automated tests and Pint formatting are
left for you to run — so the changes haven't been run through your test
suite from inside this task.

**Why 9 and not lower:**
- Scope is tight: four files, no DB changes, no new endpoints.
- `amount_pence` was already in the Lessons API payload, so the primary
  "View Summary" change is purely a UI addition with no risk of regression.
- The Schedule tab change adds one optional field to an existing payload —
  backward compatible by construction.
- The formatter (`formatCurrency`) mirrors the existing GBP formatter already
  used elsewhere in the same component, so the output style is consistent.
