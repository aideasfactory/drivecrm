# Mobile Finances + Mileage — Implementation Spec

Hand-off document for the mobile app developer. Covers screens, components, flows, and gotchas for the finance/mileage features now exposed via the v1 API.

Server-side reference: [api.md](api.md) — Instructor Finances + Instructor Mileage sections. Every field, error case, and response shape is documented there; this spec is about what to **build**, not what the API returns.

---

## 1. What this enables

Self-employed driving instructors track their books in the app:

- **Payments received** (franchise payouts, HMRC refunds, referral bonuses, etc.)
- **Expenses paid** (fuel, insurance, MOT, equipment, etc.) — each can have a receipt attached (PDF or image)
- **Mileage logs** — business vs personal, for HMRC
- **At-a-glance totals** over a user-selectable date range (default: last 30 days)

Every data shape on the admin web UI is mirrored 1:1 in the API — category (type-gated), payment method, recurring-flag, receipt attachments. The mobile app should reach **feature parity with the admin area**, plus one thing admin can't do: a proper date-range overview with stats.

---

## 2. Endpoint reference (quick)

Base URL: `https://drivecrm.test/api/v1` (prod URL differs). All endpoints require `Authorization: Bearer <token>` + `Accept: application/json`.

| Endpoint | Purpose |
|---|---|
| `GET /instructor/finances/config` | Dropdown options — categories, payment methods, mileage types, receipt limits. **Cache on login**, rarely changes. |
| `GET /instructor/finances/summary?from&to` | Overview screen. Full-range finances + mileage + stats. Default range: last 30 days. |
| `GET /instructor/finances?type&from&to&cursor&per_page` | Cursor-paginated finance list. Infinite scroll. |
| `GET /instructor/finances/{id}` | Single finance record (use to refresh stale `receipt.url`). |
| `POST /instructor/finances` | Create. JSON only — upload receipt separately. |
| `PUT /instructor/finances/{id}` | Update. All fields optional. |
| `DELETE /instructor/finances/{id}` | Delete (receipt is deleted with it). |
| `POST /instructor/finances/{id}/receipt` | Upload/replace receipt. **Multipart.** |
| `DELETE /instructor/finances/{id}/receipt` | Remove receipt (record stays). |
| `GET /instructor/mileage?from&to&cursor&per_page` | Cursor-paginated mileage list. |
| `GET /instructor/mileage/{id}` | Single mileage log. |
| `POST /instructor/mileage` | Create. Server computes `miles = end - start`. |
| `PUT /instructor/mileage/{id}` | Update. |
| `DELETE /instructor/mileage/{id}` | Delete. |

---

## 3. Screens to build

### 3.1 Finances Overview (the landing screen)

**Source:** `GET /instructor/finances/summary` (pass `from`/`to` when user changes range; omit both on first load).

**Layout (suggested):**

```
┌─────────────────────────────────────┐
│ ← Finances                    [＋] │
├─────────────────────────────────────┤
│ [Date range picker ▼]               │ ← "Last 30 days" (default badge)
├─────────────────────────────────────┤
│  ┌────────┐ ┌────────┐              │
│  │ Records│ │Payments│  ← stat cards
│  │   42   │ │ £1,234 │   (4 cards, 2x2 grid on mobile)
│  └────────┘ └────────┘              │
│  ┌────────┐ ┌────────┐              │
│  │Expenses│ │ Net    │              │
│  │  £654  │ │ £580   │              │
│  └────────┘ └────────┘              │
├─────────────────────────────────────┤
│  Mileage (same range)               │
│  ┌────────┐ ┌────────┐              │
│  │ Trips  │ │Business│              │
│  │   15   │ │ 450 mi │              │
│  └────────┘ └────────┘              │
│  ┌────────┐ ┌────────┐              │
│  │Personal│ │ Total  │              │
│  │  80 mi │ │530 mi  │              │
│  └────────┘ └────────┘              │
├─────────────────────────────────────┤
│  [All] [Payments] [Expenses] [Mile] │ ← segment filter
├─────────────────────────────────────┤
│  Finance/mileage rows (scrollable)  │
│  [view all →]                       │
└─────────────────────────────────────┘
```

**Notes:**
- When `date_range.default_applied === true`, show a "Last 30 days" label/badge on the picker so the user knows they're on the default.
- Summary returns **up to the full range unpaginated** — for typical 30-day windows this is tiny. If a user picks a 12-month range and scrolls the preview, fall back to the paginated list endpoints.
- Tapping the segment filter at the bottom switches to the full list screen (3.2 / 3.3) with the filter pre-applied.
- The `＋` button opens the create sheet. When the active segment is "Mileage", it opens the mileage create sheet; otherwise the finance sheet.

**Stat cards map to response fields:**

| Card | Source |
|---|---|
| Records | `stats.total_records` |
| Payments | `stats.total_payments_formatted` |
| Expenses | `stats.total_expenses_formatted` |
| Net Balance | `stats.net_balance_formatted` |
| Trips | `stats.total_trips` |
| Business / Personal / Total miles | `stats.business_miles` etc., append " mi" |

### 3.2 All Finances (list, cursor-paginated)

**Source:** `GET /instructor/finances?type=...&from=...&to=...&cursor=...`

**Layout:** list + sticky header with date range + type filter (All / Payments / Expenses).

**Row:** one line per record:
- Leading icon: `ArrowDownCircle` (payment, green) or `ArrowUpCircle` (expense, red)
- Title: `description`
- Subtitle line 1: `category_label` · `payment_method_label` (skip if `none`/null)
- Subtitle line 2: `formatted_date`
- Trailing: `formatted_amount` + paperclip icon if `receipt !== null`
- Recurring badge if `is_recurring === true`

**Pagination:**
- First call: no cursor.
- Response has `next_cursor`. If `null`, no more pages.
- Otherwise, on reaching end of list, call again with `?cursor=<next_cursor>` + **same** `from/to/type` params.
- Show a footer spinner while loading; swap for "No more records" when `next_cursor === null`.

### 3.3 Mileage List

**Source:** `GET /instructor/mileage?from=...&to=...&cursor=...`

Same pagination pattern as 3.2. Row shape:
- Leading: `Car` icon
- Title: `formatted_date` · `type_label`
- Subtitle: `start_mileage` → `end_mileage` (optionally notes preview)
- Trailing: `{miles} mi` (big, bold)

### 3.4 Finance Detail

**Source:** `GET /instructor/finances/{id}` — always refetch on open to get a fresh `receipt.url`.

**Layout:** big amount at top, then a list of key/value rows (type, category, payment method, date, description, recurring flag, notes). If `receipt !== null`, show a receipt card with file name, size, and a "View" button that opens the signed URL.

**Actions:** Edit, Delete (with confirm), Replace Receipt, Remove Receipt.

### 3.5 Mileage Detail

Similar shape. Big `{miles} mi` at top. Rows for date, type, start/end odometer, notes. Edit/Delete actions.

### 3.6 Create / Edit Finance (sheet or modal)

**Fields (in order):**

| Field | Control | Notes |
|---|---|---|
| Type | Segmented control (Payment / Expense) | Switches category list below |
| Category | Dropdown | Source: `config.expense_categories` or `config.payment_categories` depending on Type. Default `none` (label "None"). **Reset to `none` when Type changes.** |
| Payment Method | Dropdown | Source: `config.payment_methods`. Include a "— Not specified —" first option that submits `null`. |
| Description | Text input | max 255 chars |
| Amount | Currency input (£) | Convert to `amount_pence` on submit: `Math.round(amount * 100)` |
| Date | Date picker | YYYY-MM-DD |
| Recurring | Toggle | |
| Frequency | Dropdown | Shown only when Recurring is on. Required in that case. Values: `weekly`, `monthly`, `yearly`. |
| Notes | Multiline | max 1000 |
| Receipt | File picker | See §4.3 below |

**Submit flow:** see §5.1.

### 3.7 Create / Edit Mileage (sheet or modal)

| Field | Control | Notes |
|---|---|---|
| Date | Date picker | |
| Type | Segmented control | Business / Personal. Source: `config.mileage_types`. |
| Start mileage | Number input | |
| End mileage | Number input | Must be ≥ Start — validate client-side, server also enforces |
| Miles preview | Read-only text | Compute `end - start` as the user types |
| Notes | Multiline | max 1000 |

---

## 4. Reusable components

### 4.1 Date range picker

**Required everywhere summary/list data is shown.**

Recommended control: a button showing the current range ("Last 30 days", "1–30 Apr 2026", etc.) that opens a sheet with:

- **Presets** (radio buttons):
  - Last 7 days
  - Last 30 days ← default
  - This month
  - Last month
  - This tax year (6 Apr → 5 Apr — important for UK self-employed instructors)
  - Custom range
- **Custom** shows two date pickers (from / to)

On confirm, refetch `/summary` + reset the list cursor.

### 4.2 Category & method dropdowns

Source: the cached response from `GET /finances/config` (see §6.1).

**Critical:** the Category dropdown's options depend on the selected Type:

```pseudocode
const categories = type === 'payment'
    ? config.payment_categories
    : config.expense_categories
```

When the user flips Type, **reset Category to `'none'`** — a valid expense category (e.g., `fuel`) is **not** a valid payment category and the server will reject it.

Render as `{ slug: label }` pairs — bind the slug on submit, show the label in the UI. `"none"` maps to "None" and is valid for both types.

### 4.3 File picker + preview (for receipts)

**Accept:** PDF, JPG/JPEG, PNG. Max size from `config.receipt.max_size_kb` (10 MB default).

**Sources (native pickers):**
- iOS: `UIDocumentPickerViewController` for files; `PHPicker` for photo library; `UIImagePickerController` for camera.
- Android: `ACTION_GET_CONTENT` for files; `MediaStore` for camera/gallery.
- React Native: `expo-document-picker` + `expo-image-picker` (or `react-native-document-picker` / `react-native-image-picker`).

**Preview in-form:**
- For images: show a thumbnail.
- For PDFs: show a "filename.pdf · 234 KB" pill with a PDF icon.

**In-list + detail preview:**
- Signed S3 URLs (`receipt.url`) have a **20-minute TTL**. Always open them in a webview/browser **immediately after fetching the record**. Don't store the URL in long-lived state — re-fetch via `GET /finances/{id}` just before opening.

### 4.4 Receipt viewer

For a tappable receipt in detail view:
- If `mime_type` starts with `image/` → open in a zoomable image viewer.
- If `mime_type === 'application/pdf'` → open in the platform's PDF viewer (iOS: QuickLook via `UIDocumentInteractionController`; Android: `PdfRenderer` or intent to system PDF app; React Native: `react-native-pdf` or `WebView`).

Don't cache the signed URL. If the view is in the background for 20+ minutes and the user comes back to tap "View", refetch first.

### 4.5 Cursor-paginated list

Thin abstraction over FlatList / RecyclerView:

```pseudocode
state: { items: [], cursor: null, loading: false, done: false }

fetchPage():
    if loading or done: return
    loading = true
    const url = cursor
        ? `${base}&cursor=${cursor}`
        : base
    const res = await fetch(url)
    items.push(...res.data)
    cursor = res.next_cursor
    done = res.next_cursor === null
    loading = false

onEndReached: fetchPage()
```

When filters change (type, date range), reset entire state and refetch.

### 4.6 Recurring badge

Just a display flag right now. Show a badge like `🔁 monthly` next to the amount when `is_recurring === true`. No calendar integration yet — see §9 Out of Scope.

---

## 5. Critical flows

### 5.1 Create finance + optional receipt

Two-step, because the receipt is its own endpoint:

```pseudocode
onSubmit:
    1. POST /instructor/finances with JSON payload
    2. On success (201), capture returned `data.id`
    3. If the user picked a receipt:
         POST /instructor/finances/{id}/receipt (multipart)
    4. On success, close sheet, toast "Saved"
    5. If step 3 fails:
         - Keep the record (step 1 succeeded)
         - Toast "Saved — receipt upload failed, tap to retry"
         - Store the file in memory so the retry works without re-picking
```

**Don't** combine both into a single multipart request — we chose separate endpoints specifically so receipt upload is retryable independently of the record create.

### 5.2 Edit finance (with receipt changes)

```pseudocode
onSubmit:
    1. PUT /instructor/finances/{id} with changed fields
    2. If user chose "remove current receipt" (no new file picked):
         DELETE /instructor/finances/{id}/receipt
    3. If user picked a new file:
         POST /instructor/finances/{id}/receipt (replaces existing)
    4. On success, refresh detail screen
```

### 5.3 First-load and cache hydration

On app boot / login:

```pseudocode
1. Check cached config; if missing or older than 24h:
     GET /instructor/finances/config
     Store in AsyncStorage (RN) / SharedPreferences / CoreData.
2. Continue app boot.
```

On opening the finance section:

```pseudocode
1. GET /instructor/finances/summary (no date params = last 30 days)
2. Render stats + first page of each list
```

### 5.4 Open receipt (stale URL protection)

```pseudocode
onTapReceipt(finance):
    1. GET /instructor/finances/{finance.id}    ← refresh signed URL
    2. Open the returned receipt.url in viewer
```

This is cheap (one API call) and avoids the "URL expired" failure mode 20 minutes after loading the list.

### 5.5 Changing date range from overview

```pseudocode
onRangeChanged(from, to):
    1. Dismiss range picker sheet
    2. Show loading skeleton on stat cards
    3. GET /instructor/finances/summary?from=X&to=Y
    4. Replace UI state with new response
    5. Reset list cursor state (§4.5) — next scroll refetches from page 1
```

---

## 6. State & caching

### 6.1 Config cache

`/finances/config` changes rarely. Cache locally, TTL 24h:

```pseudocode
cached = storage.get('finances_config')
if (cached && now - cached.fetched_at < 24h) {
    return cached.value
}
// else refetch and store
```

Manual refresh: on pull-to-refresh in the finance section, invalidate the cache.

### 6.2 Summary / list cache

**Don't** cache summary or list responses across app launches — they're user-specific and date-bound, and the server is fast. Standard in-memory state for the current session is enough.

### 6.3 Optimistic updates (optional)

For delete, you can optimistically remove the row before the DELETE returns. For create/update, wait for the server response before mutating the list (the returned record has server-assigned fields like `created_at`).

---

## 7. Calendar / date-range strategy

You asked about re-using the existing calendar / week-scroller. **I can't see the current mobile codebase**, so this is a recommendation based on the API shape — confirm or redirect based on what actually exists.

### 7.1 Two different needs

| Use case | Right control |
|---|---|
| "Show me finances for a date range" (overview, lists) | **Range picker** with presets (§4.1). Date ranges don't map well to a week scroller — 30 days spans 5 weeks. |
| "Show me finances for one specific day" (drill-down) | A week scroller (like the lessons calendar) works here. Tap a day → filters list to that day. |

I'd suggest:

1. **Primary control: the date range picker.** Everything else hangs off it.
2. **Secondary: extend your existing week scroller (if you have one).** If the app already has a horizontal week strip for lessons, consider adding a "Finances" tab in that view where each day shows a `£` indicator (total amount that day) and mileage icon if any trips were logged. Tapping a day filters the list to that one date.

### 7.2 If your existing calendar is per-day (lessons UI)

- Keep that for the daily view.
- For the finances-primary screen, use the date range picker instead — it's the natural control for "last 30 days" / "this tax year" queries.
- Don't force the range into a weekly-grid view — users need long ranges (a year for tax accounting) that don't fit on a strip.

### 7.3 Integration with the existing "lessons" calendar

There's already a `PATCH /api/v1/instructor/lessons/{lesson}/mileage` endpoint for recording miles against a lesson. That's a **separate concept** from the new mileage logs:

- **Lesson mileage** = miles driven during one specific booked lesson (attached to the lesson record).
- **Mileage log** = a standalone trip record (may or may not relate to a lesson).

Don't confuse the two in the UI. If you want to show a "total miles today" figure in the lesson calendar, sum **both** sources for that date. Flag this if the UX feels off and we can unify them later.

---

## 8. Edge cases & gotchas

- **Category mismatch on Type change** — always reset Category to `'none'` when Type toggles. Otherwise you'll ship a 422 validation error to the user because (e.g.) `"fuel"` isn't valid when Type=payment.
- **Signed URL expiry** — 20 minutes. Refetch the record before opening the receipt (§5.4). If a user leaves the app backgrounded for > 20 min and returns, the thumbnail in the list is fine (images with expired URLs will just 403 and your image component will show a placeholder), but tap-to-open must refetch.
- **Amount pence vs £** — the API is in pence (integer). The UI is in £ (decimal). Convert at the boundary: `amount_pence = Math.round(parseFloat(inputValue) * 100)` and `display = (amount_pence / 100).toFixed(2)`.
- **`is_recurring=true` without frequency** — the Create form should force the Frequency dropdown to be required when Recurring is toggled on. Server will 422 otherwise.
- **Receipt upload failures** — network flaky scenario. Keep the file in memory after a failed upload so "Retry" works without re-picking. See §5.1.
- **Pagination filter changes mid-scroll** — if the user changes Type or date range while scrolled deep into a list, reset cursor state entirely (§4.5). Don't merge cursors across filters.
- **Empty states** — both list endpoints can return `data: []` with `next_cursor: null`. Render a proper empty state: "No finances in this range · Tap ＋ to add one".
- **Role guard** — these endpoints are instructor-only. If a student somehow hits them they get 403. Don't show the Finances tab in the student app role.

---

## 9. Out of scope (deliberately — will arrive in a later task)

- **Recurring materialisation.** Today, `is_recurring` is a display-only flag. Marking a payment as "monthly recurring" does NOT auto-create next month's entry. Show the badge but don't imply to the user that the app or server will fire on a schedule. A future task will add a scheduler; the API won't break when it arrives.
- **Category / mileage-type filters on list endpoints.** Only `type` (payment/expense) is filterable server-side for now. Show the filter in the overview segment but implement full category filtering later if users ask.
- **Advanced reports** (spending-by-category pie chart, year-on-year comparisons) — not exposed yet. Use the summary stats for now.
- **Offline-first writes.** If the user creates a record while offline, the app should fail politely, not queue. We can build a write queue later if it's a real need.
- **Multi-receipt per record.** Each record has at most one receipt. If users ask for multiple (some do — attaching fuel receipt + VAT invoice + bank statement to one expense), we'll extend the schema.

---

## 10. Suggested ship order

1. **Config caching + Overview screen + date range picker** (§3.1, §4.1, §6.1) — the app has something to show.
2. **List screens + cursor pagination** (§3.2, §3.3, §4.5) — users can scroll full history.
3. **Detail screens** (§3.4, §3.5) — users can tap a row.
4. **Create/Edit flows — finances + mileage** (§3.6, §3.7, §5.1, §5.2) — users can add/edit records.
5. **Receipt upload + viewer** (§4.3, §4.4, §5.4) — last, because the write flow works without it.
6. **Extend existing week scroller (optional)** if a daily drill-down is desired (§7.2).

Each step is independently useful — users can see their data after step 1 even if they can't add anything yet.

---

## 11. Questions to flag during build

Anything that blocks you, flag back and we can adjust the server:
- If there's a field the app needs that the API doesn't return, add it to the relevant Resource.
- If the cursor pagination envelope is awkward for your list library, we can add a custom envelope.
- If receipt upload behaves badly on slow mobile networks, we can add chunked upload or a longer timeout.
- If you want category-filter query params, easy to add.
- If the signed URL TTL is too short for your UX, we can lengthen it.

---

**Last Updated:** 2026-04-24
