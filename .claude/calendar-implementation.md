# Calendar Implementation - Knowledge Base

## Overview

A custom-built interactive weekly calendar for managing instructor time slots (availability). Instructors can view, create, edit, drag-and-drop, and delete time slots. Each slot has a status of **available** (green) or **unavailable** (red). Zero external calendar dependencies — built entirely with Vue 3, Tailwind CSS, and ShadCN components.

---

## Architecture

### Component Tree

```
ScheduleTab.vue (orchestrator)
├── useCalendarNavigation.ts (composable — week logic)
├── WeeklyCalendarGrid.vue (CSS Grid — layout + drag-drop)
│   └── CalendarEventBlock.vue (single event — color + click + drag)
├── Sheet (create form)
├── Sheet (edit form)
└── Dialog (delete confirmation)
```

### Data Flow

```
Backend API (JSON)
    ↓ axios GET
ScheduleTab.vue
    ↓ itemsMap (Map<number, CalendarItemResponse>) → rebuildEvents()
    ↓ events (CalendarEvent[])
WeeklyCalendarGrid.vue
    ↓ eventsByDate (Map<string, CalendarEvent[]>)
CalendarEventBlock.vue (renders each event)
```

**Events flow upward:**
- `CalendarEventBlock` emits `click` / `dragstart`
- `WeeklyCalendarGrid` emits `click-slot` / `event-click` / `event-move`
- `ScheduleTab` handles API calls and state updates

---

## File Reference

### Frontend

| File | Purpose |
|------|---------|
| `resources/js/composables/useCalendarNavigation.ts` | Week navigation composable (Monday start, next/prev/today) |
| `resources/js/components/Instructors/Tabs/Schedule/CalendarEventBlock.vue` | Single event block (color, time display, click/drag emits) |
| `resources/js/components/Instructors/Tabs/Schedule/WeeklyCalendarGrid.vue` | CSS Grid layout, time labels, slot clicks, drag-and-drop logic |
| `resources/js/components/Instructors/Tabs/ScheduleTab.vue` | Orchestrator: navigation, API, sheets, dialogs, state management |
| `resources/js/types/instructor.ts` | TypeScript interfaces (CalendarEvent, CalendarItemFormData, CalendarItemResponse) |

### Backend

| File | Purpose |
|------|---------|
| `app/Http/Controllers/InstructorController.php` | Calendar CRUD endpoints (calendar, storeCalendarItem, updateCalendarItem, destroyCalendarItem) |
| `app/Services/InstructorService.php` | Service layer — delegates to action classes |
| `app/Actions/Instructor/CreateCalendarItemAction.php` | Creates a calendar item with `firstOrCreate` on the Calendar model |
| `app/Actions/Instructor/UpdateCalendarItemAction.php` | Updates item — handles cross-date moves, cleans up empty calendars |
| `app/Actions/Instructor/DeleteCalendarItemAction.php` | Deletes a calendar item |
| `app/Actions/Instructor/GetInstructorCalendarAction.php` | Fetches calendar data for a date range |
| `app/Http/Requests/StoreCalendarItemRequest.php` | Validation for creating items (date, times, is_available, overlap check) |
| `app/Http/Requests/UpdateCalendarItemRequest.php` | Validation for updating items (same rules, excludes self from overlap check) |

---

## Database Schema

### `calendars` table

One row per instructor per date. Acts as a grouping container for time slots.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | Primary key |
| `instructor_id` | bigint | FK → instructors.id (cascade delete) |
| `date` | date | The calendar date |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Unique index:** `(instructor_id, date)` — one calendar per instructor per day.

### `calendar_items` table

Individual time slots within a calendar day.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | Primary key |
| `calendar_id` | bigint | FK → calendars.id (cascade delete) |
| `start_time` | time | e.g. `09:00:00` |
| `end_time` | time | e.g. `10:00:00` |
| `is_available` | tinyint | 1 = available (green), 0 = unavailable (red) |
| `status` | enum | Booking state: `draft`, `reserved`, `booked`, or null |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Relationships:**
- `Calendar hasMany CalendarItem`
- `CalendarItem belongsTo Calendar`
- `Instructor hasMany Calendar`

---

## API Endpoints

All endpoints are under the authenticated middleware group.

### GET `/instructors/{instructor}/calendar`

Fetch calendar items for a date range.

**Query params:**
| Param | Type | Default |
|-------|------|---------|
| `start_date` | `Y-m-d` | Current week Monday |
| `end_date` | `Y-m-d` | Current week Sunday |

**Response:**
```json
{
  "calendar": [
    {
      "id": 1,
      "instructor_id": 1,
      "date": "2026-02-12",
      "items": [
        {
          "id": 10,
          "calendar_id": 1,
          "start_time": "09:00:00",
          "end_time": "10:00:00",
          "is_available": true,
          "status": null
        }
      ]
    }
  ]
}
```

### POST `/instructors/{instructor}/calendar/items`

Create a new time slot.

**Request body:**
```json
{
  "date": "2026-02-12",
  "start_time": "09:00",
  "end_time": "10:00",
  "is_available": true
}
```

**Validation:**
- `date`: required, Y-m-d format, must be today or future
- `start_time`: required, H:i format
- `end_time`: required, H:i format, must be after start_time
- `is_available`: optional boolean (defaults to true)
- Overlap check: rejects if new slot overlaps existing slots on same date

**Response (201):**
```json
{
  "calendar_item": {
    "id": 10,
    "calendar_id": 1,
    "date": "2026-02-12",
    "start_time": "09:00:00",
    "end_time": "10:00:00",
    "is_available": true,
    "status": "available"
  }
}
```

### PUT `/instructors/{instructor}/calendar/items/{calendarItem}`

Update a time slot (used for drag-and-drop moves AND edit form).

**Request body:**
```json
{
  "date": "2026-02-13",
  "start_time": "11:00",
  "end_time": "12:00",
  "is_available": false
}
```

**Validation:** Same rules as store, plus overlap check excludes the item being updated.

**Cross-date moves:** If the date changes, the action:
1. Finds or creates a `Calendar` for the new date
2. Moves the `CalendarItem` to the new calendar
3. Deletes the old `Calendar` if it has no remaining items

**Response (200):** Same shape as create response.

### DELETE `/instructors/{instructor}/calendar/items/{calendarItem}`

Delete a time slot.

**Response (200):**
```json
{
  "message": "Calendar item removed successfully."
}
```

**Ownership:** All PUT/DELETE endpoints verify the calendar item belongs to the instructor via `$calendarItem->calendar->instructor_id`.

---

## Frontend Components — Detail

### `useCalendarNavigation.ts` Composable

Manages week-level navigation with Monday as first day of week.

**Exports:**
| Name | Type | Description |
|------|------|-------------|
| `currentWeekStart` | `Ref<Date>` | Monday of the current week |
| `weekDays` | `Computed<Date[]>` | Array of 7 Date objects (Mon–Sun) |
| `weekEnd` | `Computed<Date>` | Sunday of the current week |
| `weekStartFormatted` | `Computed<string>` | Monday as `YYYY-MM-DD` |
| `weekEndFormatted` | `Computed<string>` | Sunday as `YYYY-MM-DD` |
| `goToNextWeek()` | Function | Advance by 7 days |
| `goToPreviousWeek()` | Function | Go back by 7 days |
| `goToToday()` | Function | Reset to current week |
| `formatDate()` | Function | `Date → "YYYY-MM-DD"` |

**No external date library** — uses native JS `Date`.

### `CalendarEventBlock.vue`

Renders a single time slot as an absolute-positioned block inside a day column.

**Props:**
| Prop | Type | Description |
|------|------|-------------|
| `event` | `CalendarEvent` | The event data |
| `dayStartHour` | `number` | Day starts at this hour (6) |
| `rowHeight` | `number` | Pixels per 30-min row (40) |

**Emits:**
| Event | Payload | Trigger |
|-------|---------|---------|
| `click` | `CalendarEvent` | Click on event (opens edit sheet) |
| `dragstart` | `CalendarEvent, PointerEvent` | Pointer down (starts drag) |

**Positioning formula:**
```
topPx = ((startMinutes - dayStartHour * 60) / 30) * rowHeight
heightPx = (durationMinutes / 30) * rowHeight
```

**Color classes:**
- Available: `bg-green-100 dark:bg-green-900/30 border-green-300 dark:border-green-700 text-green-800 dark:text-green-300`
- Unavailable: `bg-red-100 dark:bg-red-900/30 border-red-300 dark:border-red-700 text-red-800 dark:text-red-300`

**Adaptive content:** Status label ("Available"/"Unavailable") only shows when `heightPx > 30` to avoid overflow on short slots.

### `WeeklyCalendarGrid.vue`

The main calendar grid. Uses CSS Grid for layout and Pointer Events for drag-and-drop.

**Props:**
| Prop | Type | Description |
|------|------|-------------|
| `weekDays` | `Date[]` | 7 days to display (from composable) |
| `events` | `CalendarEvent[]` | All events for the visible week |

**Emits:**
| Event | Payload | Trigger |
|-------|---------|---------|
| `click-slot` | `date: string, time: string` | Click on empty grid cell |
| `event-click` | `CalendarEvent` | Click on existing event |
| `event-move` | `eventId, newDate, newStartTime, newEndTime` | Drop after drag |

**Layout constants:**
| Constant | Value | Description |
|----------|-------|-------------|
| `DAY_START_HOUR` | `6` | First visible hour (06:00) |
| `DAY_END_HOUR` | `22` | Last visible hour (22:00) |
| `ROW_HEIGHT` | `40` | Pixels per 30-min slot |
| `SLOT_COUNT` | `32` | (22-6) * 2 = 32 half-hour rows |

**CSS Grid structure:**
```
grid-cols-[4rem_repeat(7,1fr)]
```
- First column: 4rem time gutter (hour labels)
- 7 equal columns: one per day (Mon–Sun)

**Grid lines:**
- Hour lines: solid `border-border`
- Half-hour lines: dashed `border-dashed`

**Today highlight:** `bg-primary/5 dark:bg-primary/10` on the day column + header

**Drag-and-drop mechanics:**
1. `pointerdown` on event → stores drag state (start position, offset, ghost dimensions)
2. 5px movement threshold before drag activates (prevents accidental drags from clicks)
3. `pointermove` → snaps ghost to 30-min increments vertically, detects day column horizontally
4. Original event becomes `opacity-30 pointer-events-none` during drag
5. Ghost element follows cursor with dashed border
6. `pointerup` → calculates new date/time from snapped position, emits `event-move`
7. Boundary clamping: won't allow drops outside 06:00–22:00 range

### `ScheduleTab.vue` (Orchestrator)

Top-level component that wires everything together.

**Props:**
| Prop | Type | Description |
|------|------|-------------|
| `instructorId` | `number` | The instructor whose calendar to display |

**State management:**
- `itemsMap`: `Map<number, CalendarItemResponse>` — backend items keyed by ID for O(1) lookups
- `events`: `CalendarEvent[]` — derived from itemsMap via `rebuildEvents()`
- `loading`: skeleton state during initial fetch
- `formLoading`: spinner state during form submissions

**Data loading:**
- On mount: fetches current week via `loadCalendarRange(weekStart, weekEnd)`
- On week change: `watch(weekStartFormatted)` triggers new fetch
- Backend response structure: `{ calendar: [{ id, date, items: [...] }] }`

**Optimistic updates (drag-and-drop):**
1. Immediately update `itemsMap` and `rebuildEvents()` with new position
2. Send PUT request to backend
3. On success: update with server response
4. On error: revert to old values from saved snapshot

**UI components used:**
- `Card`, `CardHeader`, `CardContent`, `CardTitle` — layout
- `Sheet`, `SheetContent`, `SheetHeader`, `SheetTitle` — create/edit forms
- `Dialog`, `DialogContent`, `DialogHeader`, `DialogTitle`, `DialogDescription`, `DialogFooter` — delete confirmation
- `Button` — actions with `formLoading` spinner pattern
- `Input` — date/time fields (type="date", type="time" with step="900" for 15-min increments)
- `Label` — form labels
- `Skeleton` — loading state
- `toast()` — success/error feedback on all API calls

**Navigation bar:**
- Previous week: `ChevronLeft` icon button
- Today: text button
- Next week: `ChevronRight` icon button
- Week label: "12 - 18 Feb 2026" format (handles cross-month display)

---

## TypeScript Interfaces

### Frontend Event (used within grid components)

```typescript
// Defined in CalendarEventBlock.vue
interface CalendarEvent {
    id: number
    date: string        // "YYYY-MM-DD"
    startTime: string   // "HH:MM" or "HH:MM:SS"
    endTime: string     // "HH:MM" or "HH:MM:SS"
    isAvailable: boolean
}
```

### Backend Response Type

```typescript
// Defined in types/instructor.ts
interface CalendarItemResponse {
    id: number
    calendar_id: number
    date: string         // "YYYY-MM-DD"
    start_time: string   // "HH:MM:SS" (backend returns with seconds)
    end_time: string     // "HH:MM:SS"
    is_available: boolean
    status: string | null
}
```

### Form Data Type

```typescript
// Defined in types/instructor.ts
interface CalendarItemFormData {
    date: string         // "YYYY-MM-DD"
    start_time: string   // "HH:MM"
    end_time: string     // "HH:MM"
    is_available: boolean
}
```

**Note:** Backend returns times as `HH:MM:SS`, frontend sends as `HH:MM`. The `normaliseTime()` helper handles conversion: `t.substring(0, 5)`.

---

## Interaction Flows

### Create a Time Slot

```
User clicks empty grid cell
    → WeeklyCalendarGrid emits clickSlot(date, time)
    → ScheduleTab pre-fills createForm (date, start_time, end_time = start + 1hr, is_available = true)
    → Opens create Sheet
User fills form, clicks "Add Time Slot"
    → Client-side validation (all fields, end > start)
    → POST /instructors/{id}/calendar/items
    → On success: add to itemsMap, rebuildEvents(), toast, close sheet
    → On error: toast with server message
```

### Edit a Time Slot

```
User clicks event block
    → CalendarEventBlock emits click(event)
    → WeeklyCalendarGrid emits eventClick(event)
    → ScheduleTab looks up itemsMap, fills editForm
    → Opens edit Sheet
User modifies form, clicks "Save Changes"
    → PUT /instructors/{id}/calendar/items/{itemId}
    → On success: update itemsMap, rebuildEvents(), toast, close sheet
    → On error: toast with server message
```

### Drag-and-Drop a Time Slot

```
User presses and holds on event block
    → CalendarEventBlock emits dragstart(event, pointerEvent)
    → WeeklyCalendarGrid stores drag state, attaches window listeners
User drags (>5px movement threshold)
    → Ghost element appears (dashed border)
    → Original event fades (opacity-30)
    → Ghost snaps to 30-min grid vertically, tracks day columns horizontally
User releases
    → Calculate new date/time from snapped ghost position
    → WeeklyCalendarGrid emits eventMove(id, newDate, newStart, newEnd)
    → ScheduleTab optimistically updates itemsMap + rebuildEvents()
    → PUT /instructors/{id}/calendar/items/{itemId}
    → On success: update with server response, toast
    → On error: REVERT to saved snapshot, rebuildEvents(), toast
```

### Delete a Time Slot

```
User clicks event → opens edit Sheet
User clicks "Delete" button
    → Close edit Sheet, open delete Dialog
User clicks "Remove" in Dialog
    → DELETE /instructors/{id}/calendar/items/{itemId}
    → On success: remove from itemsMap, rebuildEvents(), toast, close dialog
    → On error: toast with server message
```

### Navigate Weeks

```
User clicks prev/next/today
    → useCalendarNavigation updates currentWeekStart
    → weekStartFormatted changes (computed)
    → ScheduleTab watch() triggers loadCalendarRange(newStart, newEnd)
    → GET /instructors/{id}/calendar?start_date=...&end_date=...
    → Replaces itemsMap entirely, rebuildEvents()
```

---

## Dark Mode

Full support via Tailwind `dark:` variants. The app uses `.dark` class on `<html>` element (toggled by `useAppearance` composable).

**Event blocks:**
- Available: `bg-green-100 → dark:bg-green-900/30`, `border-green-300 → dark:border-green-700`, `text-green-800 → dark:text-green-300`
- Unavailable: `bg-red-100 → dark:bg-red-900/30`, `border-red-300 → dark:border-red-700`, `text-red-800 → dark:text-red-300`

**Grid:**
- Borders use `border-border` (ShadCN token — auto light/dark)
- Today column: `bg-primary/5 dark:bg-primary/10`
- Hover on empty slot: `hover:bg-muted/50`
- Text uses `text-foreground`, `text-muted-foreground` (ShadCN tokens)

**Drag ghost:** Separate dark variants with `/50` opacity for background.

---

## Extension Points

### Adding New Event Statuses

1. Add new color classes to `CalendarEventBlock.vue` (extend the `:class` ternary to handle new statuses)
2. Add new `is_available` isn't sufficient — consider adding a new field or using the existing `status` enum (`draft`, `reserved`, `booked`)
3. Update the `toCalendarEvent()` mapping in `ScheduleTab.vue`

### Adding Month View

1. Create a new `MonthlyCalendarGrid.vue` component
2. Extend `useCalendarNavigation.ts` with `goToNextMonth()`, `goToPreviousMonth()`, `monthDays` computed
3. Add a view toggle button in `ScheduleTab.vue` navigation bar

### Adding Event Resize

1. Add a resize handle element at the bottom of `CalendarEventBlock.vue`
2. On `pointerdown` on the handle, start a resize operation (similar to drag but only changes `endTime`)
3. Emit a new `resize` event from `WeeklyCalendarGrid.vue`
4. Handle in `ScheduleTab.vue` with a PUT request

### Booking Integration

The `status` field on `calendar_items` (`draft` | `reserved` | `booked` | null) is reserved for booking state. When bookings are added:
1. A booked slot should display differently (e.g. blue, with pupil name)
2. Booked slots should not be draggable or deletable
3. The `CalendarEvent` interface will need a `status` field and possibly `pupilName`
4. `CalendarEventBlock.vue` will need additional color schemes and content

### 5-Day Work Week Option

Change `SLOT_COUNT` isn't needed — instead:
1. Modify `useCalendarNavigation.ts` to return 5 days instead of 7
2. Change grid to `grid-cols-[4rem_repeat(5,1fr)]`
3. Add a toggle in the navigation bar

### Custom Day Boundaries

The `DAY_START_HOUR` and `DAY_END_HOUR` constants in `WeeklyCalendarGrid.vue` control the visible range. To make these configurable:
1. Move them to props on `WeeklyCalendarGrid.vue`
2. Pass from `ScheduleTab.vue` (could come from instructor settings)
3. The `CalendarEventBlock.vue` already receives `dayStartHour` as a prop

---

## Key Design Decisions

| Decision | Rationale |
|----------|-----------|
| Custom calendar (not Schedule X) | Schedule X v4 uses Temporal API which broke all callbacks. No native Tailwind dark mode. |
| Pointer Events for drag (not HTML5 Drag API) | Smoother UX, works on touch devices, easier snap calculation, no drag image issues |
| CSS Grid (not flexbox) | Aligned time rows across all day columns, clean gutter column |
| Absolute positioning for events | Events overlay the grid cells, allows overlapping events, precise time-to-pixel mapping |
| 30-min grid snap | Matches visual grid lines, good UX balance between precision and ease |
| `itemsMap` (Map) + `rebuildEvents()` | O(1) lookups by ID for edit/drag, single source of truth with derived events array |
| Optimistic drag updates with revert | Instant visual feedback; server error reverts to saved snapshot |
| No external date library | Week navigation only needs basic date arithmetic — native JS Date suffices |
| Separate CalendarEventBlock component | Encapsulates color logic, positioning math, and event handling per block |
