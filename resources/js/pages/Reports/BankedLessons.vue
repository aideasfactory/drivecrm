<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Archive, CheckCircle2, Download, Mail, Phone } from 'lucide-vue-next';
import { index as reportsIndex } from '@/routes/reports';
import { exportMethod as exportBankedLessons } from '@/routes/reports/banked-lessons';

interface BankedLessonRow {
    lesson_id: number;
    student_id: number | null;
    instructor_id: number | null;
    learner_name: string;
    learner_phone: string | null;
    learner_email: string | null;
    instructor_name: string | null;
    package: string | null;
    order_id: number | null;
    amount_pence: number;
    payment_status: 'due' | 'paid' | 'refunded' | null;
    banked_at: string | null;
    days_waiting: number;
    expires_at: string | null;
    banked_by: string | null;
    reason: string | null;
}

interface Props {
    report: {
        rows: BankedLessonRow[];
        generated_at: string;
    };
}

defineProps<Props>();

const exportUrl = exportBankedLessons.url();

const formatDate = (date: string | null): string =>
    date ? new Date(date).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) : '—';

const formatCurrency = (pence: number): string =>
    new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(pence / 100);
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reports', href: reportsIndex().url }, { title: 'Banked Lessons' }]">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Banked Lessons</h1>
                    <p class="text-sm text-muted-foreground">
                        Lessons taken out of the diary but kept in the pupil's booking at the price they paid. Ask the
                        instructor to agree a new date and rebook them from the pupil's Lessons tab or the app. Any not
                        rebooked by their "Rebook by" date are cancelled automatically and sent to Head Office to refund.
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
                        <Archive class="h-5 w-5" />
                        Waiting to be rebooked ({{ report.rows.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table v-if="report.rows.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Learner</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead>Instructor</TableHead>
                                <TableHead>Package</TableHead>
                                <TableHead>Lesson Price</TableHead>
                                <TableHead>Kept Since</TableHead>
                                <TableHead>Rebook By</TableHead>
                                <TableHead>Reason</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in report.rows" :key="row.lesson_id">
                                <TableCell class="font-medium">
                                    <Link
                                        v-if="row.student_id && row.instructor_id"
                                        :href="`/instructors/${row.instructor_id}?tab=student&student=${row.student_id}&subtab=lessons`"
                                        class="hover:text-primary hover:underline"
                                    >
                                        {{ row.learner_name }}
                                    </Link>
                                    <span v-else>{{ row.learner_name }}</span>
                                    <div v-if="row.order_id" class="text-xs text-muted-foreground">Booking #{{ row.order_id }}</div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-col gap-1 text-sm">
                                        <a
                                            v-if="row.learner_phone"
                                            :href="`tel:${row.learner_phone}`"
                                            class="flex items-center gap-1.5 text-muted-foreground hover:text-foreground"
                                        >
                                            <Phone class="h-3.5 w-3.5" />
                                            {{ row.learner_phone }}
                                        </a>
                                        <a
                                            v-if="row.learner_email"
                                            :href="`mailto:${row.learner_email}`"
                                            class="flex items-center gap-1.5 text-muted-foreground hover:text-foreground"
                                        >
                                            <Mail class="h-3.5 w-3.5" />
                                            {{ row.learner_email }}
                                        </a>
                                        <span v-if="!row.learner_phone && !row.learner_email" class="text-muted-foreground/60">
                                            No contact details
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell>{{ row.instructor_name ?? '—' }}</TableCell>
                                <TableCell class="text-sm">{{ row.package ?? '—' }}</TableCell>
                                <TableCell>
                                    <div class="tabular-nums">{{ formatCurrency(row.amount_pence) }}</div>
                                    <Badge :variant="row.payment_status === 'paid' ? 'default' : 'outline'" class="mt-1">
                                        {{ row.payment_status === 'paid' ? 'Paid' : row.payment_status === 'due' ? 'Due' : '—' }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <div class="text-sm">{{ formatDate(row.banked_at) }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ row.days_waiting }} day{{ row.days_waiting === 1 ? '' : 's' }}
                                        <template v-if="row.banked_by"> · by {{ row.banked_by }}</template>
                                    </div>
                                </TableCell>
                                <TableCell class="text-sm">{{ formatDate(row.expires_at) }}</TableCell>
                                <TableCell class="max-w-[260px] text-sm text-muted-foreground">{{ row.reason ?? '—' }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <CheckCircle2 class="h-12 w-12 text-muted-foreground/50" />
                        <p class="mt-4 text-lg font-medium text-muted-foreground">No banked lessons</p>
                        <p class="mt-1 text-sm text-muted-foreground/70">Every kept lesson has been rebooked.</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
