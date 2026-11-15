<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { CalendarX, CheckCircle2, Download, Mail, Phone } from 'lucide-vue-next';
import { index as reportsIndex } from '@/routes/reports';
import { exportMethod as exportLowAvailability } from '@/routes/reports/low-availability';

interface LowAvailabilityRow {
    instructor_id: number;
    name: string;
    email: string;
    phone: string | null;
    available_slots_by_week: number[];
    total_available_slots: number;
}

interface Props {
    report: {
        rows: LowAvailabilityRow[];
        settings: {
            weeks: number;
            min_slots_per_week: number;
        };
        week_starts: string[];
        generated_at: string;
    };
}

const props = defineProps<Props>();

const exportUrl = exportLowAvailability.url();

const formatWeek = (date: string): string =>
    new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });

const isShortWeek = (slots: number): boolean => slots < props.report.settings.min_slots_per_week;
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reports', href: reportsIndex().url }, { title: 'Low Availability Instructors' }]">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Low Availability Instructors</h1>
                    <p class="text-sm text-muted-foreground">
                        Active instructors with Stripe connected who have fewer than
                        {{ report.settings.min_slots_per_week }} open slot{{ report.settings.min_slots_per_week === 1 ? '' : 's' }}
                        in at least one of the next {{ report.settings.weeks }} weeks. Open slots are unbooked "Available"
                        diary slots. Change the threshold in Settings → Low Availability.
                    </p>
                </div>
                <Button v-if="report.rows.length > 0" as="a" :href="exportUrl" variant="outline">
                    <Download class="h-4 w-4" />
                    Download CSV
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <CalendarX class="h-5 w-5" />
                        Low availability ({{ report.rows.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table v-if="report.rows.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Instructor</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead v-for="weekStart in report.week_starts" :key="weekStart" class="text-right">
                                    Week of {{ formatWeek(weekStart) }}
                                </TableHead>
                                <TableHead class="text-right">Total</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in report.rows" :key="row.instructor_id">
                                <TableCell class="font-medium">
                                    <Link :href="`/instructors/${row.instructor_id}`" class="hover:text-primary hover:underline">
                                        {{ row.name }}
                                    </Link>
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-col gap-1 text-sm">
                                        <a
                                            v-if="row.phone"
                                            :href="`tel:${row.phone}`"
                                            class="flex items-center gap-1.5 text-muted-foreground hover:text-foreground"
                                        >
                                            <Phone class="h-3.5 w-3.5" />
                                            {{ row.phone }}
                                        </a>
                                        <a
                                            :href="`mailto:${row.email}`"
                                            class="flex items-center gap-1.5 text-muted-foreground hover:text-foreground"
                                        >
                                            <Mail class="h-3.5 w-3.5" />
                                            {{ row.email }}
                                        </a>
                                    </div>
                                </TableCell>
                                <TableCell
                                    v-for="(slots, week) in row.available_slots_by_week"
                                    :key="week"
                                    class="text-right tabular-nums"
                                    :class="isShortWeek(slots) ? 'font-semibold text-destructive' : ''"
                                >
                                    {{ slots }}
                                </TableCell>
                                <TableCell class="text-right font-medium tabular-nums">{{ row.total_available_slots }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <CheckCircle2 class="h-12 w-12 text-muted-foreground/50" />
                        <p class="mt-4 text-lg font-medium text-muted-foreground">No low availability instructors</p>
                        <p class="mt-1 text-sm text-muted-foreground/70">Every active instructor meets the availability threshold.</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
