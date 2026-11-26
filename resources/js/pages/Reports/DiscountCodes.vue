<script setup lang="ts">
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Download, Percent, Tag } from 'lucide-vue-next';
import { index as reportsIndex, discountCodes } from '@/routes/reports';
import { exportMethod as exportDiscountCodes } from '@/routes/reports/discount-codes';

interface DiscountedOrderRow {
    order_id: number;
    student_id: number | null;
    instructor_id: number | null;
    learner_name: string;
    learner_email: string | null;
    instructor_name: string | null;
    package: string | null;
    discount_code: string | null;
    discount_summary: string | null;
    discount_pence: number;
    total_price_pence: number;
    payment_mode: 'upfront' | 'weekly' | 'imported' | 'cash' | null;
    status: 'active' | 'completed' | 'cancelled' | null;
    booked_at: string | null;
}

interface Props {
    report: {
        rows: DiscountedOrderRow[];
        codes: string[];
        totals: { orders: number; discount_pence: number };
        generated_at: string;
    };
    filters: { code: string | null };
}

const props = defineProps<Props>();

const exportUrl = computed(() =>
    exportDiscountCodes.url(props.filters.code ? { query: { code: props.filters.code } } : undefined),
);

const filterByCode = (code: string | null): void => {
    router.get(discountCodes.url(), code ? { code } : {}, { preserveScroll: true, preserveState: true });
};

const formatDate = (date: string | null): string =>
    date ? new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

const formatCurrency = (pence: number): string =>
    new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(pence / 100);

const statusLabel = (status: DiscountedOrderRow['status']): string =>
    status === 'active' ? 'Active' : status === 'completed' ? 'Completed' : status === 'cancelled' ? 'Cancelled' : '—';
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reports', href: reportsIndex().url }, { title: 'Discount Codes' }]">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Discount Codes</h1>
                    <p class="text-sm text-muted-foreground">
                        Paid bookings that used a discount code. The discount came off the package price, so the
                        lesson prices (and the instructor's pay) are net of it; booking and digital fees were charged in
                        full. Bookings that were never paid are not shown.
                    </p>
                </div>
                <Button v-if="report.rows.length > 0" as="a" :href="exportUrl" variant="outline">
                    <Download class="h-4 w-4" />
                    Download CSV
                </Button>
            </div>

            <Card v-if="report.codes.length > 0">
                <CardContent class="flex flex-wrap items-end gap-6 pt-6">
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs text-muted-foreground">Code</Label>
                        <div class="flex flex-wrap items-center gap-1 rounded-lg border p-1">
                            <Button size="sm" :variant="filters.code ? 'ghost' : 'default'" @click="filterByCode(null)">
                                All codes
                            </Button>
                            <Button
                                v-for="code in report.codes"
                                :key="code"
                                size="sm"
                                :variant="filters.code === code ? 'default' : 'ghost'"
                                @click="filterByCode(code)"
                            >
                                {{ code }}
                            </Button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs text-muted-foreground">Total discount given</Label>
                        <div class="text-lg font-semibold tabular-nums">
                            {{ formatCurrency(report.totals.discount_pence) }}
                            <span class="text-sm font-normal text-muted-foreground">
                                across {{ report.totals.orders }} booking{{ report.totals.orders === 1 ? '' : 's' }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Percent class="h-5 w-5" />
                        Discounted bookings ({{ report.rows.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table v-if="report.rows.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Booked</TableHead>
                                <TableHead>Learner</TableHead>
                                <TableHead>Instructor</TableHead>
                                <TableHead>Package</TableHead>
                                <TableHead>Code</TableHead>
                                <TableHead class="text-right">Discount</TableHead>
                                <TableHead class="text-right">Total Charged</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in report.rows" :key="row.order_id">
                                <TableCell class="text-sm">
                                    {{ formatDate(row.booked_at) }}
                                    <div class="text-xs text-muted-foreground">Booking #{{ row.order_id }}</div>
                                </TableCell>
                                <TableCell class="font-medium">
                                    <Link
                                        v-if="row.student_id && row.instructor_id"
                                        :href="`/instructors/${row.instructor_id}?tab=student&student=${row.student_id}&subtab=payments`"
                                        class="hover:text-primary hover:underline"
                                    >
                                        {{ row.learner_name }}
                                    </Link>
                                    <span v-else>{{ row.learner_name }}</span>
                                    <div v-if="row.learner_email" class="text-xs font-normal text-muted-foreground">
                                        {{ row.learner_email }}
                                    </div>
                                </TableCell>
                                <TableCell>{{ row.instructor_name ?? '—' }}</TableCell>
                                <TableCell class="text-sm">{{ row.package ?? '—' }}</TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-1.5">
                                        <Tag class="h-3.5 w-3.5 text-muted-foreground" />
                                        <span class="font-mono text-sm font-semibold">{{ row.discount_code }}</span>
                                    </div>
                                    <div v-if="row.discount_summary" class="text-xs text-muted-foreground">
                                        {{ row.discount_summary }}
                                    </div>
                                </TableCell>
                                <TableCell class="text-right tabular-nums">-{{ formatCurrency(row.discount_pence) }}</TableCell>
                                <TableCell class="text-right tabular-nums">
                                    {{ formatCurrency(row.total_price_pence) }}
                                    <div class="text-xs text-muted-foreground">
                                        {{ row.payment_mode === 'weekly' ? 'Weekly' : 'Pay in full' }}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="row.status === 'cancelled' ? 'outline' : 'secondary'">
                                        {{ statusLabel(row.status) }}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <Percent class="h-12 w-12 text-muted-foreground/50" />
                        <p class="mt-4 text-lg font-medium text-muted-foreground">No discounted bookings yet</p>
                        <p class="mt-1 text-sm text-muted-foreground/70">
                            Paid bookings that use a discount code will appear here.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
