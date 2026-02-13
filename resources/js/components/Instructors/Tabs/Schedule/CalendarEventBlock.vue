<script setup lang="ts">
import { computed } from 'vue'

export interface CalendarEvent {
    id: number
    date: string
    startTime: string
    endTime: string
    isAvailable: boolean
    status: string | null
    studentName: string | null
}

interface Props {
    event: CalendarEvent
    dayStartHour: number
    rowHeight: number
}

const props = defineProps<Props>()

const emit = defineEmits<{
    click: [event: CalendarEvent]
    dragstart: [event: CalendarEvent, pointerEvent: PointerEvent]
}>()

/** Normalise "HH:MM:SS" or "HH:MM" → minutes from midnight */
function timeToMinutes(t: string): number {
    const [h, m] = t.split(':').map(Number)
    return h * 60 + m
}

/** Format "HH:MM:SS" or "HH:MM" → "HH:MM" */
function formatTime(t: string): string {
    return t.substring(0, 5)
}

/** Calculate top offset in px based on start time */
const topPx = computed(() => {
    const startMinutes = timeToMinutes(props.event.startTime)
    const dayStartMinutes = props.dayStartHour * 60
    const offsetMinutes = startMinutes - dayStartMinutes
    return (offsetMinutes / 30) * props.rowHeight
})

/** Calculate height in px based on duration */
const heightPx = computed(() => {
    const startMinutes = timeToMinutes(props.event.startTime)
    const endMinutes = timeToMinutes(props.event.endTime)
    const durationMinutes = endMinutes - startMinutes
    return (durationMinutes / 30) * props.rowHeight
})

/** Status-based color classes */
const colorClasses = computed(() => {
    const status = props.event.status

    if (status === 'booked') {
        return 'border-blue-300 bg-blue-100 text-blue-800 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
    }

    if (status === 'draft') {
        return 'border-gray-300 bg-gray-100 text-gray-800 dark:border-gray-600 dark:bg-gray-800/30 dark:text-gray-300'
    }

    if (status === 'reserved') {
        return 'border-orange-300 bg-orange-100 text-orange-800 dark:border-orange-700 dark:bg-orange-900/30 dark:text-orange-300'
    }

    // Unavailable (is_available = false, no booking status)
    if (!props.event.isAvailable) {
        return 'border-red-300 bg-red-100 text-red-800 dark:border-red-700 dark:bg-red-900/30 dark:text-red-300'
    }

    if (status === 'completed') {
        return 'border-green-300 bg-green-100 text-green-800 dark:border-green-700 dark:bg-green-900/30 dark:text-green-300'
    }

    // Available (is_available = true, status null/available)
    return 'border-yellow-300 bg-yellow-100 text-yellow-800 dark:border-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300'
})

/** Status label for display */
const statusLabel = computed(() => {
    const status = props.event.status

    if (status === 'booked') return 'Booked'
    if (status === 'draft') return 'Draft'
    if (status === 'reserved') return 'Reserved'
    if (status === 'completed') return 'Completed'

    return props.event.isAvailable ? 'Available' : 'Unavailable'
})

function handleClick(e: MouseEvent) {
    e.stopPropagation()
    emit('click', props.event)
}

function handlePointerDown(e: PointerEvent) {
    e.stopPropagation()
    emit('dragstart', props.event, e)
}
</script>

<template>
    <div
        class="absolute inset-x-1 z-10 cursor-pointer select-none overflow-hidden rounded-md border px-2 py-1 text-xs leading-tight transition-shadow hover:shadow-md"
        :class="colorClasses"
        :style="{ top: `${topPx}px`, height: `${heightPx}px`, minHeight: '20px' }"
        @click="handleClick"
        @pointerdown="handlePointerDown"
    >
        <div class="font-medium">
            {{ formatTime(event.startTime) }} - {{ formatTime(event.endTime) }}
        </div>
        <div v-if="heightPx > 30" class="mt-0.5 opacity-75">
            {{ statusLabel }}
        </div>
        <div v-if="event.studentName && heightPx > 50" class="mt-0.5 truncate font-medium">
            {{ event.studentName }}
        </div>
    </div>
</template>
