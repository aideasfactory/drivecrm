<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import axios from 'axios';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { toast } from '@/components/ui/sonner';
import { Loader2, ShieldCheck } from 'lucide-vue-next';

interface InsuranceRow {
    id: number;
    date: string;
    description: string;
    amount_pence: number;
}

interface Props {
    rows: InsuranceRow[];
    redirectTo?: string;
}

interface Emits {
    (e: 'reviewed', updated: number): void;
    (e: 'skipped'): void;
}

const props = withDefaults(defineProps<Props>(), {
    redirectTo: '/hmrc/vehicles',
});
const emit = defineEmits<Emits>();

const saving = ref(false);

type Target = 'vehicle_insurance' | 'business_insurance' | null;
const decisions = reactive<Record<number, Target>>({});
props.rows.forEach((row) => {
    decisions[row.id] = null;
});

const hasDecisions = computed(() => Object.values(decisions).some((v) => v !== null));

const formatDate = (iso: string): string => new Date(iso).toLocaleDateString();
const formatGbp = (pence: number): string =>
    (pence / 100).toLocaleString(undefined, { style: 'currency', currency: 'GBP' });

const setAll = (target: Exclude<Target, null>) => {
    props.rows.forEach((row) => {
        decisions[row.id] = target;
    });
};

const handleSubmit = async () => {
    saving.value = true;
    const payload = Object.entries(decisions)
        .filter(([, v]) => v !== null)
        .map(([id, target]) => ({
            finance_row_id: Number(id),
            target_category: target as string,
        }));

    if (payload.length === 0) {
        toast.error('No rows selected. Tag at least one row before saving.');
        saving.value = false;
        return;
    }

    try {
        await axios.post('/hmrc/vehicles/review-insurance', { decisions: payload });
        toast.success(`${payload.length} insurance rows updated.`);
        emit('reviewed', payload.length);
    } catch (error: any) {
        toast.error(error.response?.data?.message ?? 'Could not save changes.');
    } finally {
        saving.value = false;
    }
};

const handleSkip = () => {
    emit('skipped');
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <ShieldCheck class="h-5 w-5" />
                Review your insurance entries
            </CardTitle>
            <CardDescription>
                HMRC needs vehicle insurance and business / public-liability insurance reported under different buckets.
                We have not auto-categorised these — defaulting either way would miscategorise some rows. Skipped rows
                are excluded from HMRC payloads until reviewed.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div class="mb-4 flex flex-wrap gap-2">
                <Button variant="outline" size="sm" @click="setAll('vehicle_insurance')" class="cursor-pointer">
                    Mark all as vehicle insurance
                </Button>
                <Button variant="outline" size="sm" @click="setAll('business_insurance')" class="cursor-pointer">
                    Mark all as business insurance
                </Button>
            </div>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                        <TableHead>Category</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="row in rows" :key="row.id">
                        <TableCell>{{ formatDate(row.date) }}</TableCell>
                        <TableCell>{{ row.description }}</TableCell>
                        <TableCell class="text-right">{{ formatGbp(row.amount_pence) }}</TableCell>
                        <TableCell>
                            <div class="flex flex-wrap gap-2">
                                <label class="flex items-center gap-1 text-sm">
                                    <input
                                        type="radio"
                                        :value="'vehicle_insurance'"
                                        v-model="decisions[row.id]"
                                    />
                                    Vehicle
                                </label>
                                <label class="flex items-center gap-1 text-sm">
                                    <input
                                        type="radio"
                                        :value="'business_insurance'"
                                        v-model="decisions[row.id]"
                                    />
                                    Business
                                </label>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div class="mt-6 flex items-center justify-between">
                <Button variant="ghost" @click="handleSkip" :disabled="saving" class="cursor-pointer">
                    Skip — I'll do this later
                </Button>
                <Button @click="handleSubmit" :disabled="saving || !hasDecisions" class="cursor-pointer min-w-[140px]">
                    <Loader2 v-if="saving" class="mr-2 h-4 w-4 animate-spin" />
                    {{ saving ? 'Saving...' : 'Save tags' }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
