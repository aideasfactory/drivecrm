# Task: Messages Feature — Instructor ↔ Student Chat

**Created:** 2026-02-13
**Last Updated:** 2026-02-13
**Status:** ✅ All Phases Complete — Awaiting Review

---

## Overview

### Goal
Build a messaging system between instructors and students, accessible from the Student Messages sub-tab. The UI follows a chat-style conversation layout (wireframe: `wireframes/message.html`). When a message is sent, an activity log entry is created and an email notification is sent to the recipient.

### Requirements
1. **Chat UI** — Conversation-style message thread between instructor and student
2. **Message colours** — Red/primary background for student messages, white/muted background for instructor messages
3. **Activity Log** — When a message is added, log it to `activity_logs`
4. **Email Notification** — When a message is sent, email the recipient
5. **ShadCN components** — All UI must use ShadCN
6. **Industry standard** — Use best-practice patterns for chat system DB design and UI

### What Already Exists
- **`messages` table** — Migration exists (`2026_02_12_213053_create_messages_table.php`) with `from`, `to`, `message`, `deleted_at`, `created_at`, `updated_at`
- **`Message` model** (`app/Models/Message.php`) — Has `sender()` and `recipient()` BelongsTo relationships to `User`
- **`SendBroadcastMessageAction`** — Existing action for broadcast messages (creates Message records per student)
- **`MessagesSubTab.vue`** — Was a placeholder ("Messages coming soon")
- **Database schema docs** — Messages table already documented in `.claude/database-schema.md`

---

## Phase 1: Planning ✅

### Tasks
- [x] Read instructions.md, backend-coding-standards.md, frontend-coding-standards.md, database-schema.md, wireframe-rules.md
- [x] Explore existing patterns (Message model, broadcast action, activity log, notifications)
- [x] Identify all files that need changes
- [x] Create task breakdown

### Reflection
- Existing `messages` table and `Message` model were already well-structured for 1-to-1 messaging
- Broadcast messages already create per-student Message records, so they naturally appear in conversations
- No migration changes needed — reuse existing infrastructure

---

## Phase 2: Backend — Actions, Controller, Routes, Notification ✅

### Tasks
- [x] Add `sentMessages()` and `receivedMessages()` HasMany relationships to `User` model
- [x] Create `GetConversationAction` — paginated messages between two users, ordered by `created_at` desc
- [x] Create `SendMessageAction` — create message, log activity on both student + instructor, dispatch email notification
- [x] Create `NewMessageNotification` — queued email notification to recipient
- [x] Add `messages()` endpoint to `PupilController` (GET — conversation between instructor and student)
- [x] Add `sendMessage()` endpoint to `PupilController` (POST — send message from instructor to student)
- [x] Add routes to `routes/web.php`

### Reflection
- Actions follow existing patterns (invokable, type-hinted, LogActivityAction injection)
- `GetConversationAction` uses bi-directional WHERE query with eager-loaded sender
- `SendMessageAction` logs activity on BOTH student and instructor entities for complete audit trail
- Controller resolves instructor via `$student->instructor` — messages always sent as instructor regardless of logged-in user
- `NewMessageNotification` follows `OrderConfirmationNotification` pattern (queued, `MailMessage`)
- Validation guards: student must have `user_id` AND `instructor_id`

---

## Phase 3: Frontend — MessagesSubTab Chat UI ✅

### Tasks
- [x] Rebuild `MessagesSubTab.vue` with chat conversation layout
- [x] Chat header — student Avatar with initials, name, and "Learner Driver" label
- [x] Scrollable message area (600px height) with auto-scroll to bottom on load and new messages
- [x] Instructor messages (left-aligned): Avatar, muted background bubble, rounded-tl-none
- [x] Student messages (right-aligned): Avatar, destructive/red background bubble with white text, rounded-tr-none
- [x] Sender name and timestamp on each message
- [x] Message input area — Input + Send Button with Loader2 spinner
- [x] Loading skeletons (chat-style alternating left/right skeleton bubbles)
- [x] Empty state when no messages exist
- [x] Error state when student lacks user account or instructor
- [x] Toast notifications for send success/error
- [x] "Load older messages" button with scroll position preservation
- [x] Enter key to send (matching industry standard chat UX)

### Reflection
- Used `bg-destructive` / `text-destructive-foreground` for student messages (maps to red in the theme)
- Used `bg-muted` for instructor messages (maps to white/light gray)
- Message bubble corner treatment: `rounded-tl-none` for instructor, `rounded-tr-none` for student (matching wireframe layout)
- API returns newest-first for pagination; frontend reverses for chronological display
- Scroll position preserved when loading older messages (saves scrollHeight before prepend, restores after)
- All ShadCN components: Avatar, AvatarFallback, Button, Input, Skeleton

---

## Phase 4: Review & Reflection ✅

### Verification
- [x] Backend actions follow Controller → Action pattern (GetConversationAction, SendMessageAction)
- [x] Activity log fires for both student and instructor entities (category: `message`)
- [x] Email notification is queued (`NewMessageNotification implements ShouldQueue`)
- [x] Frontend uses ShadCN components throughout (Avatar, Button, Input, Skeleton)
- [x] Red/destructive for student messages, muted for instructor messages
- [x] Loading states (skeletons), sending state (Loader2 spinner), toasts present
- [x] Empty state and error state (student without user_id) handled
- [x] Broadcast messages from instructor will appear in conversations (same `messages` table)

### Reflection
- Clean implementation leveraging existing infrastructure — no new migrations needed
- The `SendBroadcastMessageAction` and new `SendMessageAction` both create records in the same `messages` table, so broadcast and direct messages are unified
- `timeAgo` could be extracted to shared utility (also exists in OverviewSubTab and ActivitySubTab) — keeping local follows current convention
- Future enhancement: `read_at` column for read receipts, real-time updates via WebSockets/polling

---

## Decisions Log
- **No new migration**: Existing `messages` table already has correct structure
- **User-based messaging**: Messages between User records, matching `SendBroadcastMessageAction` pattern
- **Activity log on both sides**: Log on Student AND Instructor entities for complete audit trail
- **Queued email**: `NewMessageNotification` implements `ShouldQueue` (matching `OrderConfirmationNotification`)
- **Instructor as sender**: Messages always sent from `student.instructor.user`, not `auth()->user()` — consistent with broadcast pattern
- **ShadCN only**: Using Avatar, Button, Input, Skeleton — red colour via `bg-destructive` theme token (not custom CSS)

## Files Changed
| File | Change |
|------|--------|
| `app/Models/User.php` | Added `sentMessages()` and `receivedMessages()` HasMany relationships |
| `app/Actions/Shared/Message/GetConversationAction.php` | NEW — paginated bi-directional conversation query |
| `app/Actions/Shared/Message/SendMessageAction.php` | NEW — send message + activity log on both entities + email notification |
| `app/Notifications/NewMessageNotification.php` | NEW — queued email notification to recipient |
| `app/Http/Controllers/PupilController.php` | Added `messages()` and `sendMessage()` endpoints |
| `routes/web.php` | Added 2 message routes (GET, POST) |
| `resources/js/components/Instructors/Tabs/Student/MessagesSubTab.vue` | Full rebuild — chat UI with bubbles, skeletons, send |

## Architecture Notes
- Messages reuse existing `messages` table and `Message` model — no schema changes
- Broadcast messages (from `SendBroadcastMessageAction`) naturally appear in individual conversations
- `GetConversationAction` follows `GetNotesAction` pattern (paginated query)
- `SendMessageAction` follows `CreateNoteAction` pattern (create + log activity)
- Email notification follows `OrderConfirmationNotification` pattern (queued, `MailMessage`)
- Frontend follows self-loading component pattern (axios, local state, skeletons)
