import { computed, ref } from 'vue'

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
    const currentWeekStart = ref<Date>(getMonday(new Date()))

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

    return {
        currentWeekStart,
        weekDays,
        weekEnd,
        weekStartFormatted,
        weekEndFormatted,
        goToNextWeek,
        goToPreviousWeek,
        goToToday,
        formatDate,
    }
}
