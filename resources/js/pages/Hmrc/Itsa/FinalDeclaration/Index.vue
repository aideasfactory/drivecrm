<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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
    Calculator,
    CheckCircle2,
    ChevronRight,
    FileSignature,
    Loader2,
    ShieldCheck,
} from 'lucide-vue-next';

interface Step {
    type: string;
    label: string;
    completed: boolean;
    submitted_at: string | null;
}

interface Quarterly {
    id: number;
    business_id: string;
    period_key: string;
    period_start_date: string;
    period_end_date: string;
    submission_id: string | null;
    submitted_at: string | null;
    turnover: number;
    total_expenses: number;
}

interface CalculationRow {
    id: number;
    calculation_id: string;
    type: string;
    status: string;
    triggered_at: string;
    processed_at: string | null;
}

interface FinalDeclaration {
    id: number;
    submitted_at: string | null;
    correlation_id: string | null;
}

interface PageProps {
    flash?: { success?: string | null; error?: string | null };
}

const props = defineProps<{
    taxYear: string;
    steps: Step[];
    quarterly: Quarterly[];
    calculations: CalculationRow[];
    finalDeclaration: FinalDeclaration | null;
}>();

const page = usePage<PageProps>();
const action = useHmrcAction();
const triggering = ref(false);

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

const allSupplementaryComplete = props.steps.every((s) => s.completed);
const canTriggerCalculation = allSupplementaryComplete && !props.finalDeclaration;

const stepBadge = (completed: boolean): 'secondary' | 'default' =>
    completed ? 'default' : 'secondary';

const goToStep = (type: string) => {
    router.visit(`/hmrc/itsa/final-declaration/${props.taxYear}/step/${type}`);
};

const triggerCalculation = async () => {
    triggering.value = true;
    await action.refreshFingerprint().catch(() => null);
    if (action.error.value) {
        toast.error(action.error.value);
        triggering.value = false;
        return;
    }
    router.post(
        `/hmrc/itsa/final-declaration/${props.taxYear}/calculate`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                triggering.value = false;
            },
        },
    );
};

const goToCalculation = (id: number) => {
    router.visit(`/hmrc/itsa/final-declaration/${props.taxYear}/calculation/${id}`);
};

const breadcrumbs = [
    { title: 'HMRC / Tax', href: '/hmrc' },
    { title: 'MTD Income Tax', href: '/hmrc/itsa' },
    { title: `Final declaration ${props.taxYear}` },
];
</script>

<template>
    <Head :title="`Final declaration ${taxYear}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-2">
                <h2 class="text-3xl font-bold flex items-center gap-3">
                    <FileSignature class="h-8 w-8" />
                    Final declaration {{ taxYear }}
                </h2>
                <p class="text-muted-foreground">
                    Review your year, add supplementary income/reliefs, then submit your final declaration.
                </p>
            </div>

            <Alert v-if="finalDeclaration" variant="default">
                <CheckCircle2 class="h-4 w-4" />
                <AlertTitle>Final declaration submitted</AlertTitle>
                <AlertDescription>
                    Submitted {{ formatDateTime(finalDeclaration.submitted_at) }}.
                    Correlation ID:
                    <span class="font-mono text-xs">{{ finalDeclaration.correlation_id ?? '—' }}</span>.
                    Quarterly updates for this year are now immutable.
                </AlertDescription>
            </Alert>

            <!-- Step 1: Self-employment review -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <ShieldCheck class="h-5 w-5" />
                        Step 1 — Self-employment quarterly updates
                    </CardTitle>
                    <CardDescription>
                        Already submitted via the quarterly flow. To amend, return to the
                        <Link :href="`/hmrc/itsa`" class="underline">MTD ITSA dashboard</Link>.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Alert v-if="quarterly.length === 0" variant="destructive">
                        <AlertCircle class="h-4 w-4" />
                        <AlertTitle>No quarterly updates found for {{ taxYear }}</AlertTitle>
                        <AlertDescription>
                            HMRC will reject the final declaration unless all four quarterly updates have been
                            submitted for this tax year.
                        </AlertDescription>
                    </Alert>
                    <Table v-else>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Period</TableHead>
                                <TableHead>Submitted</TableHead>
                                <TableHead>Turnover</TableHead>
                                <TableHead>Expenses</TableHead>
                                <TableHead>Reference</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="q in quarterly" :key="q.id">
                                <TableCell>
                                    {{ formatDate(q.period_start_date) }} – {{ formatDate(q.period_end_date) }}
                                </TableCell>
                                <TableCell>{{ formatDateTime(q.submitted_at) }}</TableCell>
                                <TableCell>{{ formatGbp(q.turnover) }}</TableCell>
                                <TableCell>{{ formatGbp(q.total_expenses) }}</TableCell>
                                <TableCell class="font-mono text-xs">{{ q.submission_id ?? '—' }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Steps 2–4: Supplementary data -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <ShieldCheck class="h-5 w-5" />
                        Steps 2–4 — Supplementary data
                    </CardTitle>
                    <CardDescription>
                        Add savings, dividends, reliefs, disclosures and personal details before HMRC calculates
                        your liability. You can revisit each section until you submit the final declaration.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-2">
                    <button
                        v-for="(step, i) in steps"
                        :key="step.type"
                        type="button"
                        class="flex items-center justify-between rounded-md border px-4 py-3 text-left hover:bg-muted/50"
                        @click="goToStep(step.type)"
                        :disabled="!!finalDeclaration"
                    >
                        <div class="flex flex-col">
                            <span class="font-medium">{{ i + 2 }}. {{ step.label }}</span>
                            <span class="text-xs text-muted-foreground">
                                {{ step.completed ? `Saved ${formatDateTime(step.submitted_at)}` : 'Not submitted' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge :variant="stepBadge(step.completed)">
                                {{ step.completed ? 'Complete' : 'Pending' }}
                            </Badge>
                            <ChevronRight class="h-4 w-4 text-muted-foreground" />
                        </div>
                    </button>
                </CardContent>
            </Card>

            <!-- Step 5: Calculation -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Calculator class="h-5 w-5" />
                        Step 5 — HMRC tax calculation
                    </CardTitle>
                    <CardDescription>
                        Trigger HMRC to calculate the liability based on your quarterly + supplementary data.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <Alert v-if="!allSupplementaryComplete" variant="default">
                        <AlertCircle class="h-4 w-4" />
                        <AlertTitle>Complete steps 2–4 first</AlertTitle>
                        <AlertDescription>
                            All five supplementary sections must be saved before calculation can be triggered.
                        </AlertDescription>
                    </Alert>

                    <Table v-if="calculations.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Triggered</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Processed</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="c in calculations" :key="c.id">
                                <TableCell>{{ formatDateTime(c.triggered_at) }}</TableCell>
                                <TableCell>{{ c.type }}</TableCell>
                                <TableCell>
                                    <Badge :variant="c.status === 'processed' ? 'default' : c.status === 'errored' ? 'destructive' : 'secondary'">
                                        {{ c.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell>{{ formatDateTime(c.processed_at) }}</TableCell>
                                <TableCell class="text-right">
                                    <Button size="sm" variant="outline" @click="goToCalculation(c.id)">
                                        Review
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <Button
                        :disabled="!canTriggerCalculation || triggering"
                        @click="triggerCalculation"
                    >
                        <Loader2 v-if="triggering" class="mr-2 h-4 w-4 animate-spin" />
                        <Calculator v-else class="mr-2 h-4 w-4" />
                        Trigger calculation
                    </Button>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
