<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { toast } from '@/components/ui/sonner';
import { useHmrcAction } from '@/composables/useHmrcAction';
import { ArrowLeft, FileSignature, Loader2, Save } from 'lucide-vue-next';

interface ExistingRow {
    payload: Record<string, unknown> | null;
    submitted_at: string | null;
    submission_id: string | null;
}

interface PageProps {
    flash?: { success?: string | null; error?: string | null };
    errors?: Record<string, string>;
}

const props = defineProps<{
    taxYear: string;
    type: string;
    label: string;
    fields: string[];
    existing: ExistingRow | null;
}>();

const page = usePage<PageProps>();
const action = useHmrcAction();

const formatDateTime = (iso: string | null): string => (iso ? new Date(iso).toLocaleString() : '—');

const initialFormFor = (type: string): Record<string, string> => {
    switch (type) {
        case 'reliefs':
            return {
                pension_contributions: '',
                one_off_pension_contributions: '',
                charitable_giving: '',
            };
        case 'disclosures':
            return {
                marriage_allowance_recipient_nino: '',
                marriage_allowance_start_date: '',
            };
        case 'savings':
            return { uk_interest: '', foreign_interest: '' };
        case 'dividends':
            return { uk_dividends: '', other_uk_dividends: '' };
        case 'individual_details':
            return {
                first_name: '',
                last_name: '',
                address_line_1: '',
                address_line_2: '',
                postcode: '',
                marital_status: 'single',
            };
        default:
            return {};
    }
};

const form = ref<Record<string, string>>(initialFormFor(props.type));
const submitting = ref(false);
const errors = ref<Record<string, string>>({});

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

const handleSubmit = async () => {
    submitting.value = true;
    errors.value = {};

    await action.refreshFingerprint().catch(() => null);
    if (action.error.value) {
        toast.error(action.error.value);
        submitting.value = false;
        return;
    }

    router.post(`/hmrc/itsa/final-declaration/${props.taxYear}/step/${props.type}`, { ...form.value }, {
        preserveScroll: true,
        onError: (e) => {
            errors.value = e as Record<string, string>;
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
};

const goBack = () => {
    router.visit(`/hmrc/itsa/final-declaration/${props.taxYear}`);
};

const breadcrumbs = [
    { title: 'HMRC / Tax', href: '/hmrc' },
    { title: 'MTD Income Tax', href: '/hmrc/itsa' },
    { title: `Final declaration ${props.taxYear}`, href: `/hmrc/itsa/final-declaration/${props.taxYear}` },
    { title: props.label },
];
</script>

<template>
    <Head :title="`${label} — ${taxYear}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-3xl">
            <Button variant="ghost" size="sm" class="self-start" @click="goBack">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back
            </Button>

            <div class="flex flex-col gap-2">
                <h2 class="text-3xl font-bold flex items-center gap-3">
                    <FileSignature class="h-8 w-8" />
                    {{ label }}
                </h2>
                <p class="text-muted-foreground">Tax year {{ taxYear }}</p>
            </div>

            <Alert v-if="existing?.submitted_at" variant="default">
                <AlertTitle>Previously saved</AlertTitle>
                <AlertDescription>
                    Last submitted {{ formatDateTime(existing.submitted_at) }}.
                    Resubmitting will overwrite HMRC's record for this year.
                </AlertDescription>
            </Alert>

            <Card>
                <CardHeader>
                    <CardTitle>{{ label }}</CardTitle>
                    <CardDescription>
                        Enter the values you want to declare. Leave fields blank if they don't apply to you.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="handleSubmit" class="space-y-4">
                        <!-- Reliefs -->
                        <template v-if="type === 'reliefs'">
                            <div class="space-y-2">
                                <Label for="pension_contributions">Regular pension contributions (£)</Label>
                                <Input id="pension_contributions" v-model="form.pension_contributions" placeholder="0.00" />
                                <p v-if="errors.pension_contributions_pence" class="text-sm text-destructive">
                                    {{ errors.pension_contributions_pence }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="one_off_pension_contributions">One-off pension contributions (£)</Label>
                                <Input id="one_off_pension_contributions" v-model="form.one_off_pension_contributions" placeholder="0.00" />
                            </div>
                            <div class="space-y-2">
                                <Label for="charitable_giving">Charitable giving (£)</Label>
                                <Input id="charitable_giving" v-model="form.charitable_giving" placeholder="0.00" />
                            </div>
                        </template>

                        <!-- Disclosures -->
                        <template v-if="type === 'disclosures'">
                            <div class="space-y-2">
                                <Label for="marriage_allowance_recipient_nino">Marriage Allowance — recipient NINO</Label>
                                <Input id="marriage_allowance_recipient_nino" v-model="form.marriage_allowance_recipient_nino" placeholder="QQ123456C" />
                                <p v-if="errors.marriage_allowance_recipient_nino" class="text-sm text-destructive">
                                    {{ errors.marriage_allowance_recipient_nino }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="marriage_allowance_start_date">Start date</Label>
                                <Input id="marriage_allowance_start_date" type="date" v-model="form.marriage_allowance_start_date" />
                            </div>
                        </template>

                        <!-- Savings -->
                        <template v-if="type === 'savings'">
                            <div class="space-y-2">
                                <Label for="uk_interest">UK savings interest (£)</Label>
                                <Input id="uk_interest" v-model="form.uk_interest" placeholder="0.00" />
                            </div>
                            <div class="space-y-2">
                                <Label for="foreign_interest">Foreign savings interest (£)</Label>
                                <Input id="foreign_interest" v-model="form.foreign_interest" placeholder="0.00" />
                            </div>
                        </template>

                        <!-- Dividends -->
                        <template v-if="type === 'dividends'">
                            <div class="space-y-2">
                                <Label for="uk_dividends">UK dividends (£)</Label>
                                <Input id="uk_dividends" v-model="form.uk_dividends" placeholder="0.00" />
                            </div>
                            <div class="space-y-2">
                                <Label for="other_uk_dividends">Other UK dividends (£)</Label>
                                <Input id="other_uk_dividends" v-model="form.other_uk_dividends" placeholder="0.00" />
                            </div>
                        </template>

                        <!-- Individual details -->
                        <template v-if="type === 'individual_details'">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="first_name">First name</Label>
                                    <Input id="first_name" v-model="form.first_name" />
                                    <p v-if="errors.first_name" class="text-sm text-destructive">{{ errors.first_name }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="last_name">Last name</Label>
                                    <Input id="last_name" v-model="form.last_name" />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label for="address_line_1">Address line 1</Label>
                                <Input id="address_line_1" v-model="form.address_line_1" />
                            </div>
                            <div class="space-y-2">
                                <Label for="address_line_2">Address line 2</Label>
                                <Input id="address_line_2" v-model="form.address_line_2" />
                            </div>
                            <div class="space-y-2">
                                <Label for="postcode">Postcode</Label>
                                <Input id="postcode" v-model="form.postcode" />
                            </div>
                            <div class="space-y-2">
                                <Label for="marital_status">Marital status</Label>
                                <select
                                    id="marital_status"
                                    v-model="form.marital_status"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                                >
                                    <option value="single">Single</option>
                                    <option value="married">Married</option>
                                    <option value="civil_partnership">Civil partnership</option>
                                    <option value="divorced">Divorced</option>
                                    <option value="widowed">Widowed</option>
                                </select>
                            </div>
                        </template>

                        <Button type="submit" :disabled="submitting" class="min-w-[140px]">
                            <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                            <Save v-else class="mr-2 h-4 w-4" />
                            Save
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
