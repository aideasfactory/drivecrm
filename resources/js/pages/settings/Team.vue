<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Loader2, Palette, Save, RotateCcw } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { toast } from '@/components/ui/sonner';

interface Props {
    team: {
        id: number;
        name: string;
    };
    settings: {
        primary_color: string | null;
        default_slot_duration_minutes: number;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Team settings',
    },
];

const form = useForm({
    primary_color: props.settings.primary_color ?? '',
    default_slot_duration_minutes: props.settings.default_slot_duration_minutes,
});

const colorPreview = ref(form.primary_color || '#000000');

watch(
    () => form.primary_color,
    (val) => {
        if (val && /^#[0-9A-Fa-f]{6}$/.test(val)) {
            colorPreview.value = val;
        }
    },
);

function onColorPickerChange(event: Event) {
    const target = event.target as HTMLInputElement;
    form.primary_color = target.value;
    colorPreview.value = target.value;
}

function handleSubmit() {
    form.put('/settings/team', {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Team settings updated successfully.');
        },
        onError: () => {
            toast.error('Failed to update team settings.');
        },
    });
}

function resetColor() {
    form.primary_color = '';
    colorPreview.value = '#000000';
}

const durationOptions = [
    { label: '30 minutes', value: 30 },
    { label: '1 hour', value: 60 },
    { label: '1.5 hours', value: 90 },
    { label: '2 hours', value: 120 },
    { label: '2.5 hours', value: 150 },
    { label: '3 hours', value: 180 },
    { label: '4 hours', value: 240 },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Team settings" />

        <h1 class="sr-only">Team Settings</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Team settings"
                    :description="`Configure settings for ${team.name}`"
                />

                <form @submit.prevent="handleSubmit" class="space-y-8">
                    <!-- Primary Colour -->
                    <div class="space-y-3">
                        <Label for="primary_color">Primary Colour</Label>
                        <p class="text-sm text-muted-foreground">
                            Set a custom primary colour for your team's
                            branding. Leave empty to use the default theme.
                        </p>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <input
                                    type="color"
                                    :value="colorPreview"
                                    @input="onColorPickerChange"
                                    class="h-10 w-14 cursor-pointer rounded-md border border-input p-1"
                                />
                            </div>
                            <Input
                                id="primary_color"
                                v-model="form.primary_color"
                                placeholder="#FF5733"
                                class="max-w-[140px] font-mono uppercase"
                            />
                            <Button
                                v-if="form.primary_color"
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="resetColor"
                            >
                                <RotateCcw class="mr-1 h-4 w-4" />
                                Reset
                            </Button>
                        </div>
                        <div
                            v-if="
                                form.primary_color &&
                                /^#[0-9A-Fa-f]{6}$/.test(form.primary_color)
                            "
                            class="flex items-center gap-2"
                        >
                            <div
                                class="h-8 w-8 rounded-md border"
                                :style="{
                                    backgroundColor: form.primary_color,
                                }"
                            />
                            <span class="text-sm text-muted-foreground"
                                >Preview</span
                            >
                        </div>
                        <p
                            v-if="form.errors.primary_color"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.primary_color }}
                        </p>
                    </div>

                    <!-- Default Slot Duration -->
                    <div class="space-y-3">
                        <Label for="default_slot_duration_minutes"
                            >Default Time-Slot Duration</Label
                        >
                        <p class="text-sm text-muted-foreground">
                            Set the default duration for new calendar time
                            slots. Instructors can still adjust individual
                            slots.
                        </p>
                        <select
                            id="default_slot_duration_minutes"
                            v-model.number="form.default_slot_duration_minutes"
                            class="file:text-foreground placeholder:text-muted-foreground dark:bg-input/30 border-input h-9 w-full max-w-[200px] rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                        >
                            <option
                                v-for="opt in durationOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.default_slot_duration_minutes"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.default_slot_duration_minutes }}
                        </p>
                    </div>

                    <!-- Submit -->
                    <div class="flex items-center gap-3">
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="min-w-[120px]"
                        >
                            <Loader2
                                v-if="form.processing"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            <Save v-else class="mr-2 h-4 w-4" />
                            Save Changes
                        </Button>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
