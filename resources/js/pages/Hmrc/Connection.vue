<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import HmrcConnectionPanel from '@/components/Hmrc/HmrcConnectionPanel.vue';

interface ConnectionStatus {
    connected: boolean;
    connected_at: string | null;
    expires_at: string | null;
    refresh_expires_at: string | null;
    scopes: string[];
    days_until_refresh_expiry: number | null;
}

interface TaxProfile {
    completed_at: string | null;
    business_type: string | null;
    vat_registered: boolean;
    vrn: string | null;
    utr: string | null;
    nino: string | null;
    companies_house_number: string | null;
}

interface ItsaThreshold {
    date: string;
    income: number;
    label: string;
}

interface Applicability {
    profile_complete: boolean;
    business_type: string | null;
    vat: { applies: boolean; vrn: string | null };
    itsa: { applies: boolean; status: string; thresholds: ItsaThreshold[] };
    corporation_tax: { applies: false; reason: string };
    summary: string;
}

interface BusinessTypeOption {
    value: string;
    label: string;
}

const props = defineProps<{
    environment: string;
    connection: ConnectionStatus;
    helloWorldResponse: Record<string, unknown> | null;
    taxProfile: TaxProfile | null;
    applicability: Applicability | null;
    businessTypes: BusinessTypeOption[];
}>();

const breadcrumbs = [{ title: 'HMRC / Tax' }];
</script>

<template>
    <Head title="HMRC / Tax" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <HmrcConnectionPanel
                :environment="props.environment"
                :connection="props.connection"
                :hello-world-response="props.helloWorldResponse"
                :tax-profile="props.taxProfile"
                :applicability="props.applicability"
                :business-types="props.businessTypes"
            />
        </div>
    </AppLayout>
</template>
