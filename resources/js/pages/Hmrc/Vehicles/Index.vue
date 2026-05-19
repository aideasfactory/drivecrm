<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { toast } from '@/components/ui/sonner';
import { Car, Plus, AlertCircle } from 'lucide-vue-next';
import VehicleSheet from '@/components/Hmrc/Vehicles/Sheet.vue';

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

interface PageProps {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

const props = defineProps<{
    vehicles: VehicleRow[];
    methodOptions: MethodLabel[];
}>();

const page = usePage<PageProps>();
watch(
    () => page.props.flash?.success,
    (v) => {
        if (v) toast.success(v);
    },
    { immediate: true },
);
watch(
    () => page.props.flash?.error,
    (v) => {
        if (v) toast.error(v);
    },
    { immediate: true },
);

const sheetOpen = ref(false);
const editingVehicle = ref<VehicleRow | null>(null);

const activeVehicles = computed(() => props.vehicles.filter((v) => !v.disposed_on));
const disposedVehicles = computed(() => props.vehicles.filter((v) => v.disposed_on));

const formatDate = (iso: string | null): string => (iso ? new Date(iso).toLocaleDateString() : '—');

const openCreateSheet = () => {
    editingVehicle.value = null;
    sheetOpen.value = true;
};

const openEditSheet = (vehicle: VehicleRow) => {
    editingVehicle.value = vehicle;
    sheetOpen.value = true;
};

const onSheetClose = (saved: boolean) => {
    sheetOpen.value = false;
    editingVehicle.value = null;
    if (saved) {
        router.reload({ only: ['vehicles'] });
    }
};

const disposeVehicle = (vehicle: VehicleRow) => {
    if (!confirm(`Mark "${vehicle.display_name}" as disposed? Historical rows stay attached; the vehicle disappears from active pickers.`)) {
        return;
    }
    router.delete(`/hmrc/vehicles/${vehicle.id}`, { preserveScroll: true });
};

const breadcrumbs = [
    { title: 'HMRC / Tax', href: '/hmrc' },
    { title: 'Vehicles' },
];
</script>

<template>
    <Head title="Vehicles" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-2">
                    <h2 class="text-3xl font-bold flex items-center gap-3">
                        <Car class="h-8 w-8" />
                        Vehicles
                    </h2>
                    <p class="text-muted-foreground">
                        Your tuition vehicles drive how DRIVE calculates car / van / travel expenses for ITSA quarterlies.
                    </p>
                </div>
                <Button @click="openCreateSheet" class="cursor-pointer">
                    <Plus class="mr-2 h-4 w-4" />
                    Add vehicle
                </Button>
            </div>

            <Alert v-if="vehicles.length === 0">
                <AlertCircle class="h-4 w-4" />
                <AlertTitle>No vehicles yet</AlertTitle>
                <AlertDescription>
                    Before you can submit an ITSA quarterly, add the vehicle you use for tuition.
                    Existing fuel, insurance and MOT rows will be linked to it automatically.
                </AlertDescription>
            </Alert>

            <Card v-if="activeVehicles.length > 0">
                <CardHeader>
                    <CardTitle>Active vehicles</CardTitle>
                    <CardDescription>
                        The method choice is per-vehicle and locked for the life of that vehicle once a quarterly has been
                        submitted. Soft-lock only — switching after lock is possible with a warning.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Registration</TableHead>
                                <TableHead>Method</TableHead>
                                <TableHead>Acquired</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="vehicle in activeVehicles" :key="vehicle.id">
                                <TableCell class="font-medium">{{ vehicle.display_name }}</TableCell>
                                <TableCell>{{ vehicle.registration || '—' }}</TableCell>
                                <TableCell>
                                    <Badge :variant="vehicle.method.value === 'simplified' ? 'secondary' : 'default'">
                                        {{ vehicle.method.label }}
                                    </Badge>
                                    <Badge v-if="vehicle.method_locked" variant="outline" class="ml-2">
                                        Locked
                                    </Badge>
                                </TableCell>
                                <TableCell>{{ formatDate(vehicle.acquired_on) }}</TableCell>
                                <TableCell class="text-right">
                                    <Button variant="ghost" size="sm" @click="openEditSheet(vehicle)" class="cursor-pointer">
                                        Edit
                                    </Button>
                                    <Button variant="ghost" size="sm" @click="disposeVehicle(vehicle)" class="cursor-pointer text-destructive">
                                        Dispose
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Card v-if="disposedVehicles.length > 0">
                <CardHeader>
                    <CardTitle>Disposed vehicles</CardTitle>
                    <CardDescription>
                        These vehicles are no longer in active service. Their historical finance and mileage rows stay attached
                        for HMRC retention.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Registration</TableHead>
                                <TableHead>Method</TableHead>
                                <TableHead>Disposed</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="vehicle in disposedVehicles" :key="vehicle.id">
                                <TableCell class="font-medium">{{ vehicle.display_name }}</TableCell>
                                <TableCell>{{ vehicle.registration || '—' }}</TableCell>
                                <TableCell>{{ vehicle.method.label }}</TableCell>
                                <TableCell>{{ formatDate(vehicle.disposed_on) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <VehicleSheet
                :open="sheetOpen"
                :vehicle="editingVehicle"
                :method-options="methodOptions"
                @close="onSheetClose"
            />
        </div>
    </AppLayout>
</template>
