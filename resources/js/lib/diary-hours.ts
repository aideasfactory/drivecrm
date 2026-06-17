/**
 * Diary / calendar allowed time window.
 *
 * Single source of truth for the hour bounds that constrain when diary
 * entries (lessons, availability slots, practical tests) may start and end.
 * Both the visible calendar grid and the start-time picker import from here.
 *
 * Backend mirror: config/diary.php (kept in sync manually).
 */
export const DIARY_START_HOUR = 0
export const DIARY_END_HOUR = 24

export const DIARY_START_MINUTES = DIARY_START_HOUR * 60
export const DIARY_END_MINUTES = DIARY_END_HOUR * 60

/**
 * Latest end_time (in minutes since midnight) that can be stored as HH:MM.
 *
 * HH:MM has no representation for 24:00, so the bookable upper bound is
 * 23:45 — the last 15-minute increment before midnight. The visible grid
 * still extends to 24:00; only the *start* of a new regular (2-hour) slot
 * is constrained by this value, not what's drawn.
 */
export const DIARY_MAX_END_MINUTES = 23 * 60 + 45
