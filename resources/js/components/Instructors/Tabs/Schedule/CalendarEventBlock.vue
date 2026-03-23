<script setup lang="ts">
import { computed } from 'vue'
import {
    Check,
    X,
    Flag,
    PoundSterling,
    CircleOff,
} from 'lucide-vue-next'

import type { CalendarItemTypeValue, RecurrencePattern } from '@/types/instructor'

export interface CalendarEvent {
    id: number
    date: string
    startTime: string
    endTime: string
    isAvailable: boolean
    status: string | null
    itemType: CalendarItemTypeValue
    travelTimeMinutes: number | null
    parentItemId: number | null
    studentName: string | null
    isPaid: boolean | null
    notes: string | null
    unavailabilityReason: string | null
    recurrencePattern: RecurrencePattern
    recurrenceGroupId: string | null
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

/** Whether this event is a travel-time block */
const isTravel = computed(() => props.event.itemType === 'travel')

/** Whether this event is a practical test slot */
const isPracticalTest = computed(() => props.event.itemType === 'practical_test')

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

/** Whether the booked/completed slot has been paid */
const isPaid = computed(() => props.event.isPaid === true)

/** Whether the slot has a booking (booked or completed) */
const hasBooking = computed(() => {
    const status = props.event.status
    return status === 'booked' || status === 'completed'
})

/** Status-based color classes */
const colorClasses = computed(() => {
    // Travel-time blocks — purple dashed
    if (isTravel.value) {
        return 'border-purple-300 bg-purple-100 text-purple-800 dark:border-purple-700 dark:bg-purple-900/30 dark:text-purple-300'
    }

    // Practical test slots — teal
    if (isPracticalTest.value) {
        return 'border-teal-300 bg-teal-100 text-teal-800 dark:border-teal-700 dark:bg-teal-900/30 dark:text-teal-300'
    }

    const status = props.event.status

    // Completed — green
    if (status === 'completed') {
        return 'border-green-300 bg-green-100 text-green-800 dark:border-green-700 dark:bg-green-900/30 dark:text-green-300'
    }

    // Booked & paid — amber
    if (status === 'booked' && isPaid.value) {
        return 'border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
    }

    // Booked & not paid — gray
    if (status === 'booked') {
        return 'border-gray-300 bg-gray-100 text-gray-800 dark:border-gray-600 dark:bg-gray-800/30 dark:text-gray-300'
    }

    // Draft — gray (lighter)
    if (status === 'draft') {
        return 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-600 dark:bg-gray-800/20 dark:text-gray-400'
    }

    // Reserved — orange
    if (status === 'reserved') {
        return 'border-orange-300 bg-orange-100 text-orange-800 dark:border-orange-700 dark:bg-orange-900/30 dark:text-orange-300'
    }

    // Unavailable — red
    if (!props.event.isAvailable) {
        return 'border-red-300 bg-red-100 text-red-800 dark:border-red-700 dark:bg-red-900/30 dark:text-red-300'
    }

    // Available — blue
    return 'border-blue-300 bg-blue-100 text-blue-800 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
})

/** Status label for display */
const statusLabel = computed(() => {
    if (isTravel.value) return 'Travel'
    if (isPracticalTest.value) return 'Practical Test'

    const status = props.event.status

    if (status === 'completed') return 'Completed'
    if (status === 'booked' && isPaid.value) return 'Booked & Paid'
    if (status === 'booked') return 'Booked (Unpaid)'
    if (status === 'draft') return 'Draft'
    if (status === 'reserved') return 'Reserved'

    return props.event.isAvailable ? 'Available' : 'Unavailable'
})

/** Whether to show the status flag icon */
const showFlag = computed(() => {
    if (isTravel.value || isPracticalTest.value) return false
    const status = props.event.status
    return status === 'booked' || status === 'completed' || !props.event.isAvailable
})

/** Whether to show the payment icon */
const showPaymentIcon = computed(() => {
    return hasBooking.value && props.event.isPaid !== null
})

function handleClick(e: MouseEvent) {
    e.stopPropagation()
    emit('click', props.event)
}

function handlePointerDown(e: PointerEvent) {
    // Prevent dragging travel-time and practical test blocks
    if (isTravel.value || isPracticalTest.value) return
    e.stopPropagation()
    emit('dragstart', props.event, e)
}
</script>

<template>
    <div
        class="absolute inset-x-1 z-10 select-none overflow-hidden rounded-md border px-2 py-1 text-xs leading-tight transition-shadow"
        :class="[
            colorClasses,
            isTravel ? 'cursor-default border-dashed opacity-80' : '',
            isPracticalTest ? 'cursor-pointer border-solid' : '',
            !isTravel && !isPracticalTest ? 'cursor-pointer hover:shadow-md' : '',
        ]"
        :style="{ top: `${topPx}px`, height: `${heightPx}px`, minHeight: '20px' }"
        @click="handleClick"
        @pointerdown="handlePointerDown"
    >
        <div class="flex items-center gap-1 font-medium">
            <!-- Travel icon for travel blocks -->
            <svg v-if="isTravel" class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            </svg>
            <!-- Practical test icon -->
            <svg v-if="isPracticalTest" class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <!-- Status flag icons -->
            <Flag v-if="showFlag && event.status === 'completed'" class="h-3 w-3 shrink-0" />
            <Check v-if="showFlag && event.status === 'booked'" class="h-3 w-3 shrink-0" />
            <X v-if="showFlag && !event.isAvailable && event.status !== 'booked' && event.status !== 'completed'" class="h-3 w-3 shrink-0" />
            <!-- Payment icons -->
            <PoundSterling v-if="showPaymentIcon && isPaid" class="h-3 w-3 shrink-0" />
            <CircleOff v-if="showPaymentIcon && !isPaid" class="h-3 w-3 shrink-0" />
            <span>{{ formatTime(event.startTime) }} - {{ formatTime(event.endTime) }}</span>
            <!-- Recurrence indicator -->
            <svg v-if="event.recurrencePattern && event.recurrencePattern !== 'none'" class="h-3 w-3 shrink-0 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Recurring">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
        </div>
        <div v-if="heightPx > 30" class="mt-0.5 opacity-75">
            {{ statusLabel }}
        </div>
        <div v-if="event.studentName && heightPx > 50" class="mt-0.5 truncate font-medium">
            {{ event.studentName }}
        </div>
        <!-- Notes indicator -->
        <div v-if="event.notes && !isTravel && heightPx > 40" class="mt-0.5 flex items-center gap-1 truncate opacity-75">
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span class="truncate">{{ event.notes }}</span>
        </div>
        <!-- Unavailability reason (only shown when unavailable and not travel) -->
        <div v-if="!event.isAvailable && !isTravel && event.unavailabilityReason && heightPx > 40" class="mt-0.5 flex items-center gap-1 truncate text-red-700 dark:text-red-300">
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span class="truncate">{{ event.unavailabilityReason }}</span>
        </div>
    </div>
</template>
