<script setup lang="ts">
import { reactive, ref } from 'vue';
import axios from 'axios';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { toast } from '@/components/ui/sonner';
import { Car, Loader2, Info } from 'lucide-vue-next';

interface MethodOption {
    value: string;
    label: string;
}

interface Props {
    methodOptions: MethodOption[];
    redirectTo?: string;
}

const props = withDefaults(defineProps<Props>(), {
    redirectTo: '/hmrc/vehicles',
});

const saving = ref(false);
const errors = reactive<Record<string, string>>({});

const form = reactive({
    display_name: 'My tuition car',
    registration: '',
    engine_size_cc: '' as string | number,
    method: 'simplified',
    acquired_on: new Date().toISOString().slice(0, 10),
});

const handleSubmit = async () => {
    saving.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    try {
        await axios.post('/hmrc/vehicles/backfill-primary', {
            display_name: form.display_name,
            registration: form.registration || null,
            engine_size_cc: form.engine_size_cc === '' ? null : Number(form.engine_size_cc),
            method: form.method,
            acquired_on: form.acquired_on,
        });
        toast.success('Vehicle saved. Your existing expense and mileage rows have been linked.');
        window.location.href = props.redirectTo;
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
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Car class="h-5 w-5" />
                Set up your tuition vehicle
            </CardTitle>
            <CardDescription>
                Before we can submit an ITSA quarterly, we need to know which vehicle the fuel, insurance and mileage
                you have already logged belong to. Set it up once — we will link everything to it for you.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <Alert class="mb-4">
                <Info class="h-4 w-4" />
                <AlertTitle>What happens when you save</AlertTitle>
                <AlertDescription>
                    Every existing fuel, vehicle-insurance, MOT and mileage entry will be tagged to this vehicle in a
                    single transaction. Non-vehicle expenses (advertising, accountant fees, etc.) stay untouched.
                </AlertDescription>
            </Alert>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="bf_display_name">Display name</Label>
                    <Input id="bf_display_name" v-model="form.display_name" type="text" />
                    <p v-if="errors.display_name" class="text-sm text-destructive">{{ errors.display_name }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="bf_registration">Registration (optional)</Label>
                        <Input id="bf_registration" v-model="form.registration" type="text" />
                        <p v-if="errors.registration" class="text-sm text-destructive">{{ errors.registration }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="bf_acquired_on">Acquired on</Label>
                        <Input id="bf_acquired_on" v-model="form.acquired_on" type="date" />
                        <p v-if="errors.acquired_on" class="text-sm text-destructive">{{ errors.acquired_on }}</p>
                    </div>
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
                            <input type="radio" :value="option.value" v-model="form.method" class="h-4 w-4" />
                            <span class="text-sm font-medium">{{ option.label }}</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <Button type="submit" :disabled="saving" class="cursor-pointer min-w-[140px]">
                        <Loader2 v-if="saving" class="mr-2 h-4 w-4 animate-spin" />
                        {{ saving ? 'Saving...' : 'Set up vehicle' }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
