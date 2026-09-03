<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CalendarX, Download, CheckCircle2, X } from 'lucide-vue-next';
import { index as reportsIndex, cancelledLessons } from '@/routes/reports';

interface CancelledLessonRow {
    lesson_id: number;
    student_id: number | null;
    instructor_id: number | null;
    learner_name: string;
    learner_phone: string | null;
    learner_email: string | null;
    instructor_name: string | null;
    lesson_date: string;
    lesson_time: string;
    amount: string;
    amount_pence: number;
    payment_status: string | null;
    cancellation_reason: string | null;
    cancelled_at: string | null;
}

interface Filters {
    cancelled_from: string | null;
    cancelled_to: string | null;
    payment_status: 'paid' | 'due' | 'refunded' | null;
}

interface Props {
    report: {
        rows: CancelledLessonRow[];
        generated_at: string;
    };
    filters: Filters;
}

const props = defineProps<Props>();

const paymentOptions: { value: Filters['payment_status']; label: string }[] = [
    { value: null, label: 'All' },
    { value: 'paid', label: 'Paid — refund required' },
    { value: 'due', label: 'Unpaid' },
    { value: 'refunded', label: 'Refunded' },
];

const cancelledFrom = ref(props.filters.cancelled_from ?? '');
const cancelledTo = ref(props.filters.cancelled_to ?? '');

const activeFilterParams = (changes: Partial<Filters> = {}): Record<string, string> => {
    const merged: Filters = {
        cancelled_from: cancelledFrom.value || null,
        cancelled_to: cancelledTo.value || null,
        payment_status: props.filters.payment_status,
        ...changes,
    };

    return Object.fromEntries(Object.entries(merged).filter(([, value]) => value !== null && value !== '')) as Record<string, string>;
};

const applyFilters = (changes: Partial<Filters> = {}) => {
    router.get(cancelledLessons.url(), activeFilterParams(changes), { preserveScroll: true, preserveState: true });
};

const clearDates = () => {
    cancelledFrom.value = '';
    cancelledTo.value = '';
    applyFilters();
};

const hasDateFilter = computed(() => cancelledFrom.value !== '' || cancelledTo.value !== '');

const exportUrl = computed(() => cancelledLessons.export.url({ query: activeFilterParams() }));

const formatDate = (date: string | null): string =>
    date ? new Date(date).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) : '—';

const formatDateTime = (date: string | null): string =>
    date
        ? new Date(date).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : '—';

const paymentBadgeVariant = (status: string | null): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'paid') return 'destructive';
    if (status === 'refunded') return 'secondary';
    return 'outline';
};

const paymentLabel = (status: string | null): string => {
    if (status === 'paid') return 'Paid — refund required';
    if (status === 'refunded') return 'Refunded';
    if (status === 'due') return 'Unpaid';
    return '—';
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reports', href: reportsIndex().url }, { title: 'Cancelled Lessons' }]">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Cancelled Lessons</h1>
                    <p class="text-sm text-muted-foreground">
                        Lessons cancelled when an instructor's booked diary slot was removed. The lesson record is kept even though the diary
                        slot is deleted.
                    </p>
                </div>
                <Button v-if="report.rows.length > 0" as="a" :href="exportUrl" variant="outline">
                    <Download class="h-4 w-4" />
                    Download CSV
                </Button>
            </div>

            <Card>
                <CardContent class="flex flex-wrap items-end gap-6 pt-6">
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs text-muted-foreground">Payment</Label>
                        <div class="flex items-center gap-1 rounded-lg border p-1">
                            <Button
                                v-for="option in paymentOptions"
                                :key="option.value ?? 'all'"
                                size="sm"
                                :variant="filters.payment_status === option.value ? 'default' : 'ghost'"
                                @click="applyFilters({ payment_status: option.value })"
                            >
                                {{ option.label }}
                            </Button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="cancelled-from" class="text-xs text-muted-foreground">Cancelled from</Label>
                        <Input id="cancelled-from" v-model="cancelledFrom" type="date" class="w-40" @change="applyFilters()" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="cancelled-to" class="text-xs text-muted-foreground">Cancelled to</Label>
                        <Input id="cancelled-to" v-model="cancelledTo" type="date" class="w-40" @change="applyFilters()" />
                    </div>
                    <Button v-if="hasDateFilter" variant="ghost" size="sm" @click="clearDates">
                        <X class="h-4 w-4" />
                        Clear dates
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <CalendarX class="h-5 w-5" />
                        Cancelled lessons ({{ report.rows.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table v-if="report.rows.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Learner</TableHead>
                                <TableHead>Instructor</TableHead>
                                <TableHead>Lesson</TableHead>
                                <TableHead class="text-right">Amount</TableHead>
                                <TableHead>Payment</TableHead>
                                <TableHead>Reason</TableHead>
                                <TableHead>Cancelled</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in report.rows" :key="row.lesson_id">
                                <TableCell class="font-medium">
                                    <Link
                                        v-if="row.student_id && row.instructor_id"
                                        :href="`/instructors/${row.instructor_id}?tab=student&student=${row.student_id}&subtab=overview`"
                                        class="hover:text-primary hover:underline"
                                    >
                                        {{ row.learner_name }}
                                    </Link>
                                    <span v-else>{{ row.learner_name }}</span>
                                </TableCell>
                                <TableCell>{{ row.instructor_name ?? '—' }}</TableCell>
                                <TableCell>
                                    <div class="flex flex-col">
                                        <span>{{ formatDate(row.lesson_date) }}</span>
                                        <span class="text-sm text-muted-foreground">{{ row.lesson_time }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right font-medium">{{ row.amount }}</TableCell>
                                <TableCell>
                                    <Badge :variant="paymentBadgeVariant(row.payment_status)">
                                        {{ paymentLabel(row.payment_status) }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="max-w-xs">
                                    <span class="line-clamp-2 text-sm">{{ row.cancellation_reason ?? '—' }}</span>
                                </TableCell>
                                <TableCell class="whitespace-nowrap text-sm text-muted-foreground">
                                    {{ formatDateTime(row.cancelled_at) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <CheckCircle2 class="h-12 w-12 text-muted-foreground/50" />
                        <p class="mt-4 text-lg font-medium text-muted-foreground">No cancelled lessons</p>
                        <p class="mt-1 text-sm text-muted-foreground/70">
                            {{
                                filters.payment_status || hasDateFilter
                                    ? 'No cancelled lessons match the current filters.'
                                    : 'No booked lessons have been cancelled yet.'
                            }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
