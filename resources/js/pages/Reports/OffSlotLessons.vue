<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { CalendarX, CheckCircle2 } from 'lucide-vue-next';
import { clashes as clashesReport, index as reportsIndex } from '@/routes/reports';

interface OffSlotLessonRow {
    lesson_id: number;
    student_id: number | null;
    instructor_id: number | null;
    learner_name: string;
    instructor_name: string | null;
    lesson_date: string;
    lesson_time: string;
    package: string | null;
    is_provisional: boolean;
}

interface Props {
    report: {
        rows: OffSlotLessonRow[];
        generated_at: string;
    };
}

defineProps<Props>();

const formatDate = (date: string): string =>
    new Date(date).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reports', href: reportsIndex().url }, { title: 'Lessons Not In A Slot' }]">
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Lessons Not In A Slot</h1>
                <p class="text-sm text-muted-foreground">
                    Upcoming lessons booked at a time where the instructor had no pre-made available slot, so a new
                    diary item was created. Check the instructor is happy to teach at that time.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <CalendarX class="h-5 w-5" />
                        Upcoming ({{ report.rows.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table v-if="report.rows.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Instructor</TableHead>
                                <TableHead>Learner</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Time</TableHead>
                                <TableHead>Package</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in report.rows" :key="row.lesson_id">
                                <TableCell>{{ row.instructor_name ?? '—' }}</TableCell>
                                <TableCell class="font-medium">
                                    <Link
                                        v-if="row.student_id && row.instructor_id"
                                        :href="`/instructors/${row.instructor_id}?tab=student&student=${row.student_id}&subtab=lessons`"
                                        class="hover:text-primary hover:underline"
                                    >
                                        {{ row.learner_name }}
                                    </Link>
                                    <span v-else>{{ row.learner_name }}</span>
                                </TableCell>
                                <TableCell>{{ formatDate(row.lesson_date) }}</TableCell>
                                <TableCell class="tabular-nums">
                                    {{ row.lesson_time }}
                                    <Link v-if="row.is_provisional" :href="clashesReport().url" class="mt-1 block w-fit">
                                        <Badge variant="destructive">Clash</Badge>
                                    </Link>
                                </TableCell>
                                <TableCell class="text-sm">{{ row.package ?? '—' }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <CheckCircle2 class="h-12 w-12 text-muted-foreground/50" />
                        <p class="mt-4 text-lg font-medium text-muted-foreground">Every upcoming lesson is in a pre-made slot</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
