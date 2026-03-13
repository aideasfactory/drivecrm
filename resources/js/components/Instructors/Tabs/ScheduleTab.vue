<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue'
import axios from 'axios'
import {
    Plus,
    Trash2,
    Loader2,
    Calendar as CalendarIcon,
    Clock,
    Check,
    X,
    ChevronLeft,
    ChevronRight,
    Repeat,
    CalendarDays,
    CalendarRange,
    Car,
} from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { toast } from '@/components/ui/toast'
import WeeklyCalendarGrid from './Schedule/WeeklyCalendarGrid.vue'
import MonthlyCalendarGrid from './Schedule/MonthlyCalendarGrid.vue'
import type { CalendarEvent } from './Schedule/CalendarEventBlock.vue'
import { useCalendarNavigation } from '@/composables/useCalendarNavigation'
import type { CalendarItemFormData, CalendarItemResponse, RecurrencePattern } from '@/types/instructor'

interface Props {
    instructorId: number
}

const props = defineProps<Props>()

// ── Navigation ───────────────────────────────────────────
const {
    currentView,
    weekDays,
    weekStartFormatted,
    weekEndFormatted,
    goToNextWeek,
    goToPreviousWeek,
    goToToday,
    monthDays,
    currentMonth,
    monthStartFormatted,
    monthEndFormatted,
    goToNextMonth,
    goToPreviousMonth,
    goToCurrentMonth,
    rangeStartFormatted,
    rangeEndFormatted,
} = useCalendarNavigation()

// ── State ────────────────────────────────────────────────
const loading = ref(true)
const isCreateSheetOpen = ref(false)
const isEditSheetOpen = ref(false)
const isDeleteDialogOpen = ref(false)
const formLoading = ref(false)
const events = ref<CalendarEvent[]>([])
const deleteScope = ref<'single' | 'future'>('single')

// Map of backend items by ID for quick lookup
const itemsMap = ref<Map<number, CalendarItemResponse>>(new Map())

// ── Recurrence options ──────────────────────────────────
const recurrenceOptions: { value: RecurrencePattern; label: string }[] = [
    { value: 'none', label: 'Does not repeat' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'biweekly', label: 'Every 2 weeks' },
    { value: 'monthly', label: 'Monthly' },
]

// ── Travel time options ─────────────────────────────────
const travelTimeOptions = [
    { value: 0, label: 'No travel time' },
    { value: 15, label: '15 minutes' },
    { value: 30, label: '30 minutes' },
    { value: 45, label: '45 minutes' },
]

// ── Form state ───────────────────────────────────────────
const createForm = ref<CalendarItemFormData>({
    date: '',
    start_time: '',
    end_time: '',
    is_available: true,
    notes: '',
    unavailability_reason: '',
    recurrence_pattern: 'none',
    recurrence_end_date: '',
    travel_time_minutes: 30,
})

const editForm = ref<{
    id: number
    date: string
    start_time: string
    end_time: string
    is_available: boolean
    notes: string
    unavailability_reason: string
    recurrence_pattern: RecurrencePattern
    recurrence_group_id: string | null
    item_type: string
}>({
    id: 0,
    date: '',
    start_time: '',
    end_time: '',
    is_available: true,
    notes: '',
    unavailability_reason: '',
    recurrence_pattern: 'none',
    recurrence_group_id: null,
    item_type: 'slot',
})

/** Whether the item being edited is part of a recurring series */
const editItemIsRecurring = computed(() => {
    return editForm.value.recurrence_pattern !== 'none' && editForm.value.recurrence_group_id !== null
})

/** Whether the item being edited is a travel-time block */
const editItemIsTravel = computed(() => {
    return editForm.value.item_type === 'travel'
})

// ── Time slot options (30-min increments, 08:00–16:00) ───
const SLOT_DURATION_HOURS = 2
const startTimeOptions = computed(() => {
    const options: { value: string; label: string }[] = []
    for (let h = 8; h <= 16; h++) {
        for (let m = 0; m < 60; m += 30) {
            // Don't go past 16:00 (a 2-hour slot ending at 18:00)
            if (h === 16 && m > 0) break
            const time = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
            options.push({ value: time, label: time })
        }
    }
    return options
})

// ── Helpers ──────────────────────────────────────────────
/** Normalise "HH:MM:SS" or "HH:MM" → "HH:MM" */
const normaliseTime = (t: string): string => t.substring(0, 5)

const timeToMinutes = (time: string): number => {
    const [h, m] = time.split(':').map(Number)
    return h * 60 + m
}

const minutesToTime = (minutes: number): string => {
    const h = Math.floor(minutes / 60) % 24
    const m = minutes % 60
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

/** Calculate end time from start time (adds SLOT_DURATION_HOURS) */
function calcEndTime(startTime: string): string {
    const minutes = timeToMinutes(startTime)
    return minutesToTime(minutes + SLOT_DURATION_HOURS * 60)
}

/** Snap a time string to the nearest valid 30-minute start time */
function snapToStartOption(time: string): string {
    const minutes = timeToMinutes(time)
    // Round down to nearest 30-minute increment
    const snappedMinutes = Math.floor(minutes / 30) * 30
    // Clamp between 08:00 (480) and 16:00 (960)
    const clamped = Math.max(480, Math.min(snappedMinutes, 960))
    return minutesToTime(clamped)
}

// Auto-calculate end time when start time changes
watch(() => createForm.value.start_time, (newStart) => {
    if (newStart) {
        createForm.value.end_time = calcEndTime(newStart)
    }
})

watch(() => editForm.value.start_time, (newStart) => {
    if (newStart) {
        editForm.value.end_time = calcEndTime(newStart)
    }
})

/** Convert backend item to grid event */
function toCalendarEvent(item: CalendarItemResponse): CalendarEvent {
    return {
        id: item.id,
        date: item.date,
        startTime: item.start_time,
        endTime: item.end_time,
        isAvailable: item.is_available,
        status: item.status,
        itemType: item.item_type ?? 'slot',
        travelTimeMinutes: item.travel_time_minutes ?? null,
        parentItemId: item.parent_item_id ?? null,
        studentName: item.student_name,
        notes: item.notes,
        unavailabilityReason: item.unavailability_reason,
        recurrencePattern: item.recurrence_pattern ?? 'none',
        recurrenceGroupId: item.recurrence_group_id ?? null,
    }
}

/** Rebuild events array from itemsMap */
function rebuildEvents() {
    events.value = Array.from(itemsMap.value.values()).map(toCalendarEvent)
}

// ── Data loading ─────────────────────────────────────────
async function loadCalendarRange(startDate: string, endDate: string) {
    try {
        const response = await axios.get(
            `/instructors/${props.instructorId}/calendar`,
            { params: { start_date: startDate, end_date: endDate } },
        )

        const calendars = response.data.calendar || []
        const newItemsMap = new Map<number, CalendarItemResponse>()

        for (const cal of calendars) {
            for (const item of cal.items) {
                const calItem: CalendarItemResponse = {
                    id: item.id,
                    calendar_id: item.calendar_id ?? cal.id,
                    date: item.date ?? cal.date,
                    start_time: item.start_time,
                    end_time: item.end_time,
                    is_available: item.is_available,
                    status: item.status,
                    item_type: item.item_type ?? 'slot',
                    travel_time_minutes: item.travel_time_minutes ?? null,
                    parent_item_id: item.parent_item_id ?? null,
                    notes: item.notes ?? null,
                    unavailability_reason: item.unavailability_reason ?? null,
                    student_name: item.student_name ?? null,
                    recurrence_pattern: item.recurrence_pattern ?? 'none',
                    recurrence_end_date: item.recurrence_end_date ?? null,
                    recurrence_group_id: item.recurrence_group_id ?? null,
                }
                newItemsMap.set(item.id, calItem)
            }
        }

        itemsMap.value = newItemsMap
        rebuildEvents()
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to load calendar'
        toast({ title: message, variant: 'destructive' })
    } finally {
        loading.value = false
    }
}

// Reload when the active date range changes (covers both week and month nav)
watch(rangeStartFormatted, () => {
    loading.value = true
    loadCalendarRange(rangeStartFormatted.value, rangeEndFormatted.value)
})

// Reload when switching views
watch(currentView, () => {
    loading.value = true
    loadCalendarRange(rangeStartFormatted.value, rangeEndFormatted.value)
})

// ── View navigation helpers ─────────────────────────────
function goToPrevious() {
    if (currentView.value === 'week') {
        goToPreviousWeek()
    } else {
        goToPreviousMonth()
    }
}

function goToNext() {
    if (currentView.value === 'week') {
        goToNextWeek()
    } else {
        goToNextMonth()
    }
}

function goToNow() {
    if (currentView.value === 'week') {
        goToToday()
    } else {
        goToCurrentMonth()
    }
}

// ── Click on empty slot → open create sheet ──────────────
function handleSlotClick(date: string, time: string) {
    const startTime = snapToStartOption(time)

    createForm.value = {
        date,
        start_time: startTime,
        end_time: calcEndTime(startTime),
        is_available: true,
        notes: '',
        unavailability_reason: '',
        recurrence_pattern: 'none',
        recurrence_end_date: '',
        travel_time_minutes: 30,
    }
    isCreateSheetOpen.value = true
}

// ── Click on day in monthly view → open create sheet ─────
function handleDayClick(date: string) {
    createForm.value = {
        date,
        start_time: '08:00',
        end_time: '10:00',
        is_available: true,
        notes: '',
        unavailability_reason: '',
        travel_time_minutes: 30,
    }
    isCreateSheetOpen.value = true
}

// ── Create time slot ─────────────────────────────────────
async function handleCreateSubmit() {
    if (!createForm.value.date || !createForm.value.start_time || !createForm.value.end_time) {
        toast({ title: 'Please fill in all fields', variant: 'destructive' })
        return
    }

    if (createForm.value.end_time <= createForm.value.start_time) {
        toast({ title: 'End time must be after start time', variant: 'destructive' })
        return
    }

    // Validate unavailability reason when marking as unavailable
    if (!createForm.value.is_available && !createForm.value.unavailability_reason?.trim()) {
        toast({ title: 'Please provide a reason for unavailability', variant: 'destructive' })
        return
    }

    formLoading.value = true
    try {
        const travelMinutes = createForm.value.is_available ? (createForm.value.travel_time_minutes || 0) : 0

        const response = await axios.post(
            `/instructors/${props.instructorId}/calendar/items`,
            {
                date: createForm.value.date,
                start_time: createForm.value.start_time,
                end_time: createForm.value.end_time,
                is_available: createForm.value.is_available,
                notes: createForm.value.notes || null,
                unavailability_reason: createForm.value.is_available ? null : createForm.value.unavailability_reason,
                recurrence_pattern: createForm.value.recurrence_pattern || 'none',
                recurrence_end_date: createForm.value.recurrence_end_date || null,
                travel_time_minutes: travelMinutes > 0 ? travelMinutes : null,
            },
        )

        const recurringCount = response.data.recurring_count
        const hasTravelItem = response.data.has_travel_item

        if (recurringCount && recurringCount > 1) {
            toast({ title: `${recurringCount} recurring time slots created!` })
            await loadCalendarRange(rangeStartFormatted.value, rangeEndFormatted.value)
        } else if (hasTravelItem) {
            // Reload to pick up both the slot and travel item
            toast({ title: 'Time slot with travel time added!' })
            await loadCalendarRange(rangeStartFormatted.value, rangeEndFormatted.value)
        } else {
            const newItem: CalendarItemResponse = response.data.calendar_item
            itemsMap.value.set(newItem.id, newItem)
            rebuildEvents()
            toast({ title: 'Time slot added successfully!' })
        }

        isCreateSheetOpen.value = false
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to add time slot'
        toast({ title: message, variant: 'destructive' })
    } finally {
        formLoading.value = false
    }
}

// ── Click on event → open edit sheet ─────────────────────
function handleEventClick(event: CalendarEvent) {
    const item = itemsMap.value.get(event.id)
    if (!item) return

    const startTime = item.item_type === 'travel'
        ? normaliseTime(item.start_time)
        : snapToStartOption(normaliseTime(item.start_time))

    editForm.value = {
        id: item.id,
        date: item.date,
        start_time: startTime,
        end_time: item.item_type === 'travel' ? normaliseTime(item.end_time) : calcEndTime(startTime),
        is_available: item.is_available,
        notes: item.notes ?? '',
        unavailability_reason: item.unavailability_reason ?? '',
        recurrence_pattern: item.recurrence_pattern ?? 'none',
        recurrence_group_id: item.recurrence_group_id ?? null,
        item_type: item.item_type ?? 'slot',
    }
    isEditSheetOpen.value = true
}

// ── Edit time slot ───────────────────────────────────────
async function handleEditSubmit() {
    // Validate unavailability reason when marking as unavailable
    if (!editForm.value.is_available && !editItemIsTravel.value && !editForm.value.unavailability_reason?.trim()) {
        toast({ title: 'Please provide a reason for unavailability', variant: 'destructive' })
        return
    }

    formLoading.value = true
    try {
        const response = await axios.put(
            `/instructors/${props.instructorId}/calendar/items/${editForm.value.id}`,
            {
                date: editForm.value.date,
                start_time: editForm.value.start_time,
                end_time: editForm.value.end_time,
                is_available: editForm.value.is_available,
                notes: editForm.value.notes || null,
                unavailability_reason: editForm.value.is_available ? null : editForm.value.unavailability_reason,
            },
        )

        // Reload full calendar to pick up any travel item changes
        toast({ title: 'Time slot updated successfully!' })
        await loadCalendarRange(rangeStartFormatted.value, rangeEndFormatted.value)
        isEditSheetOpen.value = false
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to update time slot'
        toast({ title: message, variant: 'destructive' })
    } finally {
        formLoading.value = false
    }
}

// ── Drag-and-drop move ───────────────────────────────────
async function handleEventMove(eventId: number, newDate: string, newStartTime: string, newEndTime: string) {
    const item = itemsMap.value.get(eventId)
    if (!item) return

    // Don't allow dragging travel items
    if (item.item_type === 'travel') return

    // Optimistically update
    const oldItem = { ...item }
    item.date = newDate
    item.start_time = newStartTime
    item.end_time = newEndTime
    rebuildEvents()

    try {
        await axios.put(
            `/instructors/${props.instructorId}/calendar/items/${eventId}`,
            {
                date: newDate,
                start_time: newStartTime,
                end_time: newEndTime,
                is_available: item.is_available,
            },
        )

        // Reload to get updated travel items too
        toast({ title: 'Time slot moved successfully!' })
        await loadCalendarRange(rangeStartFormatted.value, rangeEndFormatted.value)
    } catch (error: any) {
        // Revert on error
        itemsMap.value.set(eventId, oldItem as CalendarItemResponse)
        rebuildEvents()

        const message = error.response?.data?.message || 'Failed to move time slot'
        toast({ title: message, variant: 'destructive' })
    }
}

// ── Delete time slot ─────────────────────────────────────
function openDeleteDialog() {
    isEditSheetOpen.value = false
    deleteScope.value = 'single'
    isDeleteDialogOpen.value = true
}

async function handleDelete() {
    formLoading.value = true
    try {
        const scopeParam = deleteScope.value === 'future' && editItemIsRecurring.value ? 'future' : 'single'

        await axios.delete(
            `/instructors/${props.instructorId}/calendar/items/${editForm.value.id}`,
            { params: { scope: scopeParam } },
        )

        // Always reload to pick up travel item deletions
        toast({ title: scopeParam === 'future' ? 'Recurring time slots removed successfully!' : 'Time slot removed successfully!' })
        await loadCalendarRange(rangeStartFormatted.value, rangeEndFormatted.value)

        isDeleteDialogOpen.value = false
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to delete time slot'
        toast({ title: message, variant: 'destructive' })
    } finally {
        formLoading.value = false
    }
}

// ── Navigation label ────────────────────────────────────
const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
const shortMonthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

function formatWeekLabel(days: Date[]): string {
    if (days.length === 0) return ''
    const first = days[0]
    const last = days[6]

    if (first.getMonth() === last.getMonth()) {
        return `${first.getDate()} - ${last.getDate()} ${shortMonthNames[first.getMonth()]} ${first.getFullYear()}`
    }
    return `${first.getDate()} ${shortMonthNames[first.getMonth()]} - ${last.getDate()} ${shortMonthNames[last.getMonth()]} ${first.getFullYear()}`
}

function formatMonthLabel(date: Date): string {
    return `${monthNames[date.getMonth()]} ${date.getFullYear()}`
}

// ── Mount ────────────────────────────────────────────────
onMounted(() => {
    loading.value = true
    loadCalendarRange(rangeStartFormatted.value, rangeEndFormatted.value)
})
</script>

<template>
    <div class="flex flex-col gap-6">

        <!-- Navigation + Calendar Grid -->
        <Card class="!pb-6 !pt-0">
            <!-- Navigation Bar -->
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="icon" @click="goToPrevious">
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <Button variant="outline" size="sm" @click="goToNow">
                        Today
                    </Button>
                    <Button variant="outline" size="icon" @click="goToNext">
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>

                <span class="text-sm font-medium text-foreground">
                    {{ currentView === 'week' ? formatWeekLabel(weekDays) : formatMonthLabel(currentMonth) }}
                </span>

                <!-- View Toggle -->
                <div class="flex items-center gap-1 rounded-md border border-border p-0.5">
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="currentView === 'week' ? 'bg-muted' : ''"
                        @click="currentView = 'week'"
                    >
                        <CalendarDays class="mr-1.5 h-4 w-4" />
                        Week
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="currentView === 'month' ? 'bg-muted' : ''"
                        @click="currentView = 'month'"
                    >
                        <CalendarRange class="mr-1.5 h-4 w-4" />
                        Month
                    </Button>
                </div>
            </div>

            <!-- Calendar Grid -->
            <CardContent class="p-0">
                <div v-if="loading" class="space-y-4 p-6">
                    <Skeleton class="h-8 w-full" />
                    <Skeleton class="h-[500px] w-full" />
                </div>

                <!-- Weekly View -->
                <div v-else-if="currentView === 'week'" class="overflow-x-auto">
                    <div class="min-w-[700px]">
                        <WeeklyCalendarGrid
                            :week-days="weekDays"
                            :events="events"
                            @click-slot="handleSlotClick"
                            @event-click="handleEventClick"
                            @event-move="handleEventMove"
                        />
                    </div>
                </div>

                <!-- Monthly View -->
                <div v-else>
                    <MonthlyCalendarGrid
                        :month-days="monthDays"
                        :current-month="currentMonth"
                        :events="events"
                        @click-day="handleDayClick"
                        @event-click="handleEventClick"
                    />
                </div>
            </CardContent>
        </Card>

        <!-- Create Time Slot Sheet -->
        <Sheet v-model:open="isCreateSheetOpen">
            <SheetContent side="right">
                <SheetHeader>
                    <SheetTitle class="flex items-center gap-2">
                        <Clock class="h-5 w-5" />
                        Add Time Slot
                    </SheetTitle>
                </SheetHeader>

                <form @submit.prevent="handleCreateSubmit" class="mt-6 space-y-6 px-6 py-4">
                    <div class="space-y-2">
                        <Label for="create-date">Date</Label>
                        <Input
                            id="create-date"
                            v-model="createForm.date"
                            type="date"
                            required
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="create-start">Start Time</Label>
                        <select
                            id="create-start"
                            v-model="createForm.start_time"
                            required
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="" disabled>Select start time</option>
                            <option
                                v-for="opt in startTimeOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <Label>End Time</Label>
                        <div class="flex h-10 w-full items-center rounded-md border border-input bg-muted/50 px-3 text-sm text-muted-foreground">
                            {{ createForm.end_time || '—' }}
                        </div>
                    </div>

                    <!-- Travel Time (only shown when available) -->
                    <div v-if="createForm.is_available" class="space-y-2">
                        <Label for="create-travel">
                            <span class="flex items-center gap-1.5">
                                <Car class="h-4 w-4" />
                                Travel Time After Lesson
                            </span>
                        </Label>
                        <select
                            id="create-travel"
                            v-model.number="createForm.travel_time_minutes"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option
                                v-for="opt in travelTimeOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <p v-if="createForm.travel_time_minutes && createForm.travel_time_minutes > 0 && createForm.end_time" class="text-xs text-muted-foreground">
                            Travel block: {{ createForm.end_time }} - {{ minutesToTime(timeToMinutes(createForm.end_time) + (createForm.travel_time_minutes || 0)) }}
                        </p>
                    </div>

                    <!-- Recurrence Pattern -->
                    <div class="space-y-2">
                        <Label for="create-recurrence">
                            <span class="flex items-center gap-1.5">
                                <Repeat class="h-4 w-4" />
                                Repeat
                            </span>
                        </Label>
                        <select
                            id="create-recurrence"
                            v-model="createForm.recurrence_pattern"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option
                                v-for="opt in recurrenceOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Recurrence End Date (shown only when repeating) -->
                    <div v-if="createForm.recurrence_pattern && createForm.recurrence_pattern !== 'none'" class="space-y-2">
                        <Label for="create-recurrence-end">Repeat Until</Label>
                        <Input
                            id="create-recurrence-end"
                            v-model="createForm.recurrence_end_date"
                            type="date"
                            placeholder="Leave empty for 6 months"
                        />
                        <p class="text-xs text-muted-foreground">
                            Leave empty to repeat for 6 months
                        </p>
                    </div>

                    <!-- Status Toggle -->
                    <div class="space-y-2">
                        <Label>Status</Label>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                :variant="createForm.is_available ? 'default' : 'outline'"
                                class="flex-1"
                                @click="createForm.is_available = true"
                            >
                                <Check class="mr-2 h-4 w-4" />
                                Available
                            </Button>
                            <Button
                                type="button"
                                :variant="!createForm.is_available ? 'destructive' : 'outline'"
                                class="flex-1"
                                @click="createForm.is_available = false"
                            >
                                <X class="mr-2 h-4 w-4" />
                                Unavailable
                            </Button>
                        </div>
                    </div>

                    <!-- Notes Field -->
                    <div class="space-y-2">
                        <Label for="create-notes">Notes</Label>
                        <textarea
                            id="create-notes"
                            v-model="createForm.notes"
                            placeholder="Add any notes about this time slot (optional)"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            maxlength="1000"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ createForm.notes?.length || 0 }}/1000 characters
                        </p>
                    </div>

                    <!-- Unavailability Reason Field (shown only when unavailable) -->
                    <div v-if="!createForm.is_available" class="space-y-2">
                        <Label for="create-unavailability-reason">
                            Unavailability Reason <span class="text-destructive">*</span>
                        </Label>
                        <textarea
                            id="create-unavailability-reason"
                            v-model="createForm.unavailability_reason"
                            placeholder="Why is this slot unavailable?"
                            required
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            maxlength="500"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ createForm.unavailability_reason?.length || 0 }}/500 characters
                        </p>
                    </div>

                    <Button
                        type="submit"
                        :disabled="formLoading"
                        class="w-full min-w-[120px]"
                    >
                        <Loader2 v-if="formLoading" class="mr-2 h-4 w-4 animate-spin" />
                        <Plus v-else class="mr-2 h-4 w-4" />
                        {{ createForm.recurrence_pattern && createForm.recurrence_pattern !== 'none' ? 'Add Recurring Slots' : 'Add Time Slot' }}
                    </Button>
                </form>
            </SheetContent>
        </Sheet>

        <!-- Edit Time Slot Sheet -->
        <Sheet v-model:open="isEditSheetOpen">
            <SheetContent side="right">
                <SheetHeader>
                    <SheetTitle class="flex items-center gap-2">
                        <Car v-if="editItemIsTravel" class="h-5 w-5" />
                        <Clock v-else class="h-5 w-5" />
                        {{ editItemIsTravel ? 'Travel Time' : 'Edit Time Slot' }}
                        <span v-if="editItemIsRecurring" class="ml-auto flex items-center gap-1 text-xs font-normal text-muted-foreground">
                            <Repeat class="h-3.5 w-3.5" />
                            Recurring
                        </span>
                    </SheetTitle>
                </SheetHeader>

                <!-- Travel item view (read-only info) -->
                <div v-if="editItemIsTravel" class="mt-6 space-y-4 px-6 py-4">
                    <div class="rounded-md border border-purple-200 bg-purple-50 p-4 dark:border-purple-800 dark:bg-purple-900/20">
                        <p class="text-sm font-medium text-purple-800 dark:text-purple-300">
                            This is a travel-time block created automatically after a lesson slot.
                        </p>
                        <p class="mt-1 text-xs text-purple-600 dark:text-purple-400">
                            Travel time blocks cannot be booked and are managed through the parent lesson slot.
                        </p>
                    </div>
                    <div class="space-y-2">
                        <p class="text-sm text-muted-foreground">
                            <strong>Date:</strong> {{ editForm.date }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            <strong>Time:</strong> {{ editForm.start_time }} - {{ editForm.end_time }}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        class="w-full"
                        @click="isEditSheetOpen = false"
                    >
                        Close
                    </Button>
                </div>

                <!-- Regular slot edit form -->
                <form v-else @submit.prevent="handleEditSubmit" class="mt-6 space-y-6 px-6 py-4">
                    <div class="space-y-2">
                        <Label for="edit-date">Date</Label>
                        <Input
                            id="edit-date"
                            v-model="editForm.date"
                            type="date"
                            required
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-start">Start Time</Label>
                        <select
                            id="edit-start"
                            v-model="editForm.start_time"
                            required
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="" disabled>Select start time</option>
                            <option
                                v-for="opt in startTimeOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <Label>End Time</Label>
                        <div class="flex h-10 w-full items-center rounded-md border border-input bg-muted/50 px-3 text-sm text-muted-foreground">
                            {{ editForm.end_time || '—' }}
                        </div>
                    </div>

                    <!-- Status Toggle -->
                    <div class="space-y-2">
                        <Label>Status</Label>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                :variant="editForm.is_available ? 'default' : 'outline'"
                                class="flex-1"
                                @click="editForm.is_available = true"
                            >
                                <Check class="mr-2 h-4 w-4" />
                                Available
                            </Button>
                            <Button
                                type="button"
                                :variant="!editForm.is_available ? 'destructive' : 'outline'"
                                class="flex-1"
                                @click="editForm.is_available = false"
                            >
                                <X class="mr-2 h-4 w-4" />
                                Unavailable
                            </Button>
                        </div>
                    </div>

                    <!-- Notes Field -->
                    <div class="space-y-2">
                        <Label for="edit-notes">Notes</Label>
                        <textarea
                            id="edit-notes"
                            v-model="editForm.notes"
                            placeholder="Add any notes about this time slot (optional)"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            maxlength="1000"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ editForm.notes?.length || 0 }}/1000 characters
                        </p>
                    </div>

                    <!-- Unavailability Reason Field (shown only when unavailable) -->
                    <div v-if="!editForm.is_available" class="space-y-2">
                        <Label for="edit-unavailability-reason">
                            Unavailability Reason <span class="text-destructive">*</span>
                        </Label>
                        <textarea
                            id="edit-unavailability-reason"
                            v-model="editForm.unavailability_reason"
                            placeholder="Why is this slot unavailable?"
                            required
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            maxlength="500"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ editForm.unavailability_reason?.length || 0 }}/500 characters
                        </p>
                    </div>

                    <!-- Info about editing recurring items -->
                    <p v-if="editItemIsRecurring" class="text-xs text-muted-foreground">
                        Changes apply to this occurrence only. To remove the entire series, use Delete.
                    </p>

                    <div class="flex gap-2">
                        <Button
                            type="submit"
                            :disabled="formLoading"
                            class="min-w-[120px] flex-1"
                        >
                            <Loader2 v-if="formLoading" class="mr-2 h-4 w-4 animate-spin" />
                            <Check v-else class="mr-2 h-4 w-4" />
                            Save Changes
                        </Button>

                        <Button
                            type="button"
                            variant="destructive"
                            :disabled="formLoading"
                            class="min-w-[100px]"
                            @click="openDeleteDialog"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Delete
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove Time Slot</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to remove this time slot? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <div class="py-4 space-y-2">
                    <p class="text-sm text-muted-foreground">
                        <strong>Date:</strong> {{ editForm.date }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        <strong>Time:</strong> {{ editForm.start_time }} - {{ editForm.end_time }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        <strong>Status:</strong> {{ editForm.is_available ? 'Available' : 'Unavailable' }}
                    </p>
                    <p v-if="editForm.notes" class="text-sm text-muted-foreground">
                        <strong>Notes:</strong> {{ editForm.notes }}
                    </p>
                    <p v-if="!editForm.is_available && editForm.unavailability_reason" class="text-sm text-muted-foreground">
                        <strong>Reason:</strong> {{ editForm.unavailability_reason }}
                    </p>
                </div>

                <!-- Recurring delete scope selector -->
                <div v-if="editItemIsRecurring" class="space-y-3 border-t border-border pt-4">
                    <Label class="text-sm font-medium">Delete scope</Label>
                    <div class="space-y-2">
                        <label class="flex cursor-pointer items-center gap-3 rounded-md border border-input px-3 py-2.5 transition-colors hover:bg-muted/50" :class="{ 'border-primary bg-primary/5': deleteScope === 'single' }">
                            <input type="radio" v-model="deleteScope" value="single" class="accent-primary" />
                            <div>
                                <div class="text-sm font-medium">This event only</div>
                                <div class="text-xs text-muted-foreground">Remove just this occurrence</div>
                            </div>
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-md border border-input px-3 py-2.5 transition-colors hover:bg-muted/50" :class="{ 'border-primary bg-primary/5': deleteScope === 'future' }">
                            <input type="radio" v-model="deleteScope" value="future" class="accent-primary" />
                            <div>
                                <div class="text-sm font-medium">This and all future events</div>
                                <div class="text-xs text-muted-foreground">Remove this and all later occurrences in the series</div>
                            </div>
                        </label>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="isDeleteDialogOpen = false"
                        :disabled="formLoading"
                        class="min-w-[80px]"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="handleDelete"
                        :disabled="formLoading"
                        class="min-w-[100px]"
                    >
                        <Loader2 v-if="formLoading" class="mr-2 h-4 w-4 animate-spin" />
                        <Trash2 v-else class="mr-2 h-4 w-4" />
                        {{ formLoading ? 'Removing...' : 'Remove' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
