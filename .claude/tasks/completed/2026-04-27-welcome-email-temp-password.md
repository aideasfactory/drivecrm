# Task: First-Time Pupil Welcome Email (Temp Password) on Onboarding

## Overview

When a pupil completes the **web onboarding flow** for the first time (regardless of upfront vs weekly payment) AND they were newly created in the database during that onboarding, send them a welcome email containing their temporary password so they can log in.

If the user already existed before the onboarding (i.e. a returning pupil who came back through the form), do **not** send the welcome email — they already have a password.

Mobile API path is **out of scope** — pupils registering through the app set their own password; only the web onboarding pipeline issues a temp password.

**Reuse:** `WelcomeStudentNotification` already exists and includes the temp password. We do not need a new mailable.

---

## Phase 1: Planning ✅

### Convergence point

Both web payment routes ultimately call `SendOrderConfirmationEmailAction::execute($order, $student)`. There are 4 callers:

1. `StepSixController::success()` line 446 — weekly path, after order activation
2. `StepSixController::success()` line 489 — upfront path, after Stripe session verified at success URL
3. `WebhookController::sendOrderConfirmationEmail()` line 540 — upfront path, via `checkout.session.completed` webhook
4. `OrderService::bookLessons()` line 90 / `OrderService::verifyCheckout()` line 209 — mobile API (always existing user → no-op for our purposes)

→ **Single edit point:** `SendOrderConfirmationEmailAction`. Hook the welcome-email send there. All four web paths covered automatically; mobile path is naturally a no-op because the new-user flag is never set for mobile bookings.

### "Is this a new user?" persistence

Add a `welcome_email_pending` boolean column on `users`:

- Default `false`.
- Set to `true` ONLY in `CreateUserAndStudentFromEnquiryAction` when `User::create()` runs (i.e. when `$isNewUser = true`).
- Cleared (`false`) the moment the welcome email is dispatched.
- Naturally guards against double-send when both Stripe webhook AND success page fire `SendOrderConfirmationEmailAction` for the same order — first one wins via atomic conditional update.
- Naturally fails closed for returning users — we never set the flag for them.

Why a column on `users` (not `orders` or enquiry step6 data)? The email is conceptually about the user account, not the order. Survives the time gap between user creation (in `store()`) and payment success (which can come hours later via webhook). Also clean for future re-use if any other path needs to know "this user has a pending temp-password welcome email".

### Password handling

The placeholder `Hash::make(Str::random(32))` set during `CreateUserAndStudentFromEnquiryAction` is just a NOT-NULL filler that no one knows. At welcome-email time we:

1. Generate a fresh `Str::random(12)` plaintext (mirrors `CreatePupilAction`).
2. `Hash::make()` it and save to `users.password`.
3. Pass the plaintext into `WelcomeStudentNotification($plaintext, $instructor)`.

No plaintext is persisted anywhere. `password_change_required` is already `true` from user creation, so first login will force a change.

### Atomic claim to prevent double-send

```php
$claimed = User::where('id', $user->id)
    ->where('welcome_email_pending', true)
    ->update([
        'password' => Hash::make($plaintext),
        'welcome_email_pending' => false,
    ]);

if ($claimed === 0) {
    return; // either not a new user, or already claimed by a parallel handler
}

Notification::route('mail', $user->email)
    ->notify(new WelcomeStudentNotification($plaintext, $instructor));
```

Recipient: ALWAYS `$user->email` (the person who will log in). For `owns_account = false` bookings, that is the **learner**, not the contact — the contact gets the order confirmation, but only the user with login credentials gets the welcome email.

### Decisions

- [x] Reuse `WelcomeStudentNotification` — no new mailable.
- [x] Single hook in `SendOrderConfirmationEmailAction` covers all 4 web call sites.
- [x] Mobile is naturally excluded (flag never set there).
- [x] Atomic conditional update prevents double-send under webhook+success-page race.
- [x] Recipient is `$user->email` regardless of `owns_account`.

---

## Phase 2: Implementation ✅

### Steps

- [x] **Migration:** added `welcome_email_pending` boolean (default `false`) on `users` after `password_change_required` in `2026_04_27_193134_add_welcome_email_pending_to_users_table.php`.
- [x] **User model:** added `welcome_email_pending` to `$fillable` and to `casts()` as `'boolean'`.
- [x] **CreateUserAndStudentFromEnquiryAction:** set `welcome_email_pending => true` in the `User::create([...])` payload (only the new-user branch — the existing-user `Reusing existing user` branch is untouched, so returning pupils never get the email).
- [x] **SendOrderConfirmationEmailAction:** added `sendWelcomeEmailIfPending(Order, Student)`, called from `execute()` after the existing order-confirmation send. Imports `User`, `WelcomeStudentNotification`, `Hash`, `Str`. Atomic claim via `User::where('id', …)->where('welcome_email_pending', true)->update([...])`. Wrapped in its own try/catch so a welcome-email failure does not break order confirmation. Logs activity entries for both the student and the instructor when fired (mirrors `CreatePupilAction`).
- [x] **database-schema.md:** added a `welcome_email_pending` row to the users column table and a description bullet in the "Key Tables & Relationships → users" summary.

### Files to change

| File | Change |
|------|--------|
| `database/migrations/<new>_add_welcome_email_pending_to_users_table.php` | New migration adding `welcome_email_pending` boolean default false |
| `app/Models/User.php` | Add `welcome_email_pending` to `$fillable` and `casts()` |
| `app/Actions/Onboarding/CreateUserAndStudentFromEnquiryAction.php` | Set `welcome_email_pending => true` when creating a new user |
| `app/Actions/Onboarding/SendOrderConfirmationEmailAction.php` | After the existing send, atomically claim + dispatch `WelcomeStudentNotification` |
| `.claude/database-schema.md` | Document the new column |

### Out of scope

- Mobile API path (no new-user creation there).
- Resending welcome emails (one-shot only — once `welcome_email_pending` is cleared, it stays cleared).
- A "resend temp password" feature for stuck pupils (use existing forgot-password flow).

---

## Phase 3: Reflection ✅

### What went well

- **Single edit point.** `SendOrderConfirmationEmailAction` is the natural convergence — both web payment routes and both webhook/success-page paths funnel through it. One call site, four covered triggers.
- **Reused `WelcomeStudentNotification`.** No new mailable. The notification's existing shape (`temporaryPassword` + `Instructor`) matches exactly what the onboarding flow has at email time.
- **Atomic claim pattern.** `where('welcome_email_pending', true)->update(...)` does the read+write in one statement, so even if the Stripe webhook and the user-facing success page fire `SendOrderConfirmationEmailAction` simultaneously, only one of them claims the dispatch — the second sees `affected_rows = 0` and returns. No double-send, no extra locking, no transaction.
- **Returning pupils naturally excluded.** The flag is only ever set on the new-user branch of `CreateUserAndStudentFromEnquiryAction`. Existing users hit the "Reusing existing user" branch where the flag stays at its default of `false`. No conditional logic needed in the email path.
- **Mobile API naturally excluded.** The mobile `OrderService::bookLessons` path never creates new users, so the flag is never set, and the existing call to `sendConfirmationEmail->execute(...)` in `OrderService` is a clean no-op for the welcome email. Zero risk of accidental sends to mobile-registered pupils.
- **No plaintext password persistence.** The placeholder hash set at user creation is just a NOT-NULL filler. The real plaintext is generated at email-send time, hashed onto the user, and passed straight into the queued notification — never stored in any extra column or cache.

### Anti-pattern check

- ✅ Migration documented in `database-schema.md` immediately (before marking the task complete).
- ✅ Action remains pure — no HTTP, no Inertia, no caching.
- ✅ No tests touched (per project rules).
- ✅ No `vendor/bin/pint` run, no `php artisan test`.
- ✅ No new Service class created — reused the existing Action call site.
- ✅ Welcome notification reused, not duplicated.
- ⚠️ `SendOrderConfirmationEmailAction` is now ~150 lines and conceptually does two things (order confirmation + first-login welcome). If a third "after-payment user-facing email" ever lands in the same convergence point, it would be worth extracting a `PostBookingNotificationDispatcher` service that owns the fan-out. For two emails, it stays inline.
- ⚠️ The atomic claim does the password update + flag-clear BEFORE the queue dispatch. If the queue dispatch itself fails synchronously (rare — Laravel just writes a row to the jobs table), the user has been issued a password that nobody knows. Acceptable trade-off because: (a) the alternative is a race-prone "dispatch then clear" pattern that would risk double-send, and (b) the user can fall back to forgot-password to recover. Logged loudly in the catch block.

### Technical debt / future considerations

- **No "resend temp password" path.** Once `welcome_email_pending` flips to false, it stays false. If a pupil never sees the email (spam folder, typo'd address) they go through forgot-password. Acceptable today; if we get support tickets, consider an instructor-facing "resend welcome" button that re-sets the flag and the password.
- **Activity logging mirrors `CreatePupilAction` verbatim.** If we later centralise welcome-email semantics (e.g. the same student is welcomed via two paths), the duplicate logging strings could be extracted. Not worth doing now.

### Score

**9 / 10.** Loses one point for the atomic-claim trade-off described above (password rotated before dispatch is confirmed). Otherwise: minimal additive change, no new mailable, single edit point, returning pupils + mobile users both naturally excluded with zero additional logic, and the new flag is simple enough to reason about (set in one place, cleared in one place, never re-set).

---

**Status:** All phases complete.
**Last Updated:** 2026-04-27
