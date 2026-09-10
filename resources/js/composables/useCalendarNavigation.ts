import { computed, ref } from 'vue'

export type CalendarView = 'day' | 'week' | 'month'

/**
 * Midnight on the given date.
 */
function startOfDay(date: Date): Date {
    const d = new Date(date)
    d.setHours(0, 0, 0, 0)
    return d
}

/**
 * Get Monday of the week containing the given date.
 */
function getMonday(date: Date): Date {
    const d = startOfDay(date)
    const day = d.getDay()
    const diff = day === 0 ? -6 : 1 - day // Monday = 1
    d.setDate(d.getDate() + diff)
    return d
}

function isSameDay(a: Date, b: Date): boolean {
    return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    )
}

/**
 * Format a Date as YYYY-MM-DD.
 */
export function formatDate(date: Date): string {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
}

export function useCalendarNavigation() {
    const currentView = ref<CalendarView>('week')
    const currentDay = ref<Date>(startOfDay(new Date()))
    const currentWeekStart = ref<Date>(getMonday(new Date()))
    const currentMonth = ref<Date>(new Date(new Date().getFullYear(), new Date().getMonth(), 1))

    // ── Day ─────────────────────────────────────────────────
    const dayDays = computed<Date[]>(() => [currentDay.value])

    const dayStartFormatted = computed(() => formatDate(currentDay.value))
    const dayEndFormatted = computed(() => formatDate(currentDay.value))

    function goToNextDay() {
        const next = new Date(currentDay.value)
        next.setDate(next.getDate() + 1)
        syncFromDate(next)
    }

    function goToPreviousDay() {
        const prev = new Date(currentDay.value)
        prev.setDate(prev.getDate() - 1)
        syncFromDate(prev)
    }

    function goToCurrentDay() {
        syncFromDate(new Date())
    }

    // ── Week ────────────────────────────────────────────────
    const weekDays = computed<Date[]>(() => {
        const days: Date[] = []
        for (let i = 0; i < 7; i++) {
            const d = new Date(currentWeekStart.value)
            d.setDate(d.getDate() + i)
            days.push(d)
        }
        return days
    })

    const weekEnd = computed<Date>(() => {
        const d = new Date(currentWeekStart.value)
        d.setDate(d.getDate() + 6)
        return d
    })

    const weekStartFormatted = computed(() => formatDate(currentWeekStart.value))
    const weekEndFormatted = computed(() => formatDate(weekEnd.value))

    function goToNextWeek() {
        const next = new Date(currentWeekStart.value)
        next.setDate(next.getDate() + 7)
        currentWeekStart.value = next
        const nextEnd = new Date(next)
        nextEnd.setDate(nextEnd.getDate() + 6)
        if (currentDay.value < next || currentDay.value > nextEnd) {
            currentDay.value = new Date(next)
        }
        currentMonth.value = new Date(next.getFullYear(), next.getMonth(), 1)
    }

    function goToPreviousWeek() {
        const prev = new Date(currentWeekStart.value)
        prev.setDate(prev.getDate() - 7)
        currentWeekStart.value = prev
        const prevEnd = new Date(prev)
        prevEnd.setDate(prevEnd.getDate() + 6)
        if (currentDay.value < prev || currentDay.value > prevEnd) {
            currentDay.value = new Date(prev)
        }
        currentMonth.value = new Date(prev.getFullYear(), prev.getMonth(), 1)
    }

    function goToToday() {
        syncFromDate(new Date())
    }

    // ── Month ───────────────────────────────────────────────

    /** All calendar cells for the month grid (includes leading/trailing days from adjacent months). */
    const monthDays = computed<Date[]>(() => {
        const year = currentMonth.value.getFullYear()
        const month = currentMonth.value.getMonth()

        // First day of the month
        const firstDay = new Date(year, month, 1)
        // Last day of the month
        const lastDay = new Date(year, month + 1, 0)

        // Start from Monday of the week containing the first day
        const start = getMonday(firstDay)

        // End on Sunday of the week containing the last day
        const endDate = new Date(lastDay)
        const endDayOfWeek = endDate.getDay()
        if (endDayOfWeek !== 0) {
            endDate.setDate(endDate.getDate() + (7 - endDayOfWeek))
        }
        endDate.setHours(0, 0, 0, 0)

        const days: Date[] = []
        const cursor = new Date(start)
        while (cursor <= endDate) {
            days.push(new Date(cursor))
            cursor.setDate(cursor.getDate() + 1)
        }

        return days
    })

    const monthStartFormatted = computed(() => {
        if (monthDays.value.length === 0) return ''
        return formatDate(monthDays.value[0])
    })

    const monthEndFormatted = computed(() => {
        if (monthDays.value.length === 0) return ''
        return formatDate(monthDays.value[monthDays.value.length - 1])
    })

    function goToNextMonth() {
        const next = new Date(currentMonth.value)
        next.setMonth(next.getMonth() + 1)
        currentMonth.value = next
        syncDayAndWeekIfOutsideMonth(next)
    }

    function goToPreviousMonth() {
        const prev = new Date(currentMonth.value)
        prev.setMonth(prev.getMonth() - 1)
        currentMonth.value = prev
        syncDayAndWeekIfOutsideMonth(prev)
    }

    function goToCurrentMonth() {
        syncFromDate(new Date())
    }

    function syncDayAndWeekIfOutsideMonth(monthStart: Date) {
        const inMonth =
            currentDay.value.getFullYear() === monthStart.getFullYear() &&
            currentDay.value.getMonth() === monthStart.getMonth()

        if (!inMonth) {
            syncFromDate(monthStart)
        }
    }

    function syncFromDate(date: Date) {
        const day = startOfDay(date)
        currentDay.value = day
        currentWeekStart.value = getMonday(day)
        currentMonth.value = new Date(day.getFullYear(), day.getMonth(), 1)
    }

    function setView(view: CalendarView) {
        if (view === currentView.value) {
            return
        }

        if (view === 'day') {
            const today = startOfDay(new Date())
            if (currentView.value === 'week') {
                const match = weekDays.value.find((d) => isSameDay(d, today))
                currentDay.value = startOfDay(match ?? weekDays.value[0] ?? today)
            } else if (currentView.value === 'month') {
                const inMonth =
                    today.getFullYear() === currentMonth.value.getFullYear() &&
                    today.getMonth() === currentMonth.value.getMonth()
                currentDay.value = inMonth ? today : new Date(currentMonth.value)
            }
            currentWeekStart.value = getMonday(currentDay.value)
            currentMonth.value = new Date(currentDay.value.getFullYear(), currentDay.value.getMonth(), 1)
        }

        if (view === 'week') {
            currentWeekStart.value = getMonday(currentDay.value)
        }

        if (view === 'month') {
            currentMonth.value = new Date(currentDay.value.getFullYear(), currentDay.value.getMonth(), 1)
        }

        currentView.value = view
    }

    // ── View-aware date range ───────────────────────────────
    const rangeStartFormatted = computed(() => {
        if (currentView.value === 'day') {
            return dayStartFormatted.value
        }

        return currentView.value === 'week' ? weekStartFormatted.value : monthStartFormatted.value
    })

    const rangeEndFormatted = computed(() => {
        if (currentView.value === 'day') {
            return dayEndFormatted.value
        }

        return currentView.value === 'week' ? weekEndFormatted.value : monthEndFormatted.value
    })

    const visibleDays = computed<Date[]>(() => {
        if (currentView.value === 'day') {
            return dayDays.value
        }

        return currentView.value === 'week' ? weekDays.value : monthDays.value
    })

    return {
        currentView,
        currentDay,
        currentWeekStart,
        currentMonth,
        dayDays,
        dayStartFormatted,
        dayEndFormatted,
        goToNextDay,
        goToPreviousDay,
        goToCurrentDay,
        weekDays,
        weekEnd,
        weekStartFormatted,
        weekEndFormatted,
        goToNextWeek,
        goToPreviousWeek,
        goToToday,
        monthDays,
        monthStartFormatted,
        monthEndFormatted,
        goToNextMonth,
        goToPreviousMonth,
        goToCurrentMonth,
        setView,
        visibleDays,
        rangeStartFormatted,
        rangeEndFormatted,
        formatDate,
    }
}
