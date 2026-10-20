const TIME_ZONE = 'Europe/London'

const dayKeyFormatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: TIME_ZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
})

const timeFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: TIME_ZONE,
    hour: '2-digit',
    minute: '2-digit',
})

const shortDateFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: TIME_ZONE,
    day: 'numeric',
    month: 'short',
})

const longDateFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: TIME_ZONE,
    day: 'numeric',
    month: 'short',
    year: 'numeric',
})

/** Calendar day of the instant in Europe/London, as YYYY-MM-DD. */
const londonDayKey = (date: Date): string => dayKeyFormatter.format(date)

const previousDayKey = (dayKey: string): string => {
    const [year, month, day] = dayKey.split('-').map(Number)

    return new Date(Date.UTC(year, month - 1, day - 1)).toISOString().slice(0, 10)
}

/**
 * Day label compared by calendar day in Europe/London (not elapsed hours):
 * "Today", "Yesterday", "18 Oct", or "18 Oct 2025" for other years.
 */
export const formatRelativeDay = (value: string | Date, now: Date = new Date()): string => {
    const date = value instanceof Date ? value : new Date(value)
    const dayKey = londonDayKey(date)
    const todayKey = londonDayKey(now)

    if (dayKey === todayKey) return 'Today'
    if (dayKey === previousDayKey(todayKey)) return 'Yesterday'
    if (dayKey.slice(0, 4) === todayKey.slice(0, 4)) return shortDateFormatter.format(date)

    return longDateFormatter.format(date)
}

/** e.g. "Today at 16:31", "Yesterday at 16:26", "18 Oct at 15:15" (Europe/London). */
export const formatRelativeDateTime = (value: string | Date, now: Date = new Date()): string => {
    const date = value instanceof Date ? value : new Date(value)

    return `${formatRelativeDay(date, now)} at ${timeFormatter.format(date)}`
}
