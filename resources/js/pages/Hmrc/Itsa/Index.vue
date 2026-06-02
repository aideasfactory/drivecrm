<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ItsaIndexPanel from '@/components/Hmrc/Itsa/IndexPanel.vue';

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

const props = defineProps<{
    connected: boolean;
    enrolmentStatus: EnrolmentStatus;
    businesses: BusinessLite[];
    openObligations: ObligationLite[];
    history: SubmissionRow[];
}>();

const breadcrumbs = [
    { title: 'HMRC / Tax', href: '/hmrc' },
    { title: 'MTD Income Tax' },
];
</script>

<template>
    <Head title="MTD Income Tax" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <ItsaIndexPanel
                :connected="props.connected"
                :enrolment-status="props.enrolmentStatus"
                :businesses="props.businesses"
                :open-obligations="props.openObligations"
                :history="props.history"
            />
        </div>
    </AppLayout>
</template>
