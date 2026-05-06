<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import {
    PoundSterling,
    Users,
    CalendarCheck,
    GraduationCap,
    TrendingUp,
    TrendingDown,
    Minus,
    Activity,
    UserPlus,
    Trophy,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Kpi {
    label: string;
    value: number;
    delta: number | null;
}

interface WeeklyPoint {
    week: string;
    value: number;
}

interface PaymentModeRow {
    mode: string;
    count: number;
    revenue: number;
}

interface OrderStatusRow {
    status: string;
    count: number;
}

interface TopInstructor {
    id: number;
    name: string;
    revenue: number;
    lessons_completed: number;
}

interface ActivityRow {
    id: number;
    category: string;
    message: string;
    created_at: string;
    subject: string | null;
}

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
    created_at: string;
}

interface Metrics {
    kpis: Record<string, Kpi>;
    revenueTrend: WeeklyPoint[];
    signupsTrend: WeeklyPoint[];
    paymentModeMix: PaymentModeRow[];
    orderStatusBreakdown: OrderStatusRow[];
    topInstructors: TopInstructor[];
    latestActivity: ActivityRow[];
    latestUsers: UserRow[];
}

const props = defineProps<{ metrics: Metrics }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: dashboard().url }];

const formatPence = (pence: number): string =>
    new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        maximumFractionDigits: 0,
    }).format((pence || 0) / 100);

const formatNumber = (n: number): string => new Intl.NumberFormat('en-GB').format(n || 0);

const formatDate = (iso: string): string => {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatRelative = (iso: string): string => {
    if (!iso) return '';
    const diffMs = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diffMs / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    const days = Math.floor(hrs / 24);
    if (days < 30) return `${days}d ago`;
    return formatDate(iso);
};

const isCurrencyKpi = (key: string) => key === 'revenue30' || key === 'avgOrderValue';

const kpiOrder = [
    'revenue30',
    'newStudents30',
    'lessonsBooked30',
    'lessonsCompleted30',
    'activeOrders',
    'activeStudents',
    'activeInstructors',
    'avgOrderValue',
] as const;

const orderedKpis = computed(() =>
    kpiOrder
        .filter((k) => props.metrics.kpis[k])
        .map((k) => ({ key: k, ...props.metrics.kpis[k] })),
);

const revenueMax = computed(() =>
    Math.max(1, ...props.metrics.revenueTrend.map((p) => p.value)),
);
const signupsMax = computed(() =>
    Math.max(1, ...props.metrics.signupsTrend.map((p) => p.value)),
);

const totalPaymentMix = computed(() =>
    props.metrics.paymentModeMix.reduce((acc, r) => acc + r.count, 0),
);

const paymentMixWithPct = computed(() =>
    props.metrics.paymentModeMix.map((r) => ({
        ...r,
        pct: totalPaymentMix.value > 0 ? Math.round((r.count / totalPaymentMix.value) * 100) : 0,
    })),
);

const totalOrders = computed(() =>
    props.metrics.orderStatusBreakdown.reduce((acc, r) => acc + r.count, 0),
);

const orderStatusWithPct = computed(() =>
    props.metrics.orderStatusBreakdown.map((r) => ({
        ...r,
        pct: totalOrders.value > 0 ? Math.round((r.count / totalOrders.value) * 100) : 0,
    })),
);

const statusVariant = (
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'active':
        case 'completed':
            return 'default';
        case 'pending':
            return 'secondary';
        case 'cancelled':
            return 'destructive';
        default:
            return 'outline';
    }
};

const roleVariant = (role: string): 'default' | 'secondary' | 'outline' => {
    if (role === 'owner') return 'default';
    if (role === 'instructor') return 'secondary';
    return 'outline';
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <!-- KPI cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card v-for="kpi in orderedKpis" :key="kpi.key">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            {{ kpi.label }}
                        </CardTitle>
                        <PoundSterling
                            v-if="kpi.key === 'revenue30' || kpi.key === 'avgOrderValue'"
                            class="h-4 w-4 text-muted-foreground"
                        />
                        <Users
                            v-else-if="kpi.key === 'newStudents30' || kpi.key === 'activeStudents'"
                            class="h-4 w-4 text-muted-foreground"
                        />
                        <CalendarCheck
                            v-else-if="kpi.key === 'lessonsBooked30' || kpi.key === 'lessonsCompleted30'"
                            class="h-4 w-4 text-muted-foreground"
                        />
                        <GraduationCap v-else class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ isCurrencyKpi(kpi.key) ? formatPence(kpi.value) : formatNumber(kpi.value) }}
                        </div>
                        <div
                            v-if="kpi.delta !== null"
                            class="mt-1 flex items-center gap-1 text-xs"
                            :class="{
                                'text-green-600': kpi.delta > 0,
                                'text-red-600': kpi.delta < 0,
                                'text-muted-foreground': kpi.delta === 0,
                            }"
                        >
                            <TrendingUp v-if="kpi.delta > 0" class="h-3 w-3" />
                            <TrendingDown v-else-if="kpi.delta < 0" class="h-3 w-3" />
                            <Minus v-else class="h-3 w-3" />
                            <span>{{ kpi.delta > 0 ? '+' : '' }}{{ kpi.delta }}% vs prev 30d</span>
                        </div>
                        <div v-else class="mt-1 text-xs text-muted-foreground">Live total</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Trend charts -->
            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <TrendingUp class="h-5 w-5" />
                            Revenue (last 12 weeks)
                        </CardTitle>
                        <CardDescription>Weekly order revenue from active &amp; completed orders</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex h-44 gap-2">
                            <div
                                v-for="point in metrics.revenueTrend"
                                :key="point.week"
                                class="group relative flex flex-1 flex-col justify-end"
                            >
                                <div class="absolute -top-4 left-1/2 -translate-x-1/2 whitespace-nowrap text-[10px] font-medium opacity-0 group-hover:opacity-100">
                                    {{ formatPence(point.value) }}
                                </div>
                                <div
                                    class="w-full rounded-t bg-primary transition-all hover:bg-primary/80"
                                    :style="{
                                        height: `${(point.value / revenueMax) * 100}%`,
                                        minHeight: '2px',
                                    }"
                                />
                            </div>
                        </div>
                        <div class="mt-1 flex gap-2">
                            <div
                                v-for="point in metrics.revenueTrend"
                                :key="`label-${point.week}`"
                                class="flex-1 text-center text-[10px] text-muted-foreground"
                            >
                                {{ point.week }}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <UserPlus class="h-5 w-5" />
                            New Student Signups (last 12 weeks)
                        </CardTitle>
                        <CardDescription>Weekly student registrations</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex h-44 gap-2">
                            <div
                                v-for="point in metrics.signupsTrend"
                                :key="point.week"
                                class="group relative flex flex-1 flex-col justify-end"
                            >
                                <div class="absolute -top-4 left-1/2 -translate-x-1/2 whitespace-nowrap text-[10px] font-medium opacity-0 group-hover:opacity-100">
                                    {{ formatNumber(point.value) }}
                                </div>
                                <div
                                    class="w-full rounded-t bg-blue-500 transition-all hover:bg-blue-400"
                                    :style="{
                                        height: `${(point.value / signupsMax) * 100}%`,
                                        minHeight: '2px',
                                    }"
                                />
                            </div>
                        </div>
                        <div class="mt-1 flex gap-2">
                            <div
                                v-for="point in metrics.signupsTrend"
                                :key="`label-${point.week}`"
                                class="flex-1 text-center text-[10px] text-muted-foreground"
                            >
                                {{ point.week }}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Breakdowns -->
            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Payment Mode Mix</CardTitle>
                        <CardDescription>Upfront vs weekly across active &amp; completed orders</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="paymentMixWithPct.length === 0" class="text-sm text-muted-foreground">
                            No data yet.
                        </div>
                        <div v-else class="space-y-4">
                            <div v-for="row in paymentMixWithPct" :key="row.mode">
                                <div class="mb-1 flex items-center justify-between text-sm">
                                    <span class="font-medium capitalize">{{ row.mode }}</span>
                                    <span class="text-muted-foreground">
                                        {{ row.count }} orders · {{ formatPence(row.revenue) }} ({{ row.pct }}%)
                                    </span>
                                </div>
                                <div class="h-2 w-full rounded-full bg-muted">
                                    <div
                                        class="h-2 rounded-full bg-primary"
                                        :style="{ width: `${row.pct}%` }"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Order Status Breakdown</CardTitle>
                        <CardDescription>All orders grouped by current status</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Status</TableHead>
                                    <TableHead class="text-right">Orders</TableHead>
                                    <TableHead class="text-right">Share</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="row in orderStatusWithPct" :key="row.status">
                                    <TableCell>
                                        <Badge :variant="statusVariant(row.status)" class="capitalize">
                                            {{ row.status }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">{{ formatNumber(row.count) }}</TableCell>
                                    <TableCell class="text-right">{{ row.pct }}%</TableCell>
                                </TableRow>
                                <TableRow v-if="orderStatusWithPct.length === 0">
                                    <TableCell colspan="3" class="text-center text-muted-foreground">
                                        No orders yet.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <!-- Tables row -->
            <div class="grid gap-4 lg:grid-cols-3">
                <Card class="lg:col-span-1">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Trophy class="h-5 w-5" />
                            Top Instructors (30d)
                        </CardTitle>
                        <CardDescription>By revenue from new orders</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Instructor</TableHead>
                                    <TableHead class="text-right">Revenue</TableHead>
                                    <TableHead class="text-right">Lessons</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="inst in metrics.topInstructors" :key="inst.id">
                                    <TableCell class="font-medium">{{ inst.name }}</TableCell>
                                    <TableCell class="text-right">{{ formatPence(inst.revenue) }}</TableCell>
                                    <TableCell class="text-right">{{ inst.lessons_completed }}</TableCell>
                                </TableRow>
                                <TableRow v-if="metrics.topInstructors.length === 0">
                                    <TableCell colspan="3" class="text-center text-muted-foreground">
                                        No instructor activity in window.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card class="lg:col-span-1">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Activity class="h-5 w-5" />
                            Latest Activity
                        </CardTitle>
                        <CardDescription>Most recent system events</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Event</TableHead>
                                    <TableHead class="text-right">When</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="log in metrics.latestActivity" :key="log.id">
                                    <TableCell>
                                        <div class="flex flex-col">
                                            <span class="text-sm">{{ log.message }}</span>
                                            <span class="text-xs text-muted-foreground">
                                                <Badge variant="outline" class="mr-1 capitalize">
                                                    {{ log.category }}
                                                </Badge>
                                                <span v-if="log.subject">{{ log.subject }}</span>
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-right text-xs text-muted-foreground">
                                        {{ formatRelative(log.created_at) }}
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="metrics.latestActivity.length === 0">
                                    <TableCell colspan="2" class="text-center text-muted-foreground">
                                        No activity yet.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card class="lg:col-span-1">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <UserPlus class="h-5 w-5" />
                            Latest Registered Users
                        </CardTitle>
                        <CardDescription>Newest accounts created</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>User</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead class="text-right">Joined</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="user in metrics.latestUsers" :key="user.id">
                                    <TableCell>
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium">{{ user.name }}</span>
                                            <span class="text-xs text-muted-foreground">{{ user.email }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="roleVariant(user.role)" class="capitalize">
                                            {{ user.role }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right text-xs text-muted-foreground">
                                        {{ formatRelative(user.created_at) }}
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="metrics.latestUsers.length === 0">
                                    <TableCell colspan="3" class="text-center text-muted-foreground">
                                        No users yet.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
