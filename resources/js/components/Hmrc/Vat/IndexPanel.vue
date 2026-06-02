<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useHmrcAction } from '@/composables/useHmrcAction';
import {
    AlertCircle,
    CalendarClock,
    CheckCircle2,
    Info,
    Loader2,
    RefreshCw,
    Wallet,
} from 'lucide-vue-next';

interface Obligation {
    period_key: string;
    period_start_date: string;
    period_end_date: string;
    due_date: string;
    status: string;
    days_until_due: number;
}

interface ReturnHistoryItem {
    id: number;
    period_key: string;
    submitted_at: string | null;
    form_bundle_number: string | null;
    charge_ref_number: string | null;
    payment_indicator: string | null;
    correlation_id: string | null;
    total_vat_due: number;
    net_vat_due: number;
}

defineProps<{
    connected: boolean;
    eligible: boolean;
    hasVatScope: boolean;
    vrn: string | null;
    openObligations: Obligation[];
    history: ReturnHistoryItem[];
}>();

const action = useHmrcAction();
const syncing = ref(false);

// Inline feedback chip — shown next to the sync button after the action
// completes, auto-clears after 5 seconds.
const syncFeedback = ref<{ kind: 'success' | 'error'; message: string } | null>(null);

const showFeedback = (kind: 'success' | 'error', message: string) => {
    syncFeedback.value = { kind, message };
    setTimeout(() => {
        if (syncFeedback.value?.message === message) {
            syncFeedback.value = null;
        }
    }, 5000);
};

const handleSync = async () => {
    syncing.value = true;
    await action.refreshFingerprint().catch(() => null);
    if (action.error.value) {
        showFeedback('error', action.error.value);
        syncing.value = false;
        return;
    }
    router.post(
        '/hmrc/vat/sync-obligations',
        {},
        {
            preserveScroll: true,
            onSuccess: () => showFeedback('success', 'VAT obligations refreshed from HMRC.'),
            onError: () => showFeedback('error', 'Could not sync VAT obligations. Try again or check the HMRC connection.'),
            onFinish: () => {
                syncing.value = false;
            },
        },
    );
};

const dueBadge = (days: number): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (days <= 7) return 'destructive';
    if (days <= 14) return 'outline';
    return 'secondary';
};

const formatDate = (iso: string | null): string => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
};
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <h2 class="text-3xl font-bold flex items-center gap-3">
                <Wallet class="h-8 w-8" />
                MTD VAT
            </h2>
            <p class="text-muted-foreground">
                Submit your quarterly 9-box VAT return directly to HMRC.
            </p>
            <div v-if="vrn" class="text-sm text-muted-foreground">
                VRN <span class="font-mono">{{ vrn }}</span>
            </div>
        </div>

        <Alert v-if="!connected" variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <AlertTitle>Not connected to HMRC</AlertTitle>
            <AlertDescription>
                <a href="/hmrc" class="underline">Connect to HMRC</a> before submitting a VAT return.
            </AlertDescription>
        </Alert>

        <Alert v-else-if="!eligible" variant="default">
            <Info class="h-4 w-4" />
            <AlertTitle>Not VAT-registered</AlertTitle>
            <AlertDescription>
                Your tax profile says you are not VAT-registered. If that's wrong,
                <a href="/hmrc" class="underline">update your tax profile</a> and add your VRN.
            </AlertDescription>
        </Alert>

        <Alert v-else-if="!hasVatScope" variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <AlertTitle>VAT permissions not granted</AlertTitle>
            <AlertDescription>
                Your HMRC connection doesn't currently include VAT permissions. Reconnect to grant them —
                your existing Income Tax permissions will be preserved.
                <div class="mt-3">
                    <Button as-child size="sm">
                        <a href="/hmrc/connect">Reconnect to HMRC</a>
                    </Button>
                </div>
            </AlertDescription>
        </Alert>

        <template v-else>
            <Alert variant="default">
                <Info class="h-4 w-4" />
                <AlertTitle>VAT submissions are final</AlertTitle>
                <AlertDescription>
                    Once submitted, a VAT return cannot be amended at HMRC. Corrections must be made in a
                    future-period adjustment, not by editing the row.
                </AlertDescription>
            </Alert>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <CalendarClock class="h-5 w-5" />
                        Open obligations
                    </CardTitle>
                    <CardDescription>
                        VAT periods HMRC currently expects a return for.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <Button :disabled="syncing" variant="outline" @click="handleSync">
                            <Loader2 v-if="syncing" class="mr-2 h-4 w-4 animate-spin" />
                            <RefreshCw v-else class="mr-2 h-4 w-4" />
                            Refresh from HMRC
                        </Button>
                        <span
                            v-if="syncFeedback"
                            class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium"
                            :class="syncFeedback.kind === 'success'
                                ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-400'
                                : 'border-destructive/30 bg-destructive/10 text-destructive'"
                        >
                            <CheckCircle2 v-if="syncFeedback.kind === 'success'" class="h-3.5 w-3.5" />
                            <AlertCircle v-else class="h-3.5 w-3.5" />
                            {{ syncFeedback.message }}
                        </span>
                    </div>

                    <p v-if="openObligations.length === 0" class="text-sm text-muted-foreground">
                        No open obligations on file. If that's surprising, refresh from HMRC.
                    </p>

                    <div v-else class="rounded-md border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/40 text-left">
                                <tr>
                                    <th class="p-2">Period</th>
                                    <th class="p-2">Due</th>
                                    <th class="p-2">Days left</th>
                                    <th class="p-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="o in openObligations" :key="o.period_key" class="border-t">
                                    <td class="p-2">{{ o.period_start_date }} – {{ o.period_end_date }}</td>
                                    <td class="p-2">{{ o.due_date }}</td>
                                    <td class="p-2">
                                        <Badge :variant="dueBadge(o.days_until_due)">
                                            {{ o.days_until_due }} days
                                        </Badge>
                                    </td>
                                    <td class="p-2 text-right">
                                        <Button as-child size="sm">
                                            <a :href="`/hmrc/vat/${encodeURIComponent(o.period_key)}/period`">
                                                Submit return
                                            </a>
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Submission history</CardTitle>
                    <CardDescription>
                        Permanent audit record of every VAT return filed through DRIVE (6-year retention).
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p v-if="history.length === 0" class="text-sm text-muted-foreground">
                        No submissions yet.
                    </p>
                    <div v-else class="rounded-md border overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/40 text-left">
                                <tr>
                                    <th class="p-2">Period key</th>
                                    <th class="p-2">Submitted</th>
                                    <th class="p-2">Form bundle</th>
                                    <th class="p-2 text-right">Net VAT due</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in history" :key="r.id" class="border-t">
                                    <td class="p-2 font-mono">{{ r.period_key }}</td>
                                    <td class="p-2">{{ formatDate(r.submitted_at) }}</td>
                                    <td class="p-2 font-mono text-xs">{{ r.form_bundle_number ?? '—' }}</td>
                                    <td class="p-2 text-right">£{{ r.net_vat_due.toFixed(2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </template>
    </div>
</template>
