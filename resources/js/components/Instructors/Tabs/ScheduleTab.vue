<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { ScheduleXCalendar } from '@schedule-x/vue'
import {
    createCalendar,
    createViewWeek,
    type CalendarEvent,
} from '@schedule-x/calendar'
import '@schedule-x/theme-default/dist/index.css'
import { Temporal } from 'temporal-polyfill'
import axios from 'axios'
import { Plus, Trash2, Loader2, Calendar as CalendarIcon, Clock } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
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
import { toast } from 'vue-sonner'
import type { Calendar as CalendarType, CalendarItem, CalendarItemFormData } from '@/types/instructor'

interface Props {
    instructorId: number
}

const props = defineProps<Props>()

// State
const loading = ref(true)
const calendars = ref<CalendarType[]>([])
const isAddSheetOpen = ref(false)
const isDeleteDialogOpen = ref(false)
const itemToDelete = ref<CalendarItem | null>(null)
const formLoading = ref(false)

// Form data
const formData = ref<CalendarItemFormData>({
    date: '',
    start_time: '',
    end_time: '',
})

// Schedule X calendar instance
const calendar = createCalendar({
    views: [createViewWeek()],
    defaultView: 'week',
    events: [],
    callbacks: {
        onEventClick(calendarEvent) {
            // When event is clicked, show delete confirmation
            const item = calendars.value
                .flatMap((cal) => cal.calendar_items)
                .find((item) => item.id === Number(calendarEvent.id))

            if (item) {
                itemToDelete.value = item
                isDeleteDialogOpen.value = true
            }
        },
    },
})

// Computed
const calendarEvents = computed((): CalendarEvent[] => {
    return calendars.value.flatMap((cal) =>
        cal.calendar_items.map((item) => ({
            id: String(item.id),
            title: `${item.start_time.substring(0, 5)} - ${item.end_time.substring(0, 5)}`,
            start: `${cal.date} ${item.start_time}`,
            end: `${cal.date} ${item.end_time}`,
        }))
    )
})

const hasCalendarItems = computed(() => calendars.value.some((cal) => cal.calendar_items.length > 0))

// Load calendar data
const loadCalendar = async () => {
    loading.value = true
    try {
        const response = await axios.get(`/instructors/${props.instructorId}/calendar`)
        calendars.value = response.data.calendars || []

        // Update calendar events
        calendar.events.set(calendarEvents.value)
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to load calendar'
        toast.error(message)
    } finally {
        loading.value = false
    }
}

// Add time slot
const handleAddSubmit = async () => {
    // Client-side validation
    if (!formData.value.date || !formData.value.start_time || !formData.value.end_time) {
        toast.error('Please fill in all fields')
        return
    }

    if (formData.value.end_time <= formData.value.start_time) {
        toast.error('End time must be after start time')
        return
    }

    formLoading.value = true
    try {
        const response = await axios.post(
            `/instructors/${props.instructorId}/calendar/items`,
            formData.value
        )

        // Add new calendar or update existing
        const newItem = response.data.calendar_item
        const calendarDate = response.data.calendar

        const existingCalendar = calendars.value.find((cal) => cal.date === calendarDate.date)

        if (existingCalendar) {
            existingCalendar.calendar_items.push(newItem)
        } else {
            calendars.value.push({
                ...calendarDate,
                calendar_items: [newItem],
            })
        }

        // Update calendar events
        calendar.events.set(calendarEvents.value)

        toast.success('Time slot added successfully!')

        // Reset form and close sheet
        formData.value = { date: '', start_time: '', end_time: '' }
        isAddSheetOpen.value = false
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to add time slot'
        toast.error(message)
    } finally {
        formLoading.value = false
    }
}

// Delete time slot
const handleDelete = async () => {
    if (!itemToDelete.value) return

    formLoading.value = true
    try {
        await axios.delete(
            `/instructors/${props.instructorId}/calendar/items/${itemToDelete.value.id}`
        )

        // Remove from local state
        calendars.value = calendars.value
            .map((cal) => ({
                ...cal,
                calendar_items: cal.calendar_items.filter(
                    (item) => item.id !== itemToDelete.value!.id
                ),
            }))
            .filter((cal) => cal.calendar_items.length > 0)

        // Update calendar events
        calendar.events.set(calendarEvents.value)

        toast.success('Time slot removed successfully!')

        isDeleteDialogOpen.value = false
        itemToDelete.value = null
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to delete time slot'
        toast.error(message)
    } finally {
        formLoading.value = false
    }
}

// Cancel delete
const handleCancelDelete = () => {
    isDeleteDialogOpen.value = false
    itemToDelete.value = null
}

// Load data on mount
onMounted(() => {
    loadCalendar()
})

// Set today as default date when sheet opens
const handleSheetOpenChange = (open: boolean) => {
    isAddSheetOpen.value = open
    if (open && !formData.value.date) {
        // Set default date to today
        const today = new Date().toISOString().split('T')[0]
        formData.value.date = today
    }
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Calendar Header Card -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <CalendarIcon class="h-5 w-5" />
                        <CardTitle>Weekly Schedule</CardTitle>
                    </div>

                    <!-- Add Time Slot Button -->
                    <Sheet :open="isAddSheetOpen" @update:open="handleSheetOpenChange">
                        <SheetTrigger as-child>
                            <Button class="min-w-[140px]">
                                <Plus class="h-4 w-4 mr-2" />
                                Add Time Slot
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right">
                            <SheetHeader>
                                <SheetTitle class="flex items-center gap-2">
                                    <Clock class="h-5 w-5" />
                                    Add Time Slot
                                </SheetTitle>
                            </SheetHeader>

                            <form @submit.prevent="handleAddSubmit" class="mt-6 space-y-6 px-6 py-4">
                                <!-- Date -->
                                <div class="space-y-2">
                                    <Label for="date">Date</Label>
                                    <Input
                                        id="date"
                                        v-model="formData.date"
                                        type="date"
                                        :min="new Date().toISOString().split('T')[0]"
                                        required
                                    />
                                </div>

                                <!-- Start Time -->
                                <div class="space-y-2">
                                    <Label for="start_time">Start Time</Label>
                                    <Input
                                        id="start_time"
                                        v-model="formData.start_time"
                                        type="time"
                                        required
                                    />
                                </div>

                                <!-- End Time -->
                                <div class="space-y-2">
                                    <Label for="end_time">End Time</Label>
                                    <Input
                                        id="end_time"
                                        v-model="formData.end_time"
                                        type="time"
                                        required
                                    />
                                </div>

                                <!-- Submit Button -->
                                <Button
                                    type="submit"
                                    :disabled="formLoading"
                                    class="w-full min-w-[120px]"
                                >
                                    <Loader2 v-if="formLoading" class="animate-spin mr-2 h-4 w-4" />
                                    <Plus v-else class="h-4 w-4 mr-2" />
                                    Add Time Slot
                                </Button>
                            </form>
                        </SheetContent>
                    </Sheet>
                </div>
            </CardHeader>
        </Card>

        <!-- Calendar Card -->
        <Card>
            <CardContent class="p-6">
                <!-- Loading State -->
                <div v-if="loading" class="space-y-4">
                    <Skeleton class="h-12 w-full" />
                    <Skeleton class="h-96 w-full" />
                    <Skeleton class="h-12 w-full" />
                </div>

                <!-- Empty State -->
                <div
                    v-else-if="!hasCalendarItems"
                    class="flex flex-col items-center justify-center py-16 text-center"
                >
                    <CalendarIcon class="h-16 w-16 text-muted-foreground mb-4" />
                    <h3 class="text-lg font-semibold mb-2">No schedule set up yet</h3>
                    <p class="text-sm text-muted-foreground mb-6">
                        Click the "Add Time Slot" button above to start adding available time slots
                    </p>
                </div>

                <!-- Calendar View -->
                <div v-else class="schedule-x-calendar-wrapper">
                    <ScheduleXCalendar :calendar-app="calendar" />
                </div>
            </CardContent>
        </Card>

        <!-- Delete Confirmation Dialog -->
        <Dialog :open="isDeleteDialogOpen" @update:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove Time Slot</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to remove this time slot? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="itemToDelete" class="py-4">
                    <p class="text-sm text-muted-foreground">
                        <strong>Time:</strong>
                        {{ itemToDelete.start_time.substring(0, 5) }} - {{ itemToDelete.end_time.substring(0, 5) }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="handleCancelDelete"
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
                        <Loader2 v-if="formLoading" class="animate-spin mr-2 h-4 w-4" />
                        <Trash2 v-else class="h-4 w-4 mr-2" />
                        {{ formLoading ? 'Removing...' : 'Remove' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.schedule-x-calendar-wrapper {
    width: 100%;
    height: 600px;
}

/* Ensure Schedule X calendar fills the wrapper */
:deep(.sx__calendar-wrapper) {
    height: 100%;
}
</style>
