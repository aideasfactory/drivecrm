<script setup lang="ts">
import { computed } from 'vue'

export interface CalendarEvent {
    id: number
    date: string
    startTime: string
    endTime: string
    isAvailable: boolean
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
        :class="
            event.isAvailable
                ? 'border-green-300 bg-green-100 text-green-800 dark:border-green-700 dark:bg-green-900/30 dark:text-green-300'
                : 'border-red-300 bg-red-100 text-red-800 dark:border-red-700 dark:bg-red-900/30 dark:text-red-300'
        "
        :style="{ top: `${topPx}px`, height: `${heightPx}px`, minHeight: '20px' }"
        @click="handleClick"
        @pointerdown="handlePointerDown"
    >
        <div class="font-medium">
            {{ formatTime(event.startTime) }} - {{ formatTime(event.endTime) }}
        </div>
        <div v-if="heightPx > 30" class="mt-0.5 opacity-75">
            {{ event.isAvailable ? 'Available' : 'Unavailable' }}
        </div>
    </div>
</template>
