<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
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
import type { CalendarEvent } from './Schedule/CalendarEventBlock.vue'
import { useCalendarNavigation } from '@/composables/useCalendarNavigation'
import type { CalendarItemFormData, CalendarItemResponse } from '@/types/instructor'

interface Props {
    instructorId: number
}

const props = defineProps<Props>()

// ── Navigation ───────────────────────────────────────────
const {
    weekDays,
    weekStartFormatted,
    weekEndFormatted,
    goToNextWeek,
    goToPreviousWeek,
    goToToday,
} = useCalendarNavigation()

// ── State ────────────────────────────────────────────────
const loading = ref(true)
const isCreateSheetOpen = ref(false)
const isEditSheetOpen = ref(false)
const isDeleteDialogOpen = ref(false)
const formLoading = ref(false)
const events = ref<CalendarEvent[]>([])

// Map of backend items by ID for quick lookup
const itemsMap = ref<Map<number, CalendarItemResponse>>(new Map())

// ── Form state ───────────────────────────────────────────
const createForm = ref<CalendarItemFormData>({
    date: '',
    start_time: '',
    end_time: '',
    is_available: true,
})

const editForm = ref<{
    id: number
    date: string
    start_time: string
    end_time: string
    is_available: boolean
}>({
    id: 0,
    date: '',
    start_time: '',
    end_time: '',
    is_available: true,
})

// ── Time slot options (2-hour blocks within 08:00–18:00) ─
const SLOT_DURATION_HOURS = 2
const startTimeOptions = [
    { value: '08:00', label: '08:00' },
    { value: '10:00', label: '10:00' },
    { value: '12:00', label: '12:00' },
    { value: '14:00', label: '14:00' },
    { value: '16:00', label: '16:00' },
]

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

/** Snap a time string to the nearest valid start time option */
function snapToStartOption(time: string): string {
    const minutes = timeToMinutes(time)
    const hour = Math.floor(minutes / 60)
    const snappedHour = hour % 2 === 0 ? hour : hour - 1
    const clamped = Math.max(8, Math.min(snappedHour, 16))
    return `${String(clamped).padStart(2, '0')}:00`
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

// Reload when week changes
watch(weekStartFormatted, () => {
    loadCalendarRange(weekStartFormatted.value, weekEndFormatted.value)
})

// ── Click on empty slot → open create sheet ──────────────
function handleSlotClick(date: string, time: string) {
    const startTime = snapToStartOption(time)

    createForm.value = {
        date,
        start_time: startTime,
        end_time: calcEndTime(startTime),
        is_available: true,
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

    formLoading.value = true
    try {
        const response = await axios.post(
            `/instructors/${props.instructorId}/calendar/items`,
            createForm.value,
        )

        const newItem: CalendarItemResponse = response.data.calendar_item
        itemsMap.value.set(newItem.id, newItem)
        rebuildEvents()

        toast({ title: 'Time slot added successfully!' })
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

    const startTime = snapToStartOption(normaliseTime(item.start_time))
    editForm.value = {
        id: item.id,
        date: item.date,
        start_time: startTime,
        end_time: calcEndTime(startTime),
        is_available: item.is_available,
    }
    isEditSheetOpen.value = true
}

// ── Edit time slot ───────────────────────────────────────
async function handleEditSubmit() {
    formLoading.value = true
    try {
        const response = await axios.put(
            `/instructors/${props.instructorId}/calendar/items/${editForm.value.id}`,
            {
                date: editForm.value.date,
                start_time: editForm.value.start_time,
                end_time: editForm.value.end_time,
                is_available: editForm.value.is_available,
            },
        )

        const updated: CalendarItemResponse = response.data.calendar_item
        itemsMap.value.set(updated.id, updated)
        rebuildEvents()

        toast({ title: 'Time slot updated successfully!' })
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

    // Optimistically update
    const oldItem = { ...item }
    item.date = newDate
    item.start_time = newStartTime
    item.end_time = newEndTime
    rebuildEvents()

    try {
        const response = await axios.put(
            `/instructors/${props.instructorId}/calendar/items/${eventId}`,
            {
                date: newDate,
                start_time: newStartTime,
                end_time: newEndTime,
                is_available: item.is_available,
            },
        )

        const updated: CalendarItemResponse = response.data.calendar_item
        itemsMap.value.set(updated.id, updated)
        rebuildEvents()

        toast({ title: 'Time slot moved successfully!' })
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
    isDeleteDialogOpen.value = true
}

async function handleDelete() {
    formLoading.value = true
    try {
        await axios.delete(
            `/instructors/${props.instructorId}/calendar/items/${editForm.value.id}`,
        )

        itemsMap.value.delete(editForm.value.id)
        rebuildEvents()

        toast({ title: 'Time slot removed successfully!' })
        isDeleteDialogOpen.value = false
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to delete time slot'
        toast({ title: message, variant: 'destructive' })
    } finally {
        formLoading.value = false
    }
}

// ── Week label ───────────────────────────────────────────
function formatWeekLabel(days: Date[]): string {
    if (days.length === 0) return ''
    const first = days[0]
    const last = days[6]
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

    if (first.getMonth() === last.getMonth()) {
        return `${first.getDate()} - ${last.getDate()} ${monthNames[first.getMonth()]} ${first.getFullYear()}`
    }
    return `${first.getDate()} ${monthNames[first.getMonth()]} - ${last.getDate()} ${monthNames[last.getMonth()]} ${first.getFullYear()}`
}

// ── Mount ────────────────────────────────────────────────
onMounted(() => {
    loading.value = true
    loadCalendarRange(weekStartFormatted.value, weekEndFormatted.value)
})
</script>

<template>
    <div class="flex flex-col gap-6">
  
        <!-- Week Navigation + Calendar Grid -->
        <Card class="!pb-6 !pt-0">
            <!-- Navigation Bar -->
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="icon" @click="goToPreviousWeek">
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <Button variant="outline" size="sm" @click="goToToday">
                        Today
                    </Button>
                    <Button variant="outline" size="icon" @click="goToNextWeek">
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>

                <span class="text-sm font-medium text-foreground">
                    {{ formatWeekLabel(weekDays) }}
                </span>
            </div>

            <!-- Calendar Grid -->
            <CardContent class="p-0">
                <div v-if="loading" class="space-y-4 p-6">
                    <Skeleton class="h-8 w-full" />
                    <Skeleton class="h-[500px] w-full" />
                </div>
                <div v-else class="overflow-x-auto">
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

                    <Button
                        type="submit"
                        :disabled="formLoading"
                        class="w-full min-w-[120px]"
                    >
                        <Loader2 v-if="formLoading" class="mr-2 h-4 w-4 animate-spin" />
                        <Plus v-else class="mr-2 h-4 w-4" />
                        Add Time Slot
                    </Button>
                </form>
            </SheetContent>
        </Sheet>

        <!-- Edit Time Slot Sheet -->
        <Sheet v-model:open="isEditSheetOpen">
            <SheetContent side="right">
                <SheetHeader>
                    <SheetTitle class="flex items-center gap-2">
                        <Clock class="h-5 w-5" />
                        Edit Time Slot
                    </SheetTitle>
                </SheetHeader>

                <form @submit.prevent="handleEditSubmit" class="mt-6 space-y-6 px-6 py-4">
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

                <div class="py-4">
                    <p class="text-sm text-muted-foreground">
                        <strong>Date:</strong> {{ editForm.date }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        <strong>Time:</strong> {{ editForm.start_time }} - {{ editForm.end_time }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        <strong>Status:</strong> {{ editForm.is_available ? 'Available' : 'Unavailable' }}
                    </p>
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
