<?php

declare(strict_types=1);

/*
 * Diary / calendar allowed time window.
 *
 * Bounds enforced on the StoreCalendarItemRequest, UpdateCalendarItemRequest,
 * and Api\V1\StoreCalendarItemRequest form requests. Frontend mirror lives at
 * resources/js/lib/diary-hours.ts (kept in sync manually).
 *
 * The window is midnight-to-midnight (24h). `end_time` is '23:59' because
 * HH:MM cannot represent 24:00 — that is the strictest representable upper
 * bound and matches the latest end_time the picker will produce.
 */
return [
    'start_hour' => 0,
    'end_hour' => 24,
    'start_time' => '00:00',
    'end_time' => '23:59',
];
