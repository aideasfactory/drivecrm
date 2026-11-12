const TIME_ZONE = 'Europe/London'

/** Mirrors `booking_payments.instructor_hold_hours_before_lesson`. */
export const CANCELLATION_PERIOD_HOURS = 48

export const CANCELLATION_PERIOD_WARNING =
    "This lesson is being booked within the 48 hour cancellation period and won't be cancelled automatically"

const wallClockFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: TIME_ZONE,
    hourCycle: 'h23',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
})

/** The instant a UK wall-clock date (YYYY-MM-DD) and time (HH:MM) refers to. */
const londonWallClockToDate = (date: string, time: string): Date => {
    const [year, month, day] = date.slice(0, 10).split('-').map(Number)
    const [hour, minute] = time.split(':').map(Number)
    const asUtc = Date.UTC(year, month - 1, day, hour, minute)

    const parts = Object.fromEntries(
        wallClockFormatter.formatToParts(new Date(asUtc)).map((part) => [part.type, part.value]),
    )
    const londonAsUtc = Date.UTC(
        Number(parts.year),
        Number(parts.month) - 1,
        Number(parts.day),
        Number(parts.hour),
        Number(parts.minute),
    )

    return new Date(asUtc - (londonAsUtc - asUtc))
}

/**
 * Whether a first lesson starting at this UK date and time is inside the
 * cancellation period, so an unpaid instructor booking is not cancelled
 * automatically. Matches `BookingPayments::isWithinCancellationPeriod()`.
 */
export const isWithinCancellationPeriod = (date: string, startTime: string): boolean => {
    if (!date || !startTime) {
        return false
    }

    const startsAt = londonWallClockToDate(date, startTime)

    return Date.now() >= startsAt.getTime() - CANCELLATION_PERIOD_HOURS * 60 * 60 * 1000
}
