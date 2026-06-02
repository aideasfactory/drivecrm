<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import VehiclesIndexPanel from '@/components/Hmrc/Vehicles/IndexPanel.vue';

interface MethodLabel {
    value: string;
    label: string;
}

interface VehicleRow {
    id: number;
    display_name: string;
    registration: string | null;
    engine_size_cc: number | null;
    method: MethodLabel;
    business_use_percentage: number;
    acquired_on: string | null;
    disposed_on: string | null;
    method_locked: boolean;
}

const props = defineProps<{
    vehicles: VehicleRow[];
    methodOptions: MethodLabel[];
}>();

const breadcrumbs = [
    { title: 'HMRC / Tax', href: '/hmrc' },
    { title: 'Vehicles' },
];
</script>

<template>
    <Head title="Vehicles" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <VehiclesIndexPanel :vehicles="props.vehicles" :method-options="props.methodOptions" />
        </div>
    </AppLayout>
</template>
