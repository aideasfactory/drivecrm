<script setup lang="ts">
import { computed } from 'vue'
import type { CalendarEvent } from './CalendarEventBlock.vue'
import { formatDate } from '@/composables/useCalendarNavigation'

interface Props {
    monthDays: Date[]
    currentMonth: Date
    events: CalendarEvent[]
}

const props = defineProps<Props>()

const emit = defineEmits<{
    clickDay: [date: string]
    eventClick: [event: CalendarEvent]
}>()

const dayHeaders = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

/** Events grouped by date string */
const eventsByDate = computed(() => {
    const map = new Map<string, CalendarEvent[]>()
    for (const evt of props.events) {
        const list = map.get(evt.date)
        if (list) {
            list.push(evt)
        } else {
            map.set(evt.date, [evt])
        }
    }
    return map
})

/** Rows of 7 days for the grid */
const weeks = computed(() => {
    const rows: Date[][] = []
    for (let i = 0; i < props.monthDays.length; i += 7) {
        rows.push(props.monthDays.slice(i, i + 7))
    }
    return rows
})

function isToday(date: Date): boolean {
    const now = new Date()
    return (
        date.getFullYear() === now.getFullYear() &&
        date.getMonth() === now.getMonth() &&
        date.getDate() === now.getDate()
    )
}

function isCurrentMonth(date: Date): boolean {
    return date.getMonth() === props.currentMonth.getMonth() &&
        date.getFullYear() === props.currentMonth.getFullYear()
}

/** Status-based dot color */
function dotColor(event: CalendarEvent): string {
    if (event.itemType === 'practical_test') return 'bg-teal-500'

    const status = event.status

    if (status === 'booked') return 'bg-blue-500'
    if (status === 'draft') return 'bg-gray-400'
    if (status === 'reserved') return 'bg-orange-500'
    if (status === 'completed') return 'bg-green-500'
    if (!event.isAvailable) return 'bg-red-500'

    return 'bg-yellow-500'
}

/** Status-based event pill classes */
function pillClasses(event: CalendarEvent): string {
    if (event.itemType === 'practical_test') return 'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-300'

    const status = event.status

    if (status === 'booked') return 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300'
    if (status === 'draft') return 'bg-gray-100 text-gray-700 dark:bg-gray-800/40 dark:text-gray-300'
    if (status === 'reserved') return 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300'
    if (status === 'completed') return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
    if (!event.isAvailable) return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'

    return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'
}

function formatTime(t: string): string {
    return t.substring(0, 5)
}

function statusLabel(event: CalendarEvent): string {
    if (event.itemType === 'practical_test') return 'Practical Test'

    const status = event.status

    if (status === 'booked') return 'Booked'
    if (status === 'draft') return 'Draft'
    if (status === 'reserved') return 'Reserved'
    if (status === 'completed') return 'Completed'

    return event.isAvailable ? 'Available' : 'Unavailable'
}

const MAX_VISIBLE_EVENTS = 3
</script>

<template>
    <div>
        <!-- Day headers -->
        <div class="grid grid-cols-7 border-b border-border">
            <div
                v-for="header in dayHeaders"
                :key="header"
                class="border-r border-border px-2 py-2 text-center text-xs font-medium text-muted-foreground last:border-r-0"
            >
                {{ header }}
            </div>
        </div>

        <!-- Week rows -->
        <div
            v-for="(week, weekIdx) in weeks"
            :key="weekIdx"
            class="grid grid-cols-7"
        >
            <div
                v-for="(day, dayIdx) in week"
                :key="dayIdx"
                class="min-h-[100px] cursor-pointer border-b border-r border-border p-1.5 transition-colors hover:bg-muted/50 last:border-r-0"
                :class="{
                    'bg-primary/5 dark:bg-primary/10': isToday(day),
                    'opacity-40': !isCurrentMonth(day),
                }"
                @click="emit('clickDay', formatDate(day))"
            >
                <!-- Day number -->
                <div class="mb-1 flex items-center justify-between">
                    <span
                        class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium"
                        :class="{
                            'bg-primary text-primary-foreground': isToday(day),
                            'text-foreground': !isToday(day) && isCurrentMonth(day),
                            'text-muted-foreground': !isCurrentMonth(day),
                        }"
                    >
                        {{ day.getDate() }}
                    </span>

                    <!-- Event count badge (when more than MAX_VISIBLE_EVENTS) -->
                    <span
                        v-if="(eventsByDate.get(formatDate(day))?.length ?? 0) > MAX_VISIBLE_EVENTS"
                        class="text-[10px] font-medium text-muted-foreground"
                    >
                        {{ eventsByDate.get(formatDate(day))?.length }} slots
                    </span>
                </div>

                <!-- Event pills (show up to MAX_VISIBLE_EVENTS) -->
                <div class="space-y-0.5">
                    <div
                        v-for="evt in (eventsByDate.get(formatDate(day)) || []).slice(0, MAX_VISIBLE_EVENTS)"
                        :key="evt.id"
                        class="flex items-center gap-1 rounded px-1 py-0.5 text-[10px] leading-tight"
                        :class="pillClasses(evt)"
                        @click.stop="emit('eventClick', evt)"
                    >
                        <span class="inline-block h-1.5 w-1.5 flex-shrink-0 rounded-full" :class="dotColor(evt)"></span>
                        <span class="truncate">{{ formatTime(evt.startTime) }} {{ statusLabel(evt) }}</span>
                    </div>

                    <!-- Overflow indicator -->
                    <div
                        v-if="(eventsByDate.get(formatDate(day))?.length ?? 0) > MAX_VISIBLE_EVENTS"
                        class="px-1 text-[10px] text-muted-foreground"
                    >
                        +{{ (eventsByDate.get(formatDate(day))?.length ?? 0) - MAX_VISIBLE_EVENTS }} more
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
