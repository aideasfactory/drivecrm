<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { toast } from '@/components/ui/sonner';
import { Loader2, Save, TrafficCone, Wand2 } from 'lucide-vue-next';

export interface ScoringZoneItem {
    hazard_number: number;
    score: number;
    start_seconds: number | string;
    end_seconds: number | string;
}

export interface HazardPerceptionVideoItem {
    id: number;
    title: string;
    description: string | null;
    category: string;
    topic: string;
    video_url: string;
    duration_seconds: number;
    is_double_hazard: boolean;
    thumbnail_url: string | null;
    has_recap: boolean;
    recap_video_url: string | null;
    attempts_count?: number;
    scoring_zones: ScoringZoneItem[];
}

interface ZoneInput {
    score: number;
    start_seconds: string;
    end_seconds: string;
}

const props = defineProps<{
    open: boolean;
    video: HazardPerceptionVideoItem | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const CATEGORIES = ['Car', 'ADI', 'Motorcycle', 'LGV-PCV'];
const SCORES = [5, 4, 3, 2, 1];

const isSubmitting = ref(false);
const uploadProgress = ref(0);
const errors = ref<Record<string, string>>({});

const videoInput = ref<HTMLInputElement | null>(null);
const thumbnailInput = ref<HTMLInputElement | null>(null);
const recapInput = ref<HTMLInputElement | null>(null);
const videoFile = ref<File | null>(null);
const thumbnailFile = ref<File | null>(null);
const recapFile = ref<File | null>(null);

const form = ref({
    title: '',
    description: '',
    category: 'Car',
    topic: '',
    duration_seconds: '',
    is_double_hazard: false,
    has_recap: false,
});

const hazardStartQuickFill = ref<Record<number, string>>({ 1: '', 2: '' });

const emptyZones = (): ZoneInput[] =>
    SCORES.map((score) => ({ score, start_seconds: '', end_seconds: '' }));

const zones = ref<Record<number, ZoneInput[]>>({ 1: emptyZones(), 2: emptyZones() });

const isEditing = computed(() => props.video !== null);
const activeHazards = computed(() => (form.value.is_double_hazard ? [1, 2] : [1]));

watch(
    () => props.open,
    (open) => {
        if (!open) return;

        errors.value = {};
        uploadProgress.value = 0;
        videoFile.value = null;
        thumbnailFile.value = null;
        recapFile.value = null;
        hazardStartQuickFill.value = { 1: '', 2: '' };
        [videoInput, thumbnailInput, recapInput].forEach((input) => {
            if (input.value) input.value.value = '';
        });

        if (props.video) {
            form.value = {
                title: props.video.title,
                description: props.video.description ?? '',
                category: props.video.category,
                topic: props.video.topic,
                duration_seconds: String(props.video.duration_seconds),
                is_double_hazard: props.video.is_double_hazard,
                has_recap: props.video.has_recap,
            };
            zones.value = { 1: emptyZones(), 2: emptyZones() };
            for (const zone of props.video.scoring_zones) {
                const row = zones.value[zone.hazard_number]?.find(
                    (z) => z.score === zone.score,
                );
                if (row) {
                    row.start_seconds = String(parseFloat(String(zone.start_seconds)));
                    row.end_seconds = String(parseFloat(String(zone.end_seconds)));
                }
            }
        } else {
            form.value = {
                title: '',
                description: '',
                category: 'Car',
                topic: '',
                duration_seconds: '',
                is_double_hazard: false,
                has_recap: false,
            };
            zones.value = { 1: emptyZones(), 2: emptyZones() };
        }
    },
);

const handleFileChange = (target: 'video' | 'thumbnail' | 'recap', e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    if (target === 'video') {
        videoFile.value = file;
        if (file && !form.value.title) {
            form.value.title = file.name.replace(/\.[^/.]+$/, '');
        }
    } else if (target === 'thumbnail') {
        thumbnailFile.value = file;
    } else {
        recapFile.value = file;
    }
};

/**
 * Fill 5 contiguous 1-second blocks from the given start time
 * (5 points first), matching the DVSA-style timing sheets.
 */
const quickFillZones = (hazardNumber: number) => {
    const start = parseFloat(hazardStartQuickFill.value[hazardNumber]);
    if (isNaN(start)) {
        toast.error('Enter the hazard start time (seconds) first');
        return;
    }
    zones.value[hazardNumber] = SCORES.map((score, index) => ({
        score,
        start_seconds: (start + index).toFixed(2),
        end_seconds: (start + index + 1).toFixed(2),
    }));
};

const handleSubmit = async () => {
    if (!isEditing.value && !videoFile.value) {
        errors.value = { video: 'Please select a video file to upload.' };
        return;
    }

    errors.value = {};
    isSubmitting.value = true;
    uploadProgress.value = 0;

    const formData = new FormData();
    formData.append('title', form.value.title);
    formData.append('category', form.value.category);
    formData.append('topic', form.value.topic);
    formData.append('duration_seconds', form.value.duration_seconds);
    formData.append('is_double_hazard', form.value.is_double_hazard ? '1' : '0');
    formData.append('has_recap', form.value.has_recap ? '1' : '0');
    if (form.value.description) {
        formData.append('description', form.value.description);
    }
    if (videoFile.value) {
        formData.append('video', videoFile.value);
    }
    if (thumbnailFile.value) {
        formData.append('thumbnail', thumbnailFile.value);
    }
    if (recapFile.value) {
        formData.append('recap_video', recapFile.value);
    }

    let zoneIndex = 0;
    for (const hazardNumber of activeHazards.value) {
        for (const zone of zones.value[hazardNumber]) {
            formData.append(`zones[${zoneIndex}][hazard_number]`, String(hazardNumber));
            formData.append(`zones[${zoneIndex}][score]`, String(zone.score));
            formData.append(`zones[${zoneIndex}][start_seconds]`, zone.start_seconds);
            formData.append(`zones[${zoneIndex}][end_seconds]`, zone.end_seconds);
            zoneIndex++;
        }
    }

    const url = isEditing.value
        ? `/hazard-perception/videos/${props.video!.id}`
        : '/hazard-perception/videos';
    if (isEditing.value) {
        formData.append('_method', 'PUT');
    }

    try {
        await axios.post(url, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (progressEvent) => {
                if (progressEvent.total) {
                    uploadProgress.value = Math.round(
                        (progressEvent.loaded * 100) / progressEvent.total,
                    );
                }
            },
        });
        toast.success(
            isEditing.value
                ? 'Video updated successfully'
                : 'Video uploaded successfully',
        );
        emit('update:open', false);
        emit('saved');
    } catch (error: any) {
        if (error.response?.status === 422) {
            errors.value = Object.fromEntries(
                Object.entries(error.response.data.errors).map(([key, val]) => [
                    key,
                    (val as string[])[0],
                ]),
            );
            toast.error('Please fix the highlighted errors');
        } else {
            toast.error(error.response?.data?.message || 'Failed to save video');
        }
    } finally {
        isSubmitting.value = false;
    }
};

const zoneError = computed(() => {
    if (errors.value.zones) return errors.value.zones;
    const key = Object.keys(errors.value).find((k) => k.startsWith('zones.'));
    return key ? errors.value[key] : null;
});
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent side="right" class="flex flex-col overflow-hidden sm:max-w-xl">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <TrafficCone class="h-5 w-5" />
                    {{ isEditing ? 'Edit Hazard Clip' : 'Upload Hazard Clip' }}
                </SheetTitle>
                <SheetDescription>
                    {{
                        isEditing
                            ? 'Update the clip details, files, and scoring zones.'
                            : 'Upload a hazard perception clip to the bucket and define its scoring zones.'
                    }}
                </SheetDescription>
            </SheetHeader>

            <form
                class="mt-6 flex-1 space-y-6 overflow-y-auto px-6 py-4"
                @submit.prevent="handleSubmit"
            >
                <!-- Video file -->
                <div class="space-y-2">
                    <Label for="hp_video_file">
                        Video File {{ isEditing ? '' : '*' }}
                    </Label>
                    <input
                        id="hp_video_file"
                        ref="videoInput"
                        type="file"
                        accept="video/mp4,video/webm,video/quicktime"
                        :disabled="isSubmitting"
                        class="border-input bg-background file:text-foreground placeholder:text-muted-foreground flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:cursor-not-allowed disabled:opacity-50"
                        @change="handleFileChange('video', $event)"
                    />
                    <p class="text-muted-foreground text-xs">
                        MP4, WebM, or MOV. Max 500MB.
                        {{ isEditing ? 'Leave empty to keep the current video.' : '' }}
                    </p>
                    <p v-if="errors.video" class="text-destructive text-sm">
                        {{ errors.video }}
                    </p>
                </div>

                <!-- Title -->
                <div class="space-y-2">
                    <Label for="hp_title">Title *</Label>
                    <Input
                        id="hp_title"
                        v-model="form.title"
                        placeholder="e.g. Junction approach with pedestrian"
                        :disabled="isSubmitting"
                    />
                    <p v-if="errors.title" class="text-destructive text-sm">
                        {{ errors.title }}
                    </p>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="hp_description">Description</Label>
                    <textarea
                        id="hp_description"
                        v-model="form.description"
                        rows="3"
                        placeholder="Describe the clip scenario..."
                        :disabled="isSubmitting"
                        class="border-input bg-background placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <p v-if="errors.description" class="text-destructive text-sm">
                        {{ errors.description }}
                    </p>
                </div>

                <!-- Category -->
                <div class="space-y-2">
                    <Label>Category *</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <Button
                            v-for="category in CATEGORIES"
                            :key="category"
                            type="button"
                            :variant="form.category === category ? 'default' : 'outline'"
                            :disabled="isSubmitting"
                            @click="form.category = category"
                        >
                            {{ category }}
                        </Button>
                    </div>
                    <p v-if="errors.category" class="text-destructive text-sm">
                        {{ errors.category }}
                    </p>
                </div>

                <!-- Topic + Duration -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="hp_topic">Topic *</Label>
                        <Input
                            id="hp_topic"
                            v-model="form.topic"
                            placeholder="e.g. Junctions"
                            :disabled="isSubmitting"
                        />
                        <p v-if="errors.topic" class="text-destructive text-sm">
                            {{ errors.topic }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="hp_duration">Duration (seconds) *</Label>
                        <Input
                            id="hp_duration"
                            v-model="form.duration_seconds"
                            type="number"
                            min="1"
                            placeholder="60"
                            :disabled="isSubmitting"
                        />
                        <p
                            v-if="errors.duration_seconds"
                            class="text-destructive text-sm"
                        >
                            {{ errors.duration_seconds }}
                        </p>
                    </div>
                </div>

                <!-- Flags -->
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="hp_double_hazard"
                            v-model="form.is_double_hazard"
                            :disabled="isSubmitting"
                        />
                        <Label for="hp_double_hazard" class="cursor-pointer">
                            Double hazard clip (two scored hazards, max 10 points)
                        </Label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="hp_has_recap"
                            v-model="form.has_recap"
                            :disabled="isSubmitting"
                        />
                        <Label for="hp_has_recap" class="cursor-pointer">
                            This clip has a recap — the app skips the recap step when unchecked
                        </Label>
                    </div>
                </div>

                <!-- Recap video (only when has_recap) -->
                <div v-if="form.has_recap" class="space-y-2">
                    <Label for="hp_recap_file">Recap Video</Label>
                    <input
                        id="hp_recap_file"
                        ref="recapInput"
                        type="file"
                        accept="video/mp4,video/webm,video/quicktime"
                        :disabled="isSubmitting"
                        class="border-input bg-background file:text-foreground placeholder:text-muted-foreground flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:cursor-not-allowed disabled:opacity-50"
                        @change="handleFileChange('recap', $event)"
                    />
                    <p class="text-muted-foreground text-xs">
                        Optional explainer video shown after the clip is completed.
                        {{ isEditing && video?.recap_video_url ? 'Leave empty to keep the current recap video.' : '' }}
                    </p>
                    <p v-if="errors.recap_video" class="text-destructive text-sm">
                        {{ errors.recap_video }}
                    </p>
                </div>

                <!-- Thumbnail -->
                <div class="space-y-2">
                    <Label for="hp_thumbnail_file">Thumbnail</Label>
                    <input
                        id="hp_thumbnail_file"
                        ref="thumbnailInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        :disabled="isSubmitting"
                        class="border-input bg-background file:text-foreground placeholder:text-muted-foreground flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:cursor-not-allowed disabled:opacity-50"
                        @change="handleFileChange('thumbnail', $event)"
                    />
                    <p class="text-muted-foreground text-xs">
                        Optional. JPG, PNG, or WebP. Max 10MB.
                    </p>
                    <p v-if="errors.thumbnail" class="text-destructive text-sm">
                        {{ errors.thumbnail }}
                    </p>
                </div>

                <!-- Scoring zones -->
                <div
                    v-for="hazardNumber in activeHazards"
                    :key="hazardNumber"
                    class="space-y-3 rounded-md border p-4"
                >
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold">
                            Hazard {{ hazardNumber }} Scoring Zones *
                        </h4>
                    </div>
                    <div class="flex items-end gap-2">
                        <div class="flex-1 space-y-1">
                            <Label
                                :for="`hp_quickfill_${hazardNumber}`"
                                class="text-muted-foreground text-xs"
                            >
                                Quick fill: hazard start (seconds)
                            </Label>
                            <Input
                                :id="`hp_quickfill_${hazardNumber}`"
                                v-model="hazardStartQuickFill[hazardNumber]"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="e.g. 18.76"
                                :disabled="isSubmitting"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="isSubmitting"
                            @click="quickFillZones(hazardNumber)"
                        >
                            <Wand2 class="mr-2 h-4 w-4" />
                            Fill 1s Blocks
                        </Button>
                    </div>
                    <div class="space-y-2">
                        <div
                            class="text-muted-foreground grid grid-cols-[70px_1fr_1fr] items-center gap-2 text-xs font-medium"
                        >
                            <span>Score</span>
                            <span>Start (s)</span>
                            <span>End (s)</span>
                        </div>
                        <div
                            v-for="zone in zones[hazardNumber]"
                            :key="zone.score"
                            class="grid grid-cols-[70px_1fr_1fr] items-center gap-2"
                        >
                            <span class="text-sm font-medium">
                                {{ zone.score }} {{ zone.score === 1 ? 'point' : 'points' }}
                            </span>
                            <Input
                                v-model="zone.start_seconds"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                :disabled="isSubmitting"
                            />
                            <Input
                                v-model="zone.end_seconds"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                :disabled="isSubmitting"
                            />
                        </div>
                    </div>
                </div>
                <p v-if="zoneError" class="text-destructive text-sm">
                    {{ zoneError }}
                </p>

                <!-- Upload progress -->
                <div v-if="isSubmitting && uploadProgress > 0" class="space-y-1">
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">Uploading...</span>
                        <span class="font-medium">{{ uploadProgress }}%</span>
                    </div>
                    <div class="bg-secondary h-2 overflow-hidden rounded-full">
                        <div
                            class="bg-primary h-full transition-all duration-300"
                            :style="{ width: `${uploadProgress}%` }"
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="isSubmitting"
                        @click="emit('update:open', false)"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="isSubmitting"
                        class="min-w-[140px]"
                    >
                        <Loader2
                            v-if="isSubmitting"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Save v-else class="mr-2 h-4 w-4" />
                        {{ isEditing ? 'Save Changes' : 'Upload' }}
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
