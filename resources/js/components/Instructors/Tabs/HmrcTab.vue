<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-vue-next';
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

interface HmrcData {
    environment: string;
    connection: ConnectionStatus;
    helloWorldResponse: Record<string, unknown> | null;
    taxProfile: TaxProfile | null;
    applicability: Applicability | null;
    businessTypes: BusinessTypeOption[];
}

const props = defineProps<{
    instructorId: number;
    hmrc: HmrcData;
}>();

const goBack = () => {
    router.visit(`/instructors/${props.instructorId}`, {
        data: { tab: 'schedule' },
        preserveState: false,
    });
};
</script>

<template>
    <div class="flex flex-col gap-6">
        <div>
            <Button variant="outline" @click="goBack">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back to instructor
            </Button>
        </div>

        <HmrcConnectionPanel
            :environment="props.hmrc.environment"
            :connection="props.hmrc.connection"
            :hello-world-response="props.hmrc.helloWorldResponse"
            :tax-profile="props.hmrc.taxProfile"
            :applicability="props.hmrc.applicability"
            :business-types="props.hmrc.businessTypes"
        />
    </div>
</template>
