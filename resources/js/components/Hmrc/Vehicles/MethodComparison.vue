<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Loader2, BadgeInfo } from 'lucide-vue-next';

interface Props {
    vehicleId: number;
    selectedMethod?: string;
}

interface ComparisonPayload {
    window: { start: string; end: string };
    simplified_pence: number;
    actual_pence: number;
    business_miles: number;
    vehicle_running_costs_pence: number;
    business_use_percentage: number;
}

const props = defineProps<Props>();

const loading = ref(false);
const error = ref<string | null>(null);
const data = ref<ComparisonPayload | null>(null);

// Scenario A baseline (28k miles, ~£6,135 running × 95%) from hmrc-tax-categories-client-summary.md §6
// Used when the instructor has effectively no data yet (under a month of mileage).
const FALLBACK = {
    label: 'Typical full-time instructor — your figures will replace this once you have a few months of records.',
    simplified_pence: 9_000_00,
    actual_pence: 5_828_00,
    business_miles: 28_000,
    vehicle_running_costs_pence: 6_135_00,
    business_use_percentage: 95,
};

const fetchComparison = async () => {
    loading.value = true;
    error.value = null;
    try {
        const response = await axios.get<ComparisonPayload>(`/hmrc/vehicles/${props.vehicleId}/compare`);
        data.value = response.data;
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? 'Could not load comparison.';
    } finally {
        loading.value = false;
    }
};

watch(() => props.vehicleId, fetchComparison, { immediate: true });

const usingFallback = computed(() => !data.value || data.value.business_miles < 200);

const view = computed(() => {
    if (usingFallback.value) {
        return { ...FALLBACK, fallback: true };
    }
    return { ...data.value!, fallback: false, label: 'Based on your last 12 months of records.' };
});

const winner = computed<'simplified' | 'actual' | null>(() => {
    const v = view.value;
    if (v.simplified_pence === v.actual_pence) return null;
    return v.simplified_pence > v.actual_pence ? 'simplified' : 'actual';
});

const formatGbp = (pence: number): string =>
    (pence / 100).toLocaleString(undefined, { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 });
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center justify-between gap-2 text-base">
                <span class="flex items-center gap-2">
                    <BadgeInfo class="h-4 w-4" />
                    Method comparison
                </span>
                <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
            </CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <p class="text-xs text-muted-foreground">{{ view.label }}</p>

            <div class="grid grid-cols-2 gap-3">
                <div
                    class="rounded-md border p-3"
                    :class="{
                        'border-primary': selectedMethod === 'simplified',
                        'bg-accent': winner === 'simplified',
                    }"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-medium">Simplified</span>
                        <Badge v-if="winner === 'simplified'" variant="default">Suggested</Badge>
                    </div>
                    <div class="mt-1 text-2xl font-bold">{{ formatGbp(view.simplified_pence) }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ view.business_miles.toLocaleString() }} business miles × HMRC rates
                    </div>
                </div>

                <div
                    class="rounded-md border p-3"
                    :class="{
                        'border-primary': selectedMethod === 'actual',
                        'bg-accent': winner === 'actual',
                    }"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-medium">Advanced</span>
                        <Badge v-if="winner === 'actual'" variant="default">Suggested</Badge>
                    </div>
                    <div class="mt-1 text-2xl font-bold">{{ formatGbp(view.actual_pence) }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ formatGbp(view.vehicle_running_costs_pence) }} running × {{ view.business_use_percentage }}% business use
                    </div>
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                This is a tax decision worth checking with your accountant. Simplified usually wins for instructors who keep
                cars for 5+ years and avoids years of receipt-keeping.
            </p>

            <p v-if="error" class="text-xs text-destructive">{{ error }}</p>
        </CardContent>
    </Card>
</template>
