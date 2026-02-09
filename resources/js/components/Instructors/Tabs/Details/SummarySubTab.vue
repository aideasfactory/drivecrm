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
    },
    {
        title: 'Open Enquiries',
        value: props.instructor.stats.open_enquiries,
        icon: Mail,
    },
]
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
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <p class="text-sm font-medium">Current Week</p>
                        <p class="text-3xl font-bold">
                            {{ instructor.booking_hours.current_week }}
                            <span class="text-base font-normal text-muted-foreground"
                                >hours</span
                            >
                        </p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <p class="text-sm font-medium">Next Week</p>
                        <p class="text-3xl font-bold">
                            {{ instructor.booking_hours.next_week }}
                            <span class="text-base font-normal text-muted-foreground"
                                >hours</span
                            >
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Contact Details -->
        <Card>
            <CardHeader>
                <CardTitle>Contact</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex flex-col gap-4">
                    <div
                        v-if="instructor.phone"
                        class="flex items-center justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <Phone class="h-4 w-4 text-muted-foreground" />
                            <div>
                                <p class="text-sm font-medium">Phone</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ instructor.phone }}
                                </p>
                            </div>
                        </div>
                        <Button variant="outline" size="sm">
                            <Phone class="mr-2 h-4 w-4" />
                            Call
                        </Button>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <Mail class="h-4 w-4 text-muted-foreground" />
                            <div>
                                <p class="text-sm font-medium">Email</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ instructor.email }}
                                </p>
                            </div>
                        </div>
                        <Button variant="outline" size="sm">
                            <MessageSquare class="mr-2 h-4 w-4" />
                            Message
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
