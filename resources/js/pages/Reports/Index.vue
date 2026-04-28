<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { CalendarDays, Users, CalendarCheck, TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';

interface InstructorAnalytics {
    id: number;
    name: string;
    avatar: string | null;
    total_slots: number;
    total_booked: number;
    total_free: number;
    utilization_rate: number;
}

interface AnalyticsSummary {
    total_slots: number;
    total_booked: number;
    total_free: number;
    overall_utilization: number;
}

interface Props {
    analytics: {
        instructors: InstructorAnalytics[];
        summary: AnalyticsSummary;
    };
}

const props = defineProps<Props>();

const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const getUtilizationVariant = (rate: number): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (rate >= 75) return 'default';
    if (rate >= 50) return 'secondary';
    if (rate >= 25) return 'outline';
    return 'destructive';
};

const summaryCards = computed(() => [
    {
        title: 'Total Slots',
        value: props.analytics.summary.total_slots,
        icon: CalendarDays,
    },
    {
        title: 'Total Booked',
        value: props.analytics.summary.total_booked,
        icon: CalendarCheck,
    },
    {
        title: 'Free',
        value: props.analytics.summary.total_free,
        icon: Users,
    },
    {
        title: 'Overall Utilization',
        value: `${props.analytics.summary.overall_utilization}%`,
        icon: TrendingUp,
    },
]);
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reports' }]">
        <div class="flex flex-col gap-6 p-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card v-for="card in summaryCards" :key="card.title">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            {{ card.title }}
                        </CardTitle>
                        <component :is="card.icon" class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ card.value }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Instructor Analytics Table -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-5 w-5" />
                        Instructor Availability & Booking Analytics
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table v-if="analytics.instructors.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Instructor</TableHead>
                                <TableHead class="text-right">Total</TableHead>
                                <TableHead class="text-right">Booked</TableHead>
                                <TableHead class="text-right">Free</TableHead>
                                <TableHead class="text-right">Utilization</TableHead>
                                <TableHead class="w-[200px]">Performance</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="instructor in analytics.instructors" :key="instructor.id">
                                <TableCell>
                                    <div class="flex items-center gap-3">
                                        <Avatar class="h-8 w-8">
                                            <AvatarImage v-if="instructor.avatar" :src="instructor.avatar" :alt="instructor.name" />
                                            <AvatarFallback>{{ getInitials(instructor.name) }}</AvatarFallback>
                                        </Avatar>
                                        <span class="font-medium">{{ instructor.name }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right">{{ instructor.total_slots }}</TableCell>
                                <TableCell class="text-right">{{ instructor.total_booked }}</TableCell>
                                <TableCell class="text-right">{{ instructor.total_free }}</TableCell>
                                <TableCell class="text-right">
                                    <Badge :variant="getUtilizationVariant(instructor.utilization_rate)">
                                        {{ instructor.utilization_rate }}%
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-full rounded-full bg-secondary">
                                            <div
                                                class="h-2 rounded-full bg-primary transition-all"
                                                :style="{ width: `${Math.min(instructor.utilization_rate, 100)}%` }"
                                            />
                                        </div>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <CalendarDays class="h-12 w-12 text-muted-foreground/50" />
                        <p class="mt-4 text-lg font-medium text-muted-foreground">No analytics data available</p>
                        <p class="mt-1 text-sm text-muted-foreground/70">
                            Instructor availability data will appear here once calendar slots are created.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
