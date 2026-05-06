<script setup lang="ts">
import { computed, watch } from 'vue';
import { Head, router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { toast } from '@/components/ui/sonner';
import { useHmrcAction } from '@/composables/useHmrcAction';
import {
    AlertCircle,
    CalendarClock,
    CheckCircle2,
    ExternalLink,
    Info,
    Pencil,
    RefreshCw,
    ShieldCheck,
} from 'lucide-vue-next';

interface EnrolmentStatus {
    value: string;
    label: string;
    can_submit: boolean;
    checked_at: string | null;
}

interface BusinessLite {
    business_id: string;
    type_of_business: string;
    trading_name: string | null;
    accounting_type: string | null;
    commencement_date: string | null;
    cessation_date: string | null;
}

interface ObligationLite {
    business_id: string;
    period_key: string;
    period_start_date: string;
    period_end_date: string;
    due_date: string;
    status: string;
    days_until_due: number;
}

interface SubmissionRow {
    id: number;
    business_id: string;
    period_key: string;
    period_start_date: string;
    period_end_date: string;
    submitted_at: string | null;
    submission_id: string | null;
    correlation_id: string | null;
    turnover: number;
    total_expenses: number;
    is_itemised: boolean;
}

interface PageProps {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

const props = defineProps<{
    connected: boolean;
    enrolmentStatus: EnrolmentStatus;
    businesses: BusinessLite[];
    openObligations: ObligationLite[];
    history: SubmissionRow[];
}>();

const page = usePage<PageProps>();

watch(
    () => page.props.flash?.success,
    (v) => {
        if (v) toast.success(v);
    },
    { immediate: true },
);
watch(
    () => page.props.flash?.error,
    (v) => {
        if (v) toast.error(v);
    },
    { immediate: true },
);

const formatDate = (iso: string | null): string => (iso ? new Date(iso).toLocaleDateString() : '—');
const formatDateTime = (iso: string | null): string => (iso ? new Date(iso).toLocaleString() : '—');
const formatGbp = (amount: number): string =>
    amount.toLocaleString(undefined, { style: 'currency', currency: 'GBP' });

const hmrcAction = useHmrcAction();

const refreshStatus = async () => {
    await hmrcAction.refreshFingerprint();
    router.post('/hmrc/itsa/refresh-status', {}, { preserveScroll: true });
};
const syncObligations = async () => {
    await hmrcAction.refreshFingerprint();
    router.post('/hmrc/itsa/sync-obligations', {}, { preserveScroll: true });
};
const goToPeriod = (businessId: string, periodKey: string) => {
    router.visit(`/hmrc/itsa/${encodeURIComponent(businessId)}/period/${encodeURIComponent(periodKey)}`);
};

const dueBadge = (days: number): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (days <= 7) return 'destructive';
    if (days <= 14) return 'outline';
    return 'secondary';
};

const showSubmissionUi = computed(() => props.enrolmentStatus.can_submit);

const breadcrumbs = [
    { title: 'HMRC / Tax', href: '/hmrc' },
    { title: 'MTD Income Tax' },
];
</script>

<template>
    <Head title="MTD Income Tax" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-2">
                <h2 class="text-3xl font-bold flex items-center gap-3">
                    <ShieldCheck class="h-8 w-8" />
                    MTD Income Tax
                </h2>
                <p class="text-muted-foreground">
                    File your quarterly self-employment update to HMRC.
                </p>
            </div>

            <Alert v-if="!connected" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertTitle>Connect to HMRC first</AlertTitle>
                <AlertDescription>
                    You need an active HMRC connection before submitting quarterly updates.
                    <Link href="/hmrc" class="underline">Open HMRC settings →</Link>
                </AlertDescription>
            </Alert>

            <!-- Enrolment status state cards -->
            <Card v-if="connected">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <ShieldCheck class="h-5 w-5" />
                        Enrolment status
                    </CardTitle>
                    <CardDescription>
                        Last checked {{ formatDateTime(enrolmentStatus.checked_at) }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <Alert v-if="enrolmentStatus.value === 'not_signed_up'" variant="destructive">
                        <AlertCircle class="h-4 w-4" />
                        <AlertTitle>{{ enrolmentStatus.label }}</AlertTitle>
                        <AlertDescription class="space-y-2">
                            <p>You need to sign up for MTD ITSA at HMRC before we can file on your behalf.</p>
                            <Button as-child variant="outline" size="sm">
                                <a
                                    href="https://www.gov.uk/guidance/sign-up-your-business-for-making-tax-digital-for-income-tax"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    Open gov.uk sign-up
                                    <ExternalLink class="ml-2 h-3 w-3" />
                                </a>
                            </Button>
                        </AlertDescription>
                    </Alert>

                    <Alert v-else-if="enrolmentStatus.value === 'income_source_missing'" variant="default">
                        <Info class="h-4 w-4" />
                        <AlertTitle>{{ enrolmentStatus.label }}</AlertTitle>
                        <AlertDescription class="space-y-2">
                            <p>
                                HMRC says you are signed up for SA but your self-employment business hasn't been
                                added to MTD yet. Add it on gov.uk, then refresh this page.
                            </p>
                            <Button as-child variant="outline" size="sm">
                                <a
                                    href="https://www.gov.uk/guidance/sign-up-your-business-for-making-tax-digital-for-income-tax"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    Open gov.uk sign-up
                                    <ExternalLink class="ml-2 h-3 w-3" />
                                </a>
                            </Button>
                        </AlertDescription>
                    </Alert>

                    <Alert v-else-if="enrolmentStatus.value === 'unknown'" variant="default">
                        <Info class="h-4 w-4" />
                        <AlertTitle>{{ enrolmentStatus.label }}</AlertTitle>
                        <AlertDescription>
                            We haven't checked your MTD enrolment with HMRC yet. Click "Refresh status" to do so.
                        </AlertDescription>
                    </Alert>

                    <Alert v-else variant="default">
                        <CheckCircle2 class="h-4 w-4" />
                        <AlertTitle>{{ enrolmentStatus.label }}</AlertTitle>
                        <AlertDescription>
                            You can submit quarterly updates from here. Mandate dates and thresholds were captured
                            on your tax profile.
                        </AlertDescription>
                    </Alert>

                    <div class="flex gap-2">
                        <Button variant="outline" @click="refreshStatus">
                            <RefreshCw class="mr-2 h-4 w-4" />
                            Refresh status
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Businesses -->
            <Card v-if="connected && businesses.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <ShieldCheck class="h-5 w-5" />
                        Your MTD businesses
                    </CardTitle>
                    <CardDescription>
                        Self-employment / property businesses registered with HMRC under your NINO.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Trading name</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Accounting</TableHead>
                                <TableHead>Started</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="b in businesses" :key="b.business_id">
                                <TableCell>{{ b.trading_name ?? '—' }}</TableCell>
                                <TableCell>{{ b.type_of_business }}</TableCell>
                                <TableCell>{{ b.accounting_type ?? '—' }}</TableCell>
                                <TableCell>{{ formatDate(b.commencement_date) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Open obligations -->
            <Card v-if="showSubmissionUi">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <CalendarClock class="h-5 w-5" />
                        Open obligations
                    </CardTitle>
                    <CardDescription>
                        Quarterly periods waiting to be submitted.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div v-if="openObligations.length === 0" class="text-sm text-muted-foreground">
                        Nothing open right now. Tap "Sync from HMRC" to refresh.
                    </div>
                    <Table v-else>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Period</TableHead>
                                <TableHead>Due</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="o in openObligations" :key="`${o.business_id}:${o.period_key}`">
                                <TableCell>
                                    {{ formatDate(o.period_start_date) }} – {{ formatDate(o.period_end_date) }}
                                </TableCell>
                                <TableCell class="flex items-center gap-2">
                                    {{ formatDate(o.due_date) }}
                                    <Badge :variant="dueBadge(o.days_until_due)">
                                        {{ o.days_until_due }} days
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">{{ o.status }}</Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button size="sm" @click="goToPeriod(o.business_id, o.period_key)">
                                        Submit / amend
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div>
                        <Button variant="outline" @click="syncObligations">
                            <RefreshCw class="mr-2 h-4 w-4" />
                            Sync from HMRC
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- History -->
            <Card v-if="history.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <CheckCircle2 class="h-5 w-5" />
                        Submission history
                    </CardTitle>
                    <CardDescription>Your filed quarterly updates and HMRC reference numbers.</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Period</TableHead>
                                <TableHead>Submitted</TableHead>
                                <TableHead>Turnover</TableHead>
                                <TableHead>Expenses</TableHead>
                                <TableHead>HMRC reference</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="h in history" :key="h.id">
                                <TableCell>
                                    {{ formatDate(h.period_start_date) }} – {{ formatDate(h.period_end_date) }}
                                </TableCell>
                                <TableCell>{{ formatDateTime(h.submitted_at) }}</TableCell>
                                <TableCell>{{ formatGbp(h.turnover) }}</TableCell>
                                <TableCell>
                                    {{ formatGbp(h.total_expenses) }}
                                    <Badge variant="secondary" class="ml-1">
                                        {{ h.is_itemised ? 'itemised' : 'consolidated' }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <span class="font-mono text-xs">{{ h.submission_id ?? '—' }}</span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button size="sm" variant="outline" @click="goToPeriod(h.business_id, h.period_key)">
                                        <Pencil class="mr-2 h-3 w-3" />
                                        Amend
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
