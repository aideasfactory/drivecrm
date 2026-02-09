<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Calendar } from 'lucide-vue-next'

const daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
const timeSlots = [
    '09:00',
    '10:00',
    '11:00',
    '12:00',
    '13:00',
    '14:00',
    '15:00',
    '16:00',
    '17:00',
    '18:00',
]
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Calendar Header Card -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Calendar class="h-5 w-5" />
                        <CardTitle>Weekly Schedule</CardTitle>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Calendar integration coming soon
                    </p>
                </div>
            </CardHeader>
        </Card>

        <!-- Calendar Grid Demo -->
        <Card>
            <CardContent class="p-6">
                <!-- Week Days Header -->
                <div class="grid grid-cols-8 gap-2 mb-4">
                    <div class="text-sm font-medium text-muted-foreground">Time</div>
                    <div
                        v-for="day in daysOfWeek"
                        :key="day"
                        class="text-sm font-medium text-center"
                    >
                        {{ day }}
                    </div>
                </div>

                <!-- Time Slots Grid -->
                <div class="grid grid-cols-8 gap-2">
                    <template v-for="time in timeSlots" :key="time">
                        <!-- Time Label -->
                        <div class="text-sm text-muted-foreground py-2">
                            {{ time }}
                        </div>

                        <!-- Empty slots for each day -->
                        <div
                            v-for="day in daysOfWeek"
                            :key="`${day}-${time}`"
                            class="min-h-[60px] rounded border border-dashed border-muted p-2"
                        >
                            <!-- Placeholder for lessons/bookings -->
                        </div>
                    </template>
                </div>

                <!-- Demo Message -->
                <div class="mt-8 text-center">
                    <p class="text-sm text-muted-foreground">
                        This is a demo calendar layout. Actual lesson scheduling and
                        booking functionality will be implemented in a future phase.
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
