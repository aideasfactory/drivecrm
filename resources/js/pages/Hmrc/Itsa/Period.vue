<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-vue-next';
import ItsaPeriodPanel from '@/components/Hmrc/Itsa/PeriodPanel.vue';

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

const props = defineProps<{
    businessId: string;
    periodKey: string;
    obligation: Obligation | null;
    existing: ExistingUpdate | null;
    expenseCategories: ExpenseCategory[];
}>();

const isAmend = computed(
    () => props.existing?.submission_id !== null && props.existing?.submission_id !== undefined,
);

const breadcrumbs = [
    { title: 'HMRC / Tax', href: '/hmrc' },
    { title: 'MTD Income Tax', href: '/hmrc/itsa' },
    { title: 'Quarterly update' },
];
</script>

<template>
    <Head :title="isAmend ? 'Amend quarterly update' : 'Submit quarterly update'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-6">
            <Button variant="ghost" size="sm" as-child class="self-start">
                <a href="/hmrc/itsa">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to ITSA
                </a>
            </Button>

            <ItsaPeriodPanel
                :business-id="props.businessId"
                :period-key="props.periodKey"
                :obligation="props.obligation"
                :existing="props.existing"
                :expense-categories="props.expenseCategories"
            />
        </div>
    </AppLayout>
</template>
