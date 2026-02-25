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
import { Upload, Loader2, Save } from 'lucide-vue-next';
import TagInput from '@/components/Resources/TagInput.vue';

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
const form = ref({
    title: '',
    description: '',
    tags: [] as string[],
});

watch(
    () => props.open,
    (val) => {
        if (val) {
            form.value = { title: '', description: '', tags: [] };
            selectedFile.value = null;
            uploadProgress.value = 0;
            errors.value = {};
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
    if (!selectedFile.value) {
        errors.value = { file: 'Please select a file to upload.' };
        return;
    }

    errors.value = {};
    isSubmitting.value = true;
    uploadProgress.value = 0;

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    formData.append('title', form.value.title);
    formData.append('resource_folder_id', String(props.folderId));
    if (form.value.description) {
        formData.append('description', form.value.description);
    }
    form.value.tags.forEach((tag, i) => {
        formData.append(`tags[${i}]`, tag);
    });

    try {
        await axios.post('/resources/files', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (progressEvent) => {
                if (progressEvent.total) {
                    uploadProgress.value = Math.round(
                        (progressEvent.loaded * 100) / progressEvent.total,
                    );
                }
            },
        });
        toast.success('File uploaded successfully');
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
                error.response?.data?.message || 'Failed to upload file',
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
                    Upload File
                </SheetTitle>
                <SheetDescription>
                    Upload a video or PDF file to this folder.
                </SheetDescription>
            </SheetHeader>

            <form
                class="mt-6 space-y-6 px-6 py-4"
                @submit.prevent="handleSubmit"
            >
                <!-- File Input -->
                <div class="space-y-2">
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

                <!-- Upload Progress -->
                <div
                    v-if="isSubmitting && uploadProgress > 0"
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
                        Upload
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
