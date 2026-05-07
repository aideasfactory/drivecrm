<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { toast } from '@/components/ui/sonner';
import { useHmrcAction } from '@/composables/useHmrcAction';
import { AlertCircle, ArrowLeft, CalendarClock, Loader2, Save, ShieldCheck } from 'lucide-vue-next';

interface ExpenseCategory {
    value: string;
    label: string;
    hmrc_key: string;
}

interface Obligation {
    period_start_date: string;
    period_end_date: string;
    due_date: string;
    days_until_due: number;
}

interface ExistingUpdate {
    id: number;
    submission_id: string | null;
    submitted_at: string | null;
    period_start_date: string;
    period_end_date: string;
    turnover_pence: number;
    other_income_pence: number;
    consolidated_expenses_pence: number | null;
    expenses_pence: Record<string, number | null>;
    is_itemised: boolean;
}

interface PageProps {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
    errors?: Record<string, string>;
}

const props = defineProps<{
    businessId: string;
    periodKey: string;
    obligation: Obligation | null;
    existing: ExistingUpdate | null;
    expenseCategories: ExpenseCategory[];
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

const penceToPounds = (pence: number | null): string => {
    if (pence === null || pence === undefined) return '';
    return (pence / 100).toFixed(2);
};

const initialForm = () => {
    const expenses: Record<string, string> = {};
    for (const cat of props.expenseCategories) {
        expenses[cat.value] = penceToPounds(props.existing?.expenses_pence[cat.value] ?? null);
    }
    return {
        period_start_date: props.existing?.period_start_date ?? props.obligation?.period_start_date ?? '',
        period_end_date: props.existing?.period_end_date ?? props.obligation?.period_end_date ?? '',
        turnover: penceToPounds(props.existing?.turnover_pence ?? 0),
        other_income: penceToPounds(props.existing?.other_income_pence ?? 0),
        mode: (props.existing?.is_itemised
            ? 'itemised'
            : props.existing?.consolidated_expenses_pence !== null && props.existing?.consolidated_expenses_pence !== undefined
              ? 'consolidated'
              : 'consolidated') as 'consolidated' | 'itemised',
        consolidated_expenses: penceToPounds(props.existing?.consolidated_expenses_pence ?? null),
        expenses,
        attestation: false,
    };
};

const form = ref(initialForm());
const submitting = ref(false);
const errors = ref<Record<string, string>>({});
const action = useHmrcAction();

const isAmend = computed(() => props.existing?.submission_id !== null && props.existing?.submission_id !== undefined);

const totalExpenses = computed(() => {
    if (form.value.mode === 'consolidated') {
        return parseFloat(form.value.consolidated_expenses || '0') || 0;
    }
    return Object.values(form.value.expenses).reduce(
        (sum, v) => sum + (parseFloat(v || '0') || 0),
        0,
    );
});

const showAttestationDialog = ref(false);

const buildPayload = () => {
    const payload: Record<string, unknown> = {
        period_start_date: form.value.period_start_date,
        period_end_date: form.value.period_end_date,
        turnover: form.value.turnover,
        other_income: form.value.other_income,
        attestation: form.value.attestation,
    };
    if (form.value.mode === 'consolidated') {
        payload.consolidated_expenses = form.value.consolidated_expenses;
        payload.expenses = {};
    } else {
        payload.consolidated_expenses = null;
        payload.expenses = form.value.expenses;
    }
    return payload;
};

const handleSubmit = async (event?: Event) => {
    event?.preventDefault();
    if (!form.value.attestation) {
        errors.value = { attestation: 'Tick the digital-records attestation before submitting.' };
        return;
    }
    if (!showAttestationDialog.value) {
        showAttestationDialog.value = true;
        return;
    }

    submitting.value = true;
    errors.value = {};

    const payload = buildPayload();
    const url = isAmend.value
        ? `/hmrc/itsa/quarterly-updates/${props.existing!.id}`
        : `/hmrc/itsa/${props.businessId}/period/${props.periodKey}`;
    const method = isAmend.value ? 'put' : 'post';

    // Capture fingerprint via composable; then submit via Inertia router
    await action.refreshFingerprint().catch(() => null);

    if (action.error.value) {
        errors.value = { _: action.error.value };
        submitting.value = false;
        showAttestationDialog.value = false;
        return;
    }

    router[method](url, payload, {
        preserveScroll: true,
        onError: (e) => {
            errors.value = e as Record<string, string>;
        },
        onFinish: () => {
            submitting.value = false;
            showAttestationDialog.value = false;
        },
    });
};

const dueBadge = (days: number): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (days <= 7) return 'destructive';
    if (days <= 14) return 'outline';
    return 'secondary';
};

const breadcrumbs = [
    { title: 'HMRC / Tax', href: '/hmrc' },
    { title: 'MTD Income Tax', href: '/hmrc/itsa' },
    { title: 'Quarterly update' },
];
</script>

<template>
    <Head :title="isAmend ? 'Amend quarterly update' : 'Submit quarterly update'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-4xl">
            <div class="flex flex-col gap-2">
                <Button variant="ghost" size="sm" as-child class="self-start">
                    <a href="/hmrc/itsa">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to ITSA
                    </a>
                </Button>
                <h2 class="text-3xl font-bold flex items-center gap-3">
                    <CalendarClock class="h-8 w-8" />
                    {{ isAmend ? 'Amend quarterly update' : 'Submit quarterly update' }}
                </h2>
                <div v-if="obligation" class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <span>{{ obligation.period_start_date }} – {{ obligation.period_end_date }}</span>
                    <span>·</span>
                    <span>Due {{ obligation.due_date }}</span>
                    <Badge :variant="dueBadge(obligation.days_until_due)">
                        {{ obligation.days_until_due }} days
                    </Badge>
                </div>
                <Alert v-if="isAmend" variant="default">
                    <AlertCircle class="h-4 w-4" />
                    <AlertTitle>Amending a previous submission</AlertTitle>
                    <AlertDescription>
                        HMRC reference: <span class="font-mono">{{ existing?.submission_id }}</span>.
                        Amendments are accepted up until your Final Declaration for the tax year.
                    </AlertDescription>
                </Alert>
            </div>

            <Alert v-if="errors._" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertTitle>Couldn't submit</AlertTitle>
                <AlertDescription>{{ errors._ }}</AlertDescription>
            </Alert>

            <form class="flex flex-col gap-6" @submit.prevent="handleSubmit">
                <Card>
                    <CardHeader>
                        <CardTitle>Income</CardTitle>
                        <CardDescription>Pounds and pence; HMRC takes 2dp.</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="turnover">Turnover (£) *</Label>
                            <Input id="turnover" v-model="form.turnover" inputmode="decimal" placeholder="0.00" :disabled="submitting" />
                            <p v-if="errors.turnover_pence" class="text-destructive text-sm">{{ errors.turnover_pence }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="other_income">Other income (£) *</Label>
                            <Input id="other_income" v-model="form.other_income" inputmode="decimal" placeholder="0.00" :disabled="submitting" />
                            <p v-if="errors.other_income_pence" class="text-destructive text-sm">{{ errors.other_income_pence }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Expenses</CardTitle>
                        <CardDescription>
                            Choose either a single consolidated total or itemised categories — not both.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                :variant="form.mode === 'consolidated' ? 'default' : 'outline'"
                                size="sm"
                                @click="form.mode = 'consolidated'"
                            >
                                Consolidated total
                            </Button>
                            <Button
                                type="button"
                                :variant="form.mode === 'itemised' ? 'default' : 'outline'"
                                size="sm"
                                @click="form.mode = 'itemised'"
                            >
                                Itemised categories
                            </Button>
                        </div>

                        <div v-if="form.mode === 'consolidated'" class="space-y-2 max-w-sm">
                            <Label for="consolidated_expenses">Consolidated expenses (£)</Label>
                            <Input
                                id="consolidated_expenses"
                                v-model="form.consolidated_expenses"
                                inputmode="decimal"
                                placeholder="0.00"
                                :disabled="submitting"
                            />
                            <p v-if="errors.consolidated_expenses_pence" class="text-destructive text-sm">
                                {{ errors.consolidated_expenses_pence }}
                            </p>
                        </div>

                        <div v-else class="grid gap-4 sm:grid-cols-2">
                            <div v-for="cat in expenseCategories" :key="cat.value" class="space-y-1">
                                <Label :for="`exp-${cat.value}`">{{ cat.label }} (£)</Label>
                                <Input
                                    :id="`exp-${cat.value}`"
                                    v-model="form.expenses[cat.value]"
                                    inputmode="decimal"
                                    placeholder="0.00"
                                    :disabled="submitting"
                                />
                            </div>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            Total expenses entered: £{{ totalExpenses.toFixed(2) }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <ShieldCheck class="h-5 w-5" />
                            Digital-records attestation
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex items-start gap-3">
                            <Checkbox
                                id="attestation"
                                :model-value="form.attestation"
                                @update:model-value="form.attestation = $event === true"
                            />
                            <Label for="attestation" class="cursor-pointer text-sm leading-relaxed">
                                I confirm these figures are derived from digital business records that I keep in
                                line with MTD requirements.
                            </Label>
                        </div>
                        <p v-if="errors.attestation" class="text-destructive text-sm">{{ errors.attestation }}</p>
                    </CardContent>
                </Card>

                <div class="flex justify-end gap-2">
                    <Button as-child variant="outline" type="button">
                        <a href="/hmrc/itsa">Cancel</a>
                    </Button>
                    <Button type="submit" :disabled="submitting" class="min-w-[180px]">
                        <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                        <Save v-else class="mr-2 h-4 w-4" />
                        {{ isAmend ? 'Submit amendment' : 'Submit to HMRC' }}
                    </Button>
                </div>
            </form>
        </div>

        <!-- Confirmation alert -->
        <div
            v-if="showAttestationDialog"
            class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-sm"
            @click.self="showAttestationDialog = false"
        >
            <Card class="max-w-md mx-4">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <AlertCircle class="h-5 w-5" />
                        {{ isAmend ? 'Confirm amendment' : 'Confirm submission' }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p class="text-sm">
                        {{
                            isAmend
                                ? 'This will replace the previously-filed figures for this period at HMRC.'
                                : 'This will file your quarterly update with HMRC. You can amend it later — up until your Final Declaration for the tax year.'
                        }}
                    </p>
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" @click="showAttestationDialog = false" :disabled="submitting">
                            Cancel
                        </Button>
                        <Button @click="handleSubmit()" :disabled="submitting">
                            <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                            Confirm and send
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
