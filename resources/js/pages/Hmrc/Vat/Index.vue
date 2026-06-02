<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import VatIndexPanel from '@/components/Hmrc/Vat/IndexPanel.vue';

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

const props = defineProps<{
    connected: boolean;
    eligible: boolean;
    hasVatScope: boolean;
    vrn: string | null;
    openObligations: Obligation[];
    history: ReturnHistoryItem[];
}>();

const breadcrumbs = [
    { title: 'HMRC / Tax', href: '/hmrc' },
    { title: 'MTD VAT' },
];
</script>

<template>
    <Head title="MTD VAT" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <VatIndexPanel
                :connected="props.connected"
                :eligible="props.eligible"
                :has-vat-scope="props.hasVatScope"
                :vrn="props.vrn"
                :open-obligations="props.openObligations"
                :history="props.history"
            />
        </div>
    </AppLayout>
</template>
