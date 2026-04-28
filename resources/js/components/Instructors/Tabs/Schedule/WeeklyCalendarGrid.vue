<script setup lang="ts">
import { computed, ref } from 'vue'
import CalendarEventBlock from './CalendarEventBlock.vue'
import type { CalendarEvent } from './CalendarEventBlock.vue'
import { formatDate } from '@/composables/useCalendarNavigation'
import { DIARY_START_HOUR, DIARY_END_HOUR } from '@/lib/diary-hours'

interface Props {
    weekDays: Date[]
    events: CalendarEvent[]
}

const props = defineProps<Props>()

const emit = defineEmits<{
    clickSlot: [date: string, time: string]
    eventClick: [event: CalendarEvent]
    eventMove: [eventId: number, newDate: string, newStartTime: string, newEndTime: string]
}>()

// ── Constants ────────────────────────────────────────────
const DAY_START_HOUR = DIARY_START_HOUR
const DAY_END_HOUR = DIARY_END_HOUR
const ROW_HEIGHT = 40 // px per 30-min slot (visual grid row)
const SLOT_COUNT = (DAY_END_HOUR - DAY_START_HOUR) * 2 // half-hour visual rows
const SLOT_DURATION_HOURS = 2 // lesson slots are always 2 hours
const SNAP_MINUTES = 15 // drag & click snap to 15-minute increments
const SNAP_PX = ROW_HEIGHT / 2 // 15 min = half a visual row = 20px

// ── Time labels ──────────────────────────────────────────
const timeLabels = computed(() => {
    const labels: string[] = []
    for (let h = DAY_START_HOUR; h < DAY_END_HOUR; h++) {
        labels.push(`${String(h).padStart(2, '0')}:00`)
        labels.push(`${String(h).padStart(2, '0')}:30`)
    }
    return labels
})

// ── Events grouped by date ───────────────────────────────
const eventsByDate = computed(() => {
    const map = new Map<string, CalendarEvent[]>()
    for (const day of props.weekDays) {
        map.set(formatDate(day), [])
    }
    for (const evt of props.events) {
        const list = map.get(evt.date)
        if (list) list.push(evt)
    }
    return map
})

// ── Overlap layout: assign columns to overlapping events ─
interface EventLayout {
    columnIndex: number
    totalColumns: number
}

/**
 * For a list of events on one day, compute which visual column each should occupy.
 * Overlapping events get side-by-side columns (like Google Calendar).
 */
function computeOverlapLayout(events: CalendarEvent[]): Map<number, EventLayout> {
    const layout = new Map<number, EventLayout>()
    if (events.length === 0) return layout

    // Sort by start time, then by end time descending (longer events first)
    const sorted = [...events].sort((a, b) => {
        const diff = timeToMinutes(a.startTime) - timeToMinutes(b.startTime)
        if (diff !== 0) return diff
        return timeToMinutes(b.endTime) - timeToMinutes(a.endTime)
    })

    // Assign columns using a greedy algorithm
    const columns: { endMinutes: number; eventId: number }[][] = []

    for (const evt of sorted) {
        const startMin = timeToMinutes(evt.startTime)
        const endMin = timeToMinutes(evt.endTime)

        // Find the first column where this event doesn't overlap
        let placed = false
        for (let col = 0; col < columns.length; col++) {
            const lastInCol = columns[col][columns[col].length - 1]
            if (lastInCol.endMinutes <= startMin) {
                columns[col].push({ endMinutes: endMin, eventId: evt.id })
                layout.set(evt.id, { columnIndex: col, totalColumns: 0 }) // totalColumns set later
                placed = true
                break
            }
        }

        if (!placed) {
            columns.push([{ endMinutes: endMin, eventId: evt.id }])
            layout.set(evt.id, { columnIndex: columns.length - 1, totalColumns: 0 })
        }
    }

    // Now determine totalColumns for each overlap group
    // An overlap group is a set of events that transitively overlap
    const groups: CalendarEvent[][] = []
    let currentGroup: CalendarEvent[] = []
    let groupEnd = 0

    for (const evt of sorted) {
        const startMin = timeToMinutes(evt.startTime)
        const endMin = timeToMinutes(evt.endTime)

        if (currentGroup.length === 0 || startMin < groupEnd) {
            currentGroup.push(evt)
            groupEnd = Math.max(groupEnd, endMin)
        } else {
            groups.push(currentGroup)
            currentGroup = [evt]
            groupEnd = endMin
        }
    }
    if (currentGroup.length > 0) groups.push(currentGroup)

    // Set totalColumns for each event in the group
    for (const group of groups) {
        // Find max column index in this group
        let maxCol = 0
        for (const evt of group) {
            const l = layout.get(evt.id)
            if (l && l.columnIndex > maxCol) maxCol = l.columnIndex
        }
        const totalCols = maxCol + 1
        for (const evt of group) {
            const l = layout.get(evt.id)
            if (l) l.totalColumns = totalCols
        }
    }

    return layout
}

/** Computed overlap layouts per date */
const overlapLayoutByDate = computed(() => {
    const map = new Map<string, Map<number, EventLayout>>()
    for (const [date, events] of eventsByDate.value) {
        map.set(date, computeOverlapLayout(events))
    }
    return map
})

// ── Day header formatting ────────────────────────────────
const dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

function isToday(date: Date): boolean {
    const now = new Date()
    return (
        date.getFullYear() === now.getFullYear() &&
        date.getMonth() === now.getMonth() &&
        date.getDate() === now.getDate()
    )
}

// ── Click on empty slot (snap to nearest 15-min) ────────
function handleSlotClick(dayDate: Date, slotIndex: number) {
    const date = formatDate(dayDate)
    const totalMinutes = DAY_START_HOUR * 60 + slotIndex * 30
    // Round down to nearest 15-min increment
    const snappedMinutes = Math.floor(totalMinutes / SNAP_MINUTES) * SNAP_MINUTES
    // Clamp so the 2-hour slot fits within the day
    const maxStart = (DAY_END_HOUR - SLOT_DURATION_HOURS) * 60
    const clampedMinutes = Math.max(DAY_START_HOUR * 60, Math.min(snappedMinutes, maxStart))
    const time = minutesToTime(clampedMinutes)
    emit('clickSlot', date, time)
}

// ── Drag and Drop ────────────────────────────────────────
const dragging = ref<{
    event: CalendarEvent
    startY: number
    startX: number
    offsetY: number
    ghostTop: number
    ghostLeft: number
    ghostWidth: number
    ghostHeight: number
    currentDayIndex: number
    isDragging: boolean
} | null>(null)

const gridRef = ref<HTMLElement | null>(null)

function timeToMinutes(t: string): number {
    const [h, m] = t.split(':').map(Number)
    return h * 60 + m
}

function minutesToTime(minutes: number): string {
    const h = Math.floor(minutes / 60) % 24
    const m = minutes % 60
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

function handleEventDragStart(event: CalendarEvent, pointerEvent: PointerEvent) {
    const target = pointerEvent.currentTarget as HTMLElement
    if (!target || !gridRef.value) return

    const gridRect = gridRef.value.getBoundingClientRect()
    const targetRect = target.getBoundingClientRect()

    const startMinutes = timeToMinutes(event.startTime)
    const endMinutes = timeToMinutes(event.endTime)
    const durationMinutes = endMinutes - startMinutes
    const height = (durationMinutes / 30) * ROW_HEIGHT

    // Find which day column this event is in
    const dayIndex = props.weekDays.findIndex(
        (d) => formatDate(d) === event.date,
    )

    dragging.value = {
        event,
        startY: pointerEvent.clientY,
        startX: pointerEvent.clientX,
        offsetY: pointerEvent.clientY - targetRect.top,
        ghostTop: targetRect.top - gridRect.top,
        ghostLeft: targetRect.left - gridRect.left,
        ghostWidth: targetRect.width,
        ghostHeight: height,
        currentDayIndex: dayIndex,
        isDragging: false,
    }

    window.addEventListener('pointermove', handlePointerMove)
    window.addEventListener('pointerup', handlePointerUp)
}

function handlePointerMove(e: PointerEvent) {
    if (!dragging.value || !gridRef.value) return

    const dx = Math.abs(e.clientX - dragging.value.startX)
    const dy = Math.abs(e.clientY - dragging.value.startY)

    // Only start drag after 5px threshold
    if (!dragging.value.isDragging && dx < 5 && dy < 5) return
    dragging.value.isDragging = true

    const gridRect = gridRef.value.getBoundingClientRect()

    // Calculate ghost position (snap to 15-minute increments vertically)
    const rawTop = e.clientY - gridRect.top - dragging.value.offsetY
    const snappedTop = Math.round(rawTop / SNAP_PX) * SNAP_PX
    dragging.value.ghostTop = Math.max(0, Math.min(snappedTop, SLOT_COUNT * ROW_HEIGHT - dragging.value.ghostHeight))

    // Determine which day column we're over (accounting for the time gutter)
    const timeGutterWidth = 64 // 4rem = 64px
    const dayColumnsWidth = gridRect.width - timeGutterWidth
    const dayColumnWidth = dayColumnsWidth / 7
    const relativeX = e.clientX - gridRect.left - timeGutterWidth
    const dayIndex = Math.floor(relativeX / dayColumnWidth)
    const clampedDayIndex = Math.max(0, Math.min(dayIndex, 6))

    dragging.value.currentDayIndex = clampedDayIndex
    dragging.value.ghostLeft = timeGutterWidth + clampedDayIndex * dayColumnWidth + 4
    dragging.value.ghostWidth = dayColumnWidth - 8
}

function handlePointerUp(_e: PointerEvent) {
    window.removeEventListener('pointermove', handlePointerMove)
    window.removeEventListener('pointerup', handlePointerUp)

    if (!dragging.value || !dragging.value.isDragging) {
        dragging.value = null
        return
    }

    const drag = dragging.value
    const event = drag.event

    // Calculate new time from snapped position (15-min increments, 2-hour duration)
    const snapBlocks = Math.round(drag.ghostTop / SNAP_PX)
    const newStartMinutes = DAY_START_HOUR * 60 + snapBlocks * SNAP_MINUTES
    const newEndMinutes = newStartMinutes + SLOT_DURATION_HOURS * 60

    // Clamp within day boundaries
    if (newStartMinutes < DAY_START_HOUR * 60 || newEndMinutes > DAY_END_HOUR * 60) {
        dragging.value = null
        return
    }

    const newDate = formatDate(props.weekDays[drag.currentDayIndex])
    const newStartTime = minutesToTime(newStartMinutes)
    const newEndTime = minutesToTime(newEndMinutes)

    // Only emit if something changed
    if (newDate !== event.date || newStartTime !== event.startTime.substring(0, 5) || newEndTime !== event.endTime.substring(0, 5)) {
        emit('eventMove', event.id, newDate, newStartTime, newEndTime)
    }

    dragging.value = null
}
</script>

<template>
    <div ref="gridRef" class="relative select-none">
        <!-- Header Row: Time gutter + 7 day columns -->
        <div class="grid grid-cols-[4rem_repeat(7,1fr)] border-b border-border">
            <div class="border-r border-border p-2"></div>
            <div
                v-for="(day, i) in weekDays"
                :key="i"
                class="border-r border-border p-2 text-center last:border-r-0"
                :class="isToday(day) ? 'bg-primary/5 dark:bg-primary/10' : ''"
            >
                <div class="text-xs font-medium text-muted-foreground">
                    {{ dayNames[i] }}
                </div>
                <div
                    class="mt-0.5 text-sm font-semibold"
                    :class="isToday(day) ? 'text-primary' : 'text-foreground'"
                >
                    {{ day.getDate() }}
                </div>
            </div>
        </div>

        <!-- Time Grid: rows of 30-min slots -->
        <div class="grid grid-cols-[4rem_repeat(7,1fr)]">
            <!-- Time gutter -->
            <div class="border-r border-border">
                <div
                    v-for="(label, i) in timeLabels"
                    :key="i"
                    class="relative border-b border-border pr-2 text-xs text-muted-foreground"
                    :style="{ height: `${ROW_HEIGHT}px` }"
                >
                    <span
                        v-if="i % 2 === 0"
                        class="absolute right-2 top-0 -translate-y-1/2 bg-background px-1"
                    >
                        {{ label }}
                    </span>
                </div>
            </div>

            <!-- Day columns -->
            <div
                v-for="(day, dayIdx) in weekDays"
                :key="dayIdx"
                class="relative border-r border-border last:border-r-0"
                :class="isToday(day) ? 'bg-primary/5 dark:bg-primary/10' : ''"
            >
                <!-- Grid lines (30-min slots) -->
                <div
                    v-for="slotIdx in SLOT_COUNT"
                    :key="slotIdx"
                    class="cursor-pointer border-b border-border transition-colors hover:bg-muted/50"
                    :class="(slotIdx - 1) % 2 === 0 ? '' : 'border-dashed'"
                    :style="{ height: `${ROW_HEIGHT}px` }"
                    @click="handleSlotClick(day, slotIdx - 1)"
                ></div>

                <!-- Event blocks (absolutely positioned, side-by-side when overlapping) -->
                <CalendarEventBlock
                    v-for="evt in eventsByDate.get(formatDate(day)) || []"
                    :key="evt.id"
                    :event="evt"
                    :day-start-hour="DAY_START_HOUR"
                    :row-height="ROW_HEIGHT"
                    :column-index="overlapLayoutByDate.get(formatDate(day))?.get(evt.id)?.columnIndex ?? 0"
                    :total-columns="overlapLayoutByDate.get(formatDate(day))?.get(evt.id)?.totalColumns ?? 1"
                    :has-clash="(overlapLayoutByDate.get(formatDate(day))?.get(evt.id)?.totalColumns ?? 1) > 1"
                    :class="{ 'pointer-events-none opacity-30': dragging?.isDragging && dragging.event.id === evt.id }"
                    @click="emit('eventClick', $event)"
                    @dragstart="handleEventDragStart"
                />
            </div>
        </div>

        <!-- Drag ghost -->
        <div
            v-if="dragging?.isDragging"
            class="pointer-events-none absolute z-50 rounded-md border-2 border-dashed px-2 py-1 text-xs opacity-80"
            :class="
                dragging.event.isAvailable
                    ? 'border-green-500 bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300'
                    : 'border-red-500 bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300'
            "
            :style="{
                top: `${dragging.ghostTop + ROW_HEIGHT + 33}px`,
                left: `${dragging.ghostLeft}px`,
                width: `${dragging.ghostWidth}px`,
                height: `${dragging.ghostHeight}px`,
            }"
        >
            <div class="font-medium">Moving...</div>
        </div>
    </div>
</template>
