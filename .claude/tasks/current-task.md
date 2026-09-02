# Task: Admin-editable instructor and learner emails

## Overview

Staff need to see and edit the copy of emails sent to instructors and
learners, without changing when those emails send, who they go to, or
enrolment/scheduling/payment behaviour.

Emails today are hardcoded in Laravel Mailables (Blade HTML) and
Notifications (`MailMessage` fluent lines). Mandrill-hosted templates are
unused except for a diagnostic command. This work adds a catalog-backed
`email_templates` store and an owner-only admin UI. Senders resolve copy
from the store (falling back to catalog defaults) and only interpolate
placeholders for dynamic data.

## Phase 1: Planning ✅

### Current state
- Instructor/learner copy lives in `app/Mail/*` and `app/Notifications/*`.
- No admin-editable store. No `email_templates` table.
- Sending, queueing, and recipients stay in existing Actions/Services.

### Approach
1. PHP catalog of template keys, audience, description, placeholders, and default copy.
2. `email_templates` table stores staff overrides (subject, greeting, body, salutation, action label).
3. Renderer interpolates `{{placeholders}}`; missing keys become empty strings.
4. Notifications/Mailables ask the renderer for copy; they still compute data blocks and action URLs.
5. Owner-only Inertia page to list, view, edit, and reset-to-default.
6. Sync inserts missing catalog keys without overwriting edits.

### Reflection
Catalog + DB overrides keeps sending working with an empty table, so a
missed migration cannot silence mail. Staff cannot change keys, recipients,
or triggers.

**Last Updated:** 2026-09-02.

## Phase 2: Implementation ✅

### Currently working on
Complete.

### Tasks
- [x] Migration, model, factory, enums
- [x] Catalog, interpolator, actions, service
- [x] Controller, form request, routes
- [x] Wire Mailables and Notifications
- [x] Inertia list/edit UI and sidebar
- [x] Tests
- [x] database-schema.md

### Reflection
Owner-only `/email-templates` lists all instructor/learner templates with
search and audience filters. Edits persist in `email_templates` and are
interpolated at send time. Action URLs, recipients, and queueing stay in
existing senders. Catalog defaults are used when the table or row is missing.

## Phase 3: Reflection ✅

Staff can now view and edit instructor and learner email copy from the CRM
without disrupting sending, scheduling, or enrolments. Adding a new email
means an enum case, a catalog row, and wiring the sender to the renderer.

**Last Updated:** 2026-09-02.
