<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
import { toast } from '@/components/ui/sonner';
import { useHmrcAction } from '@/composables/useHmrcAction';
import { AlertCircle, ArrowLeft, Calculator, CheckCircle2, FileSignature, Loader2 } from 'lucide-vue-next';

interface CalculationView {
    id: number;
    calculation_id: string;
    status: string;
    status_label: string;
    triggered_at: string;
    processed_at: string | null;
    summary_payload: Record<string, unknown> | null;
    error_payload: Record<string, unknown> | null;
}

interface PageProps {
    flash?: { success?: string | null; error?: string | null };
}

const props = defineProps<{
    taxYear: string;
    calculation: CalculationView;
    finalDeclarationSubmitted: boolean;
}>();

const page = usePage<PageProps>();
const action = useHmrcAction();

const live = ref<CalculationView>({ ...props.calculation });
const polling = ref(false);
const submitting = ref(false);
const showAttestation = ref(false);
const attestation = ref(false);
let pollHandle: number | null = null;

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

const formatDateTime = (iso: string | null): string => (iso ? new Date(iso).toLocaleString() : '—');

const totalLiability = computed<number | null>(() => {
    const summary = live.value.summary_payload;
    if (!summary || typeof summary !== 'object') return null;
    const calc = (summary as Record<string, unknown>).taxCalculation;
    if (!calc || typeof calc !== 'object') return null;
    const total = (calc as Record<string, unknown>).totalIncomeTaxAndNicsDue;
    return typeof total === 'number' ? total : null;
});

const refreshFromHmrc = async () => {
    polling.value = true;
    try {
        const response = await axios.get(
            `/hmrc/itsa/final-declaration/${props.taxYear}/calculation/${live.value.id}/poll`,
        );
        live.value = { ...live.value, ...response.data };
        if (live.value.status !== 'pending' && pollHandle) {
            clearInterval(pollHandle);
            pollHandle = null;
        }
    } catch (e: unknown) {
        const err = e as { response?: { data?: { error?: string } } };
        toast.error(err.response?.data?.error ?? 'Failed to refresh calculation');
    } finally {
        polling.value = false;
    }
};

onMounted(() => {
    if (live.value.status === 'pending') {
        pollHandle = window.setInterval(refreshFromHmrc, 5000);
    }
});

onUnmounted(() => {
    if (pollHandle) clearInterval(pollHandle);
});

const submitFinalDeclaration = async () => {
    if (!attestation.value) {
        toast.error('Tick the digital-records attestation before submitting.');
        return;
    }
    submitting.value = true;
    await action.refreshFingerprint().catch(() => null);
    if (action.error.value) {
        toast.error(action.error.value);
        submitting.value = false;
        return;
    }
    router.post(
        `/hmrc/itsa/final-declaration/${props.taxYear}/submit/${live.value.id}`,
        { attestation: true },
        {
            preserveScroll: true,
            onFinish: () => {
                submitting.value = false;
                showAttestation.value = false;
            },
        },
    );
};

const goBack = () => {
    router.visit(`/hmrc/itsa/final-declaration/${props.taxYear}`);
};

const breadcrumbs = [
    { title: 'HMRC / Tax', href: '/hmrc' },
    { title: 'MTD Income Tax', href: '/hmrc/itsa' },
    { title: `Final declaration ${props.taxYear}`, href: `/hmrc/itsa/final-declaration/${props.taxYear}` },
    { title: 'Calculation' },
];
</script>

<template>
    <Head :title="`Calculation review — ${taxYear}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-3xl">
            <Button variant="ghost" size="sm" class="self-start" @click="goBack">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back
            </Button>

            <div class="flex flex-col gap-2">
                <h2 class="text-3xl font-bold flex items-center gap-3">
                    <Calculator class="h-8 w-8" />
                    Tax calculation review
                </h2>
                <p class="text-muted-foreground">Tax year {{ taxYear }}</p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        Calculation status
                        <Badge :variant="live.status === 'processed' ? 'default' : live.status === 'errored' ? 'destructive' : 'secondary'">
                            {{ live.status_label }}
                        </Badge>
                    </CardTitle>
                    <CardDescription>
                        Triggered {{ formatDateTime(live.triggered_at) }}
                        <span v-if="live.processed_at"> • processed {{ formatDateTime(live.processed_at) }}</span>
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <Alert v-if="live.status === 'pending'" variant="default">
                        <Loader2 class="h-4 w-4 animate-spin" />
                        <AlertTitle>HMRC is still calculating</AlertTitle>
                        <AlertDescription>
                            We refresh every 5 seconds. You can close this page and return later — it will pick up
                            where it left off.
                        </AlertDescription>
                    </Alert>
                    <Alert v-else-if="live.status === 'errored'" variant="destructive">
                        <AlertCircle class="h-4 w-4" />
                        <AlertTitle>HMRC reported an error</AlertTitle>
                        <AlertDescription>
                            <pre class="text-xs whitespace-pre-wrap">{{ JSON.stringify(live.error_payload, null, 2) }}</pre>
                        </AlertDescription>
                    </Alert>
                    <Alert v-else variant="default">
                        <CheckCircle2 class="h-4 w-4" />
                        <AlertTitle>Calculation ready</AlertTitle>
                        <AlertDescription v-if="totalLiability !== null">
                            Total income tax and NICs due:
                            <strong>{{ totalLiability.toLocaleString(undefined, { style: 'currency', currency: 'GBP' }) }}</strong>
                        </AlertDescription>
                        <AlertDescription v-else>
                            HMRC returned the calculation but the total isn't in the expected shape — review the
                            full payload below before submitting.
                        </AlertDescription>
                    </Alert>

                    <Button variant="outline" :disabled="polling" @click="refreshFromHmrc">
                        <Loader2 v-if="polling" class="mr-2 h-4 w-4 animate-spin" />
                        Refresh from HMRC
                    </Button>
                </CardContent>
            </Card>

            <Card v-if="live.status === 'processed' && live.summary_payload">
                <CardHeader>
                    <CardTitle>Calculation breakdown</CardTitle>
                    <CardDescription>Full payload returned by HMRC</CardDescription>
                </CardHeader>
                <CardContent>
                    <pre class="text-xs bg-muted p-3 rounded overflow-auto max-h-96">{{ JSON.stringify(live.summary_payload, null, 2) }}</pre>
                </CardContent>
            </Card>

            <Card v-if="live.status === 'processed' && !finalDeclarationSubmitted">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileSignature class="h-5 w-5" />
                        Submit final declaration
                    </CardTitle>
                    <CardDescription>
                        This finalises your tax return for {{ taxYear }}. After submission you can no longer amend
                        the quarterly updates for this year.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div v-if="!showAttestation">
                        <Button @click="showAttestation = true">
                            <FileSignature class="mr-2 h-4 w-4" />
                            Submit final declaration
                        </Button>
                    </div>
                    <div v-else class="space-y-4 rounded-md border p-4 bg-muted/30">
                        <p class="font-medium">Confirm before submitting:</p>
                        <div class="flex items-start gap-2">
                            <Checkbox id="attestation" v-model:checked="attestation" />
                            <Label for="attestation" class="text-sm font-normal">
                                I confirm these figures are derived from digital business records that I keep in line
                                with MTD requirements.
                            </Label>
                        </div>
                        <div class="flex gap-2">
                            <Button variant="outline" @click="showAttestation = false" :disabled="submitting">
                                Cancel
                            </Button>
                            <Button :disabled="!attestation || submitting" @click="submitFinalDeclaration">
                                <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                                Confirm &amp; submit
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Alert v-if="finalDeclarationSubmitted" variant="default">
                <CheckCircle2 class="h-4 w-4" />
                <AlertTitle>Final declaration already submitted</AlertTitle>
                <AlertDescription>
                    The final declaration for {{ taxYear }} has been filed. No further action required.
                </AlertDescription>
            </Alert>
        </div>
    </AppLayout>
</template>
