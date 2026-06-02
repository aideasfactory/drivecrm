<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-vue-next';
import HmrcConnectionPanel from '@/components/Hmrc/HmrcConnectionPanel.vue';
import ItsaIndexPanel from '@/components/Hmrc/Itsa/IndexPanel.vue';
import ItsaPeriodPanel from '@/components/Hmrc/Itsa/PeriodPanel.vue';
import VatIndexPanel from '@/components/Hmrc/Vat/IndexPanel.vue';
import VehiclesIndexPanel from '@/components/Hmrc/Vehicles/IndexPanel.vue';
import ArchiveIndexPanel from '@/components/Hmrc/Archive/IndexPanel.vue';

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
    vehicles: { required: boolean; configured: boolean; active_count: number };
    summary: string;
}

interface BusinessTypeOption {
    value: string;
    label: string;
}

interface MethodOption {
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
    methodOptions: MethodOption[];
}

interface HmrcServicePayload {
    name: 'itsa' | 'vat' | 'vehicles' | 'archive' | string;
    view?: 'index' | 'period';
    data: Record<string, unknown> | null;
}

const props = defineProps<{
    instructorId: number;
    hmrc: HmrcData;
    hmrcService?: HmrcServicePayload | null;
}>();

const goBackToInstructor = () => {
    router.visit(`/instructors/${props.instructorId}`, {
        data: { tab: 'schedule' },
        preserveState: false,
    });
};

const goBackToHmrc = () => {
    router.visit(`/instructors/${props.instructorId}`, {
        data: { tab: 'hmrc' },
        preserveState: false,
    });
};

const goBackToItsa = () => {
    router.visit(`/instructors/${props.instructorId}`, {
        data: { tab: 'hmrc', service: 'itsa' },
        preserveState: false,
    });
};

const serviceName = computed(() => props.hmrcService?.name ?? null);
const serviceView = computed(() => props.hmrcService?.view ?? 'index');
const serviceData = computed(() => props.hmrcService?.data ?? null);

const isItsaPeriodView = computed(
    () => serviceName.value === 'itsa' && serviceView.value === 'period',
);
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex items-center gap-2">
            <Button variant="outline" @click="goBackToInstructor">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back to instructor
            </Button>
            <!-- "Back to HMRC" is shown on every service view EXCEPT the ITSA
                 period detail, which gets its own "Back to ITSA" instead. -->
            <Button v-if="serviceName && !isItsaPeriodView" variant="outline" @click="goBackToHmrc">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back to HMRC
            </Button>
            <Button v-if="isItsaPeriodView" variant="outline" @click="goBackToItsa">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back to ITSA
            </Button>
        </div>

        <!-- HMRC overview (no service selected) -->
        <HmrcConnectionPanel
            v-if="!serviceName"
            :environment="props.hmrc.environment"
            :connection="props.hmrc.connection"
            :hello-world-response="props.hmrc.helloWorldResponse"
            :tax-profile="props.hmrc.taxProfile"
            :applicability="props.hmrc.applicability"
            :business-types="props.hmrc.businessTypes"
            :method-options="props.hmrc.methodOptions"
            :instructor-id="props.instructorId"
        />

        <!-- ITSA period detail (form view) -->
        <ItsaPeriodPanel
            v-else-if="isItsaPeriodView && serviceData"
            v-bind="serviceData as any"
            :instructor-id="props.instructorId"
        />

        <!-- Service index panels rendered inline -->
        <ItsaIndexPanel
            v-else-if="serviceName === 'itsa' && serviceData"
            v-bind="serviceData as any"
            :instructor-id="props.instructorId"
        />
        <VatIndexPanel
            v-else-if="serviceName === 'vat' && serviceData"
            v-bind="serviceData as any"
        />
        <VehiclesIndexPanel
            v-else-if="serviceName === 'vehicles' && serviceData"
            v-bind="serviceData as any"
        />
        <ArchiveIndexPanel
            v-else-if="serviceName === 'archive' && serviceData"
            v-bind="serviceData as any"
        />
    </div>
</template>
