<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { ReceiptText, Download, CheckCircle2, Phone, Mail } from 'lucide-vue-next';
import { index as reportsIndex, invoiceDue } from '@/routes/reports';

interface InvoiceDueRow {
    lesson_id: number;
    learner_name: string;
    learner_phone: string | null;
    learner_email: string | null;
    instructor_name: string | null;
    lesson_date: string;
    lesson_time: string;
    amount_due: string;
    amount_pence: number;
    due_date: string | null;
}

interface Props {
    report: {
        rows: InvoiceDueRow[];
        generated_at: string;
        target_date: string;
    };
}

const props = defineProps<Props>();

const exportUrl = invoiceDue.export.url();

const formatDate = (date: string | null): string =>
    date ? new Date(date).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }) : '—';

const targetDateLabel = new Date(props.report.target_date).toLocaleDateString('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reports', href: reportsIndex().url }, { title: 'Invoice Due (2 days away)' }]">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Invoice Due (2 days away)</h1>
                    <p class="text-sm text-muted-foreground">
                        Learners with an unpaid lesson on {{ targetDateLabel }} (two days from today).
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
                        <ReceiptText class="h-5 w-5" />
                        Unpaid learners ({{ report.rows.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table v-if="report.rows.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Learner</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead>Instructor</TableHead>
                                <TableHead>Lesson Time</TableHead>
                                <TableHead class="text-right">Amount Due</TableHead>
                                <TableHead>Invoice Due</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in report.rows" :key="row.lesson_id">
                                <TableCell class="font-medium">{{ row.learner_name }}</TableCell>
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
                                <TableCell>{{ row.lesson_time }}</TableCell>
                                <TableCell class="text-right font-medium">{{ row.amount_due }}</TableCell>
                                <TableCell>{{ formatDate(row.due_date) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <CheckCircle2 class="h-12 w-12 text-muted-foreground/50" />
                        <p class="mt-4 text-lg font-medium text-muted-foreground">All caught up</p>
                        <p class="mt-1 text-sm text-muted-foreground/70">
                            No learners have an unpaid lesson on {{ targetDateLabel }}.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
