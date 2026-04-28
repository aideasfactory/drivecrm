<?php

declare(strict_types=1);

/*
 * Diary / calendar allowed time window.
 *
 * Bounds enforced on the StoreCalendarItemRequest, UpdateCalendarItemRequest,
 * and Api\V1\StoreCalendarItemRequest form requests. Frontend mirror lives at
 * resources/js/lib/diary-hours.ts (kept in sync manually).
 */
return [
    'start_hour' => 6,
    'end_hour' => 21,
    'start_time' => '06:00',
    'end_time' => '21:00',
];
