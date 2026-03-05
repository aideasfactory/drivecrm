<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { toast } from '@/components/ui/sonner';
import { Upload, Loader2, Save, FileUp, Link } from 'lucide-vue-next';
import TagInput from '@/components/Resources/TagInput.vue';

type ResourceType = 'file' | 'video_link';

const props = defineProps<{
    open: boolean;
    folderId: number;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'uploaded'): void;
}>();

const isSubmitting = ref(false);
const uploadProgress = ref(0);
const errors = ref<Record<string, string>>({});
const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const resourceType = ref<ResourceType>('file');
const form = ref({
    title: '',
    description: '',
    tags: [] as string[],
    video_url: '',
});

watch(
    () => props.open,
    (val) => {
        if (val) {
            form.value = { title: '', description: '', tags: [], video_url: '' };
            selectedFile.value = null;
            uploadProgress.value = 0;
            errors.value = {};
            resourceType.value = 'file';
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        }
    },
);

const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    selectedFile.value = file;

    if (file && !form.value.title) {
        form.value.title = file.name.replace(/\.[^/.]+$/, '');
    }
};

const handleSubmit = async () => {
    if (resourceType.value === 'file' && !selectedFile.value) {
        errors.value = { file: 'Please select a file to upload.' };
        return;
    }

    if (resourceType.value === 'video_link' && !form.value.video_url) {
        errors.value = { video_url: 'Please enter a video URL.' };
        return;
    }

    errors.value = {};
    isSubmitting.value = true;
    uploadProgress.value = 0;

    const formData = new FormData();
    formData.append('resource_type', resourceType.value);
    formData.append('title', form.value.title);
    formData.append('resource_folder_id', String(props.folderId));

    if (form.value.description) {
        formData.append('description', form.value.description);
    }
    form.value.tags.forEach((tag, i) => {
        formData.append(`tags[${i}]`, tag);
    });

    if (resourceType.value === 'video_link') {
        formData.append('video_url', form.value.video_url);
    } else {
        formData.append('file', selectedFile.value!);
    }

    try {
        await axios.post('/resources/files', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (progressEvent) => {
                if (resourceType.value === 'file' && progressEvent.total) {
                    uploadProgress.value = Math.round(
                        (progressEvent.loaded * 100) / progressEvent.total,
                    );
                }
            },
        });
        toast.success(
            resourceType.value === 'video_link'
                ? 'Video link added successfully'
                : 'File uploaded successfully',
        );
        emit('update:open', false);
        emit('uploaded');
    } catch (error: any) {
        if (error.response?.status === 422) {
            errors.value = Object.fromEntries(
                Object.entries(error.response.data.errors).map(
                    ([key, val]) => [key, (val as string[])[0]],
                ),
            );
        } else {
            toast.error(
                error.response?.data?.message || 'Failed to create resource',
            );
        }
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Sheet
        :open="open"
        @update:open="emit('update:open', $event)"
    >
        <SheetContent side="right" class="sm:max-w-lg">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <Upload class="h-5 w-5" />
                    Upload Resource
                </SheetTitle>
                <SheetDescription>
                    Upload a file or add a video link to this folder.
                </SheetDescription>
            </SheetHeader>

            <form
                class="mt-6 space-y-6 px-6 py-4"
                @submit.prevent="handleSubmit"
            >
                <!-- Resource Type Selector -->
                <div class="space-y-2">
                    <Label>Resource Type *</Label>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            :variant="resourceType === 'file' ? 'default' : 'outline'"
                            class="flex-1"
                            :disabled="isSubmitting"
                            @click="resourceType = 'file'"
                        >
                            <FileUp class="mr-2 h-4 w-4" />
                            File
                        </Button>
                        <Button
                            type="button"
                            :variant="resourceType === 'video_link' ? 'default' : 'outline'"
                            class="flex-1"
                            :disabled="isSubmitting"
                            @click="resourceType = 'video_link'"
                        >
                            <Link class="mr-2 h-4 w-4" />
                            Video Link
                        </Button>
                    </div>
                </div>

                <!-- File Input (only for file type) -->
                <div v-if="resourceType === 'file'" class="space-y-2">
                    <Label for="resource_file">File *</Label>
                    <input
                        id="resource_file"
                        ref="fileInput"
                        type="file"
                        accept="video/mp4,video/webm,video/quicktime,video/x-msvideo,video/x-matroska,application/pdf"
                        :disabled="isSubmitting"
                        class="border-input bg-background file:text-foreground placeholder:text-muted-foreground flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:cursor-not-allowed disabled:opacity-50"
                        @change="handleFileChange"
                    />
                    <p class="text-muted-foreground text-xs">
                        Supported: MP4, WebM, MOV, AVI, MKV, PDF. Max 500MB.
                    </p>
                    <p
                        v-if="errors.file"
                        class="text-destructive text-sm"
                    >
                        {{ errors.file }}
                    </p>
                </div>

                <!-- Video URL Input (only for video_link type) -->
                <div v-if="resourceType === 'video_link'" class="space-y-2">
                    <Label for="resource_video_url">Video URL *</Label>
                    <Input
                        id="resource_video_url"
                        v-model="form.video_url"
                        placeholder="https://www.youtube.com/watch?v=... or https://vimeo.com/..."
                        :disabled="isSubmitting"
                    />
                    <p class="text-muted-foreground text-xs">
                        Paste a YouTube or Vimeo video URL.
                    </p>
                    <p
                        v-if="errors.video_url"
                        class="text-destructive text-sm"
                    >
                        {{ errors.video_url }}
                    </p>
                </div>

                <!-- Title -->
                <div class="space-y-2">
                    <Label for="resource_title">Title *</Label>
                    <Input
                        id="resource_title"
                        v-model="form.title"
                        placeholder="e.g. Turning right at roundabout"
                        :disabled="isSubmitting"
                    />
                    <p
                        v-if="errors.title"
                        class="text-destructive text-sm"
                    >
                        {{ errors.title }}
                    </p>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="resource_description">Description</Label>
                    <textarea
                        id="resource_description"
                        v-model="form.description"
                        rows="3"
                        placeholder="Describe the content of this resource..."
                        :disabled="isSubmitting"
                        class="border-input bg-background placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <p
                        v-if="errors.description"
                        class="text-destructive text-sm"
                    >
                        {{ errors.description }}
                    </p>
                </div>

                <!-- Tags -->
                <div class="space-y-2">
                    <Label>Tags</Label>
                    <TagInput
                        v-model="form.tags"
                        :disabled="isSubmitting"
                        placeholder="Type a tag and press Enter"
                    />
                    <p class="text-muted-foreground text-xs">
                        Tags help with searching and AI recommendations.
                    </p>
                </div>

                <!-- Upload Progress (only for file uploads) -->
                <div
                    v-if="resourceType === 'file' && isSubmitting && uploadProgress > 0"
                    class="space-y-1"
                >
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">Uploading...</span>
                        <span class="font-medium"
                            >{{ uploadProgress }}%</span
                        >
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
                        {{ resourceType === 'video_link' ? 'Save' : 'Upload' }}
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
