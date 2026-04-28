/**
 * Diary / calendar allowed time window.
 *
 * Single source of truth for the hour bounds that constrain when diary
 * entries (lessons, availability slots, practical tests) may start and end.
 * Both the visible calendar grid and the start-time picker import from here.
 *
 * Backend mirror: config/diary.php (kept in sync manually).
 */
export const DIARY_START_HOUR = 6
export const DIARY_END_HOUR = 21

export const DIARY_START_MINUTES = DIARY_START_HOUR * 60
export const DIARY_END_MINUTES = DIARY_END_HOUR * 60
