# Task: Admin Support Messages

## Overview

Add a "Support Messages" menu item in the admin sidebar (under Push Notifications). Students and instructors send messages from the mobile app with `to = 1` (user id 1 = the owner/support inbox). Admins see a two-column chat interface: conversations list on the left, full thread + reply composer on the right. Admin replies are written with `from = 1` so Support appears as a single virtual participant.

### Design Decisions

| Question | Decision |
|----------|----------|
| Support identity | There is no "support user". The admin page is simply the logged-in admin's messages inbox (`$request->user()`). Mobile app users send to the admin's user id (incidentally 1 in seed data). |
| FK constraints | Unchanged |
| Architecture | Reuse existing `MessageService` entirely — `getConversations($admin)`, `getConversationMessages($admin, $user)`, `sendMessage($admin, $user, $text)` |
| SendMessageAction signature | `Student`/`Instructor` params made nullable so admin-↔-user path (no student/instructor scope) can skip activity logging |
| Frontend | Vue 3 + Inertia v2, ShadCN components, Wayfinder routes |
| Routing | `GET /support-messages?user=X`, `POST /support-messages/{user}` under `EnsureOwner` middleware |

## Phase 1: Planning ✅

- [x] Confirm support inbox = user id 1 (owner)
- [x] Confirm reply direction: `from = 1, to = user_id`
- [x] Confirm no schema change required
- [x] Plan controller reusing existing MessageService

### Reflection
- First iteration created a parallel SupportMessageService + three Actions — user correctly pointed out this was duplication; reverted to reusing `MessageService` directly.
- Second iteration hardcoded `SUPPORT_USER_ID = 1` throughout the controller — user correctly pointed out this was still over-specified. The admin area is scoped to the authenticated admin, so `$request->user()` is the natural from/participant, same as every other user in the system. Final design has zero hardcoded ids and calls `MessageService` exactly the way mobile API `MessageController` does.
- Only real code change outside the new files is making `Student`/`Instructor` params on `SendMessageAction::__invoke` nullable — backward-compatible for the two existing callers (PupilController, API V1 MessageController).

## Phase 2: Implementation ✅

### Backend
- [x] Refactor `SendMessageAction` — Student/Instructor params now nullable; skips activity logging when null
- [x] Refactor `MessageService::sendMessage` — matching nullable signature
- [x] `app/Http/Controllers/SupportMessagesController.php` — index + store, consumes `MessageService`
- [x] `app/Http/Requests/SendSupportReplyRequest.php` — owner-gated, `message` max 5000
- [x] Routes under `EnsureOwner` group in `routes/web.php`

### Frontend
- [x] `resources/js/pages/SupportMessages/Index.vue` — two-column chat UI
- [x] `resources/js/routes/support-messages/index.ts` — Wayfinder route helpers (seed; auto-regen on next `npm run dev`)
- [x] `Support Messages` nav item added in `AppSidebar.vue` directly under `Push Notifications`, with `MessageSquare` icon and `roles: ['owner']`

### Docs
- [x] `.claude/database-schema.md` — added user-id-1 support-inbox convention note to the `messages` table section

### Files Created
- `app/Http/Controllers/SupportMessagesController.php`
- `app/Http/Requests/SendSupportReplyRequest.php`
- `resources/js/pages/SupportMessages/Index.vue`
- `resources/js/routes/support-messages/index.ts`

### Files Modified
- `app/Actions/Shared/Message/SendMessageAction.php` — nullable Student/Instructor; skip activity log when null
- `app/Services/MessageService.php` — matching nullable signature
- `routes/web.php` — two new routes under EnsureOwner
- `resources/js/components/AppSidebar.vue` — new nav item + MessageSquare icon import + wayfinder import
- `.claude/database-schema.md` — support-inbox convention note on messages table

## Phase 3: Reflection ✅

- [x] All implementation complete
- [x] Reused existing MessageService + SendMessageAction (user's feedback correctly applied)
- [x] No new migration — FK constraints intact
- [x] Docs updated

### Technical Debt / Follow-Up
- **Read/unread state**: messages table has no `read_at` column; the inbox currently shows every conversation without unread indicator. If needed later, add a `read_at` column scoped to the recipient.
- **Mobile API endpoint for support**: the existing `POST /api/v1/messages` has student↔instructor resolution logic that does not cleanly handle `recipient_id = 1` for instructor senders. If the mobile app wires support messaging through that endpoint, the instructor branch in `MessageController::resolveConversationUser` will need a short-circuit for support.
- **Wayfinder regeneration**: hand-wrote the Wayfinder helper for the new routes; will be re-emitted identically on next `npm run dev` / `npm run build` via `@laravel/vite-plugin-wayfinder`.

---

**Status:** All phases complete.
**Last Updated:** 2026-04-22
