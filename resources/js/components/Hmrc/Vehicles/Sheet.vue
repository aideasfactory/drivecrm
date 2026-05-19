<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import axios from 'axios';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/ui/sonner';
import { Car, Loader2, Save } from 'lucide-vue-next';
import MethodComparison from '@/components/Hmrc/Vehicles/MethodComparison.vue';

interface MethodOption {
    value: string;
    label: string;
}

interface VehicleRow {
    id: number;
    display_name: string;
    registration: string | null;
    engine_size_cc: number | null;
    method: { value: string; label: string };
    business_use_percentage: number;
    acquired_on: string | null;
    disposed_on: string | null;
    method_locked: boolean;
}

interface Props {
    open: boolean;
    vehicle: VehicleRow | null;
    methodOptions: MethodOption[];
}

interface Emits {
    (e: 'close', saved: boolean): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const saving = ref(false);

const form = reactive({
    display_name: '',
    registration: '',
    engine_size_cc: '' as string | number,
    method: 'simplified',
    acquired_on: '',
});

const errors = reactive<Record<string, string>>({});

const isEdit = computed(() => props.vehicle !== null);

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        Object.keys(errors).forEach((k) => delete errors[k]);
        if (props.vehicle) {
            form.display_name = props.vehicle.display_name;
            form.registration = props.vehicle.registration ?? '';
            form.engine_size_cc = props.vehicle.engine_size_cc ?? '';
            form.method = props.vehicle.method.value;
            form.acquired_on = props.vehicle.acquired_on ?? '';
        } else {
            form.display_name = 'My tuition car';
            form.registration = '';
            form.engine_size_cc = '';
            form.method = 'simplified';
            form.acquired_on = new Date().toISOString().slice(0, 10);
        }
    },
);

const submitMethodChange = async () => {
    if (!props.vehicle) return false;
    if (props.vehicle.method.value === form.method) return true;

    try {
        const response = await axios.post(`/hmrc/vehicles/${props.vehicle.id}/switch-method`, {
            method: form.method,
            confirmed: false,
        });

        if (response.data.status === 'requires_confirmation') {
            if (!confirm(response.data.message + '\n\nProceed?')) {
                return false;
            }
            await axios.post(`/hmrc/vehicles/${props.vehicle.id}/switch-method`, {
                method: form.method,
                confirmed: true,
            });
        }
        return true;
    } catch (error: any) {
        toast.error(error?.response?.data?.message ?? 'Could not switch method.');
        return false;
    }
};

const handleSave = async () => {
    saving.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    const payload: Record<string, unknown> = {
        display_name: form.display_name,
        registration: form.registration || null,
        engine_size_cc: form.engine_size_cc === '' ? null : Number(form.engine_size_cc),
        acquired_on: form.acquired_on,
    };

    if (!isEdit.value) {
        payload.method = form.method;
    }

    try {
        if (isEdit.value && props.vehicle) {
            await axios.put(`/hmrc/vehicles/${props.vehicle.id}`, payload);
            const methodOk = await submitMethodChange();
            if (!methodOk) {
                saving.value = false;
                return;
            }
            toast.success('Vehicle updated.');
        } else {
            await axios.post('/hmrc/vehicles', payload);
            toast.success('Vehicle added.');
        }
        emit('close', true);
    } catch (error: any) {
        if (error.response?.status === 422) {
            const validationErrors = error.response.data.errors || {};
            Object.entries(validationErrors).forEach(([key, msgs]) => {
                errors[key] = (msgs as string[])[0];
            });
        } else {
            toast.error(error.response?.data?.message ?? 'Could not save vehicle.');
        }
    } finally {
        saving.value = false;
    }
};

const handleOpenChange = (value: boolean) => {
    if (saving.value) return;
    if (!value) emit('close', false);
};
</script>

<template>
    <Sheet :open="open" @update:open="handleOpenChange">
        <SheetContent class="overflow-y-auto sm:max-w-2xl">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <Car class="h-5 w-5" />
                    {{ isEdit ? 'Edit vehicle' : 'Add vehicle' }}
                </SheetTitle>
                <SheetDescription>
                    The method choice (Simplified or Advanced) is per-vehicle and effectively for the life of the vehicle.
                    Simplified covers all running costs through a flat-rate per business mile; Advanced uses your actual
                    fuel / insurance / MOT receipts plus a business-use percentage.
                </SheetDescription>
            </SheetHeader>

            <form @submit.prevent="handleSave" class="mt-6 space-y-6 px-6 py-4">
                <div class="space-y-2">
                    <Label for="display_name">Display name</Label>
                    <Input id="display_name" v-model="form.display_name" type="text" />
                    <p v-if="errors.display_name" class="text-sm text-destructive">{{ errors.display_name }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="registration">Registration</Label>
                        <Input id="registration" v-model="form.registration" type="text" placeholder="AB12 CDE" />
                        <p v-if="errors.registration" class="text-sm text-destructive">{{ errors.registration }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="engine_size_cc">Engine size (cc)</Label>
                        <Input id="engine_size_cc" v-model="form.engine_size_cc" type="number" min="0" />
                        <p v-if="errors.engine_size_cc" class="text-sm text-destructive">{{ errors.engine_size_cc }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="acquired_on">Acquired on</Label>
                    <Input id="acquired_on" v-model="form.acquired_on" type="date" />
                    <p v-if="errors.acquired_on" class="text-sm text-destructive">{{ errors.acquired_on }}</p>
                </div>

                <div class="space-y-2">
                    <Label>Method</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <label
                            v-for="option in methodOptions"
                            :key="option.value"
                            class="flex cursor-pointer items-center gap-3 rounded-md border p-3 hover:bg-accent"
                            :class="{ 'border-primary bg-accent': form.method === option.value }"
                        >
                            <input
                                type="radio"
                                :value="option.value"
                                v-model="form.method"
                                class="h-4 w-4"
                            />
                            <span class="text-sm font-medium">{{ option.label }}</span>
                        </label>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Simplified covers fuel, insurance, MOT, servicing, repairs, road tax and breakdown cover through the
                        flat-rate mileage allowance. Advanced sums those receipts and applies your business-use percentage.
                    </p>
                </div>

                <MethodComparison
                    v-if="isEdit && props.vehicle"
                    :vehicle-id="props.vehicle.id"
                    :selected-method="form.method"
                />

                <div class="flex justify-end gap-3 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="handleOpenChange(false)"
                        :disabled="saving"
                        class="cursor-pointer"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="saving" class="cursor-pointer min-w-[140px]">
                        <Loader2 v-if="saving" class="mr-2 h-4 w-4 animate-spin" />
                        <Save v-else class="mr-2 h-4 w-4" />
                        {{ saving ? 'Saving...' : isEdit ? 'Save changes' : 'Add vehicle' }}
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
