<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import LowAvailabilityController from '@/actions/App/Http/Controllers/Settings/LowAvailabilityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/low-availability-settings';
import { type BreadcrumbItem } from '@/types';

interface Props {
    settings: {
        weeks: number;
        min_slots_per_week: number;
    };
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Low availability settings',
        href: edit().url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Low availability settings" />

        <h1 class="sr-only">Low Availability Settings</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Instructor low availability"
                    description="Active instructors with Stripe connected are flagged as low availability when any one of the upcoming weeks has fewer open diary slots than the minimum below. Weeks run for 7 days starting today."
                />

                <Form
                    v-bind="LowAvailabilityController.update.form()"
                    :options="{
                        preserveScroll: true,
                    }"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="weeks">Weeks to check ahead</Label>
                        <Input
                            id="weeks"
                            name="weeks"
                            type="number"
                            min="1"
                            max="12"
                            class="mt-1 block w-full"
                            :default-value="settings.weeks"
                            required
                        />
                        <InputError :message="errors.weeks" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="min_slots_per_week">Minimum slots added per week</Label>
                        <Input
                            id="min_slots_per_week"
                            name="min_slots_per_week"
                            type="number"
                            min="1"
                            max="100"
                            class="mt-1 block w-full"
                            :default-value="settings.min_slots_per_week"
                            required
                        />
                        <p class="text-sm text-muted-foreground">
                            Set to 1 to flag only instructors with a week that has no availability at all.
                        </p>
                        <InputError :message="errors.min_slots_per_week" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="processing">Save</Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
