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
import {
    AlertCircle,
    ArrowLeft,
    CalendarClock,
    Loader2,
    Save,
    ShieldCheck,
} from 'lucide-vue-next';

interface Obligation {
    period_start_date: string;
    period_end_date: string;
    due_date: string;
    days_until_due: number;
}

interface ExistingReturn {
    id: number;
    submitted_at: string | null;
    form_bundle_number: string | null;
    charge_ref_number: string | null;
    correlation_id: string | null;
}

interface PageProps {
    flash?: { success?: string | null; error?: string | null };
}

const props = defineProps<{
    periodKey: string;
    obligation: Obligation | null;
    existing: ExistingReturn | null;
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

const blank = () => ({
    vat_due_sales: '',
    vat_due_acquisitions: '0.00',
    total_vat_due: '',
    vat_reclaimed_curr_period: '',
    net_vat_due: '',
    total_value_sales_ex_vat: '',
    total_value_purchases_ex_vat: '',
    total_value_goods_supplied_ex_vat: '0',
    total_acquisitions_ex_vat: '0',
    attestation: false,
});

const form = ref(blank());
const submitting = ref(false);
const errors = ref<Record<string, string>>({});
const action = useHmrcAction();

const showAttestationDialog = ref(false);
const alreadyFiled = computed(() => props.existing?.submitted_at !== null && props.existing?.submitted_at !== undefined);

const num = (v: string): number => parseFloat(v || '0') || 0;

const computedBox3 = computed(() => num(form.value.vat_due_sales) + num(form.value.vat_due_acquisitions));
const computedBox5 = computed(() => Math.abs(computedBox3.value - num(form.value.vat_reclaimed_curr_period)));

const useComputed = () => {
    form.value.total_vat_due = computedBox3.value.toFixed(2);
    form.value.net_vat_due = computedBox5.value.toFixed(2);
};

const dueBadge = (days: number): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (days <= 7) return 'destructive';
    if (days <= 14) return 'outline';
    return 'secondary';
};

const buildPayload = () => ({
    vat_due_sales: form.value.vat_due_sales,
    vat_due_acquisitions: form.value.vat_due_acquisitions,
    total_vat_due: form.value.total_vat_due,
    vat_reclaimed_curr_period: form.value.vat_reclaimed_curr_period,
    net_vat_due: form.value.net_vat_due,
    total_value_sales_ex_vat: form.value.total_value_sales_ex_vat,
    total_value_purchases_ex_vat: form.value.total_value_purchases_ex_vat,
    total_value_goods_supplied_ex_vat: form.value.total_value_goods_supplied_ex_vat,
    total_acquisitions_ex_vat: form.value.total_acquisitions_ex_vat,
    attestation: form.value.attestation,
});

const handleSubmit = async (event?: Event) => {
    event?.preventDefault();
    if (alreadyFiled.value) return;

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

    await action.refreshFingerprint().catch(() => null);
    if (action.error.value) {
        errors.value = { _: action.error.value };
        submitting.value = false;
        showAttestationDialog.value = false;
        return;
    }

    router.post(`/hmrc/vat/${encodeURIComponent(props.periodKey)}/period`, buildPayload(), {
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

const breadcrumbs = [
    { title: 'HMRC / Tax', href: '/hmrc' },
    { title: 'MTD VAT', href: '/hmrc/vat' },
    { title: 'Submit return' },
];
</script>

<template>
    <Head title="Submit VAT return" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-4xl">
            <div class="flex flex-col gap-2">
                <Button variant="ghost" size="sm" as-child class="self-start">
                    <a href="/hmrc/vat">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to VAT
                    </a>
                </Button>
                <h2 class="text-3xl font-bold flex items-center gap-3">
                    <CalendarClock class="h-8 w-8" />
                    Submit VAT return
                </h2>
                <div v-if="obligation" class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <span>{{ obligation.period_start_date }} – {{ obligation.period_end_date }}</span>
                    <span>·</span>
                    <span>Due {{ obligation.due_date }}</span>
                    <Badge :variant="dueBadge(obligation.days_until_due)">
                        {{ obligation.days_until_due }} days
                    </Badge>
                </div>
                <div class="text-xs text-muted-foreground">
                    Period key <span class="font-mono">{{ periodKey }}</span>
                </div>
            </div>

            <Alert v-if="alreadyFiled" variant="default">
                <AlertCircle class="h-4 w-4" />
                <AlertTitle>Already submitted</AlertTitle>
                <AlertDescription>
                    This period was filed on {{ existing?.submitted_at }}.
                    HMRC reference: <span class="font-mono">{{ existing?.form_bundle_number ?? '—' }}</span>.
                    VAT submissions cannot be amended — make corrections in a future-period adjustment.
                </AlertDescription>
            </Alert>

            <Alert v-else variant="default">
                <AlertCircle class="h-4 w-4" />
                <AlertTitle>VAT submissions are final</AlertTitle>
                <AlertDescription>
                    Submitting will file your 9-box return with HMRC. Once filed, it cannot be amended —
                    corrections must be made in a future-period adjustment.
                </AlertDescription>
            </Alert>

            <Alert v-if="errors._" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertTitle>Couldn't submit</AlertTitle>
                <AlertDescription>{{ errors._ }}</AlertDescription>
            </Alert>

            <form class="flex flex-col gap-6" @submit.prevent="handleSubmit">
                <Card>
                    <CardHeader>
                        <CardTitle>VAT due (boxes 1–5)</CardTitle>
                        <CardDescription>Pounds and pence; HMRC takes 2dp.</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="vat_due_sales">Box 1 — VAT due on sales (£) *</Label>
                            <Input
                                id="vat_due_sales"
                                v-model="form.vat_due_sales"
                                inputmode="decimal"
                                placeholder="0.00"
                                :disabled="submitting || alreadyFiled"
                            />
                            <p v-if="errors.vat_due_sales_pence" class="text-destructive text-sm">
                                {{ errors.vat_due_sales_pence }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="vat_due_acquisitions">Box 2 — VAT due on acquisitions (£) *</Label>
                            <Input
                                id="vat_due_acquisitions"
                                v-model="form.vat_due_acquisitions"
                                inputmode="decimal"
                                placeholder="0.00"
                                :disabled="submitting || alreadyFiled"
                            />
                            <p v-if="errors.vat_due_acquisitions_pence" class="text-destructive text-sm">
                                {{ errors.vat_due_acquisitions_pence }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="total_vat_due">Box 3 — Total VAT due (£) *</Label>
                            <Input
                                id="total_vat_due"
                                v-model="form.total_vat_due"
                                inputmode="decimal"
                                placeholder="0.00"
                                :disabled="submitting || alreadyFiled"
                            />
                            <p class="text-xs text-muted-foreground">
                                Must equal Box 1 + Box 2 (currently £{{ computedBox3.toFixed(2) }}).
                                <button type="button" class="underline" @click="useComputed">Use computed</button>
                            </p>
                            <p v-if="errors.total_vat_due_pence" class="text-destructive text-sm">
                                {{ errors.total_vat_due_pence }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="vat_reclaimed_curr_period">Box 4 — VAT reclaimed on purchases (£) *</Label>
                            <Input
                                id="vat_reclaimed_curr_period"
                                v-model="form.vat_reclaimed_curr_period"
                                inputmode="decimal"
                                placeholder="0.00"
                                :disabled="submitting || alreadyFiled"
                            />
                            <p v-if="errors.vat_reclaimed_curr_period_pence" class="text-destructive text-sm">
                                {{ errors.vat_reclaimed_curr_period_pence }}
                            </p>
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="net_vat_due">Box 5 — Net VAT due (£) *</Label>
                            <Input
                                id="net_vat_due"
                                v-model="form.net_vat_due"
                                inputmode="decimal"
                                placeholder="0.00"
                                :disabled="submitting || alreadyFiled"
                            />
                            <p class="text-xs text-muted-foreground">
                                Must equal abs(Box 3 − Box 4) (currently £{{ computedBox5.toFixed(2) }}). Non-negative.
                                <button type="button" class="underline" @click="useComputed">Use computed</button>
                            </p>
                            <p v-if="errors.net_vat_due_pence" class="text-destructive text-sm">
                                {{ errors.net_vat_due_pence }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Values (boxes 6–9)</CardTitle>
                        <CardDescription>
                            Whole pounds — HMRC discards pence on these boxes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="total_value_sales_ex_vat">Box 6 — Total value of sales ex VAT (£) *</Label>
                            <Input
                                id="total_value_sales_ex_vat"
                                v-model="form.total_value_sales_ex_vat"
                                inputmode="numeric"
                                placeholder="0"
                                :disabled="submitting || alreadyFiled"
                            />
                            <p v-if="errors.total_value_sales_ex_vat_pence" class="text-destructive text-sm">
                                {{ errors.total_value_sales_ex_vat_pence }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="total_value_purchases_ex_vat">Box 7 — Total value of purchases ex VAT (£) *</Label>
                            <Input
                                id="total_value_purchases_ex_vat"
                                v-model="form.total_value_purchases_ex_vat"
                                inputmode="numeric"
                                placeholder="0"
                                :disabled="submitting || alreadyFiled"
                            />
                            <p v-if="errors.total_value_purchases_ex_vat_pence" class="text-destructive text-sm">
                                {{ errors.total_value_purchases_ex_vat_pence }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="total_value_goods_supplied_ex_vat">Box 8 — Goods supplied to EU (£) *</Label>
                            <Input
                                id="total_value_goods_supplied_ex_vat"
                                v-model="form.total_value_goods_supplied_ex_vat"
                                inputmode="numeric"
                                placeholder="0"
                                :disabled="submitting || alreadyFiled"
                            />
                            <p v-if="errors.total_value_goods_supplied_ex_vat_pence" class="text-destructive text-sm">
                                {{ errors.total_value_goods_supplied_ex_vat_pence }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="total_acquisitions_ex_vat">Box 9 — Acquisitions from EU (£) *</Label>
                            <Input
                                id="total_acquisitions_ex_vat"
                                v-model="form.total_acquisitions_ex_vat"
                                inputmode="numeric"
                                placeholder="0"
                                :disabled="submitting || alreadyFiled"
                            />
                            <p v-if="errors.total_acquisitions_ex_vat_pence" class="text-destructive text-sm">
                                {{ errors.total_acquisitions_ex_vat_pence }}
                            </p>
                        </div>
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
                                :disabled="alreadyFiled"
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
                        <a href="/hmrc/vat">Cancel</a>
                    </Button>
                    <Button
                        type="submit"
                        :disabled="submitting || alreadyFiled"
                        class="min-w-[180px]"
                    >
                        <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                        <Save v-else class="mr-2 h-4 w-4" />
                        Submit to HMRC
                    </Button>
                </div>
            </form>
        </div>

        <!-- Confirmation overlay -->
        <div
            v-if="showAttestationDialog"
            class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-sm"
            @click.self="showAttestationDialog = false"
        >
            <Card class="max-w-md mx-4">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <AlertCircle class="h-5 w-5" />
                        Confirm VAT submission
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p class="text-sm">
                        You are about to file your VAT return with HMRC. <strong>This is final</strong> — VAT
                        returns cannot be amended once filed. Corrections must be made in a future-period
                        adjustment.
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
