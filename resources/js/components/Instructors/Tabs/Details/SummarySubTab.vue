<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import {
    Users,
    CheckCircle,
    Archive,
    Clock,
    Mail,
    Phone,
    MessageSquare,
} from 'lucide-vue-next'
import type { InstructorDetail } from '@/types/instructor'

interface Props {
    instructor: InstructorDetail
}

const props = defineProps<Props>()

const statsCards = [
    {
        title: 'Current Pupils',
        value: props.instructor.stats.current_pupils,
        icon: Users,
    },
    {
        title: 'Passed Pupils',
        value: props.instructor.stats.passed_pupils,
        icon: CheckCircle,
    },
    {
        title: 'Archived',
        value: props.instructor.stats.archived_pupils,
        icon: Archive,
    },
    {
        title: 'Waiting List',
        value: props.instructor.stats.waiting_list,
        icon: Clock,
    }
]

const dateRangeFormatter = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
})

function formatDateRange(start: string, end: string): string {
    const startDate = new Date(start)
    const endDate = new Date(end)
    return `${dateRangeFormatter.format(startDate)} – ${dateRangeFormatter.format(endDate)}`
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-5">
            <Card v-for="stat in statsCards" :key="stat.title">
                <CardContent class="p-6">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <component
                                :is="stat.icon"
                                class="h-4 w-4 text-muted-foreground"
                            />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ stat.value }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ stat.title }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Booking Hours -->
        <Card>
            <CardHeader>
                <CardTitle>Booking Hours</CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        v-for="week in instructor.booking_hours.weeks"
                        :key="week.start_date"
                        class="flex flex-col gap-2"
                    >
                        <div class="flex flex-col gap-0.5">
                            <p class="text-sm font-medium">{{ week.label }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatDateRange(week.start_date, week.end_date) }}
                            </p>
                        </div>
                        <p class="text-3xl font-bold">
                            {{ week.hours }}
                            <span class="text-base font-normal text-muted-foreground"
                                >hours</span
                            >
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>
 
    </div>
</template>
