# Task: Slim mobile sign-off to match admin

**Created:** 2026-09-18
**Last Updated:** 2026-09-18
**Status:** Complete

Mobile sign-off should mirror admin: `{ "summary": "..." }` only, then
the existing LessonSignOffService. The four-prompt reflective log is
leftover and must not be required.

Shared pipeline (SignOffLessonAction, payout, job) stays as on main.
The API controller runs that same service in-request and returns the
completed lesson so the app can clear Needs Sign Off.
