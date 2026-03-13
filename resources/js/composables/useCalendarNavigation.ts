import { computed, ref } from 'vue'

export type CalendarView = 'week' | 'month'

/**
 * Get Monday of the week containing the given date.
 */
function getMonday(date: Date): Date {
    const d = new Date(date)
    const day = d.getDay()
    const diff = day === 0 ? -6 : 1 - day // Monday = 1
    d.setDate(d.getDate() + diff)
    d.setHours(0, 0, 0, 0)
    return d
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
    const currentWeekStart = ref<Date>(getMonday(new Date()))
    const currentMonth = ref<Date>(new Date(new Date().getFullYear(), new Date().getMonth(), 1))

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
    }

    function goToPreviousWeek() {
        const prev = new Date(currentWeekStart.value)
        prev.setDate(prev.getDate() - 7)
        currentWeekStart.value = prev
    }

    function goToToday() {
        currentWeekStart.value = getMonday(new Date())
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
    }

    function goToPreviousMonth() {
        const prev = new Date(currentMonth.value)
        prev.setMonth(prev.getMonth() - 1)
        currentMonth.value = prev
    }

    function goToCurrentMonth() {
        currentMonth.value = new Date(new Date().getFullYear(), new Date().getMonth(), 1)
    }

    // ── View-aware date range ───────────────────────────────
    const rangeStartFormatted = computed(() =>
        currentView.value === 'week' ? weekStartFormatted.value : monthStartFormatted.value,
    )

    const rangeEndFormatted = computed(() =>
        currentView.value === 'week' ? weekEndFormatted.value : monthEndFormatted.value,
    )

    return {
        currentView,
        currentWeekStart,
        currentMonth,
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
        rangeStartFormatted,
        rangeEndFormatted,
        formatDate,
    }
}
